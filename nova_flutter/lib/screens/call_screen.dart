import 'dart:async';
import "package:flutter_webrtc/flutter_webrtc.dart";
import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/call_service.dart';
import '../utils/nova_ui.dart';

/// شاشة المكالمة الصوتية/المرئية — WebRTC حقيقي مع polling للتوصيل
///
/// - المتصل: يبدأ WebRTC + عرض الفيديو، ويظل يعرض "يتصل..." حتى يقبل الطرف الآخر
/// - الطرف الآخر: عند قبول المكالمة يُولَّد answer ويُعرض الفيديو الثنائي
/// - الحالة النهائية (ended/rejected/missed) → إغلاق الشاشة تلقائيًا
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
  bool _callAcceptedByPeer = false; // تم قبول الطرف الآخر للمكالمة (للبدء WebRTC)
  bool _webrtcStarted = false; // جلسة WebRTC بدأت (لعرض معاينة الفيديو المحلي)
  DateTime? _startedAt;
  CallService? _svc;

  /// هل هذه الشاشة للمتصل (caller) أم للمُستقبِل (callee)
  bool get _isCaller => widget.callData['caller_name'] != null &&
      (widget.callData['status']?.toString() ?? 'calling') != 'ringing' &&
      _isOutgoingFromData;

  late final bool _isOutgoingFromData;

  static const _terminalStatuses = {'ended', 'rejected', 'missed', 'failed', 'cancelled'};

  @override
  void initState() {
    super.initState();
    // تحديد الاتجاه: إذا كانت callData تحوي caller_name فهذا يعني أنها
    // مكالمة واردة (الطرف الآخر هو المتصل). outgoing عندنا لا يحتوي caller_name
    // عادة لكنه قد يحتوي caller_name أيضًا — نستخدم callUuid/peer:
    // الأبسط: outgoing = تم إنشاؤها من _startCall (نمرر caller_id = current user)
    _isOutgoingFromData =
        widget.callData['is_outgoing']?.toString() == 'true' ||
        widget.callData['caller_id']?.toString() ==
            ApiService.userId.toString();
    _pollNow();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _durationTimer?.cancel();
    _svc?.dispose().catchError((_) {});
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

  void _poll() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 2), (_) => _pollNow());
  }

  /// بدء جلسة WebRTC الحقيقية (كاميرا الطرفين)
  Future<void> _startWebRTC() async {
    final callId = widget.callData['id']?.toString();
    if (callId == null || callId.isEmpty || _svc != null) return;
    final svc = CallService();
    _svc = svc;
    try {
      await svc.init(
        int.parse(callId),
        _isOutgoingFromData,
        peerId: widget.callData['caller_id']?.toString() ??
            widget.callData['peer_id']?.toString(),
      );
      if (!mounted) return;
      _webrtcStarted = true;
      setState(() {});
      if (_isOutgoingFromData) {
        await svc.startCall();
      } else {
        await svc.answerCall();
      }
    } catch (e) {
      debugPrint('CallScreen: فشل بدء WebRTC: $e');
    }
  }

  Future<void> _pollNow() async {
    final callId = widget.callData['id']?.toString();
    if (callId == null || callId.isEmpty || callId == 'null') return;
    try {
      final res = await ApiService.get('/calls/$callId');
      if (!mounted) return;
      if (res['status_code'] == 401) {
        _timer?.cancel();
        return;
      }
      if (res['success'] != true) return;
      final data = res['data'] as Map<String, dynamic>? ?? {};
      final status = (data['status'] ?? '').toString();
      if (status.isEmpty) return;

      // عند قبول المكالمة (من أي طرف) نبدأ جلسة WebRTC
      final peerAccepted = (status == 'answered' || status == 'accepted');
      if (peerAccepted && !_callAcceptedByPeer && _svc == null) {
        _callAcceptedByPeer = true;
        _answered = true;
        _startWebRTC();
        if (mounted) setState(() {});
      }

      // المتصل يبدأ WebRTC فورًا (كاميرا + عرض فيديو محلي) ولا ينتظر القبول
      if (_isOutgoingFromData && _svc == null && _status == 'ringing') {
        _startWebRTC();
      }

      if (_answered && _terminalStatuses.contains(status)) {
        _closeScreen('انتهت المكالمة');
        return;
      }

      // إذا انتهت المكالمة قبل بدء WebRTC (رفض سريع) نظيف الشاشة
      if (_terminalStatuses.contains(status) && _svc == null) {
        _closeScreen('لم يتم الرد');
        return;
      }

      if (_status != status) {
        setState(() {
          _status = status;
          _answered = peerAccepted;
          if (_answered) {
            _statusText = 'المكالمة نشطة';
            _startDurationCounter();
          } else if (status == 'ringing') {
            _statusText = _isOutgoingFromData ? 'يتصل...' : 'مكالمة واردة';
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
    final c = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Stack(
          children: [
            // الفيديو البعيد (الطرف الآخر)
            if (_answered && isVideo && _svc != null)
              Positioned.fill(
                child: ValueListenableBuilder<RTCVideoRenderer>(
                  valueListenable: ValueNotifier(_svc!.remoteRenderer),
                  builder: (context, renderer, _) {
                    return RTCVideoView(
                      renderer,
                      objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitCover,
                      mirror: false,
                    );
                  },
                ),
              ),

            // الفيديو المحلي
            if (_webrtcStarted && isVideo && _svc != null)
              Positioned(
                top: _answered ? 20 : null,
                bottom: _answered ? null : 160,
                right: 20,
                width: 110,
                height: 150,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: Colors.white.withOpacity(0.5), width: 1.5),
                    ),
                    child: ValueListenableBuilder<RTCVideoRenderer>(
                      valueListenable: ValueNotifier(_svc!.localRenderer),
                      builder: (context, renderer, _) {
                        return RTCVideoView(
                          renderer,
                          objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitCover,
                          mirror: true,
                        );
                      },
                    ),
                  ),
                ),
              ),

            // أثناء الرنين: واجهة الاتصال (صورة + اسم + أزرار) — تُعرض فوق معاينة الفيديو المحلي
            if (!_answered)
              Center(
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
                        isVideo ? Icons.videocam_rounded : (_muted ? Icons.mic_off : Icons.call),
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
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const SizedBox(
                            width: 16,
                            height: 16,
                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)),
                        const SizedBox(width: 10),
                        Text(_statusText,
                            style: const TextStyle(color: Colors.white, fontSize: 17)),
                      ],
                    ),
                    if (isVideo) ...[
                      const SizedBox(height: 8),
                      Text('مكالمة فيديو',
                          style: TextStyle(color: Colors.white54, fontSize: 14)),
                    ],
                    const SizedBox(height: 56),
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

            // أزرار التحكم بعد قبول المكالمة
            if (_answered)
              Positioned(
                left: 0,
                right: 0,
                bottom: 40,
                child: Column(
                  children: [
                    if (_answered)
                      Text(_formatDuration(),
                          style: TextStyle(
                              color: Colors.white.withOpacity(0.9),
                              fontSize: 20,
                              fontFeatures: const [FontFeature.tabularFigures()])),
                    const SizedBox(height: 18),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _CtrlCircle(
                          active: _muted,
                          icon: _muted ? Icons.mic_off : Icons.mic,
                          label: 'كتم',
                          onTap: () {
                            setState(() => _muted = !_muted);
                            _svc?.setMicMuted(_muted);
                          },
                        ),
                        const SizedBox(width: 28),
                        if (isVideo)
                          _CtrlCircle(
                            active: _videoPaused,
                            icon: _videoPaused ? Icons.videocam_off : Icons.videocam,
                            label: 'فيديو',
                            onTap: () {
                              setState(() => _videoPaused = !_videoPaused);
                              _svc?.setVideoEnabled(!_videoPaused);
                            },
                          ),
                        if (isVideo) const SizedBox(width: 28),
                        if (isVideo)
                          _CtrlCircle(
                            active: false,
                            icon: Icons.flip_camera_ios,
                            label: 'تبديل',
                            onTap: () => _svc?.switchCamera(),
                          ),
                      ],
                    ),
                    const SizedBox(height: 40),
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
              ),
          ],
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
            backgroundColor:
                active ? Colors.white.withOpacity(0.9) : Colors.white.withOpacity(0.12),
            child: Icon(icon, color: active ? Colors.black : Colors.white, size: 26),
          ),
        ),
        const SizedBox(height: 8),
        Text(label,
            style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 12)),
      ],
    );
  }
}
