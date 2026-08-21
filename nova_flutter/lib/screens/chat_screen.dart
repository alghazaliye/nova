import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../offline/local_nova_db.dart';
import '../offline/local_sync_service.dart';
import '../offline/outbox_service.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';
import 'package:image_picker/image_picker.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:video_player/video_player.dart';
import 'package:just_audio/just_audio.dart';
import 'package:record/record.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart'
    show NovaMessage, Conversation, formatLastSeen;
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import '../utils/sheets.dart';
import 'call_screen.dart';
import 'group_info_screen.dart';
import '../widgets/incoming_call_overlay.dart';

/// شاشة المحادثة — تصميم القالب الجديد مع التعديل والحذف لدى الطرفين والوسائط والمكالمات
class ChatScreen extends StatefulWidget {
  final Conversation conv;
  const ChatScreen({super.key, required this.conv});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final List<NovaMessage> _messages = [];
  final TextEditingController _ctrl = TextEditingController();
  final TextEditingController _searchCtrl = TextEditingController();
  final ScrollController _scroll = ScrollController();
  final AudioRecorder _recorder = AudioRecorder();
  bool _loading = false;
  bool _isRecording = false;
  bool _cancelRecording = false;
  DateTime? _recordStartedAt;
  Duration _recordElapsed = Duration.zero;
  Timer? _recordTicker;
  StreamSubscription<RecordState>? _recSub;
  NovaMessage? _editingMessage;
  NovaMessage? _replyingTo;
  bool _isSearching = false;
  String _searchQuery = '';
  int _searchIndex = 0;
  final Set<int> _starredMessageIds = <int>{};
  final Set<int> _pinnedMessageIds = <int>{};
  int? _chatBackgroundArgb;
  bool _chatMuted = false;
  bool _hasMore = true;
  bool _hasText = false;
  Timer? _pollTimer;
  Timer? _incomingCallTimer;
  Timer? _typingTimer;
  bool _lastTypingSent = false;
  Map<String, dynamic>? _incomingCall;
  List<Map<String, dynamic>> _localTypingUsers = const [];
  bool _incomingCallPolling = false;
  // يمنع إعادة إظهار نفس المكالمة إذا تأخر تحديث الحالة في الخادم.
  final Set<String> _handledIncomingCallIds = <String>{};
  final ImagePicker _picker = ImagePicker();
  static const Uuid _uuid = Uuid();

