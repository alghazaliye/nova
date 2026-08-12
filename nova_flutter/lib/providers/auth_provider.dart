import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';

/// مزود حالة المصادقة والمستخدم الحالي
class AuthProvider extends ChangeNotifier {
  NovaUser? _user;
  bool _loading = false;
  String? _error;

  NovaUser? get user => _user;

  set user(NovaUser? value) {
    _user = value;
    notifyListeners();
  }
  bool get loading => _loading;
  String? get error => _error;
  bool get isLoggedIn => _user != null;

  /// التوكن الحالي في الذاكرة (لقراءة فورية دون await)
  static String? get currentToken => ApiService.token;

  /// حفظ رمز التحقق
  static Future<void> saveToken(String token) async {
    ApiService.token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token);
  }

  static Future<String?> loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    if (token != null) ApiService.token = token;
    return token;
  }

  static Future<void> clearToken() async {
    ApiService.token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('token');
  }

  /// POST /auth/register
  Future<bool> register(String phone,
      {String? countryCode, String? name, String? email}) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/register', body: {
      'phone': phone,
      if (countryCode != null) 'country_code': countryCode,
      if (name != null) 'name': name,
      if (email != null) 'email': email,
    });
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'فشل في التسجيل';
      notifyListeners();
      return false;
    }
    notifyListeners();
    return true;
  }

  /// POST /auth/login
  Future<bool> login(String phone) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/login', body: {'phone': phone});
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'فشل في تسجيل الدخول';
      notifyListeners();
      return false;
    }
    notifyListeners();
    return true;
  }

  /// POST /auth/verify-otp
  Future<bool> verifyOtp(String phone, String otp) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/verify-otp', body: {
      'phone': phone,
      'otp': otp,
    });
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'رمز التحقق غير صحيح';
      notifyListeners();
      return false;
    }
    final data = res['data'] as Map<String, dynamic>? ?? {};
    final token = data['token'] as String?;
    if (token != null) {
      _user = NovaUser.fromJson(Map<String, dynamic>.from(data['user'] ?? {}));
      await saveToken(token);
    }
    notifyListeners();
    return true;
  }

  /// GET /auth/me
  Future<bool> fetchMe() async {
    if (ApiService.token == null) return false;
    final res = await ApiService.get('/auth/me');
    if (res['success'] == true) {
      _user = NovaUser.fromJson(Map<String, dynamic>.from(res['data'] ?? {}));
      notifyListeners();
      return true;
    }
    return false;
  }

  Future<void> logout() async {
    try {
      await ApiService.post('/auth/logout');
    } catch (_) {}
    _user = null;
    await clearToken();
    notifyListeners();
  }
}
