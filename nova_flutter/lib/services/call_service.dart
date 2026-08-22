import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'api_service.dart';

/// خدمة WebRTC للمكالمات الصوتية/المرئية في NOVA Messenger.
///
/// آلية التشغيل:
/// - المتصل (caller) ينشئ PeerConnection + كاميرا، يولّد offer، ويحفظه عبر
///   POST /calls/{id}/signal (signal_type=offer).
/// - الطرف الآخر يستقبل الإشارة عبر GET /calls/{id}/signals ويولّد answer.
/// - ICE candidates تُرسل عبر نفس endpoint (signal_type=candidate).
/// - الميديا تُعرض عبر RTCVideoRenderer (كاميرتان: محلية + بعيدة).
class CallService {
  RTCPeerConnection? _pc;
  Timer? _signalTimer;
  DateTime? _lastSignalCheck;

  MediaStream? _localStream;
  RTCVideoRenderer localRenderer = RTCVideoRenderer();
  RTCVideoRenderer remoteRenderer = RTCVideoRenderer();

  bool _isCaller = false;
  int? _callId;
  String? _peerId;

  bool get isReady => _pc != null;

  /// إعداد الرندررات (يُنادى مرة في initState)
  static Future<void> initRenderers(
      RTCVideoRenderer local, RTCVideoRenderer remote) async {
    await local.initialize();
    await remote.initialize();
  }

  /// تهيئة المكالمة (قبل العرض أو القبول)
  Future<void> init(int callId, bool isCaller, {String? peerId}) async {
    _callId = callId;
    _isCaller = isCaller;
    _peerId = peerId;

    await localRenderer.initialize();
    await remoteRenderer.initialize();

    _pc = await createPeerConnection({
      'iceServers': [
        {'urls': 'stun:stun.l.google.com:19302'},
        {'urls': 'stun:stun1.l.google.com:19302'},
      ],
      'sdpSemantics': 'unified-plan',
    }, {
      'mandatory': {},
      'optional': [
        {'DtlsSrtpKeyAgreement': true},
      ],
    });

    // التقاط الكاميرا والميكروفون (مع إعادة المحاولة لأن المتصفح قد يرفض الطلب الأول)
    MediaStream? acquired;
    // محاولة الحصول على الفيديو والصوت بأقل قيود ممكنة لضمان النجاح
    try {
      // محاولة الحصول على الفيديو والصوت بقيود مرنة لضمان النجاح في الويب
      acquired = await navigator.mediaDevices.getUserMedia({
        'audio': true,
        'video': kIsWeb ? true : {
          'facingMode': 'user',
          'width': {'min': 320, 'ideal': 640, 'max': 1280},
          'height': {'min': 240, 'ideal': 480, 'max': 720},
        },
      });
    } catch (e) {
      debugPrint('CallService: فشل الحصول على الفيديو، محاولة الصوت فقط: $e');
      try {
        acquired = await navigator.mediaDevices.getUserMedia({'audio': true, 'video': false});
      } catch (e2) {
        debugPrint('CallService: فشل الحصول على أي وسائط: $e2');
      }
    }
    if (acquired != null) {
      _localStream = acquired;
      final stream = acquired;
      stream.getTracks().forEach((track) {
        _pc!.addTrack(track, stream);
        debugPrint('CallService: أُضيف المسار: ${track.kind} enabled=${track.enabled}');
      });
      // معاينة الفيديو المحلي فورًا (حتى أثناء الرنين)
      localRenderer.srcObject = stream;
    } else {
      debugPrint('CallService: تنبيه — لم يتم التقاط أي وسائط (لا كاميرا ولا صوت)');
    }

    // تتبع حالة ICE للتشخيص
    _pc!.onIceGatheringState = (state) {
      debugPrint('CallService: ICE gathering: $state');
    };

    // استقبال الإشارات من الطرف الآخر
    _pc!.onTrack = (RTCTrackEvent event) {
      debugPrint('CallService: onTrack: ${event.track.kind}');
      if (event.streams.isNotEmpty) {
        if (remoteRenderer.srcObject?.id != event.streams.first.id) {
          remoteRenderer.srcObject = event.streams.first;
        }
      }
    };
    _pc!.onAddStream = (MediaStream stream) {
      debugPrint('CallService: onAddStream: ${stream.id}');
      if (remoteRenderer.srcObject?.id != stream.id) {
        remoteRenderer.srcObject = stream;
      }
    };

    // ICE candidates
    _pc!.onIceCandidate = (RTCIceCandidate candidate) async {
      if (candidate.candidate != null && candidate.candidate!.isNotEmpty) {
        await _sendSignal('candidate', {
          'candidate': candidate.candidate,
          'sdpMid': candidate.sdpMid,
          'sdpMLineIndex': candidate.sdpMLineIndex,
        });
      }
    };

    _pc!.onIceConnectionState = (RTCIceConnectionState state) {
      debugPrint('CallService: ICE $state');
    };

    _startSignalPolling();
  }

