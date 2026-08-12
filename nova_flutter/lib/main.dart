import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'package:flutter/foundation.dart';
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
    return ChangeNotifierProvider(
      create: (_) => AuthProvider(),
      child: MaterialApp(
        title: 'NOVA Messenger',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(
            seedColor: const Color(0xFF2563EB),
            brightness: Brightness.light,
          ),
          fontFamily: 'Cairo',
          useMaterial3: true,
          scaffoldBackgroundColor: const Color(0xFFF8FAFC),
          appBarTheme: const AppBarTheme(
            elevation: 0,
            scrolledUnderElevation: 1,
            centerTitle: true,
          ),
          elevatedButtonTheme: ElevatedButtonThemeData(
            style: ElevatedButton.styleFrom(
              elevation: 2,
              padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 28),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(24),
              ),
            ),
          ),
          floatingActionButtonTheme: FloatingActionButtonThemeData(
            elevation: 4,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(28),
            ),
          ),
        ),
        home: const AppRouter(),
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

// للوصول لشاشة الويب لاحقًا:
// Navigator.push(context, MaterialPageRoute(builder: (_) => const WebLoginScreen()));
