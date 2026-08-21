import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../utils/nova_web_state.dart';

/// إعدادات طرق المصادقة (من GET /auth/config) — تحكم ديناميكي في شاشات
/// التسجيل والدخول (هاتف/بريد/اسم مستخدم ON/OFF من لوحة الإدارة).
class AuthConfig {
  final bool phoneRegistration;
  final bool emailRegistration;
  final bool phoneLogin;
  final bool emailLogin;
  final bool usernameLogin;
  final bool phoneOtpEnabled;
  final bool emailOtpEnabled;

  const AuthConfig({
    this.phoneRegistration = true,
    this.emailRegistration = true,
    this.phoneLogin = true,
    this.emailLogin = true,
    this.usernameLogin = true,
    this.phoneOtpEnabled = true,
    this.emailOtpEnabled = true,
    this.timezone = 'Asia/Riyadh',
  });

  /// المنطقة الزمنية المعتمدة في إعدادات لوحة التحكم (مثل Asia/Riyadh).
  final String timezone;

  factory AuthConfig.fromJson(Map<String, dynamic> j) {
    final reg = Map<String, dynamic>.from(j['registration'] ?? {});
    final login = Map<String, dynamic>.from(j['login'] ?? {});
    final otp = Map<String, dynamic>.from(j['otp'] ?? {});
    return AuthConfig(
      phoneRegistration: reg['phone'] != false,
      emailRegistration: reg['email'] != false,
      phoneLogin: login['phone'] != false,
      emailLogin: login['email'] != false,
      usernameLogin: login['username'] != false,
      phoneOtpEnabled:
          Map<String, dynamic>.from(otp['phone'] ?? {})['enabled'] != false,
      emailOtpEnabled:
          Map<String, dynamic>.from(otp['email'] ?? {})['enabled'] != false,
      timezone: (j['timezone'] ?? 'Asia/Riyadh').toString(),
    );
  }

  /// إزاحة المنطقة الزمنية عن UTC بالدقائق (مع دعم نصف الساعات مثل طهران).
  int get utcOffsetMinutes {
    if (timezone == 'UTC') return 0;
    // خريطة صريحة للإزاحات الشائعة (ساعات كاملة ونصف ساعة)
    const offsets = <String, int>{
      'Africa/Cairo': 120, 'Africa/Tripoli': 120, 'Africa/Tunis': 60,
      'Africa/Algiers': 60, 'Africa/Casablanca': 60, 'Africa/Lagos': 60,
      'Africa/Johannesburg': 120, 'Africa/Khartoum': 120,
      'Asia/Riyadh': 180, 'Asia/Kuwait': 180, 'Asia/Qatar': 180,
      'Asia/Bahrain': 180, 'Asia/Muscat': 240, 'Asia/Dubai': 240,
      'Asia/Baghdad': 180, 'Asia/Amman': 180, 'Asia/Damascus': 180,
      'Asia/Beirut': 180, 'Asia/Jerusalem': 180, 'Asia/Aden': 180,
      'Asia/Tehran': 210, 'Asia/Kabul': 270, 'Asia/Karachi': 300,
      'Asia/Kolkata': 330, 'Asia/Dhaka': 360, 'Asia/Bangkok': 420,
      'Asia/Jakarta': 420, 'Asia/Shanghai': 480, 'Asia/Tokyo': 540,
      'Asia/Seoul': 540, 'Europe/Istanbul': 180, 'Europe/London': 0,
      'Europe/Paris': 120, 'Europe/Berlin': 120, 'Europe/Moscow': 180,
      'Australia/Sydney': 600, 'Pacific/Auckland': 720,
      'America/New_York': -240, 'America/Los_Angeles': -420,
      'America/Chicago': -300, 'America/Sao_Paulo': -180,
    };
    return offsets[timezone] ?? 180;
  }

  /// هل أي طريقة تسجيل متاحة؟
  bool get registrationEnabled => phoneRegistration || emailRegistration;

  /// هل أي طريقة دخول متاحة؟
  bool get loginEnabled => phoneLogin || emailLogin || usernameLogin;
}

/// إعدادات التطبيق العامة (من GET /settings)
class AppSettings {
  final bool allowCalls;
  final bool allowGroups;
  final bool allowStories;
  final bool allowRegistration;
  final bool maintenanceMode;
  final String appName;
  final int maxFileSizeMb;
  final int maxImageSizeMb;
  final int maxVideoSizeMb;
  final int storyDurationHrs;
  final bool fcmEnabled;