  /// المتصل: يولّد offer ويبدأ المكالمة
  Future<void> startCall() async {
    if (_pc == null || _callId == null) return;
    final offer = await _pc!.createOffer({
      'offerToReceiveAudio': true,
      'offerToReceiveVideo': true,
    });
    await _pc!.setLocalDescription(offer);
    await _sendSignal('offer', {
      'type': offer.type,
      'sdp': offer.sdp,
    });
  }

  /// الطرف الآخر: يستقبل offer ويولّد answer
  Future<void> answerCall() async {
    if (_pc == null || _callId == null) return;
    // استقبال offer القديم + أي candidates تراكمت
    await _processIncomingSignals();
    final answer = await _pc!.createAnswer({
      'offerToReceiveAudio': true,
      'offerToReceiveVideo': true,
    });
    await _pc!.setLocalDescription(answer);
    await _sendSignal('answer', {
      'type': answer.type,
      'sdp': answer.sdp,
    });
  }

  /// معالجة الإشارات الواردة (offer/candidates) من الطرف الآخر
  Future<void> _processIncomingSignals() async {
    if (_pc == null || _callId == null) return;
    final since = _lastSignalCheck?.toIso8601String().replaceAll('T', ' ').substring(0, 19);
    final res = await ApiService.get('/calls/$_callId/signals', query: {
      if (since != null && since.isNotEmpty) 'since': since,
    });
    if (res['status_code'] == 401) {
      await dispose();
      return;
    }
    _lastSignalCheck = DateTime.now();
    if (res['success'] != true) return;
    final rows = res['data'] as List? ?? [];
    for (final raw in rows) {
      if (raw is! Map) continue;
      final type = (raw['signal_type'] ?? '').toString();
      final payload = raw['payload'];
      final createdAt = raw['created_at']?.toString();
      if (payload == null) continue;
      
      if (createdAt != null) {
        final signalTime = DateTime.tryParse(createdAt);
        if (signalTime != null && (_lastSignalCheck == null || signalTime.isAfter(_lastSignalCheck!))) {
          _lastSignalCheck = signalTime;
        }
      }

      Map<String, dynamic> data;
      if (payload is String) {
        try {
          data = jsonDecode(payload) as Map<String, dynamic>;
        } catch (_) {
          data = {'sdp': payload};
        }
      } else {
        data = Map<String, dynamic>.from(payload);
      }
      try {
        if (type == 'offer' && data['sdp'] != null) {
          await _pc!.setRemoteDescription(
              RTCSessionDescription(data['sdp'], data['type'] ?? 'offer'));
        } else if (type == 'answer' && data['sdp'] != null) {
          await _pc!.setRemoteDescription(
              RTCSessionDescription(data['sdp'], data['type'] ?? 'answer'));
        } else if (type == 'candidate' && data['candidate'] != null) {
          await _pc!.addCandidate(RTCIceCandidate(
            data['candidate'] as String?,
            data['sdpMid'] as String?,
            data['sdpMLineIndex'] as int?,
          ));
        }
      } catch (e) {
        debugPrint('CallService: خطأ معالجة إشارة $type: $e');
      }
    }
  }

  /// إرسال إشارة signal_type/payload إلى الخادم
  Future<void> _sendSignal(String type, Map<String, dynamic> payload) async {
    if (_callId == null || ApiService.token == null) return;
    try {
      final res = await ApiService.post('/calls/$_callId/signal', body: {
        'signal_type': type,
        ...payload,
      });
      if (res['status_code'] == 401) {
        await dispose();
      }
    } catch (e) {
      debugPrint('CallService: فشل إرسال إشارة $type: $e');
    }
  }

