import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'call_screen.dart';

/// تبويب المكالمات — تصميم القالب مع chips + بطاقات + أزرار الاتصال
class CallsScreen extends StatefulWidget {
  const CallsScreen({super.key});

  @override
  State<CallsScreen> createState() => _CallsScreenState();
}

class _CallsScreenState extends State<CallsScreen> {
  List<Map<String, dynamic>> _calls = [];
  bool _loading = true;
  String _chip = 'الكل';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (ApiService.token == null) return;
    setState(() => _loading = true);
    try {
      final res = await ApiService.get('/calls');
      if (res['status_code'] == 401) {
        // AuthProvider handles global redirect, just stop loading
        if (mounted) setState(() => _loading = false);
        return;
      }
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
      showToast(context, res['message'] ?? 'فشل بدء المكالمة');
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
    final me = context.read<AuthProvider>().user;
    final incoming = call['caller_id'].toString() != (me?.id ?? -1).toString();
    final targetId = incoming ? call['caller_id'] : call['callee_id'];
    
    if (targetId != null) {
      final res = await ApiService.post('/calls', body: {
        'callee_id': targetId,
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
        showToast(context, res['message'] ?? 'فشل بدء المكالمة');
      }
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

  List<Map<String, dynamic>> get _filtered {
    switch (_chip) {
      case 'الفائتة':
        return _calls.where((c) => c['status'] == 'missed').toList();
      case 'الواردة':
        return _calls.where((c) => c['caller_id'].toString() != (context.read<AuthProvider>().user?.id ?? -1).toString()).toList();
      default:
        return _calls;
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    final me = auth.user;
    return Column(
      children: [
        novaTopBar(context,
            title: 'المكالمات',
            actions: [
              PressScale(
                onTap: () => _startCall('video'),
                child: Container(
                  decoration: BoxDecoration(color: c.accent, borderRadius: BorderRadius.circular(13)),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                  child: const Row(
                    children: [
                      Icon(Icons.videocam, size: 17, color: Colors.white),
                      SizedBox(width: 5),
                      Text('فيديو', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                    ],
                  ),
                ),
              ),
              PressScale(
                onTap: () => _startCall('voice'),
                child: Container(
                  decoration: BoxDecoration(color: c.green, borderRadius: BorderRadius.circular(13)),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
                  child: const Row(
                    children: [
                      Icon(Icons.call, size: 17, color: Colors.white),
                      SizedBox(width: 5),
                      Text('صوت', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                    ],
                  ),
                ),
              ),
            ]),
        // chips
        Container(
          color: c.surface,
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
          child: Row(
            children: [
              TabChip(label: 'الكل', active: _chip == 'الكل', onTap: () => setState(() => _chip = 'الكل')),
              const SizedBox(width: 8),
              TabChip(label: 'الفائتة', active: _chip == 'الفائتة', onTap: () => setState(() => _chip = 'الفائتة')),
              const SizedBox(width: 8),
              TabChip(label: 'الواردة', active: _chip == 'الواردة', onTap: () => setState(() => _chip = 'الواردة')),
            ],
          ),
        ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _filtered.isEmpty
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
                          Text('استخدم الأزرار لبدء مكالمة صوتية أو فيديو',
                              style: TextStyle(fontSize: 13, color: c.muted)),
                        ],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 160),
                        itemCount: _filtered.length,
                        itemBuilder: (_, i) {
                          final call = _filtered[i];
                          final incoming =
                              call['caller_id'].toString() != (me?.id ?? -1).toString();
                          final isVideo =
                              (call['call_type'] ?? '').toString() == 'video';
                          final missed = call['status'] == 'missed';
                          final label = incoming
                              ? 'مكالمة ${call['caller_name'] ?? 'واردة'}'
                              : 'مكالمة إلى ${call['peer_name'] ?? 'صادر'}';
                          return PressScale(
                            onTap: () => _showCallMenu(call),
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: NovaCard(
                                padding: const EdgeInsets.all(12),
                                child: Row(
                                  children: [
                                    Container(
                                      width: 54,
                                      height: 54,
                                      decoration: BoxDecoration(
                                        color: c.surface2,
                                        borderRadius: BorderRadius.circular(17),
                                      ),
                                      child: Icon(
                                          isVideo ? Icons.videocam : Icons.call,
                                          color: missed
                                              ? Colors.redAccent
                                              : incoming
                                                  ? Colors.green
                                                  : c.accent,
                                          size: 23),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(label,
                                              style: TextStyle(
                                                  fontSize: 15.5,
                                                  fontWeight: FontWeight.w700,
                                                  color: missed ? Colors.redAccent : c.text)),
                                          const SizedBox(height: 4),
                                          Text(
                                              '${_statusLabel(call)} • ${novaServerTime(call['created_at'] ?? '', auth.timezoneOffsetMinutes)}',
                                              style: TextStyle(fontSize: 13, color: c.muted)),
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
    );
  }
}
