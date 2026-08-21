class NovaUser {
  final int id;
  final String uuid;
  final String phone;
  final String? email;
  final String? name;
  final String? username;
  final String? bio;
  final String? avatar;
  final bool isOnline;
  final bool isVerified;
  final bool isBlocked;
  final Map<String, dynamic>? plan;
  final int activeDevicesCount;
  final int maxDevicesAllowed;

  NovaUser({
    required this.id,
    required this.uuid,
    required this.phone,
    this.email,
    this.name,
    this.username,
    this.bio,
    this.avatar,
    this.isOnline = false,
    this.isVerified = false,
    this.isBlocked = false,
    this.plan,
    this.activeDevicesCount = 0,
    this.maxDevicesAllowed = 1,
  });

  factory NovaUser.fromJson(Map<String, dynamic> j) => NovaUser(
        id: j['id'] is int ? j['id'] : int.parse(j['id'].toString()),
        uuid: j['uuid'] ?? '',
        phone: j['phone'] ?? '',
        email: j['email'],
        name: j['name'],
        username: j['username'],
        bio: j['bio'],
        avatar: j['avatar'],
        isOnline: (j['is_online'] ?? 0) == 1,
        isVerified: (j['is_verified'] ?? 0) == 1,
        isBlocked: (j['is_blocked'] ?? 0) == 1,
        plan: j['plan'] is Map ? Map<String, dynamic>.from(j['plan']) : null,
        activeDevicesCount: int.parse((j['active_devices_count'] ?? 0).toString()),
        maxDevicesAllowed: int.parse((j['max_devices_allowed'] ?? 1).toString()),
      );

  String? get planName => plan != null ? (plan!['name']?.toString()) : null;
  int? get planMaxDevices => plan != null ? int.tryParse((plan!['max_devices'] ?? 1).toString()) : null;

  String get displayName => name ?? username ?? phone;
}

class Conversation {
  final int id;
  final String uuid;
  final String name;
  final String? avatar;
  final String? lastMessage;
  final String? lastMessageAt;
  final int unreadCount;
  final List<NovaUser> members;
  final bool isVerified;
  final String phone;
  final int otherUserId;
  final bool isOnline;
  final String? lastSeen;
  final String? lastMessageType;
  final int? disappearAfter;
  final bool isGroup;
  final int? groupId;
  final int memberCount;
  final Map<String, dynamic>? lastCall;
  final List<Map<String, dynamic>> typingUsers;

  Conversation({
    required this.id,
    required this.uuid,
    required this.name,
    this.avatar,
    this.lastMessage,
    this.lastMessageAt,
    this.unreadCount = 0,
    required this.members,
    this.isVerified = false,
    this.phone = '',
    this.otherUserId = 0,
    this.isOnline = false,
    this.lastSeen,
    this.lastMessageType,
    this.disappearAfter,
    this.isGroup = false,
    this.groupId,
    this.memberCount = 0,
    this.lastCall,
    this.typingUsers = const [],
  });

  factory Conversation.fromJson(Map<String, dynamic> j) => Conversation(
        id: j['id'] is int ? j['id'] : int.parse(j['id'].toString()),
        uuid: j['uuid'] ?? '',
        name: j['name'] ?? j['title'] ?? 'محادثة',
        avatar: j['avatar'],
        lastMessage: j['last_message'] ?? j['last_message_body'],
        lastMessageType: j['type'] == 'private' ? (j['last_message_type'] ?? 'text') : 'text',
        lastMessageAt: j['last_message_at'] ?? j['updated_at'],
        unreadCount: j['unread_count'] is int
            ? j['unread_count']
            : int.parse((j['unread_count'] ?? 0).toString()),
        members: (j['members'] is List)
            ? (j['members'] as List)
                .map((e) => NovaUser.fromJson(Map<String, dynamic>.from(e)))
                .toList()
            : [],
        isVerified: _otherIsVerified(j),
        phone: _memberPhone(j),
        otherUserId: _otherUserId(j),
        isOnline: _otherIsOnline(j),
        lastSeen: _otherLastSeen(j),
        disappearAfter: j['disappear_after'] is int ? j['disappear_after'] as int : int.tryParse((j['disappear_after'] ?? 0).toString()),
        isGroup: j['is_group'] == true || j['type'] == 'group',
        groupId: j['group_id'] != null ? int.tryParse(j['group_id'].toString()) : null,
        memberCount: j['member_count'] != null ? int.parse(j['member_count'].toString()) : 0,
        lastCall: j['last_call'],
        typingUsers: (j['typing_users'] is List)
            ? (j['typing_users'] as List)
                .map((e) => Map<String, dynamic>.from(e as Map))
                .toList()
            : [],
      );

  static String _memberPhone(Map<String, dynamic> j) {
    try {
      final members = j['members'];
      if (members is List && members.isNotEmpty) {
        return NovaUser.fromJson(Map<String, dynamic>.from(members[0])).phone;
      }
    } catch (_) {}
    return '';
  }

  static bool _otherIsOnline(Map<String, dynamic> j) {
    try {
      final ou = j['other_user'];
      if (ou is Map) return (ou['is_online'] ?? 0) == 1;
    } catch (_) {}
    return false;
  }

  static String? _otherLastSeen(Map<String, dynamic> j) {
    try {
      final ou = j['other_user'];
      if (ou is Map) return ou['last_seen']?.toString();
    } catch (_) {}
    return null;
  }

