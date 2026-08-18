// Provider singleton for LocalNovaDb.
// Opens the SQLite DB under <app support dir>/nova_local.db lazily.
import 'dart:io';
import 'package:drift/native.dart';
import 'package:path_provider/path_provider.dart';
import 'local_nova_db.dart';

/// Singleton access to the local offline database.
class LocalNovaDbProvider {
  static LocalNovaDb? _instance;

  static Future<LocalNovaDb> get db async {
    if (_instance != null) return _instance!;
    final dir = await getApplicationSupportDirectory();
    final file = File('${dir.path}/nova_local.db');
    _instance = LocalNovaDb(NativeDatabase.createInBackground(file));
    return _instance!;
  }

  /// Delete the local DB + media folder (used at logout).
  static Future<void> wipeLocalData() async {
    final dir = await getApplicationSupportDirectory();
    final dbFile = File('${dir.path}/nova_local.db');
    final d = _instance;
    _instance = null;
    try {
      await d?.close();
    } catch (_) {}
    try {
      if (await dbFile.exists()) await dbFile.delete();
      final media = Directory('${dir.path}/nova_media');
      if (await media.exists()) await media.delete(recursive: true);
    } catch (_) {}
  }
}
