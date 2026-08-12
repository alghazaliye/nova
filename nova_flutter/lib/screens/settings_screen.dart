import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import 'web_login_screen.dart';

/// تبويب الإعدادات: الملف الشخصي، تعديل الاسم والبريد، QR للويب، تسجيل الخروج
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool _saving = false;

  Future<void> _updateProfile() async {
    final auth = context.read<AuthProvider>();
    final me = auth.user;
    if (me == null) return;
    final nameCtrl = TextEditingController(text: me.name ?? '');
    final emailCtrl = TextEditingController(text: me.email ?? '');
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('تعديل الملف الشخصي'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameCtrl,
              decoration:
                  const InputDecoration(labelText: 'الاسم (إلزامي)'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: emailCtrl,
              decoration: const InputDecoration(labelText: 'البريد (اختياري)'),
              keyboardType: TextInputType.emailAddress,
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('حفظ')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    if (nameCtrl.text.trim().isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('الاسم مطلوب')));
      return;
    }
    setState(() => _saving = true);
    final res = await ApiService.put('/users/me', body: {
      'name': nameCtrl.text.trim(),
      'email': emailCtrl.text.trim().isEmpty ? null : emailCtrl.text.trim(),
    });
    setState(() => _saving = false);
    if (!mounted) return;
    if (res['success'] == true) {
      auth.fetchMe();
      ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('تم حفظ التعديلات')));
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res['message'] ?? 'فشل الحفظ')));
    }
  }

  Future<void> _confirmLogout() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: const Text('تسجيل الخروج'),
        content: const Text('هل تريد تسجيل الخروج من الحساب؟'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(c, false),
              child: const Text('إلغاء')),
          TextButton(
              onPressed: () => Navigator.pop(c, true),
              child: const Text('خروج',
                  style: TextStyle(color: Colors.red))),
        ],
      ),
    );
    if (ok == true && mounted) {
      await context.read<AuthProvider>().logout();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final me = auth.user;
    return Scaffold(
      body: _saving
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              children: [
                // بطاقة المستخدم
                Container(
                  padding: const EdgeInsets.all(20),
                  color: Theme.of(context).colorScheme.primaryContainer,
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 32,
                        backgroundColor:
                            Theme.of(context).colorScheme.primary,
                        child: Text(
                            (me?.name ?? '?').isNotEmpty
                                ? (me?.name ?? '?')[0]
                                : '?',
                            style: const TextStyle(
                                color: Colors.white, fontSize: 28)),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(children: [
                              Expanded(
                                child: Text(me?.name ?? '-',
                                    style: const TextStyle(
                                        fontSize: 18,
                                        fontWeight: FontWeight.bold,
                                        fontFamily: 'Cairo'),
                                    overflow: TextOverflow.ellipsis),
                              ),
                              if (me?.isVerified == true)
                                const Padding(
                                    padding: EdgeInsets.only(right: 4),
                                    child: Icon(Icons.verified,
                                        color: Colors.blue, size: 18)),
                            ]),
                            Text(me?.phone ?? '',
                                style: const TextStyle(
                                    fontSize: 13, color: Colors.black54)),
                            if (me?.email != null &&
                                me!.email.toString().isNotEmpty)
                              Text(me.email ?? '',
                                  style: const TextStyle(
                                      fontSize: 12, color: Colors.black45)),
                          ],
                        ),
                      ),
                      IconButton(
                          onPressed: _updateProfile,
                          icon: const Icon(Icons.edit)),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                // أدوات الإعدادات
                ListTile(
                  leading: const Icon(Icons.qr_code_2,
                      color: Color(0xFF00A884)),
                  title: const Text('الأجهزة المرتبطة (الويب)'),
                  subtitle: const Text('امسح رمز QR من nova.computer/web'),
                  onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                          builder: (_) => const WebLoginScreen())),
                ),
                ListTile(
                  leading: const Icon(Icons.edit, color: Color(0xFF00A884)),
                  title: const Text('تعديل الملف الشخصي'),
                  subtitle: const Text('الاسم والبريد الإلكتروني'),
                  onTap: _updateProfile,
                ),
                ListTile(
                  leading: const Icon(Icons.notifications_outlined,
                      color: Color(0xFF00A884)),
                  title: const Text('الإشعارات'),
                  subtitle:
                      const Text('تصلك عبر الإشعارات الفورية (FCM)'),
                  onTap: () => showDialog(
                    context: context,
                    builder: (c) => AlertDialog(
                      title: const Text('الإشعارات'),
                      content: const Text(
                          'تُفعّل الإشعارات تلقائيًا عند تسجيل الدخول وترسل عبر FCM لكل رسالة ومكالمة وقصة.'),
                      actions: [
                        TextButton(
                            onPressed: () => Navigator.pop(c),
                            child: const Text('حسنًا')),
                      ],
                    ),
                  ),
                ),
                ListTile(
                  leading: const Icon(Icons.data_saver_on,
                      color: Color(0xFF00A884)),
                  title: const Text('استخدام البيانات'),
                  subtitle: const Text('الرسائل والوسائط تحفظ لدى الطرفين'),
                  onTap: () => showDialog(
                    context: context,
                    builder: (c) => AlertDialog(
                      title: const Text('استخدام البيانات'),
                      content: const Text(
                          'الرسائل والوسائط والقصص والمكالمات كلها محفوظة على الخادم، وتظهر لدى الطرفين وفق الصلاحيات.'),
                      actions: [
                        TextButton(
                            onPressed: () => Navigator.pop(c),
                            child: const Text('حسنًا')),
                      ],
                    ),
                  ),
                ),
                ListTile(
                  leading: const Icon(Icons.info_outline,
                      color: Color(0xFF00A884)),
                  title: const Text('حول التطبيق'),
                  subtitle: const Text('NOVA Messenger v3.0'),
                  onTap: () => showDialog(
                    context: context,
                    builder: (c) => AlertDialog(
                      title: const Text('حول NOVA Messenger'),
                      content: const Column(
                        mainAxisSize: MainAxisSize.min,
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('الإصدار: 3.0 (Flutter)'),
                          SizedBox(height: 8),
                          Text('المميزات:'),
                          Text(
                              '• رسائل فورية مع تعديل وحذف لدى الطرفين'),
                          Text('• مكالمات صوتية وفيديو'),
                          Text('• القصص (الحالة)'),
                          Text('• الأجهزة المرتبطة (QR)'),
                          Text('• حسابات موثّقة بعلامة زرقاء'),
                          Text('• إشعارات فورية FCM'),
                        ],
                      ),
                      actions: [
                        TextButton(
                            onPressed: () => Navigator.pop(c),
                            child: const Text('حسنًا')),
                      ],
                    ),
                  ),
                ),
                const Divider(height: 24),
                ListTile(
                  leading: const Icon(Icons.logout, color: Colors.red),
                  title: const Text('تسجيل الخروج',
                      style: TextStyle(color: Colors.red)),
                  onTap: _confirmLogout,
                ),
                const SizedBox(height: 24),
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 20),
                  child: Text(
                    'NOVA Messenger © 2026',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 12, color: Colors.black45),
                  ),
                ),
                const SizedBox(height: 16),
              ],
            ),
    );
  }
}
