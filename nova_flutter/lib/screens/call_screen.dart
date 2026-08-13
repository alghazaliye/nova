import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import '../services/api_service.dart';
import '../services/call_service.dart';

/// شاشة المكالمة الصوتية/الفيديو — WebRTC حقيقي مع signaling عبر الخادم.
/// المتصل ينشئ Offer ويرسله، والمستدعى يستقبل الإشارات ويجيب بـ Answer.
class CallScreen extends StatefulWidget {
  final Map<String, dynamic> callData;
  const CallScreen({super.key, required this.callData});

  @override
  State<CallScreen> createState() => _CallScreenState();
}

class _CallScreenState extends State<CallScreen> {
  String _status = 'يتصل...';
  Timer? _timer;
  Timer? _signalTimer;
  bool _answered = false;
  bool _isCaller = false;
  bool _ready = false;
  bool _muted = false;
  bool _cameraOff = false;
  final RTCVideoRenderer _localRenderer = RTCVideoRenderer();
  final RTCVideoRenderer _remoteRenderer = RTCVideoRenderer();
  int _lastSignalId = 0;
  String? _lastSignalTime;

  int get _callId => widget.callData['id'] is int
      ? widget.callData['id'] as int
      : int.tryParse(widget.callData['id'].toString()) ?? 0;

  @override
  void initState() {
    super.initState();
    _initRenderers();
    _setup();
  }

  Future<void> _initRenderers() async {
    await _localRenderer.initialize();
    await _remoteRenderer.initialize();
  }

  Future<void> _setup() async {
    // تحديد هل أنا المتصل
    try {
      final meRes = await ApiService.get('/auth/me');
      if (meRes['success'] == true) {
        final meId = meRes['data']['id'];
        _isCaller = meId == widget.callData['caller_id'];
      } else {
        _isCaller = false;
      }
    } catch (_) {
      _isCaller = false;
    }

    final isVideo = (widget.callData['call_type'] ?? 'voice') == 'video';

    // بدء البث المحلي (كاميرا للفيديو، صوت فقط للاتصال الصوتي)
    try {
      await CallService.startLocalMedia(video: isVideo);
      setState(() => _ready = true);
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تعذّر الوصول للميكروفون/الكاميرا — تحقق من الأذونات')));
      }
    }

    // ربط مشغل الفيديو المحلي
    if (CallService.localStream != null && isVideo && mounted) {
      _localRenderer.srcObject = CallService.localStream;
    }
    CallService.onRemoteStream = (stream) {
      if (mounted) setState(() {
        _remoteRenderer.srcObject = stream;
        _answered = true;
        _status = 'المكالمة نشطة';
      });
    };
    CallService.onConnectionState = (state) {
      if (mounted && (_status == 'يتصل...' || _status == 'الرد...')) {
        setState(() => _status = state == 'connected' ? 'المكالمة نشطة' : state);
      }
    };

    CallService.attachCandidateSender(_callId);

    // قراءة أي إشارات وصلها قبل اكتمال الإعداد (مهمة للمستدعى)
    await _fetchSignals();

    if (_isCaller) {
      // المتصل: إنشاء Offer فور تجهيز الوسائط
      try {
        await CallService.createOffer(_callId);
        if (mounted) setState(() => _status = 'الرد...');
      } catch (_) {}
    }

    // polling للإشارات الواردة (offer/answer/candidate) كل ثانية
    _signalTimer = Timer.periodic(const Duration(seconds: 1), (_) => _fetchSignals());

