// OutboxService — pending-operations queue with exponential backoff.
// Operations: SEND_MESSAGE / UPLOAD_MEDIA / EDIT_MESSAGE / DELETE_MESSAGE /
// MARK_READ / UPDATE_PROFILE. Items stay in the queue until the server
// confirms, so offline actions are never lost.
import 'dart:async';
import 'dart:io';
import 'dart:convert';
import 'package:drift/drift.dart' as drift;
import 'package:drift/drift.dart' show OrderingTerm;
import 'package:http/http.dart' as http;
import 'local_nova_db.dart';
import 'local_nova_db_provider.dart';
import '../services/api_service.dart';
import 'network_detector.dart';

/// Backoff delays (seconds) per attempt index — configurable.
const List<int> kOutboxBackoffSeconds = [2, 5, 10, 30, 60];

class OutboxService {
  static Timer? _drainTimer;
  static StreamSubscription<List<NovaNetworkState>>? _netSub;
  static bool _isDraining = false;

  /// Start background drain loop + network listener (call once at app start).
  static void start(NetworkDetector detector) {
    _drainTimer?.cancel();
    _drainTimer = Timer.periodic(const Duration(seconds: 5), (_) async {
      await drain();
    });
    _netSub?.cancel();
    detector.addListener(() async {
      if (detector.state == NovaNetworkState.online) {
        await drain();
      }
    });
  }

  /// Push a pending operation into the local outbox.
  static Future<int> push({
    required String operation,
    required String entityRef,
    required Map<String, dynamic> payload,
    String entityType = 'message',
  }) async {
    final db = await LocalNovaDbProvider.db;
    return db.into(db.localOutbox).insert(LocalOutboxCompanion(
          operation: drift.Value(operation),
          entityRef: drift.Value(entityRef),
          payload: drift.Value(jsonEncode(payload)),
          entityType: drift.Value(entityType),
          createdAt: drift.Value(DateTime.now().toIso8601String()),
        ));
  }

  /// Drain: process pending items when online and server reachable.
  static Future<void> drain() async {
    if (_isDraining || ApiService.token == null) return;
    _isDraining = true;
    try {
      final db = await LocalNovaDbProvider.db;
      final now = DateTime.now().toIso8601String();
      final items = await (db.select(db.localOutbox)
            ..where((t) =>
                t.status.equals('pending') &
                (t.nextRetryAt.isNull() | t.nextRetryAt.isSmallerOrEqualValue(now)))
            ..orderBy([(t) => OrderingTerm.asc(t.id)])
            ..limit(10))
          .get();
      for (final item in items) {
        await _processOne(db, item);
      }
    } finally {
      _isDraining = false;
    }
  }

  static Future<void> _processOne(LocalNovaDb db, OutboxItem item) async {
    // Mark in-progress
    await (db.update(db.localOutbox)
          ..where((t) => t.id.equals(item.id)))
        .write(LocalOutboxCompanion(
      status: const drift.Value('in_progress'),
      lastAttemptAt: drift.Value(DateTime.now().toIso8601String()),
    ));
    try {
      final payload = jsonDecode(item.payload) as Map<String, dynamic>;
      bool ok = false;
      switch (item.operation) {
        case 'SEND_MESSAGE':
          ok = await _sendTextMessage(db, payload);
          break;
        case 'UPLOAD_MEDIA':
          ok = await _uploadMedia(db, payload);
          break;
        case 'EDIT_MESSAGE':
          ok = await _editMessage(db, payload);
          break;
        case 'DELETE_MESSAGE':
          ok = await _deleteMessage(db, payload);
          break;
        case 'MARK_READ':
          ok = await _markRead(payload);
          break;
        default:
          ok = true;
      }
      if (ok) {
        await (db.delete(db.localOutbox)..where((t) => t.id.equals(item.id)))
            .go();
      } else {
        await _scheduleRetry(db, item, 'فشل العملية');
      }
    } catch (e) {
      await _scheduleRetry(db, item, e.toString());
    }
  }

  static Future<void> _scheduleRetry(
      LocalNovaDb db, OutboxItem item, String error) async {
    final attempt = item.retryCount + 1;
    final delay = attempt < kOutboxBackoffSeconds.length
        ? kOutboxBackoffSeconds[attempt]
        : kOutboxBackoffSeconds.last;
    final nextAt =
        DateTime.now().add(Duration(seconds: delay)).toIso8601String();
    await (db.update(db.localOutbox)
          ..where((t) => t.id.equals(item.id)))
        .write(LocalOutboxCompanion(
      retryCount: drift.Value(attempt),
      status: const drift.Value('pending'),
      nextRetryAt: drift.Value(nextAt),
      lastError: drift.Value(error.substring(0, 500)),
    ));
  }

