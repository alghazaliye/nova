import 'dart:convert';
import 'package:http/http.dart' as http;

/// خدمة الاتصال بخادم NOVA Messenger API
class ApiService {
  static const String baseUrl =
      'https://80-iuawg7ipo5m5tnjzab4ca-3e3e9a64.us2.manus.computer/nova/backend/public/api/v1';

  static String? token;

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

  static Future<Map<String, dynamic>> delete(String path,
      {Map<String, dynamic>? body}) async {
    final url = Uri.parse('$baseUrl$path');
    final http.Response res;
    if (body != null) {
      res = await http.delete(url, headers: headers, body: jsonEncode(body));
    } else {
      res = await http.delete(url, headers: headers);
    }
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

  static Map<String, dynamic> _decode(http.Response res) {
    try {
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      return {'success': false, 'message': res.body};
    }
  }
}
