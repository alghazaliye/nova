
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl_phone_field/intl_phone_field.dart';
import '../providers/auth_provider.dart';
import '../utils/nova_ui.dart';
import '../utils/nova_web_state.dart';
import 'otp_screen.dart';
import 'chats_screen.dart';

/// شاشة تسجيل الدخول الديناميكية — تعرض طرق الدخول المتاحة حسب
/// GET /auth/config من لوحة الإدارة (هاتف / بريد / اسم مستخدم).
class PhoneScreen extends StatefulWidget {
  const PhoneScreen({super.key});

  @override
  State<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends State<PhoneScreen> with SingleTickerProviderStateMixin {
  String _phone = '';
  String? _autoPhone;
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  late TabController _tabController;
  AuthConfig? _config;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AuthProvider>().fetchAuthConfig();
    });
    if (kIsWeb) {
      final href = novaHref();
      debugPrint('[NovaWeb] href: $href');
      final uri = Uri.parse(href);
      final p = uri.queryParameters['phone'];
      if (p != null && p.length >= 7) {
        _autoPhone = p;
        setNovaState('phone_param=$p');
        final otpAuto = uri.queryParameters['otp'];
        WidgetsBinding.instance.addPostFrameCallback((_) {
          setNovaState('postframe_filling');
          _fillAuto();
          if (mounted && otpAuto != null && otpAuto.length >= 4) {
            setNovaState('auto_jump_otp');
            Navigator.pushReplacement(
                context,
                MaterialPageRoute(
                    builder: (_) => OtpScreen(phone: _phone, isRegister: false)));
          }
        });
      } else {
        setNovaState('no_phone_param');
      }
    }
  }

  void _fillAuto() {
    if (_autoPhone != null && mounted) {
      _phone = _autoPhone!;
      var raw = _autoPhone!;
      if (raw.startsWith('+966')) raw = raw.substring(4);
      _phoneController.text = raw;
      _phoneController.selection = TextSelection.fromPosition(
          TextPosition(offset: _autoPhone!.length));
      Future.delayed(const Duration(seconds: 1), () async {
        if (!mounted) return;
        try {
          final auth = context.read<AuthProvider>();
          final ok = await auth.login(_phone);
          setNovaState('login_done=$ok');
          if (ok && mounted) {
            Navigator.push(context,
                MaterialPageRoute(builder: (_) => OtpScreen(phone: _phone, isRegister: false)));
          }
        } catch (e) {
          setNovaState('login_error=$e');
        }
      });
    }
  }

  /// عدد التبويبات المفعّلة (هاتف/بريد/اسم مستخدم)
  int get _enabledTabs {
    var n = 0;
    if (_config?.phoneLogin ?? true) n++;
    if (_config?.emailLogin ?? true) n++;
    if (_config?.usernameLogin ?? true) n++;
    return n;
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _emailController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _tabController.dispose();
    super.dispose();
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  Future<void> _doPhoneLogin() async {
    if (_phone.length < 6) {
      _showError('أدخل رقمًا صحيحًا');
      return;
    }
    final auth = context.read<AuthProvider>();
    final ok = await auth.login(_phone);
    if (ok && context.mounted) _handleLoginResult(auth);
  }

  Future<void> _doEmailLogin() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    if (email.isEmpty || !email.contains('@') || password.isEmpty) {
      _showError('أدخل البريد وكلمة المرور بشكل صحيح');
      return;
    }
    final auth = context.read<AuthProvider>();
    final ok = await auth.loginEmail(email, password);
    if (ok && context.mounted) _handleLoginResult(auth);
  }

  Future<void> _doUsernameLogin() async {
    final username = _usernameController.text.trim();
    final password = _passwordController.text;
    if (username.isEmpty || password.isEmpty) {
      _showError('أدخل اسم المستخدم وكلمة المرور');
      return;
    }
    final auth = context.read<AuthProvider>();
    final ok = await auth.loginUsername(username, password);
    if (ok && context.mounted) _handleLoginResult(auth);
  }

  void _handleLoginResult(AuthProvider auth) {
    if (auth.lastLoginBypass) {
      Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const ChatsScreen()),
          (route) => false);
    } else if (_tabController.index == 0) {
      // هاتف: OTP
      Navigator.push(context,
          MaterialPageRoute(builder: (_) => OtpScreen(phone: _phone, isRegister: false)));
    }
    // البريد واسم المستخدم لا يحتاجان OTP
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    _config ??= auth.authConfig;
    // تحديد التبويبات المفعّلة من الإعدادات
    final tabs = <Widget>[];
    final phoneEnabled = _config?.phoneLogin ?? true;
    final emailEnabled = _config?.emailLogin ?? true;
    final usernameEnabled = _config?.usernameLogin ?? true;
    if (phoneEnabled) tabs.add(const Tab(text: 'هاتف'));
    if (emailEnabled) tabs.add(const Tab(text: 'بريد'));
    if (usernameEnabled) tabs.add(const Tab(text: 'اسم مستخدم'));

    Widget content;
    if (!(_config?.loginEnabled ?? true)) {
      content = const Padding(
        padding: EdgeInsets.symmetric(vertical: 40),
        child: Center(child: Text('خدمة تسجيل الدخول متوقفة مؤقتًا من لوحة الإدارة',
            textAlign: TextAlign.center, style: TextStyle(fontSize: 15))),
      );
    } else if (tabs.length == 1) {
      // طريقة واحدة فقط: نموذج مباشر بدون تبويبات
      content = _buildFormFor(tabs.length == 1 && phoneEnabled
          ? 0
          : (tabs.length == 1 && emailEnabled
              ? 1
              : 2),
          c);
    } else {
      content = Column(
        children: [
          Container(
            decoration: BoxDecoration(
              color: c.surface2,
              borderRadius: BorderRadius.circular(12),
            ),
            child: TabBar(
              controller: _tabController,
              onTap: (_) => setState(() {}),
              dividerColor: Colors.transparent,
              indicator: BoxDecoration(
                color: c.accent,
                borderRadius: BorderRadius.circular(10),
              ),
              labelColor: Colors.white,
              unselectedLabelColor: c.muted,
              tabs: tabs,
            ),
          ),
          const SizedBox(height: 24),
          Expanded(child: _buildFormFor(_tabController.index, c)),
        ],
      );
    }

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 32),
              Center(
                child: Column(
                  children: [
                    Container(
                      width: 96,
                      height: 96,
                      decoration: BoxDecoration(
                        color: c.accent,
                        borderRadius: BorderRadius.circular(28),
                      ),
                      child: const Icon(Icons.chat_bubble,
                          color: Colors.white, size: 52),
                    ),
                    const SizedBox(height: 20),
                    Text('NOVA Messenger',
                        style: TextStyle(
                            fontSize: 28,
                            fontWeight: FontWeight.w800,
                            fontFamily: 'Cairo',
                            color: c.text)),
                    const SizedBox(height: 8),
                    Text('سجّل دخولك للمتابعة', style: TextStyle(fontSize: 15, color: c.muted)),
                  ],
                ),
              ),
              const SizedBox(height: 28),
              if (tabs.length > 1) content else Expanded(child: content),
              const SizedBox(height: 16),
              if (auth.loading)
                const Center(child: CircularProgressIndicator())
              else ...[
                if (auth.error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Text(auth.error!,
                        style: TextStyle(color: Colors.redAccent, fontSize: 13),
                        textAlign: TextAlign.center),
                  ),
              ],
              const SizedBox(height: 12),
              Expanded(
                child: Align(
                  alignment: Alignment.bottomCenter,
                  child: Text(
                    'الحسابات التجريبية: +966599995001، +966599995002، +966599991001\nرمز التحقق التجريبي: 123456',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 12, color: c.muted),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// بناء نموذج الدخول حسب التبويب المفعّل (0=هاتف، 1=بريد، 2=اسم مستخدم)
  Widget _buildFormFor(int tabIndex, NovaColors c) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (tabIndex == 0) ...[
          IntlPhoneField(
            controller: _phoneController,
            decoration: InputDecoration(
              labelText: 'رقم الهاتف',
              filled: true,
              fillColor: c.surface2,
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.line)),
              focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.accent)),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            ),
            initialCountryCode: 'SA',
            languageCode: 'ar',
            disableLengthCheck: true,
            autovalidateMode: AutovalidateMode.disabled,
            onChanged: (p) => _phone = p.completeNumber,
          ),
          const SizedBox(height: 20),
          _primaryButton('التالي', c, _doPhoneLogin),
        ] else if (tabIndex == 1) ...[
          TextField(
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(
              labelText: 'البريد الإلكتروني',
              filled: true,
              fillColor: c.surface2,
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.line)),
              focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.accent)),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _passwordController,
            obscureText: true,
            decoration: InputDecoration(
              labelText: 'كلمة المرور',
              filled: true,
              fillColor: c.surface2,
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.line)),
              focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.accent)),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            ),
          ),
          const SizedBox(height: 20),
          _primaryButton('دخول', c, _doEmailLogin),
        ] else ...[
          TextField(
            controller: _usernameController,
            keyboardType: TextInputType.text,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: 'اسم المستخدم',
              filled: true,
              fillColor: c.surface2,
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.line)),
              focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.accent)),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _passwordController,
            obscureText: true,
            decoration: InputDecoration(
              labelText: 'كلمة المرور',
              filled: true,
              fillColor: c.surface2,
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.line)),
              focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: c.accent)),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            ),
          ),
          const SizedBox(height: 20),
          _primaryButton('دخول', c, _doUsernameLogin),
        ],
      ],
    );
  }

  Widget _primaryButton(String label, NovaColors c, VoidCallback onPressed) {
    return ElevatedButton(
      style: ElevatedButton.styleFrom(
        backgroundColor: c.accent,
        foregroundColor: Colors.white,
        elevation: 0,
        padding: const EdgeInsets.symmetric(vertical: 16),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      onPressed: onPressed,
      child: Text(label, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
    );
  }
}
