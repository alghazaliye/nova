import 'dart:async';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import 'chat_screen.dart';
import '../utils/nova_web_state.dart';
import '../utils/nova_ui.dart';
import 'stories_screen.dart';
import 'calls_screen.dart';
import 'settings_screen.dart';
import 'call_screen.dart';
import '../utils/web_notifier.dart';

/// الشل الرئيسي بشريط تنقل سفلي زجاجي: المحادثات، المكالمات، الحالات، جهات الاتصال، الإعدادات
class ChatsScreen extends StatefulWidget {
  const ChatsScreen({super.key});

  @override
  State<ChatsScreen> createState() => _ChatsScreenState();
}

class _ChatsScreenState extends State<ChatsScreen> {
  int _index = 0;
  Timer? _incomingCallTimer;
  Map<String, dynamic>? _incomingCall;

  @override
  void initState() {
    super.initState();
    if (kIsWeb) {
      final url = novaHref();
      final q = url.contains('?') ? url.split('?')[1] : '';
      for (final part in q.split('&')) {
        final kv = part.split('=');
        if (kv.length == 2 && kv[0] == 'tab') {
          if (kv[1] == 'calls' || kv[1] == 'call') _index = 1;
          if (kv[1] == 'stories' || kv[1] == 'status') _index = 2;
          if (kv[1] == 'contacts' || kv[1] == 'contact') _index = 3;
          if (kv[1] == 'settings' || kv[1] == 'setting') _index = 4;
          if (kv[1] == 'chats' || kv[1] == 'chat' || kv[1] == 'home') _index = 0;
        }
      }
    }
    // فحص المكالمات الواردة كل 2 ثانية (إشعار فوري)
    _incomingCallTimer = Timer.periodic(const Duration(seconds: 2), (_) async {
      if (!mounted || _incomingCall != null) return;
      try {
        final res = await ApiService.get('/calls/incoming');
        if (!mounted || res['success'] != true) return;
        final data = res['data'];
        if (data is List && data.isNotEmpty) {
          final call = Map<String, dynamic>.from(data[0] as Map<String, dynamic>);
          setState(() => _incomingCall = call);
          if (!mounted) return;
          await showDialog(
            context: context,
            barrierDismissible: false,
            builder: (c) => _IncomingCallDialog(
              call: call,
              onAnswer: () => _acceptCall(call),
              onReject: () => _rejectCall(call),
            ),
          );
        }
      } catch (_) {}
    });
  }

  Future<void> _acceptCall(Map<String, dynamic> call) async {
    final callId = call['id'];
    try {
      await ApiService.post('/calls/$callId/sign', body: {'signal': 'accept'});
      await ApiService.post('/calls/$callId/answer');
      if (!mounted) return;
      Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => CallScreen(callData: call)));
    } catch (e) {
      if (mounted) showToast(context, 'فشل قبول المكالمة');
    }
  }

  Future<void> _rejectCall(Map<String, dynamic> call) async {
    final callId = call['id'];
    try {
      await ApiService.post('/calls/$callId/reject');
    } catch (_) {}
  }

  @override
  void dispose() {
    _incomingCallTimer?.cancel();
    super.dispose();
  }

  final List<Widget> _pages = const [
    ChatsTab(),
    CallsScreen(),
    StoriesScreen(),
    ContactsTab(),
    SettingsScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        children: [
          Positioned.fill(
            child: IndexedStack(index: _index, children: _pages),
          ),
          if (_incomingCall != null)
            Positioned.fill(
              child: _IncomingCallDialog(
                call: _incomingCall!,
                onAnswer: () {
                  Navigator.of(context).pop();
                  _acceptCall(_incomingCall!);
                },
                onReject: () {
                  Navigator.of(context).pop();
                  _rejectCall(_incomingCall!);
                  setState(() => _incomingCall = null);
                },
              ),
            ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: NovaBottomNav(
              index: _index,
              onTap: (i) {
                if (kIsWeb) {
                  final names = ['chats', 'calls', 'stories', 'contacts', 'settings'];
                  if (_index != i) {
                    setNovaState('tab=${names[i]}');
                  }
                }
                setState(() => _index = i);
              },
            ),
          ),
        ],
      ),
    );
  }
}

/// تبويب المحادثات — شعار + بحث + بطاقات
class ChatsTab extends StatefulWidget {
  const ChatsTab({super.key});

  @override
  State<ChatsTab> createState() => _ChatsTabState();
}