  static int _otherUserId(Map<String, dynamic> j) {
    try {
      final ou = j['other_user'];
      if (ou is Map && ou['id'] != null) {
        return ou['id'] is int ? ou['id'] as int : int.parse(ou['id'].toString());
      }
    } catch (_) {}
    return 0;
  }

  static bool _otherIsVerified(Map<String, dynamic> j) {
    try {
      // 1. تحقق من الحقل المباشر (للمجموعات أو البحث)
      if ((j['is_verified'] ?? 0) == 1) return true;
      // 2. تحقق من المستخدم الآخر (للمحادثات الخاصة)
      final ou = j['other_user'];
      if (ou is Map) return (ou['is_verified'] ?? 0) == 1;
    } catch (_) {}
    return false;
  }
}

class NovaMessage {
  final int id;
  final String uuid;
  final int senderId;
  final String type;
  final String? body;
  final int? replyToMessageId;
  final String status;
  final String createdAt;
  final bool isEdited;
  final bool isDeleted;
  final bool isVerified;

  // Attachment fields
  final String? filePath;
  final String? thumbnailPath;
  final String? mimeType;
  final int? fileSize;
  final int? width;
  final int? height;
  final int? duration;

  NovaMessage({
    required this.id,
    required this.uuid,
    required this.senderId,
    required this.type,
    this.body,
    this.replyToMessageId,
    required this.status,
    required this.createdAt,
    this.isEdited = false,
    this.isDeleted = false,
    this.isVerified = false,
    this.filePath,
    this.thumbnailPath,
    this.mimeType,
    this.fileSize,
    this.width,
    this.height,
    this.duration,
  });

  factory NovaMessage.fromJson(Map<String, dynamic> j) => NovaMessage(
        id: j['id'] is int ? j['id'] : int.parse(j['id'].toString()),
        uuid: j['uuid'] ?? '',
        senderId: j['sender_id'] is int
            ? j['sender_id']
            : int.parse(j['sender_id'].toString()),
        type: j['type'] ?? 'text',
        body: j['body'],
        replyToMessageId: j['reply_to_message_id'] == null
            ? null
            : int.parse(j['reply_to_message_id'].toString()),
        status: j['status'] ?? 'sent',
        createdAt: j['created_at'] ?? '',
        isEdited: (j['is_edited'] ?? 0) == 1,
        isDeleted: j['deleted_at'] != null || j['status'] == 'deleted',
        isVerified: (j['is_verified'] ?? 0) == 1,
        filePath: j['file_path'],
        thumbnailPath: j['thumbnail_path'],
        mimeType: j['mime_type'],
        fileSize: j['file_size'] == null ? null : int.parse(j['file_size'].toString()),
        width: j['width'] == null ? null : int.parse(j['width'].toString()),
        height: j['height'] == null ? null : int.parse(j['height'].toString()),
        duration: j['duration'] == null ? null : int.parse(j['duration'].toString()),
      );

  bool get isMedia =>
      type == 'image' || type == 'video' || type == 'audio' || type == 'voice' || type == 'file';
}

/// تنسيق "آخر ظهور" بالعربية
/// تصريف الوحدة الزمنية بالعربية حسب العدد (مفرد / مثنى / جمع)
String _arabicCountedUnit(int count, String singular, String dual, String plural) {
  final word = count == 1 ? singular : (count == 2 ? dual : plural);
  return 'منذ $count $word';
}

/// تنسيق التاريخ بالعربية: «20 أغسطس، 10:35 م»
String _formatArabicDate(DateTime dt) {
  const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
  final hour24 = dt.hour;
  final period = hour24 >= 12 ? 'م' : 'ص';
  final hour12 = hour24 % 12 == 0 ? 12 : hour24 % 12;
  final time = '${hour12.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')} $period';
  return '${dt.day} ${months[dt.month - 1]}، $time';
}

String formatLastSeen(String? lastSeen, {bool isOnline = false, int? utcOffsetMinutes}) {
  if (isOnline) return 'متصل الآن';
  if (lastSeen == null || lastSeen.isEmpty) return 'آخر ظهور: مؤخراً';
  try {
    // التواريخ تُخزن UTC نصية — نحسب الفرق بالتوقيت المعتمد إذا توفرت الإزاحة
    DateTime dt;
    final normalized = lastSeen.contains('T') ? lastSeen : lastSeen.replaceFirst(' ', 'T');
    final parsed = DateTime.tryParse(normalized);
    if (parsed == null) return 'آخر ظهور: مؤخراً';
    dt = (utcOffsetMinutes != null) ? parsed.toUtc().add(Duration(minutes: utcOffsetMinutes)) : parsed;
    final diff = DateTime.now().difference(dt.toUtc());
    String label;
    if (diff.inSeconds < 60) {
      label = 'منذ لحظات';
    } else if (diff.inMinutes < 60) {
      label = _arabicCountedUnit(diff.inMinutes, 'دقيقة', 'دقيقتين', 'دقائق');
    } else if (diff.inHours < 24) {
      label = _arabicCountedUnit(diff.inHours, 'ساعة', 'ساعتين', 'ساعات');
    } else if (diff.inDays < 7) {
      label = _arabicCountedUnit(diff.inDays, 'يوم', 'يومين', 'أيام');
    } else {
      label = _formatArabicDate(dt);
    }
    return 'آخر ظهور: $label';
  } catch (_) {
    return 'آخر ظهور: مؤخراً';
  }
}
