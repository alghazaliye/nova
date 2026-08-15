import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';
import 'package:image_picker/image_picker.dart';
import 'package:video_player/video_player.dart';
import 'package:just_audio/just_audio.dart';
import 'package:record/record.dart';
import 'package:permission_handler/permission_handler.dart';
import '../models/user_model.dart' show NovaMessage, Conversation, formatLastSeen;
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import '../utils/sheets.dart';
import 'call_screen.dart';

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
  final ScrollController _scroll = ScrollController();
  final AudioRecorder _recorder = AudioRecorder();
  bool _loading = false;
  bool _isRecording = false;
  bool _cancelRecording = false;
  DateTime? _recordStartedAt;
  StreamSubscription<RecordState>? _recSub;
  bool _hasMore = true;
  bool _hasText = false;
  Timer? _pollTimer;
  final ImagePicker _picker = ImagePicker();
  static const Uuid _uuid = Uuid();

  @override
  void initState() {
    super.initState();
    _ctrl.addListener(() {
      final has = _ctrl.text.trim().isNotEmpty;
      if (has != _hasText) setState(() => _hasText = has);
    });
    _load();
    // Polling: تحديث تلقائي للرسائل كل 3 ثوانٍ
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted && !_loading) _refreshSilent();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _recSub?.cancel();
    _recorder.dispose();
    _ctrl.dispose();
    _scroll.dispose();
    super.dispose();
  }

  /// تسجيل صوتي بأسلوب الواتساب: ضغط مطوّل = ابدأ، سحب لليمين/الأسفل = إلغاء، رفع الإصبع = إرسال
  Future<void> _startRecording() async {
    if (_isRecording) return;
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
    final path = '/tmp/nova_voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc, bitRate: 64000, sampleRate: 16000),
      path: path,
    );
    setState(() {
      _isRecording = true;
      _cancelRecording = false;
      _recordStartedAt = DateTime.now();
    });
  }

  Future<void> _sendVoice() async {
    if (!_isRecording) return;
    await _stopRecording();
  }

  Future<void> _stopRecording() async {
    if (!_isRecording) return;
    final wasCancelled = _cancelRecording;
    _recSub?.cancel();
    final path = await _recorder.stop();
    setState(() => _isRecording = false);
    if (wasCancelled || path == null || path.isEmpty) return;
    // أرسل المقطع المسجل
    try {
      if (mounted) showToast(context, 'جاري إرسال المقطع الصوتي...');
      final file = http.MultipartFile.fromBytes(
        'attachment', await File(path).readAsBytes(),
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
    // سحب لأكثر من 80px لليمين = إلغاء التسجيل (مثل الواتساب)
    if (offset.dx > 80) {
      if (!_cancelRecording) setState(() => _cancelRecording = true);
    } else if (_cancelRecording) {
      setState(() => _cancelRecording = false);
    }
  }

  /// تحديث صامت: يجلب أحدث الرسائل الجديدة فقط ويحدّث حالة الرسائل الحالية
  Future<void> _refreshSilent() async {
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
          }
        }
      });
      // تمرير للأسفل إذا وصلت رسائل جديدة
      _scrollToBottom();
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
    setState(() => _loading = false);
  }

  Future<void> _refresh() async {
    setState(() => _hasMore = true);
    _messages.clear();
    await _load();
    _scrollToBottom();
  }

  void _scrollToBottom() {
    if (_scroll.hasClients) {
      Future.delayed(const Duration(milliseconds: 100), () {
        if (_scroll.hasClients) _scroll.jumpTo(_scroll.position.maxScrollExtent);
      });
    }
  }

  Future<void> _sendMessage() async {
    final text = _ctrl.text.trim();
    if (text.isEmpty) return;
    final clientId = _uuid.v4();
    _ctrl.clear();
    final me = context.read<AuthProvider>().user;
    final temp = NovaMessage(
      id: -1,
      uuid: clientId,
      senderId: me?.id ?? 0,
      type: 'text',
      body: text,
      status: 'sent',
      createdAt: DateTime.now().toIso8601String(),
    );
    setState(() => _messages.add(temp));
    _scrollToBottom();
    try {
      final res = await ApiService.post('/conversations/${widget.conv.id}/messages', body: {
        'client_message_id': clientId,
        'type': 'text',
        'body': text,
      });
      if (res['success'] == true && res['data'] != null) {
        setState(() {
          _messages.removeWhere((m) => m.id == -1);
          _messages.add(NovaMessage.fromJson(
              Map<String, dynamic>.from(res['data'] as Map<String, dynamic>)));
        });
        _scrollToBottom();
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(res['message'] ?? 'فشل الإرسال')));
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('خطأ في الاتصال')));
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
  String _fileUrl(String? path) {
    if (path == null || path.isEmpty) return '';
    if (path.startsWith('http')) return path;
    final base = ApiService.baseUrl.replaceAll('/api/v1', '');
    return '$base/nova/backend/storage/$path';
  }

  Future<void> _pickMedia() async {
    try {
      final XFile? f = await _picker.pickImage(source: ImageSource.gallery);
      if (f == null || !mounted) return;
      final name = f.name.split('/').last;
      final ext = name.contains('.') ? name.split('.').last.toLowerCase() : 'bin';
      final mime = _mimeFromExt(ext);
      final type = _guessType(name);
      if (!mounted) return;
      showToast(context, 'جاري رفع الملف...');
      try {
        final bytes = await f.readAsBytes();
        final res = await ApiService.uploadMultipart(
          '/conversations/${widget.conv.id}/messages',
          [
            http.MultipartFile.fromBytes(
              'attachment', bytes,
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
        final mime = _mimeFromExt(name.contains('.') ? name.split('.').last.toLowerCase() : 'bin');
        final res = await ApiService.uploadMultipart(
          '/conversations/${widget.conv.id}/media',
          [
            http.MultipartFile.fromBytes(
              'attachment', bytes,
              filename: name,
              contentType: http.MediaType.parse(mime),
            ),
          ],
          fields: {'client_message_id': _uuid.v4()},
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
      final XFile? f = await _picker.pickMedia();
      if (f == null || !mounted) return;
      final name = f.name.split('/').last;
      final ext = name.contains('.') ? name.split('.').last.toLowerCase() : '';
      if (!['mp3', 'wav', 'ogg', 'm4a', 'webm'].contains(ext)) {
        if (mounted) showToast(context, 'اختر ملف صوتي (mp3/wav/ogg/m4a)');
        return;
      }
      if (!mounted) return;
      showToast(context, 'جاري رفع المقطع الصوتي...');
      try {
        final bytes = await f.readAsBytes();
        final mime = _mimeFromExt(ext);
        final res = await ApiService.uploadMultipart(
          '/conversations/${widget.conv.id}/media',
          [
            http.MultipartFile.fromBytes(
              'attachment', bytes,
              filename: name,
              contentType: http.MediaType.parse(mime),
            ),
          ],
          fields: {'client_message_id': _uuid.v4()},
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

  Future<void> _startCall(String type) async {
    final calleeId = widget.conv.otherUserId;
    final res = await ApiService.post('/calls', body: {
      'callee_id': calleeId,
      'call_type': type,
    });
    if (!mounted) return;
    if (res['success'] == true && res['data'] != null) {
      Navigator.push(context, MaterialPageRoute(
          builder: (_) => CallScreen(callData: res['data'] as Map<String, dynamic>)));
    } else if (mounted) {
      showToast(context, res['message'] ?? 'فشل الاتصال');
    }
  }

  Future<void> _editMessage(NovaMessage msg) async {
    final ctrl = TextEditingController(text: msg.body ?? '');
    final newBody = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('تعديل الرسالة'),
        content: TextField(controller: ctrl),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, null), child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, ctrl.text.trim()),
              child: const Text('حفظ')),
        ],
      ),
    );
    if (newBody == null || newBody.isEmpty) return;
    final res = await ApiService.put('/messages/${msg.id}', body: {'body': newBody});
    if (!mounted) return;
    if (res['success'] == true) {
      await _refresh();
      if (mounted) showToast(context, 'تم تعديل الرسالة');
    } else if (mounted) {
      showToast(context, res['message'] ?? 'فشل التعديل');
    }
  }

  Future<void> _deleteMessage(NovaMessage msg) async {
    final res = await ApiService.delete('/messages/${msg.id}');
    if (!mounted) return;
    if (res['success'] == true) {
      await _refresh();
      if (mounted) showToast(context, 'تم حذف الرسالة');
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
          padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.paddingOf(c).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                    width: 42, height: 4,
                    decoration: BoxDecoration(color: cl.line, borderRadius: BorderRadius.circular(5))),
              ),
              const SizedBox(height: 15),
              Text('الرسائل المختفية',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: cl.text)),
              const SizedBox(height: 6),
              Text('تُطبق على الرسائل الجديدة فقط',
                  style: TextStyle(fontSize: 12.5, color: cl.muted)),
              const SizedBox(height: 14),
              ..._disappearOptions.map((opt) => PressScale(
                    onTap: () => Navigator.pop(c, opt['value'] as int?),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(children: [
                        Icon(opt['icon'] as IconData, color: cl.accent, size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(opt['label'] as String,
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
    if (selected == null || !mounted) return;
    final res = await ApiService.put('/conversations/${widget.conv.id}', body: {'disappear_after': selected});
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

  void _showMessageMenu(NovaMessage msg) {
    final me = context.read<AuthProvider>().user;
    final isMine = msg.senderId == me?.id;
    final ctx = context;
    showModalBottomSheet(
      context: ctx,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withOpacity(0.42),
      builder: (c) {
        final cl = NovaColors.of(c);
        return Container(
          decoration: BoxDecoration(
            color: cl.surface,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(25)),
          ),
          padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.paddingOf(c).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                    width: 42, height: 4,
                    decoration: BoxDecoration(color: cl.line, borderRadius: BorderRadius.circular(5))),
              ),
              const SizedBox(height: 15),
              Text('خيارات الرسالة',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: cl.text)),
              const SizedBox(height: 14),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  if (isMine)
                    _MenuChip(Icons.edit, 'تعديل', cl, () {
                      Navigator.pop(c);
                      _editMessage(msg);
                    }),
                  _MenuChip(Icons.delete_outline, 'حذف لدى الطرفين', cl, () {
                    Navigator.pop(c);
                    _deleteMessage(msg);
                  }),
                  _MenuChip(Icons.copy, 'نسخ النص', cl, () {
                    Navigator.pop(c);
                    if (msg.body != null && msg.body!.isNotEmpty) {
                      Clipboard.setData(ClipboardData(text: msg.body!));
                      showToast(ctx, 'تم النسخ');
                    }
                  }),
                  _MenuChip(Icons.close, 'إغلاق', cl, () => Navigator.pop(c)),
                ],
              ),
            ],
          ),
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
      body: Column(
        children: [
          // رأس المحادثة (نمط القالب)
          Container(
            color: c.surface,
            child: SafeArea(
              bottom: false,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                decoration: BoxDecoration(border: Border(bottom: BorderSide(color: c.line))),
                child: Row(
                  children: [
                    IconBtn(icon: Icons.arrow_back, onTap: () => Navigator.pop(context)),
                    NovaAvatar(
                      letter: widget.conv.name.isNotEmpty ? widget.conv.name[0] : '?',
                      size: 42,
                      radius: 14,
                      online: widget.conv.isOnline,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: PressScale(
                        onTap: () => _startCall('voice'),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(widget.conv.name,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                          fontSize: 15.5,
                                          fontWeight: FontWeight.w800,
                                          color: c.text)),
                                ),
                                if (widget.conv.isVerified)
                                  const Icon(Icons.verified, color: Colors.blue, size: 14),
                              ],
                            ),
                            Text(
                              widget.conv.isOnline
                                  ? 'متصل الآن'
                                  : formatLastSeen(widget.conv.lastSeen),
                              style: TextStyle(
                                  fontSize: 12,
                                  color: widget.conv.isOnline
                                      ? const Color(0xFF25D366)
                                      : c.muted),
                            ),
                          ],
                        ),
                      ),
                    ),
                    IconBtn(icon: Icons.phone, onTap: () => _startCall('voice')),
                    IconBtn(icon: Icons.videocam, onTap: () => _startCall('video')),
                    IconBtn(icon: Icons.timer, onTap: () => _showDisappearSheet()),
                    IconBtn(icon: Icons.more_vert, onTap: () => openChatOptionsSheet(context)),
                  ],
                ),
              ),
            ),
          ),
          // الرسائل (فقاعات RTL: المرسَلة يسارًا والواردة يمينًا)
          Expanded(
            child: NotificationListener<ScrollNotification>(
              onNotification: (n) {
                if (n is ScrollStartNotification && n.metrics.pixels == 0 && !_loading) {
                  _load();
                }
                return false;
              },
              child: LayoutBuilder(
                builder: (context, cons) => Container(
                  decoration: BoxDecoration(
                    color: c.bg,
                    gradient: RadialGradient(
                      center: const Alignment(-0.6, -0.6),
                      radius: 0.6,
                      colors: [c.accent.withOpacity(0.07), Colors.transparent],
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
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                            decoration: BoxDecoration(
                              color: c.surface,
                              borderRadius: BorderRadius.circular(20),
                              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10)],
                            ),
                            child: const Text('اليوم',
                                style: TextStyle(fontSize: 11, color: Color(0xFF667085), fontWeight: FontWeight.w600)),
                          ),
                        );
                      }
                      final mi = i - 1;
                      if (mi == _messages.length) {
                        return const Center(child: Padding(
                            padding: EdgeInsets.all(12), child: CircularProgressIndicator()));
                      }
                      final msg = _messages[mi];
                      final isMine = msg.senderId == auth.user?.id;
                      return GestureDetector(
                        onLongPress: () => _showMessageMenu(msg),
                        child: _Bubble(msg: msg, isMine: isMine, maxWidth: cons.maxWidth * 0.82, colors: c),
                      );
                    },
                  ),
                ),
              ),
            ),
          ),
          // حقل الكتابة (نمط القالب)
          Container(
            color: c.surface,
            padding: EdgeInsets.fromLTRB(10, 9, 10, MediaQuery.paddingOf(context).bottom + 9),
            child: Row(
              children: [
                IconBtn(icon: Icons.add_circle_outline, onTap: () => openAttachSheet(context,
                      onImage: _pickMedia, onDocument: _pickMedia,
                      onVideo: _pickVideo, onAudio: _pickAudio)),
                const SizedBox(width: 7),
                  Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (_isRecording) _buildRecordingIndicator(c),
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
                        hintText: 'اكتب رسالة...',
                        hintStyle: TextStyle(color: c.muted, fontSize: 14),
                        filled: true,
                        fillColor: c.surface2,
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
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
                          borderSide: BorderSide(color: c.accent.withOpacity(0.6)),
                        ),
                      ),
                    ),
                    ),
                  ],
                ),
                ),
                const SizedBox(width: 7),
                if (!_isRecording) ...[
                  IconBtn(icon: Icons.emoji_emotions_outlined, onTap: _addEmoji),
                  const SizedBox(width: 7),
                ],
                PressScale(
                  onTap: _isRecording ? null : (_hasText ? _sendMessage : _startRecording),
                  onLongPressStart: (_) => _startRecording(),
                  onLongPressMoveUpdate: (d) => _toggleCancelRecording(d.localPosition),
                  onLongPressEnd: (_) => _sendVoice(),
                  behavior: HitTestBehavior.opaque,
                    child: Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: _isRecording
                          ? (_cancelRecording ? Colors.red : c.accent)
                          : c.accent,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(color: c.accent.withOpacity(0.35), blurRadius: 14, offset: const Offset(0, 5)),
                      ],
                    ),
                    child: _isRecording
                        ? Icon(Icons.mic, color: Colors.white, size: 20)
                        : Icon(_hasText ? Icons.send : Icons.mic, color: Colors.white, size: 20),
                  ),
                ),
              ],
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

  /// مؤشر التسجيل بأسلوب الواتساب (يعرض عداد المدة وشريط الإلغاء)
  Widget _buildRecordingIndicator(NovaColors c) {
    final elapsed = _recordStartedAt != null
        ? DateTime.now().difference(_recordStartedAt!).inSeconds
        : 0;
    final mins = elapsed ~/ 60;
    final secs = elapsed % 60;
    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      decoration: BoxDecoration(
        color: _cancelRecording ? Colors.red.shade100 : c.surface2,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(
            _cancelRecording ? Icons.cancel_outlined : Icons.mic,
            color: _cancelRecording ? Colors.red : c.accent,
            size: 18,
          ),
          const SizedBox(width: 8),
          Text(
            _cancelRecording ? 'اسحب مرة أخرى للإلغاء' : 'تسجيل... $mins:${secs.toString().padLeft(2, '0')}',
            style: TextStyle(color: _cancelRecording ? Colors.red : c.text, fontSize: 13, fontWeight: FontWeight.w500),
          ),
          const Spacer(),
          Text('↗ ${_cancelRecording ? 'إلغاء' : 'إرسال'}',
            style: TextStyle(color: _cancelRecording ? Colors.red : c.muted, fontSize: 12)),
        ],
      ),
    );
  }
}

