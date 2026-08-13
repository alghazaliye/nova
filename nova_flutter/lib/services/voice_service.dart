/// خدمة تسجيل الرسائل الصوتية — تعمل على الويب عبر MediaRecorder
import 'dart:async';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:universal_html/html.dart' as html;

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
    final constraints = {'audio': true};
    final stream = await html.window.navigator.mediaDevices?.getUserMedia(constraints);
    if (stream == null) throw Exception('تعذر الوصول إلى الميكروفون');

    _chunks.clear();
    _seconds = 0;

    final options = <String, Object>{
      'mimeType': _supportedMime(),
      'audioBitsPerSecond': 48000,
    };
    final recorder = html.MediaRecorder(stream, options);

    recorder.on['dataavailable'].listen((event) {
      final blobEvent = event as html.BlobEvent;
      final data = blobEvent.data;
      if (data != null) {
        final impl = data as dynamic;
        final bytes = impl._data;
        if (bytes is List<int>) {
          _chunks.add(Uint8List.fromList(bytes));
        }
      }
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
    // إيقافTracks الميكروفون (نفس أسلوب call_service)
    try {
      final stream = recorder.stream;
      if (stream != null) {
        for (final t in stream.getAudioTracks()) {
          t.stop();
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
