// Nova Messenger Offline-First: Local Drift Database
// This file defines the local SQLite schema. Do not delete existing working code.
// Generated files: local_nova_db.g.dart (build_runner)
//
// Tables:
//   local_chats            — mirror of /conversations
//   local_messages         — mirror of messages (with sync_status)
//   local_users            — contacts / senders cache (presence)
//   local_media            — downloaded media cache
//   local_outbox           — pending operations queue
//   local_sync_state       — per-user sync cursors / state

import 'package:drift/drift.dart';

part 'local_nova_db.g.dart';

@DataClassName('LocalChat')
class LocalChats extends Table {
  IntColumn get id => integer()();
  TextColumn get chatId => text().unique()(); // stringified conv id for keying
  TextColumn get convType => text().withDefault(const Constant('private'))();
  TextColumn get title => text().withDefault(const Constant(''))();
  TextColumn get avatar => text().nullable()();
  IntColumn get lastMessageId => integer().withDefault(const Constant(0))();
  TextColumn get lastMessagePreview => text().withDefault(const Constant(''))();
  TextColumn get lastMessageAt => text().nullable()();
  IntColumn get unreadCount => integer().withDefault(const Constant(0))();
  BoolColumn get muted => boolean().withDefault(const Constant(false))();
  BoolColumn get archived => boolean().withDefault(const Constant(false))();
  BoolColumn get pinned => boolean().withDefault(const Constant(false))();
  BoolColumn get isGroup => boolean().withDefault(const Constant(false))();
  IntColumn get memberCount => integer().withDefault(const Constant(0))();
  IntColumn get otherUserId => integer().withDefault(const Constant(0))();
  IntColumn get groupId => integer().nullable()();
  TextColumn get updatedAt => text().withDefault(const Constant(''))();
  IntColumn get deletedForMe => integer().withDefault(const Constant(0))(); // 1 = personal delete
  @override
  Set<Column> get primaryKey => {id};
}

@DataClassName('LocalMessage')
class LocalMessages extends Table {
  IntColumn get id => integer().autoIncrement()();
  IntColumn get serverId => integer().nullable()(); // server messages.id
  IntColumn get conversationId => integer()();
  TextColumn get localUuid => text().unique()(); // client_message_id (uuid)
  IntColumn get senderId => integer()();
  TextColumn get messageType => text().withDefault(const Constant('text'))();
  TextColumn get bodyText => text().nullable()();
  IntColumn get replyToServerId => integer().nullable()();
  IntColumn get replyToLocalUuid => integer().nullable()(); // local id of replied msg if exists
  IntColumn get mediaLocalId => integer().nullable()(); // local_media entry
  TextColumn get filePath => text().nullable()(); // remote storage_path
  TextColumn get thumbnailPath => text().nullable()();
  TextColumn get mimeType => text().nullable()();
  IntColumn get fileSize => integer().nullable()();
  IntColumn get width => integer().nullable()();
  IntColumn get height => integer().nullable()();
  IntColumn get duration => integer().nullable()();
  TextColumn get serverTimestamp => text().withDefault(const Constant(''))();
  TextColumn get localCreatedAt => text().withDefault(const Constant(''))();
  TextColumn get status => text().withDefault(const Constant('pending_sync'))(); // local|sending|sent|delivered|read|failed|pending_sync
  TextColumn get syncStatus => text().withDefault(const Constant('pending'))(); // pending|synced|conflict|removed
  IntColumn get deletedForMe => integer().withDefault(const Constant(0))();
  IntColumn get deletedForAll => integer().withDefault(const Constant(0))();
  IntColumn get isEdited => integer().withDefault(const Constant(0))();
  TextColumn get editedAt => text().nullable()();
  IntColumn get attempt => integer().withDefault(const Constant(0))();
}

@DataClassName('LocalUser')
class LocalUsers extends Table {
  IntColumn get userId => integer().unique()();
  TextColumn get name => text().withDefault(const Constant(''))();
  TextColumn get phone => text().withDefault(const Constant(''))();
  TextColumn get email => text().nullable()();
  TextColumn get username => text().nullable()();
  TextColumn get avatar => text().nullable()();
  TextColumn get bio => text().nullable()();
  TextColumn get presence => text().withDefault(const Constant('offline'))(); // online|offline
  TextColumn get lastSeen => text().nullable()();
  IntColumn get isVerified => integer().withDefault(const Constant(0))();
  TextColumn get updatedAt => text().withDefault(const Constant(''))();
  @override
  Set<Column> get primaryKey => {userId};
}

@DataClassName('LocalMediaRecord')
class LocalMedia extends Table {
  IntColumn get id => integer().autoIncrement()();
  IntColumn get serverAttachmentId => integer().nullable()();
  IntColumn get messageId => integer().nullable()(); // local_messages.id
  TextColumn get remoteUrl => text().nullable()();
  TextColumn get localPath => text()();
  TextColumn get mimeType => text().withDefault(const Constant(''))();
  IntColumn get sizeBytes => integer().withDefault(const Constant(0))();
  TextColumn get checksum => text().withDefault(const Constant(''))();
  TextColumn get category => text().withDefault(const Constant('image'))(); // image|video|audio|document
  TextColumn get downloadStatus => text().withDefault(const Constant('downloaded'))(); // downloaded|in_progress|failed
  TextColumn get createdAt => text().withDefault(const Constant(''))();
}

@DataClassName('OutboxItem')
class LocalOutbox extends Table {
  IntColumn get id => integer().autoIncrement()();
  TextColumn get operation => text()(); // SEND_MESSAGE|UPLOAD_MEDIA|EDIT_MESSAGE|DELETE_MESSAGE|MARK_READ|UPDATE_PROFILE
  TextColumn get entityType => text().withDefault(const Constant('message'))();
  TextColumn get entityRef => text().withDefault(const Constant(''))(); // localUuid or serverId
  TextColumn get payload => text().withDefault(const Constant('{}'))(); // json payload
  TextColumn get status => text().withDefault(const Constant('pending'))(); // pending|in_progress|failed|done
  IntColumn get retryCount => integer().withDefault(const Constant(0))();
  TextColumn get nextRetryAt => text().nullable()();
  TextColumn get lastError => text().nullable()();
  TextColumn get createdAt => text().withDefault(const Constant(''))();
  TextColumn get lastAttemptAt => text().nullable()();
}

@DataClassName('SyncState')
class LocalSyncState extends Table {
  IntColumn get id => integer().autoIncrement()();
  TextColumn get stateKey => text().unique()(); // e.g. 'last_sync_ts', 'me_user_id'
  TextColumn get stateValue => text().withDefault(const Constant(''))();
  TextColumn get updatedAt => text().withDefault(const Constant(''))();
}

@DriftDatabase(
  tables: [
    LocalChats,
    LocalMessages,
    LocalUsers,
    LocalMedia,
    LocalOutbox,
    LocalSyncState,
  ],
)
class LocalNovaDb extends _$LocalNovaDb {
  LocalNovaDb(super.executor);

  @override
  int get schemaVersion => 1;

  /// Upsert a chat row (by chatId string).
  Future<int> upsertChat(LocalChatsCompanion chat) async {
    final existing = await (select(localChats)..where((t) => t.chatId.equals(chat.chatId.value))).getSingleOrNull();
    if (existing == null) {
      return into(localChats).insert(chat);
    }
    await (update(localChats)..where((t) => t.id.equals(existing.id))).write(chat);
    return existing.id;
  }
}