  /// polling للإشارات الواردة كل ثانيتين
  void _startSignalPolling() {
    _signalTimer?.cancel();
    _signalTimer = Timer.periodic(const Duration(seconds: 2), (_) async {
      if (_pc == null) return;
      final since = _lastSignalCheck
          ?.toIso8601String()
          .replaceAll('T', ' ')
          .substring(0, 19);
      final res = await ApiService.get('/calls/$_callId/signals', query: {
        if (since != null && since.isNotEmpty) 'since': since,
      });
      if (res['status_code'] == 401) {
        await dispose();
        return;
      }
      if (res['success'] != true) return;
      final rows = res['data'] as List? ?? [];
      for (final raw in rows) {
        if (raw is! Map) continue;
        final type = (raw['signal_type'] ?? '').toString();
        final payload = raw['payload'];
        if (payload == null) continue;
        Map<String, dynamic> data;
        if (payload is String) {
          try {
            data = jsonDecode(payload) as Map<String, dynamic>;
          } catch (_) {
            data = {'sdp': payload};
          }
        } else {
          data = Map<String, dynamic>.from(payload);
        }
        try {
          if (type == 'offer' && data['sdp'] != null) {
            await _pc!.setRemoteDescription(
                RTCSessionDescription(data['sdp'], data['type'] ?? 'offer'));
          } else if (type == 'answer' && data['sdp'] != null) {
            await _pc!.setRemoteDescription(
                RTCSessionDescription(data['sdp'], data['type'] ?? 'answer'));
          } else if (type == 'candidate' && data['candidate'] != null) {
            await _pc!.addCandidate(RTCIceCandidate(
              data['candidate'] as String?,
              data['sdpMid'] as String?,
              data['sdpMLineIndex'] as int?,
            ));
          }
        } catch (e) {
          debugPrint('CallService: خطأ إشارة واردة $type: $e');
        }
      }
    });
  }

  /// تبديل الكاميرا الأمامية/الخلفية
  Future<void> switchCamera() async {
    if (_localStream == null || _pc == null) return;
    try {
      final devices = await navigator.mediaDevices.enumerateDevices();
      final cams = devices.where((d) => d.kind == 'videoinput').toList();
      if (cams.length < 2) return;
      final label =
          _localStream!.getVideoTracks().firstOrNull?.label ?? '';
      MediaDeviceInfo? next;
      for (final d in cams) {
        if (d.deviceId != label && d.label != label) {
          next = d;
          break;
        }
      }
      if (next == null) return;
      final newStream = await navigator.mediaDevices.getUserMedia({
        'audio': false,
        'video': {'deviceId': next.deviceId},
      });
      final newTrack = newStream.getVideoTracks().first;
      final senders = await _pc!.getSenders();
      final sender = senders.firstWhere(
          (s) => s.track?.kind == 'video',
          orElse: () => senders.first);
      await sender.replaceTrack(newTrack);
      // تحديث الـ local preview
      if (_localStream!.getVideoTracks().isNotEmpty) {
        _localStream!.removeTrack(_localStream!.getVideoTracks().first);
      }
      _localStream!.addTrack(newTrack);
      localRenderer.srcObject = _localStream;
      newStream.removeTrack(newTrack);
    } catch (e) {
      debugPrint('CallService: تبديل الكاميرا غير مدعوم: $e');
    }
  }

  /// كتم/إلغاء كتم الميكروفون
  void setMicMuted(bool muted) {
    _localStream?.getAudioTracks().forEach((t) {
      (t as MediaStreamTrack).enabled = !muted;
    });
  }

  /// إيقاف/تشغيل الفيديو
  void setVideoEnabled(bool enabled) {
    _localStream?.getVideoTracks().forEach((t) {
      (t as MediaStreamTrack).enabled = enabled;
    });
  }

  /// إنهاء المكالمة وتنظيف الموارد
  Future<void> dispose() async {
    _signalTimer?.cancel();
    _localStream?.getTracks().forEach((t) => t.stop());
    localRenderer.srcObject = null;
    remoteRenderer.srcObject = null;
    await localRenderer.dispose();
    await remoteRenderer.dispose();
    await _pc?.close();
    _pc = null;
  }
}
