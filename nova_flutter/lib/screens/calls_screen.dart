import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'call_screen.dart';

/// تبويب المكالمات: قائمة المكالمات السابقة + بدء مكالمة جديدة (صوت/فيديو)
class CallsScreen extends StatefulWidget {
  const CallsScreen({super.key});

  @override
  State<CallsScreen> createState() => _CallsScreenState();
}

class _CallsScreenState extends State<CallsScreen> {
  List<Map<String, dynamic>> _calls = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.get('/calls');
      if (res['success'] == true && res['data'] is List) {
        setState(() {
          _calls = List<Map<String, dynamic>>.from(res['data'] as List);
        });
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _startCall(String type) async {
    final ctrl = TextEditingController();
    final phone = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('مكالمة جديدة'),
        content: TextField(
          controller: ctrl,
          decoration: const InputDecoration(
              hintText: '+966599995002', labelText: 'رقم هاتف الطرف الآخر'),
          keyboardType: TextInputType.phone,
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, null),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, ctrl.text.trim()),
              child: const Text('اتصال')),
        ],
      ),
    );
    if (phone == null || phone.isEmpty || !mounted) return;
    final res = await ApiService.post('/calls', body: {
      'contact_phone': phone,
      'call_type': type,
    });
    if (!mounted) return;
    if (res['success'] == true && res['data'] != null) {
      Navigator.push(
          context,
          MaterialPageRoute(
              builder: (_) => CallScreen(callData: Map<String, dynamic>.from(
                  res['data'] as Map<String, dynamic>))));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res['message'] ?? 'فشل بدء المكالمة')));
    }
  }

  void _showCallMenu(Map<String, dynamic> call) {
    final id = call['id']?.toString();
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (c) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.call, color: Colors.green),
              title: const Text('مكالمة صوتية'),
              onTap: () {
                Navigator.pop(c);
                _dialFromCall(id, call, 'voice');
              },
            ),
            ListTile(
              leading: const Icon(Icons.videocam, color: Colors.blue),
              title: const Text('مكالمة فيديو'),
              onTap: () {
                Navigator.pop(c);
                _dialFromCall(id, call, 'video');
              },
            ),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.delete, color: Colors.red),
              title: const Text('حذف السجل'),
              onTap: () async {
                Navigator.pop(c);
                if (id != null) {
                  await ApiService.delete('/calls/$id').catchError((_) => {'success': false});
                  _load();
                }
              },
            ),
          ],
        ),
      ),
    );
  }

  void _dialFromCall(String? id, Map<String, dynamic> call, String type) async {
    if (id != null) {
      Navigator.push(
          context,
          MaterialPageRoute(
              builder: (_) => CallScreen(callData: Map<String, dynamic>.from({
                    ...call,
                    'call_type': type,
                  }))));
    } else {
      _startCall(type);
    }
  }

  String _statusLabel(Map<String, dynamic> call) {
    switch ((call['status'] ?? '').toString()) {
      case 'accepted':
        return 'مقبولة';
      case 'rejected':
        return 'مرفوضة';
      case 'ended':
        return 'منتهية';
      case 'missed':
        return 'فائتة';
      case 'ringing':
        return 'تُرنّ...';
      default:
        return 'جارية';
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    final me = auth.user;
    return Scaffold(
      body: Column(
        children: [
          // شريط العنوان
          Container(
            color: c.surface,
            child: SafeArea(
              bottom: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(18, 13, 18, 13),
                decoration: BoxDecoration(
                    border: Border(bottom: BorderSide(color: c.line))),
                child: Row(
                  children: [
                    Expanded(
                      child: Text('المكالمات',
                          style: TextStyle(
                              fontSize: 22,
                              fontWeight: FontWeight.w800,
                              color: c.text)),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _calls.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.call_missed_outlined,
                                size: 72, color: c.muted.withOpacity(0.45)),
                            const SizedBox(height: 12),
                            Text('لا توجد مكالمات بعد',
                                style: TextStyle(fontSize: 16, color: c.muted)),
                            const SizedBox(height: 8),
                            Text('استخدم الزر لبدء مكالمة صوتية أو فيديو',
                                style: TextStyle(fontSize: 13, color: c.muted)),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 160),
                          itemCount: _calls.length,
                          itemBuilder: (_, i) {
                            final call = _calls[i];
                            final incoming =
                                call['caller_id'].toString() != (me?.id ?? -1).toString();
                            final isVideo =
                                (call['call_type'] ?? '').toString() == 'video';
                            final missed = call['status'] == 'missed';
                            return PressScale(
                              onTap: () => _showCallMenu(call),
                              child: Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: NovaCard(
                                  padding: const EdgeInsets.all(12),
                                  child: Row(
                                    children: [
                                      Container(
                                        width: 50,
                                        height: 50,
                                        decoration: BoxDecoration(
                                          color: c.surface2,
                                          borderRadius: BorderRadius.circular(15),
                                        ),
                                        child: Icon(
                                            isVideo ? Icons.videocam : Icons.call,
                                            color: missed
                                                ? Colors.redAccent
                                                : incoming
                                                    ? Colors.green
                                                    : c.accent,
                                            size: 22),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                                incoming
                                                    ? 'مكالمة ${call['caller_name'] ?? 'واردة'}'
                                                    : 'مكالمة إلى ${call['receiver_name'] ?? 'صادر'}',
                                                style: TextStyle(
                                                    fontSize: 15,
                                                    fontWeight: FontWeight.w700,
                                                    color: missed
                                                        ? Colors.redAccent
                                                        : c.text)),
                                            const SizedBox(height: 4),
                                            Text(
                                                '${_statusLabel(call)} • ${(call['created_at'] ?? '').toString().length >= 16 ? (call['created_at'] ?? '').toString().substring(0, 16) : (call['created_at'] ?? '')}',
                                                style: TextStyle(
                                                    fontSize: 13, color: c.muted)),
                                          ],
                                        ),
                                      ),
                                      IconBtn(
                                          icon: Icons.call,
                                          color: c.accent,
                                          onTap: () => _showCallMenu(call)),
                                    ],
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
      floatingActionButton: NovaCard(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconBtn(
              icon: Icons.videocam,
              color: c.accent,
              onTap: () => _startCall('video'),
            ),
            const SizedBox(width: 6),
            IconBtn(
              icon: Icons.call,
              color: c.accent,
              onTap: () => _startCall('voice'),
            ),
          ],
        ),
      ),
    );
  }
}
