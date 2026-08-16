import 'dart:convert';
import 'package:http/http.dart' as http;

/// خدمة الاتصال بخادم NOVA Messenger API
class ApiService {
  static const String baseUrl =
      'https://80-iuawg7ipo5m5tnjzab4ca-3e3e9a64.us2.manus.computer/api/v1';

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
