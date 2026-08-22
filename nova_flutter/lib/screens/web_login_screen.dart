import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'dart:async';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import '../providers/auth_provider.dart';
import 'package:provider/provider.dart';
import 'chats_screen.dart';

class WebLoginScreen extends StatefulWidget {
  const WebLoginScreen({super.key});

  @override
  State<WebLoginScreen> createState() => _WebLoginScreenState();
}

class _WebLoginScreenState extends State<WebLoginScreen> {
  List<Map<String, dynamic>> _devices = [];
  bool _loading = true;
  int _maxDevices = 1;
  String? _linkUuid;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _loadDevices();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadDevices() async {
    if (!mounted) return;
    setState(() => _loading = true);
    try {
      final res = await ApiService.get('/devices');
      if (!mounted) return;
      if (res['status_code'] == 401) return;
      
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

  Future<void> _initLinkSession() async {
    try {
      final res = await ApiService.post('/devices/link/init', body: {
        'device_name': 'Web Browser',
        'platform': 'web'
      });
      if (res['success'] == true) {
        setState(() => _linkUuid = res['data']['session_uuid']);
        _pollTimer?.cancel();
        _pollTimer = Timer.periodic(const Duration(seconds: 3), (timer) => _pollStatus());
      }
    } catch (_) {}
  }

  Future<void> _pollStatus() async {
    if (_linkUuid == null) return;
    try {
      final res = await ApiService.get('/devices/link/$_linkUuid');
      if (res['status_code'] == 401) {
        _pollTimer?.cancel();
        return;
      }
      if (res['success'] == true && res['data']['status'] == 'completed') {
        _pollTimer?.cancel();
        final token = res['data']['token'];
        if (mounted) {
          await context.read<AuthProvider>().loginWithToken(token);
          Navigator.pushAndRemoveUntil(
            context,
            MaterialPageRoute(builder: (_) => const ChatsScreen()),
            (route) => false,
          );
        }
      }
    } catch (_) {}
  }

  Future<void> _toggleDevice(Map<String, dynamic> dev) async {
    final id = dev['id'];
    try {
      final res = await ApiService.post('/devices/$id/toggle');
      if (!mounted) return;
      if (res['success'] == true) {
        showToast(context, 'تم تحديث حالة الجهاز');
        await _loadDevices();
      } else {
        showToast(context, res['message'] ?? 'تعذر تنفيذ العملية');
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final activeCount = _devices.where((d) => (d['is_active'] ?? 0) == 1).length;
    final isNearLimit = activeCount >= _maxDevices;

    return Scaffold(
      appBar: AppBar(title: const Text('الأجهزة المتصلة')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  if (_linkUuid != null)
                    Card(
                      elevation: 4,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                      child: Padding(
                        padding: const EdgeInsets.all(20),
                        child: Column(
                          children: [
                            const Text('امسح الرمز من جهازك الأساسي',
                                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                            const SizedBox(height: 15),
                            QrImageView(
                              data: _linkUuid!,
                              version: QrVersions.auto,
                              size: 200.0,
                              eyeStyle: QrEyeStyle(eyeShape: QrEyeShape.circle, color: c.accent),
                              dataModuleStyle: QrDataModuleStyle(
                                  dataModuleShape: QrDataModuleShape.circle, color: c.accent),
                            ),
                            const SizedBox(height: 10),
                            const Text('تنتهي صلاحية الرمز خلال 5 دقائق',
                                style: TextStyle(color: Colors.grey, fontSize: 12)),
                          ],
                        ),
                      ),
                    )
                  else
                    ElevatedButton.icon(
                      onPressed: _initLinkSession,
                      icon: const Icon(Icons.qr_code),
                      label: const Text('ربط جهاز جديد عبر QR'),
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: isNearLimit ? Colors.red.withOpacity(.08) : c.accent.withOpacity(.1),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: isNearLimit ? Colors.red.withOpacity(.3) : c.accent.withOpacity(.3)),
                    ),
                    child: Row(
                      children: [
                        Icon(isNearLimit ? Icons.warning : Icons.devices,
                            color: isNearLimit ? Colors.red : c.accent),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('حالة الأجهزة',
                                  style: TextStyle(fontWeight: FontWeight.bold,
                                      color: isNearLimit ? Colors.red : c.accent)),
                              Text('$activeCount من $_maxDevices جهاز نشط',
                                  style: TextStyle(fontSize: 13, color: c.muted)),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  ..._devices.map((dev) => _buildDeviceItem(dev, c)),
                ],
              ),
            ),
    );
  }

  Widget _buildDeviceItem(Map<String, dynamic> dev, NovaColors c) {
    final isActive = (dev['is_active'] ?? 0) == 1;
    final isCurrent = (dev['is_current'] ?? 0) == 1;
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: c.surface2,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: c.line),
      ),
      child: Row(
        children: [
          Icon(dev['platform'] == 'web' ? Icons.computer : Icons.smartphone, color: c.accent),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(dev['device_name'] ?? 'جهاز غير معروف',
                    style: const TextStyle(fontWeight: FontWeight.bold)),
                Text(dev['os'] ?? 'نظام غير معروف', style: TextStyle(fontSize: 12, color: c.muted)),
              ],
            ),
          ),
          if (!isCurrent)
            Switch(
              value: isActive,
              onChanged: (_) => _toggleDevice(dev),
              activeColor: c.accent,
            )
          else
            const Chip(label: Text('هذا الجهاز', style: TextStyle(fontSize: 10))),
        ],
      ),
    );
  }
}
