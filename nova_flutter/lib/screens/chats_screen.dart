
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import 'chat_screen.dart';
import '../utils/nova_web_state.dart';
import 'stories_screen.dart';
import 'calls_screen.dart';
import 'settings_screen.dart';



/// الصفحة الرئيسية بشريط تنقل سفلي: المحادثات، القصص، المكالمات، الإعدادات
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
          if (kv[1] == 'stories' || kv[1] == 'status') _index = 1;
          if (kv[1] == 'calls' || kv[1] == 'call') _index = 2;
          if (kv[1] == 'settings' || kv[1] == 'setting') _index = 3;
        }
      }
    }
  }

  static const List<String> _titles = [
    'المحادثات',
    'الحالة',
    'المكالمات',
    'الإعدادات',
  ];

  final List<Widget> _pages = const [
    ChatsTab(),
    StoriesScreen(),
    CallsScreen(),
    SettingsScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _index,
        children: _pages,
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.chat_bubble_outline),
              selectedIcon: Icon(Icons.chat_bubble),
              label: 'المحادثات'),
          NavigationDestination(
              icon: Icon(Icons.circle_outlined),
              selectedIcon: Icon(Icons.circle),
              label: 'الحالة'),
          NavigationDestination(
              icon: Icon(Icons.call_outlined),
              selectedIcon: Icon(Icons.call),
              label: 'المكالمات'),
          NavigationDestination(
              icon: Icon(Icons.settings_outlined),
              selectedIcon: Icon(Icons.settings),
              label: 'الإعدادات'),
        ],
      ),
      appBar: AppBar(title: Text(_titles[_index])),
    );
  }
}

/// تبويب المحادثات مع البحث وقائمة المحادثات
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
    // افتح المحادثة إن وجدت، وإلا أنشئها مع جهة الاتصال
    Conversation? conv = _conversations.cast<Conversation?>().firstWhere(
        (c) => c!.phone == phone, orElse: () => null);
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
      Navigator.push(context, MaterialPageRoute(builder: (_) => ChatScreen(conv: conv!)));
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

  Future<void> _searchContact() async {
    final ctrl = TextEditingController();
    final q = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('بحث عن جهة اتصال'),
        content: TextField(
          controller: ctrl,
          decoration: const InputDecoration(
              hintText: 'اسم أو رقم', labelText: 'بحث'),
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
    if (q == null || q.isEmpty || !mounted) return;
    final res = await ApiService.get('/users/search', query: {'q': q});
    if (!mounted) return;
    if (res['success'] == true && res['data'] is List && (res['data'] as List).isNotEmpty) {
      final user = Map<String, dynamic>.from(res['data'][0] as Map<String, dynamic>);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('جهة الاتصال: ${user['name'] ?? user['phone'] ?? '-'}'),
          action: SnackBarAction(
            label: 'محادثة',
            onPressed: () async {
              final phone = user['phone']?.toString() ?? '';
              final r = await ApiService.post('/conversations',
                  body: {'contact_phone': phone});
              if (!mounted) return;
              if (r['success'] == true && r['data'] != null) {
                final conv = Conversation.fromJson(
                    Map<String, dynamic>.from(r['data'] as Map<String, dynamic>));
                Navigator.push(context,
                    MaterialPageRoute(builder: (_) => ChatScreen(conv: conv)));
              } else {
                ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(r['message'] ?? 'فشل في بدء المحادثة')));
              }
            },
          ),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('لم يتم العثور على نتائج')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      // شريط البحث
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
        child: TextField(
          onChanged: (v) {
            _search = v;
            _applyFilter();
          },
          decoration: InputDecoration(
            hintText: 'ابحث في المحادثات',
            prefixIcon: const Icon(Icons.search),
            suffixIcon: IconButton(
              icon: const Icon(Icons.search),
              tooltip: 'بحث عن جهة اتصال',
              onPressed: _searchContact,
            ),
            filled: true,
            fillColor: Theme.of(context).colorScheme.surfaceVariant,
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(28),
                borderSide: BorderSide.none),
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
                            size: 72, color: Colors.grey.shade400),
                        const SizedBox(height: 12),
                        Text(
                            _search.isEmpty
                                ? 'لا توجد محادثات بعد'
                                : 'لا نتائج لـ "$_search"',
                            style: const TextStyle(
                                fontSize: 16, color: Colors.black54)),
                        const SizedBox(height: 8),
                        const Text('اضغط الزر لبدء محادثة جديدة',
                            style:
                                TextStyle(fontSize: 13, color: Colors.black45)),
                      ],
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: _load,
                    child: ListView.builder(
                      itemCount: _filtered.length,
                      itemBuilder: (_, i) {
                        final conv = _filtered[i];
                        return ListTile(
                          leading: CircleAvatar(
                              backgroundColor:
                                  Theme.of(context).colorScheme.primary,
                              child: Text(
                                  conv.name.isNotEmpty ? conv.name[0] : '?')),
                          title: Row(children: [
                            Expanded(
                              child: Text(conv.name.isNotEmpty ? conv.name : conv.phone,
                                  overflow: TextOverflow.ellipsis),
                            ),
                            if (conv.isVerified)
                              const Padding(
                                  padding: EdgeInsets.only(right: 4),
                                  child: Icon(Icons.verified,
                                      color: Colors.blue, size: 16)),
                          ]),
                          subtitle: conv.lastMessage != null
                              ? Text(conv.lastMessage!,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis)
                              : Text(conv.phone,
                                  style: const TextStyle(
                                      fontSize: 12, color: Colors.black45)),
                          trailing: conv.unreadCount > 0
                              ? CircleAvatar(
                                  radius: 10,
                                  backgroundColor:
                                      Theme.of(context).colorScheme.primary,
                                  child: Text('${conv.unreadCount}',
                                      style: const TextStyle(
                                          color: Colors.white, fontSize: 11)))
                              : null,
                          onTap: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => ChatScreen(conv: conv))),
                        );
                      },
                    ),
                  ),
      ),
    ]);
  }
}
