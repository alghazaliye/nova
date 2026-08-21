import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'providers/theme_provider.dart';
import 'package:flutter/foundation.dart';
import 'utils/nova_ui.dart';
import 'screens/phone_screen.dart';
import 'screens/otp_screen.dart';
import 'screens/chats_screen.dart';
import 'screens/chat_screen.dart';
import 'services/api_service.dart';
import 'models/user_model.dart';
import 'offline/network_detector.dart';
import 'offline/outbox_service.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  // Offline-First bootstrap: مراقبة الشبكة ومزامنة الطابور عند العودة
  final detector = NetworkDetector.instance;
  OutboxService.start(detector);
  runApp(const NovaApp());
}

class NovaApp extends StatelessWidget {
  const NovaApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
      ],
      child: Consumer<ThemeProvider>(
        builder: (context, theme, _) {
          final baseTheme = buildNovaTheme(Brightness.light, NovaColors.light);
          final darkTheme = buildNovaTheme(Brightness.dark, NovaColors.dark);
          return MaterialApp(
        title: 'NOVA Messenger',
        debugShowCheckedModeBanner: false,
        theme: baseTheme,
        darkTheme: darkTheme,
        themeMode: theme.isDark ? ThemeMode.dark : ThemeMode.light,
        // RTL إجباري: المحادثات أولًا يمين، الإعدادات آخر يسار (مثل واتساب)
        builder: (context, child) => Directionality(
          textDirection: TextDirection.rtl,
          child: _WebFrame(child: child ?? const SizedBox.shrink()),
        ),
        home: const AppRouter(),
          );
        },
      ),
    );
  }
}

/// إطار الهاتف العريض في الويب: خلفية داكنة عريضة مع منطقة 480px في المنتصف
class _WebFrame extends StatelessWidget {
  final Widget child;
  const _WebFrame({required this.child});

  @override
  Widget build(BuildContext context) {
    if (!kIsWeb) return child;
    final theme = Provider.of<ThemeProvider>(context);
    final isDark = theme.isDark;
    final c = isDark ? NovaColors.dark : NovaColors.light;
    
    // حل مشكلة المساحة البيضاء: نستخدم MediaQuery للتأكد من أن الحاوية تملأ الارتفاع المتاح
    // ونستخدم LayoutBuilder للتعامل مع تغيرات الحجم عند ظهور لوحة المفاتيح
    return Container(
      color: isDark ? const Color(0xFF020408) : const Color(0xFFF0F2F5),
      child: Center(
        child: Container(
          width: 480,
          height: double.infinity,
          decoration: BoxDecoration(
            color: c.bg,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(isDark ? 0.6 : 0.08),
                blurRadius: isDark ? 40 : 20,
              ),
            ],
          ),
          child: ClipRect(child: child),
        ),
      ),
    );
  }
}

/// توجيه البداية حسب حالة الجلسة
class AppRouter extends StatefulWidget {
  const AppRouter({super.key});

  @override
  State<AppRouter> createState() => _AppRouterState();
}

class _AppRouterState extends State<AppRouter> {
  Widget? _target;
  bool _checked = false;

  @override
  void initState() {
    super.initState();
    context.read<AuthProvider>().addListener(_onAuthChanged);
    _init();
  }

  @override
  void dispose() {
    context.read<AuthProvider>().removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    // إعادة توجيه فورية عند تسجيل خروج/دخول (بدون إعادة تحميل)
    final token = AuthProvider.currentToken;
    if (_checked) {
      // لا نُرجع إلى شاشة الدخول إذا كان المستخدم داخل شاشة OTP (رمز خاطئ أو انتهاء صلاحية):
      // أي تغيير في حالة المصادقة (مثل حفظ رسالة خطأ) كان يُعيد التطبيق فورًا إلى صفحة الدخول
      // ويُسقط شاشة التحقق — وهو سبب "الرمز لا يعمل ولا يدخل التطبيق"
      if (token == null && _target is! PhoneScreen && _target is! OtpScreen) {
        if (mounted) setState(() { _target = const PhoneScreen(); });
      } else if (token != null && _target is PhoneScreen) {
        if (mounted) setState(() { _target = const ChatsScreen(); });
      }
    }
  }