  @override
  void initState() {
    super.initState();
    _ctrl.addListener(() {
      final has = _ctrl.text.trim().isNotEmpty;
      if (has != _hasText) setState(() => _hasText = has);
      // مؤشر الكتابة: إرسال حالة writing عند الكتابة
      _notifyTyping(has);
    });
    _load();
    _loadChatTheme();
    // Polling: تحديث تلقائي للرسائل كل 3 ثوانٍ
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted && !_loading) _refreshSilent();
    });
    // Keep direct deep-linked chats eligible to receive calls.
    _incomingCallTimer = Timer.periodic(const Duration(seconds: 2), (_) {
      if (mounted) _pollIncomingCall();
    });
  }

  Future<void> _loadChatTheme() async {
    final prefs = await SharedPreferences.getInstance();
    final value = prefs.getInt('chat_theme_${widget.conv.id}');
    if (mounted && value != null) setState(() => _chatBackgroundArgb = value);
  }

  Future<void> _saveChatTheme(Color color) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt('chat_theme_${widget.conv.id}', color.value);
    if (mounted) setState(() => _chatBackgroundArgb = color.value);
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _incomingCallTimer?.cancel();
    _typingTimer?.cancel();
    _sendTypingCancel();
    _recordTicker?.cancel();
    _recSub?.cancel();
    _recorder.dispose();
    _ctrl.dispose();
    _searchCtrl.dispose();
    _scroll.dispose();
    super.dispose();
  }

  /// تسجيل صوتي بأسلوب الواتساب: ضغط مطوّل = ابدأ، سحب لليمين/الأسفل = إلغاء، رفع الإصبع = إرسال
  Future<void> _startRecording() async {
    if (_isRecording || kIsWeb) {
      if (kIsWeb && mounted)
        showToast(context, 'التسجيل الصوتي متاح من تطبيق الهاتف فقط');
      return;
    }
    try {
      final status = await Permission.microphone.request();
      if (!status.isGranted) {
        if (mounted) showToast(context, 'يُطلب السماح بالوصول إلى الميكروفون');
        return;
      }
      final ok = await _recorder.hasPermission();
      if (!ok) {
        if (mounted) showToast(context, 'لا يوجد إذن للميكروفون');
        return;
      }
      final dir = Directory.systemTemp;
      if (!await dir.exists()) await dir.create(recursive: true);
      final path =
          '${dir.path}/nova_voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
      // AAC/MP4 is supported on a wider range of Android devices than the
      // optional codecs. The backend validates the actual MIME bytes.
      await _recorder.start(
        const RecordConfig(
          encoder: AudioEncoder.aacLc,
          bitRate: 64000,
          sampleRate: 44100,
          numChannels: 1,
        ),
        path: path,
      );
      if (!mounted) {
        await _recorder.cancel();
        return;
      }
      _recordTicker?.cancel();
      setState(() {
        _isRecording = true;
        _cancelRecording = false;
        _recordStartedAt = DateTime.now();
        _recordElapsed = Duration.zero;
      });
      _recordTicker = Timer.periodic(const Duration(milliseconds: 120), (_) {
        if (!mounted || !_isRecording || _recordStartedAt == null) return;
        setState(() {
          _recordElapsed = DateTime.now().difference(_recordStartedAt!);
        });
      });
    } catch (e) {
      // Permission/platform codec errors must never escape the tap handler.
      try {
        await _recorder.cancel();
      } catch (_) {}
      if (mounted)
        showToast(context, 'تعذر بدء التسجيل. تحقق من صلاحية الميكروفون.');
    }
  }

  Future<void> _sendVoice() async {
    if (!_isRecording) return;
    await _stopRecording();
  }

  Future<void> _stopRecording() async {
    if (!_isRecording) return;
    final wasCancelled = _cancelRecording;
    _recSub?.cancel();
    String? path;
    try {
      path = await _recorder.stop();
    } catch (_) {
      try {
        await _recorder.cancel();
      } catch (_) {}
      _recordTicker?.cancel();
      _recordTicker = null;
      if (mounted) {
        setState(() {
          _isRecording = false;
          _recordElapsed = Duration.zero;
          _recordStartedAt = null;
        });
        showToast(context, 'تعذر إنهاء التسجيل');
      }
      return;
    }
    _recordTicker?.cancel();
    _recordTicker = null;
    if (mounted) {
      setState(() {
        _isRecording = false;
        _recordElapsed = Duration.zero;
        _recordStartedAt = null;
      });
    }
    if (wasCancelled || path == null || path.isEmpty) return;
    // أرسل المقطع المسجل
    try {
      final recordedFile = File(path);
      if (!await recordedFile.exists()) {
        if (mounted) showToast(context, 'ملف التسجيل غير موجود');
        return;
      }
      final bytes = await recordedFile.readAsBytes();
      if (bytes.isEmpty) {
        if (mounted) showToast(context, 'التسجيل فارغ');
        return;
      }
      if (mounted) showToast(context, 'جاري إرسال المقطع الصوتي...');
      final file = http.MultipartFile.fromBytes(
        'attachment',
        bytes,
        filename: 'voice_${DateTime.now().millisecondsSinceEpoch}.m4a',
        contentType: http.MediaType.parse('audio/mp4'),
      );
      final res = await ApiService.uploadMultipart(
        '/conversations/${widget.conv.id}/media',
        [file],
        fields: {'client_message_id': _uuid.v4(), 'type': 'audio'},
      );
      if (mounted) {
        if (res['success'] == true) {
          await _refresh();
          if (mounted) showToast(context, 'تم إرسال المقطع الصوتي');
        } else if (mounted) {
          showToast(context, res['message'] ?? 'فشل الإرسال');
        }
      }
    } catch (_) {
      if (mounted) showToast(context, 'فشل إرسال المقطع الصوتي');
    }
  }

  void _toggleCancelRecording(Offset offset) {
    // في RTL يمكن السحب يميناً أو يساراً؛ الخروج 80px من الزر يفعّل الإلغاء.
    final shouldCancel = offset.dx.abs() > 80 || offset.dy.abs() > 80;
    if (shouldCancel != _cancelRecording && mounted) {
      setState(() => _cancelRecording = shouldCancel);
    }
  }

  /// مؤشر الكتابة (مُحسَّن للأداء):
  /// - يرسل typing=true **مرة واحدة فقط** عند بداية الكتابة،
  ///   ثم يُعتمد على الخادم لإبقاء الحالة نشطة 4 ثوانٍ وانتهائها تلقائيًا.
  /// - لا يُرسل أي طلب إضافي أثناء الاستمرار (صفر طلبات أثناء الكتابة).
  /// - يُلغي الحالة فقط عند: إفراغ الحقل (توقف الكتابة) أو إرسال الرسالة أو مغادرة الشاشة.
  void _notifyTyping(bool isTyping) {
    if (isTyping) {
      // Debouncing: إرسال حالة الكتابة مرة واحدة كل 3 ثوانٍ أثناء استمرار الكتابة
      // لضمان بقاء الحالة نشطة في الخادم دون إغراق الشبكة بالطلبات.
      if (!_lastTypingSent) {
        _lastTypingSent = true;
        ApiService.post('/conversations/${widget.conv.id}/typing',
            body: {'typing': true}).catchError((_) => <String, dynamic>{'success': false, 'message': 'تعذر الاتصال'});
        
        // إعادة التعيين بعد 3 ثوانٍ للسماح بإرسال طلب آخر إذا استمر المستخدم في الكتابة
        _typingTimer?.cancel();
        _typingTimer = Timer(const Duration(seconds: 3), () {
          if (mounted) _lastTypingSent = false;
        });
      }
    } else {
      // الحقل فُرّغ: إلغاء فوري
      _typingTimer?.cancel();
      _typingTimer = null;
      if (_lastTypingSent) {
        _sendTypingCancel();
        _lastTypingSent = false;
      }
    }
  }

  void _sendTypingCancel() {
    ApiService.post('/conversations/${widget.conv.id}/typing',
        body: {'typing': false}).catchError((_) => <String, dynamic>{'success': false, 'message': 'تعذر الاتصال'});
  }

  bool _listsEqual(List<Map<String, dynamic>> a, List<Map<String, dynamic>> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) {
      if (a[i]['user_id'] != b[i]['user_id']) return false;
    }
    return true;
  }

  /// يجلب حالة الكتابة الحالية من الخادم ويحدّث العرض (throttle: مرة كل 15 ثانية كحد أقصى)
  DateTime? _lastTypingRefresh;
  Future<void> _refreshTyping() async {
    final now = DateTime.now();
    final lt = _lastTypingRefresh;
    // تحديث كل 3 ثوانٍ ليكون مؤشر "يكتب الآن" مستجيباً للواقع
    if (lt != null && now.difference(lt).inSeconds < 3) return;
    _lastTypingRefresh = now;
    try {
      final res = await ApiService.get('/conversations/${widget.conv.id}/typing');
      if (!mounted || res['success'] != true) return;
      final data = res['data'];
      final list = data is Map ? data['typing_users'] : null;
      final typingUsers = (list is List)
          ? list.map((e) => Map<String, dynamic>.from(e)).toList()
          : <Map<String, dynamic>>[];
      if (mounted && !_listsEqual(_localTypingUsers, typingUsers)) {
        setState(() => _localTypingUsers = typingUsers);
      }
    } catch (_) {}
  }

  /// تحديث صامت: يجلب أحدث الرسائل الجديدة فقط ويحدّث حالة الرسائل الحالية
  Future<void> _refreshSilent() async {
    final wasNearBottom = !_scroll.hasClients ||
        _scroll.position.maxScrollExtent - _scroll.position.pixels < 120;
    var addedNewMessage = false;
    try {
      final res = await ApiService.get(
        '/conversations/${widget.conv.id}/messages',
        query: {'limit': '50'},
      );
      if (!mounted || res['success'] != true || res['data'] is! List) return;
      final msgs = (res['data'] as List)
          .map((e) => NovaMessage.fromJson(Map<String, dynamic>.from(e)))
          .toList();
      if (msgs.isEmpty) return;
      // تحديث مؤشر الكتابة عند وصول رسائل جديدة فقط (وليس كل دورة polling)
      await _refreshTyping();
      setState(() {
        // تحديث حالة الرسائل الموجودة (تسليم/قراءة)
        for (final m in msgs) {
          final idx = _messages.indexWhere((x) => x.id == m.id);
          if (idx >= 0) {
            _messages[idx] = m;
          }
        }
        // إضافة رسائل جديدة لم تصلنا بعد
        final existingIds = _messages.map((e) => e.id).toSet();
        for (final m in msgs) {
          if (!existingIds.contains(m.id)) {
            _messages.add(m);
            addedNewMessage = true;
          }
        }
      });
      // لا نغيّر موضع القراءة إلا إذا كان المستخدم يتابع آخر الرسائل.
      if (addedNewMessage && wasNearBottom) _scrollToBottom();
    } catch (_) {}
  }

  Future<void> _load() async {
    if (_loading || !_hasMore) return;
    setState(() => _loading = true);
    try {
      final res = await ApiService.get(
        '/conversations/${widget.conv.id}/messages',
        query: {
          'limit': '50',
          if (_messages.isNotEmpty) 'before_id': '${_messages.first.id}',
        },
      );
      if (res['success'] == true && res['data'] is List) {
        final msgs = (res['data'] as List)
            .map((e) => NovaMessage.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        setState(() {
          _messages.insertAll(0, msgs);
          if (msgs.length < 50) _hasMore = false;
        });
      }
    } catch (_) {}
    if (!mounted) return;
    setState(() => _loading = false);
  }

  Future<void> _refresh() async {
    if (!mounted) return;
    setState(() => _hasMore = true);
    _messages.clear();
    await _load();
    _scrollToBottom();
  }

  void _scrollToBottom() {
    if (_scroll.hasClients) {
      Future.delayed(const Duration(milliseconds: 100), () {
        if (_scroll.hasClients)
          _scroll.jumpTo(_scroll.position.maxScrollExtent);
      });
    }
  }

  Future<void> _sendMessage() async {
    if (_editingMessage != null) {
      await _saveEditedMessage();
      return;
    }
    final text = _ctrl.text.trim();
    if (text.isEmpty) return;
    final clientId = _uuid.v4();
    final replyId = _replyingTo?.id;
    _ctrl.clear();
    if (mounted) setState(() => _replyingTo = null);
    final me = context.read<AuthProvider>().user;
    final nowIso = DateTime.now().toIso8601String();
    final temp = NovaMessage(
      id: -1,
      uuid: clientId,
      senderId: me?.id ?? 0,
      type: 'text',
      body: text,
      replyToMessageId: replyId,
      status: 'sending',
      createdAt: nowIso,
    );
    setState(() => _messages.add(temp));
    _scrollToBottom();
    // Offline-First: حفظ الرسالة محليًا فورًا (تبقى ظاهرة حتى لو انقطع الاتصال)
    LocalMessage? localRow;
    try {
      localRow = await LocalSyncService.storePendingMessage(
        conversationId: widget.conv.id,
        localUuid: clientId,
        senderId: me?.id ?? 0,
        type: 'text',
        body: text,
        replyToServerId: replyId,
      );
    } catch (_) {}
    try {
      final res = await ApiService.post(
          '/conversations/${widget.conv.id}/messages',
          body: {
            'client_message_id': clientId,
            'type': 'text',
            'body': text,
            if (replyId != null) 'reply_to_message_id': replyId,
          });
      if (res['success'] == true && res['data'] != null) {
        final confirmed = NovaMessage.fromJson(
            Map<String, dynamic>.from(res['data'] as Map<String, dynamic>));
        setState(() {
          _messages.removeWhere((m) => m.uuid == clientId);
          _messages.add(confirmed);
        });
        _scrollToBottom();
        // تحديث الحالة المحلية إلى synced
        if (localRow != null) {
          try {
            await LocalSyncService.upsertMessages([
              {
                'id': confirmed.id,
                'client_message_id': clientId,
                'conversation_id': widget.conv.id,
                'sender_id': confirmed.senderId,
                'type': confirmed.type,
                'body': confirmed.body,
                'reply_to_message_id': confirmed.replyToMessageId,
                'status': confirmed.status,
                'created_at': confirmed.createdAt,
                'is_edited': confirmed.isEdited ? 1 : 0,
                'file_path': confirmed.filePath,
                'thumbnail_path': confirmed.thumbnailPath,
                'mime_type': confirmed.mimeType,
                'file_size': confirmed.fileSize,
                'width': confirmed.width,
                'height': confirmed.height,
                'duration': confirmed.duration,
              },
            ]);
          } catch (_) {}
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(res['message'] ?? 'فشل الإرسال')));
        if (localRow != null) {
          // فشل الخادم: دفع إلى طابور المزامنة بدلًا من فقدان الرسالة
          try {
            await OutboxService.push(
              operation: 'SEND_MESSAGE',
              entityRef: '${localRow!.id}',
              payload: {
                'conversation_id': widget.conv.id,
                'client_message_id': clientId,
                'type': 'text',
                'body': text,
                if (replyId != null) 'reply_to_message_id': replyId,
              },
            );
          } catch (_) {}
        }
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('خطأ في الاتصال')));
      }
      if (localRow != null && mounted) {
        // دفع إلى طابور المزامنة للإرسال تلقائيًا عند عودة الاتصال
        try {
          await OutboxService.push(
            operation: 'SEND_MESSAGE',
            entityRef: '${localRow!.id}',
            payload: {
              'conversation_id': widget.conv.id,
              'client_message_id': clientId,
              'type': 'text',
              'body': text,
              if (replyId != null) 'reply_to_message_id': replyId,
            },
          );
        } catch (_) {}
      }
    }
  }

  String _guessType(String name) {
    final ext = name.split('.').last.toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].contains(ext)) return 'image';
    if (['mp4', 'mov', 'avi'].contains(ext)) return 'video';
    if (['mp3', 'wav', 'ogg', 'm4a'].contains(ext)) return 'audio';
    return 'file';
  }

  String _mimeFromExt(String ext) {
    switch (ext) {
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'png':
        return 'image/png';
      case 'gif':
        return 'image/gif';
      case 'mp4':
        return 'video/mp4';
      case 'mov':
        return 'video/quicktime';
      case 'webm':
        return 'video/webm';
      case 'mp3':
        return 'audio/mpeg';
      case 'wav':
        return 'audio/wav';
      case 'ogg':
        return 'audio/ogg';
      case 'pdf':
        return 'application/pdf';
      case 'doc':
      case 'docx':
        return 'application/msword';
      default:
        return 'application/octet-stream';
    }
  }

  /// تحويل المسار النسبي إلى رابط URL مطلق قابل للخدمة
  String _fileUrl(String? path) => ApiService.mediaUrl(path);

  Future<void> _sendStructuredMessage(
      String type, Map<String, dynamic> data) async {
    try {
      final res = await ApiService.post(
        '/conversations/${widget.conv.id}/messages',
        body: {
          'client_message_id': _uuid.v4(),
          'type': type,
          'body': jsonEncode(data),
        },
      );
      if (!mounted) return;
      if (res['success'] == true) {
        await _refresh();
        showToast(context,
            type == 'location' ? 'تم إرسال الموقع' : 'تم إرسال جهة الاتصال');
      } else {
        showToast(context, res['message'] ?? 'فشل الإرسال');
      }
    } catch (_) {
      if (mounted) showToast(context, 'تعذر إرسال المشاركة');
    }
  }

  Future<void> _pickDocument() async {
    try {
      final result = await FilePicker.platform.pickFiles(withData: true);
      if (result == null || result.files.isEmpty || !mounted) return;
      final picked = result.files.single;
      final bytes = picked.bytes;
      if (bytes == null) {
        showToast(context, 'تعذر قراءة المستند');
        return;
      }
      showToast(context, 'جاري رفع المستند...');
      final res = await ApiService.uploadMultipart(
        '/conversations/${widget.conv.id}/media',
        [
          http.MultipartFile.fromBytes(
            'attachment',
            bytes,
            filename: picked.name,
            contentType: http.MediaType.parse(picked.extension == 'pdf'
                ? 'application/pdf'
                : 'application/octet-stream'),
          )
        ],
        fields: {'client_message_id': _uuid.v4(), 'type': 'file'},
      );
      if (!mounted) return;
      if (res['success'] == true) {
        await _refresh();
        showToast(context, 'تم إرسال المستند');
      } else {
        showToast(context, res['message'] ?? 'فشل إرسال المستند');
      }
    } catch (_) {
      if (mounted) showToast(context, 'فشل رفع المستند');
    }
  }

  Future<void> _shareLocation() async {
    try {
      // بدون geolocator (متصفح الوeb لا يدعمها) — إدخال يدوي بسيط.
      final latCtrl = TextEditingController();
      final lngCtrl = TextEditingController();
      final c = NovaColors.of(context);
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: c.surface,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: const Text('مشاركة الموقع'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: latCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(hintText: 'خط العرض (مثل 24.7136)'),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: lngCtrl,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(hintText: 'خط الطول (مثل 46.6753)'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false),
                child: const Text('إلغاء')),
            ElevatedButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: ElevatedButton.styleFrom(
                  backgroundColor: c.accent, foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              child: const Text('إرسال'),
            ),
          ],
        ),
      );
      if (ok != true || !mounted) return;
      final lat = double.tryParse(latCtrl.text.trim());
      final lng = double.tryParse(lngCtrl.text.trim());
      if (lat == null || lng == null) {
        showToast(context, 'قيم غير صحيحة');
        return;
      }
      await _sendStructuredMessage('location', {
        'latitude': lat,
        'longitude': lng,
        'shared_at': DateTime.now().toUtc().toIso8601String(),
      });
    } catch (_) {
      if (mounted) showToast(context, 'تعذر مشاركة الموقع');
    }
  }

  Future<void> _shareContact() async {
    try {
      if (kIsWeb) {
        if (mounted)
          showToast(context, 'مشاركة جهات اتصال الهاتف متاحة من تطبيق Android');
        return;
      }
      final granted = await FlutterContacts.requestPermission(readonly: true);
      if (!granted) {
        if (mounted) showToast(context, 'يجب السماح بالوصول إلى جهات الاتصال');
        return;
      }
      final contacts = await FlutterContacts.getContacts(withProperties: true);
      if (!mounted) return;
      final selected = await showModalBottomSheet<Contact>(
        context: context,
        builder: (ctx) => SafeArea(
          child: ListView.builder(
            itemCount: contacts.length,
            itemBuilder: (_, i) {
              final c = contacts[i];
              final phone = c.phones.isNotEmpty ? c.phones.first.number : '';
              return ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person)),
                title: Text(c.displayName),
                subtitle: Text(phone),
                onTap: () => Navigator.pop(ctx, c),
              );
            },
          ),
        ),
      );
      if (selected == null || !mounted) return;
      await _sendStructuredMessage('contact', {
        'name': selected.displayName,
        'phone': selected.phones.isNotEmpty ? selected.phones.first.number : '',
      });
    } catch (_) {
      if (mounted) showToast(context, 'تعذر قراءة جهات الاتصال');
    }
  }

  Future<void> _pickMedia() async {
    try {
      final XFile? f = await _picker.pickImage(source: ImageSource.gallery);
      if (f == null || !mounted) return;
      final name = f.name.split('/').last;
      final ext =
          name.contains('.') ? name.split('.').last.toLowerCase() : 'bin';
      final mime = _mimeFromExt(ext);
      final type = _guessType(name);
      if (!mounted) return;
      showToast(context, 'جاري رفع الملف...');
      try {
        final bytes = await f.readAsBytes();
        final res = await ApiService.uploadMultipart(
          '/conversations/${widget.conv.id}/media',
          [
            http.MultipartFile.fromBytes(
              'attachment',
              bytes,
              filename: name,
              contentType: http.MediaType.parse(mime),
            ),
          ],
          fields: {
            'type': type,
            'client_message_id': _uuid.v4(),
            'attachment_type': type,
          },
        );
        if (mounted) {
          if (res['success'] == true) {
            await _refresh();
            if (mounted) showToast(context, 'تم إرسال الوسائط');
          } else if (mounted) {
            showToast(context, res['message'] ?? 'فشل الإرسال');
          }
        }
      } catch (e) {
        if (mounted) showToast(context, 'فشل الرفع');
      }
    } catch (_) {}
  }

  /// رفع فيديو من المعرض — إرساله كفقاعة فيديو قابلة للتشغيل
  Future<void> _pickVideo() async {
    try {
      final XFile? f = await _picker.pickVideo(source: ImageSource.gallery);
      if (f == null || !mounted) return;
      final name = f.name.split('/').last;
      if (!mounted) return;
      showToast(context, 'جاري رفع الفيديو...');
      try {
        final bytes = await f.readAsBytes();
        final mime = _mimeFromExt(
            name.contains('.') ? name.split('.').last.toLowerCase() : 'bin');
        final res = await ApiService.uploadMultipart(
          '/conversations/${widget.conv.id}/media',
          [
            http.MultipartFile.fromBytes(
              'attachment',
              bytes,
              filename: name,
              contentType: http.MediaType.parse(mime),
            ),
          ],
          fields: {
            'client_message_id': _uuid.v4(),
            'type': 'video',
            'attachment_type': 'video',
          },
        );
        if (mounted) {
          if (res['success'] == true) {
            await _refresh();
            if (mounted) showToast(context, 'تم إرسال الفيديو');
          } else if (mounted) {
            showToast(context, res['message'] ?? 'فشل الإرسال');
          }
        }
      } catch (e) {
        if (mounted) showToast(context, 'فشل رفع الفيديو');
      }
    } catch (_) {}
  }

  /// رفع ملف صوتي من الجهاز — إرساله كفقاعة صوت قابلة للاستماع
  Future<void> _pickAudio() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['mp3', 'wav', 'ogg', 'm4a', 'webm', 'aac'],
        withData: true,
      );
      if (result == null || result.files.isEmpty || !mounted) return;
      final picked = result.files.single;
      final name = picked.name.split('/').last;
      final ext = name.contains('.') ? name.split('.').last.toLowerCase() : '';
      if (!['mp3', 'wav', 'ogg', 'm4a', 'webm', 'aac'].contains(ext)) {
        if (mounted)
          showToast(context, 'اختر ملف صوتي (mp3/wav/ogg/m4a/webm/aac)');
        return;
      }
      if (!mounted) return;
      showToast(context, 'جاري رفع المقطع الصوتي...');
      try {
        final bytes = picked.bytes ??
            (picked.path != null
                ? await File(picked.path!).readAsBytes()
                : null);
        if (bytes == null || bytes.isEmpty) {
          if (mounted) showToast(context, 'تعذر قراءة الملف الصوتي');
          return;
        }
        final mime = _mimeFromExt(ext);
        final res = await ApiService.uploadMultipart(
          '/conversations/${widget.conv.id}/media',
          [
            http.MultipartFile.fromBytes(
              'attachment',
              bytes,
              filename: name,
              contentType: http.MediaType.parse(mime),
            ),
          ],
          fields: {
            'client_message_id': _uuid.v4(),
            'type': 'audio',
            'attachment_type': 'audio',
          },
        );
        if (mounted) {
          if (res['success'] == true) {
            await _refresh();
            if (mounted) showToast(context, 'تم إرسال المقطع الصوتي');
          } else if (mounted) {
            showToast(context, res['message'] ?? 'فشل الإرسال');
          }
        }
      } catch (e) {
        if (mounted) showToast(context, 'فشل رفع المقطع الصوتي');
      }
    } catch (_) {}
  }

  Future<void> _pollIncomingCall() async {
    if (_incomingCallPolling) return;
    _incomingCallPolling = true;
    try {
      final res = await ApiService.get('/calls/incoming');
      if (!mounted || res['success'] != true) return;
      final data = res['data'];
      Map<String, dynamic>? nextCall;
      if (data is List) {
        for (final raw in data) {
          if (raw is! Map) continue;
          final candidate = Map<String, dynamic>.from(raw);
          final candidateId = candidate['id']?.toString();
          if (candidateId == null ||
              candidateId.isEmpty ||
              _handledIncomingCallIds.contains(candidateId)) {
            continue;
          }
          nextCall = candidate;
          break;
        }
      }

      // The incoming endpoint only returns ringing/calling calls. Therefore an
      // empty result means that the caller rejected, ended, or timed out the
      // call; clear the overlay so its ringing state cannot remain stuck.
      final currentId = _incomingCall?['id']?.toString();
      final nextId = nextCall?['id']?.toString();
      if (currentId != nextId || (_incomingCall != null && nextCall == null)) {
        setState(() => _incomingCall = nextCall);
      }
    } catch (_) {
      // Keep the current overlay during a transient network failure; the next
      // poll will clear it once the API confirms the terminal call state.
    } finally {
      _incomingCallPolling = false;
    }
  }

  Future<void> _acceptIncomingCall() async {
    final call = _incomingCall;
    final callId = call?['id']?.toString();
    if (call == null || callId == null || callId.isEmpty || callId == 'null')
      return;
    _handledIncomingCallIds.add(callId);
    setState(() => _incomingCall = null);
    try {
      final sessionId = call!['session_id'] ?? call['sessionId'];
      final res = await ApiService.post('/calls/$callId/answer', body: {
        if (sessionId != null) 'session_id': sessionId,
      });
      if (!mounted) return;
      if (res['success'] == true) {
        await Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => CallScreen(callData: call)),
        );
      } else {
        showToast(context, res['message'] ?? 'فشل قبول المكالمة');
      }
    } catch (_) {
      if (mounted) showToast(context, 'فشل قبول المكالمة');
    }
  }

  Future<void> _rejectIncomingCall() async {
    final call = _incomingCall;
    final callId = call?['id']?.toString();
    if (callId == null || callId.isEmpty || callId == 'null') return;
    _handledIncomingCallIds.add(callId);
    setState(() => _incomingCall = null);
    try {
      final sessionId = call!['session_id'] ?? call['sessionId'];
      await ApiService.post('/calls/$callId/reject', body: {
        if (sessionId != null) 'session_id': sessionId,
      });
    } catch (_) {}
  }

  Future<void> _startCall(String type) async {
    if (!context.read<AuthProvider>().effectiveAppSettings.allowCalls) {
      if (mounted) showToast(context, 'المكالمات موقوفة من الإدارة');
      return;
    }
    final calleeId = widget.conv.otherUserId;
    final res = await ApiService.post('/calls', body: {
      'callee_id': calleeId,
      'call_type': type,
    });
    if (!mounted) return;
    if (res['success'] == true && res['data'] != null) {
      final callData = Map<String, dynamic>.from(
          res['data'] as Map<String, dynamic>);
      callData['caller_id'] = ApiService.userId.toString();
      callData['is_outgoing'] = true;
      Navigator.push(
          context,
          MaterialPageRoute(
              builder: (_) => CallScreen(callData: callData)));
    } else if (mounted) {
      showToast(context, res['message'] ?? 'فشل الاتصال');
    }
  }

  void _beginEditMessage(NovaMessage msg) {
    if (!mounted || msg.isDeleted || msg.type != 'text') return;
    setState(() {
      _editingMessage = msg;
      _replyingTo = null;
      _ctrl.text = msg.body ?? '';
      _ctrl.selection = TextSelection.collapsed(offset: _ctrl.text.length);
    });
    FocusScope.of(context).requestFocus(FocusNode());
  }

  Future<void> _saveEditedMessage() async {
    final msg = _editingMessage;
    final newBody = _ctrl.text.trim();
    if (msg == null || newBody.isEmpty) return;
    final res =
        await ApiService.put('/messages/${msg.id}', body: {'body': newBody});
    if (!mounted) return;
    if (res['success'] == true) {
      setState(() {
        _editingMessage = null;
        _ctrl.clear();
      });
      await _refresh();
      if (mounted) showToast(context, 'تم تحديث الرسالة');
    } else {
      showToast(context, res['message'] ?? 'فشل التعديل');
    }
  }

  void _cancelComposerMode() {
    if (!mounted) return;
    setState(() {
      _editingMessage = null;
      _replyingTo = null;
      _ctrl.clear();
    });
  }

  void _beginReply(NovaMessage msg) {
    if (!mounted || msg.isDeleted) return;
    setState(() {
      _editingMessage = null;
      _replyingTo = msg;
    });
    FocusScope.of(context).requestFocus(FocusNode());
  }

  /// نافذة اختيار نوع الحذف مثل واتساب: لدي فقط / لدى الجميع
  Future<void> _showDeleteSheet(NovaMessage msg) async {
    final me = context.read<AuthProvider>().user;
    final isMine = msg.senderId == me?.id;
    final c = NovaColors.of(context);
    final choice = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withOpacity(0.42),
      builder: (ctx) => SafeArea(
        child: Container(
          decoration: BoxDecoration(
            color: c.surface,
            borderRadius:
                const BorderRadius.vertical(top: Radius.circular(25)),
          ),
          padding: EdgeInsets.fromLTRB(
              16, 12, 16, MediaQuery.paddingOf(ctx).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(
                child: Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(
                        color: c.line,
                        borderRadius: BorderRadius.circular(5))),
              ),
              const SizedBox(height: 15),
              Text('حذف الرسالة',
                  style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: c.text)),
              const SizedBox(height: 14),
              if (isMine)
                PressScale(
                  onTap: () => Navigator.pop(ctx, 'for_all'),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Row(children: [
                      Icon(Icons.delete_sweep_outlined,
                          color: Colors.redAccent, size: 22),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text('حذف لدى الجميع',
                            style: TextStyle(
                                fontSize: 15.5,
                                color: c.text,
                                fontWeight: FontWeight.w700)),
                      ),
                    ]),
                  ),
                ),
              PressScale(
                onTap: () => Navigator.pop(ctx, 'for_me'),
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Row(children: [
                    Icon(Icons.delete_outline,
                        color: c.muted, size: 22),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text('حذف لدي فقط',
                          style: TextStyle(
                              fontSize: 15.5,
                              color: c.text,
                              fontWeight: FontWeight.w700)),
                    ),
                  ]),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (!mounted) return;
    if (isMine && choice == 'for_all') {
      await _deleteMessage(msg, forAll: true);
    } else {
      // لدي فقط (سواء رسالة مرسلة أو مستلمة): حذف شخصي لدي
      await _deleteMessagePersonal(msg);
    }
  }

  /// حذف لدى الجميع — يختفي عند كل الأطراف
  Future<void> _deleteMessage(NovaMessage msg, {required bool forAll}) async {
    final res = await ApiService.delete('/messages/${msg.id}', body: {
      'for_all': forAll,
    });
    if (!mounted) return;
    if (res['success'] == true) {
      await _refresh();
      if (mounted)
        showToast(context,
            forAll ? 'تم حذف الرسالة لدى الجميع' : 'تم حذف الرسالة لديك');
    } else if (mounted) {
      showToast(context, res['message'] ?? 'فشل الحذف');
    }
  }

  /// حذف لدي فقط — الرسالة تبقى لدى الطرف الآخر لكن تختفي عندي
  Future<void> _deleteMessagePersonal(NovaMessage msg) async {
    // لا يوجد endpoint مخصص بعد — نستخدم delete مع for_all: false
    // الخادم يحدد حسب المرسل: إذا لم تكن أنت المرسل يُسجل حذف شخصي (deleted_for_me)
    final res = await ApiService.delete('/messages/${msg.id}', body: {
      'for_all': false,
    });
    if (!mounted) return;
    if (res['success'] == true) {
      await _refresh();
      if (mounted) showToast(context, 'تم حذف الرسالة لديك');
    } else if (mounted) {
      showToast(context, res['message'] ?? 'فشل الحذف');
    }
  }

  Future<void> _showDisappearSheet() async {
    final selected = await showModalBottomSheet<int?>(
      context: context,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withOpacity(0.42),
      builder: (c) {
        final cl = NovaColors.of(c);
        return Container(
          decoration: BoxDecoration(
            color: cl.surface,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(25)),
          ),
          padding: EdgeInsets.fromLTRB(
              16, 12, 16, MediaQuery.paddingOf(c).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(
                        color: cl.line,
                        borderRadius: BorderRadius.circular(5))),
              ),
              const SizedBox(height: 15),
              Text('الرسائل المختفية',
                  style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: cl.text)),
              const SizedBox(height: 6),
              Text('تُطبق على الرسائل الجديدة فقط',
                  style: TextStyle(fontSize: 12.5, color: cl.muted)),
              const SizedBox(height: 14),
              ..._disappearOptions.map((opt) => PressScale(
                    onTap: () => Navigator.pop(c, opt['value'] as int?),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(children: [
                        Icon(opt['icon'] as IconData,
                            color: cl.accent, size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(opt['label'] as String,
                              style: TextStyle(
                                  fontSize: 15,
                                  color: cl.text,
                                  fontWeight: FontWeight.w600)),
                        ),
                      ]),
                    ),
                  )),
            ],
          ),
        );
      },
    );
    if (selected == null || !mounted) return;
    final res = await ApiService.put('/conversations/${widget.conv.id}',
        body: {'disappear_after': selected});
    if (mounted) {
      if (res['success'] == true) {
        showToast(context, 'تم حفظ الإعداد');
      } else {
        showToast(context, res['message'] ?? 'فشل الحفظ');
      }
    }
  }

  static const _disappearOptions = [
    {'label': 'دائم (لا تختفي)', 'value': 0, 'icon': Icons.inbox},
    {'label': 'بعد 24 ساعة', 'value': 86400, 'icon': Icons.timelapse},
    {'label': 'بعد القراءة', 'value': -1, 'icon': Icons.visibility},
  ];

  Future<void> _showMessageMenu(NovaMessage msg, Offset globalPosition) async {
    final me = context.read<AuthProvider>().user;
    final isMine = msg.senderId == me?.id;
    final canCopy = !msg.isDeleted &&
        (msg.type == 'text' || (msg.body?.trim().isNotEmpty ?? false));
    final canEdit = isMine && !msg.isDeleted && msg.type == 'text';
    final size = MediaQuery.sizeOf(context);
    final position = RelativeRect.fromLTRB(
      globalPosition.dx.clamp(8.0, size.width - 8),
      globalPosition.dy.clamp(8.0, size.height - 8),
      (size.width - globalPosition.dx).clamp(8.0, size.width - 8),
      (size.height - globalPosition.dy).clamp(8.0, size.height - 8),
    );
    final choice = await showMenu<String>(
      context: context,
      position: position,
      color: NovaColors.of(context).surface,
      elevation: 8,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      items: [
        _contextItem('reply', Icons.reply, 'الرد'),
        _contextItem('forward', Icons.forward, 'إعادة التوجيه'),
        if (canCopy) _contextItem('copy', Icons.copy, 'نسخ'),
        if (canEdit) _contextItem('edit', Icons.edit_outlined, 'تعديل'),
        _contextItem('delete', Icons.delete_outline, 'حذف'),
        _contextItem('pin', Icons.push_pin_outlined,
            _pinnedMessageIds.contains(msg.id) ? 'إلغاء التثبيت' : 'تثبيت'),
        _contextItem('star', Icons.star_border,
            _starredMessageIds.contains(msg.id) ? 'إزالة التمييز' : 'تمييز'),
        _contextItem('info', Icons.info_outline, 'معلومات الرسالة'),
        _contextItem('more', Icons.more_horiz, 'المزيد'),
      ],
    );
    if (!mounted || choice == null) return;
    switch (choice) {
      case 'reply':
        _beginReply(msg);
        break;
      case 'forward':
        showToast(context, 'اختر محادثة لإعادة توجيه الرسالة');
        break;
      case 'copy':
        await Clipboard.setData(ClipboardData(text: msg.body ?? ''));
        if (mounted) showToast(context, 'تم نسخ الرسالة');
        break;
      case 'edit':
        _beginEditMessage(msg);
        break;
      case 'delete':
        _showDeleteSheet(msg);
        break;
      case 'pin':
        setState(() {
          if (!_pinnedMessageIds.add(msg.id)) _pinnedMessageIds.remove(msg.id);
        });
        showToast(
            context,
            _pinnedMessageIds.contains(msg.id)
                ? 'تم تثبيت الرسالة'
                : 'تم إلغاء التثبيت');
        break;
      case 'star':
        setState(() {
          if (!_starredMessageIds.add(msg.id))
            _starredMessageIds.remove(msg.id);
        });
        showToast(
            context,
            _starredMessageIds.contains(msg.id)
                ? 'تم تمييز الرسالة'
                : 'تم إلغاء التمييز');
        break;
      case 'info':
        _showMessageInfo(msg);
        break;
      case 'more':
        showToast(context, 'المزيد من خيارات الرسالة قريباً');
        break;
    }
  }

  PopupMenuItem<String> _contextItem(
      String value, IconData icon, String label) {
    final c = NovaColors.of(context);
    return PopupMenuItem<String>(
      value: value,
      height: 44,
      child: Row(children: [
        Icon(icon, size: 19, color: c.text),
        const SizedBox(width: 12),
        Text(label, style: TextStyle(color: c.text, fontSize: 14)),
      ]),
    );
  }

  void _showMessageInfo(NovaMessage msg) {
    showDialog<void>(
      context: context,
      builder: (ctx) {
        final c = NovaColors.of(ctx);
        return AlertDialog(
          backgroundColor: c.surface,
          title: Text('معلومات الرسالة', style: TextStyle(color: c.text)),
          content: Text(
            'الحالة: ${msg.status}\n'
            'النوع: ${msg.type}\n'
            'الوقت: ${msg.createdAt}\n'
            '${msg.isEdited ? 'تم تعديلها' : 'غير معدلة'}',
            style: TextStyle(color: c.text, height: 1.7),
          ),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: Text('إغلاق', style: TextStyle(color: c.accent))),
          ],
        );
      },
    );
  }

  List<NovaMessage> get _searchResults {
    final query = _searchQuery.trim().toLowerCase();
    if (query.isEmpty) return const <NovaMessage>[];
    return _messages
        .where((m) => (m.body ?? '').toLowerCase().contains(query))
        .toList();
  }

  void _startSearch() {
    setState(() {
      _isSearching = true;
      _searchQuery = '';
      _searchIndex = 0;
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) FocusScope.of(context).requestFocus(FocusNode());
    });
  }

  void _closeSearch() {
    setState(() {
      _isSearching = false;
      _searchQuery = '';
      _searchIndex = 0;
      _searchCtrl.clear();
    });
  }

  void _moveSearch(int direction) {
    final results = _searchResults;
    if (results.isEmpty) return;
    setState(() {
      _searchIndex = (_searchIndex + direction) % results.length;
      if (_searchIndex < 0) _searchIndex = results.length - 1;
    });
    _scrollToMessage(results[_searchIndex]);
  }

  void _scrollToMessage(NovaMessage msg) {
    final index = _messages.indexWhere((m) => m.id == msg.id);
    if (index < 0 || !_scroll.hasClients) return;
    final target = (index * 88.0).clamp(0.0, _scroll.position.maxScrollExtent);
    _scroll.animateTo(target,
        duration: const Duration(milliseconds: 260), curve: Curves.easeOut);
  }

  Widget _buildSearchHeader(NovaColors c) {
    final results = _searchResults;
    return Material(
      color: c.surface,
      elevation: 5,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          child: Row(children: [
            IconBtn(icon: Icons.arrow_back, onTap: _closeSearch),
            Expanded(
              child: TextField(
                controller: _searchCtrl,
                autofocus: true,
                onChanged: (value) => setState(() {
                  _searchQuery = value;
                  _searchIndex = 0;
                }),
                style: TextStyle(color: c.text),
                decoration: InputDecoration(
                  hintText: 'بحث داخل المحادثة',
                  hintStyle: TextStyle(color: c.muted),
                  border: InputBorder.none,
                  suffixText: _searchQuery.trim().isEmpty
                      ? null
                      : '${results.isEmpty ? 0 : _searchIndex + 1}/${results.length}',
                  suffixStyle: TextStyle(color: c.muted, fontSize: 12),
                ),
              ),
            ),
            IconBtn(
                icon: Icons.keyboard_arrow_up, onTap: () => _moveSearch(-1)),
            IconBtn(
                icon: Icons.keyboard_arrow_down, onTap: () => _moveSearch(1)),
          ]),
        ),
      ),
    );
  }

  Future<void> _showChatMenu() async {
    final size = MediaQuery.sizeOf(context);
    final top = MediaQuery.paddingOf(context).top + 54;
    final choice = await showMenu<String>(
      context: context,
      position: RelativeRect.fromLTRB(size.width - 270, top, 8, 8),
      color: NovaColors.of(context).surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      items: [
        _contextItem('contact', Icons.person_outline, 'عرض جهة الاتصال'),
        _contextItem('search', Icons.search, 'بحث'),
        _contextItem('media', Icons.photo_library_outlined,
            'الوسائط والروابط والمستندات'),
        _contextItem('mute', Icons.notifications_off_outlined, 'كتم الإشعارات'),
        _contextItem('theme', Icons.palette_outlined, 'سمة الدردشة'),
        _contextItem('timer', Icons.timer_outlined, 'الرسائل المؤقتة'),
        _contextItem('more', Icons.more_horiz, 'المزيد'),
        _contextItem('report', Icons.flag_outlined, 'إبلاغ'),
        _contextItem('block', Icons.block, 'حظر'),
        _contextItem('clear', Icons.delete_sweep_outlined, 'مسح محتوى الدردشة'),
        _contextItem('move', Icons.drive_file_move_outlined, 'نقل الدردشة'),
      ],
    );
    if (!mounted || choice == null) return;
    switch (choice) {
      case 'contact':
        _showContactDetails();
        break;
      case 'search':
        _startSearch();
        break;
      case 'media':
        _showChatMedia();
        break;
      case 'mute':
        await _toggleMute();
        break;
      case 'theme':
        _showChatTheme();
        break;
      case 'timer':
        _showDisappearSheet();
        break;
      case 'clear':
        setState(() => _messages.clear());
        showToast(context, 'تم مسح محتوى المحادثة من هذا الجهاز');
        break;
      case 'report':
        await _reportUser();
        break;
      case 'block':
        await _confirmBlock();
        break;
      case 'more':
      case 'move':
        showToast(context, 'الخيار متاح من إعدادات المحادثة');
        break;
    }
  }

  Future<void> _reportUser() async {
    // الإبلاغ الفعلي إلى POST /api/v1/reports مع اختيار السبب
    final reasons = [
      'إساءة أو تحرش',
      'محتوى مخالف',
      'انتحال شخصية',
      'بريد مزعج',
      'سبب آخر',
    ];
    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withOpacity(0.42),
      builder: (ctx) {
        final cl = NovaColors.of(ctx);
        return Container(
          decoration: BoxDecoration(
            color: cl.surface,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(25)),
          ),
          padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.paddingOf(ctx).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(
                        color: cl.line, borderRadius: BorderRadius.circular(5))),
              ),
              const SizedBox(height: 15),
              Text('لماذا تُبلّغ عن ${widget.conv.name}؟',
                  style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: cl.text)),
              const SizedBox(height: 10),
              ...reasons.map((r) => PressScale(
                    onTap: () => Navigator.pop(ctx, r),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(children: [
                        const Icon(Icons.flag_outlined, size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(r,
                              style: TextStyle(fontSize: 15, color: cl.text, fontWeight: FontWeight.w600)),
                        ),
                      ]),
                    ),
                  )),
            ],
          ),
        );
      },
    );
    if (!mounted || selected == null) return;
    try {
      final res = await ApiService.post('/reports', body: {
        'reported_user_id': widget.conv.otherUserId ?? 0,
        'conversation_id': widget.conv.id,
        'reason': selected,
      }).timeout(const Duration(seconds: 15)).then((r) => r);
      if (mounted) {
        if (res['success'] == true) {
          showToast(context, 'تم تسجيل البلاغ وسيتم مراجعته من قبل الإدارة');
        } else {
          showToast(context, res['message'] ?? 'فشل تسجيل البلاغ');
        }
      }
    } catch (_) {
      if (mounted) showToast(context, 'تعذر تسجيل البلاغ. تحقق من اتصالك.');
    }
  }

  Future<void> _confirmBlock() async {
    // الحظر الفعلي: POST /users/{id}/block مع نافذة تأكيد
    final targetId = widget.conv.otherUserId;
    if (targetId == null) {
      if (mounted) showToast(context, 'المستخدم غير محدد');
      return;
    }
    final c = NovaColors.of(context);
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: NovaColors.of(ctx).surface,
        title: Text('حظر ${widget.conv.name}', style: TextStyle(color: c.text)),
        content: Text(
            'لن يتمكن هذا المستخدم من مراسلتك أو الاتصال بك. يمكنك فك الحظر لاحقًا من إعدادات الخصوصية.',
            style: TextStyle(color: c.text, fontSize: 14)),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: Text('إلغاء', style: TextStyle(color: c.muted))),
          TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('حظر', style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.w700))),
        ],
      ),
    );
    if (!mounted || confirm != true) return;
    try {
      final res = await ApiService.post('/users/$targetId/block', body: {})?.timeout(const Duration(seconds: 15)) ?? {'success': false, 'message': 'تعذر الاتصال'};
      if (mounted) {
        if ((res['success'] ?? false) == true) {
          showToast(context, 'تم حظر ${widget.conv.name}');
          if (mounted) Navigator.pop(context);
        } else {
          showToast(context, (res['message'] as String?) ?? 'فشل حظر المستخدم');
        }
      }
    } catch (_) {
      if (mounted) showToast(context, 'تعذر حظر المستخدم. تحقق من اتصالك.');
    }
  }

  Future<void> _toggleMute() async {
    try {
      final res = await ApiService.post('/conversations/${widget.conv.id}/mute',
          body: {'muted': !_chatMuted});
      if (mounted) {
        if (res['success'] == true) setState(() => _chatMuted = !_chatMuted);
        showToast(
            context,
            res['success'] == true
                ? 'تم تحديث كتم الإشعارات'
                : 'تعذر تحديث الكتم');
      }
    } catch (_) {
      if (mounted) showToast(context, 'تعذر تحديث كتم الإشعارات');
    }
  }

  void _showContactDetails() async {
    final otherId = widget.conv.otherUserId;
    if (otherId == null) return;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (c) => const Center(child: CircularProgressIndicator()),
    );

    try {
      final res = await ApiService.get('/users/$otherId');
      if (!mounted) return;
      Navigator.pop(context);

      if (res['success'] == true) {
        final data = res['data'];
        if (!mounted) return;
        showDialog(
          context: context,
          builder: (ctx) {
            final c = NovaColors.of(ctx);
            final tz = Provider.of<AuthProvider>(context, listen: false).timezoneOffsetMinutes;
            return AlertDialog(
              backgroundColor: c.surface,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              title: const Text('معلومات الملف الشخصي', textAlign: TextAlign.center),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  NovaAvatar(
                    imageUrl: data['avatar'],
                    letter: data['display_name'] ?? data['name'] ?? '؟',
                    size: 80,
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Flexible(
                        child: Text(
                          data['display_name'] ?? data['name'] ?? '؟',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: c.text),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (data['is_verified'] == true) ...[
                        const SizedBox(width: 4),
                        const Icon(Icons.verified, color: Colors.blue, size: 18),
                      ],
                    ],
                  ),
                  if (data['username'] != null)
                    Text('@${data['username']}', style: const TextStyle(color: Colors.grey, fontSize: 14)),
                  const SizedBox(height: 12),
                  const Divider(),
                  if (data['phone'] != null)
                    ListTile(
                      leading: Icon(Icons.phone, size: 20, color: c.accent),
                      title: const Text('رقم الهاتف', style: TextStyle(fontSize: 10, color: Colors.grey)),
                      subtitle: Text(data['phone'], style: TextStyle(fontSize: 14, color: c.text)),
                      dense: true,
                    ),
                  if (data['email'] != null)
                    ListTile(
                      leading: Icon(Icons.email, size: 20, color: c.accent),
                      title: const Text('البريد الإلكتروني', style: TextStyle(fontSize: 10, color: Colors.grey)),
                      subtitle: Text(data['email'], style: TextStyle(fontSize: 14, color: c.text)),
                      dense: true,
                    ),
                  if (data['bio'] != null && data['bio'].toString().isNotEmpty)
                    ListTile(
                      leading: Icon(Icons.info_outline, size: 20, color: c.accent),
                      title: const Text('الوصف', style: TextStyle(fontSize: 10, color: Colors.grey)),
                      subtitle: Text(data['bio'], style: TextStyle(color: c.text)),
                      dense: true,
                    ),
                  const SizedBox(height: 8),
                  Text(
                    data['is_online'] == true
                        ? 'متصل الآن'
                        : (data['last_seen'] != null
                            ? 'آخر ظهور: ${formatLastSeen(data['last_seen'], utcOffsetMinutes: tz)}'
                            : 'غير متصل'),
                    style: TextStyle(
                      color: data['is_online'] == true ? Colors.green : Colors.grey,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: Text('إغلاق', style: TextStyle(color: c.accent)),
                ),
              ],
            );
          },
        );
      } else {
        showToast(context, 'تعذر جلب بيانات الملف الشخصي');
      }
    } catch (e) {
      if (mounted) {
        Navigator.pop(context);
        showToast(context, 'خطأ: $e');
      }
    }
  }

  Future<void> _showChatTheme() async {
    const colors = [
      Color(0xFFF6F8FB),
      Color(0xFFE8F5E9),
      Color(0xFFFFF3E0),
      Color(0xFFE3F2FD),
      Color(0xFFF3E5F5),
      Color(0xFFECEFF1)
    ];
    await showDialog<void>(
      context: context,
      builder: (ctx) {
        final c = NovaColors.of(ctx);
        return AlertDialog(
          backgroundColor: c.surface,
          title: Text('سمة الدردشة', style: TextStyle(color: c.text)),
          content: Wrap(spacing: 12, runSpacing: 12, children: [
            for (final color in colors)
              GestureDetector(
                onTap: () {
                  _saveChatTheme(color);
                  Navigator.pop(ctx);
                },
                child: Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                        color: color,
                        shape: BoxShape.circle,
                        border: Border.all(color: c.line))),
              ),
          ]),
        );
      },
    );
  }

  void _showChatMedia() {
    final media =
        _messages.where((m) => m.type == 'image' || m.type == 'video').toList();
    final docs = _messages.where((m) => m.type == 'file').toList();
    final links = _messages
        .where((m) => RegExp(r'https?://\\S+').hasMatch(m.body ?? ''))
        .toList();
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        final c = NovaColors.of(ctx);
        return DefaultTabController(
          length: 3,
          child: Container(
            height: MediaQuery.sizeOf(ctx).height * .72,
            decoration: BoxDecoration(
                color: c.surface,
                borderRadius:
                    const BorderRadius.vertical(top: Radius.circular(24))),
            child: Column(children: [
              Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 8, 0),
                  child: Row(children: [
                    Expanded(
                        child: Text('محتوى المحادثة',
                            style: TextStyle(
                                color: c.text,
                                fontSize: 18,
                                fontWeight: FontWeight.w800))),
                    IconButton(
                        onPressed: () => Navigator.pop(ctx),
                        icon: Icon(Icons.close, color: c.muted))
                  ])),
              TabBar(
                  labelColor: c.accent,
                  unselectedLabelColor: c.muted,
                  tabs: const [
                    Tab(text: 'الوسائط'),
                    Tab(text: 'الروابط'),
                    Tab(text: 'المستندات')
                  ]),
              Expanded(
                  child: TabBarView(children: [
                _mediaList(ctx, media, c, true),
                _mediaList(ctx, links, c, false),
                _mediaList(ctx, docs, c, false)
              ])),
            ]),
          ),
        );
      },
    );
  }

  Widget _mediaList(
      BuildContext ctx, List<NovaMessage> items, NovaColors c, bool visual) {
    if (items.isEmpty)
      return Center(
          child: Text('لا يوجد محتوى بعد', style: TextStyle(color: c.muted)));
    return ListView.builder(
      padding: const EdgeInsets.all(14),
      itemCount: items.length,
      itemBuilder: (_, i) {
        final msg = items[i];
        return ListTile(
          leading: visual &&
                  msg.type == 'image' &&
                  ApiService.mediaUrl(msg.filePath).isNotEmpty
              ? Image.network(ApiService.mediaUrl(msg.filePath),
                  width: 52,
                  height: 52,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) =>
                      Icon(Icons.broken_image, color: c.muted))
              : Icon(
                  msg.type == 'video'
                      ? Icons.videocam
                      : Icons.insert_drive_file,
                  color: c.accent),
          title: Text(
              msg.body ??
                  (msg.type == 'video'
                      ? 'فيديو'
                      : msg.type == 'image'
                          ? 'صورة'
                          : 'مستند'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: c.text)),
          subtitle: Text('فتح الرسالة الأصلية',
              style: TextStyle(color: c.muted, fontSize: 11)),
          onTap: () {
            Navigator.pop(ctx);
            _scrollToMessage(msg);
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.read<AuthProvider>();
    return Scaffold(
      backgroundColor: c.bg,
      resizeToAvoidBottomInset: false, // نمنع Scaffold من تغيير حجمه عند ظهور لوحة المفاتيح
      body: Stack(
        children: [
          Column(
            children: [
              SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                    decoration: BoxDecoration(
                      color: c.surface,
                      borderRadius: BorderRadius.circular(22),
                      border: Border.all(color: c.line),
                      boxShadow: [
                        BoxShadow(
                          color: c.shadow,
                          blurRadius: 18,
                          offset: const Offset(0, 6),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        IconBtn(
                          icon: Icons.arrow_back,
                          onTap: () => Navigator.pop(context),
                        ),
                        NovaAvatar(
                          letter: widget.conv.name.isNotEmpty
                              ? widget.conv.name[0]
                              : '?',
                          imageUrl: widget.conv.avatar,
                          size: 42,
                          radius: 15,
                          online: widget.conv.isOnline,
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: PressScale(
                            onTap: () {
                              if (widget.conv.isGroup && widget.conv.groupId != null) {
                                pushScreen(context,
                                    GroupInfoScreen(groupId: widget.conv.groupId!));
                              } else {
                                _startCall('voice');
                              }
                            },
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Flexible(
                                      child: Text(
                                        widget.conv.name,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: TextStyle(
                                          fontSize: 15.5,
                                          fontWeight: FontWeight.w900,
                                          color: c.text,
                                        ),
                                      ),
                                    ),
                                    if (widget.conv.isVerified) ...[
                                      const SizedBox(width: 4),
                                      const Icon(Icons.verified,
                                          color: Color(0xFF5B6CFF), size: 14),
                                    ],
                                  ],
                                ),
                                const SizedBox(height: 3),
                                Row(
                                  children: _localTypingUsers.isNotEmpty
                                      ? [
                                          Container(
                                            width: 6,
                                            height: 6,
                                            decoration: BoxDecoration(
                                              color: c.accent,
                                              shape: BoxShape.circle,
                                            ),
                                          ),
                                          const SizedBox(width: 5),
                                          Flexible(
                                            child: Text(
                                              'يكتب الآن...',
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: c.accent,
                                                fontWeight: FontWeight.w600,
                                              ),
                                            ),
                                          ),
                                        ]
                                      : [
                                          Container(
                                            width: 6,
                                            height: 6,
                                            decoration: BoxDecoration(
                                              color: widget.conv.isOnline
                                                  ? c.green
                                                  : c.muted,
                                              shape: BoxShape.circle,
                                            ),
                                          ),
                                          const SizedBox(width: 5),
                                          Flexible(
                                            child: Text(
                                              widget.conv.isOnline
                                                  ? 'متصل الآن'
                                                  : formatLastSeen(
                                                      widget.conv.lastSeen,
                                                      utcOffsetMinutes: Provider.of<AuthProvider>(context, listen: false).timezoneOffsetMinutes),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: TextStyle(
                                                fontSize: 11,
                                                color: widget.conv.isOnline
                                                    ? c.green
                                                    : c.muted,
                                              ),
                                            ),
                                          ),
                                        ],
                                ),
                              ],
                            ),
                          ),
                        ),
                        if (auth.effectiveAppSettings.allowCalls)
                          IconBtn(
                            icon: Icons.phone_outlined,
                            onTap: () => _startCall('voice'),
                          ),
                        if (auth.effectiveAppSettings.allowCalls)
                          IconBtn(
                            icon: Icons.videocam_outlined,
                            onTap: () => _startCall('video'),
                          ),
                        IconBtn(
                          icon: Icons.more_horiz,
                          onTap: _showChatMenu,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              Expanded(
                child: NotificationListener<ScrollNotification>(
                  onNotification: (n) {
                    if (n is ScrollStartNotification &&
                        n.metrics.pixels == 0 &&
                        !_loading) {
                      _load();
                    }
                    return false;
                  },
                  child: LayoutBuilder(
                    builder: (context, cons) => Container(
                      decoration: BoxDecoration(
                        color: _chatBackgroundArgb != null
                            ? Color(_chatBackgroundArgb!)
                            : c.bg,
                        gradient: RadialGradient(
                          center: const Alignment(-0.6, -0.6),
                          radius: 0.6,
                          colors: [
                            c.accent.withOpacity(0.07),
                            Colors.transparent,
                          ],
                        ),
                      ),
                      child: ListView.builder(
                        controller: _scroll,
                        padding: const EdgeInsets.fromLTRB(14, 18, 14, 10),
                        itemCount: _messages.length + (_loading ? 1 : 0) + 1,
                        itemBuilder: (_, i) {
                          if (i == 0) {
                            return Center(
                              child: Container(
                                margin: const EdgeInsets.only(bottom: 15),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 12, vertical: 6),
                                decoration: BoxDecoration(
                                  color: c.surface,
                                  borderRadius: BorderRadius.circular(20),
                                  boxShadow: [
                                    BoxShadow(
                                        color: Colors.black.withOpacity(0.04),
                                        blurRadius: 10),
                                  ],
                                ),
                                child: const Text(
                                  'اليوم',
                                  style: TextStyle(
                                      fontSize: 11,
                                      color: Color(0xFF667085),
                                      fontWeight: FontWeight.w600),
                                ),
                              ),
                            );
                          }
                          final mi = i - 1;
                          if (mi == _messages.length) {
                            return const Center(
                                child: Padding(
                                    padding: EdgeInsets.all(12),
                                    child: CircularProgressIndicator()));
                          }
                          final msg = _messages[mi];
                          final isMine = msg.senderId == auth.user?.id;
                          return GestureDetector(
                            onLongPressStart: (details) =>
                                _showMessageMenu(msg, details.globalPosition),
                            child: _Bubble(
                              msg: msg,
                              isMine: isMine,
                              maxWidth: cons.maxWidth * 0.82,
                              colors: c,
                              searchQuery: _searchQuery,
                            ),
                          );
                        },
                      ),
                    ),
                  ),
                ),
              ),
              Padding(
                padding: EdgeInsets.fromLTRB(
                    10, 2, 10, MediaQuery.paddingOf(context).bottom + 10),
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                  decoration: BoxDecoration(
                    color: c.surface,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: c.line),
                    boxShadow: [
                      BoxShadow(
                        color: c.shadow,
                        blurRadius: 18,
                        offset: const Offset(0, -4),
                      ),
                    ],
                  ),
                  child: Row(
                  children: [
                    if (!_isRecording)
                      IconBtn(
                        icon: Icons.add_circle_outline,
                        onTap: () => openAttachSheet(
                          context,
                          onImage: _pickMedia,
                          onDocument: _pickDocument,
                          onVideo: _pickVideo,
                          onAudio: _pickAudio,
                          onLocation: _shareLocation,
                          onContact: _shareContact,
                        ),
                      ),
                    if (!_isRecording) const SizedBox(width: 7),
                    Expanded(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          if (_editingMessage != null || _replyingTo != null)
                            _buildComposerContext(c),
                          if (_isRecording)
                            _buildRecordingIndicator(c)
                          else
                            SizedBox(
                              height: 44,
                              child: TextField(
                                controller: _ctrl,
                                textInputAction: TextInputAction.send,
                                onSubmitted: (_) => _sendMessage(),
                                style: TextStyle(color: c.text, fontSize: 14),
                                cursorColor: c.accent,
                                textAlignVertical: TextAlignVertical.center,
                                decoration: InputDecoration(
                                  isCollapsed: true,
                                  hintText: _editingMessage != null
                                      ? 'عدّل الرسالة...'
                                      : 'اكتب رسالة...',
                                  hintStyle:
                                      TextStyle(color: c.muted, fontSize: 14),
                                  filled: true,
                                  fillColor: c.surface2,
                                  contentPadding: const EdgeInsets.symmetric(
                                      horizontal: 14, vertical: 12),
                                  border: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(17),
                                    borderSide: BorderSide(color: c.line),
                                  ),
                                  enabledBorder: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(17),
                                    borderSide: BorderSide(color: c.line),
                                  ),
                                  focusedBorder: OutlineInputBorder(
                                    borderRadius: BorderRadius.circular(17),
                                    borderSide: BorderSide(
                                        color: c.accent.withOpacity(0.6)),
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 7),
                    if (!_isRecording)
                      IconBtn(
                          icon: Icons.emoji_emotions_outlined,
                          onTap: _addEmoji),
                    if (!_isRecording) const SizedBox(width: 7),
                    PressScale(
                      onTap:
                          _isRecording || (!_hasText && _editingMessage == null)
                              ? null
                              : _sendMessage,
                      onLongPressStart: (_) => _startRecording(),
                      onLongPressMoveUpdate: (d) =>
                          _toggleCancelRecording(d.localPosition),
                      onLongPressEnd: (_) => _sendVoice(),
                      behavior: HitTestBehavior.opaque,
                      child: Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: _isRecording && _cancelRecording
                              ? Colors.red
                              : c.accent,
                          borderRadius: BorderRadius.circular(16),
                          boxShadow: [
                            BoxShadow(
                              color: c.accent.withOpacity(0.35),
                              blurRadius: 14,
                              offset: const Offset(0, 5),
                            ),
                          ],
                        ),
                        child: Icon(
                          _isRecording
                              ? (_cancelRecording
                                  ? Icons.delete_outline
                                  : Icons.mic)
                              : (_hasText || _editingMessage != null
                                  ? Icons.send
                                  : Icons.mic),
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            ],
          ),
          if (_isSearching)
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: _buildSearchHeader(c),
            ),
          if (_incomingCall != null)
            Positioned.fill(
              child: IncomingCallOverlay(
                call: _incomingCall!,
                onAnswer: _acceptIncomingCall,
                onReject: _rejectIncomingCall,
              ),
            ),
        ],
      ),
    );
  }

  void _addEmoji() {
    _ctrl.text = '${_ctrl.text} 😊';
    _ctrl.selection = TextSelection.collapsed(offset: _ctrl.text.length);
  }

  Widget _buildComposerContext(NovaColors c) {
    final msg = _editingMessage ?? _replyingTo;
    final label =
        _editingMessage != null ? 'تعديل الرسالة' : 'الرد على الرسالة';
    return Container(
      margin: const EdgeInsets.only(bottom: 5),
      padding: const EdgeInsetsDirectional.only(
          start: 10, end: 5, top: 5, bottom: 5),
      decoration: BoxDecoration(
        color: c.accent.withOpacity(0.10),
        borderRadius: BorderRadius.circular(12),
        border: BorderDirectional(start: BorderSide(color: c.accent, width: 3)),
      ),
      child: Row(children: [
        Icon(_editingMessage != null ? Icons.edit : Icons.reply,
            size: 16, color: c.accent),
        const SizedBox(width: 6),
        Expanded(
          child: Text('$label: ${msg?.body ?? msg?.type ?? ''}',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: c.text, fontSize: 12)),
        ),
        IconButton(
          visualDensity: VisualDensity.compact,
          onPressed: _cancelComposerMode,
          icon: Icon(Icons.close, size: 18, color: c.muted),
        ),
      ]),
    );
  }

  /// مؤشر التسجيل بأسلوب الواتساب: عداد حي وموجة وإشارة إلغاء بالسحب.
  Widget _buildRecordingIndicator(NovaColors c) {
    final mins = _recordElapsed.inMinutes;
    final secs = _recordElapsed.inSeconds % 60;
    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: _cancelRecording ? Colors.red.shade50 : c.surface2,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(children: [
        Icon(_cancelRecording ? Icons.delete_outline : Icons.mic,
            color: _cancelRecording ? Colors.red : c.accent, size: 18),
        const SizedBox(width: 7),
        Text('$mins:${secs.toString().padLeft(2, '0')}',
            style: TextStyle(color: c.text, fontWeight: FontWeight.w700)),
        const SizedBox(width: 8),
        Expanded(
          child: SizedBox(
            height: 28,
            child: CustomPaint(
              painter: _WaveformPainter(
                progress:
                    0.25 + ((_recordElapsed.inMilliseconds ~/ 90) % 60) / 100,
                active: !_cancelRecording,
                foreground: _cancelRecording ? Colors.red : c.accent,
                background: c.muted.withOpacity(0.35),
              ),
            ),
          ),
        ),
        const SizedBox(width: 7),
        Text(_cancelRecording ? 'إلغاء' : 'اسحب للإلغاء',
            style: TextStyle(
                color: _cancelRecording ? Colors.red : c.muted, fontSize: 11)),
      ]),
    );
  }
}

/* ═══════════════ فقاعة فيديو بأسلوب الواتساب (تشغيل/إيقاف + شريط تقدم) ═══════════════ */
class _VideoBubble extends StatefulWidget {
  const _VideoBubble(
      {required this.path,
      this.thumbnail,
      this.duration,
      required this.isMine,
      required this.colors});
  final String? path;
  final String? thumbnail;
  final int? duration;
  final bool isMine;
  final NovaColors colors;

  @override
  State<_VideoBubble> createState() => _VideoBubbleState();
}

class _VideoBubbleState extends State<_VideoBubble> {
  VideoPlayerController? _controller;
  bool _ready = false;
  bool _loading = false;
  bool _error = false;
  String get _url => ApiService.mediaUrl(widget.path);

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    if (_loading || _ready) return;
    if (_url.isEmpty) {
      if (mounted) setState(() => _error = true);
      return;
    }
    if (mounted) {
      setState(() {
        _loading = true;
        _error = false;
      });
    }
    VideoPlayerController? ctrl;
    try {
      ctrl = VideoPlayerController.networkUrl(Uri.parse(_url));
      await ctrl.initialize();
      await ctrl.setLooping(false);
      if (!mounted) {
        await ctrl.dispose();
        return;
      }
      ctrl.addListener(() {
        if (mounted) setState(() {});
      });
      setState(() {
        _controller = ctrl;
        _ready = true;
        _loading = false;
      });
    } catch (_) {
      await ctrl?.dispose();
      if (mounted) {
        setState(() {
          _loading = false;
          _error = true;
        });
      }
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  String _fmt(int seconds) {
    final m = seconds ~/ 60;
    final s = seconds % 60;
    return '$m:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.isMine
        ? Colors.white.withOpacity(0.92)
        : const Color(0xFF101828).withOpacity(0.85);
    final ctrl = _controller;
    final duration = widget.duration;
    return SizedBox(
      width: 250,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_ready && ctrl != null && ctrl.value.isInitialized)
            Stack(
              alignment: Alignment.center,
              children: [
                GestureDetector(
                  onTap: () async {
                    try {
                      if (ctrl.value.isPlaying) {
                        await ctrl.pause();
                      } else {
                        await ctrl.play();
                      }
                      if (mounted) setState(() {});
                    } catch (_) {
                      if (mounted) {
                        showToast(context, 'تعذر تشغيل الفيديو');
                      }
                    }
                  },
                  child: AspectRatio(
                    aspectRatio: ctrl.value.aspectRatio,
                    child: VideoPlayer(ctrl),
                  ),
                ),
                if (!ctrl.value.isPlaying)
                  Container(
                    width: 54,
                    height: 54,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.95),
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                            color: Colors.black.withOpacity(0.3),
                            blurRadius: 10)
                      ],
                    ),
                    child: const Icon(Icons.play_arrow,
                        size: 34, color: Color(0xFF5B5CE2)),
                  ),
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: Container(
                    color: c,
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    child: Row(children: [
                      Icon(
                          ctrl.value.isPlaying ? Icons.pause : Icons.play_arrow,
                          color: Colors.white,
                          size: 16),
                      const SizedBox(width: 6),
                      Expanded(
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: LinearProgressIndicator(
                            minHeight: 3,
                            value: ctrl.value.position.inMilliseconds > 0 &&
                                    ctrl.value.duration.inMilliseconds > 0
                                ? (ctrl.value.position.inMilliseconds /
                                        ctrl.value.duration.inMilliseconds)
                                    .clamp(0.0, 1.0)
                                : null,
                            backgroundColor: Colors.white.withOpacity(0.3),
                            valueColor: const AlwaysStoppedAnimation<Color>(
                                Colors.white),
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        _fmt(ctrl.value.position.inSeconds),
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w700),
                      ),
                    ]),
                  ),
                ),
              ],
            )
          else
            GestureDetector(
              onTap: _error ? null : _init,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Builder(builder: (_) {
                      final thumbUrl = ApiService.mediaUrl(widget.thumbnail);
                      if (thumbUrl.isNotEmpty && !_error) {
                        return Image.network(
                          thumbUrl,
                          width: 250,
                          height: 160,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => Container(
                            width: 250,
                            height: 160,
                            color: Colors.black12,
                            alignment: Alignment.center,
                            child: const Icon(Icons.video_library_outlined,
                                size: 42, color: Colors.white70),
                          ),
                        );
                      }
                      return Container(
                        width: 250,
                        height: 160,
                        color: Colors.black12,
                        alignment: Alignment.center,
                        child: Icon(
                          _error
                              ? Icons.error_outline
                              : Icons.video_library_outlined,
                          size: 42,
                          color: _error ? Colors.redAccent : Colors.white70,
                        ),
                      );
                    }),
                  ),
                  if (_loading)
                    const SizedBox(
                      width: 36,
                      height: 36,
                      child: CircularProgressIndicator(color: Colors.white),
                    )
                  else if (!_error)
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.9),
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                              color: Colors.black.withOpacity(0.25),
                              blurRadius: 8)
                        ],
                      ),
                      child: const Icon(Icons.play_circle_fill,
                          size: 44, color: Color(0xFF5B5CE2)),
                    ),
                  if (duration != null)
                    Positioned(
                      bottom: 6,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 7, vertical: 3),
                        decoration: BoxDecoration(
                          color: Colors.black.withOpacity(0.65),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(_fmt(duration),
                            style: const TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w700)),
                      ),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _WaveformPainter extends CustomPainter {
  const _WaveformPainter({
    required this.progress,
    required this.active,
    required this.foreground,
    required this.background,
  });
  final double progress;
  final bool active;
  final Color foreground;
  final Color background;

  @override
  void paint(Canvas canvas, Size size) {
    final count = math.max(18, (size.width / 4).floor());
    final gap = size.width / count;
    final progressX = size.width * progress.clamp(0.0, 1.0);
    for (var i = 0; i < count; i++) {
      final wave = (math.sin(i * 1.73) + 1) / 2;
      final height = 5 + wave * (size.height - 7);
      final x = i * gap + gap / 2;
      final paint = Paint()
        ..color = active && x <= progressX ? foreground : background
        ..strokeWidth = math.max(1.5, gap * 0.48)
        ..strokeCap = StrokeCap.round;
      canvas.drawLine(Offset(x, (size.height - height) / 2),
          Offset(x, (size.height + height) / 2), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _WaveformPainter oldDelegate) =>
      oldDelegate.progress != progress ||
      oldDelegate.active != active ||
      oldDelegate.foreground != foreground ||
      oldDelegate.background != background;
}

/* ═══════════════ فقاعة صوت بأسلوب الواتساب (تشغيل + شريط تقدم + مدة) ═══════════════ */
class _AudioBubble extends StatefulWidget {
  const _AudioBubble(
      {required this.path,
      this.duration,
      required this.isMine,
      required this.colors});
  final String? path;
  final int? duration;
  final bool isMine;
  final NovaColors colors;

  @override
  State<_AudioBubble> createState() => _AudioBubbleState();
}

class _AudioBubbleState extends State<_AudioBubble> {
  AudioPlayer? _player;
  bool _playing = false;
  bool _loadingAudio = false;
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;
  double _speed = 1.0;
  String get _url => ApiService.mediaUrl(widget.path);

  @override
  void initState() {
    super.initState();
    final d = widget.duration;
    if (d != null && d > 0) _duration = Duration(seconds: d);
  }

  Future<void> _toggle() async {
    if (_loadingAudio) return;
    final url = _url;
    if (url.isEmpty) {
      if (mounted) showToast(context, 'ملف الصوت غير متاح');
      return;
    }

    final existing = _player;
    if (existing == null) {
      final player = AudioPlayer();
      if (mounted) setState(() => _loadingAudio = true);
      try {
        // لا نبدأ play قبل اكتمال تحميل المصدر؛ هذا يمنع race condition
        // الذي كان يؤدي إلى توقف/انهيار المشغل في بعض نسخ Android/Web.
        await player.setUrl(url);
        if (!mounted) {
          await player.dispose();
          return;
        }
        _player = player;
        _duration = player.duration ?? _duration;
        await player.setSpeed(_speed);
        player.positionStream.listen((pos) {
          if (mounted) setState(() => _position = pos);
        });
        player.playerStateStream.listen((state) {
          if (mounted) setState(() => _playing = state.playing);
        });
        player.processingStateStream.listen((state) {
          if (state == ProcessingState.completed && mounted) {
            setState(() {
              _playing = false;
              _position = _duration;
            });
          }
        });
        setState(() {
          _loadingAudio = false;
          _playing = true;
        });
        await player.play();
      } catch (_) {
        await player.dispose();
        if (mounted) {
          setState(() {
            _loadingAudio = false;
            _playing = false;
          });
          showToast(context, 'تعذر تشغيل المقطع الصوتي');
        }
      }
      return;
    }

    try {
      if (_playing) {
        await existing.pause();
        if (mounted) setState(() => _playing = false);
      } else {
        await existing.setSpeed(_speed);
        await existing.play();
        if (mounted) setState(() => _playing = true);
      }
    } catch (_) {
      if (mounted) showToast(context, 'تعذر تشغيل المقطع الصوتي');
    }
  }

  @override
  void dispose() {
    _player?.dispose();
    super.dispose();
  }

  String _fmt(Duration d) {
    final m = d.inMinutes;
    final s = d.inSeconds % 60;
    return '$m:${s.toString().padLeft(2, '0')}';
  }

  Future<void> _seek(DragUpdateDetails details, double width) async {
    final player = _player;
    if (player == null || _duration.inMilliseconds <= 0) return;
    final box = context.findRenderObject() as RenderBox?;
    if (box == null) return;
    final local = box.globalToLocal(details.globalPosition);
    final ratio = (local.dx / width).clamp(0.0, 1.0);
    await player.seek(_duration * ratio);
  }

  Future<void> _cycleSpeed() async {
    final next = _speed == 1.0 ? 1.5 : (_speed == 1.5 ? 2.0 : 1.0);
    setState(() => _speed = next);
    if (_player != null) await _player!.setSpeed(next);
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.colors;
    final progress = _duration.inMilliseconds > 0
        ? (_position.inMilliseconds / _duration.inMilliseconds).clamp(0.0, 1.0)
        : 0.0;
    final color = widget.isMine ? Colors.white : c.accent;
    return SizedBox(
      width: 250,
      child: Row(children: [
        PressScale(
          onTap: _toggle,
          child: Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: widget.isMine
                  ? Colors.white.withOpacity(0.25)
                  : c.accent.withOpacity(0.18),
              shape: BoxShape.circle,
            ),
            child: _loadingAudio
                ? SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: color,
                    ),
                  )
                : Icon(_playing ? Icons.pause : Icons.play_arrow,
                    color: color, size: 24),
          ),
        ),
        const SizedBox(width: 9),
        Expanded(
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            GestureDetector(
              onTapDown: (d) async {
                final player = _player;
                if (player == null || _duration.inMilliseconds <= 0) return;
                final ratio = (d.localPosition.dx / 140).clamp(0.0, 1.0);
                await player.seek(_duration * ratio);
              },
              child: SizedBox(
                height: 28,
                child: CustomPaint(
                  painter: _WaveformPainter(
                    progress: progress,
                    active: true,
                    foreground: color,
                    background: color.withOpacity(0.28),
                  ),
                ),
              ),
            ),
            Row(children: [
              Text('${_fmt(_position)} / ${_fmt(_duration)}',
                  style:
                      TextStyle(fontSize: 10, color: color.withOpacity(0.82))),
              const Spacer(),
              GestureDetector(
                onTap: _cycleSpeed,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(
                      color: color.withOpacity(0.18),
                      borderRadius: BorderRadius.circular(7)),
                  child: Text('${_speed}x',
                      style: TextStyle(
                          fontSize: 10,
                          color: color,
                          fontWeight: FontWeight.w700)),
                ),
              ),
            ]),
          ]),
        ),
      ]),
    );
  }
}

