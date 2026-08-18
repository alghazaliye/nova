// SyncEngine — incremental REST sync.
// Pulls new/updated conversations & messages since last sync using
// conversation updated_at / messages before_id pagination.
// Works together with OutboxService (push) and LocalSyncService (store).
import 'package:drift/drift.dart' as drift;
import 'package:drift/drift.dart' show OrderingTerm;
import 'local_nova_db.dart';
import 'local_nova_db_provider.dart';
import 'local_sync_service.dart';
import '../services/api_service.dart';

class SyncEngine {
  /// Full foreground sync: refresh conversations, then per-chat message pull
  /// for chats with newer messages. Safe to call repeatedly; incremental.
  static Future<void> fullSync({required int myUserId}) async {
    await pullConversations(myUserId: myUserId);
    final db = await LocalNovaDbProvider.db;
    // Pull newest messages for the most recent chats (bounded sync)
    final chats = await (db.select(db.localChats)
          ..orderBy([(t) => OrderingTerm.desc(t.updatedAt)])
          ..limit(20))
        .get();
    for (final chat in chats) {
      await pullMessages(
          conversationId: chat.id, lastServerId: chat.lastMessageId);
    }
  }

  /// Pull /conversations and store locally.
  static Future<void> pullConversations({required int myUserId}) async {
    try {
      final res = await ApiService.get('/conversations');
      if (res['success'] != true || res['data'] is! List) return;
      final convs = (res['data'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      await LocalSyncService.upsertChats(convs);
      // Persist last sync timestamp
      final db = await LocalNovaDbProvider.db;
      await _setState(db, 'last_sync_ts', DateTime.now().toIso8601String());
    } catch (_) {}
  }

  /// Pull newest messages for one conversation (bounded: up to 100).
  /// Uses lastServerId to fetch forward-ish (fetch last page, merge newer).
  static Future<void> pullMessages({
    required int conversationId,
    required int lastServerId,
  }) async {
    try {
      final res = await ApiService.get(
        '/conversations/$conversationId/messages',
        query: {'limit': '50'},
      );
      if (res['success'] != true || res['data'] is! List) return;
      final msgs = (res['data'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      await LocalSyncService.upsertMessages(msgs);
    } catch (_) {}
  }

  /// Sync single chat's history with before_id pagination (older messages).
  static Future<bool> syncOlderMessages({
    required int conversationId,
    required int beforeId,
  }) async {
    try {
      final res = await ApiService.get(
        '/conversations/$conversationId/messages',
        query: {'limit': '50', 'before_id': '$beforeId'},
      );
      if (res['success'] != true || res['data'] is! List) return false;
      final msgs = (res['data'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      await LocalSyncService.upsertMessages(msgs);
      return true;
    } catch (_) {
      return false;
    }
  }

  static Future<void> _setState(
      LocalNovaDb db, String key, String value) async {
    final now = DateTime.now().toIso8601String();
    final existing = await (db.select(db.localSyncState)
          ..where((t) => t.stateKey.equals(key)))
        .getSingleOrNull();
    if (existing == null) {
      await db.into(db.localSyncState).insert(LocalSyncStateCompanion(
            stateKey: drift.Value(key),
            stateValue: drift.Value(value),
            updatedAt: drift.Value(now),
          ));
    } else {
      await (db.update(db.localSyncState)
            ..where((t) => t.id.equals(existing.id)))
          .write(LocalSyncStateCompanion(
        stateValue: drift.Value(value),
        updatedAt: drift.Value(now),
      ));
    }
  }
}