  Future<void> _init() async {
    final token = await AuthProvider.loadToken();
    // إذا كان هناك سبب خروج إجباري (حظر) — نتجاهل التوكن المحفوظ وننتقل للدخول
    final auth = context.read<AuthProvider>();
    if (auth.forcedLogoutReason != null) {
      await AuthProvider.clearToken();
      if (mounted) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text(auth.forcedLogoutReason ?? ''),
              duration: const Duration(seconds: 6),
              backgroundColor: Colors.redAccent,
            ));
          }
        });
      }
    }
    Widget target;
    // قفزة تلقائية إلى OTP عند وجود ?phone= في رابط الويب
    if (kIsWeb) {
      final uri = Uri.parse(Uri.base.toString());
      final params = uri.queryParameters;
      // دعم ?token=<jwt> لاختبار الدخول المباشر عبر الرابط (ويب فقط)
      final directToken = params['token'];
      if (directToken != null && directToken.trim().length > 20) {
        await AuthProvider.saveToken(directToken.trim());
        ApiService.userId = int.tryParse(params['user_id'] ?? '') ?? 0;
        if (mounted) {
          // فتح مباشر لمحادثة معينة عبر &chat=<id> لأغراض الاختبار
          final chatId = params['chat'];
          if (chatId != null) {
            _target = _ChatByIdLoader(id: int.tryParse(chatId) ?? 0);
            _onAuthChanged();
          } else {
            _target = const ChatsScreen();
            _onAuthChanged();
          }
          _checked = true;
          setState(() {});
        }
        return;
      }
      // دعم التحقق عبر البريد: ?email=<email>&otp=<code>&otp_expires=<iso>
      final e = params['email'];
      final otp = params['otp'];
      if (e != null && otp != null && otp.length >= 4 && e.contains('@')) {
        DateTime? otpExp;
        final otpExpRaw = uri.queryParameters['otp_expires'];
        if (otpExpRaw != null) {
          try {
            otpExp = DateTime.parse(otpExpRaw).toUtc();
          } catch (_) {}
        }
        // إن كان رمزًا ممتلئًا (6 أرقام) نجرّب التحقق تلقائيًا
        if (otp.length == 6) {
          final ok = await context.read<AuthProvider>().verifyEmailOtp(e, otp);
          if (!ok || !mounted) {
            target = OtpScreen(phone: '', email: e, autoOtp: otp, otpExpiresAt: otpExp);
          } else {
            target = const ChatsScreen();
          }
        } else {
          target = OtpScreen(phone: '', email: e, autoOtp: otp, otpExpiresAt: otpExp);
        }
        if (mounted) setState(() { _target = target; _checked = true; });
        return;
      }
      final p = params['phone'];
      if (p != null && p.length >= 7 && otp != null && otp.length >= 4) {
        final chatId = uri.queryParameters['chat'];
        final cid = chatId != null ? int.tryParse(chatId) : null;
        // صلاحية الرمز (اختياري) — للازم عدّاد تنازلي في شاشة التحقق
        DateTime? otpExp;
        final otpExpRaw = uri.queryParameters['otp_expires'];
        if (otpExpRaw != null) {
          try {
            // نحفظ التاريخ كـ UTC دائمًا (يُعتمد على مقارنة الفروق لا التوقيت المحلي)
            otpExp = DateTime.parse(otpExpRaw).toUtc();
          } catch (_) {}
        }
        if (cid != null && cid > 0) {
          // تسجيل دخول تلقائي ثم فتح المحادثة مباشرة عبر ?chat=<id>
          final ok = await context.read<AuthProvider>().verifyOtp(p, otp);
          if (!ok || !mounted) {
            target = OtpScreen(phone: p, isRegister: false, autoOtp: otp, otpExpiresAt: otpExp);
          } else {
            target = _ChatByIdLoader(id: cid);
          }
        } else {
          target = OtpScreen(phone: p, isRegister: false, autoOtp: otp, otpExpiresAt: otpExp);
        }
        if (mounted) {
          setState(() { _target = target; _checked = true; });
        }
        return;
      }
    }
    if (token != null) {
      final ok = await context.read<AuthProvider>().fetchMe();
      if (!ok) {
        target = const PhoneScreen();
      } else if (kIsWeb) {
        // فتح مباشر لمحادثة معينة عبر ?chat=<id> لأغراض الاختبار
        final chatId = Uri.parse(Uri.base.toString()).queryParameters['chat'];
        if (chatId != null) {
          target = _ChatByIdLoader(id: int.tryParse(chatId) ?? 0);
          _onAuthChanged();
        } else {
          target = const ChatsScreen();
        }
      } else {
        target = const ChatsScreen();
      }
      _onAuthChanged();
      if (mounted) setState(() {}); // ضمان بناء الهدف المحدد في مسار الويب
    } else {
      target = const PhoneScreen();
    }
    if (mounted) {
      setState(() {
        _target = target;
        _checked = true;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_checked) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    return _target!;
  }
}

/// يحمّل محادثة محددة بمعرفها من API ويفتحها مباشرة (للاختبار السريع عبر ?chat=<id>)
class _ChatByIdLoader extends StatefulWidget {
  final int id;
  const _ChatByIdLoader({required this.id});

  @override
  State<_ChatByIdLoader> createState() => _ChatByIdLoaderState();
}

class _ChatByIdLoaderState extends State<_ChatByIdLoader> {
  @override
  void initState() {
    super.initState();
    _open();
  }

  Future<void> _open() async {
    try {
      final res = await ApiService.get('/conversations');
      final list = (res['data'] is List ? res['data'] as List : <dynamic>[]);
      for (final e in list) {
        final m = Map<String, dynamic>.from(e as Map);
        if ((m['id']?.toString() ?? '') == widget.id.toString()) {
          final conv = Conversation.fromJson(m);
          if (mounted) {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => ChatScreen(conv: conv)),
            );
          }
          return;
        }
      }
      if (mounted) {
        // لم تُوجد المحادثة — الانتقال للقائمة الرئيسية
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const ChatsScreen()),
        );
      }
    } catch (_) {
      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const ChatsScreen()),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: CircularProgressIndicator()),
    );
  }
}
