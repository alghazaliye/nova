import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';
import 'package:http/http.dart' as http;
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'story_viewer_fullscreen.dart';

/// تبويب الحالة (القصص) — تصميم القالب: قصتي + تحديثات حديثة
class StoriesScreen extends StatefulWidget {
  const StoriesScreen({super.key});

  @override
  State<StoriesScreen> createState() => _StoriesScreenState();
}

class _StoriesScreenState extends State<StoriesScreen> {
  List<Map<String, dynamic>> _groupedStories = [];
  bool _loading = true;
  static const Uuid _uuid = Uuid();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await ApiService.get('/stories');
      if (res['success'] == true && res['data'] is List) {
        setState(() {
          _groupedStories = List<Map<String, dynamic>>.from(res['data'] as List);
        });
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _publishStory() async {
    if (!mounted) return;
    final auth = context.read<AuthProvider>();
    if (!auth.effectiveAppSettings.allowStories) {
      showToast(context, 'نشر الحالات موقوف من الإدارة');
      return;
    }
    final choice = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('نشر حالة'),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('اختر نوع الحالة', style: TextStyle(fontSize: 15)),
            SizedBox(height: 10),
            Text('صورة من المعرض أو نص بسيط', style: TextStyle(fontSize: 13, color: Colors.grey)),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, 'cancel'),
              child: const Text('إلغاء')),
            TextButton(
              onPressed: () => Navigator.pop(c, 'photo'),
              child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [Icon(Icons.image, size: 18), SizedBox(width: 6), Text('نشر صورة')])),
          TextButton(
              onPressed: () => Navigator.pop(c, 'video'),
              child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [Icon(Icons.videocam, size: 18), SizedBox(width: 6), Text('نشر فيديو')])),
          TextButton(
              onPressed: () => Navigator.pop(c, 'text'),
              child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [Icon(Icons.text_fields, size: 18), SizedBox(width: 6), Text('نشر نص')])),
        ],
      ),
    );
    if (!mounted || choice == null || choice == 'cancel') return;

    if (choice == 'photo' || choice == 'video') {
      try {
        final picker = ImagePicker();
        XFile? file;
        if (choice == 'photo') {
          file = await picker.pickImage(
            source: ImageSource.gallery,
            imageQuality: 85,
            maxWidth: 1600,
            maxHeight: 1600,
          );
        } else {
          file = await picker.pickVideo(source: ImageSource.gallery);
        }
        if (file == null || !mounted) return;
        await _uploadStoryMedia(file);
      } catch (e) {
        if (mounted) showToast(context, 'تعذر اختيار الوسائط: $e');
      }
    } else if (choice == 'text') {
      final ctrl = TextEditingController();
      final text = await showDialog<String>(
        context: context,
        builder: (c) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
          title: const Text('نشر قصة نصية'),
          content: TextField(
            controller: ctrl,
            maxLines: 4,
            decoration: const InputDecoration(hintText: 'اكتب قصتك'),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(c, null), child: const Text('إلغاء')),
            TextButton(onPressed: () => Navigator.pop(c, ctrl.text.trim()), child: const Text('نشر')),
          ],
        ),
      );
      if (text == null || text.isEmpty || !mounted) return;
      final res = await ApiService.post('/stories', body: {
        'type': 'text',
        'text': text,
        'privacy': 'all',
        'client_message_id': _uuid.v4(),
      });
      if (!mounted) return;
      if (res['success'] == true) {
        _load();
        if (mounted) showToast(context, 'تم نشر القصة');
      } else {
        if (mounted) showToast(context, res['message'] ?? 'فشل النشر');
      }
    }
  }

  /// رفع صورة كحالة عبر multipart إلى /stories/{id}/upload
  Future<void> _uploadStoryMedia(XFile file) async {
    if (!mounted) return;
    final meId = context.read<AuthProvider>().user?.id;
    if (meId == null) return;
    try {
      http.MultipartFile mf;
      if (kIsWeb) {
        final bytes = await file.readAsBytes();
        mf = http.MultipartFile.fromBytes('file', bytes,
            filename: file.name);
      } else {
        mf = await http.MultipartFile.fromPath('file', file.path,
            filename: file.name);
      }
      final res = await ApiService.uploadMultipart(
          '/stories/upload', [mf], fields: {'privacy': 'all'});
      if (!mounted) return;
      if (res['success'] == true) {
        _load();
        if (mounted) showToast(context, 'تم نشر الحالة');
      } else {
        if (mounted) showToast(context, res['message'] ?? 'فشل النشر');
      }
    } catch (e) {
      if (mounted) showToast(context, 'فشل رفع الصورة: $e');
    }
  }

  void _openStory(Map<String, dynamic> group) {
    final stories = List<Map<String, dynamic>>.from(group['stories'] ?? []);
    if (stories.isEmpty) return;
    Navigator.push(
        context,
        MaterialPageRoute(
            builder: (_) => NovaStoryViewer(
                stories: stories,
                initialIndex: 0)));
  }

  Future<void> _deleteStory(Map<String, dynamic> story) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('حذف القصة'),
        content: const Text('هل تريد حذف هذه القصة؟'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('حذف', style: TextStyle(color: Colors.red))),
        ],
      ),
    );
    if (ok == true && mounted) {
      final id = story['id']?.toString();
      if (id != null) {
        await ApiService.delete('/stories/$id').catchError((_) => {'success': false});
        _load();
      }
    }
  }

  String _storyText(Map<String, dynamic> s) {
    return (s['text'] ?? s['body'] ?? '').toString();
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    final me = auth.user;
    return Scaffold(
      body: Column(
        children: [
          novaTopBar(context,
              title: 'الحالة',
              actions: [
                if (context.read<AuthProvider>().effectiveAppSettings.allowStories)
                  IconBtn(icon: Icons.add_circle_outline,
                      onTap: _publishStory, color: c.accent),
              ]),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _groupedStories.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.add_photo_alternate_outlined,
                                size: 72, color: c.muted.withOpacity(0.45)),
                            const SizedBox(height: 12),
                            Text('لا توجد حالات بعد',
                                style: TextStyle(fontSize: 16, color: c.muted)),
                            const SizedBox(height: 8),
                            Text('أضف أول قصة من الزر',
                                style: TextStyle(fontSize: 13, color: c.muted)),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                          children: [
                            // قصتي
                            if (auth.effectiveAppSettings.allowStories)
                              NovaCard(
                                padding: const EdgeInsets.all(12),
                                onTap: () {
                                  try {
                                    final myGroup = _groupedStories.firstWhere(
                                      (g) => g['user_id'].toString() == (me?.id ?? -1).toString()
                                    );
                                    _openStory(myGroup);
                                  } catch (_) {
                                    _publishStory();
                                  }
                                },
                                child: Row(
                                  children: [
                                    NovaAvatar(
                                      letter: me?.name != null && me!.name!.isNotEmpty
                                          ? me.name![0]
                                          : '?',
                                      size: 54,
                                      radius: 18,
                                      imageUrl: me?.avatar != null &&
                                              me!.avatar!.isNotEmpty
                                          ? me.avatar
                                          : null),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Row(children: [
                                            Expanded(
                                              child: Text('قصتي',
                                                  style: TextStyle(
                                                      fontSize: 15.5,
                                                      fontWeight: FontWeight.w800,
                                                      color: c.text)),
                                            ),
                                            TabChip(label: 'نشر', onTap: _publishStory),
                                          ]),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            const SizedBox(height: 14),
                            SectionTitle('تحديثات حديثة'),
                            for (final group in _groupedStories)
                              PressScale(
                                onTap: () => _openStory(group),
                                child: Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: NovaCard(
                                    padding: const EdgeInsets.all(12),
                                    child: Row(
                                      children: [
                                        NovaAvatar(
                                            letter: (group['user_name']?.toString() ?? '').isNotEmpty
                                                ? group['user_name'].toString()[0]
                                                : '?',
                                            size: 54,
                                            radius: 18,
                                            imageUrl: group['user_avatar'] != null &&
                                                    group['user_avatar'].toString().isNotEmpty
                                                ? group['user_avatar'].toString()
                                                : null),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(children: [
                                                Expanded(
                                                  child: Text(
                                                      group['user_name']?.toString() ?? '-',
                                                      overflow: TextOverflow.ellipsis,
                                                      style: TextStyle(
                                                          fontSize: 15.5,
                                                          fontWeight: FontWeight.w800,
                                                          color: c.text)),
                                                ),
                                                if (group['user_id'].toString() == (me?.id ?? -1).toString())
                                                  TabChip(label: 'قصتي'),
                                              ]),
                                              const SizedBox(height: 4),
                                              Row(children: [
                                                if (group['stories'] != null && group['stories'].isNotEmpty) ...[
                                                  if (group['stories'].last['type']?.toString() == 'image')
                                                    Icon(Icons.image, size: 13, color: c.accent),
                                                  if (group['stories'].last['type']?.toString() == 'video')
                                                    Icon(Icons.videocam, size: 13, color: c.accent),
                                                  if (group['stories'].last['type']?.toString() == 'text')
                                                    Icon(Icons.text_fields, size: 13, color: c.muted),
                                                  const SizedBox(width: 4),
                                                ],
                                                Expanded(
                                                  child: Text(
                                                      group['stories'] != null && group['stories'].isNotEmpty
                                                          ? _storyText(group['stories'].last)
                                                          : 'لا توجد حالات',
                                                      maxLines: 1,
                                                      overflow: TextOverflow.ellipsis,
                                                      style: TextStyle(
                                                          fontSize: 13, color: c.muted)),
                                                ),
                                              ]),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
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

/// عارض القصة — عرض كامل الشاشة مع التنقل وحذف قصتي
class StoryViewer extends StatefulWidget {
  final List<Map<String, dynamic>> stories;
  final Map<String, dynamic> initial;
  const StoryViewer({super.key, required this.stories, required this.initial});

  @override
  State<StoryViewer> createState() => _StoryViewerState();
}

class _StoryViewerState extends State<StoryViewer> {
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = widget.stories.indexOf(widget.initial);
    if (_index < 0) _index = 0;
  }

  String _storyText(Map<String, dynamic> s) {
    return (s['text'] ?? s['body'] ?? '').toString();
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final s = widget.stories[_index];
    final auth = context.read<AuthProvider>();
    final isMine = s['user_id'].toString() == (auth.user?.id ?? -1).toString();
    final letter = (s['user_name']?.toString() ?? '').isNotEmpty
        ? s['user_name'].toString()[0]
        : '?';
    return Scaffold(
      backgroundColor: c.bg,
      body: SafeArea(
        child: Stack(
          children: [
            Center(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: GestureDetector(
                  onHorizontalDragEnd: (d) {
                    if (d.velocity.pixelsPerSecond.dx < 0 &&
                        _index < widget.stories.length - 1) {
                      setState(() => _index++);
                    } else if (d.velocity.pixelsPerSecond.dx > 0 &&
                        _index > 0) {
                      setState(() => _index--);
                    }
                  },
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      NovaCard(
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          _storyText(s),
                          style: TextStyle(
                              fontSize: 20, height: 1.6, fontFamily: 'Cairo', color: c.text),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Row(
                  children: [
                    NovaAvatar(letter: letter, size: 40, radius: 12),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        s['user_name']?.toString() ?? '-',
                        style: TextStyle(
                            color: c.text,
                            fontSize: 16,
                            fontWeight: FontWeight.bold),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (isMine)
                      IconButton(
                        icon: const Icon(Icons.delete, color: Colors.redAccent),
                        tooltip: 'حذف قصتي',
                        onPressed: () async {
                          final id = s['id']?.toString();
                          if (id != null) {
                            await ApiService.delete('/stories/$id').catchError((_) => {'success': false});
                            if (mounted) Navigator.pop(context);
                          }
                        },
                      ),
                    IconButton(
                      icon: Icon(Icons.close, color: c.text),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ],
                ),
              ),
            ),
            Positioned(
              top: 56,
              left: 16,
              right: 16,
              child: Row(
                children: List.generate(widget.stories.length, (i) {
                  return Expanded(
                    child: Container(
                      height: 2,
                      margin: const EdgeInsets.symmetric(horizontal: 2),
                      decoration: BoxDecoration(
                        color: i == _index ? c.accent : c.muted.withOpacity(0.3),
                        borderRadius: BorderRadius.circular(1),
                      ),
                    ),
                  );
                }),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