class _ChatsTabState extends State<ChatsTab> {
  List<Conversation> _conversations = [];
  List<Conversation> _filtered = [];
  bool _loading = true;
  String _search = '';
  String? _autoChat;
  Timer? _pollTimer;
  int _lastTotalUnread = 0;

  @override
  void initState() {
    super.initState();
    // طلب إذن إشعارات المتصفح عند الدخول (ويب فقط)
    if (kIsWeb) {
      WidgetsBinding.instance.addPostFrameCallback((_) => WebNotifier.requestPermission());
    }
    if (kIsWeb) {
      final url = novaHref();
      final q = url.contains('?') ? url.split('?')[1] : '';
      for (final part in q.split('&')) {
        final kv = part.split('=');
        if (kv.length == 2 && kv[0] == 'chat') {
          _autoChat = Uri.decodeComponent(kv[1]);
        }
      }
    }
    _load();
    // Polling: تحديث تلقائي للمحادثات كل 5 ثوانٍ + heartbeat كل 30 ثانية
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) _refreshSilent();
    });
    Timer.periodic(const Duration(seconds: 30), (_) async {
      if (!mounted) return;
      try {
        await ApiService.post('/heartbeat', body: {'status': 'online'});
      } catch (_) {}
    });
  }

  Future<void> _refreshSilent() async {
    try {
      final res = await ApiService.get('/conversations');
      if (mounted && res['success'] == true && res['data'] is List) {
        final convs = (res['data'] as List)
            .map((e) => Conversation.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        // إشعار ويب عند وصول رسائل جديدة
        int total = 0;
        String? latestSender;
        for (final c in convs) {
          total += c.unreadCount ?? 0;
          if ((c.unreadCount ?? 0) > 0 && latestSender == null) {
            latestSender = c.name;
          }
        }
        if (kIsWeb && total > _lastTotalUnread && latestSender != null && _lastTotalUnread > 0) {
          WebNotifier.show('NOVA Messenger', '$latestSender أرسل رسالة...', tag: 'new-message');
        }
        _lastTotalUnread = total;
        setState(() {
          _conversations = convs;
          _applyFilter();
        });
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.get('/conversations');
      if (res['success'] == true && res['data'] is List) {
        setState(() {
          _conversations = (res['data'] as List)
              .map((e) => Conversation.fromJson(Map<String, dynamic>.from(e)))
              .toList();
        });
      }
    } catch (_) {}
    _applyFilter();
    setState(() => _loading = false);
    _openAutoChat();
  }

  Future<void> _openAutoChat() async {
    final phone = _autoChat;
    if (phone == null || !mounted) return;
    _autoChat = null;
    setNovaChats('auto_chat=$phone');
    Conversation? conv = _conversations.cast<Conversation?>().firstWhere(
        (c) => c != null && c.phone == phone, orElse: () => null);
    if (conv == null) {
      final searchRes = await ApiService.get('/users/search', query: {'q': phone});
      if (mounted && searchRes['success'] == true &&
          searchRes['data'] is List &&
          (searchRes['data'] as List).isNotEmpty) {
        final target = Map<String, dynamic>.from(searchRes['data'][0] as Map<String, dynamic>);
        final targetId = target['id'] ?? target['user_id'];
        if (targetId != null) {
          final r = await ApiService.post('/conversations', body: {'user_id': targetId});
          if (mounted && r['success'] == true && r['data'] != null) {
            conv = Conversation.fromJson(Map<String, dynamic>.from(r['data'] as Map<String, dynamic>));
          }
        }
      }
    }
    if (mounted && conv != null) {
      await Future.delayed(const Duration(milliseconds: 150));
      if (!mounted) return;
      pushScreen(context, ChatScreen(conv: conv));
    }
  }

  void _applyFilter() {
    final q = _search.trim().toLowerCase();
    setState(() {
      _filtered = q.isEmpty
          ? _conversations
          : _conversations
              .where((c) =>
                  c.name.toLowerCase().contains(q) ||
                  c.phone.contains(q))
              .toList();
    });
  }

  Future<void> _openChatWithUser(Map<String, dynamic> user) async {
    final targetId = user['id'] ?? user['user_id'];
    if (targetId == null) return;
    final r = await ApiService.post('/conversations', body: {'user_id': targetId});
    if (!mounted) return;
    if (r['success'] == true && r['data'] != null) {
      final conv = Conversation.fromJson(
          Map<String, dynamic>.from(r['data'] as Map<String, dynamic>));
      pushScreen(context, ChatScreen(conv: conv));
    } else {
      showToast(context, r['message'] ?? 'فشل في بدء المحادثة');
    }
  }

  Future<void> _searchAndStart() async {
    final ctrl = TextEditingController();
    final q = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('بدء محادثة جديدة'),
        content: TextField(
          controller: ctrl,
          decoration: const InputDecoration(
              hintText: 'اسم أو رقم هاتف', labelText: 'بحث'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, null),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, ctrl.text.trim()),
              child: const Text('بحث')),
        ],
      ),
    );
    if (q == null || q.length < 2 || !mounted) return;
    final res = await ApiService.get('/users/search', query: {'q': q});
    if (!mounted) return;
    if (res['success'] == true && res['data'] is List && (res['data'] as List).isNotEmpty) {
      final user = Map<String, dynamic>.from(res['data'][0] as Map<String, dynamic>);
      if (!mounted) return;
      showToast(context, 'جهة الاتصال: ${user['name'] ?? user['username'] ?? '-'}');
      await Future.delayed(const Duration(milliseconds: 300));
      if (!mounted) return;
      _openChatWithUser(user);
    } else {
      if (mounted) showToast(context, 'لم يتم العثور على نتائج');
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Column(
      children: [
        // الشريط العلوي (نمط القالب)
        Container(
          color: c.surface,
          child: SafeArea(
            bottom: false,
            child: Container(
              padding: const EdgeInsets.fromLTRB(16, 13, 16, 12),
              decoration: BoxDecoration(border: Border(bottom: BorderSide(color: c.line))),
              child: Row(
                children: [
                  const NovaLogo(size: 40, radius: 14),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Text('NOVA',
                        style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: 2)),
                  ),
                  IconBtn(icon: Icons.search, onTap: _searchAndStart),
                  IconBtn(icon: Icons.add_circle_outline, onTap: _searchAndStart, color: c.accent),
                ],
              ),
            ),
          ),
        ),
        // شريط البحث
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
          child: Container(
            decoration: BoxDecoration(
              color: c.surface2,
              borderRadius: BorderRadius.circular(17),
            ),
            child: Row(
              children: [
                const SizedBox(width: 14),
                Icon(Icons.search, size: 20, color: c.muted),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    onChanged: (v) {
                      _search = v;
                      _applyFilter();
                    },
                    decoration: InputDecoration(
                      hintText: 'ابحث في المحادثات',
                      hintStyle: TextStyle(color: c.muted, fontSize: 13),
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _filtered.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.chat_bubble_outline,
                              size: 72, color: c.muted.withOpacity(0.45)),
                          const SizedBox(height: 12),
                          Text(
                              _search.isEmpty
                                  ? 'لا توجد محادثات بعد'
                                  : 'لا نتائج لـ "$_search"',
                              style: TextStyle(fontSize: 16, color: c.muted)),
                          const SizedBox(height: 8),
                          Text('اضغط الزر لبدء محادثة جديدة',
                              style: TextStyle(fontSize: 13, color: c.muted)),
                        ],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
                        itemCount: _filtered.length,
                        itemBuilder: (_, i) {
                          final conv = _filtered[i];
                          final letter = conv.name.isNotEmpty ? conv.name[0] : '?';
                          return PressScale(
                            onTap: () => pushScreen(context, ChatScreen(conv: conv)),
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: NovaCard(
                                onTap: () => pushScreen(context, ChatScreen(conv: conv)),
                                padding: const EdgeInsets.all(12),
                                child: Row(
                                  children: [
                                    NovaAvatar(
                                        letter: letter,
                                        size: 54,
                                        radius: 18,
                                        online: conv.isVerified),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              Expanded(
                                                child: Text(
                                                    conv.name.isNotEmpty
                                                        ? conv.name
                                                        : conv.phone,
                                                    overflow: TextOverflow.ellipsis,
                                                    style: TextStyle(
                                                        fontSize: 15.5,
                                                        fontWeight: FontWeight.w800,
                                                        color: c.text)),
                                              ),
                                              if (conv.isVerified)
                                                const Icon(Icons.verified,
                                                    color: Colors.blue, size: 16),
                                            ],
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            conv.lastMessage != null &&
                                                    conv.lastMessage!.isNotEmpty
                                                ? (conv.lastMessageType == 'image'
                                                        ? 'صورة  '
                                                        : conv.lastMessageType == 'audio'
                                                                ? 'رسالة صوتية  '
                                                                : conv.lastMessageType == 'video'
                                                                        ? 'فيديو  '
                                                                        : conv.lastMessageType == 'document'
                                                                                ? 'ملف  '
                                                                                : '') +
                                                    conv.lastMessage!
                                                : conv.phone,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: TextStyle(
                                                fontSize: 13, color: c.muted),
                                          ),
                                          const SizedBox(height: 3),
                                          Row(
                                            mainAxisSize: MainAxisSize.min,
                                            children: [
                                              Icon(
                                                conv.isOnline
                                                    ? Icons.circle
                                                    : Icons.circle_outlined,
                                                size: 8,
                                                color: conv.isOnline
                                                    ? const Color(0xFF25D366)
                                                    : c.muted,
                                              ),
                                              const SizedBox(width: 5),
                                              Text(
                                                conv.isOnline
                                                    ? 'متصل الآن'
                                                    : formatLastSeen(
                                                        conv.lastSeen,
                                                        isOnline: conv.isOnline),
                                                style: TextStyle(
                                                    fontSize: 11,
                                                    color: conv.isOnline
                                                        ? const Color(0xFF25D366)
                                                        : c.muted),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                    ),
                                    if (conv.unreadCount > 0)
                                      UnreadBadge(count: conv.unreadCount),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
        ),
      ],
    );
  }
}

/// حوار مكالمة واردة — شاشة رنين كاملة مع صوت واهتزاز
/// (في الويب: بدون اهتزاز لكن مع إشعار ويب عبر html.Notification)
class _IncomingCallDialog extends StatefulWidget {
  final Map<String, dynamic> call;
  final VoidCallback onAnswer;
  final VoidCallback onReject;
  const _IncomingCallDialog({
    required this.call,
    required this.onAnswer,
    required this.onReject,
  });

  @override
  State<_IncomingCallDialog> createState() => _IncomingCallDialogState();
}

class _IncomingCallDialogState extends State<_IncomingCallDialog> {
  @override
  void initState() {
    super.initState();
    // إشعار متصفح عند مكالمة واردة (ويب فقط)
    WebNotifier.show('NOVA Messenger',
        '${widget.call['caller_name'] ?? 'مستخدم'} يُجري اتصالًا...',
        tag: 'incoming-call');
  }

  @override
  Widget build(BuildContext context) {
    final name = widget.call['caller_name'] ?? 'متصل...';
    final type = widget.call['call_type'] ?? 'voice';
    return Container(
      color: Colors.black.withOpacity(0.92),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const SizedBox(height: 20),
            // رنين: نبض الأيقونة
            Container(
              padding: const EdgeInsets.all(40),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFF25D366).withOpacity(0.15),
              ),
              child: Icon(
                type == 'video' ? Icons.videocam : Icons.call,
                color: const Color(0xFF25D366),
                size: 56,
              ),
            ),
            const SizedBox(height: 24),
            Text(name,
                style: const TextStyle(
                    color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            Text('مكالمة ${type == 'video' ? 'فيديو' : 'صوتية'} واردة',
                style: TextStyle(color: Colors.white70, fontSize: 16)),
            const SizedBox(height: 12),
            const Text('يضغط... يرسل رنينًا',
                style: TextStyle(color: Colors.white54, fontSize: 13)),
            const SizedBox(height: 60),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // رفض
                _CallBtn(
                  icon: Icons.call_end,
                  color: const Color(0xFFEF4444),
                  label: 'رفض',
                  onTap: widget.onReject,
                ),
                const SizedBox(width: 60),
                // قبول
                _CallBtn(
                  icon: Icons.call,
                  color: const Color(0xFF25D366),
                  label: 'قبول',
                  onTap: widget.onAnswer,
                ),
              ],
            ),
            const SizedBox(height: 60),
          ],
        ),
      ),
    );
  }
}

