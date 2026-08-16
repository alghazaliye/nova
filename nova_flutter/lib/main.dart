import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'package:flutter/foundation.dart';
import 'utils/nova_ui.dart';
import 'screens/phone_screen.dart';
import 'screens/otp_screen.dart';
import 'screens/chats_screen.dart';
import 'screens/chat_screen.dart';
import 'services/api_service.dart';
import 'models/user_model.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const NovaApp());
}

class NovaApp extends StatelessWidget {
  const NovaApp({super.key});

  @override
  Widget build(BuildContext context) {
    final baseTheme = buildNovaTheme(Brightness.light, NovaColors.light);
    return ChangeNotifierProvider(
      create: (_) => AuthProvider(),
      child: MaterialApp(
        title: 'NOVA Messenger',
        debugShowCheckedModeBanner: false,
        theme: baseTheme,
        // RTL إجباري: المحادثات أولًا يمين، الإعدادات آخر يسار (مثل واتساب)
        builder: (context, child) => Directionality(
          textDirection: TextDirection.rtl,
          child: _WebFrame(child: child ?? const SizedBox.shrink()),
        ),
        home: const AppRouter(),
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
    return Container(
      color: const Color(0xFF0B0F1A),
      child: Center(
        child: Container(
          width: 480,
          decoration: BoxDecoration(
            color: NovaColors.dark.surface,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.6),
                blurRadius: 40,
              ),
            ],
          ),
          child: child,
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
      if (token == null && _target is! PhoneScreen) {
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
      final p = uri.queryParameters['phone'];
      final otp = uri.queryParameters['otp'];
      if (p != null && p.length >= 7 && otp != null && otp.length >= 4) {
        final chatId = uri.queryParameters['chat'];
        final cid = chatId != null ? int.tryParse(chatId) : null;
        if (cid != null && cid > 0) {
          // تسجيل دخول تلقائي ثم فتح المحادثة مباشرة عبر ?chat=<id>
          final ok = await context.read<AuthProvider>().verifyOtp(p, otp);
          if (!ok || !mounted) {
            target = OtpScreen(phone: p, isRegister: false, autoOtp: otp);
          } else {
            target = _ChatByIdLoader(id: cid);
          }
        } else {
          target = OtpScreen(phone: p, isRegister: false, autoOtp: otp);
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