/* ═══════════════ فقاعة فيديو بأسلوب الواتساب (تشغيل/إيقاف + شريط تقدم) ═══════════════ */
class _VideoBubble extends StatefulWidget {
  const _VideoBubble({required this.path, this.thumbnail, this.duration, required this.isMine, required this.colors});
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
  String get _url {
    final p = widget.path ?? '';
    if (p.startsWith('http')) return p;
    return '${ApiService.baseUrl.replaceAll('/api/v1', '')}/nova/backend/storage/$p';
  }

  @override
  void initState() {
    super.initState();
    _init();
  }

  Future<void> _init() async {
    try {
      final ctrl = VideoPlayerController.networkUrl(Uri.parse(_url));
      await ctrl.initialize();
      ctrl.setLooping(false);
      if (!mounted) {
        ctrl.dispose();
        return;
      }
      ctrl.addListener(() => setState(() {}));
      setState(() {
        _controller = ctrl;
        _ready = true;
      });
    } catch (_) {
      if (mounted) setState(() {});
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
    final c = widget.isMine ? Colors.white.withOpacity(0.92) : const Color(0xFF101828).withOpacity(0.85);
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
                  onTap: () {
                    if (ctrl.value.isPlaying) {
                      ctrl.pause();
                    } else {
                      ctrl.play();
                    }
                    setState(() {});
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
                      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.3), blurRadius: 10)],
                    ),
                    child: const Icon(Icons.play_arrow, size: 34, color: Color(0xFF5B5CE2)),
                  ),
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: Container(
                    color: c,
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    child: Row(children: [
                      Icon(ctrl.value.isPlaying ? Icons.pause : Icons.play_arrow,
                          color: Colors.white, size: 16),
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
                            valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        _fmt(ctrl.value.position.inSeconds),
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700),
                      ),
                    ]),
                  ),
                ),
              ],
            )
          else
            Stack(
              alignment: Alignment.center,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: widget.thumbnail != null
                      ? Image.network(
                          '${ApiService.baseUrl.replaceAll('/api/v1', '')}/nova/backend/storage/${widget.thumbnail}',
                          width: 250, height: 160, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox(height: 160, width: 250, child: Center(child: CircularProgressIndicator())))
                      : Container(width: 250, height: 160, color: Colors.black12, child: const Center(child: CircularProgressIndicator())),
                ),
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.9),
                    shape: BoxShape.circle,
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.25), blurRadius: 8)],
                  ),
                  child: const Icon(Icons.play_circle_fill, size: 44, color: Color(0xFF5B5CE2)),
                ),
                if (duration != null)
                  Positioned(
                    bottom: 6,
                    right: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.black.withOpacity(0.65),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(_fmt(duration),
                          style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }
}

