import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:nova_flutter/utils/nova_web_state.dart' show novaHref, webOriginFallback;

/// خدمة الاتصال بخادم NOVA Messenger API
class ApiService {
  /// لا رابط ثابت لأي بيئة سابقة — دائمًا نفس نطاق الصفحة الحالية.
  static const String _defaultUrl = '/api/v1'; // لا يُستخدم إلا عند عدم توفر origin

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
        String origin = Uri.parse(novaHref()).origin;
        if (origin.isEmpty || !origin.startsWith('http')) {
          // novaHref فشل في قراءة window.location — قراءة origin مباشرة
          origin = webOriginFallback() ?? '';
        }
        if (origin.isNotEmpty && origin.startsWith('http')) {
          return '$origin/api/v1';
        }
      } catch (_) {}
    }
    return _defaultUrl;
  }

  /// رابط الخدمة الحالي بدون /api/v1 — مفيد لإعادة بناء أي مسار
  static String get serviceOrigin {
    return baseUrl.replaceAll(RegExp(r'/api/v1/?$'), '');
  }

  static String? token;
  static int userId = 0;

  /// Callback when a 401 Unauthorized is received globally
  static Function(int statusCode, String? errorCode)? onUnauthorized;

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

  /// جلب رابط الوسائط مع التوكن (للوسائط التي تتطلب مصادقة مثل المرفقات)
  static Map<String, String> mediaHeaders() {
    return {
      if (token != null) 'Authorization': 'Bearer $token',
    };
  }

  /// مسح مسار نسبي إلى رابط كامل (نفس mediaUrl)
  static String getMediaUrl(String? path) => mediaUrl(path);

  static Map<String, dynamic> _decode(http.Response res) {
    Map<String, dynamic> data;
    try {
      data = jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      data = {
        'success': false,
        'message': res.body,
      };
    }
    data['status_code'] = res.statusCode;

    // Global 401 handler: only trigger if we had a token (authenticated request)
    // and the error code is UNAUTHORIZED (session expired/revoked)
    // or status is 401. Note: ACCOUNT_BANNED (403) is handled by AuthProvider.
    if (res.statusCode == 401 && token != null) {
      final errorCode = data['error_code']?.toString();
      if (onUnauthorized != null) {
        onUnauthorized!(res.statusCode, errorCode);
      }
    }

    return data;
  }
}
