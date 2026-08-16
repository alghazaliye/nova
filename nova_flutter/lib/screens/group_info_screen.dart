import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';

/// شاشة معلومات المجموعة (أسلوب واتساب):
/// - صورة واسم ووصف المجموعة
/// - قائمة الأعضاء مع الأدوار
/// - للمشرفين: إضافة/حذف أعضاء، تغيير اسم المجموعة، إعدادات النشر
/// - للمالك: تعيين/إزالة مشرف
class GroupInfoScreen extends StatefulWidget {
  final int groupId;
  const GroupInfoScreen({super.key, required this.groupId});
  @override
  State<GroupInfoScreen> createState() => _GroupInfoScreenState();
}

class _GroupInfoScreenState extends State<GroupInfoScreen> {
  Map<String, dynamic>? _group;
  List<Map<String, dynamic>> _members = [];
  List<Map<String, dynamic>> _availableUsers = [];
  String _myRole = 'member';
  bool _loading = true;
  bool _busy = false;
  final TextEditingController _searchCtrl = TextEditingController();
  final TextEditingController _titleCtrl = TextEditingController();
  final TextEditingController _descCtrl = TextEditingController();
  bool _onlyAdminsMessage = false;
  bool get _isAdmin => _myRole == 'admin' || _myRole == 'owner';
  bool get _isOwner => _myRole == 'owner';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await ApiService.get('/groups/${widget.groupId}');
      if (mounted && res['success'] == true) {
        final data = Map<String, dynamic>.from(res['data']);
        _group = data;
        _members = ((data['members'] ?? []) as List)
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
        final settings = Map<String, dynamic>.from(data['settings'] ?? {});
        _onlyAdminsMessage =
            settings['only_admins_can_message'] == 1 ||
            settings['only_admins_can_message'] == true;
        _titleCtrl.text = data['name'] ?? data['title'] ?? '';
        _descCtrl.text = data['description'] ?? '';
        for (final m in _members) {
          if (int.parse(m['user_id'].toString()) ==
              ApiService.userId) {
            _myRole = m['role']?.toString() ?? 'member';
          }
        }
      }
      // جلب قائمة المستخدمين المتاحين للإضافة
      try {
        final users = await ApiService.get('/users/search', query: {'q': 'a'});
        if (mounted && users['success'] == true && users['data'] is List) {
          _availableUsers = (users['data'] as List)
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }
      } catch (_) {}
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _showAddMembersDialog() async {
    final c = NovaColors.of(context);
    final Set<int> selected = {};
    final memberIds = _members
        .map((m) => int.parse(m['user_id'].toString()))
        .toSet();
    final candidates = _availableUsers
        .where((u) => !memberIds.contains(int.parse(u['id'].toString())))
        .toList();
    await showDialog<void>(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, st) => AlertDialog(
          backgroundColor: c.surface,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: const Text('إضافة أعضاء'),
          content: SizedBox(
            width: 340,
            height: 360,
            child: candidates.isEmpty
                ? const Center(child: Text('لا يوجد مستخدمون متاحون'))
                : ListView.builder(
                    itemCount: candidates.length,
                    itemBuilder: (_, i) {
                      final u = candidates[i];
                      final uid = int.parse(u['id'].toString());
                      final name =
                          u['name'] ?? u['username'] ?? u['phone'] ?? '-';
                      final on = selected.contains(uid);
                      return CheckboxListTile(
                        value: on,
                        activeColor: c.accent,
                        title: Text(name, style: TextStyle(color: c.text)),
                        onChanged: (v) => st(() {
                          if (v == true) selected.add(uid);
                          selected.remove(uid);
                        }),
                      );
                    },
                  ),
          ),
          actions: [
            TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: Text('إلغاء', style: TextStyle(color: c.muted))),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                  backgroundColor: c.accent,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12))),
              onPressed: selected.isEmpty ? null : () => _addMembers(selected.toList()),
              child: const Text('إضافة'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _addMembers(List<int> ids) async {
    setState(() => _busy = true);
    try {
      final res = await ApiService.post('/groups/${widget.groupId}/members',
          body: {'member_ids': ids});
      if (!mounted) return;
      showToast(context, res['message'] ?? 'تمت إضافة الأعضاء');
      Navigator.pop(context); // إغلاق Dialog الأب
      await _load();
    } catch (_) {
      if (mounted) showToast(context, 'فشل في إضافة الأعضاء');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _removeMember(Map<String, dynamic> m) async {
    final uid = int.parse(m['user_id'].toString());
    final name = m['name'] ?? m['username'] ?? 'المستخدم';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: NovaColors.of(ctx).surface,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('إزالة عضو'),
        content: Text('هل تريد إزالة "$name" من المجموعة؟'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('إزالة', style: TextStyle(color: Colors.red))),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _busy = true);
    try {
      final res =
          await ApiService.delete('/groups/${widget.groupId}/members/$uid');
      if (!mounted) return;
      showToast(context, res['message'] ?? 'تمت إزالة العضو');
      await _load();
    } catch (_) {
      if (mounted) showToast(context, 'فشل في إزالة العضو');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _setRole(Map<String, dynamic> m, String role) async {
    final uid = int.parse(m['user_id'].toString());
    final name = m['name'] ?? m['username'] ?? 'المستخدم';
    setState(() => _busy = true);
    try {
      final res = await ApiService.put(
          '/groups/${widget.groupId}/members/$uid/role',
          body: {'role': role});
      if (!mounted) return;
      showToast(
          context,
          res['message'] ??
              (role == 'admin' ? 'تم تعيين مشرف' : 'تمت إزالة الإدارة'));
      await _load();
    } catch (_) {
      if (mounted) showToast(context, 'فشل في تحديث الدور');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _updateTitle() async {
    final c = NovaColors.of(context);
    _titleCtrl.text = _group?['name'] ?? '';
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: c.surface,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('اسم المجموعة'),
        content: TextField(
          controller: _titleCtrl,
          decoration: InputDecoration(
            hintText: 'اسم المجموعة',
            filled: true,
            fillColor: c.surface2,
            border:
                OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
          ),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text('إلغاء', style: TextStyle(color: c.muted))),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
                backgroundColor: c.accent,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12))),
            onPressed: () async {
              if (_titleCtrl.text.trim().isEmpty) return;
              try {
                final res = await ApiService.put(
                    '/groups/${widget.groupId}/title',
                    body: {'title': _titleCtrl.text.trim()});
                if (ctx.mounted) Navigator.pop(ctx);
                if (mounted) {
                  showToast(context, res['message'] ?? 'تم تحديث الاسم');
                  await _load();
                }
              } catch (_) {
                if (mounted) showToast(context, 'فشل في تحديث الاسم');
              }
            },
            child: const Text('حفظ'),
          ),
        ],
      ),
    );
  }

  Future<void> _toggleAdminsOnly() async {
    if (!mounted) return;
    setState(() => _busy = true);
    try {
      final res = await ApiService.put('/groups/${widget.groupId}/settings',
          body: {'only_admins_can_message': !_onlyAdminsMessage ? 1 : 0});
      if (!mounted) return;
      if (res['success'] == true) {
        setState(() => _onlyAdminsMessage = !_onlyAdminsMessage);
      }
      showToast(context,
          res['message'] ?? 'تم تحديث إعداد النشر');
    } catch (_) {
      if (mounted) showToast(context, 'فشل في تحديث الإعداد');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _leaveGroup() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: NovaColors.of(ctx).surface,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Text('مغادرة المجموعة'),
        content: const Text('هل تريد مغادرة هذه المجموعة؟'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('مغادرة',
                  style: TextStyle(color: Colors.red))),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _busy = true);
    try {
      final res =
          await ApiService.post('/groups/${widget.groupId}/leave');
      if (!mounted) return;
      showToast(context, res['message'] ?? 'تمت مغادرة المجموعة');
      if (context.mounted) Navigator.of(context).pop();
    } catch (_) {
      if (mounted) showToast(context, 'فشل في مغادرة المجموعة');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final membersCount = (_group?['member_count'] ?? _members.length) ?? 0;
    return Scaffold(
      backgroundColor: c.bg,
      body: SafeArea(
        child: Column(
          children: [
            novaTopBar(
              context,
              title: 'معلومات المجموعة',
              actions: [
                IconBtn(icon: Icons.close, onTap: () => Navigator.pop(context))
              ],
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView(
                        padding: const EdgeInsets.all(16),
                        children: [
                          // صورة واسم المجموعة
                          Center(
                            child: Stack(
                              clipBehavior: Clip.none,
                              children: [
                                NovaAvatar(
                                  letter: (_group?['name'] ?? '?').isNotEmpty
                                      ? (_group?['name'] ?? '?')[0]
                                      : '?',
                                  size: 110,
                                  radius: 32,
                                  imageUrl: _group?['avatar'],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 14),
                          Center(
                            child: Column(
                              children: [
                                Text(
                                  _group?['name'] ?? '',
                                  style: TextStyle(
                                    fontSize: 22,
                                    fontWeight: FontWeight.w900,
                                    color: c.text,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  membersCount > 0
                                      ? '$membersCount مشارك'
                                      : 'مجموعة',
                                  style: TextStyle(fontSize: 13, color: c.muted),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 18),
                          // أدوات المشرفين
                          if (_isAdmin) ...[
                            NovaCard(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 6),
                              child: Row(
                                children: [
                                  const Icon(Icons.person_add, size: 20),
                                  const SizedBox(width: 14),
                                  Expanded(
                                      child: Text('إضافة أعضاء',
                                          style: TextStyle(color: c.text))),
                                  if (_busy)
                                    const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2))
                                  else
                                    Icon(Icons.chevron_left,
                                        color: c.muted),
                                ],
                              ),
                              onTap: _showAddMembersDialog,
                            ),
                            const SizedBox(height: 8),
                            NovaCard(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 6),
                              child: Row(
                                children: [
                                  const Icon(Icons.edit, size: 20),
                                  const SizedBox(width: 14),
                                  Expanded(
                                      child: Text('اسم المجموعة',
                                          style: TextStyle(color: c.text))),
                                  Icon(Icons.chevron_left, color: c.muted),
                                ],
                              ),
                              onTap: _updateTitle,
                            ),
                            const SizedBox(height: 8),
                            NovaCard(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 6),
                              child: Row(
                                children: [
                                  const Icon(Icons.admin_panel_settings,
                                      size: 20),
                                  const SizedBox(width: 14),
                                  Expanded(
                                      child: Text('النشر للمشرفين فقط',
                                          style: TextStyle(color: c.text))),
                                  Switch(
                                    value: _onlyAdminsMessage,
                                    activeColor: c.accent,
                                    onChanged: _busy ? null : (_) => _toggleAdminsOnly(),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 20),
                          ],
                          // إعداد النشر (للقراءة للعضو العادي)
                          if (!_isAdmin)
                            Padding(
                              padding:
                                  const EdgeInsets.symmetric(horizontal: 8),
                              child: Row(
                                children: [
                                  Icon(Icons.info_outline,
                                      size: 15, color: c.muted),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      _onlyAdminsMessage
                                          ? 'النشر مسموح للمشرفين فقط'
                                          : 'النشر مسموح لجميع الأعضاء',
                                      style:
                                          TextStyle(fontSize: 12, color: c.muted),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          const SizedBox(height: 16),
                          // قائمة الأعضاء
                          Row(
                            children: [
                              const SectionTitle('الأعضاء'),
                              const Spacer(),
                              Text('$membersCount',
                                  style: TextStyle(
                                      fontSize: 13, color: c.muted)),
                            ],
                          ),
                          const SizedBox(height: 10),
                          ..._members.map((m) {
                            final uid = int.parse(m['user_id'].toString());
                            final isMe = uid == ApiService.userId;
                            final role = m['role']?.toString() ?? 'member';
                            final name = m['name'] ??
                                m['username'] ??
                                m['phone'] ??
                                '-';
                            final letter = name.isNotEmpty ? name[0] : '?';
                            String roleLabel = '';
                            Color roleColor = c.muted;
                            if (role == 'owner') {
                              roleLabel = 'مالك';
                              roleColor = const Color(0xFF5B6CFF);
                            } else if (role == 'admin') {
                              roleLabel = 'مشرف';
                              roleColor = c.accent;
                            }
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: NovaCard(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 10),
                                child: Row(
                                  children: [
                                    NovaAvatar(
                                      letter: letter,
                                      size: 44,
                                      radius: 14,
                                      imageUrl: m['avatar'],
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              Flexible(
                                                child: Text(
                                                  isMe
                                                      ? '$name (أنت)'
                                                      : name,
                                                  maxLines: 1,
                                                  overflow:
                                                      TextOverflow.ellipsis,
                                                  style: TextStyle(
                                                      fontSize: 15,
                                                      fontWeight:
                                                          FontWeight.bold,
                                                      color: c.text),
                                                ),
                                              ),
                                              if (roleLabel.isNotEmpty) ...[
                                                const SizedBox(width: 6),
                                                Container(
                                                  padding:
                                                      const EdgeInsets.symmetric(
                                                          horizontal: 8,
                                                          vertical: 2),
                                                  decoration: BoxDecoration(
                                                    color: roleColor
                                                        .withOpacity(0.12),
                                                    borderRadius:
                                                        BorderRadius.circular(
                                                            10),
                                                  ),
                                                  child: Text(roleLabel,
                                                      style: TextStyle(
                                                          fontSize: 10.5,
                                                          fontWeight:
                                                              FontWeight.bold,
                                                          color: roleColor)),
                                                ),
                                              ],
                                            ],
                                          ),
                                          if (m['phone'] != null &&
                                              m['phone'] != '')
                                            Text(m['phone'].toString(),
                                                style: TextStyle(
                                                    fontSize: 11.5,
                                                    color: c.muted)),
                                        ],
                                      ),
                                    ),
                                    if (_isAdmin && !isMe && role != 'owner')
                                      PopupMenuButton<String>(
                                        icon: Icon(Icons.more_vert,
                                            size: 19, color: c.muted),
                                        color: c.surface,
                                        itemBuilder: (_) => [
                                          if (_isOwner && role != 'admin')
                                            const PopupMenuItem(
                                                value: 'make_admin',
                                                child: Text('تعيين مشرف')),
                                          if (_isOwner && role == 'admin')
                                            const PopupMenuItem(
                                                value: 'demote',
                                                child: Text('إزالة الإدارة')),
                                          const PopupMenuItem(
                                              value: 'remove',
                                              child: Text('إزالة من المجموعة',
                                                  style: TextStyle(
                                                      color: Colors.red))),
                                        ],
                                        onSelected: (v) {
                                          if (v == 'make_admin') {
                                            _setRole(m, 'admin');
                                          } else if (v == 'demote') {
                                            _setRole(m, 'member');
                                          } else if (v == 'remove') {
                                            _removeMember(m);
                                          }
                                        },
                                      ),
                                  ],
                                ),
                              ),
                            );
                          }),
                          const SizedBox(height: 12),
                          // مغادرة المجموعة
                          NovaCard(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 16, vertical: 12),
                            child: Row(
                              children: [
                                const Icon(Icons.logout,
                                    size: 19, color: Colors.red),
                                const SizedBox(width: 14),
                                Expanded(
                                    child: Text('مغادرة المجموعة',
                                        style: TextStyle(
                                            color: Colors.red,
                                            fontWeight: FontWeight.w700))),
                                if (_busy)
                                  const SizedBox(
                                      width: 16,
                                      height: 16,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2)),
                              ],
                            ),
                            onTap: _leaveGroup,
                          ),
                          const SizedBox(height: 20),
                        ],
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