class _CallBtn extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String label;
  final VoidCallback onTap;
  const _CallBtn({
    required this.icon,
    required this.color,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        PressScale(
          onTap: onTap,
          child: Container(
            width: 70,
            height: 70,
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
              boxShadow: [BoxShadow(color: color.withOpacity(0.5), blurRadius: 20)],
            ),
            child: Icon(icon, color: Colors.white, size: 32),
          ),
        ),
        const SizedBox(height: 10),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 13)),
      ],
    );
  }
}

/// تبويب جهات الاتصال — بحث + قائمة المستخدمين + بدء محادثة
class ContactsTab extends StatefulWidget {
  const ContactsTab({super.key});

  @override
  State<ContactsTab> createState() => _ContactsTabState();
}

class _ContactsTabState extends State<ContactsTab> {
  final TextEditingController _searchCtrl = TextEditingController();
  List<Map<String, dynamic>> _users = [];
  bool _loading = false;
  bool _searched = false;

  Future<void> _search(String q) async {
    if (q.trim().length < 2) return;
    setState(() { _loading = true; });
    try {
      final res = await ApiService.get('/users/search', query: {'q': q.trim()});
      if (res['success'] == true && res['data'] is List) {
        setState(() {
          _users = (res['data'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map<String, dynamic>))
              .toList();
          _searched = true;
        });
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _openChatWith(Map<String, dynamic> user) async {
    final targetId = user['id'] ?? user['user_id'];
    if (targetId == null) return;
    final r = await ApiService.post('/conversations', body: {'user_id': targetId});
    if (!mounted) return;
    if (r['success'] == true && r['data'] != null) {
      final conv = Conversation.fromJson(
          Map<String, dynamic>.from(r['data'] as Map<String, dynamic>));
      pushScreen(context, ChatScreen(conv: conv));
    } else {
      showToast(context, r['message'] ?? 'فشل في بدء المحادثة');
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Column(
      children: [
        novaTopBar(context,
            title: 'جهات الاتصال',
            actions: [IconBtn(icon: Icons.person_add_outlined, color: c.accent)]),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
          child: Container(
            decoration: BoxDecoration(
              color: c.surface2,
              borderRadius: BorderRadius.circular(17),
            ),
            child: Row(
              children: [
                const SizedBox(width: 14),
                Icon(Icons.search, size: 20, color: c.muted),
                const SizedBox(width: 8),
                Expanded(
                  child: TextField(
                    controller: _searchCtrl,
                    onChanged: (v) {
                      if (v.trim().length >= 2) _search(v);
                    },
                    onSubmitted: _search,
                    decoration: InputDecoration(
                      hintText: 'ابحث باسم أو رقم',
                      hintStyle: TextStyle(color: c.muted, fontSize: 13),
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _users.isEmpty && _searched
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.person_search_outlined,
                              size: 72, color: c.muted.withOpacity(0.45)),
                          const SizedBox(height: 12),
                          Text('لا نتائج لهذا البحث',
                              style: TextStyle(fontSize: 16, color: c.muted)),
                          const SizedBox(height: 8),
                          Text('جرّب اسمًا أو رقمًا مختلفًا',
                              style: TextStyle(fontSize: 13, color: c.muted)),
                        ],
                      ),
                    )
                  : !_searched
                      ? Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.people_outline,
                                  size: 72, color: c.muted.withOpacity(0.45)),
                              const SizedBox(height: 12),
                              Text('ابحث عن مستخدم لبدء محادثة',
                                  style: TextStyle(fontSize: 16, color: c.muted)),
                            ],
                          ),
                        )
                      : RefreshIndicator(
                          onRefresh: () async => _search(_searchCtrl.text),
                          child: ListView.builder(
                            padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
                            itemCount: _users.length,
                            itemBuilder: (_, i) {
                              final u = _users[i];
                              final name = u['name'] ?? u['username'] ?? u['phone'] ?? '-';
                              final letter = name.toString().isNotEmpty ? name.toString()[0] : '?';
                              return PressScale(
                                onTap: () => _openChatWith(u),
                                child: Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: NovaCard(
                                    padding: const EdgeInsets.all(12),
                                    child: Row(
                                      children: [
                                        NovaAvatar(
                                            letter: letter,
                                            size: 54,
                                            radius: 18,
                                            online: (u['is_online'] ?? 0) == 1),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(children: [
                                                Expanded(
                                                  child: Text(name.toString(),
                                                      overflow: TextOverflow.ellipsis,
                                                      style: TextStyle(
                                                          fontSize: 15.5,
                                                          fontWeight: FontWeight.w800,
                                                          color: c.text)),
                                                ),
                                                if ((u['is_verified'] ?? 0) == 1)
                                                  const Icon(Icons.verified,
                                                      color: Colors.blue, size: 16),
                                              ]),
                                              if (u['phone'] != null) ...[
                                                const SizedBox(height: 3),
                                                Text(u['phone'].toString(),
                                                    style: TextStyle(fontSize: 12.5, color: c.muted)),
                                              ],
                                            ],
                                          ),
                                        ),
                                        IconBtn(
                                          icon: Icons.chat_bubble_outline,
                                          color: c.accent,
                                          onTap: () => _openChatWith(u),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
        ),
      ],
    );
  }
}
