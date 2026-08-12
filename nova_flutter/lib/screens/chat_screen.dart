import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';
import 'package:image_picker/image_picker.dart';
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
  bool _loading = false;
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
    _ctrl.dispose();
    _scroll.dispose();
    super.dispose();
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
                      onImage: _pickMedia, onDocument: _pickMedia)),
                const SizedBox(width: 7),
                Expanded(
                  child: SizedBox(
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
                ),
                const SizedBox(width: 7),
                IconBtn(icon: Icons.emoji_emotions_outlined, onTap: _addEmoji),
                const SizedBox(width: 7),
                PressScale(
                  onTap: _sendMessage,
                  child: Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: c.accent,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(color: c.accent.withOpacity(0.35), blurRadius: 14, offset: const Offset(0, 5)),
                      ],
                    ),
                    child: Icon(_hasText ? Icons.send : Icons.mic, color: Colors.white, size: 20),
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
        color = const Color(0xFF3B82F6);
        label = '✓✓';
        break;
      case 'delivered':
        color = const Color(0xFF6B7280);
        label = '✓✓';
        break;
      case 'deleted':
        color = const Color(0xFFEF4444);
        label = '✕';
        break;
      default:
        color = const Color(0xFF9CA3AF);
        label = '✓';
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
              ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.network(msg.filePath!, width: 240, height: 160, fit: BoxFit.cover))
            else if (msg.type == 'audio' && msg.filePath != null)
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(color: c.accent.withOpacity(0.12), borderRadius: BorderRadius.circular(10)),
                child: Row(children: [
                  Icon(Icons.audiotrack, color: c.accent, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(msg.body ?? 'رسالة صوتية',
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 13, color: isMine ? c.mineText : c.text)),
                  ),
                ]),
              )
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
