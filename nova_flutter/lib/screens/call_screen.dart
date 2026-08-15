import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api_service.dart';

/// شاشة المكالمة الصوتية/الفيديو — تستخدم polling للتواصل مع الخادم
class CallScreen extends StatefulWidget {
  final Map<String, dynamic> callData;
  const CallScreen({super.key, required this.callData});

  @override
  State<CallScreen> createState() => _CallScreenState();
}

class _CallScreenState extends State<CallScreen> {
  String _status = 'يتصل...';
  Timer? _timer;
  bool _answered = false;

  @override
  void initState() {
    super.initState();
    _poll();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _endCall().catchError((e) => 0);
    super.dispose();
  }

  Future<void> _poll() async {
    final callId = widget.callData['id'];
    _timer = Timer.periodic(const Duration(seconds: 3), (_) async {
      try {
        final res = await ApiService.get('/calls/$callId');
        if (res['success'] != true) return;
        final data = res['data'] as Map<String, dynamic>? ?? {};
        final status = data['status'] ?? 'calling';
        setState(() => _status = status);
        if (status == 'accepted') setState(() => _answered = true);
        if (status == 'ended' || status == 'rejected' || status == 'missed') {
          _timer?.cancel();
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('انتهت المكالمة: $status')));
            Navigator.pop(context);
          }
        }
      } catch (_) {}
    });
  }

  Future<void> _endCall() async {
    final callId = widget.callData['id'];
    try { await ApiService.post('/calls/$callId/end'); } catch (e) {}
  }

  @override
  Widget build(BuildContext context) {
    final type = widget.callData['call_type'] ?? 'voice';
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                type == 'video' ? Icons.videocam : Icons.call,
                color: Colors.white,
                size: 64,
              ),
              const SizedBox(height: 20),
              Text(_status,
                  style: const TextStyle(color: Colors.white, fontSize: 22)),
              const SizedBox(height: 8),
              Text('مكالمة ${type == 'video' ? 'فيديو' : 'صوتية'}',
                  style: TextStyle(color: Colors.white70, fontSize: 15)),
              const SizedBox(height: 48),
              if (!_answered)
                const CircularProgressIndicator(color: Colors.white),
              const SizedBox(height: 60),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: Colors.red,
                    child: IconButton(
                        onPressed: () {
                          _endCall();
                          Navigator.pop(context);
                        },
                        icon: const Icon(Icons.call_end, color: Colors.white)),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