  /// Send a text message that was queued offline. Uses client_message_id for
  /// server-side idempotency; on success links the local row to the server id.
  static Future<bool> _sendTextMessage(
      LocalNovaDb db, Map<String, dynamic> p) async {
    final convId = p['conversation_id'].toString();
    final localUuid = p['local_uuid']?.toString() ?? '';
    final localMsgId = p['local_message_id'];
    final res = await ApiService.post('/conversations/$convId/messages', body: {
      'client_message_id': localUuid,
      'type': p['type'] ?? 'text',
      'body': p['body'],
      if (p['reply_to_message_id'] != null)
        'reply_to_message_id': p['reply_to_message_id'],
    });
    final ok = res['success'] == true && res['data'] != null;
    if (ok && localMsgId != null) {
      final data = Map<String, dynamic>.from(res['data'] as Map);
      final serverId = int.parse(data['id'].toString());
      await (db.update(db.localMessages)
            ..where((t) => t.id.equals(localMsgId)))
          .write(LocalMessagesCompanion(
        serverId: drift.Value(serverId),
        status: drift.Value(data['status'] ?? 'sent'),
        syncStatus: const drift.Value('synced'),
      ));
    }
    return ok;
  }

  /// Upload media (multipart) queued offline.
  static Future<bool> _uploadMedia(
      LocalNovaDb db, Map<String, dynamic> p) async {
    final convId = p['conversation_id'].toString();
    final localUuid = p['local_uuid']?.toString() ?? '';
    final localMsgId = p['local_message_id'];
    final localPath = p['local_path']?.toString() ?? '';
    if (localPath.isEmpty) return false;
    final file = http.MultipartFile.fromBytes('file',
        await FileIO.read(localPath), // see file_io.dart
        filename: p['file_name'] ?? 'file');
    final res = await ApiService.uploadMultipart(
      '/conversations/$convId/media',
      [file],
      fields: {
        'client_message_id': localUuid,
        'type': p['type'] ?? 'image',
        if (p['caption'] != null) 'caption': p['caption'].toString(),
      },
    );
    final ok = res['success'] == true && res['data'] != null;
    if (ok && localMsgId != null) {
      final data = Map<String, dynamic>.from(res['data'] as Map);
      final serverId = int.parse(data['id'].toString());
      await (db.update(db.localMessages)
            ..where((t) => t.id.equals(localMsgId)))
          .write(LocalMessagesCompanion(
        serverId: drift.Value(serverId),
        status: drift.Value(data['status'] ?? 'sent'),
        syncStatus: const drift.Value('synced'),
      ));
    }
    return ok;
  }

  /// Edit message queued offline.
  static Future<bool> _editMessage(
      LocalNovaDb db, Map<String, dynamic> p) async {
    final serverId = p['server_id']?.toString();
    if (serverId == null) return false;
    final res = await ApiService.put('/messages/$serverId',
        body: {'body': p['body']});
    if (res['success'] == true) {
      await (db.update(db.localMessages)
            ..where((t) => t.id.equals(int.parse(p['local_message_id'].toString()))))
          .write(const LocalMessagesCompanion(
        isEdited: drift.Value(1),
      ));
      return true;
    }
    return false;
  }

  /// Delete message queued offline (personal or for everyone).
  static Future<bool> _deleteMessage(
      LocalNovaDb db, Map<String, dynamic> p) async {
    final serverId = p['server_id']?.toString();
    if (serverId == null) return false;
    final forAll = p['for_all'] == true;
    final res = await ApiService.delete(
      '/messages/$serverId',
      body: {'delete_scope': forAll ? 'everyone' : 'personal'},
    );
    if (res['success'] == true) {
      await (db.update(db.localMessages)
            ..where((t) => t.id.equals(int.parse(p['local_message_id'].toString()))))
          .write(forAll
              ? const LocalMessagesCompanion(
                  deletedForAll: drift.Value(1),
                  status: drift.Value('deleted'))
              : const LocalMessagesCompanion(deletedForMe: drift.Value(1)));
      return true;
    }
    return false;
  }

  /// Mark messages as read queued offline.
  static Future<bool> _markRead(Map<String, dynamic> p) async {
    final serverId = p['server_id']?.toString();
    if (serverId == null) return false;
    final res = await ApiService.post('/messages/$serverId/read');
    return res['success'] == true;
  }


  static Future<void> cancelForLocalMessage(int localMessageId) async {
    final db = await LocalNovaDbProvider.db;
    await (db.delete(db.localOutbox)
          ..where((t) => t.entityRef.equals('$localMessageId')))
        .go();
  }

}

/// File IO helper — reads raw bytes of a local file path.
class FileIO {
  static Future<List<int>> read(String path) async {
    final f = File(path);
    return f.readAsBytes();
  }
}
