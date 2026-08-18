// LocalSyncService — writes server data into the local offline database.
// Used after REST sync pulls or WebSocket events. Never deletes locally-made
// messages that are still pending sync (conflict protection).
import 'package:drift/drift.dart' as drift;
import 'local_nova_db.dart';
import 'local_nova_db_provider.dart';

class LocalSyncService {
  /// Store a locally-sent message that is still pending server confirmation.
  /// Returns the local row (for outbox reference).
  static Future<LocalMessage> storePendingMessage({
    required int conversationId,
    required String localUuid,
    required int senderId,
    required String type,
    String? body,
    int? replyToServerId,
    String? filePath,
    String? mimeType,
    int? fileSize,
    int? duration,
  }) async {
    final db = await LocalNovaDbProvider.db;
    final nowIso = DateTime.now().toIso8601String();
    await db.into(db.localMessages).insert(LocalMessagesCompanion(
      serverId: drift.Value(null),
      conversationId: drift.Value(conversationId),
      localUuid: drift.Value(localUuid),
      senderId: drift.Value(senderId),
      messageType: drift.Value(type),
      bodyText: drift.Value(body ?? ''),
      replyToServerId: drift.Value(replyToServerId),
      filePath: drift.Value(filePath),
      mimeType: drift.Value(mimeType),
      fileSize: drift.Value(fileSize),
      duration: drift.Value(duration),
      serverTimestamp: drift.Value(nowIso),
      localCreatedAt: drift.Value(nowIso),
      status: drift.Value('sending'),
      syncStatus: drift.Value('pending'),
      deletedForMe: drift.Value(0),
      deletedForAll: drift.Value(0),
      isEdited: drift.Value(0),
    ));
    return await (db.select(db.localMessages)
          ..where((t) => t.localUuid.equals(localUuid)))
        .getSingle();
  }

  /// Store a list of conversations received from /conversations.
  static Future<void> upsertChats(List<Map<String, dynamic>> convs) async {
    final db = await LocalNovaDbProvider.db;
    for (final c in convs) {
      final id = int.tryParse(c['id'].toString()) ?? 0;
      if (id == 0) continue;
      final groupId = c['group_id'] != null
          ? int.tryParse(c['group_id'].toString())
          : null;
      await db.upsertChat(LocalChatsCompanion(
        id: drift.Value(id),
        chatId: drift.Value('$id'),
        convType: drift.Value(c['type'] ?? 'private'),
        title: drift.Value(c['name'] ?? c['title'] ?? 'محادثة'),
        avatar: drift.Value(c['avatar']),
        lastMessageId: drift.Value(
            c['last_message_id'] != null
                ? int.parse(c['last_message_id'].toString())
                : 0),
        lastMessagePreview:
            drift.Value(c['last_message'] ?? c['last_message_body'] ?? ''),
        lastMessageAt: drift.Value(c['last_message_at'] ?? c['updated_at']),
        unreadCount: drift.Value(
            c['unread_count'] != null
                ? int.parse(c['unread_count'].toString())
                : 0),
        muted: drift.Value((c['is_muted'] ?? 0) == 1),
        pinned: drift.Value((c['is_pinned'] ?? 0) == 1),
        isGroup: drift.Value(c['is_group'] == true || c['type'] == 'group'),
        memberCount: drift.Value(c['member_count'] != null
            ? int.parse(c['member_count'].toString())
            : 0),
        groupId: drift.Value(groupId),
        updatedAt: drift.Value(c['updated_at'] ?? ''),
      ));
    }
  }

