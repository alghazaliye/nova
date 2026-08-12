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
      );

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
  });

  factory Conversation.fromJson(Map<String, dynamic> j) => Conversation(
        id: j['id'] is int ? j['id'] : int.parse(j['id'].toString()),
        uuid: j['uuid'] ?? '',
        name: j['name'] ?? j['title'] ?? 'محادثة',
        avatar: j['avatar'],
        lastMessage: j['last_message'],
        lastMessageAt: j['updated_at'],
        unreadCount: j['unread_count'] is int
            ? j['unread_count']
            : int.parse((j['unread_count'] ?? 0).toString()),
        members: (j['members'] is List)
            ? (j['members'] as List)
                .map((e) => NovaUser.fromJson(Map<String, dynamic>.from(e)))
                .toList()
            : [],
        isVerified: (j['is_verified'] ?? 0) == 1,
        phone: _memberPhone(j),
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
