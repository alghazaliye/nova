import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:uuid/uuid.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';

/// تبويب الحالة (القصص): قصتي + قصص الآخرين + نشر قصة
class StoriesScreen extends StatefulWidget {
  const StoriesScreen({super.key});

  @override
  State<StoriesScreen> createState() => _StoriesScreenState();
}

class _StoriesScreenState extends State<StoriesScreen> {
  List<Map<String, dynamic>> _stories = [];
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
          _stories = List<Map<String, dynamic>>.from(res['data'] as List);
        });
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _publishStory() async {
    final ctrl = TextEditingController();
    final text = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('نشر قصة'),
        content: TextField(
          controller: ctrl,
          maxLines: 4,
          decoration: const InputDecoration(hintText: 'اكتب قصتك (نص)'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, null),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, ctrl.text.trim()),
              child: const Text('نشر')),
        ],
      ),
    );
    if (text == null || text.isEmpty || !mounted) return;
    final res = await ApiService.post('/stories', body: {
      'type': 'text',
      'client_message_id': _uuid.v4(),
      'body': text,
    });
    if (!mounted) return;
    if (res['success'] == true) {
      _load();
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('تم نشر القصة')));
      }
    } else {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(res['message'] ?? 'فشل النشر')));
      }
    }
  }

  void _openStory(Map<String, dynamic> story) {
    Navigator.push(
        context,
        MaterialPageRoute(
            builder: (_) =>
                StoryViewer(stories: _stories, initial: story)));
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final me = auth.user;
    return Scaffold(
      floatingActionButton: FloatingActionButton(
        backgroundColor: Theme.of(context).colorScheme.primary,
        onPressed: _publishStory,
        tooltip: 'إضافة قصة',
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _stories.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.add_photo_alternate_outlined,
                          size: 72, color: Colors.grey.shade400),
                      const SizedBox(height: 12),
                      const Text('لا توجد حالات بعد',
                          style:
                              TextStyle(fontSize: 16, color: Colors.black54)),
                      const SizedBox(height: 8),
                      const Text('أضف أول قصة من الزر',
                          style:
                              TextStyle(fontSize: 13, color: Colors.black45)),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    itemCount: _stories.length,
                    itemBuilder: (_, i) {
                      final s = _stories[i];
                      final isMine =
                          s['user_id'].toString() == (me?.id ?? -1).toString();
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundColor: Theme.of(context)
                              .colorScheme
                              .primaryContainer,
                          child: Text(
                              s['user_name'].toString().isNotEmpty
                                  ? s['user_name'].toString()[0]
                                  : '?'),
                        ),
                        title: Row(children: [
                          Text(s['user_name']?.toString() ?? '-',
                              overflow: TextOverflow.ellipsis),
                          if (isMine)
                            const Padding(
                                padding: EdgeInsets.only(right: 6),
                                child: Chip(
                                    label: Text('قصتي',
                                        style: TextStyle(fontSize: 11)))),
                        ]),
                        subtitle: Text(
                            (s['body'] ?? '').toString().length > 60
                                ? '${s['body'].toString().substring(0, 60)}...'
                                : (s['body'] ?? ''),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis),
                        onTap: () => _openStory(s),
                      );
                    },
                  ),
                ),
    );
  }
}

/// عارض القصة — عرض كامل الشاشة مع التنقل بين القصص وحذف قصتي
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

  @override
  Widget build(BuildContext context) {
    final s = widget.stories[_index];
    final auth = context.read<AuthProvider>();
    final isMine = s['user_id'].toString() == (auth.user?.id ?? -1).toString();
    return Scaffold(
      backgroundColor: const Color(0xFF128C7E),
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
                      Container(
                        width: double.infinity,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.all(24),
                        child: Text(
                          (s['body'] ?? '').toString(),
                          style: const TextStyle(
                              fontSize: 20, height: 1.6, fontFamily: 'Cairo'),
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
                    CircleAvatar(
                      backgroundColor: Colors.white,
                      child: Text(
                          s['user_name'].toString().isNotEmpty
                              ? s['user_name'].toString()[0]
                              : '?'),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        s['user_name']?.toString() ?? '-',
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.bold),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (isMine)
                      IconButton(
                        icon: const Icon(Icons.delete, color: Colors.white),
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
                      icon: const Icon(Icons.close, color: Colors.white),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ],
                ),
              ),
            ),
            // مؤشرات القصص
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
                      color: i == _index ? Colors.white : Colors.white30,
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
