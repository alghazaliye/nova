import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'web_login_screen.dart';

/// تبويب الإعدادات: الملف الشخصي، تعديل الاسم والبريد، QR للويب، تسجيل الخروج
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool _saving = false;

  Widget _iconBox({required IconData icon, Color? color}) {
    final c = NovaColors.of(context);
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(14)),
      child: Icon(icon, size: 20, color: color ?? c.text),
    );
  }

  Future<void> _updateProfile() async {
    final auth = context.read<AuthProvider>();
    final me = auth.user;
    if (me == null) return;
    final nameCtrl = TextEditingController(text: me.name ?? '');
    final emailCtrl = TextEditingController(text: me.email ?? '');
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
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
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
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

  void _infoDialog(String title, String body) {
    showDialog(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: Text(title),
        content: Text(body),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c), child: const Text('حسنًا')),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    final auth = context.watch<AuthProvider>();
    final me = auth.user;
    final letter = me?.name != null && me!.name!.isNotEmpty ? me.name![0] : '?';
    return Scaffold(
      body: _saving
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.only(bottom: 96),
              child: Column(
                children: [
                  // شريط العنوان
                  Container(
                    color: c.surface,
                    child: SafeArea(
                      bottom: false,
                      child: Container(
                        padding: const EdgeInsets.fromLTRB(18, 13, 18, 13),
                        decoration: BoxDecoration(
                            border: Border(bottom: BorderSide(color: c.line))),
                        child: Text('الإعدادات',
                            style: TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                color: c.text)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  // بطاقة المستخدم
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: NovaCard(
                      padding: const EdgeInsets.all(18),
                      child: Row(
                        children: [
                          NovaAvatar(letter: letter, size: 58, radius: 17, online: true),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(children: [
                                  Expanded(
                                    child: Text(me?.name ?? '-',
                                        style: TextStyle(
                                            fontSize: 17,
                                            fontWeight: FontWeight.w800,
                                            fontFamily: 'Cairo',
                                            color: c.text),
                                        overflow: TextOverflow.ellipsis),
                                  ),
                                  if (me?.isVerified == true)
                                    const Padding(
                                        padding: EdgeInsets.only(right: 4),
                                        child: Icon(Icons.verified,
                                            color: Colors.blue, size: 17)),
                                ]),
                                const SizedBox(height: 4),
                                Text(me?.phone ?? '',
                                    style: TextStyle(
                                        fontSize: 13, color: c.muted)),
                                if (me?.email != null &&
                                    me!.email.toString().isNotEmpty)
                                  Text(me.email ?? '',
                                      style: TextStyle(
                                          fontSize: 12, color: c.muted)),
                              ],
                            ),
                          ),
                          IconBtn(icon: Icons.edit, onTap: _updateProfile, color: c.accent),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  // أدوات الإعدادات
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SectionTitle('الحساب'),
                        NovaCard(
                          padding: const EdgeInsets.symmetric(vertical: 6),
                          child: Column(
                            children: [
                              RowItem(
                                leading: _iconBox(icon: Icons.edit),
                                title: 'تعديل الملف الشخصي',
                                subtitle: 'الاسم والبريد الإلكتروني',
                                onTap: _updateProfile,
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.qr_code_2),
                                title: 'الأجهزة المرتبطة (الويب)',
                                subtitle: 'امسح رمز QR من nova.computer/web',
                                onTap: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                        builder: (_) => const WebLoginScreen())),
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.notifications_outlined),
                                title: 'الإشعارات',
                                subtitle: 'تصلك عبر الإشعارات الفورية (FCM)',
                                onTap: () => _infoDialog('الإشعارات',
                                    'تُفعّل الإشعارات تلقائيًا عند تسجيل الدخول وترسل عبر FCM لكل رسالة ومكالمة وقصة.'),
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.data_saver_on),
                                title: 'استخدام البيانات',
                                subtitle: 'الرسائل والوسائط تحفظ لدى الطرفين',
                                onTap: () => _infoDialog('استخدام البيانات',
                                    'الرسائل والوسائط والقصص والمكالمات كلها محفوظة على الخادم، وتظهر لدى الطرفين وفق الصلاحيات.'),
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.info_outline),
                                title: 'حول التطبيق',
                                subtitle: 'NOVA Messenger v3.2 (Flutter)',
                                onTap: () => showDialog(
                                  context: context,
                                  builder: (c) => AlertDialog(
                                    shape: RoundedRectangleBorder(
                                        borderRadius: BorderRadius.circular(22)),
                                    title: const Text('حول NOVA Messenger'),
                                    content: const Column(
                                      mainAxisSize: MainAxisSize.min,
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text('الإصدار: 3.2 (Flutter)'),
                                        SizedBox(height: 8),
                                        Text('المميزات:'),
                                        Text('• رسائل فورية مع تعديل وحذف لدى الطرفين'),
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
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        SectionTitle('الحساب'),
                        NovaCard(
                          padding: const EdgeInsets.symmetric(vertical: 6),
                          child: RowItem(
                                leading: _iconBox(icon: Icons.logout, color: c.red),
                            title: 'تسجيل الخروج',
                            onTap: _confirmLogout,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text('NOVA Messenger © 2026',
                      style: TextStyle(fontSize: 12, color: c.muted)),
                ],
              ),
            ),
    );
  }
}
