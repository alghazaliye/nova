/// خدمة تسجيل الرسائل الصوتية — تعمل على الويب عبر MediaRecorder
/// تقرأ بيانات الصوت عبر dart:js_interop وBlob.arrayBuffer() مباشرة
import 'dart:async';
import 'dart:js_interop';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:universal_html/html.dart' as html;

extension type JSMediaStream(JSObject _) implements JSObject {
  external JSArray getAudioTracks();
}

extension type JSAudioTrack(JSObject _) implements JSObject {
  external void stop();
}

extension type JSBlob(JSObject _) implements JSObject {
  external JSPromise<JSArrayBuffer> arrayBuffer();
}

class VoiceService {
  static html.MediaRecorder? _recorder;
  static Timer? _timer;
  static int _seconds = 0;
  static bool _recording = false;
  static final List<Uint8List> _chunks = [];

  static bool get isRecording => _recording;
  static int get seconds => _seconds;

  /// بدء التسجيل
  static Future<void> start() async {
    if (!kIsWeb) {
      throw UnsupportedError('التسجيل الصوتي متوفر حاليًا في نسخة الويب');
    }
    final devices = html.window.navigator.mediaDevices;
    if (devices == null) throw Exception('المتصفح لا يدعم التسجيل الصوتي');
    final stream = await devices.getUserMedia({'audio': true});
    if (stream == null) throw Exception('تعذر الوصول إلى الميكروفون');

    _chunks.clear();
    _seconds = 0;

    final recorder = html.MediaRecorder(
      stream,
      <String, Object>{
        'mimeType': _supportedMime(),
        'audioBitsPerSecond': 48000,
      },
    );

    recorder.on['dataavailable'].listen((event) {
      try {
        final blobEvent = event as html.BlobEvent;
        final blob = blobEvent.data;
        if (blob == null) return;
        // قراءة البيانات عبر JS: blob.arrayBuffer() ثم تحويلها لـ Uint8List
        final jsBlob = JSBlob(blob as JSObject);
        jsBlob.arrayBuffer().toDart.then((jsBuf) {
          final bytes = jsBuf.toDart.asUint8List();
          if (bytes.isNotEmpty) _chunks.add(bytes);
        });
      } catch (_) {}
    });

    recorder.start(100); // تجميع كل 100ms
    _recorder = recorder;
    _recording = true;
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _seconds++);
  }

  /// إيقاف التسجيل وإرجاع البيانات + mime
  static Future<(Uint8List?, String)> stop() async {
    final recorder = _recorder;
    final mime = _supportedMime();
    if (recorder == null) return (null, mime);

    final completer = Completer<void>();
    final sub = recorder.on['stop'].listen((_) => completer.complete());

    recorder.stop();
    _timer?.cancel();
    _recording = false;
    _timer = null;

    await completer.future.timeout(const Duration(seconds: 5), onTimeout: () {});
    sub.cancel();
    // إيقاف مسارات الميكروفون
    try {
      final stream = recorder.stream;
      if (stream != null) {
        final jsStream = JSMediaStream(stream as JSObject);
        final tracks = jsStream.getAudioTracks();
        for (final t in tracks.toDart) {
          JSAudioTrack(t as JSObject).stop();
        }
      }
    } catch (_) {}

    if (_chunks.isEmpty) return (null, mime);
    return (_combineChunks(), mime);
  }

  static String _supportedMime() {
    if (html.MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) return 'audio/webm;codecs=opus';
    if (html.MediaRecorder.isTypeSupported('audio/webm')) return 'audio/webm';
    return 'audio/mp4';
  }

  static Uint8List _combineChunks() {
    final total = _chunks.fold<int>(0, (sum, c) => sum + c.length);
    final out = Uint8List(total);
    var offset = 0;
    for (final c in _chunks) {
      out.setRange(offset, offset + c.length, c);
      offset += c.length;
    }
    return out;
  }
}
