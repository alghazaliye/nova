import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'api_service.dart';

/// خدمة WebRTC للمكالمات الصوتية والمرئية الحقيقية.
/// تعمل على الويب والأندرويد معاً، وتستخدم /calls/{id}/signal للتواصل عبر الخادم.
class CallService {
  static RTCPeerConnection? _pc;
  static final Map<String, dynamic> _localConstraints = {
    'audio': true,
    'video': kIsWeb
        ? {
            'deviceId': '',
            'facingMode': 'user',
            'width': {'min': 320, 'ideal': 640},
            'height': {'min': 180, 'ideal': 360},
            'frameRate': {'min': 15, 'ideal': 30},
          }
        : {'facingMode': 'user'},
  };
  static MediaStream? localStream;
  static MediaStream? remoteStream;
  static Function(MediaStream)? onRemoteStream;
  static Function(String)? onConnectionState;
  static String? lastConnectionState;

  static Future<RTCPeerConnection> get pc async {
    _pc ??= await _createPeerConnection();
    return _pc!;
  }

  static Future<RTCPeerConnection> _createPeerConnection() async {
    final config = {
      'iceServers': [
        {'urls': 'stun:stun.l.google.com:19302'},
        {'urls': 'stun:stun1.l.google.com:19302'},
        {'urls': 'stun:stun2.l.google.com:19302'},
      ],
    };
    final peer = await createPeerConnection(config);
    peer.onIceCandidate = (candidate) async {
      // يُرسل تلقائياً إذا كان sender مرفقاً
    };
    peer.onTrack = (event) {
      if (event.streams.isNotEmpty) {
        remoteStream = event.streams.first;
        onRemoteStream?.call(remoteStream!);
      }
    };
    peer.onConnectionState = (state) {
      lastConnectionState = state.toString();
      onConnectionState?.call(state.toString());
    };
    return peer;
  }

  /// طلب إذونات الميكروفون/الكاميرا وبدء البث المحلي.
  static Future<void> startLocalMedia({bool video = true}) async {
    final constraints = video ? _localConstraints : {'audio': true, 'video': false};
    localStream = await navigator.mediaDevices.getUserMedia(constraints);
    final conn = await pc;
    if (video) {
      for (final track in localStream!.getVideoTracks()) {
        await conn.addTrack(track, localStream!);
      }
    }
    await conn.addTrack(localStream!.getAudioTracks().first, localStream!);
  }

  /// المتصل: إنشاء Offer وإرساله.
  static Future<void> createOffer(int callId) async {
    final conn = await pc;
    final offer = await conn.createOffer({'offerToReceiveAudio': true, 'offerToReceiveVideo': true});
    await conn.setLocalDescription(offer);
    await _sendSignal(callId, 'offer', offer.sdp ?? '');
  }

  /// المستدعى: الإجابة على Offer.
  static Future<void> createAnswer(int callId, String offerSdp) async {
    final conn = await pc;
    await conn.setRemoteDescription(RTCSessionDescription(offerSdp, 'offer'));
    final answer = await conn.createAnswer({'offerToReceiveAudio': true, 'offerToReceiveVideo': true});
    await conn.setLocalDescription(answer);
    await _sendSignal(callId, 'answer', answer.sdp ?? '');
  }

  /// معالجة إشارة SDP (offer/answer) من الخادم.
  static Future<void> handleSdpSignal(Map<String, dynamic> sig) async {
    final type = sig['signal_type'] ?? '';
    final payloadRaw = sig['payload'];
    String sdp = '';
    if (payloadRaw is Map) {
      sdp = (payloadRaw['sdp'] ?? '').toString();
    } else if (payloadRaw is String && payloadRaw.isNotEmpty) {
      try {
        final p = jsonDecode(payloadRaw) as Map<String, dynamic>;
        sdp = (p['sdp'] ?? '').toString();
      } catch (_) {}
    }
    if (sdp.isEmpty) return;
    final conn = await pc;
    if (type == 'answer') {
      await conn.setRemoteDescription(RTCSessionDescription(sdp, 'answer'));
    }
  }

  /// معالجة إشارة candidate من الخادم.
  static Future<void> handleCandidateSignal(Map<String, dynamic> sig) async {
    final payloadRaw = sig['payload'];
    Map<String, dynamic> data = {};
    if (payloadRaw is Map) {
      data = Map<String, dynamic>.from(payloadRaw);
      if (data['data'] is Map) data = Map<String, dynamic>.from(data['data'] as Map);
    } else if (payloadRaw is String && payloadRaw.isNotEmpty) {
      try {
        final p = jsonDecode(payloadRaw) as Map<String, dynamic>;
        data = (p['data'] ?? p) is Map ? Map<String, dynamic>.from((p['data'] ?? p) as Map) : {};
      } catch (_) {}
    }
    if ((data['candidate'] ?? '') == '') return;
    final conn = await pc;
    await conn.addCandidate(RTCIceCandidate(
      data['candidate'].toString(),
      data['sdp_mid']?.toString() ?? '',
      (data['sdp_mline_index'] ?? 0) is int ? data['sdp_mline_index'] as int : 0,
    ));
  }

  static Future<void> _sendSignal(int callId, String type, String sdp) async {
    try {
      await ApiService.post('/calls/$callId/signal', body: {
        'signal_type': type,
        'payload': jsonEncode({'sdp': sdp}),
      });
    } catch (_) {}
  }

  /// إرسال المرشحين (candidates) للمكالمة.
  static void attachCandidateSender(int callId) {
    pc.then((conn) {
      conn.onIceCandidate = (candidate) async {
        if (candidate.candidate != null && candidate.candidate!.isNotEmpty) {
          try {
            await ApiService.post('/calls/$callId/signal', body: {
              'signal_type': 'candidate',
              'payload': jsonEncode({
                'data': {
                  'candidate': candidate.candidate,
                  'sdp_mid': candidate.sdpMid ?? '',
                  'sdp_mline_index': candidate.sdpMLineIndex ?? 0,
                },
              }),
            });
          } catch (_) {}
        }
      };
    });
  }

  static Future<void> hangup() async {
    try {
      await localStream?.dispose();
    } catch (_) {}
    localStream = null;
    remoteStream = null;
    try {
      await _pc?.close();
    } catch (_) {}
    _pc = null;
  }

  /// إيقاف/تشغيل الميكروفون.
  static Future<void> toggleMute() async {
    final stream = localStream;
    if (stream == null) return;
    final track = stream.getAudioTracks().first;
    track.enabled = !track.enabled;
  }

  /// إيقاف/تشغيل الكاميرا.
  static Future<void> toggleCamera() async {
    final stream = localStream;
    if (stream == null) return;
    final track = stream.getVideoTracks().first;
    track.enabled = !track.enabled;
  }
}
