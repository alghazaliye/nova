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

  /// بصمة الجهاز الموحدة — تُحسب مرة واحدة وتُحفظ، وتُستخدم لتتبع الأجهزة
  static Future<String> getDeviceFingerprint() async {
    final prefs = await SharedPreferences.getInstance();
    String? fp = prefs.getString('device_fingerprint');
    if (fp != null && fp.isNotEmpty) return fp;
    // توليد بصمة مستقرة: معرف الجهاز + الوقت
    fp = 'fp_${DateTime.now().millisecondsSinceEpoch}';
    await prefs.setString('device_fingerprint', fp);
    return fp;
  }

  /// معلومات الجهاز الحالية (نظام التشغيل + الإصدار)
  static Map<String, String> get deviceInfo => {
        'device_model': 'Flutter-${kIsWeb ? 'Web' : 'Android'}',
        'os_name': kIsWeb ? 'Web Browser' : 'Android',
        'os_version': kIsWeb ? 'browser' : '14',
        'app_version': '3.6.0',
        'platform': kIsWeb ? 'web' : 'android',
      };

  /// POST /auth/register
  Future<bool> register(String phone,
      {String? countryCode, String? name, String? email}) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final deviceUuid = await getDeviceFingerprint();
    final res = await ApiService.post('/auth/register', body: {
      'phone': phone,
      if (countryCode != null) 'country_code': countryCode,
      if (name != null) 'name': name,
      if (email != null) 'email': email,
      'device_uuid': deviceUuid,
      ...deviceInfo,
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
    final deviceUuid = await getDeviceFingerprint();
    final res = await ApiService.post('/auth/login', body: {
      'phone': phone,
      'device_uuid': deviceUuid,
      ...deviceInfo,
    });
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'فشل في تسجيل الدخول';
      notifyListeners();
      return false;
    }
    // Admin disabled OTP: the response returns token directly
    final data = res['data'] as Map<String, dynamic>? ?? {};
    final bypass = data['otp_bypass'] == true;
    _lastLoginBypass = bypass;
    if (bypass) {
      final token = data['token'] as String?;
      if (token != null) {
        _user = NovaUser.fromJson(Map<String, dynamic>.from(data['user'] ?? {}));
        await saveToken(token);
        registerCurrentDevice();
      }
    }
    notifyListeners();
    return true;
  }

  /// هل عاد الدخول بدون OTP (التحقق معطّل من لوحة التحكم)
  bool _lastLoginBypass = false;
  bool get lastLoginBypass => _lastLoginBypass;

  /// POST /auth/verify-otp
  Future<bool> verifyOtp(String phone, String otp) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final deviceUuid = await getDeviceFingerprint();
    final res = await ApiService.post('/auth/verify-otp', body: {
      'phone': phone,
      'otp': otp,
      'device_uuid': deviceUuid,
      ...deviceInfo,
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
      // تسجيل تفاصيل الجهاز عند كل دخول ناجح
      registerCurrentDevice();
    }
    notifyListeners();
    return true;
  }

  /// تسجيل تفاصيل الجهاز الحالي لدى الخادم (تتبع الأجهزة وحد الباقة)
  Future<void> registerCurrentDevice() async {
    try {
      final fp = await getDeviceFingerprint();
      await ApiService.post('/devices/register', body: {
        'device_fingerprint': fp,
        ...deviceInfo,
      });
    } catch (_) {}
  }

  /// GET /auth/me
  Future<bool> fetchMe() async {
    if (ApiService.token == null) return false;
    final res = await ApiService.get('/auth/me');
    final err = (res['error_code'] ?? '').toString();
    // حساب محظور أو جلسة ملغاة → تسجيل خروج فوري
    if (err == 'FORBIDDEN' || err == 'UNAUTHORIZED') {
      await logout();
      _forcedLogoutReason = err == 'FORBIDDEN'
          ? (res['message'] ?? 'تم حظر هذا الحساب من قبل إدارة التطبيق')
          : null;
      notifyListeners();
      return false;
    }
    if (res['success'] == true) {
      _user = NovaUser.fromJson(Map<String, dynamic>.from(res['data'] ?? {}));
      notifyListeners();
      return true;
    }
    return false;
  }

  /// سبب تسجيل الخروج الإجباري (حظر) — تُعرض رسالة للمستخدم قبل شاشة الدخول
  String? _forcedLogoutReason;
  String? get forcedLogoutReason => _forcedLogoutReason;
  void clearForcedLogoutReason() => _forcedLogoutReason = null;

  Future<void> logout() async {
    try {
      await ApiService.post('/auth/logout');
    } catch (_) {}
    _user = null;
    await clearToken();
    notifyListeners();
  }
}
