import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';
import 'package:image_picker/image_picker.dart';
import '../models/user_model.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import 'call_screen.dart';

/// شاشة المحادثة — تدعم التعديل والحذف للطرفين والوسائط والمكالمات
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
  final ImagePicker _picker = ImagePicker();
  static const Uuid _uuid = Uuid();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _scroll.dispose();
    super.dispose();
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

  Future<void> _sendMessage([String? fileContent]) async {
    final text = _ctrl.text.trim();
    if (text.isEmpty && fileContent == null) return;
    final clientId = _uuid.v4();
    final type = fileContent != null ? _guessType(fileContent) : 'text';
    _ctrl.clear();
    // رسالة مؤقتة
    final temp = NovaMessage(
      id: -1, uuid: clientId, senderId: context.read<AuthProvider>().user?.id ?? 0,
      type: type, body: text, status: 'sending', createdAt: DateTime.now().toIso8601String(),
    );
    setState(() => _messages.add(temp));
    _scrollToBottom();
    try {
      final res = await ApiService.post('/conversations/${widget.conv.id}/messages', body: {
        'client_message_id': clientId,
        'type': type,
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
        setState(() => temp); // تبقى بإشارة فشل
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(res['message'] ?? 'فشل الإرسال')));
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('خطأ في الاتصال')));
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
    final mode = await showDialog<String>(
      context: context,
      builder: (c) => SimpleDialog(
        title: const Text('إضافة وسائط'),
        children: [
          SimpleDialogOption(
              onPressed: () => Navigator.pop(c, 'image'),
              child: const ListTile(leading: Icon(Icons.image), title: Text('صورة'))),
          SimpleDialogOption(
              onPressed: () => Navigator.pop(c, 'video'),
              child: const ListTile(leading: Icon(Icons.videocam), title: Text('فيديو'))),
          SimpleDialogOption(
              onPressed: () => Navigator.pop(c, 'file'),
              child: const ListTile(leading: Icon(Icons.attach_file), title: Text('ملف'))),
        ],
      ),
    );
    if (mode == null) return;
    try {
      XFile? f;
      if (mode == 'image') {
        f = await _picker.pickImage(source: ImageSource.gallery);
      } else if (mode == 'video') {
        f = await _picker.pickVideo(source: ImageSource.gallery);
      }
      if (f == null) return;
      if (!mounted) return;
      final name = f.name.split('/').last;
      final ext = name.contains('.') ? name.split('.').last.toLowerCase() : 'bin';
      final mime = _mimeFromExt(ext);
      final type = _guessType(name);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('جاري رفع الملف...')));
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
            ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('تم إرسال الوسائط')));
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(res['message'] ?? 'فشل الإرسال')));
          }
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('فشل الرفع: $e')));
        }
      }
    } catch (_) {}
  }

  Future<void> _startCall(String type) async {
    final res = await ApiService.post('/calls', body: {
      'contact_phone': widget.conv.name,
      'call_type': type,
    });
    if (!mounted) return;
    if (res['success'] == true) {
      Navigator.push(context, MaterialPageRoute(
          builder: (_) => CallScreen(callData: res['data'] as Map<String, dynamic>)));
    } else {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(res['message'] ?? 'فشل الاتصال')));
    }
  }

  Future<void> _editMessage(NovaMessage msg) async {
    final ctrl = TextEditingController(text: msg.body ?? '');
    final newBody = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
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
    } else {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(res['message'] ?? 'فشل التعديل')));
    }
  }

  Future<void> _deleteMessage(NovaMessage msg) async {
    final res = await ApiService.delete('/messages/${msg.id}');
    if (!mounted) return;
    if (res['success'] == true) {
      await _refresh();
    } else {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(res['message'] ?? 'فشل الحذف')));
    }
  }

  void _showMessageMenu(NovaMessage msg) {
    final me = context.read<AuthProvider>().user;
    final isMine = msg.senderId == me?.id;
    showModalBottomSheet(
      context: context,
      builder: (c) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (isMine)
              ListTile(
                  leading: const Icon(Icons.edit, color: Colors.orange),
                  title: const Text('تعديل الرسالة'),
                  onTap: () {
                    Navigator.pop(c);
                    _editMessage(msg);
                  }),
            ListTile(
                leading: const Icon(Icons.delete, color: Colors.red),
                title: const Text('حذف لدى الطرفين'),
                onTap: () {
                  Navigator.pop(c);
                  _deleteMessage(msg);
                }),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.read<AuthProvider>();
    return Scaffold(
      appBar: AppBar(
        title: Row(children: [
          Text(widget.conv.name),
          if (widget.conv.isVerified)
            const Padding(
                padding: EdgeInsets.only(right: 6),
                child: Icon(Icons.verified, color: Colors.blue, size: 16)),
        ]),
        actions: [
          IconButton(
              onPressed: () => _startCall('voice'),
              icon: const Icon(Icons.call)),
          IconButton(
              onPressed: () => _startCall('video'),
              icon: const Icon(Icons.videocam)),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: NotificationListener<ScrollNotification>(
              onNotification: (n) {
                if (n is ScrollStartNotification && n.metrics.pixels == 0 && !_loading) {
                  _load();
                }
                return false;
              },
              child: ListView.builder(
                controller: _scroll,
                padding: const EdgeInsets.all(12),
                itemCount: _messages.length + (_loading ? 1 : 0),
                itemBuilder: (_, i) {
                  if (_loading && i == _messages.length) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  final msg = _messages[i];
                  final isMine = msg.senderId == auth.user?.id;
                  return GestureDetector(
                    onLongPress: () => _showMessageMenu(msg),
                    child: Align(
                      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
                      child: Container(
                        margin: const EdgeInsets.symmetric(vertical: 3, horizontal: 6),
                        padding: const EdgeInsets.all(10),
                        constraints: const BoxConstraints(maxWidth: 280),
                        decoration: BoxDecoration(
                          color: isMine ? Colors.green.shade100 : Colors.grey.shade200,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            if (msg.isDeleted)
                              const Text('تم حذف هذه الرسالة',
                                  style: TextStyle(fontStyle: FontStyle.italic, color: Colors.black54))
                            else if (msg.type == 'image')
                              ClipRRect(
                                  borderRadius: BorderRadius.circular(10),
                                  child: msg.filePath != null
                                      ? Image.network(msg.filePath!, width: 200)
                                      : const Icon(Icons.image, size: 48))
                            else if (msg.type == 'video')
                              ClipRRect(
                                  borderRadius: BorderRadius.circular(10),
                                  child: msg.filePath != null
                                      ? SizedBox(
                                          width: 240,
                                          height: 160,
                                          child: Icon(Icons.videocam, size: 48))
                                      : const Icon(Icons.videocam, size: 48))
                            else
                              Text(msg.body ?? '',
                                  style: const TextStyle(fontFamily: 'Cairo')),
                            Row(mainAxisSize: MainAxisSize.min, children: [
                              if (msg.isEdited)
                                const Text(' (معدلة)',
                                    style: TextStyle(fontSize: 10, color: Colors.black45)),
                              const Spacer(),
                              Text(msg.createdAt.substring(11, 16),
                                  style: const TextStyle(fontSize: 10, color: Colors.black54)),
                            ]),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: Colors.white,
              border: Border(top: BorderSide(color: Colors.grey.shade300)),
            ),
            child: Row(
              children: [
                IconButton(
                    onPressed: _pickMedia,
                    icon: const Icon(Icons.attach_file, color: Colors.grey)),
                Expanded(
                  child: TextField(
                    controller: _ctrl,
                    decoration: InputDecoration(
                      hintText: 'اكتب رسالة',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(22)),
                      filled: true,
                      fillColor: Colors.grey.shade100,
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    ),
                    onSubmitted: (_) => _sendMessage(),
                  ),
                ),
                const SizedBox(width: 6),
                CircleAvatar(
                  backgroundColor: Theme.of(context).colorScheme.primary,
                  child: IconButton(
                      onPressed: () => _sendMessage(),
                      icon: const Icon(Icons.send, color: Colors.white)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
