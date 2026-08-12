import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'web_login_screen.dart';

/// تبويب الإعدادات — تصميم القالب الجديد مع الوظائف الحقيقية
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
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('تعديل الملف الشخصي'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: nameCtrl,
              decoration: const InputDecoration(labelText: 'الاسم (إلزامي)'),
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
      showToast(context, 'الاسم مطلوب');
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
      showToast(context, 'تم حفظ التعديلات');
    } else {
      showToast(context, res['message'] ?? 'فشل الحفظ');
    }
  }

  Future<void> _confirmLogout() async {
    if (!mounted) return;
    await context.read<AuthProvider>().logout();
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

  Widget _iconBox({required IconData icon, Color? color}) {
    final c = NovaColors.of(context);
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(
        color: c.surface2,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Icon(icon, size: 20, color: color ?? c.text),
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
              padding: EdgeInsets.only(bottom: 96),
              child: Column(
                children: [
                  novaTopBar(context, title: 'الإعدادات'),
                  const SizedBox(height: 14),
                  // بطاقة المستخدم (نمط القالب)
                  Center(
                    child: Column(
                      children: [
                        NovaAvatar(letter: letter, size: 92, radius: 30, online: me?.isOnline ?? false),
                        const SizedBox(height: 12),
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(me?.name ?? '-',
                                style: TextStyle(
                                    fontSize: 19, fontWeight: FontWeight.w800, color: c.text)),
                            if (me?.isVerified == true)
                              const Padding(padding: EdgeInsets.only(right: 6),
                                  child: Icon(Icons.verified, color: Colors.blue, size: 16)),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(me?.phone ?? '',
                            style: TextStyle(fontSize: 13, color: c.muted)),
                        if (me?.planName != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                              decoration: BoxDecoration(
                                gradient: LinearGradient(colors: [
                                  const Color(0xFFB7791F).withOpacity(.18),
                                  const Color(0xFFD69E2E).withOpacity(.12),
                                ]),
                                borderRadius: BorderRadius.circular(22),
                                border: Border.all(color: const Color(0xFFB7791F).withOpacity(.45)),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.workspace_premium, color: Color(0xFFB7791F), size: 16),
                                  const SizedBox(width: 6),
                                  Text('باقة ${me!.planName}',
                                      style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700,
                                          color: Color(0xFF975A16))),
                                ],
                              ),
                            ),
                          ),
                        const SizedBox(height: 12),
                        TabChip(label: 'تعديل الملف الشخصي', onTap: _updateProfile),
                      ],
                    ),
                  ),
                  // الأقسام
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const SizedBox(height: 18),
                        SectionTitle('الحساب'),
                        NovaCard(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Column(
                            children: [
                              RowItem(
                                leading: _iconBox(icon: Icons.edit),
                                title: 'تعديل الملف الشخصي',
                                subtitle: 'الاسم والبريد الإلكتروني',
                                onTap: _updateProfile,
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.devices),
                                title: 'الأجهزة المتصلة',
                                subtitle: '${me?.activeDevicesCount ?? 0} من ${me?.maxDevicesAllowed ?? 1} جهاز مسموح',
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
                                onTap: () => _infoDialog('حول NOVA Messenger',
                                    'الإصدار: 3.2 (Flutter)\n\n• رسائل فورية مع تعديل وحذف لدى الطرفين\n• مكالمات صوتية وفيديو\n• القصص (الحالة)\n• الأجهزة المرتبطة (QR)\n• حسابات موثّقة بعلامة زرقاء\n• إشعارات فورية FCM'),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        SectionTitle('الحساب'),
                        NovaCard(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: RowItem(
                            leading: _iconBox(icon: Icons.logout, color: c.red),
                            title: 'تسجيل الخروج',
                            onTap: _confirmLogout,
                            last: true,
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