  /// Store a list of messages received from /conversations/{id}/messages.
  /// Messages that exist locally with syncStatus != 'synced' are kept
  /// (outbox items / pending local edits) — server data only wins when local
  /// row is already synced from the server (no local drift).
  static Future<void> upsertMessages(List<Map<String, dynamic>> msgs) async {
    final db = await LocalNovaDbProvider.db;
    for (final m in msgs) {
      final serverId = int.tryParse(m['id'].toString());
      if (serverId == null) continue;
      final localUuid = m['client_message_id']?.toString() ?? '';
      final existing = await (db.select(db.localMessages)
            ..where((t) => t.serverId.equals(serverId)))
          .getSingleOrNull();
      // Conflict protection: never overwrite locally pending/edited rows
      if (existing != null &&
          (existing.syncStatus == 'pending' ||
              existing.syncStatus == 'conflict')) {
        continue;
      }
      final deletedForAll = m['deleted_at'] != null || m['status'] == 'deleted';
      final deletedForMe = m['deleted_for_me'] == true ||
          m['deleted_for_me'] == 1;
      if (deletedForAll && existing != null) {
        await (db.update(db.localMessages)
              ..where((t) => t.id.equals(existing.id)))
            .write(const LocalMessagesCompanion(
          deletedForAll: drift.Value(1),
          status: drift.Value('deleted'),
        ));
        continue;
      }
      final row = LocalMessagesCompanion(
        serverId: drift.Value(serverId),
        conversationId: drift.Value(
            int.parse(m['conversation_id'].toString())),
        localUuid: drift.Value(localUuid),
        senderId: drift.Value(int.parse(m['sender_id'].toString())),
        messageType: drift.Value(m['type'] ?? 'text'),
        bodyText: drift.Value(m['body']),
        replyToServerId: m['reply_to_message_id'] != null
            ? drift.Value(int.parse(m['reply_to_message_id'].toString()))
            : drift.Value(null),
        filePath: drift.Value(m['file_path']),
        thumbnailPath: drift.Value(m['thumbnail_path']),
        mimeType: drift.Value(m['mime_type']),
        fileSize: m['file_size'] != null
            ? drift.Value(int.parse(m['file_size'].toString()))
            : drift.Value(null),
        width: m['width'] != null
            ? drift.Value(int.parse(m['width'].toString()))
            : drift.Value(null),
        height: m['height'] != null
            ? drift.Value(int.parse(m['height'].toString()))
            : drift.Value(null),
        duration: m['duration'] != null
            ? drift.Value(int.parse(m['duration'].toString()))
            : drift.Value(null),
        serverTimestamp: drift.Value(m['created_at'] ?? ''),
        localCreatedAt: drift.Value(existing?.localCreatedAt ??
            DateTime.now().toIso8601String()),
        status: drift.Value(m['status'] ?? 'sent'),
        syncStatus: drift.Value('synced'),
        deletedForMe: drift.Value(deletedForMe ? 1 : 0),
        deletedForAll: drift.Value(deletedForAll ? 1 : 0),
        isEdited: drift.Value((m['is_edited'] ?? 0) == 1 ? 1 : 0),
        editedAt: drift.Value(m['edited_at']),
      );
      if (existing == null) {
        await db.into(db.localMessages).insert(row);
      } else {
        await (db.update(db.localMessages)
              ..where((t) => t.id.equals(existing.id)))
            .write(row);
      }
    }
  }

  /// Mark a local message as deleted-for-all locally (from server echo).
  static Future<void> markDeletedAll(int serverId) async {
    final db = await LocalNovaDbProvider.db;
    await (db.update(db.localMessages)
          ..where((t) => t.serverId.equals(serverId)))
        .write(const LocalMessagesCompanion(
      deletedForAll: drift.Value(1),
      status: drift.Value('deleted'),
    ));
  }

  /// Mark a local message as deleted-for-me (server echo of personal delete).
  static Future<void> markDeletedMe(int serverId) async {
    final db = await LocalNovaDbProvider.db;
    await (db.update(db.localMessages)
          ..where((t) => t.serverId.equals(serverId)))
        .write(const LocalMessagesCompanion(
      deletedForMe: drift.Value(1),
    ));
  }

  /// Read cached conversations (last-known state) for offline fallback.
  static Future<List<LocalChat>> cachedChats() async {
    final db = await LocalNovaDbProvider.db;
    return await (db.select(db.localChats)
          ..orderBy([(t) => drift.OrderingTerm(
              expression: t.lastMessageAt, mode: drift.OrderingMode.desc)]))
        .get();
  }

  /// Read cached messages for one conversation (local + synced).
  static Future<List<LocalMessage>> cachedMessages(int conversationId) async {
    final db = await LocalNovaDbProvider.db;
    return await (db.select(db.localMessages)
          ..where((t) => t.conversationId.equals(conversationId))
          ..where((t) => t.deletedForMe.equals(0))
          ..orderBy([(t) => drift.OrderingTerm(
              expression: t.localCreatedAt, mode: drift.OrderingMode.asc)]))
        .get();
  }

  /// Update chat unread count / last message from a new message event.
  static Future<void> updateChatFromMessage(Map<String, dynamic> m) async {
    final convId = int.tryParse(m['conversation_id']?.toString() ?? '');
    if (convId == null) return;
    final db = await LocalNovaDbProvider.db;
    await (db.update(db.localChats)
          ..where((t) => t.id.equals(convId)))
        .write(LocalChatsCompanion(
      lastMessageId: drift.Value(int.parse(m['id'].toString())),
      lastMessagePreview: drift.Value(m['body'] ?? ''),
      lastMessageAt: drift.Value(m['created_at'] ?? ''),
      updatedAt: drift.Value(DateTime.now().toIso8601String()),
    ));
  }
}
