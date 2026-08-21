import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'package:pin_code_fields/pin_code_fields.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../utils/nova_ui.dart';
import '../utils/nova_web_state.dart';
import 'profile_screen.dart';
import 'chats_screen.dart';
import '../models/user_model.dart';
import 'chat_screen.dart';
import '../services/api_service.dart';


/// شاشة إدخال رمز التحقق OTP
class OtpScreen extends StatefulWidget {
  final String phone;
  /// بريد إلكتروني بدل الهاتف (عند التحقق عبر البريد) — لا يُستخدم مع phone معًا
  final String? email;
  final bool isRegister;
  /// رمز OTP من رابط الويب (تجنّب JS interop غير الموثوق في WASM)
  final String? autoOtp;
  /// صلاحية الرمز النشط (للعرض مع عدّاد تنازلي)
  final DateTime? otpExpiresAt;

  const OtpScreen({super.key, required this.phone, this.email, this.isRegister = false, this.autoOtp, this.otpExpiresAt});

  @override
  State<OtpScreen> createState() => _OtpScreenState();
}

class _OtpScreenState extends State<OtpScreen> {
  String _otp = '';
  final TextEditingController _ctrl = TextEditingController();
  /// تحديث العدّاد التنازلي كل ثانية
  late final Timer _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
    if (mounted) setState(() {});
  });

  @override
  void initState() {
    super.initState();
    _fillAutoOtp();
  }

  @override
  void dispose() {
    _countdownTimer.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  void _fillAutoOtp() {
    if (!kIsWeb) return;
    String otp = widget.autoOtp ?? '';
    if (otp.isEmpty) {
      try {
        final q = Uri.splitQueryString(Uri.parse(novaHref()).query);
        otp = q['otp'] ?? '';
      } catch (_) {}
    }
    if (otp.length == 6) {
      // رمز كامل من الرابط — تحقق تلقائي بعد بناء الإطار الأول
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _ctrl.text = otp;
        _verify(otp);
      });
    } else if (otp.isNotEmpty) {
      // رمز جزئي (<6) لا يصلح للملء التلقائي: ملؤه كان يُعرض في خانات خاطئة
      // ويعرقل الإدخال اليدوي، فلا نلوث حالة الخانات به
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      resizeToAvoidBottomInset: false,
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
              Text(widget.email ?? widget.phone,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      fontFamily: 'Cairo',
                      color: c.text)),
              const SizedBox(height: 8),
              _OtpCountdown(otpExpiresAt: widget.otpExpiresAt, muted: c.muted, accent: c.accent),
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
                onChanged: (v) {
                  setState(() => _otp = v);
                  // التزامن الفوري مع الـ controller في Flutter Web:
                  // إذا انفصل عرض الخانات عن الحالة (إدخال سريع / لصق) نعيد ضبط العرض
                  if (_ctrl.text != v) _ctrl.text = v;
                },
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
    // منع الضغط المزدوج على «تحقق» أثناء التحقق الجاري:
    // بدون ذلك يصل طلبان متتاليان بالرمز نفسه، وأولهما يتحقق بنجاح
    // والثاني يعيد 400 (OTP_EXPIRED) فيبدو أن التحقق «لا يعمل»
    if (context.read<AuthProvider>().loading) return;
    // المصدر الصحيح للرمز هو الـ controller — حتى لو انفصل العرض عن الحالة
    // عند الإدخال السريع أو اللصق في Flutter Web
    otp = _ctrl.text.replaceAll(RegExp(r'[^0-9]'), '');
    setState(() => _otp = otp);
    if (otp.length < 6) {
      // رسالة واضحة بدل الصمت (كان الزر لا يفعل شيئًا عند الرمز غير المكتمل)
      context.read<AuthProvider>().showError('أدخل رمز التحقق كاملًا (6 أرقام)');
      return;
    }
    final bool ok;
    if (widget.email != null) {
      ok = await context.read<AuthProvider>().verifyEmailOtp(widget.email!, otp);
    } else {
      ok = await context.read<AuthProvider>().verifyOtp(widget.phone, otp);
    }
    if (!mounted) return;
    if (ok) {
      // فتح محادثة مباشرة عند وجود ?chat=<id> في الرابط (للاختبار السريع)
      final q = Uri.splitQueryString(Uri.parse(novaHref()).query);
      final chatId = int.tryParse(q['chat'] ?? '') ?? 0;
      final screen = widget.isRegister
          ? const ProfileScreen()
          : (chatId > 0 ? const ChatsScreen() : const ChatsScreen());
      Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => screen));
      if (chatId > 0) {
        // جلب المحادثة وفتحها بعد بناء قائمة المحادثات
        try {
          final res = await ApiService.get('/conversations');
          if (mounted) {
            final list = (res['data'] is List) ? res['data'] as List : [];
            Conversation? conv;
            for (final item in list) {
              if (item is Map &&
                  (item['id'] == chatId || '${item['id']}' == '$chatId')) {
                conv = Conversation.fromJson(Map<String, dynamic>.from(item));
                break;
              }
            }
            if (conv != null) {
              Navigator.push(
                  context,
                  MaterialPageRoute(
                      builder: (_) => ChatScreen(conv: conv!)));
            }
          }
        } catch (_) {}
      }
    }
  }
}

/// عدّاد تنازلي للوقت المتبقي لانتهاء صلاحية الرمز (يتحدث كل ثانية)
class _OtpCountdown extends StatefulWidget {
  final DateTime? otpExpiresAt;
  final Color muted;
  final Color accent;

  const _OtpCountdown({required this.otpExpiresAt, required this.muted, required this.accent});

  @override
  State<_OtpCountdown> createState() => _OtpCountdownState();
}

class _OtpCountdownState extends State<_OtpCountdown> {
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    if (widget.otpExpiresAt != null) {
      // تحديث العدّاد كل ثانية حتى الوصول إلى الصفر
      _timer = Timer.periodic(const Duration(seconds: 1), (t) {
        // مقارنة UTC بـ UTC — DateTime.now() محلي، لذا نحوّله إلى UTC أولًا
        final diff = widget.otpExpiresAt!.difference(DateTime.now().toUtc());
        if (!mounted) return;
        if (diff.isNegative || diff == Duration.zero) {
          t.cancel();
        }
        setState(() {});
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  /// الوقت المتبقي (0 إذا انتهى) — مقارنة UTC بـ UTC
  Duration _remaining() {
    if (widget.otpExpiresAt == null) return Duration.zero;
    final diff = widget.otpExpiresAt!.difference(DateTime.now().toUtc());
    return diff.isNegative ? Duration.zero : diff;
  }

  String _format(Duration d) {
    final m = d.inMinutes;
    final s = d.inSeconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final remaining = _remaining();
    if (widget.otpExpiresAt == null) {
      // صلاحية غير متاحة من الخادم — نص صامت
      return const SizedBox.shrink();
    }
    if (remaining == Duration.zero) {
      return Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.timer_off, size: 14, color: Colors.redAccent),
              const SizedBox(width: 4),
              Text('انتهت صلاحية الرمز — اطلب رمزًا جديدًا',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: Colors.redAccent)),
            ],
          ),
        ],
      );
    }
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.timer, size: 14, color: widget.accent),
            const SizedBox(width: 4),
            Text('الوقت المتبقي لانتهاء الرمز: ',
                style: TextStyle(fontSize: 13, color: widget.muted)),
            Text(_format(remaining),
                style: TextStyle(fontSize: 14, color: widget.muted, fontFeatures: const [FontFeature.tabularFigures()])),

          ],
        ),
      ],
    );
  }
}
