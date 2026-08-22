import 'dart:async';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:provider/provider.dart';
import '../models/user_model.dart';
import '../offline/local_sync_service.dart';
import '../services/api_service.dart';
import '../providers/auth_provider.dart';
import 'chat_screen.dart';
import '../utils/nova_web_state.dart';
import '../utils/nova_ui.dart';
import 'stories_screen.dart';
import 'calls_screen.dart';
import 'settings_screen.dart';
import 'groups_screen.dart';
import 'profile_screen.dart';
import 'call_screen.dart';
import '../utils/web_notifier.dart';

/// الغلاف الرئيسي لمساحة Nova مع مسار تنقل جانبي متكيف: المحادثات، المكالمات، الحالات، جهات الاتصال، الإعدادات
class ChatsScreen extends StatefulWidget {
  const ChatsScreen({super.key});

  @override
  State<ChatsScreen> createState() => _ChatsScreenState();
}

class _ChatsScreenState extends State<ChatsScreen> {
  int _index = 0;
  Timer? _incomingCallTimer;
  Timer? _activeCallTimer;
  Timer? _contactsTimer;
  Map<String, dynamic>? _incomingCall;
  bool _incomingCallPolling = false;
  bool _activeCallPolling = false;
  // يمنع إعادة إظهار نفس المكالمة بعد التعامل معها قبل وصول تحديث الحالة.
  final Set<String> _handledIncomingCallIds = <String>{};

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
          if (kv[1] == 'chats' || kv[1] == 'chat' || kv[1] == 'home')
            _index = 0;
        }
      }
    }
    // فحص المكالمات الواردة كل ثانيتين، بما في ذلك التحقق من انتهاء
    // المكالمة الحالية حتى لا تبقى نافذة الرنين ظاهرة بعد إغلاق المتصل.
    _incomingCallTimer = Timer.periodic(const Duration(seconds: 2), (_) {
      if (mounted && ApiService.token != null) _pollIncomingCall();
    });
    _pollIncomingCall();
    _activeCallTimer = Timer.periodic(const Duration(seconds: 3), (_) {
      if (mounted && ApiService.token != null) _pollActiveCall();
    });
  }

  Future<void> _pollActiveCall() async {
    if (_activeCallPolling || ApiService.token == null) return;
    _activeCallPolling = true;
    try {
      final res = await ApiService.get('/calls');
      if (!mounted) return;
      if (res['status_code'] == 401) {
        _activeCallTimer?.cancel();
        return;
      }
      if (res['success'] != true) return;
      final data = res['data'];
      if (data is! List) return;
      for (final raw in data) {
        if (raw is! Map) continue;
        final call = Map<String, dynamic>.from(raw);
        final status = (call['status'] ?? '').toString();
        if (status != 'ringing' && status != 'answered' && status != 'accepted') continue;
        final callId = call['id']?.toString();
        if (callId == null || callId.isEmpty) continue;
        // فحص أن المستخدم مشارك فعليًا في المكالمة
        final callerId = call['caller_id']?.toString();
        final calleeId = call['callee_id']?.toString();
        final me = ApiService.userId.toString();
        if (callerId != me && calleeId != me) continue;
        call['is_outgoing'] = callerId == me;
        if (ModalRoute.of(context)?.isCurrent != true) continue;
        await Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => CallScreen(callData: call)));
        return;
      }
    } catch (_) {}
    finally {
      _activeCallPolling = false;
    }
  }

  Future<void> _pollIncomingCall() async {
    if (!context.read<AuthProvider>().effectiveAppSettings.allowCalls) {
      if (_incomingCall != null && mounted)
        setState(() => _incomingCall = null);
      return;
    }
    if (_incomingCallPolling || ApiService.token == null) return;
    _incomingCallPolling = true;
    try {
      final res = await ApiService.get('/calls/incoming');
      if (!mounted) return;
      if (res['status_code'] == 401) {
        _incomingCallTimer?.cancel();
        return;
      }
      if (res['success'] != true) return;
      final data = res['data'];
      Map<String, dynamic>? nextCall;
      if (data is List) {
        for (final raw in data) {
          if (raw is! Map) continue;
          final candidate = Map<String, dynamic>.from(raw);
          final candidateId = candidate['id']?.toString();
          if (candidateId == null ||
              candidateId.isEmpty ||
              _handledIncomingCallIds.contains(candidateId)) {
            continue;
          }
          nextCall = candidate;
          break;
        }
      }
      final currentId = _incomingCall?['id']?.toString();
      final nextId = nextCall?['id']?.toString();
      if (currentId != nextId) {
        setState(() => _incomingCall = nextCall);
      }
    } catch (_) {
      // لا نزيل النافذة بسبب انقطاع مؤقت؛ سيتم التحقق في الدورة التالية.
    } finally {
      _incomingCallPolling = false;
    }
  }

  Future<void> _acceptCall(Map<String, dynamic> call) async {
    final callId = call['id']?.toString();
    if (callId == null || callId.isEmpty || callId == 'null') return;
    _handledIncomingCallIds.add(callId);
    setState(() => _incomingCall = null);
    final sessionId = call['session_id'] ?? call['sessionId'];
    final res = await ApiService.post('/calls/$callId/answer', body: {
      if (sessionId != null) 'session_id': sessionId,
    });
    if (!mounted) return;
    if (res['success'] == true) {
      await Navigator.of(context)
          .push(MaterialPageRoute(builder: (_) => CallScreen(callData: call)));
    } else {
      showToast(context, res['message'] ?? 'انتهت المكالمة أو تعذر قبولها');
    }
  }

  Future<void> _rejectCall(Map<String, dynamic> call) async {
    final callId = call['id']?.toString();
    if (callId == null || callId.isEmpty || callId == 'null') return;
    _handledIncomingCallIds.add(callId);
    if (mounted) setState(() => _incomingCall = null);
    try {
      final sessionId = call['session_id'] ?? call['sessionId'];
      await ApiService.post('/calls/$callId/reject', body: {
        if (sessionId != null) 'session_id': sessionId,
      });
    } catch (_) {}
  }

  @override
  void dispose() {
    _incomingCallTimer?.cancel();
    _activeCallTimer?.cancel();
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
      resizeToAvoidBottomInset: true,
      body: Stack(
        children: [
          Positioned.fill(
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.only(bottom: 64),
                child: IndexedStack(index: _index, children: _pages),
              ),
            ),
          ),
          if (_incomingCall != null)
            Positioned.fill(
              child: _IncomingCallDialog(
                call: _incomingCall!,
                onAnswer: () {
                  final call = _incomingCall;
                  if (call != null) _acceptCall(call);
                },
                onReject: () {
                  final call = _incomingCall;
                  if (call != null) _rejectCall(call);
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
                  final settings = context.read<AuthProvider>().effectiveAppSettings;
                  if (i == 1 && !settings.allowCalls) {
                    showToast(context, 'المكالمات موقوفة من الإدارة');
                    return;
                  }
                  if (i == 2 && !settings.allowStories) {
                    showToast(context, 'الحالات موقوفة من الإدارة');
                    return;
                  }
                  if (kIsWeb) {
                    final names = [
                      'chats',
                      'calls',
                      'stories',
                      'contacts',
                      'settings'
                    ];
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

class _ChatsTabState extends State<ChatsTab> with WidgetsBindingObserver {
  List<Conversation> _conversations = [];
  List<Conversation> _filtered = [];
  bool _loading = true;
  String _search = '';
  String? _autoChat;
  Timer? _pollTimer;
  Timer? _heartbeatTimer;
  int _lastTotalUnread = 0;

  @override
  void initState() {
    super.initState();
    // طلب إذن إشعارات المتصفح عند الدخول (ويب فقط)
    if (kIsWeb) {
      WidgetsBinding.instance
          .addPostFrameCallback((_) => WebNotifier.requestPermission());
    }
    if (kIsWeb) {
      final url = novaHref();
      final q = url.contains('?') ? url.split('?')[1] : '';
      for (final part in q.split('&')) {
        final kv = part.split('=');
        if (kv.length == 2 && kv[0] == 'chat') {
          final value = Uri.decodeComponent(kv[1]);
          // Numeric chat values are conversation IDs handled by AppRouter.
          // Only a non-numeric value should be treated as a phone deep link.
          if (int.tryParse(value) == null) _autoChat = value;
        }
      }
    }
    _load();
    // حضور الويب: blur/focus/beforeunload → offline/online فعليًا
    if (kIsWeb) enablePresenceListeners();
    // Polling: تحديث تلقائي للمحادثات كل 5 ثوانٍ + heartbeat كل 45 ثانية
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) _refreshSilent();
    });
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 45), (_) async {
      if (!mounted) return;
      try {
        await ApiService.post('/heartbeat', body: {'status': 'online'});
      } catch (_) {}
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    // حضور حقيقي: عند العودة يظهر متصلًا، وعند الخلفية أو الإغلاق offline فعليًا
    if (state == AppLifecycleState.resumed) {
      ApiService.post('/heartbeat', body: {'status': 'online'}).ignore();
    } else if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.detached) {
      ApiService.post('/heartbeat/offline', body: {}).ignore();
    }
  }

  Future<void> _refreshSilent() async {
    if (ApiService.token == null) return;
    try {
      final res = await ApiService.get('/conversations');
      if (res['status_code'] == 401) {
        _pollTimer?.cancel();
        _heartbeatTimer?.cancel();
        return;
      }
      if (mounted && res['success'] == true && res['data'] is List) {
        final convs = (res['data'] as List)
            .map((e) => Conversation.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        _mergeCalls(convs);
        // إشعار ويب عند وصول رسائل جديدة
        int total = 0;
        String? latestSender;
        for (final c in convs) {
          total += c.unreadCount;
          if (c.unreadCount > 0 && latestSender == null) {
            latestSender = c.name;
          }
        }
        if (total > _lastTotalUnread &&
            latestSender != null &&
            _lastTotalUnread > 0) {
          if (kIsWeb) {
            WebNotifier.show('NOVA Messenger', '$latestSender أرسل رسالة...',
                tag: 'new-message');
          } else if (mounted && ModalRoute.of(context)?.isCurrent == true) {
            showToast(context, 'رسالة جديدة من $latestSender');
          }
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
    WidgetsBinding.instance.removeObserver(this);
    _pollTimer?.cancel();
    _heartbeatTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    if (mounted) setState(() => _loading = true);
    List<Conversation>? loaded;
    try {
      final res = await ApiService.get('/conversations');
      if (res['success'] == true && res['data'] is List) {
        loaded = (res['data'] as List)
            .map((e) => Conversation.fromJson(Map<String, dynamic>.from(e)))
            .toList();
        _mergeCalls(loaded);
        // Offline-First: حفظ المحادثات محليًا للمزامنة عند عدم الاتصال
        try {
          await LocalSyncService.upsertChats(loaded
              .map((cv) => <String, dynamic>{
                    'id': cv.id,
                    'uuid': cv.uuid,
                    'name': cv.name,
                    'title': cv.name,
                    'avatar': cv.avatar,
                    'last_message': cv.lastMessage,
                    'last_message_at': cv.lastMessageAt,
                    'updated_at': cv.lastMessageAt,
                    'unread_count': cv.unreadCount,
                    'type': cv.isGroup ? 'group' : 'private',
                    'is_group': cv.isGroup,
                    'group_id': cv.groupId,
                    'member_count': cv.memberCount,
                    'is_muted': 0,
                    'is_pinned': 0,
                  })
              .toList());
        } catch (_) {}
      }
    } catch (_) {}
    // عند فشل الشبكة: استعادة آخر نسخة محلية من Drift
    if (loaded == null) {
      try {
        final rows = await LocalSyncService.cachedChats();
        if (rows.isNotEmpty) {
          loaded = rows.map((r) => Conversation(
                id: r.id,
                uuid: r.chatId,
                name: r.title,
                avatar: r.avatar,
                lastMessage: r.lastMessagePreview.isEmpty ? null : r.lastMessagePreview,
                lastMessageAt: (r.lastMessageAt ?? '').isEmpty ? null : r.lastMessageAt,
                unreadCount: r.unreadCount,
                members: [],
                isGroup: r.isGroup,
                memberCount: r.memberCount,
                groupId: r.groupId,
              )).toList();
          _mergeCalls(loaded);
        }
      } catch (_) {}
    }
    if (!mounted) return;
    setState(() {
      if (loaded != null) {
        _conversations = loaded;
        // اعتماد القيمة الحالية كخط أساس حتى لا يظهر إشعار عند أول تحميل.
        _lastTotalUnread = _conversations.fold<int>(
            0, (sum, item) => sum + item.unreadCount);
      }
      _applyFilter();
      _loading = false;
    });
    _openAutoChat();
  }

  /// دمج آخر مكالمة لكل محادثة من سجل /calls
  void _mergeCalls(List<Conversation> convs) {
    try {
      final me = ApiService.userId.toString();
      ApiService.get('/calls', query: {'limit': '100'}).then((res) {
        if (!mounted || res['success'] != true) return;
        final data = res['data'];
        if (data is! List) return;
        final calls = <String, Map<String, dynamic>>{};
        for (final raw in data) {
          if (raw is! Map) continue;
          final c = Map<String, dynamic>.from(raw);
          final callerId = c['caller_id']?.toString() ?? '';
          final calleeId = c['callee_id']?.toString() ?? '';
          if (callerId.isEmpty || calleeId.isEmpty) continue;
          final other = callerId == me ? calleeId : callerId;
          final existing = calls[other];
          final createdAt =
              (c['created_at'] ?? c['started_at'] ?? '').toString();
          final existingAt = (existing?['created_at'] ?? '').toString();
          if (existing == null || createdAt.compareTo(existingAt) > 0) {
            calls[other] = {
              ...c,
              'direction': callerId == me ? 'out' : 'in',
            };
          }
        }
        if (!mounted) return;
        setState(() {
          _conversations = convs
              .map((cv) {
                final lc = calls[cv.otherUserId.toString()];
                if (lc == null) return cv;
                final Map<String, dynamic> j = {
                  'id': cv.id,
                  'uuid': cv.uuid,
                  'name': cv.name,
                  'avatar': cv.avatar,
                  'last_message': cv.lastMessage,
                  'unread_count': cv.unreadCount,
                      'members': cv.members
                      .map((m) => <String, dynamic>{
                            'id': m.id,
                            'uuid': m.uuid,
                            'name': m.name,
                            'phone': m.phone,
                            'avatar': m.avatar,
                            'is_online': m.isOnline ? 1 : 0,
                            'is_verified': m.isVerified ? 1 : 0,
                            'last_seen': m.isOnline
                                ? DateTime.now().toIso8601String()
                                : null,
                          })
                      .toList(),
                  'is_verified': cv.isVerified ? 1 : 0,
                  'last_call': lc,
                  'last_message_at': cv.lastMessageAt ?? '',
                  'type': cv.isGroup ? 'group' : 'private',
                };
                if (cv.phone.isNotEmpty) j['phone'] = cv.phone;
                if (cv.isGroup) {
                  j['is_group'] = true;
                  j['group_id'] = cv.groupId;
                  j['member_count'] = cv.memberCount;
                }
                return Conversation.fromJson(j);
              })
              .toList();
          _applyFilter();
        });
      });
    } catch (_) {}
  }

  /// إعادة الاتصال بنفس نوع آخر مكالمة
  Future<void> _reCall(Conversation conv) async {
    final lc = conv.lastCall;
    if (lc == null) return;
    final type = (lc['call_type'] ?? 'voice').toString();
    try {
      final res = await ApiService.post('/calls', body: {
        'callee_id': conv.otherUserId,
        'call_type': type,
      });
      if (!mounted) return;
      if (res['success'] == true && res['data'] is Map) {
        final call = Map<String, dynamic>.from(res['data'] as Map);
        call['is_outgoing'] = true;
        call['caller_id'] = ApiService.userId;
        await Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => CallScreen(callData: call)));
      } else {
        showToast(context, res['message'] ?? 'فشل بدء المكالمة');
      }
        } catch (_) {
      if (mounted) showToast(context, 'فشل بدء المكالمة');
    }
  }

  /// سطر آخر مكالمة في بطاقة المحادثة
  Widget _buildCallRow(Conversation conv) {
    final c = NovaColors.of(context);
    final lc = conv.lastCall!;
    final status = (lc['status'] ?? '').toString();
    final direction = (lc['direction'] ?? 'out').toString();
    final type = (lc['call_type'] ?? 'voice').toString();
    final isMissed = status == 'missed';
    final isRejected = status == 'rejected';
    final isActive = status == 'ringing' ||
        status == 'answered' ||
        status == 'accepted' ||
        status == 'calling';
    final color = isMissed
        ? const Color(0xFFE53935)
            : (isRejected
            ? c.muted
            : const Color(0xFF128C7E)); // سطر المكالمات
    IconData icon;
    String label;
    if (isMissed) {
      icon = direction == 'out' ? Icons.call_missed : Icons.call_missed_outlined;
      label = direction == 'out' ? 'مكالمة مفقودة' : 'مكالمة فائتة';
    } else if (isRejected) {
      icon = Icons.call_end;
      label = 'مرفوضة';
    } else if (isActive) {
      icon = Icons.phone_callback;
      label = 'جارية الآن';
    } else if (direction == 'out') {
      icon = Icons.call_made;
      label = 'مكالمة صادرة';
    } else {
      icon = Icons.call_received;
      label = 'مكالمة واردة';
    }
    final time = _formatCallTime(lc['started_at']?.toString() ??
        lc['created_at']?.toString() ??
        '');
    final dur = lc['duration'];
    final durationTxt = dur != null &&
            int.tryParse(dur.toString()) != null &&
            int.parse(dur.toString()) > 0
        ? ' · ${_formatDuration(int.parse(dur.toString()))}'
        : '';
    final callTypeIcon = type == 'video' ? Icons.videocam : Icons.call;
    return GestureDetector(
      onTap: () => _reCall(conv),
      child: Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Row(
          children: [
            Icon(callTypeIcon, size: 11, color: c.muted),
            const SizedBox(width: 4),
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 5),
            Expanded(
              child: Text(
                '$label$durationTxt',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 11, color: color),
              ),
            ),
            const SizedBox(width: 4),
            Text(time,
                style: TextStyle(fontSize: 10, color: c.muted.withOpacity(0.85))),
          ],
        ),
      ),
    );
  }

  static String _formatCallTime(String at) {
    if (at.isEmpty) return '';
    try {
      final d = DateTime.parse(at);
      final now = DateTime.now();
      final diff = now.difference(d);
      if (diff.inMinutes < 1) return 'الآن';
      if (diff.inHours < 1) return 'منذ ${diff.inMinutes} د';
      if (diff.inDays < 1) return 'منذ ${diff.inHours} س';
      if (diff.inDays < 7) return 'منذ ${diff.inDays} يوم';
      return '${d.month}/${d.day}/${d.year.toString().substring(2)}';
    } catch (_) {
      return '';
    }
  }

  static String _formatDuration(int ms) {
    final sec = (ms / 1000).floor();
    final m = sec ~/ 60;
    final s = sec % 60;
    return m > 0 ? '${m}د${s.toString().padLeft(2, '0')}ث' : '$sث';
  }

  Future<void> _openAutoChat() async {
    final phone = _autoChat;
    if (phone == null || !mounted) return;
    _autoChat = null;
    setNovaChats('auto_chat=$phone');
    Conversation? conv = _conversations
        .cast<Conversation?>()
        .firstWhere((c) => c != null && c.phone == phone, orElse: () => null);
    if (conv == null) {
      final searchRes =
          await ApiService.get('/users/search', query: {'q': phone});
      if (mounted &&
          searchRes['success'] == true &&
          searchRes['data'] is List &&
          (searchRes['data'] as List).isNotEmpty) {
        final target = Map<String, dynamic>.from(
            searchRes['data'][0] as Map<String, dynamic>);
        final targetId = target['id'] ?? target['user_id'];
        if (targetId != null) {
          final r = await ApiService.post('/conversations',
              body: {'user_id': targetId});
          if (mounted && r['success'] == true && r['data'] != null) {
            conv = Conversation.fromJson(
                Map<String, dynamic>.from(r['data'] as Map<String, dynamic>));
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
    _filtered = q.isEmpty
        ? List<Conversation>.of(_conversations)
        : _conversations
            .where(
                (c) => c.name.toLowerCase().contains(q) || c.phone.contains(q))
            .toList();
  }

  Future<void> _openChatWithUser(Map<String, dynamic> user) async {
    final targetId = user['id'] ?? user['user_id'];
    if (targetId == null) return;
    final r =
        await ApiService.post('/conversations', body: {'user_id': targetId});
    if (!mounted) return;
    if (r['success'] == true && r['data'] != null) {
      final conv = Conversation.fromJson(
          Map<String, dynamic>.from(r['data'] as Map<String, dynamic>));
      pushScreen(context, ChatScreen(conv: conv));
    } else {
      showToast(context, r['message'] ?? 'فشل في بدء المحادثة');
    }
  }

  // إنشاء مجموعة جديدة مع اختيار الأعضاء
  Future<void> _showCreateGroupDialog() async {
    final auth = context.read<AuthProvider>();
    if (!auth.effectiveAppSettings.allowGroups) {
      showToast(context, 'إنشاء المجموعات موقوف من الإدارة');
      return;
    }
    final c = NovaColors.of(context);
    final titleCtrl = TextEditingController();
    final searchCtrl = TextEditingController();
    final Set<int> selected = {};
    await showDialog<void>(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (dialogCtx, setState) {
          return AlertDialog(
            shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(22)),
            title: const Text('مجموعة جديدة'),
            content: SizedBox(
              width: 360,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: titleCtrl,
                    decoration: InputDecoration(
                      hintText: 'اسم المجموعة',
                      filled: true,
                      fillColor: c.surface2,
                      border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: searchCtrl,
                    onChanged: (_) => setState(() {}),
                    decoration: InputDecoration(
                      hintText: 'ابحث عن أعضاء...',
                      prefixIcon:
                          Icon(Icons.search, size: 19, color: c.muted),
                      filled: true,
                      fillColor: c.surface2,
                      border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14)),
                    ),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    height: 240,
                    child: FutureBuilder(
                      future: searchCtrl.text.length >= 3
                          ? ApiService.get('/users/search',
                              query: {'q': searchCtrl.text})
                          : Future.value({'success': true, 'data': []}),
                      builder: (context, snap) {
                        if (snap.connectionState != ConnectionState.done) {
                          return const Center(
                              child: CircularProgressIndicator());
                        }
                        if (snap.data?['success'] != true ||
                            snap.data?['data'] is! List) {
                          return const Center(child: Text('لا نتائج'));
                        }
                        final users = (snap.data!['data'] as List)
                            .map((e) =>
                                Map<String, dynamic>.from(e))
                            .toList();
                        if (users.isEmpty && searchCtrl.text.length >= 3) {
                          return const Center(child: Text('لا مستخدمين'));
                        }
                        if (users.isEmpty) {
                          return const Center(child: Text('ابحث عن أعضاء لإضافتهم'));
                        }
                        return ListView.builder(
                          itemCount: users.length,
                          itemBuilder: (_, i) {
                            final u = users[i];
                            final uid = int.parse(u['id'].toString());
                            final name = u['display_name'] ??
                                u['name'] ??
                                u['phone'] ??
                                '-';
                            final on = selected.contains(uid);
                            return CheckboxListTile(
                              value: on,
                              activeColor: c.accent,
                              dense: true,
                              title: Row(
                                children: [
                                  Flexible(child: Text(name, style: TextStyle(color: c.text))),
                                  if (u['is_verified'] == true || (u['is_verified'] ?? 0) == 1) ...[
                                    const SizedBox(width: 4),
                                    const Icon(Icons.verified, color: Colors.blue, size: 14),
                                  ],
                                ],
                              ),
                              subtitle: u['phone'] != null
                                  ? Text(u['phone'].toString(),
                                      style: TextStyle(
                                          fontSize: 11, color: c.muted))
                                  : null,
                              onChanged: (v) => setState(() {
                                if (v == true) {
                                  selected.add(uid);
                                } else {
                                  selected.remove(uid);
                                }
                              }),
                            );
                          },
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text('تم اختيار ${selected.length} عضو',
                      style: TextStyle(fontSize: 12, color: c.muted)),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogCtx),
                child: Text('إلغاء', style: TextStyle(color: c.muted)),
              ),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: c.accent,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: (titleCtrl.text.trim().isEmpty ||
                        selected.isEmpty)
                    ? null
                    : () async {
                        final res = await ApiService.post('/conversations',
                            body: {
                              'type': 'group',
                              'title': titleCtrl.text.trim(),
                              'members': selected.toList(),
                            });
                        if (!dialogCtx.mounted) return;
                        Navigator.pop(dialogCtx);
                        if (res['success'] == true &&
                            res['data'] != null) {
                          final conv = Conversation.fromJson(
                              Map<String, dynamic>.from(
                                  res['data'] as Map<String, dynamic>));
                          if (mounted) {
                            pushScreen(context, ChatScreen(conv: conv));
                            _load();
                          }
                        } else {
                          if (mounted) {
                            showToast(context,
                                res['message'] ?? 'فشل إنشاء المجموعة');
                          }
                        }
                      },
                child: const Text('إنشاء المجموعة'),
              ),
            ],
          );
        },
      ),
    );
  }

  // زر جهات الاتصال الجديدة في الشاشة الرئيسية
  Future<void> _showAddNewContactDialog() async {
    final c = NovaColors.of(context);
    final res = await ApiService.get('/contacts/new');
    if (!mounted) return;
    List<Map<String, dynamic>> newContacts = [];
    if (res['success'] == true && res['data'] is List) {
      newContacts = (res['data'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map<String, dynamic>))
          .toList();
    }
    if (!mounted) return;
    final phoneCtrl = TextEditingController();
    await showDialog(
      context: context,
      builder: (dialogCtx) => StatefulBuilder(
        builder: (dialogCtx, setState) {
          return AlertDialog(
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
            title: const Text('جهات الاتصال الجديدة'),
            content: SizedBox(
              width: 340,
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      decoration: BoxDecoration(
                        color: c.accent.withOpacity(0.12),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: TextField(
                        controller: phoneCtrl,
                        keyboardType: TextInputType.text,
                        decoration: const InputDecoration(
                          hintText: 'رقم، أو بريد',
                          labelText: 'بحث عن مستخدم',
                          border: InputBorder.none,
                          contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(children: [
                      Expanded(
                        child: ElevatedButton(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: c.accent,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          onPressed: () async {
                            final query = phoneCtrl.text.trim();
                            if (query.length < 3) {
                              showToast(dialogCtx, 'أدخل 3 أحرف على الأقل للبحث');
                              return;
                            }
                            Map<String, dynamic> searchRes;
                            try {
                              searchRes = await ApiService.get('/users/search', query: {'q': query});
                            } catch (e) {
                              if (!dialogCtx.mounted) return;
                              showToast(dialogCtx, 'فشل الاتصال بالخادم — حاول مجددًا');
                              return;
                            }
                            if (!dialogCtx.mounted) return;
                            if (searchRes['success'] != true) {
                              final msg = searchRes['message']?.toString() ?? '';
                              showToast(dialogCtx, msg.isNotEmpty ? msg : 'لم يتم العثور على مستخدم مطابق لبحثك');
                              return;
                            }
                            if (searchRes['data'] is List && (searchRes['data'] as List).isNotEmpty) {
                              final user = Map<String, dynamic>.from(searchRes['data'][0] as Map<String, dynamic>);
                              Map<String, dynamic> addRes;
                              try {
                                addRes = await ApiService.post('/contacts', body: {'contact_user_id': user['id']});
                              } catch (e) {
                                if (!dialogCtx.mounted) return;
                                showToast(dialogCtx, 'فشل الاتصال بالخادم — حاول مجددًا');
                                return;
                              }
                              if (!dialogCtx.mounted) return;
                              if (addRes['success'] == true) {
                                showToast(dialogCtx, addRes['message'] ?? 'تمت إضافة جهة الاتصال');
                                Navigator.pop(dialogCtx);
                              } else {
                                showToast(dialogCtx, addRes['message'] ?? 'فشل الإضافة');
                              }
                            } else {
                              showToast(dialogCtx, 'لم يتم العثور على مستخدم');
                            }
                          },
                          child: const Text('بحث وإضافة'),
                        ),
                      ),
                    ]),
                    const SizedBox(height: 14),
                    if (newContacts.isNotEmpty)
                      Text('المضافون حديثًا (${newContacts.length})',
                          style: TextStyle(fontSize: 13, color: c.muted, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 6),
                    SizedBox(
                      height: (newContacts.length * 58.0).clamp(0, 232),
                      child: ListView.builder(
                        shrinkWrap: true,
                        itemCount: newContacts.length,
                        itemBuilder: (d, i) {
                          final u = newContacts[i];
                          final name = u['name'] ?? u['phone'] ?? '-';
                          final online = u['is_online'] == true || u['is_online'] == 1 || u['is_online'] == '1';
                          return ListTile(
                            dense: true,
                            leading: NovaAvatar(
                                letter: name.toString().isEmpty ? '?' : name.toString()[0],
                                size: 40,
                                radius: 14,
                                imageUrl: u['avatar'],
                                online: online),
                            title: Text(name.toString(), style: const TextStyle(fontSize: 14)),
                            subtitle: Text(
                                formatLastSeen(u['last_seen']?.toString(),
                                    isOnline: online,
                                    utcOffsetMinutes: context.read<AuthProvider>().timezoneOffsetMinutes),
                                style: TextStyle(fontSize: 11.5, color: online ? c.accent : c.muted)),
                            onTap: () async {
                              Navigator.pop(d);
                              final convRes = await ApiService.post('/conversations', body: {'user_id': u['id']});
                              if (!this.mounted) return;
                              if (convRes['success'] == true && convRes['data'] != null) {
                                pushScreen(
                                    this.context,
                                    ChatScreen(
                                      conv: Conversation.fromJson(Map<String, dynamic>.from(convRes['data'] as Map<String, dynamic>)),
                                    ));
                              } else {
                                showToast(this.context, convRes['message'] ?? 'فشل بدء المحادثة');
                              }
                            },
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogCtx),
                child: const Text('إغلاق'),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _searchAndStart() async {
    final ctrl = TextEditingController();
    final q = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('بدء محادثة جديدة'),
        content: SingleChildScrollView(
          child: TextField(
            controller: ctrl,
            decoration: const InputDecoration(
                hintText: 'رقم، أو بريد', labelText: 'بحث'),
          ),
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
    if (res['success'] == true &&
        res['data'] is List &&
        (res['data'] as List).isNotEmpty) {
      final user =
          Map<String, dynamic>.from(res['data'][0] as Map<String, dynamic>);
      if (!mounted) return;
      showToast(
          context, 'جهة الاتصال: ${user['name'] ?? '-'}');
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
              decoration: BoxDecoration(
                  border: Border(bottom: BorderSide(color: c.line))),
              child: Consumer<AuthProvider>(
                builder: (context, auth, _) {
                  final user = auth.user;
                  final displayName = user?.displayName ?? 'مساحتك';
                  return Row(
                    children: [
                      const NovaLogo(size: 40, radius: 14),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'مساحة نوفا',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                                color: c.text,
                              ),
                            ),
                            Text(
                              displayName,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(fontSize: 11, color: c.muted),
                            ),
                          ],
                        ),
                      ),
                      if (auth.effectiveAppSettings.allowGroups) ...[
                        IconBtn(icon: Icons.groups_2, onTap: () {
                          pushScreen(context, const GroupsScreen());
                        }),
                        IconBtn(
                          icon: Icons.group_add,
                          onTap: _showCreateGroupDialog,
                          color: c.accent,
                        ),
                      ],
                      IconBtn(
                        icon: Icons.person_add_outlined,
                        onTap: _showAddNewContactDialog,
                        color: c.accent,
                      ),
                      IconBtn(icon: Icons.search, onTap: _searchAndStart),
                      IconBtn(
                        icon: Icons.add_circle_outline,
                        onTap: _searchAndStart,
                        color: c.accent,
                      ),
                      PressScale(
                        onTap: () => pushScreen(context, const ProfileScreen()),
                        child: NovaAvatar(
                          letter: displayName,
                          imageUrl: user?.avatar,
                          size: 34,
                          radius: 12,
                          online: user?.isOnline ?? false,
                        ),
                      ),
                    ],
                  );
                },
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
                      setState(() {
                        _search = v;
                        _applyFilter();
                      });
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
                          final letter =
                              conv.name.isNotEmpty ? conv.name[0] : '?';
                          return PressScale(
                            onTap: () =>
                                pushScreen(context, ChatScreen(conv: conv)),
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: NovaCard(
                                onTap: () =>
                                    pushScreen(context, ChatScreen(conv: conv)),
                                padding: const EdgeInsets.all(12),
                                child: Row(
                                  children: [
                                    NovaAvatar(
                                        letter: letter,
                                        size: 54,
                                        radius: 18,
                                        imageUrl: conv.avatar,
                                        online: conv.isOnline),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Row(
                                            children: [
                                              Expanded(
                                                child: Text(
                                                    conv.name.isNotEmpty
                                                        ? conv.name
                                                        : conv.phone,
                                                    overflow:
                                                        TextOverflow.ellipsis,
                                                    style: TextStyle(
                                                        fontSize: 15.5,
                                                        fontWeight:
                                                            FontWeight.w800,
                                                        color: c.text)),
                                              ),
                                              if (conv.isGroup)
                                                Container(
                                                  padding: const EdgeInsets.symmetric(
                                                      horizontal: 7, vertical: 3),
                                                  decoration: BoxDecoration(
                                                    color: c.accent.withValues(alpha: 0.12),
                                                    borderRadius: BorderRadius.circular(8),
                                                  ),
                                                  child: Row(
                                                    mainAxisSize: MainAxisSize.min,
                                                    children: [
                                                      Icon(Icons.groups_2,
                                                          size: 11,
                                                          color: c.accent),
                                                      const SizedBox(width: 3),
                                                      Text('مجموعة',
                                                          style: TextStyle(
                                                              fontSize: 10,
                                                              fontWeight:
                                                                  FontWeight.w800,
                                                              color: c.accent)),
                                                    ],
                                                  ),
                                                ),
                                              if (conv.isVerified) ...[
                                                const SizedBox(width: 4),
                                                const Icon(Icons.verified,
                                                    color: Colors.blue,
                                                    size: 16),
                                              ],
                                            ],
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            conv.lastMessage != null &&
                                                    conv.lastMessage!.isNotEmpty
                                                ? (conv.lastMessageType ==
                                                            'image'
                                                        ? 'صورة  '
                                                        : conv.lastMessageType ==
                                                                'audio'
                                                            ? 'رسالة صوتية  '
                                                            : conv.lastMessageType ==
                                                                    'video'
                                                                ? 'فيديو  '
                                                                : conv.lastMessageType ==
                                                                        'document'
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
                                            children: conv.typingUsers.isNotEmpty
                                                ? [
                                                    Icon(Icons.circle,
                                                        size: 8,
                                                        color: c.accent),
                                                    const SizedBox(width: 5),
                                                    Text(
                                                      conv.typingUsers.length > 1
                                                          ? 'يكتبون الآن...'
                                                          : 'يكتب الآن...',
                                                      style: TextStyle(
                                                          fontSize: 11,
                                                          color: c.accent,
                                                          fontWeight:
                                                              FontWeight.w600),
                                                    ),
                                                  ]
                                                : [
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
                                                              isOnline: conv.isOnline,
                                                              utcOffsetMinutes:
                                                                  context.read<AuthProvider>().timezoneOffsetMinutes),
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
                                    if (conv.isGroup)
                                      const SizedBox.shrink()
                                    else if (conv.lastCall != null)
                                      _buildCallRow(conv),
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
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w800)),
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
              boxShadow: [
                BoxShadow(color: color.withOpacity(0.5), blurRadius: 20)
              ],
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
  Timer? _debounce;
  Timer? _contactsTimer;
  List<Map<String, dynamic>> _users = [];
  List<Map<String, dynamic>> _newContacts = [];
  bool _loading = false;
  bool _contactLoading = false;
  bool _searched = false;
  bool _contactsLoaded = false;
  bool _asBool(dynamic value) =>
      value == true || value == 1 || value == '1' || value == 'true';

  Future<void> _loadNewContacts() async {
    try {
      final res = await ApiService.get('/contacts/new');
      if (!mounted) return;
      if (res['success'] == true && res['data'] is List) {
        setState(() {
          _newContacts = (res['data'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map<String, dynamic>))
              .toList();
          _contactsLoaded = true;
        });
      }
    } catch (_) {}
  }

  Future<void> _addToContacts(Map<String, dynamic> user) async {
    final targetId = user['id'];
    if (targetId == null) return;
    final r = await ApiService.post('/contacts',
        body: {'contact_user_id': targetId});
    if (!mounted) return;
    if (r['success'] == true) {
      showToast(context, r['message'] ?? 'تمت إضافة جهة الاتصال');
      _loadNewContacts();
    } else {
      showToast(context, r['message'] ?? 'فشل في إضافة جهة الاتصال');
    }
  }

  Future<void> _removeFromContacts(int contactId) async {
    final r = await ApiService.delete('/contacts/$contactId');
    if (!mounted) return;
    if (r['success'] == true) {
      showToast(context, 'تمت إزالة جهة الاتصال');
      _loadNewContacts();
    } else {
      showToast(context, r['message'] ?? 'فشل في إزالة جهة الاتصال');
    }
  }

  Future<void> _syncPhoneContacts() async {
    if (kIsWeb) {
      showToast(context, 'مزامنة جهات اتصال الهاتف متاحة من تطبيق Android');
      return;
    }
    setState(() => _contactLoading = true);
    try {
      final granted = await FlutterContacts.requestPermission(readonly: true);
      if (!granted) {
        if (mounted)
          showToast(context, 'اسمح للتطبيق بالوصول إلى جهات الاتصال');
        return;
      }
      final contacts = await FlutterContacts.getContacts(withProperties: true);
      final phones = <String>{};
      for (final contact in contacts) {
        for (final phone in contact.phones) {
          final raw = phone.number.trim();
          final normalized = raw.replaceAll(RegExp(r'[^0-9+]'), '');
          if (normalized.length >= 7) phones.add(normalized);
        }
      }
      final found = <String, Map<String, dynamic>>{};
      for (final phone in phones.take(80)) {
        try {
          final res =
              await ApiService.get('/users/search', query: {'q': phone});
          if (res['success'] == true && res['data'] is List) {
            for (final item in res['data']) {
              if (item is Map && item['id'] != null) {
                found[item['id'].toString()] =
                    Map<String, dynamic>.from(item);
              }
            }
          }
        } catch (_) {}
      }
      if (!mounted) return;
      setState(() {
        _users = found.values.toList();
        _searched = true;
      });
      showToast(
          context,
          _users.isEmpty
              ? 'لا يوجد مستخدمون من جهات اتصالك'
              : 'تمت مزامنة ${_users.length} جهة');
    } catch (_) {
      if (mounted) showToast(context, 'تعذر قراءة جهات الاتصال');
    } finally {
      if (mounted) setState(() => _contactLoading = false);
    }
  }

  Future<void> _search(String q) async {
    if (q.trim().length < 2) return;
    setState(() {
      _loading = true;
    });
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

  Future<void> _findAndAdd(String phone) async {
    if (phone.trim().isEmpty) return;
    setState(() => _loading = true);
    try {
      final res =
          await ApiService.get('/users/search', query: {'q': phone.trim()});
      if (!mounted) return;
      if (res['success'] == true &&
          res['data'] is List &&
          (res['data'] as List).isNotEmpty) {
        final user = Map<String, dynamic>.from(
            res['data'][0] as Map<String, dynamic>);
        await _addToContacts(user);
      } else {
        showToast(context, 'لم يتم العثور على مستخدم بهذا الرقم');
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  Future<void> _openChatWith(Map<String, dynamic> user) async {
    // الأولوية لـ contact_user_id لأنه المعرف الحقيقي للمستخدم في قائمة جهات الاتصال
    // أما 'id' في استجابة /contacts/new فهو معرف السجل في جدول contacts
    final targetId = user['contact_user_id'] ?? user['user_id'] ?? user['id'];
    if (targetId == null) return;
    final r =
        await ApiService.post('/conversations', body: {'user_id': targetId});
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
  void initState() {
    super.initState();
    _loadNewContacts();
    // تحديث دوري لجهات الاتصال الجديدة (آخر ظهور / متصل)
    _contactsTimer = Timer.periodic(const Duration(seconds: 8), (_) {
      if (mounted && ApiService.token != null) _loadNewContacts();
    });
  }

  Future<void> _showAddContactDialog() async {
    final ctrl = TextEditingController();
        final entered = await showDialog<String>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('إضافة جهة اتصال جديدة'),
        content: SingleChildScrollView(
          child: TextField(
            controller: ctrl,
            decoration: const InputDecoration(
                hintText: 'رقم، أو بريد', labelText: 'بحث'),
          ),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, null),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, ctrl.text.trim()),
              child: const Text('بحث وإضافة')),
        ],
      ),
    );
    if (entered == null) return;
    await _findAndAdd(entered);
  }

  @override
  void dispose() {
    _contactsTimer?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  Widget _contactTile({
    required Map<String, dynamic> user,
    required bool online,
    required bool showAdd,
    int? contactId,
    String? nickname,
  }) {
    final c = NovaColors.of(context);
    final name = user['display_name'] ?? user['name'] ?? user['username'] ?? user['phone'] ?? '-';
    final letter = name.toString().isNotEmpty ? name.toString()[0] : '?';
    final displayName = (nickname != null && nickname.isNotEmpty)
        ? '$nickname — $name'
        : name.toString();
    return PressScale(
      onTap: () => _openChatWith(user),
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
                  imageUrl: user['avatar'],
                  online: online),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Flexible(
                        child: Text(displayName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                                fontSize: 15.5,
                                fontWeight: FontWeight.w800,
                                color: c.text)),
                      ),
                      if (user['is_verified'] == true || (user['is_verified'] ?? 0) == 1) ...[
                        const SizedBox(width: 4),
                        const Icon(Icons.verified,
                            color: Colors.blue, size: 16),
                      ],
                    ]),
                    const SizedBox(height: 3),
                    Text(
                      formatLastSeen(user['last_seen']?.toString(),
                          isOnline: online,
                          utcOffsetMinutes: context.read<AuthProvider>().timezoneOffsetMinutes),
                      style: TextStyle(
                          fontSize: 12.5,
                          color: online ? c.accent : c.muted),
                    ),
                  ],
                ),
              ),
              if (showAdd)
                IconBtn(
                  icon: Icons.person_add,
                  color: c.accent,
                  onTap: () => _addToContacts(user),
                )
              else if (contactId != null)
                IconBtn(
                  icon: Icons.delete_outline,
                  color: c.muted,
                  onTap: () => _removeFromContacts(contactId),
                )
              else
                IconBtn(
                  icon: Icons.chat_bubble_outline,
                  color: c.accent,
                  onTap: () => _openChatWith(user),
                ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Column(
      children: [
        novaTopBar(context, title: 'جهات الاتصال', actions: [
          IconBtn(
              icon: Icons.person_add_outlined,
              color: c.accent,
              onTap: _showAddContactDialog),
          IconBtn(
            icon: _contactLoading ? Icons.sync : Icons.contacts_outlined,
            color: c.text,
            onTap: _syncPhoneContacts,
          )
        ]),
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
                      if (_debounce?.isActive ?? false) _debounce!.cancel();
                      _debounce = Timer(const Duration(milliseconds: 500), () {
                        if (v.trim().length >= 2) _search(v);
                      });
                    },
                    onSubmitted: _search,
                    decoration: InputDecoration(
                      hintText: 'ابحث باسم، رقم، أو بريد',
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
                  : _users.isNotEmpty
                      ? RefreshIndicator(
                          onRefresh: () async => _search(_searchCtrl.text),
                          child: ListView.builder(
                            padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
                            itemCount: _users.length,
                            itemBuilder: (_, i) {
                              final u = _users[i];
                              return _contactTile(
                                user: u,
                                online: _asBool(u['is_online']),
                                showAdd: true,
                              );
                            },
                          ),
                        )
                      : !_searched && _contactsLoaded
                          ? RefreshIndicator(
                              onRefresh: _loadNewContacts,
                              child: ListView.builder(
                                padding:
                                    const EdgeInsets.fromLTRB(16, 4, 16, 100),
                                itemCount: _newContacts.length,
                                itemBuilder: (_, i) {
                                  final u = _newContacts[i];
                                  return _contactTile(
                                    user: u,
                                    online: _asBool(u['is_online']),
                                    showAdd: false,
                                    contactId: u['id'],
                                    nickname: u['nickname'],
                                  );
                                },
                              ),
                            )
                          : RefreshIndicator(
                              onRefresh: () async {
                                await _loadNewContacts();
                              },
                              child: ListView(
                                padding: const EdgeInsets.all(16),
                                children: [
                                  Center(
                                    child: Column(
                                      mainAxisAlignment:
                                          MainAxisAlignment.center,
                                      children: [
                                        const SizedBox(height: 24),
                                        Icon(Icons.people_outline,
                                            size: 72,
                                            color:
                                                c.muted.withOpacity(0.45)),
                                        const SizedBox(height: 12),
                                        Text(
                                          !_searched && !_contactsLoaded
                                              ? 'جارٍ تحميل جهات الاتصال...'
                                              : 'لا توجد جهات اتصال جديدة',
                                          style: TextStyle(
                                              fontSize: 16, color: c.muted),
                                        ),
                                        const SizedBox(height: 8),
                                        Text(
                                          'أضف جهة اتصال جديدة بالضغط على زر الإضافة في الأعلى',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                              fontSize: 13, color: c.muted),
                                        ),
                                        const SizedBox(height: 16),
                                        Container(
                                          decoration: BoxDecoration(
                                            color: c.accent.withOpacity(0.12),
                                            borderRadius:
                                                BorderRadius.circular(16),
                                          ),
                                          child: ListTile(
                                            leading: Icon(Icons.person_add,
                                                color: c.accent),
                                            title: Text('إضافة جهة اتصال جديدة',
                                                style: TextStyle(
                                                    color: c.accent,
                                                    fontWeight:
                                                        FontWeight.w700)),
                                            subtitle: Text(
                                                'ابحث برقم الهاتف لإضافة مستخدم',
                                                style:
                                                    TextStyle(color: c.muted)),
                                            onTap: _showAddContactDialog,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
        ),
      ],
    );
  }
}
