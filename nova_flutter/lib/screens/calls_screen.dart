import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
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
      // إعادة استخدام سجل المكالمة إذا كان نشطًا
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
    final auth = context.watch<AuthProvider>();
    final me = auth.user;
    return Scaffold(
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _calls.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.call_missed_outlined,
                          size: 72, color: Colors.grey.shade400),
                      const SizedBox(height: 12),
                      const Text('لا توجد مكالمات بعد',
                          style:
                              TextStyle(fontSize: 16, color: Colors.black54)),
                      const SizedBox(height: 8),
                      const Text('استخدم الزر لبدء مكالمة صوتية أو فيديو',
                          style:
                              TextStyle(fontSize: 13, color: Colors.black45)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    itemCount: _calls.length,
                    itemBuilder: (_, i) {
                      final call = _calls[i];
                      final incoming =
                          call['caller_id'].toString() != (me?.id ?? -1).toString();
                      final isVideo =
                          (call['call_type'] ?? '').toString() == 'video';
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundColor:
                              Theme.of(context).colorScheme.primaryContainer,
                          child: Icon(isVideo ? Icons.videocam : Icons.call,
                              color: Theme.of(context).colorScheme.primary),
                        ),
                        title: Text(incoming
                            ? 'مكالمة ${call['caller_name'] ?? 'واردة'}'
                            : 'مكالمة إلى ${call['receiver_name'] ?? 'صادر'}'),
                        subtitle: Text(
                            '${_statusLabel(call)} • ${(call['created_at'] ?? '').toString().length >= 16 ? (call['created_at'] ?? '').toString().substring(0, 16) : (call['created_at'] ?? '')}'),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (call['status'] == 'missed')
                              const Icon(Icons.call_missed,
                                  color: Colors.red, size: 20),
                            const SizedBox(width: 4),
                            IconButton(
                              icon: const Icon(Icons.call, color: Colors.green),
                              tooltip: 'اتصال',
                              onPressed: () => _showCallMenu(call),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
      floatingActionButton: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          FloatingActionButton.small(
            backgroundColor: Colors.blue,
            heroTag: 'video',
            onPressed: () => _startCall('video'),
            child: const Icon(Icons.videocam, color: Colors.white),
          ),
          const SizedBox(height: 12),
          FloatingActionButton(
            backgroundColor: Colors.green,
            heroTag: 'voice',
            onPressed: () => _startCall('voice'),
            child: const Icon(Icons.call, color: Colors.white),
          ),
        ],
      ),
    );
  }
}
