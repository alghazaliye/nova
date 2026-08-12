import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../providers/auth_provider.dart';
import 'chats_screen.dart';

/// شاشة الدخول عبر الويب (WhatsApp Web style)
/// المرحلة الأولى: الدخول برقم هاتف + OTP (قابلة للتحويل لاحقًا لربط الجهاز بالباركود)
class WebLoginScreen extends StatelessWidget {
  const WebLoginScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final me = context.watch<AuthProvider>().user;
    return Scaffold(
      appBar: AppBar(title: const Text('الأجهزة المرتبطة')),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              if (me == null)
                const Column(children: [
                  Icon(Icons.phone_android, size: 56, color: Colors.grey),
                  SizedBox(height: 12),
                  Text('سجّل الدخول في التطبيق أولًا',
                      style: TextStyle(fontSize: 16, color: Colors.black54)),
                ])
              else ...[
                const Icon(Icons.link, size: 56, color: Color(0xFF00A884)),
                const SizedBox(height: 16),
                const Text('لربط جهاز كمبيوتر بحسابك:',
                    style: TextStyle(fontSize: 16, fontFamily: 'Cairo')),
                const SizedBox(height: 8),
                QrImageView(
                  data: 'https://nova.computer/web?session=${me.uuid}',
                  version: QrVersions.auto,
                  size: 200,
                  backgroundColor: Colors.white,
                ),
                const SizedBox(height: 16),
                const Text('1) افتح nova.computer/web في المتصفح',
                    style: TextStyle(fontSize: 14)),
                const Text('2) امسح رمز QR أو أدخل رمز الجلسة أدناه',
                    style: TextStyle(fontSize: 14)),
                const Text('3) سجّل الدخول برقم هاتفك ورمز التحقق',
                    style: TextStyle(fontSize: 14)),
                const SizedBox(height: 16),
                OutlinedButton.icon(
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: me.uuid));
                    ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('تم نسخ رمز الجلسة')));
                  },
                  icon: const Icon(Icons.copy),
                  label: Text('نسخ رمز الجلسة (${me.uuid.substring(0, 8)}...)',
                      style: const TextStyle(fontFamily: 'Cairo')),
                ),
                const SizedBox(height: 24),
                OutlinedButton(
                  onPressed: () {
                    Navigator.pushReplacement(context,
                        MaterialPageRoute(builder: (_) => const ChatsScreen()));
                  },
                  child: const Text('العودة للمحادثات')),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
