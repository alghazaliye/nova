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

/// الشل الرئيسي بشريط تنقل سفلي زجاجي: المحادثات، المكالمات، الحالات، جهات الاتصال، الإعدادات
class ChatsScreen extends StatefulWidget {
  const ChatsScreen({super.key});

  @override
  State<ChatsScreen> createState() => _ChatsScreenState();
}

class _ChatsScreenState extends State<ChatsScreen> {
  int _index = 0;

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

  @override
  void initState() {
    super.initState();
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
                                                ? conv.lastMessage!
                                                : conv.phone,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: TextStyle(
                                                fontSize: 13, color: c.muted),
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
