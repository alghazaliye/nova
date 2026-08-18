import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';
import 'web_login_screen.dart';
import 'privacy_screen.dart';
import '../utils/avatar_picker.dart';
import '../offline/network_detector.dart';
import '../offline/media_store.dart';
import 'package:flutter/material.dart' show Icons;

/// تبويب الإعدادات — تصميم القالب الجديد مع الوظائف الحقيقية
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  bool _saving = false;

  /// حالة الاتصال الحالية من مراقبة الشبكة (Offline-First)
  String get _netStatus {
    switch (NetworkDetector.instance.state) {
      case NovaNetworkState.online:
        return 'متصل بالخادم';
      case NovaNetworkState.serverDown:
        return 'الشبكة تعمل والخادم غير متاح — وضع غير متصل';
      case NovaNetworkState.offline:
        return 'لا يوجد اتصال بالإنترنت — وضع غير متصل';
    }
  }

  Future<void> _showStorageDialog() async {
    Map<String, int> usage = {};
    try {
      usage = await MediaStore.usageByCategory();
    } catch (_) {}
    final sizeMB = (usage.values.fold<int>(0, (s, v) => s + v) / 1024 / 1024)
        .toStringAsFixed(1);
    if (!mounted) return;
    showDialog(
      context: context,
      builder: (c) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: const Text('التخزين المحلي'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('الحجم الكلي: $sizeMB MB',
                style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            if (usage.isNotEmpty)
              ...usage.entries.map((e) => Text('${e.key}: ${e.value} KB')),
            const SizedBox(height: 8),
            const Text(
                'الرسائل والمحادثةات تُحفظ تلقائيًا وتعمل في الوضع غير المتصل.'),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () async {
                Navigator.pop(c);
                try {
                  await MediaStore.clearCache();
                  if (mounted) showToast(context, 'تم مسح التخزين المؤقت');
                } catch (_) {
                  if (mounted) showToast(context, 'تعذر مسح التخزين');
                }
              },
              child: const Text('مسح التخزين المؤقت',
                  style: TextStyle(color: Colors.red))),
          TextButton(
              onPressed: () => Navigator.pop(c), child: const Text('حسنًا')),
        ],
      ),
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
                        Stack(
                          clipBehavior: Clip.none,
                          children: [
                            NovaAvatar(letter: letter, size: 92, radius: 30, online: me?.isOnline ?? false, imageUrl: me?.avatar),
                            Positioned(
                              left: -6,
                              bottom: -6,
                              child: PressScale(
                                onTap: () => NovaAvatarPicker.pick(context),
                                child: Container(
                                  width: 34,
                                  height: 34,
                                  decoration: BoxDecoration(
                                    color: c.accent,
                                    shape: BoxShape.circle,
                                    border: Border.all(color: c.bg, width: 2),
                                  ),
                                  child: const Icon(Icons.photo_camera, color: Colors.white, size: 17),
                                ),
                              ),
                            ),
                          ],
                        ),
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
                                leading: _iconBox(icon: Icons.photo_camera),
                                title: 'تغيير الصورة الشخصية',
                                subtitle: 'اضغط لاختيار صورة من الجهاز',
                                onTap: () => NovaAvatarPicker.pick(context),
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.lock_outline),
                                title: 'الخصوصية',
                                subtitle: 'آخر الظهور، الصورة الشخصية، حالة النشاط',
                                onTap: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                        builder: (_) => const PrivacyScreen())),
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.timer_off),
                                title: 'الرسائل المختفية',
                                subtitle: 'تلقائيًا: تُضبط لكل محادثة من شاشة المحادثة نفسها',
                                onTap: () => _infoDialog('الرسائل المختفية',
                                    'من شاشة أي محادثة، اضغط أيقونة "المؤقت" أعلى المحادثة واختر: \n• دائم (افتراضي)\n• بعد 24 ساعة\n• بعد القراءة'),
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
                                subtitle: 'NOVA Messenger v3.6.0 (Flutter)',
                                onTap: () => _infoDialog('حول NOVA Messenger',
                                    'الإصدار: 3.6.0 (Flutter)\n\n• رسائل فورية مع تعديل وحذف لدى الطرفين\n• مكالمات صوتية وفيديو\n• القصص (الحالة)\n• الأجهزة المرتبطة (QR) بحدود الباقة\n• حسابات موثّقة بعلامة زرقاء\n• باقات الاشتراك (مجانية/ذهبية/بلاتينية)\n• نظام حظر الحسابات من لوحة الإدارة\n• إشعارات فورية FCM'),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        SectionTitle('التخزين والبيانات المحلية'),
                        NovaCard(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Column(
                            children: [
                              RowItem(
                                leading: _iconBox(icon: Icons.cloud_off_outlined),
                                title: 'حالة الاتصال',
                                subtitle: _netStatus,
                                onTap: () => _infoDialog('الوضع غير المتصل',
                                    'يعمل التطبيق بالوضع غير المتصل: الرسائل تُحفظ محليًا وتُرسل تلقائيًا عند عودة الاتصال، والمحادثات تبقى متاحة من الذاكرة المحلية.'),
                              ),
                              RowItem(
                                leading: _iconBox(icon: Icons.storage_outlined),
                                title: 'التخزين المحلي',
                                subtitle: 'الرسائل والمحادثةات والوسائط',
                                onTap: () => _showStorageDialog(),
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