  const AppSettings({
    required this.allowCalls,
    required this.allowGroups,
    required this.allowStories,
    required this.allowRegistration,
    required this.maintenanceMode,
    required this.appName,
    required this.maxFileSizeMb,
    required this.maxImageSizeMb,
    required this.maxVideoSizeMb,
    required this.storyDurationHrs,
    required this.fcmEnabled,
  });

  factory AppSettings.fromJson(Map<String, dynamic> j) => AppSettings(
        allowCalls: j['allow_calls'] == true,
        allowGroups: j['allow_groups'] == true,
        allowStories: j['allow_stories'] == true,
        allowRegistration: j['allow_registration'] == true,
        maintenanceMode: j['maintenance_mode'] == true,
        appName: (j['app_name'] ?? 'NOVA Messenger').toString(),
        maxFileSizeMb: (j['max_file_size_mb'] ?? 50) as int,
        maxImageSizeMb: (j['max_image_size_mb'] ?? 10) as int,
        maxVideoSizeMb: (j['max_video_size_mb'] ?? 100) as int,
        storyDurationHrs: (j['story_duration_hrs'] ?? 24) as int,
        fcmEnabled: j['fcm_enabled'] == true,
      );
}

/// مزود حالة المصادقة والمستخدم الحالي
class AuthProvider extends ChangeNotifier {
  NovaUser? _user;
  AppSettings? _appSettings;
  AuthConfig? _authConfig;
  bool _loading = false;
  String? _error;

  /// إعدادات المصادقة الديناميكية (طرق التسجيل/الدخول من لوحة الإدارة)
  AuthConfig? get authConfig => _authConfig;

  /// المنطقة الزمنية المعتمدة حاليًا (من إعدادات لوحة التحكم أو الافتراضية).
  String get timezone => _authConfig?.timezone ?? 'Asia/Riyadh';

  /// إزاحة المنطقة الزمنية المعتمدة عن UTC بالدقائق.
  int get timezoneOffsetMinutes => _authConfig?.utcOffsetMinutes ?? 180;

  /// عرض رسالة خطأ في الواجهة (مثال: رمز غير مكتمل) بدون اتصال بالخادم
  void showError(String message) {
    _error = message;
    notifyListeners();
  }

  /// GET /auth/config
  Future<void> fetchAuthConfig() async {
    try {
      final res = await ApiService.get('/auth/config');
      if (res['success'] == true && res['data'] != null) {
        _authConfig = AuthConfig.fromJson(Map<String, dynamic>.from(res['data']));
        notifyListeners();
      }
    } catch (_) {}
  }

  NovaUser? get user => _user;

  /// إعدادات التطبيق العامة (المكالمات/المجموعات/الحالات من لوحة التحكم)
  AppSettings? get appSettings => _appSettings;

  /// إعدادات افتراضية عند عدم توفر استجابة الخادم.
  static AppSettings get defaultAppSettings => const AppSettings(
        allowCalls: true,
        allowGroups: true,
        allowStories: true,
        allowRegistration: true,
        maintenanceMode: false,
        appName: 'NOVA Messenger',
        maxFileSizeMb: 50,
        maxImageSizeMb: 10,
        maxVideoSizeMb: 100,
        storyDurationHrs: 24,
        fcmEnabled: false,
      );

  /// إعدادات فعّالة (افتراضية إذا كانت null).
  AppSettings get effectiveAppSettings => _appSettings ?? defaultAppSettings;

  /// GET /settings — جلب إعدادات التطبيق العامة
  Future<void> fetchAppSettings() async {
    try {
      if (ApiService.token == null) return;
      final res = await ApiService.get('/settings');
      if (res['success'] == true && res['data'] != null) {
        _appSettings = AppSettings.fromJson(Map<String, dynamic>.from(res['data']));
        notifyListeners();
      }
    } catch (_) {}
  }

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
  static Future<void> saveToken(String token, {int? userId}) async {
    ApiService.token = token;
    if (userId != null) ApiService.userId = userId;
    if (kIsWeb) setNovaStateToken('Bearer $token');
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('token', token);
    if (userId != null) await prefs.setInt('user_id', userId);
  }