/* ═══════════════ فقاعة صوت بأسلوب الواتساب (تشغيل + شريط تقدم + مدة) ═══════════════ */
class _AudioBubble extends StatefulWidget {
  const _AudioBubble({required this.path, this.duration, required this.isMine, required this.colors});
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
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;
  String get _url {
    final p = widget.path ?? '';
    if (p.startsWith('http')) return p;
    return '${ApiService.baseUrl.replaceAll('/api/v1', '')}/nova/backend/storage/$p';
  }

  @override
  void initState() {
    super.initState();
    final d = widget.duration;
    if (d != null && d > 0) _duration = Duration(seconds: d);
  }

  Future<void> _toggle() async {
    final p = _player;
    if (p == null) {
      final player = AudioPlayer();
      player.setUrl(_url).then((_) {
        setState(() => _duration = player.duration ?? _duration);
      }).catchError((_) {
        if (mounted) showToast(context, 'تعذر تشغيل المقطع');
      });
      player.positionStream.listen((pos) {
        if (mounted) setState(() => _position = pos);
      });
      player.playerStateStream.listen((state) {
        if (mounted) setState(() => _playing = state.playing);
      });
      player.processingStateStream.listen((state) {
        if (state == ProcessingState.completed && mounted) {
          setState(() => _playing = false);
        }
      });
      setState(() => _player = player);
      await player.play();
      setState(() => _playing = true);
      return;
    }
    if (_playing) {
      await p.pause();
      setState(() => _playing = false);
    } else {
      await p.play();
      setState(() => _playing = true);
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

  @override
  Widget build(BuildContext context) {
    final c = widget.colors;
    return SizedBox(
      width: 230,
      child: Row(children: [
        PressScale(
          onTap: _toggle,
          child: Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: widget.isMine ? Colors.white.withOpacity(0.25) : c.accent.withOpacity(0.18),
              shape: BoxShape.circle,
            ),
            child: Icon(_playing ? Icons.pause : Icons.play_arrow,
                color: widget.isMine ? Colors.white : c.accent, size: 24),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                minHeight: 4,
                value: _duration.inMilliseconds > 0
                    ? (_position.inMilliseconds / _duration.inMilliseconds).clamp(0.0, 1.0)
                    : null,
                color: widget.isMine ? Colors.white : c.accent,
                backgroundColor: (widget.isMine ? Colors.white : c.accent).withOpacity(0.25),
              ),
            ),
            const SizedBox(height: 5),
            Text(
              _fmt(_position) + ' / ' + _fmt(_duration),
              style: TextStyle(fontSize: 11, color: widget.isMine ? c.mineText.withOpacity(0.8) : c.muted),
            ),
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
        decoration: BoxDecoration(color: colors.surface2, borderRadius: BorderRadius.circular(18)),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 23, color: colors.text),
            const SizedBox(height: 6),
            Text(label, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: colors.text)),
          ],
        ),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.msg, required this.isMine, required this.maxWidth, required this.colors});
  final NovaMessage msg;
  final bool isMine;
  final double maxWidth;
  final NovaColors colors;

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

  @override
  Widget build(BuildContext context) {
    final c = colors;
    final time = msg.createdAt.length >= 16 ? msg.createdAt.substring(11, 16) : '';
    return Align(
      alignment: isMine ? const Alignment(-1, 0) : const Alignment(1, 0),
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 3.5),
        constraints: BoxConstraints(maxWidth: maxWidth),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isMine ? c.mine : c.surface,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(18),
            topRight: const Radius.circular(18),
            bottomLeft: Radius.circular(isMine ? 6 : 18),
            bottomRight: Radius.circular(isMine ? 18 : 6),
          ),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 12, offset: const Offset(0, 3))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (msg.isDeleted)
              Text('تم حذف هذه الرسالة',
                  style: TextStyle(fontStyle: FontStyle.italic, color: isMine ? c.mineText.withOpacity(0.7) : c.muted))
            else if (msg.type == 'image' && msg.filePath != null)
              ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.network(msg.filePath!, width: 220))
            else if (msg.type == 'video' && msg.filePath != null)
              _VideoBubble(path: msg.filePath, thumbnail: msg.thumbnailPath, duration: msg.duration, isMine: isMine, colors: c)
            else if (msg.type == 'audio' && msg.filePath != null)
              _AudioBubble(path: msg.filePath, duration: msg.duration, isMine: isMine, colors: c)
            else if (msg.type == 'file' && msg.filePath != null)
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(color: c.accent.withOpacity(0.12), borderRadius: BorderRadius.circular(10)),
                child: Row(children: [
                  Icon(Icons.attach_file, color: c.accent, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(msg.body ?? 'ملف',
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 13, color: isMine ? c.mineText : c.text)),
                  ),
                ]),
              )
            else
              Text(msg.body ?? '',
                  style: TextStyle(fontSize: 14.5, height: 1.5, color: isMine ? c.mineText : c.text)),
            const SizedBox(height: 3),
            Row(mainAxisSize: MainAxisSize.min, children: [
              if (msg.isEdited)
                Text('(معدلة)  ',
                    style: TextStyle(fontSize: 10,
                        color: isMine ? c.mineText.withOpacity(0.7) : c.muted)),
              if (time.isNotEmpty)
                Text('$time  ',
                    style: TextStyle(fontSize: 10,
                        color: isMine ? c.mineText.withOpacity(0.7) : c.muted)),
              if (isMine) ..._statusTicks(msg.status, c),
            ]),
          ],
        ),
      ),
    );
  }
}