class _MenuChip extends StatelessWidget {
  const _MenuChip(this.icon, this.label, this.colors, this.onTap);
  final IconData icon;
  final String label;
  final NovaColors colors;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return PressScale(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
            color: colors.surface2, borderRadius: BorderRadius.circular(18)),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 23, color: colors.text),
            const SizedBox(height: 6),
            Text(label,
                style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w600,
                    color: colors.text)),
          ],
        ),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble(
      {required this.msg,
      required this.isMine,
      required this.maxWidth,
      required this.colors,
      this.searchQuery = ''});
  final NovaMessage msg;
  final bool isMine;
  final double maxWidth;
  final NovaColors colors;
  final String searchQuery;

  /// علامات القراءة: ✓ (أُرسلت) / ✓✓ رمادي (سُلمت) / ✓✓ أزرق (قُرئت)
  static List<Widget> _statusTicks(String status, NovaColors c) {
    Color color;
    String label;
    switch (status) {
      case 'read':
        {
          color = const Color(0xFF3B82F6);
          label = '✓✓';
        }
        break;
      case 'delivered':
        {
          color = const Color(0xFF6B7280);
          label = '✓✓';
        }
        break;
      case 'deleted':
        {
          color = const Color(0xFFEF4444);
          label = '✕';
        }
        break;
      default:
        {
          color = const Color(0xFF9CA3AF);
          label = '✓';
        }
    }
    return [
      const SizedBox(width: 4),
      Text(label,
          style: TextStyle(
              fontSize: 12, fontWeight: FontWeight.w700, color: color)),
    ];
  }

  String _mediaUrl(String? path) => ApiService.mediaUrl(path);

  Widget _bodyText(NovaColors c, bool isMine) {
    final text = msg.body ?? '';
    final query = searchQuery.trim();
    final baseColor = isMine ? c.mineText : c.text;
    if (query.isEmpty || text.isEmpty) {
      return Text(text,
          style: TextStyle(fontSize: 14.5, height: 1.5, color: baseColor));
    }
    final lower = text.toLowerCase();
    final needle = query.toLowerCase();
    final spans = <TextSpan>[];
    var start = 0;
    while (true) {
      final index = lower.indexOf(needle, start);
      if (index < 0) {
        if (start < text.length)
          spans.add(TextSpan(text: text.substring(start)));
        break;
      }
      if (index > start)
        spans.add(TextSpan(text: text.substring(start, index)));
      spans.add(TextSpan(
          text: text.substring(index, index + query.length),
          style: const TextStyle(
              backgroundColor: Color(0xFFFFE082), color: Colors.black)));
      start = index + query.length;
    }
    return RichText(
        text: TextSpan(
            style: TextStyle(fontSize: 14.5, height: 1.5, color: baseColor),
            children: spans));
  }

  Widget _mediaError(NovaColors c, String label) => Container(
        width: 220,
        height: 150,
        color: c.surface2,
        alignment: Alignment.center,
        child: Text(label, style: TextStyle(color: c.muted, fontSize: 12)),
      );

  @override
  Widget build(BuildContext context) {
    final c = colors;
    final time = novaServerTime(
      msg.createdAt,
      Provider.of<AuthProvider>(context, listen: false).timezoneOffsetMinutes,
    );
    return Align(
      alignment: isMine
          ? AlignmentDirectional.centerEnd
          : AlignmentDirectional.centerStart,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 3.5),
        constraints: BoxConstraints(maxWidth: maxWidth),
        padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
        decoration: BoxDecoration(
          color: isMine ? c.mine : c.surface,
          border: Border.all(
            color: isMine ? Colors.transparent : c.line,
            width: 0.8,
          ),
          borderRadius: BorderRadiusDirectional.only(
            topStart: const Radius.circular(19),
            topEnd: const Radius.circular(19),
            bottomStart: Radius.circular(isMine ? 19 : 6),
            bottomEnd: Radius.circular(isMine ? 6 : 19),
          ),
          boxShadow: [
            BoxShadow(
              color: c.shadow,
              blurRadius: 14,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (msg.isDeleted)
              Text('تم حذف هذه الرسالة',
                  style: TextStyle(
                      fontStyle: FontStyle.italic,
                      color: isMine ? c.mineText.withOpacity(0.7) : c.muted))
            else if (msg.type == 'image' && _mediaUrl(msg.filePath).isNotEmpty)
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(
                  _mediaUrl(msg.filePath),
                  width: 220,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) =>
                      _mediaError(c, 'تعذر عرض الصورة'),
                ),
              )
            else if (msg.type == 'video' && _mediaUrl(msg.filePath).isNotEmpty)
              _VideoBubble(
                  path: msg.filePath,
                  thumbnail: msg.thumbnailPath,
                  duration: msg.duration,
                  isMine: isMine,
                  colors: c)
            else if ((msg.type == 'audio' || msg.type == 'voice') &&
                _mediaUrl(msg.filePath).isNotEmpty)
              _AudioBubble(
                  path: msg.filePath,
                  duration: msg.duration,
                  isMine: isMine,
                  colors: c)
            else if (msg.type == 'file' && msg.filePath != null)
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                    color: c.accent.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(10)),
                child: Row(children: [
                  Icon(Icons.attach_file, color: c.accent, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(msg.body ?? 'ملف',
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 13, color: isMine ? c.mineText : c.text)),
                  ),
                ]),
              )
            else
              _bodyText(c, isMine),
            const SizedBox(height: 3),
            Row(mainAxisSize: MainAxisSize.min, children: [
              if (msg.isEdited)
                Text('(معدلة)  ',
                    style: TextStyle(
                        fontSize: 10,
                        color: isMine ? c.mineText.withOpacity(0.7) : c.muted)),
              if (time.isNotEmpty)
                Text('$time  ',
                    style: TextStyle(
                        fontSize: 10,
                        color: isMine ? c.mineText.withOpacity(0.7) : c.muted)),
              if (isMine) ..._statusTicks(msg.status, c),
            ]),
          ],
        ),
      ),
    );
  }
}
