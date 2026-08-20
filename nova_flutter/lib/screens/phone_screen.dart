
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl_phone_field/intl_phone_field.dart';
import '../providers/auth_provider.dart';
import '../utils/nova_ui.dart';
import '../utils/nova_web_state.dart';
import 'otp_screen.dart';
import 'chats_screen.dart';

/// شاشة الدخول — تعرض أولاً خيارين: **تسجيل الدخول** أو **إنشاء حساب**،
/// ثم تعرض الطرق المتاحة لكل خيار حسب إعدادات auth-settings.php في لوحة الإدارة:
/// - طرق التسجيل: الهاتف (OTP)، البريد (رمز OTP)
/// - طرق الدخول: الهاتف (OTP)، البريد (كلمة مرور)، اسم المستخدم (كلمة مرور)
/// أي طريقة غير مفعّلة لا تظهر إطلاقًا.
class PhoneScreen extends StatefulWidget {
  const PhoneScreen({super.key});

  @override
  State<PhoneScreen> createState() => _PhoneScreenState();
}

/// مرحلة الشاشة: اختيار الوضع (دخول/تسجيل) ثم اختيار الطريقة ثم النموذج
enum _Mode { pickFlow, phone, email, username }

enum _RegisterMethod { phone, email }

enum _LoginMethod { phone, email, username }

class _PhoneScreenState extends State<PhoneScreen> {
  String _phone = '';
  String? _autoPhone;
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _usernameController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  _Mode _mode = _Mode.pickFlow;
  /// طريقة الدخول المختارة عند الدخول (ليست نفس طرق التسجيل)
  _LoginMethod? _loginMethod;
  /// طريقة التسجيل المختارة
  _RegisterMethod? _registerMethod;
  bool _emailSending = false;

