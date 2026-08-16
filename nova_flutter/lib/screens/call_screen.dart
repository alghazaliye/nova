import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';

/// شاشة المكالمة الصوتية/الفيديو — تستخدم polling للتواصل مع الخادم
///
/// الحالة الصحيحة للواجهة:
/// - ringing → "يتصل..." (أو "المتصل يرن...") مع مؤشر تحميل فقط
/// - answered/accepted → واجهة المكالمة: عداد مدة + أزرار (كتم/فيديو/إنهاء)
/// - ended/rejected/missed → إغلاق الشاشة تلقائيًا
class CallScreen extends StatefulWidget {
  final Map<String, dynamic> callData;
  const CallScreen({super.key, required this.callData});

  @override
  State<CallScreen> createState() => _CallScreenState();
}

class _CallScreenState extends State<CallScreen> {
  String _status = 'ringing';
  String _statusText = 'يتصل...';
  Timer? _timer;
  Timer? _durationTimer;
  bool _answered = false;
  bool _muted = false;
  bool _videoPaused = false;
  DateTime? _startedAt;

  /// الحالة النهائية التي تُنهي الشاشة
  static const _terminalStatuses = {
    'ended',
    'rejected',
    'missed',
    'failed',
    'cancelled',
  };

  @override
  void initState() {
    super.initState();
    _pollNow();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _durationTimer?.cancel();
    // إنهاء المكالمة في الخلفية فقط إذا كانت لا تزال نشطة
    if (!_answered) {
      _endCall().catchError((_) {});
    }
    super.dispose();
  }

  void _startDurationCounter() {
    if (_durationTimer != null) return;
    _startedAt = DateTime.now();
    _durationTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() {});
    });
  }

  String _formatDuration() {
    if (_startedAt == null) return '00:00';
    final d = DateTime.now().difference(_startedAt!);
    final m = d.inMinutes.toString().padLeft(2, '0');
    final s = (d.inSeconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  /// فحص الحالة فورًا ثم كل ثانيتين
  void _poll() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 2), (_) => _pollNow());
  }

  Future<void> _pollNow() async {
    final callId = widget.callData['id']?.toString();
    if (callId == null || callId.isEmpty || callId == 'null') return;
    try {
      final res = await ApiService.get('/calls/$callId');
      if (!mounted || res['success'] != true) return;
      final data = res['data'] as Map<String, dynamic>? ?? {};
      final status = (data['status'] ?? '').toString();
      if (status.isEmpty) return;
      // تجاهل التحديثات بعد الانتهاء
      if (_answered && _terminalStatuses.contains(status)) {
        _closeScreen('انتهت المكالمة');
        return;
      }
      if (_status != status) {
        setState(() {
          _status = status;
          _answered = (status == 'answered' || status == 'accepted');
          if (_answered) {
            _statusText = 'المكالمة نشطة';
            _startDurationCounter();
          } else if (status == 'ringing') {
            _statusText = 'يتصل...';
          } else if (status == 'rejected' ||
              status == 'missed' ||
              status == 'cancelled') {
            _statusText = 'لم يتم الرد';
          } else if (status == 'ended') {
            _statusText = 'انتهت المكالمة';
          } else {
            _statusText = 'يتصل...';
          }
        });
      }
      if (_terminalStatuses.contains(status)) {
        _closeScreen(_statusText);
      }
    } catch (_) {}
  }

  void _closeScreen(String message) {
    _timer?.cancel();
    _durationTimer?.cancel();
    if (mounted) {
      if (ModalRoute.of(context)?.isCurrent == true) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(message)));
        Navigator.pop(context);
      }
    }
  }

  Future<void> _endCall() async {
    final callId = widget.callData['id']?.toString();
    if (callId == null || callId.isEmpty || callId == 'null') return;
    try {
      await ApiService.post('/calls/$callId/end');
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final type = widget.callData['call_type'] ?? 'voice';
    final isVideo = type == 'video';
    final peerName = widget.callData['peer_name'] ??
        widget.callData['caller_name'] ??
        '';
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 110,
                height: 110,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: Colors.white.withOpacity(0.08),
                ),
                child: Icon(
                  isVideo
                      ? Icons.videocam_rounded
                      : (_muted ? Icons.mic_off : Icons.call),
                  color: Colors.white,
                  size: 54,
                ),
              ),
              const SizedBox(height: 22),
              Text(
                peerName.isNotEmpty ? peerName : 'مكالمة',
                style: const TextStyle(
                    color: Colors.white, fontSize: 24, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 10),
              // أثناء الرنين لا نعرض "بانتظار الطرف الآخر" كحالة معلّقة؛
              // فقط مؤشر اتصال بسيط، وعند القبول تظهر واجهة المكالمة
              _answered
                  ? Text(_formatDuration(),
                      style: TextStyle(
                          color: Colors.white.withOpacity(0.85),
                          fontSize: 20,
                          fontFeatures: const [FontFeature.tabularFigures()]))
                  : Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(
                                color: Colors.white, strokeWidth: 2)),
                        const SizedBox(width: 10),
                        Text(_statusText,
                            style: const TextStyle(
                                color: Colors.white, fontSize: 17)),
                      ],
                    ),
              if (isVideo) ...[
                const SizedBox(height: 8),
                Text(_answered ? '' : 'مكالمة فيديو',
                    style: TextStyle(color: Colors.white54, fontSize: 14)),
              ],
              const SizedBox(height: 56),
              if (_answered)
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    // كتم الصوت
                    _CtrlCircle(
                      active: _muted,
                      icon: _muted ? Icons.mic_off : Icons.mic,
                      label: 'كتم',
                      onTap: () => setState(() => _muted = !_muted),
                    ),
                    const SizedBox(width: 28),
                    // إيقاف/تشغيل الفيديو
                    _CtrlCircle(
                      active: _videoPaused,
                      icon: _videoPaused ? Icons.videocam_off : Icons.videocam,
                      label: 'فيديو',
                      onTap: () =>
                          setState(() => _videoPaused = !_videoPaused),
                    ),
                    const SizedBox(width: 28),
                    // مكبر الصوت
                    _CtrlCircle(
                      active: false,
                      icon: Icons.volume_up,
                      label: 'مكبر',
                      onTap: () {},
                    ),
                  ],
                ),
              const SizedBox(height: 72),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircleAvatar(
                    radius: 32,
                    backgroundColor: Colors.red.shade600,
                    child: IconButton(
                      iconSize: 30,
                      onPressed: () {
                        _endCall().then((_) {
                          if (mounted) Navigator.pop(context);
                        });
                      },
                      icon: const Icon(Icons.call_end, color: Colors.white),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
            ],
          ),
        ),
      ),
    );
  }
}

/// زر تحكم دائري داخل شاشة المكالمة
class _CtrlCircle extends StatelessWidget {
  final bool active;
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _CtrlCircle({
    required this.active,
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        GestureDetector(
          onTap: onTap,
          child: CircleAvatar(
            radius: 28,
            backgroundColor: active
                ? Colors.white.withOpacity(0.9)
                : Colors.white.withOpacity(0.12),
            child: Icon(icon,
                color: active ? Colors.black : Colors.white, size: 26),
          ),
        ),
        const SizedBox(height: 8),
        Text(label,
            style: TextStyle(
                color: Colors.white.withOpacity(0.7), fontSize: 12)),
      ],
    );
  }
}