    // polling لحالة المكالمة كل 3 ثوانٍ (للإنهاء/الرفض)
    _timer = Timer.periodic(const Duration(seconds: 3), (_) async {
      try {
        final res = await ApiService.get('/calls/$_callId');
        if (res['success'] != true) return;
        final data = res['data'] as Map<String, dynamic>? ?? {};
        final status = (data['status'] ?? '').toString();
        if (status == 'answered') {
          if (mounted) setState(() {
            _answered = true;
            _status = 'المكالمة نشطة';
          });
        }
        if (status == 'ended' || status == 'rejected' || status == 'missed') {
          _timer?.cancel();
          _signalTimer?.cancel();
          if (mounted) {
            Navigator.pop(context);
          }
        }
      } catch (_) {}
    });
  }

  Future<void> _fetchSignals() async {
    try {
      // استخدام since أوسع (قبل 10 ثوانٍ) + تصفية client-side لتجنب ضياع الإشارات
      // المتزامنة في نفس الثانية
      String? since = _lastSignalTime;
      final res = await ApiService.get('/calls/$_callId/signals', query: {
        'since': since ?? DateTime.now().subtract(const Duration(seconds: 10)).toString().replaceAll('T', ' ').split('.')[0],
      });
      if (res['success'] != true) return;
      final rows = (res['data'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
      for (final m in rows) {
        final sigId = (m['id'] is int) ? m['id'] as int : int.tryParse(m['id'].toString()) ?? 0;
        if (sigId <= _lastSignalId) continue;
        _lastSignalId = sigId;
        final ca = m['created_at']?.toString();
        if (ca != null && (_lastSignalTime == null || ca.compareTo(_lastSignalTime!) > 0)) {
          _lastSignalTime = ca;
        }
        if (!_isCaller && m['signal_type'] == 'offer') {
          // المستدعى يستقبل Offer — استخراج SDP من payload
          var payload = m['payload'];
          String sdp = '';
          if (payload is Map) {
            sdp = payload['sdp']?.toString() ?? payload['data']?['sdp']?.toString() ?? '';
          } else if (payload is String) {
            try {
              final p = jsonDecode(payload) as Map<String, dynamic>;
              sdp = p['sdp']?.toString() ?? p['data']?['sdp']?.toString() ?? '';
            } catch (_) {}
          }
          if (sdp.isNotEmpty) {
            await CallService.createAnswer(_callId, sdp);
            if (mounted) setState(() => _status = 'جارٍ الاتصال...');
          }
        } else if (_isCaller && m['signal_type'] == 'answer') {
          var payload = m['payload'];
          String sdp = '';
          if (payload is Map) {
            sdp = payload['sdp']?.toString() ?? payload['data']?['sdp']?.toString() ?? '';
          } else if (payload is String) {
            try {
              final p = jsonDecode(payload) as Map<String, dynamic>;
              sdp = p['sdp']?.toString() ?? p['data']?['sdp']?.toString() ?? '';
            } catch (_) {}
          }
          if (sdp.isNotEmpty) {
            await CallService.handleSdpSignal(m);
          }
        } else if (m['signal_type'] == 'candidate') {
          await CallService.handleCandidateSignal(m);
        }
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _timer?.cancel();
    _signalTimer?.cancel();
    _endCall().catchError((e) => 0);
    _localRenderer.dispose();
    _remoteRenderer.dispose();
    super.dispose();
  }

  Future<void> _endCall() async {
    try {
      await ApiService.post('/calls/$_callId/end');
    } catch (_) {}
    try {
      await CallService.hangup();
    } catch (_) {}
  }

  Future<void> _toggleMute() async {
    await CallService.toggleMute();
    if (mounted) setState(() => _muted = !_muted);
  }

  Future<void> _toggleCamera() async {
    await CallService.toggleCamera();
    if (mounted) setState(() => _cameraOff = !_cameraOff);
  }

  @override
  Widget build(BuildContext context) {
    final type = widget.callData['call_type'] ?? 'voice';
    final isVideo = type == 'video';
    final name = widget.callData['caller_name'] ?? 'مستخدم';

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // البث البعيد (ملء الشاشة للفيديو)
          if (isVideo && _answered)
            Positioned.fill(
              child: RTCVideoView(_remoteRenderer, objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitCover),
            ),
          // البث المحلي (نافذة صغيرة أعلى اليسار)
          if (isVideo && _ready && CallService.localStream != null)
            Positioned(
              right: 16,
              top: 60,
              child: Container(
                width: 110,
                height: 160,
                decoration: BoxDecoration(
                  color: Colors.black26,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: Colors.white24, width: 1),
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(13),
                  child: RTCVideoView(_localRenderer, objectFit: RTCVideoViewObjectFit.RTCVideoViewObjectFitCover, mirror: true),
                ),
              ),
            ),
          // اسم وحالة أعلى (للصوت وأثناء انتظار الفيديو)
          if (!isVideo || !_answered)
            SafeArea(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(40),
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: const Color(0xFF25D366).withOpacity(0.15),
                      ),
                      child: Icon(
                        isVideo ? Icons.videocam : Icons.call,
                        color: const Color(0xFF25D366),
                        size: 56,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Text(name,
                        style: const TextStyle(
                            color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    Text(_status,
                        style: TextStyle(color: Colors.white70, fontSize: 16)),
                    const SizedBox(height: 36),
                    if (!_answered)
                      const CircularProgressIndicator(color: Colors.white),
                  ],
                ),
              ),
            ),
          // شريط الأزرار السفلي
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: Container(
              padding: EdgeInsets.fromLTRB(20, 12, 20, MediaQuery.of(context).padding.bottom + 24),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  if (isVideo && _ready)
                    _ControlBtn(
                      icon: _muted ? Icons.mic_off : Icons.mic,
                      active: _muted,
                      onTap: _toggleMute,
                    ),
                  if (isVideo && _ready)
                    _ControlBtn(
                      icon: _cameraOff ? Icons.videocam_off : Icons.videocam,
                      active: _cameraOff,
                      onTap: _toggleCamera,
                    ),
                  CircleAvatar(
                    radius: 32,
                    backgroundColor: Colors.red,
                    child: IconButton(
                        onPressed: () {
                          _endCall();
                          Navigator.pop(context);
                        },
                        icon: const Icon(Icons.call_end, color: Colors.white, size: 26)),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ControlBtn extends StatelessWidget {
  final IconData icon;
  final bool active;
  final VoidCallback onTap;
  const _ControlBtn({required this.icon, required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return CircleAvatar(
      radius: 26,
      backgroundColor: active ? Colors.white : Colors.white12,
      child: IconButton(
        onPressed: onTap,
        icon: Icon(icon, color: active ? Colors.black87 : Colors.white, size: 22),
      ),
    );
  }
}
