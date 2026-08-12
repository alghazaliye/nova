
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:pin_code_fields/pin_code_fields.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../utils/nova_ui.dart';
import '../utils/nova_web_state.dart';
import 'profile_screen.dart';
import 'chats_screen.dart';


/// شاشة إدخال رمز التحقق OTP
class OtpScreen extends StatefulWidget {
  final String phone;
  final bool isRegister;

  const OtpScreen({super.key, required this.phone, this.isRegister = false});

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  String _otp = '';
  final TextEditingController _ctrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _fillAutoOtp();
  }

  void _fillAutoOtp() {
    if (!kIsWeb) return;
    try {
      final q = Uri.splitQueryString(Uri.parse(novaHref()).query);
      final otp = q['otp'] ?? '';
      if (otp.length == 6) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          _ctrl.text = otp;
          _verify(otp);
        });
      }

    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      appBar: AppBar(
        title: Text('رمز التحقق', style: TextStyle(color: c.text, fontWeight: FontWeight.w800, fontFamily: 'Cairo')),
        backgroundColor: c.surface,
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 16),
              Text('أدخل رمز التحقق المرسل إلى:',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 15, color: c.muted)),
              const SizedBox(height: 4),
              Text(widget.phone,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      fontFamily: 'Cairo',
                      color: c.text)),
              const SizedBox(height: 8),
              Text('رمز تجريبي: 123456',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: c.muted)),
              const SizedBox(height: 28),
              PinCodeTextField(
                appContext: context,
                length: 6,
                controller: _ctrl,
                keyboardType: TextInputType.number,
                autoFocus: true,
                animationType: AnimationType.fade,
                pinTheme: PinTheme(
                  shape: PinCodeFieldShape.box,
                  borderRadius: BorderRadius.circular(12),
                  fieldHeight: 54,
                  fieldWidth: 46,
                  activeFillColor: c.accent.withValues(alpha: 0.08),
                  selectedFillColor: c.surface2,
                  inactiveFillColor: c.surface2,
                  activeColor: c.accent,
                  inactiveColor: c.line,
                ),
                onCompleted: (v) => _verify(v),
                onChanged: (v) => setState(() => _otp = v),
              ),
              const SizedBox(height: 24),
              if (auth.loading)
                const Center(child: CircularProgressIndicator())
              else ...[
                if (auth.error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(auth.error!,
                        style: TextStyle(color: Colors.redAccent, fontSize: 13),
                        textAlign: TextAlign.center),
                  ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: c.accent,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: () => _verify(_otp),
                  child: const Text('تحقق',
                      style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _verify(String otp) async {
    if (otp.length < 6) return;
    final ok = await context.read<AuthProvider>().verifyOtp(widget.phone, otp);
    if (!mounted) return;
    if (ok) {
      Navigator.pushReplacement(
          context,
          MaterialPageRoute(
              builder: (_) =>
                  widget.isRegister ? const ProfileScreen() : const ChatsScreen()));
    }
  }
}
