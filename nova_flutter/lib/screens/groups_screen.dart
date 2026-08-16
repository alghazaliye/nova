import 'package:flutter/material.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../providers/auth_provider.dart';
import '../utils/nova_ui.dart';
import 'group_info_screen.dart';
import 'chat_screen.dart';

/// قائمة مجموعاتي — تعرض مجموعات المستخدم مع عدد الأعضاء.
/// النقر على مجموعة يفتح شاشة معلوماتها (المشرفون يديرون الأعضاء).
class GroupsScreen extends StatefulWidget {
  const GroupsScreen({super.key});

  @override
  State<GroupsScreen> createState() => _GroupsScreenState();
}

class _GroupsScreenState extends State<GroupsScreen> {
  List<Map<String, dynamic>> _groups = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadGroups();
  }

  Future<void> _loadGroups() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.get('/groups/mine');
      if (!mounted) return;
      if (res['success'] == true) {
        final data = res['data'];
        final list = <Map<String, dynamic>>[];
        if (data is List) {
          for (final raw in data) {
            if (raw is Map) list.add(Map<String, dynamic>.from(raw));
          }
        }
        setState(() {
          _groups = list;
          _loading = false;
        });
      } else {
        setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Scaffold(
      backgroundColor: c.bg,
      body: SafeArea(
        child: Column(
          children: [
            novaTopBar(context,
                title: 'مجموعاتي',
                actions: [IconBtn(icon: Icons.refresh, onTap: _loadGroups)]),
            Expanded(
              child: _loading
                  ? Center(child: CircularProgressIndicator(color: c.accent))
                  : _groups.isEmpty
                      ? Center(
                          child: Text('لا توجد مجموعات بعد',
                              style: TextStyle(fontSize: 15, color: c.muted)),
                        )
                      : RefreshIndicator(
                          onRefresh: _loadGroups,
                          color: c.accent,
                          child: ListView.separated(
                            padding: const EdgeInsets.all(14),
                            itemCount: _groups.length,
                            separatorBuilder: (_, __) =>
                                const SizedBox(height: 10),
                            itemBuilder: (_, i) {
                              final g = _groups[i];
                              final name =
                                  (g['name'] ?? 'مجموعة').toString();
                              final memberCount =
                                  (g['member_count'] ?? g['members_count'] ??
                                          (g['members'] as List?)?.length ??
                                          0)
                                      .toString();
                              return NovaCard(
                                onTap: () => _openGroup(g),
                                child: Row(children: [
                                  NovaAvatar(
                                    letter: name.isNotEmpty ? name[0] : 'N',
                                    size: 52,
                                    radius: 16,
                                    imageUrl: _groupAvatar(g),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(name,
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: TextStyle(
                                                fontSize: 15.5,
                                                fontWeight: FontWeight.w800,
                                                color: c.text)),
                                        const SizedBox(height: 4),
                                        Text('$memberCount أعضاء',
                                            style: TextStyle(
                                                fontSize: 12.5,
                                                color: c.muted)),
                                      ],
                                    ),
                                  ),
                                  Icon(Icons.chevron_left, color: c.muted),
                                ]),
                              );
                            },
                          ),
                        ),
            ),
          ],
        ),
      ),
    );
  }

  String? _groupAvatar(Map<String, dynamic> g) {
    final a = g['avatar'];
    if (a == null || a.toString().isEmpty) return null;
    return ApiService.mediaUrl(a.toString());
  }

  void _openGroup(Map<String, dynamic> g) {
    final convId = g['conversation_id']?.toString();
    if (convId == null || convId.isEmpty) return;
    final name = (g['name'] ?? 'مجموعة').toString();
    final conv = Conversation(
      id: int.tryParse(convId) ?? 0,
      uuid: g['uuid'] ?? '',
      name: name,
      avatar: _groupAvatar(g),
      members: const [],
      isGroup: true,
      groupId: int.tryParse(convId),
      memberCount: _memberCount(g),
    );
    pushScreen(context, ChatScreen(conv: conv));
  }

  int _memberCount(Map<String, dynamic> g) {
    try {
      if (g['member_count'] != null) {
        return int.parse(g['member_count'].toString());
      }
      final members = g['members'];
      if (members is List) return members.length;
    } catch (_) {}
    return 0;
  }
}
