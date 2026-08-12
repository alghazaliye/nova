import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'chats_screen.dart';

/// شاشة الأجهزة المتصلة — قائمة حقيقية من API مع التحكم بالباركود/الويب
class WebLoginScreen extends StatefulWidget {
  const WebLoginScreen({super.key});

  @override
  State<WebLoginScreen> createState() => _WebLoginScreenState();
}

class _WebLoginScreenState extends State<WebLoginScreen> {
  List<Map<String, dynamic>> _devices = [];
  bool _loading = true;
  int _maxDevices = 1;

  @override
  void initState() {
    super.initState();
    _loadDevices();
  }

  Future<void> _loadDevices() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.get('/devices');
      if (res['success'] == true) {
        final data = Map<String, dynamic>.from(res['data'] ?? {});
        setState(() {
          _devices = (data['devices'] is List)
              ? List<Map<String, dynamic>>.from(
                  (data['devices'] as List).map((e) => Map<String, dynamic>.from(e)))
              : [];
          _maxDevices = int.parse((data['max_devices'] ?? 1).toString());
        });
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _toggleDevice(Map<String, dynamic> dev) async {
    final id = dev['id'];
    final isActive = (dev['is_active'] ?? 0) == 1;
    try {
      final res = await ApiService.post('/devices/$id/toggle');
      if (!mounted) return;
      if (res['success'] == true) {
        showToast(context, isActive ? 'تم إلغاء ارتباط الجهاز' : 'تم تفعيل الجهاز');
        await _loadDevices();
      } else {
        showToast(context, res['message'] ?? 'تعذر تنفيذ العملية');
      }
    } catch (_) {}
  }

  static const _osIcon = {
    'web': Icons.computer,
    'android': Icons.android,
    'ios': Icons.phone_iphone,
  };

  String _timeAgo(String? at) {
    if (at == null || at.isEmpty) return 'غير معروف';
    try {
      final dt = DateTime.parse(at);
      final diff = DateTime.now().difference(dt);
      if (diff.inHours > 24) return 'منذ ${diff.inDays} يوم';
      if (diff.inMinutes > 60) return 'منذ ${diff.inHours} ساعة';
      return 'منذ ${diff.inMinutes} دقيقة';
    } catch (_) {
      return '';
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final me = context.watch<AuthProvider>().user;
    final activeCount = _devices.where((d) => (d['is_active'] ?? 0) == 1).length;
    final isNearLimit = activeCount >= _maxDevices;

    return Scaffold(
      appBar: AppBar(title: const Text('الأجهزة المتصلة')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadDevices,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // بطاقة الباقة وحد الأجهزة
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: isNearLimit
                          ? Colors.red.withOpacity(.08)
                          : const Color(0xFFB7791F).withOpacity(.1),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(
                          color: isNearLimit
                              ? Colors.red.withOpacity(.35)
                              : const Color(0xFFB7791F).withOpacity(.35)),
                    ),
                    child: Row(
                      children: [
                        Icon(isNearLimit ? Icons.warning_amber_rounded : Icons.devices,
                            color: isNearLimit ? Colors.red : const Color(0xFF975A16)),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('الأجهزة المتصلة',
                                  style: TextStyle(fontWeight: FontWeight.w700,
                                      color: isNearLimit ? Colors.red : const Color(0xFF975A16))),
                              const SizedBox(height: 4),
                              Text('$activeCount من $_maxDevices جهاز مسموح',
                                  style: TextStyle(fontSize: 13, color: c.muted)),
                              if (isNearLimit)
                                const Padding(
                                  padding: EdgeInsets.only(top: 6),
                                  child: Text(
                                    'وصلت للحد الأقصى — ألغِ ارتباط جهاز لاستقبال جهاز جديد',
                                    style: TextStyle(fontSize: 12.5, color: Colors.red),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  // قائمة الأجهزة
                  ..._devices.map((dev) {
                    final isActive = (dev['is_active'] ?? 0) == 1;
                    final isCurrent = (dev['is_current'] ?? 0) == 1;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: c.surface2,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: c.line),
                      ),
                      child: Row(
                        children: [
                          CircleAvatar(
                            radius: 20,
                            backgroundColor: c.accent.withOpacity(.15),
                            child: Icon(_osIcon[dev['platform']] ?? Icons.device_unknown,
                                color: c.accent),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(children: [
                                  Expanded(
                                    child: Text(dev['device_model'] ?? 'جهاز',
                                        style: const TextStyle(
                                            fontSize: 14.5, fontWeight: FontWeight.w700)),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: isActive
                                          ? Colors.green.withOpacity(.12)
                                          : Colors.grey.withOpacity(.12),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text(isActive ? 'متصل' : 'غير متصل',
                                        style: TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.w700,
                                            color: isActive ? Colors.green.shade700 : Colors.grey.shade600)),
                                  ),
                                  if (isCurrent)
                                    Container(
                                      margin: const EdgeInsets.only(right: 6),
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: c.accent.withOpacity(.12),
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: const Text('هذا الجهاز',
                                          style: TextStyle(
                                              fontSize: 11,
                                              fontWeight: FontWeight.w700,
                                              color: Color(0xFF00A884))),
                                    ),
                                ]),
                                const SizedBox(height: 3),
                                Text('${dev['os_name'] ?? ''} ${dev['os_version'] ?? ''} • ${_timeAgo(dev['last_seen'])}',
                                    style: TextStyle(fontSize: 12, color: c.muted)),
                              ],
                            ),
                          ),
                          if (!isCurrent)
                            IconButton(
                              icon: Icon(isActive ? Icons.link_off : Icons.link,
                                  color: isActive ? Colors.redAccent : Colors.green.shade700),
                              onPressed: () => _toggleDevice(dev),
                            ),
                        ],
                      ),
                    );
                  }),
                  if (_devices.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 40),
                      child: Center(
                        child: Column(children: [
                          Icon(Icons.devices_other, size: 48, color: c.muted.withOpacity(.5)),
                          const SizedBox(height: 10),
                          Text('لا توجد أجهزة متصلة',
                              style: TextStyle(color: c.muted)),
                        ]),
                      ),
                    ),
                  const SizedBox(height: 20),
                  OutlinedButton(
                    onPressed: () {
                      Navigator.pushReplacement(context,
                          MaterialPageRoute(builder: (_) => const ChatsScreen()));
                    },
                    child: const Text('العودة للمحادثات')),
                ],
              ),
            ),
    );
  }
}
