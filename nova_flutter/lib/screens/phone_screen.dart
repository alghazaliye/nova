
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:intl_phone_field/intl_phone_field.dart';
import '../providers/auth_provider.dart';
import '../utils/nova_web_state.dart';
import 'otp_screen.dart';

/// شاشة إدخال رقم الهاتف — نمط WhatsApp
class PhoneScreen extends StatefulWidget {
  const PhoneScreen({super.key});

  @override
  State<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends State<PhoneScreen> {
  String _phone = '';
  String? _autoPhone;
  final TextEditingController _phoneController = TextEditingController();

  @override
  void initState() {
    super.initState();
    if (kIsWeb) {
      final href = novaHref();
      debugPrint('[NovaWeb] href: $href');
      final uri = Uri.parse(href);
      final p = uri.queryParameters['phone'];
      if (p != null && p.length >= 7) {
        _autoPhone = p;
        setNovaState('phone_param=$p');
      final otpAuto = uri.queryParameters['otp'];
      // ملء الحقل تلقائياً بعد بناء الإطار
      WidgetsBinding.instance.addPostFrameCallback((_) {
        setNovaState('postframe_filling');
        _fillAuto();
        // تخطي login والانتقال المباشر إلى OTP إذا أُعطى otp في الرابط
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
      setNovaState('filling_controller');
      _phone = _autoPhone!;
      // إزالة البادئة المكررة: إذا بدأت القيمة بـ +966 والبلد SA، نضع الرقم بدون البادئة
      var raw = _autoPhone!;
      if (raw.startsWith('+966')) raw = raw.substring(4);
      _phoneController.text = raw;
      _phoneController.selection = TextSelection.fromPosition(
          TextPosition(offset: _autoPhone!.length));
      // النقر على التالي بعد ثانية لالتقاط شاشة OTP
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

  @override
  void dispose() {
    _phoneController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 40),
              Center(
                child: Column(
                  children: [
                    Container(
                      width: 96,
                      height: 96,
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primary,
                        borderRadius: BorderRadius.circular(28),
                      ),
                      child: const Icon(Icons.chat_bubble,
                          color: Colors.white, size: 52),
                    ),
                    const SizedBox(height: 20),
                    const Text('NOVA Messenger',
                        style: TextStyle(
                            fontSize: 28,
                            fontWeight: FontWeight.bold,
                            fontFamily: 'Cairo')),
                    const SizedBox(height: 8),
                    const Text('أدخل رقم هاتفك للمتابعة',
                        style: TextStyle(fontSize: 15, color: Colors.black54)),
                  ],
                ),
              ),
              const SizedBox(height: 48),
              IntlPhoneField(
                controller: _phoneController,
                decoration: InputDecoration(
                  labelText: 'رقم الهاتف',
                  border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14)),
                  filled: true,
                  fillColor: Colors.grey.shade100,
                ),
                initialCountryCode: 'SA',
                languageCode: 'ar',
                disableLengthCheck: true,
                autovalidateMode: AutovalidateMode.disabled,
                onChanged: (p) => _phone = p.completeNumber,
                onCountryChanged: (c) {},
              ),
              const SizedBox(height: 24),
              if (auth.loading)
                const Center(child: CircularProgressIndicator())
              else ...[
                if (auth.error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(auth.error!,
                        style: const TextStyle(color: Colors.red, fontSize: 13),
                        textAlign: TextAlign.center),
                  ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Theme.of(context).colorScheme.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: () async {
                    if (_phone.length < 6) {
                      setState(() => auth); // noop
                      ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('أدخل رقمًا صحيحًا')));
                      return;
                    }
                    final ok = await auth.login(_phone);
                    if (ok && context.mounted) {
                      Navigator.push(
                          context,
                          MaterialPageRoute(
                              builder: (_) =>
                                  OtpScreen(phone: _phone, isRegister: false)));
                    }
                  },
                  child: const Text('التالي',
                      style: TextStyle(fontSize: 17)),
                ),
              ],
              const SizedBox(height: 24),
              const Text(
                'الحسابات التجريبية: +966599995001، +966599995002، +966599991001\nرمز التحقق التجريبي: 123456',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: Colors.black45),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
