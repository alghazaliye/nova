// Local media storage manager.
// Downloads remote media to <support>/nova_media/{images,videos,audio,documents}/
// and records metadata in local_media (Drift).
// Read priority: local file if exists → remote URL fallback.
import 'dart:io';
import 'package:crypto/crypto.dart';
import 'package:drift/drift.dart' as drift;
import 'package:http/http.dart' as http;
import 'package:path_provider/path_provider.dart';
import 'local_nova_db.dart';
import 'local_nova_db_provider.dart';

class MediaStore {
  static const List<String> _categories = ['images', 'videos', 'audio', 'documents'];

  static String _categoryFor(String? mime) {
    final m = (mime ?? '').toLowerCase();
    if (m.startsWith('image/')) return 'images';
    if (m.startsWith('video/')) return 'videos';
    if (m.startsWith('audio/')) return 'audio';
    return 'documents';
  }

  static String _extFromMime(String? mime) {
    final m = (mime ?? '').toLowerCase();
    if (m.contains('jpeg') || m == 'image/jpg') return '.jpg';
    if (m == 'image/png') return '.png';
    if (m == 'image/gif') return '.gif';
    if (m == 'image/webp') return '.webp';
    if (m == 'video/mp4') return '.mp4';
    if (m == 'video/quicktime') return '.mov';
    if (m == 'video/webm') return '.webm';
    if (m == 'audio/mpeg') return '.mp3';
    if (m == 'audio/wav') return '.wav';
    if (m == 'audio/ogg') return '.ogg';
    if (m == 'audio/mp4' || m == 'audio/aac' || m == 'audio/x-m4a') return '.m4a';
    if (m == 'application/pdf') return '.pdf';
    return '';
  }

  /// Ensure the media base directory exists and return it.
  static Future<Directory> _baseDir() async {
    final support = await getApplicationSupportDirectory();
    final base = Directory('${support.path}/nova_media');
    if (!await base.exists()) await base.create(recursive: true);
    return base;
  }

  /// Download remote media to local storage; returns LocalMedia record id.
  /// If [mediaLocalId] provided, updates the existing record.
  static Future<int?> downloadMedia({
    required String remoteUrl,
    String? mimeType,
    int? serverAttachmentId,
    int? messageId,
  }) async {
    if (remoteUrl.isEmpty) return null;
    final db = await LocalNovaDbProvider.db;
    // 1. Check if already downloaded (by remote url)
    final existing = await (db.select(db.localMedia) 
          ..where((t) => t.remoteUrl.equals(remoteUrl)))
        .getSingleOrNull();
    if (existing != null) {
      if (await File(existing.localPath).exists()) return existing.id;
    }
    // NOTE: rows carry typed column accessors; we read raw values below.

    final base = await _baseDir();
    final ext = _extFromMime(mimeType);
    final fileName =
        '${DateTime.now().millisecondsSinceEpoch}_${remoteUrl.hashCode}$ext';
    final cat = _categoryFor(mimeType);
    final dir = Directory('${base.path}/$cat');
    if (!await dir.exists()) await dir.create(recursive: true);
    final file = File('${dir.path}/$fileName');

    try {
      final res = await http.get(Uri.parse(remoteUrl));
      if (res.statusCode != 200) return existing?.id;
      await file.writeAsBytes(res.bodyBytes);
      final checksum = md5.convert(res.bodyBytes).toString();
      if (existing != null) {
        await (db.update(db.localMedia)
              ..where((t) => t.id.equals(existing.id)))
            .write(LocalMediaCompanion(
          downloadStatus: const drift.Value('downloaded'),
          sizeBytes: drift.Value(res.bodyBytes.length),
          checksum: drift.Value(checksum),
        ));
        return existing.id;
      }
      return await db.into(db.localMedia).insert(LocalMediaCompanion(
            remoteUrl: drift.Value(remoteUrl),
            localPath: drift.Value(file.path),
            mimeType: drift.Value(mimeType ?? ''),
            sizeBytes: drift.Value(res.bodyBytes.length),
            checksum: drift.Value(checksum),
            category: drift.Value(cat),
            serverAttachmentId:
                drift.Value(serverAttachmentId),
            messageId: drift.Value(messageId),
            createdAt: drift.Value(DateTime.now().toIso8601String()),
          ));
    } catch (_) {
      return existing?.id;
    }
  }

  /// Local file path for a media id (for display priority: local first).
  static Future<String?> localPath(int mediaLocalId) async {
    final db = await LocalNovaDbProvider.db;
    final row = await (db.select(db.localMedia)
          ..where((t) => t.id.equals(mediaLocalId)))
        .getSingleOrNull();
    if (row == null) return null;
    return await File(row.localPath).exists() ? row.localPath : null;
  }

  /// Delete cache only (does not touch local messages).
  static Future<int> clearCache() async {
    final base = await _baseDir();
    final thumbs = Directory('${base.path}/thumbnails');
    if (await thumbs.exists()) await thumbs.delete(recursive: true);
    await thumbs.create(recursive: true);
    final db = await LocalNovaDbProvider.db;
    return db.delete(db.localMedia).go();
  }

  /// Storage usage per category (bytes).
  static Future<Map<String, int>> usageByCategory() async {
    final base = await _baseDir();
    final result = <String, int>{
      for (final c in _categories) c: 0,
      'thumbnails': 0,
    };
    for (final c in [..._categories, 'thumbnails']) {
      final dir = Directory('${base.path}/$c');
      if (!await dir.exists()) continue;
      int total = 0;
      await for (final f in dir.list(recursive: true)) {
        if (f is File) total += await f.length();
      }
      result[c] = total;
    }
    return result;
  }
}
