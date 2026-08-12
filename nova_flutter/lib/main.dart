import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'package:flutter/foundation.dart';
import 'utils/nova_ui.dart';
import 'screens/phone_screen.dart';
import 'screens/otp_screen.dart';
import 'screens/chats_screen.dart';

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
    _init();
  }

  Future<void> _init() async {
    final token = await AuthProvider.loadToken();
    Widget target;
    // قفزة تلقائية إلى OTP عند وجود ?phone= في رابط الويب
    if (kIsWeb) {
      final uri = Uri.parse(Uri.base.toString());
      final p = uri.queryParameters['phone'];
      final otp = uri.queryParameters['otp'];
      if (p != null && p.length >= 7 && otp != null && otp.length >= 4) {
        target = OtpScreen(phone: p, isRegister: false);
        if (mounted) {
          setState(() { _target = target; _checked = true; });
        }
        return;
      }
    }
    if (token != null) {
      final ok = await context.read<AuthProvider>().fetchMe();
      target = ok ? const ChatsScreen() : const PhoneScreen();
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
