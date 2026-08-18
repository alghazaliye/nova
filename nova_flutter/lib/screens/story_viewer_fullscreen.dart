import 'package:flutter/foundation.dart' show kIsWeb;
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'web_story_video.dart' if (dart.library.io) 'stub_story_video.dart';

/// عارض الحالات بملء الشاشة — نمط واتساب:
/// - خلفية داكنة كاملة الشاشة (تغطي شريط الحالة)
/// - شريط تقدم متحرك تلقائي (5 ثوانٍ للصورة/النص، مدة الفيديو)
/// - النقر يمين/يسار للانتقال للتالي/السابق
/// - الضغط المطول يوقف التقدم مؤقتًا
/// - عرض الصورة/الفيديو بتكبير كامل
class NovaStoryViewer extends StatefulWidget {
  final List<Map<String, dynamic>> stories;
  final int initialIndex;
  const NovaStoryViewer({
    super.key,
    required this.stories,
    required this.initialIndex,
  });

  @override
  State<NovaStoryViewer> createState() => _NovaStoryViewerState();
}

class _NovaStoryViewerState extends State<NovaStoryViewer>
    with SingleTickerProviderStateMixin {
  late int _userIdx;
  late int _storyIdx;
  late final AnimationController _progress;
  VideoPlayerController? _video;
  bool _videoReady = false;
  bool _disposed = false;
  // على الويب نستخدم عنصر <video> الأصلي مباشرة عبر HtmlElementView
  // لأن video_player قد يفشل في بعض المتصفحات مع wasm renderer.
  bool _webVideoFailed = false;

  /// القصص المصنّفة حسب المستخدم (مجموعات متتالية لكل مستخدم)
  List<List<Map<String, dynamic>>> _userGroups = [];
  int get _totalStories => _userGroups.fold(0, (s, g) => s + g.length);

  int get _globalIdx {
    int before = 0;
    for (int i = 0; i < _userIdx; i++) {
      before += _userGroups[i].length;
    }
    return before + _storyIdx;
  }

  Map<String, dynamic> get _current => _userGroups[_userIdx][_storyIdx];

  @override
  void initState() {
    super.initState();
    _buildGroups();
    int target = widget.initialIndex;
    if (target >= _totalStories) target = _totalStories - 1;
    if (target < 0) target = 0;
    _userIdx = 0;
    _storyIdx = target;
    int before = 0;
    for (int i = 0; i < _userGroups.length; i++) {
      if (before + _userGroups[i].length > target) {
        _userIdx = i;
        _storyIdx = target - before;
        break;
      }
      before += _userGroups[i].length;
    }
    _progress = AnimationController(
      vsync: this,
      duration: _durationFor(_current),
    );
    _progress.addStatusListener(_onProgressDone);
    _progress.forward();
    _loadVideoIfNeeded();
  }

  void _buildGroups() {
    final List<List<Map<String, dynamic>>> groups = [];
    for (final s in widget.stories) {
      final u = s['user_id'].toString();
      if (groups.isNotEmpty &&
          groups.last.first['user_id'].toString() == u) {
        groups.last.add(s);
      } else {
        groups.add([s]);
      }
    }
    _userGroups = groups.isEmpty ? [[]] : groups;
  }

  Duration _durationFor(Map<String, dynamic> s) {
    if (s['type'] == 'video') {
      final d = _video?.value.duration;
      if (d != null && d > Duration.zero) return d;
      final secs = int.tryParse((s['duration'] ?? 15).toString()) ?? 15;
      return Duration(seconds: secs.clamp(5, 60));
    }
    return const Duration(seconds: 5);
  }

  Future<void> _loadVideoIfNeeded() async {
    await _video?.dispose();
    _video = null;
    _videoReady = false;
    if (_current['type'] != 'video') {
      if (mounted) setState(() {});
      return;
    }
    final url = ApiService.mediaUrl(_current['file_url']?.toString() ?? '');
    if (kIsWeb) {
      // مسار الويب المباشر: عنصر <video> أصلي مع fallback إلى video_player
      try {
        _webVideo.createAndInsertVideo(url);
        _webVideo.play();
        _webVideoFailed = false;
        _videoReady = true;
        if (mounted) setState(() {});
        return;
      } catch (e) {
        _webVideoFailed = true;
        if (mounted) setState(() {});
      }
    }
    try {
      final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
      await ctrl.initialize();
      if (_disposed) {
        await ctrl.dispose();
        return;
      }
      _video = ctrl;
      _videoReady = true;
      ctrl.setLooping(false);
      ctrl.play();
      if (mounted) setState(() {});
    } catch (_) {
      if (mounted) setState(() {});
    }
  }

  void _onProgressDone(AnimationStatus status) {
    if (status == AnimationStatus.completed && mounted) {
      _next();
    }
  }

  void _next() {
    if (kIsWeb && _current['type'] == 'video') {
      _webVideo.pause();
      _webVideo.removeVideo();
    }
    if (_current['type'] == 'video') _video?.pause();
    if (_storyIdx < _userGroups[_userIdx].length - 1) {
      setState(() => _storyIdx++);
      _restart();
    } else if (_userIdx < _userGroups.length - 1) {
      setState(() {
        _userIdx++;
        _storyIdx = 0;
      });
      _restart();
    } else {
      Navigator.pop(context);
    }
  }

  void _prev() {
    if (kIsWeb && _current['type'] == 'video') {
      _webVideo.pause();
      _webVideo.removeVideo();
    }
    if (_current['type'] == 'video') _video?.pause();
    if (_storyIdx > 0) {
      setState(() => _storyIdx--);
      _restart();
    } else if (_userIdx > 0) {
      setState(() {
        _userIdx--;
        _storyIdx = _userGroups[_userIdx].length - 1;
      });
      _restart();
    }
  }

  void _restart() {
    _progress.reset();
    _progress.duration = _durationFor(_current);
    _progress.forward();
    _loadVideoIfNeeded();
  }

  void _togglePause() {
    if (kIsWeb && _current['type'] == 'video') {
      if (_webVideo.isPlaying()) {
        _webVideo.pause();
        _progress.stop();
      } else {
        _webVideo.play();
        _progress.forward();
      }
      setState(() {});
      return;
    }
    if (_current['type'] == 'video' && _video != null) {
      if (_video!.value.isPlaying) {
        _video!.pause();
        _progress.stop();
      } else {
        _video!.play();
        _progress.forward();
      }
      setState(() {});
    } else {
      if (_progress.isAnimating) {
        _progress.stop();
      } else {
        _progress.forward();
      }
    }
  }

  void _holdPause() {
    if (kIsWeb && _current['type'] == 'video') {
      if (_webVideo.isPlaying()) _webVideo.pause();
      return;
    }
    if (_current['type'] == 'video' && _video != null && _video!.value.isPlaying) {
      _video!.pause();
    } else if (_progress.isAnimating) {
      _progress.stop();
    }
  }

  void _holdResume() {
    if (kIsWeb && _current['type'] == 'video') {
      if (!_webVideo.isPlaying()) _webVideo.play();
      return;
    }
    if (_current['type'] == 'video' && _video != null && !_video!.value.isPlaying) {
      _video!.play();
      _progress.forward();
    } else if (!_progress.isAnimating &&
        _progress.status != AnimationStatus.completed) {
      _progress.forward();
    }
  }

  @override
  void dispose() {
    _disposed = true;
    _video?.dispose();
    _progress.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.read<AuthProvider>();
    final isMine =
        _current['user_id'].toString() == (auth.user?.id ?? -1).toString();
    final group = _userGroups[_userIdx];
    final userName =
        (group.first['user_name']?.toString() ?? '').isNotEmpty
            ? group.first['user_name'].toString()
            : '?';
    final letter = userName[0];

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // المحتوى: صورة/فيديو/خلفية بملء الشاشة
          Positioned.fill(
            child: _contentWidget(c),
          ),
          // نص القصة فوق المحتوى
          if (_text.isNotEmpty)
            Positioned(
              left: 24,
              right: 24,
              bottom: 70,
              child: Text(
                _text,
                textAlign: TextAlign.center,
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 19,
                    height: 1.5,
                    fontFamily: 'Cairo',
                    shadows: [
                      Shadow(
                          color: Colors.black87,
                          blurRadius: 8,
                          offset: Offset(0, 2))
                    ]),
              ),
            ),
          // مؤشر تشغيل الفيديو
          if (_current['type'] == 'video' &&
              _video != null &&
              !_video!.value.isPlaying)
            const Center(
              child: Icon(Icons.play_circle_filled_rounded,
                  size: 76, color: Colors.white),
            ),
          // طبقات النقر: يمين/يسار + الضغط المطول للإيقاف
          Positioned.fill(
            child: Row(
              children: [
                Expanded(
                  child: GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: _prev,
                    onLongPressStart: (_) => _holdPause(),
                    onLongPressEnd: (_) => _holdResume(),
                    child: const SizedBox.expand(),
                  ),
                ),
                Expanded(
                  child: GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: _next,
                    onLongPressStart: (_) => _holdPause(),
                    onLongPressEnd: (_) => _holdResume(),
                    child: const SizedBox.expand(),
                  ),
                ),
              ],
            ),
          ),
          // شريط التقدم المتحرك أعلى الشاشة
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              bottom: false,
              child: Container(
                padding: const EdgeInsets.fromLTRB(8, 8, 8, 6),
                color: Colors.black26,
                child: Row(
                  children: [
                    for (int i = 0; i < group.length; i++)
                      Expanded(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 1.5),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(2),
                            child: ProgressBar(
                              controller: _progress,
                              active: i == _storyIdx,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
          // رأس الصف (اسم المستخدم + حذف قصتي + إغلاق)
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              bottom: false,
              child: Container(
                margin: const EdgeInsets.only(top: 30),
                padding: const EdgeInsets.symmetric(horizontal: 10),
                child: Row(
                  children: [
                    NovaAvatar(
                        letter: letter,
                        size: 40,
                        radius: 12),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        userName,
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 15.5,
                            fontWeight: FontWeight.bold,
                            shadows: [
                              Shadow(
                                  color: Colors.black54,
                                  blurRadius: 6,
                                  offset: Offset(0, 1))
                            ]),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (isMine)
                      IconButton(
                        icon: const Icon(Icons.delete_outline_rounded,
                            color: Colors.white),
                        onPressed: () async {
                          final id = _current['id']?.toString();
                          if (id != null) {
                            await ApiService.delete('/stories/$id')
                                .catchError((_) => {'success': false});
                            if (mounted) Navigator.pop(context);
                          }
                        },
                      ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded,
                          color: Colors.white),
                      onPressed: () => Navigator.pop(context),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  String get _text {
    return (_current['text'] ?? _current['body'] ?? '').toString();
  }

  Widget _contentWidget(NovaColors c) {
    final fileUrl = _current['file_url']?.toString() ?? '';
    if (_current['type'] == 'image' && fileUrl.isNotEmpty) {
      // نمط واتساب: خلفية ضبابية من الصورة + الصورة الكاملة في المنتصف
      return Stack(
        fit: StackFit.expand,
        children: [
          // خلفية ضبابية ممتدة مثل واتساب
          Positioned.fill(
            child: Image.network(
              ApiService.mediaUrl(fileUrl),
              fit: BoxFit.cover,
              color: Colors.black45,
              colorBlendMode: BlendMode.darken,
            ),
          ),
          Positioned.fill(
            child: BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 30, sigmaY: 30),
              child: Container(color: Colors.black26),
            ),
          ),
          Center(
            child: Image.network(
              ApiService.mediaUrl(fileUrl),
              fit: BoxFit.contain,
              errorBuilder: (_, __, ___) => const Icon(Icons.broken_image_rounded,
                  size: 72, color: Colors.white38),
            ),
          ),
        ],
      );
    }
    if (_current['type'] == 'video') {
      if (kIsWeb && !_webVideoFailed) {
        return _webVideo.buildWidget();
      }
      if (_video != null && _videoReady) {
        return Center(
          child: AspectRatio(
            aspectRatio: _video!.value.aspectRatio,
            child: VideoPlayer(_video!),
          ),
        );
      }
      if (fileUrl.isNotEmpty) {
        return const Center(
          child: CircularProgressIndicator(color: Colors.white),
        );
      }
      return const Center(
          child: Icon(Icons.videocam_off_rounded, size: 72, color: Colors.white38));
    }
    // قصة نصية فقط — خلفية متدرجة
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            c.accent.withOpacity(0.75),
            c.accent.withOpacity(0.45),
            Colors.black87,
          ],
        ),
      ),
    );
  }
}

/// شريط تقدم: يُملا تدريجيًا أثناء التشغيل (يمثل الزمن المتبقي)
/* ══════════════════════════════════════════════════════════════════
   Helper للفيديو على الويب: عنصر <video> HTML أصلي داخل Flutter
   (يتجاوز مشاكل video_player مع wasm renderer)
   يُستخدم عبر conditional import: web_story_video.dart / stub_story_video.dart
   ══════════════════════════════════════════════════════════════════ */
final _webVideo = StoryVideoHelper();

class ProgressBar extends StatelessWidget {
  final AnimationController controller;
  final bool active;
  const ProgressBar({super.key, required this.controller, required this.active});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (_, __) => Container(
        height: 3,
        width: double.infinity,
        color: active ? Colors.white : Colors.white.withOpacity(0.35),
        child: active
            ? FractionallySizedBox(
                alignment: AlignmentDirectional.centerStart,
                widthFactor: 1 - controller.value,
                child: Container(color: Colors.white.withOpacity(0.55)),
              )
            : null,
      ),
    );
  }
}
