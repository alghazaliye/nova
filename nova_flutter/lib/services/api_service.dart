import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:nova_flutter/utils/nova_web_state.dart' show novaHref;

/// خدمة الاتصال بخادم NOVA Messenger API
class ApiService {
  static const String _defaultUrl =
      'https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/api/v1';

  /// تجاوز يدوي للرابط الأساسي (يُستخدم لتغيير الخادم عند النشر)
  static String? baseUrlOverride;

  /// الرابط الأساسي — ديناميكي حسب نطاق الاستضافة الحالية (نفس النطاق + /api/v1)،
  /// وقابل للتغيير عبر baseUrlOverride أو ?api=HOST في الويب
  static String get baseUrl {
    final ov = baseUrlOverride;
    if (ov != null && ov.trim().isNotEmpty) {
      final u = ov.trim().replaceAll(RegExp(r'/+$'), '');
      return u.endsWith('/api/v1') ? u : '$u/api/v1';
    }
    if (kIsWeb) {
      try {
        final params = Uri.parse(novaHref()).queryParameters;
        final api = params['api'];
        if (api != null && api.trim().isNotEmpty) {
          final u = api.trim().replaceAll(RegExp(r'/+$'), '');
          return u.endsWith('/api/v1') ? u : '$u/api/v1';
        }
      } catch (_) {}
      // افتراضيًا نفس نطاق الصفحة الحالية (يعمل على Render وأي استضافة)
      try {
        final origin = Uri.parse(novaHref()).origin;
        if (origin.isNotEmpty && origin.startsWith('http')) {
          return '$origin/api/v1';
        }
      } catch (_) {}
    }
    return _defaultUrl;
  }

  static String? token;
  static int userId = 0;

  static final Map<String, String> _headers = {
    'Content-Type': 'application/json',
    'Accept-Language': 'ar',
  };

  static Map<String, String> get headers => {
        ..._headers,
        if (token != null) 'Authorization': 'Bearer $token',
      };

  static Future<Map<String, dynamic>> post(String path,
      {Map<String, dynamic>? body}) async {
    final url = Uri.parse('$baseUrl$path');
    final res = await http.post(url, headers: headers, body: jsonEncode(body));
    return _decode(res);
  }

  static Future<Map<String, dynamic>> get(String path,
      {Map<String, String>? query}) async {
    final url = Uri.parse('$baseUrl$path').replace(queryParameters: query);
    final res = await http.get(url, headers: headers);
    return _decode(res);
  }

  static Future<Map<String, dynamic>> put(String path,
      {Map<String, dynamic>? body}) async {
    final url = Uri.parse('$baseUrl$path');
    final res = await http.put(url, headers: headers, body: jsonEncode(body));
    return _decode(res);
  }

  static Future<Map<String, dynamic>> delete(String path, {Map<String, dynamic>? body}) async {
    final url = Uri.parse('$baseUrl$path');
    final Map<String, String> delHeaders = {...headers};
    if (body != null) delHeaders['Content-Type'] = 'application/json; charset=utf-8';
    final res = await http.delete(url, headers: delHeaders, body: body != null ? jsonEncode(body) : null);
    return _decode(res);
  }

  static Future<Map<String, dynamic>> uploadMultipart(
      String path, List<http.MultipartFile> files,
      {Map<String, String>? fields}) async {
    final url = Uri.parse('$baseUrl$path');
    final req = http.MultipartRequest('POST', url);
    req.headers.addAll({..._headers, if (token != null) 'Authorization': 'Bearer $token'});
    req.files.addAll(files);
    if (fields != null) req.fields.addAll(fields);
    final streamed = await req.send();
    final res = await http.Response.fromStream(streamed);
    return _decode(res);
  }

  /// تحويل مسار ملف نسبي إلى رابط كامل قابل للعرض
  static String mediaUrl(String? path) {
    if (path == null || path.isEmpty) return '';
    if (path.startsWith('http')) return path;
    final base = baseUrl.replaceAll(RegExp(r'/api/v1/?$'), '');
    final clean = path.startsWith('/') ? path : '/$path';
    return '$base/media$clean';
  }

  /// مسح مسار نسبي إلى رابط كامل (نفس mediaUrl)
  static String getMediaUrl(String? path) => mediaUrl(path);

  static Map<String, dynamic> _decode(http.Response res) {
    try {
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      return {'success': false, 'message': res.body};
    }
  }
}