  static Future<String?> loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');
    if (token != null) ApiService.token = token;
    ApiService.userId = prefs.getInt('user_id') ?? 0;
    if (kIsWeb) setNovaStateToken(token != null ? 'Bearer $token' : null);
    return token;
  }

  static Future<void> clearToken() async {
    ApiService.token = null;
    if (kIsWeb) setNovaStateToken(null);
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

  /// POST /auth/register-email — إنشاء حساب بالبريد (يُرسل OTP بريد)
  Future<bool> registerEmail(String email, {String? name}) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/register-email', body: {
      'email': email,
      'name': name ?? email,
      'phone': '',
      'device_uuid': await getDeviceFingerprint(),
      ...deviceInfo,
    });
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'فشل في التسجيل بالبريد';
      notifyListeners();
      return false;
    }
    notifyListeners();
    return true;
  }

  /// POST /auth/verify-email-otp — تفعيل الحساب بالبريد (يُعيد التوكن)
  Future<bool> verifyEmailOtp(String email, String otp) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/verify-email-otp', body: {
      'email': email,
      'otp': otp,
      'device_uuid': await getDeviceFingerprint(),
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
      await saveToken(token, userId: _user?.id);
      registerCurrentDevice();
      await fetchAppSettings();
    }
    notifyListeners();
    return true;
  }

  /// POST /auth/resend-email-otp — إعادة إرسال رمز البريد
  Future<bool> resendEmailOtp(String email) async {
    try {
      final res = await ApiService.post('/auth/resend-email-otp', body: {'email': email});
      return res['success'] == true;
    } catch (_) {
      return false;
    }
  }

  /// POST /auth/login-email — دخول بالبريد + كلمة المرور
  Future<bool> loginEmail(String email, String password) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/login-email', body: {
      'email': email,
      'password': password,
      'device_uuid': await getDeviceFingerprint(),
      ...deviceInfo,
    });
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'البريد أو كلمة المرور غير صحيحة';
      notifyListeners();
      return false;
    }
    final data = res['data'] as Map<String, dynamic>? ?? {};
    final token = data['token'] as String?;
    if (token != null) {
      _user = NovaUser.fromJson(Map<String, dynamic>.from(data['user'] ?? {}));
      await saveToken(token, userId: _user?.id);
      registerCurrentDevice();
      await fetchAppSettings();
    }
    notifyListeners();
    return true;
  }

  /// POST /auth/login-username — دخول باسم المستخدم + كلمة المرور
  Future<bool> loginUsername(String username, String password) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/login-username', body: {
      'username': username,
      'password': password,
      'device_uuid': await getDeviceFingerprint(),
      ...deviceInfo,
    });
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'اسم المستخدم أو كلمة المرور غير صحيحة';
      notifyListeners();
      return false;
    }
    final data = res['data'] as Map<String, dynamic>? ?? {};
    final token = data['token'] as String?;
    if (token != null) {
      _user = NovaUser.fromJson(Map<String, dynamic>.from(data['user'] ?? {}));
      await saveToken(token, userId: _user?.id);
      registerCurrentDevice();
      await fetchAppSettings();
    }
    notifyListeners();
    return true;
  }

  /// POST /auth/set-password — تعيين أو تغيير كلمة المرور (بعد ربط البريد)
  Future<bool> setPassword(String password) async {
    _loading = true;
    _error = null;
    notifyListeners();
    final res = await ApiService.post('/auth/set-password', body: {'password': password});
    _loading = false;
    if (res['success'] != true) {
      _error = res['message'] ?? 'فشل في تعيين كلمة المرور';
      notifyListeners();
      return false;
    }
    notifyListeners();
    return true;
  }

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
    _otpExpiresAt = _parseOtpExpiry(res['data'] as Map?);
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
    // Security-masked response for unregistered numbers: the server
    // returns success=true with an empty data block (no cooldown /
    // expires_at / otp_bypass) so the caller can show a clear
    // message instead of opening the OTP screen silently.
    _lastLoginUnregistered = data['cooldown'] == null &&
        data['expires_at'] == null &&
        data['otp_bypass'] != true &&
        data['delivery_mode'] == null;
    final bypass = data['otp_bypass'] == true;
    _lastLoginBypass = bypass;
    if (bypass) {
      final token = data['token'] as String?;
      if (token != null) {
        _user = NovaUser.fromJson(Map<String, dynamic>.from(data['user'] ?? {}));
        await saveToken(token, userId: _user?.id);
        registerCurrentDevice();
        await fetchAppSettings();
      }
    }
    _otpExpiresAt = _parseOtpExpiry(res['data'] as Map?);
    notifyListeners();
    return true;
  }

  /// هل عاد الدخول بدون OTP (التحقق معطّل من لوحة التحكم)
  bool _lastLoginBypass = false;
  bool get lastLoginBypass => _lastLoginBypass;

  /// هل الرقم غير مسجل (خادم أرسل success بدون بيانات OTP)؟
  bool _lastLoginUnregistered = false;
  bool get lastLoginUnregistered => _lastLoginUnregistered;

  /// صلاحية الرمز النشط (للعرض في شاشة التحقق مع عدّاد تنازلي)
  DateTime? _otpExpiresAt;
  DateTime? get otpExpiresAt => _otpExpiresAt;

  DateTime? _parseOtpExpiry(Map? data) {
    final raw = data?['expires_at'];
    if (raw == null) return null;
    try {
      final str = '$raw';
      // الخادم يخزن expires_at UTC صريحًا (gmdate) ويُرجعها بدون timezone
      // (مثل: 2026-08-20 22:54:53). لذلك: إذا احتوت Z نعتمدها مباشرة،
      // وإلا نعامل السلسلة نفسها كتوقيت UTC ولا نلتبس بالتوقيت المحلي
      // للجهاز — وإلا سيظهر الرمز «منتهيًا» فور فتح الشاشة على الأجهزة
      // التي تسبق UTC (مثل GMT+3: الساعة المحلية 01:50 > 22:54)
      final dt = str.endsWith('Z')
          ? DateTime.parse(str).toUtc()
          : DateTime.parse(str).add(DateTime.now().timeZoneOffset).toUtc();
      return dt;
    } catch (_) {
      return null;
    }
  }

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
      await saveToken(token, userId: _user?.id);
      // تسجيل الجهاز وجلب إعدادات التطبيق لا يجب أن يؤخّر الدخول:
      // الطلبان بطيئان (حتى ~25 ثانية) وكانت بطأتهما تدفع المستخدم
      // للضغط المزدوج على «تحقق» (فيظهر خطأ 400 رمز منتهٍ)
      registerCurrentDevice().catchError((_) {});
      fetchAppSettings().catchError((_) {});
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
    await fetchAppSettings();
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
      ApiService.userId = _user?.id ?? 0;
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
    _appSettings = null;
    await clearToken();
    notifyListeners();
  }

  /// تحديث بيانات الملف الشخصي (الاسم، البريد، إلخ)
  Future<bool> updateProfile({String? name, String? email, String? username, String? bio}) async {
    _loading = true;
    _error = null;
    notifyListeners();

    final body = <String, dynamic>{};
    if (name != null) body['name'] = name;
    if (email != null) body['email'] = email;
    if (username != null) body['username'] = username;
    if (bio != null) body['bio'] = bio;

    final res = await ApiService.put('/users/me', body: body);
    _loading = false;
    
    if (res['success'] == true) {
      if (res['data'] != null && res['data'] is Map) {
        _user = NovaUser.fromJson(Map<String, dynamic>.from(res['data']));
      } else {
        await fetchMe();
      }
      notifyListeners();
      return true;
    } else {
      _error = res['message'] ?? 'فشل تحديث الملف الشخصي';
      notifyListeners();
      return false;
    }
  }

  /// رفع صورة شخصية جديدة
  Future<bool> uploadAvatar(List<int> bytes, String fileName) async {
    _loading = true;
    _error = null;
    notifyListeners();

    try {
      final file = http.MultipartFile.fromBytes('avatar', bytes, filename: fileName);
      final res = await ApiService.uploadMultipart('/users/me/avatar', [file]);
      _loading = false;
      
      if (res['success'] == true) {
        if (res['data'] != null && res['data'] is Map) {
           _user = NovaUser.fromJson(Map<String, dynamic>.from(res['data']));
        } else {
           await fetchMe();
        }
        notifyListeners();
        return true;
      } else {
        _error = res['message'] ?? 'فشل رفع الصورة';
        notifyListeners();
        return false;
      }
    } catch (e) {
      _loading = false;
      _error = 'خطأ في الاتصال أثناء رفع الصورة';
      notifyListeners();
      return false;
    }
  }
}