  @override
  void initState() {
    super.initState();
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
                MaterialPageRoute(builder: (_) => OtpScreen(phone: _phone, isRegister: false, otpExpiresAt: context.read<AuthProvider>().otpExpiresAt)));
          }
        } catch (e) {
          setNovaState('login_error=$e');
        }
      });
    }
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _emailController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  void _showError(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }

  // ---------------------------------------------------------------------------
  // انتقال بين الوضعيات
  // ---------------------------------------------------------------------------

  /// `isLogin`: true = اختار المستخدم «تسجيل الدخول»، false = «إنشاء حساب».
  /// النتيجة: ننتقل تلقائيًا إلى أول طريقة متاحة ونعرض نموذجها مباشرةً
  /// (بدلًا من تثبيت الوضع على اختيار الطرق الذي يجعل البطاقة لا تستجيب).
  void _pickFlow(bool isLogin) {
    if (isLogin) {
      final methods = _loginMethods;
      if (methods.isEmpty) {
        _showError('لا تتوفر حاليًا أي طريقة للدخول');
        return;
      }
      final method = methods.first;
      _LoginMethod? prev = _loginMethod;
      _loginMethod = method;
      _registerMethod = null;
      _Mode next = _methodToMode(method);
      if (_mode == next && prev == method) {
        // نفس الطريقة السابقة — فقط نحدث الحالة
        setState(() {});
      } else {
        setState(() => _mode = next);
      }
    } else {
      final methods = _registerMethods;
      if (methods.isEmpty) {
        _showError('التسجيل غير متاح حاليًا');
        return;
      }
      final method = methods.first;
      _registerMethod = method;
      _loginMethod = null;
      setState(() => _mode = _methodToModeReg(method));
    }
  }

  /// ربط الطريقة المختارة بوضع الشاشة (الهاتف أو البريد أو اسم المستخدم)
  _Mode _methodToMode(_LoginMethod method) {
    switch (method) {
      case _LoginMethod.phone:
        return _Mode.phone;
      case _LoginMethod.email:
        return _Mode.email;
      case _LoginMethod.username:
        return _Mode.username;
    }
  }

  _Mode _methodToModeReg(_RegisterMethod method) {
    switch (method) {
      case _RegisterMethod.phone:
        return _Mode.phone;
      case _RegisterMethod.email:
        return _Mode.email;
    }
  }

  void _setMethod(_Mode mode) {
    setState(() => _mode = mode);
  }

  // ---------------------------------------------------------------------------
  // طرق التسجيل المتاحة حسب الإعدادات
  // ---------------------------------------------------------------------------

  /// طرق **التسجيل** المتاحة: تسجيل الهاتف (requires OTP)، تسجيل البريد (رمز OTP)
  List<_RegisterMethod> get _registerMethods {
    final cfg = context.read<AuthProvider>().authConfig;
    if (cfg == null) return const [_RegisterMethod.phone, _RegisterMethod.email];
    final methods = <_RegisterMethod>[];
    if (cfg.phoneRegistration) methods.add(_RegisterMethod.phone);
    if (cfg.emailRegistration) methods.add(_RegisterMethod.email);
    return methods;
  }

  /// طرق **الدخول** المتاحة: هاتف OTP، بريد + كلمة مرور، اسم مستخدم + كلمة مرور
  List<_LoginMethod> get _loginMethods {
    final cfg = context.read<AuthProvider>().authConfig;
    if (cfg == null) return const [_LoginMethod.phone, _LoginMethod.email, _LoginMethod.username];
    final methods = <_LoginMethod>[];
    if (cfg.phoneLogin) methods.add(_LoginMethod.phone);
    if (cfg.emailLogin) methods.add(_LoginMethod.email);
    if (cfg.usernameLogin) methods.add(_LoginMethod.username);
    return methods;
  }

  // ---------------------------------------------------------------------------
  // إجراءات الدخول والتسجيل
  // ---------------------------------------------------------------------------

  Future<void> _doPhoneLogin() async {
    if (_phone.length < 6) {
      _showError('أدخل رقمًا صحيحًا');
      return;
    }
    final auth = context.read<AuthProvider>();
    final ok = await auth.login(_phone);
    if (ok && mounted) _handleLoginResult(auth);
  }

  Future<void> _doPhoneRegister() async {
    if (_phone.length < 6) {
      _showError('أدخل رقمًا صحيحًا');
      return;
    }
    final auth = context.read<AuthProvider>();
    final ok = await auth.login(_phone); // مسار الهاتف: تسجيل + OTP واحد
    if (ok && mounted) _handleLoginResult(auth);
  }

  Future<void> _doEmailVerify() async {
    final email = _emailController.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      _showError('أدخل بريدك الإلكتروني أولًا');
      return;
    }
    if (_emailSending) return;
    setState(() => _emailSending = true);
    try {
      final auth = context.read<AuthProvider>();
      final ok = await auth.registerEmail(email);
      if (!mounted) return;
      if (ok) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('تم إرسال رمز التحقق إلى بريدك')));
        Navigator.push(context,
            MaterialPageRoute(builder: (_) => OtpScreen(phone: email, isRegister: true, otpExpiresAt: context.read<AuthProvider>().otpExpiresAt)));
      } else {
        _showError(auth.error ?? 'فشل إرسال رمز التحقق');
      }
    } finally {
      if (mounted) setState(() => _emailSending = false);
    }
  }

  Future<void> _doEmailLogin() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    if (email.isEmpty || !email.contains('@')) {
      _showError('أدخل بريدًا إلكترونيًا صحيحًا');
      return;
    }
    if (password.isEmpty) {
      _showError('أدخل كلمة المرور');
      return;
    }
    final auth = context.read<AuthProvider>();
    final ok = await auth.loginEmail(email, password);
    if (!mounted) return;
    if (ok) {
      _handleLoginResult(auth);
    } else {
      _showError(auth.error ?? 'البريد أو كلمة المرور غير صحيحة');
    }
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
    if (ok && mounted) _handleLoginResult(auth);
  }

  void _handleLoginResult(AuthProvider auth) {
    if (auth.lastLoginBypass) {
      Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const ChatsScreen()),
          (route) => false);
    } else {
      Navigator.push(context,
          MaterialPageRoute(builder: (_) => OtpScreen(phone: _phone, isRegister: false, otpExpiresAt: auth.otpExpiresAt)));
    }
  }

  // ---------------------------------------------------------------------------
  // البناء
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    final cfg = auth.authConfig;
    final regMethods = _registerMethods;
    final loginMethods = _loginMethods;
    final regAvailable = regMethods.isNotEmpty && (cfg?.registrationEnabled ?? true);
    final loginAvailable = loginMethods.isNotEmpty && (cfg?.loginEnabled ?? true);
    final stopped = cfg != null && !cfg.loginEnabled && !cfg.registrationEnabled;

    return Scaffold(
      body: SafeArea(
        child: Container(
          decoration: BoxDecoration(gradient: LinearGradient(
            begin: Alignment.topCenter, end: Alignment.bottomCenter,
            colors: [c.bg, c.surface2],
          )),
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const SizedBox(height: 16),
                    // الشعار
                    Center(
                      child: Column(
                        children: [
                          Container(
                            width: 92,
                            height: 92,
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.topLeft, end: Alignment.bottomRight,
                                colors: [c.accent, c.accent.withOpacity(0.75)],
                              ),
                              borderRadius: BorderRadius.circular(30),
                              boxShadow: [
                                BoxShadow(color: c.accent.withOpacity(0.35),
                                    blurRadius: 24, offset: const Offset(0, 10)),
                              ],
                            ),
                            child: const Icon(Icons.chat_bubble,
                                color: Colors.white, size: 50),
                          ),
                          const SizedBox(height: 14),
                          Text('NOVA Messenger',
                              style: TextStyle(
                                  fontSize: 25,
                                  fontWeight: FontWeight.w800,
                                  fontFamily: 'Cairo',
                                  color: c.text)),
                          const SizedBox(height: 4),
                          Text('مرحباً بك', style: TextStyle(fontSize: 14, color: c.muted)),
                        ],
                      ),
                    ),
                    const SizedBox(height: 18),
                    // البطاقة
                    Container(
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        color: c.surface,
                        borderRadius: BorderRadius.circular(22),
                        boxShadow: [
                          BoxShadow(color: Colors.black.withOpacity(0.05),
                              blurRadius: 18, offset: const Offset(0, 6)),
                        ],
                      ),
                      child: _buildCardContent(c, regAvailable, loginAvailable, stopped),
                    ),
                    const SizedBox(height: 12),
                    // أخطاء عامة
                    if (!auth.loading && auth.error != null)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Text(auth.error!,
                            style: TextStyle(color: Colors.redAccent, fontSize: 13),
                            textAlign: TextAlign.center),
                      ),
                    const SizedBox(height: 6),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildCardContent(NovaColors c, bool regAvailable, bool loginAvailable, bool stopped) {
    if (stopped) return _stoppedWidget(c);

    switch (_mode) {
      case _Mode.pickFlow:
        return _pickFlowCard(c, regAvailable, loginAvailable);
      case _Mode.phone:
        return _phoneForm(c);
      case _Mode.email:
        return _emailForm(c);
      case _Mode.username:
        return _usernameForm(c);
    }
  }

  /// هل الوضع الحالي يُستخدم للدخول (وليس التسجيل)؟
  bool get _currentModeIsLogin {
    if (_mode == _Mode.pickFlow) return false;
    if (_mode == _Mode.username) return true; // اسم المستخدم للدخول فقط
    if (_loginMethod != null) {
      return (_mode == _Mode.phone && _loginMethod == _LoginMethod.phone) ||
          (_mode == _Mode.email && _loginMethod == _LoginMethod.email);
    }
    return false;
  }

  Widget _stoppedWidget(NovaColors c) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 24),
      child: Center(child: Text('خدمة الدخول والتسجيل متوقفة مؤقتًا من لوحة الإدارة',
          textAlign: TextAlign.center, style: TextStyle(fontSize: 14))),
    );
  }

  // بطاقة اختيار الوضع: دخول أو إنشاء حساب
  Widget _pickFlowCard(NovaColors c, bool regAvailable, bool loginAvailable) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (loginAvailable) ...[
          _flowButton(
            c,
            label: 'تسجيل الدخول',
            subtitle: 'دخول بحسابك الحالي',
            icon: Icons.login_rounded,
            onPressed: () => _pickFlow(true),
          ),
          const SizedBox(height: 12),
        ],
        if (regAvailable) ...[
          _flowButton(
            c,
            label: 'إنشاء حساب',
            subtitle: 'حساب جديد في ثوانٍ',
            icon: Icons.person_add_rounded,
            onPressed: () => _pickFlow(false),
          ),
        ],
        if (!loginAvailable && !regAvailable)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(child: Text('لا تتوفر حاليًا أي طريقة للدخول أو التسجيل',
                textAlign: TextAlign.center, style: TextStyle(fontSize: 14))),
          ),
      ],
    );
  }

  Widget _flowButton(NovaColors c, {
    required String label,
    required String subtitle,
    required IconData icon,
    required VoidCallback onPressed,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          decoration: BoxDecoration(
            color: c.surface2,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: c.line),
          ),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                    colors: [c.accent, c.accent.withOpacity(0.8)],
                  ),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(icon, color: Colors.white, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, style: TextStyle(
                        fontSize: 16, fontWeight: FontWeight.w800,
                        fontFamily: 'Cairo', color: c.text)),
                    const SizedBox(height: 2),
                    Text(subtitle, style: TextStyle(fontSize: 12.5, color: c.muted)),
                  ],
                ),
              ),
              Icon(Icons.chevron_left, color: c.muted, size: 20),
            ],
          ),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // نماذج الطرق
  // ---------------------------------------------------------------------------

  Widget _phoneForm(NovaColors c) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _methodHeader(c, label: 'رقم الهاتف',
            subtitle: 'سنتحقق من ملكيتك للرقم عبر رمز OTP'),
        const SizedBox(height: 14),
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
        const SizedBox(height: 18),
        _primaryButton('إرسال رمز التحقق', c, _doPhoneLogin),
        const SizedBox(height: 10),
        _backButton(c),
      ],
    );
  }

  Widget _emailForm(NovaColors c) {
    final isLogin = _currentModeIsLogin;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _methodHeader(c, label: 'البريد الإلكتروني',
            subtitle: isLogin
                ? 'الدخول بريدك وكلمة المرور'
                : 'سنتحقق من ملكيتك للبريد برمز OTP'),
        const SizedBox(height: 14),
        TextField(
          controller: _emailController,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.go,
          decoration: InputDecoration(
            labelText: 'البريد الإلكتروني',
            hintText: 'example@mail.com',
            filled: true,
            fillColor: c.surface2,
            suffixIcon: (!isLogin && _emailSending)
                ? const Padding(
                    padding: EdgeInsets.all(14),
                    child: SizedBox(width: 20, height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2)))
                : (isLogin ? null : TextButton(
                    onPressed: _doEmailVerify,
                    style: TextButton.styleFrom(
                      backgroundColor: c.accent.withOpacity(0.10),
                      foregroundColor: c.accent,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(99)),
                    ),
                    child: const Text('تحقق',
                        style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700)),
                  )),
            suffixIconConstraints: const BoxConstraints(maxHeight: 36, minWidth: 0),
            enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: BorderSide(color: c.line)),
            focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: BorderSide(color: c.accent)),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          ),
          onSubmitted: (_) => _doEmailVerify(),
        ),
        const SizedBox(height: 10),
        if (isLogin)
          TextField(
            controller: _passwordController,
            obscureText: true,
            textInputAction: TextInputAction.go,
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
            onSubmitted: (_) => _doEmailLogin(),
          )
        else
          Text('اضغط «تحقق» داخل الحقل لإرسال رمز التحقق إلى بريدك',
              style: TextStyle(fontSize: 12, color: c.muted),
              textAlign: TextAlign.end),
        const SizedBox(height: 16),
        _primaryButton(
            isLogin ? 'دخول' : 'إرسال رمز التحقق',
            c,
            isLogin ? _doEmailLogin : _doEmailVerify),
        const SizedBox(height: 10),
        _backButton(c),
      ],
    );
  }

  Widget _usernameForm(NovaColors c) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _methodHeader(c, label: 'اسم المستخدم',
            subtitle: 'الدخول باسم المستخدم وكلمة المرور'),
        const SizedBox(height: 14),
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
        const SizedBox(height: 12),
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
        const SizedBox(height: 18),
        _primaryButton('دخول', c, _doUsernameLogin),
        const SizedBox(height: 10),
        _backButton(c),
      ],
    );
  }

  Widget _methodHeader(NovaColors c, {required String label, required String subtitle}) {
    return Row(
      children: [
        Icon(Icons.tune, size: 17, color: c.accent),
        const SizedBox(width: 6),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: TextStyle(
                  fontSize: 16, fontWeight: FontWeight.w800,
                  fontFamily: 'Cairo', color: c.text)),
              const SizedBox(height: 1),
              Text(subtitle, style: TextStyle(fontSize: 11.5, color: c.muted)),
            ],
          ),
        ),
        // إذا كانت هناك أكثر من طريقة متاحة نعرض أيقونة تبديل بينها
        if (_switchableMethods.length > 1)
          PopupMenuButton<_Mode>(
            icon: Icon(Icons.swap_horiz_rounded, size: 18, color: c.accent),
            tooltip: 'تبديل الطريقة',
            itemBuilder: (_) => _switchableMethods
                .map((m) => PopupMenuItem(value: m,
                    child: Row(children: [
                      Icon(_modeIcon(m), size: 17, color: c.accent),
                      const SizedBox(width: 8),
                      Text(_modeLabel(m)),
                    ])))
                .toList(),
            onSelected: (m) => setState(() => _mode = m),
          ),
        InkWell(
          onTap: () => setState(() => _mode = _Mode.pickFlow),
          child: Padding(
            padding: const EdgeInsets.all(4),
            child: Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: c.muted),
          ),
        ),
      ],
    );
  }

  /// الطرق المتاحة في الوضع الحالي (دخول أو تسجيل) لتبديلها
  List<_Mode> get _switchableMethods {
    if (_mode == _Mode.pickFlow) return [];
    final isLogin = _currentModeIsLogin;
    if (isLogin) {
      return _loginMethods.map((m) => _methodToMode(m)).toList();
    } else {
      return _registerMethods.map((m) => _methodToModeReg(m)).toList();
    }
  }

  IconData _modeIcon(_Mode m) {
    switch (m) {
      case _Mode.phone: return Icons.phone_android_rounded;
      case _Mode.email: return Icons.email_rounded;
      case _Mode.username: return Icons.person_rounded;
      case _Mode.pickFlow: return Icons.tune;
    }
  }

  String _modeLabel(_Mode m) {
    switch (m) {
      case _Mode.phone: return 'رقم الهاتف';
      case _Mode.email: return 'البريد الإلكتروني';
      case _Mode.username: return 'اسم المستخدم';
      case _Mode.pickFlow: return 'اختيار';
    }
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

  Widget _backButton(NovaColors c) {
    return TextButton.icon(
      onPressed: () => setState(() => _mode = _Mode.pickFlow),
      icon: Icon(Icons.arrow_back_ios_new_rounded, size: 15, color: c.muted),
      label: Text('العودة للخيارات', style: TextStyle(fontSize: 13, color: c.muted)),
    );
  }
}
