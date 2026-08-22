import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_service.dart';
import '../utils/nova_ui.dart';

/// شاشة الخصوصية — آخر الظهور، الصورة الشخصية، إيصالات القراءة
/// الخيارات: everybody (الجميع) / contacts (جهات الاتصال) / nobody (لا أحد)
class PrivacyScreen extends StatefulWidget {
  const PrivacyScreen({super.key});

  @override
  State<PrivacyScreen> createState() => _PrivacyScreenState();
}

class _PrivacyScreenState extends State<PrivacyScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _settings = {};
  int? _readReceipts;

  static const _labels = {
    'everybody': 'الجميع',
    'contacts': 'جهات الاتصال',
    'nobody': 'لا أحد',
    'none': 'لا أحد',
    'name_username': 'الاسم الموحد',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ApiService.get('/privacy').timeout(const Duration(seconds: 15));
      if (!mounted) return;
      if (res['success'] == true && res['data'] is Map) {
        setState(() {
          _settings = Map<String, dynamic>.from(res['data'] as Map<String, dynamic>);
          // API returns read_receipts as bool; keep int semantics for the toggle
          final rr = _settings['read_receipts'];
          _readReceipts = (rr == false || rr == 0) ? 0 : 1;
          _loading = false;
          _error = null;
        });
      } else {
        if (!mounted) return;
        setState(() {
          _loading = false;
          _error = (res is Map) ? (res['message'] ?? 'فشل في تحميل إعدادات الخصوصية') : 'فشل في تحميل إعدادات الخصوصية';
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'تعذر تحميل إعدادات الخصوصية. تحقق من اتصالك وحاول مجددًا.';
        });
      }
    }
  }

  Future<void> _update(String key, dynamic value) async {
    final payload = <String, dynamic>{key: value};
    final res = await ApiService.put('/privacy', body: payload);
    if (!mounted) return;
    if (res['success'] == true) {
      setState(() => _settings[key] = value);
      showToast(context, 'تم الحفظ');
    } else {
      showToast(context, res['message'] ?? 'فشل الحفظ');
    }
  }

  bool _boolSetting(String key) => _settings[key] == true || _settings[key] == 1;

  Future<void> _showVisibilitySheet(String title, String key, String current) async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      barrierColor: Colors.black.withOpacity(0.42),
      builder: (c) {
        final cl = NovaColors.of(c);
        return Container(
          decoration: BoxDecoration(
            color: cl.surface,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(25)),
          ),
          padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.paddingOf(c).bottom + 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                    width: 42, height: 4,
                    decoration: BoxDecoration(color: cl.line, borderRadius: BorderRadius.circular(5))),
              ),
              const SizedBox(height: 15),
              Text(title, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: cl.text)),
              const SizedBox(height: 14),
              ...['everybody', 'contacts', 'nobody'].map((v) => PressScale(
                    onTap: () => Navigator.pop(c, v),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(
                        children: [
                          Radio<String>(
                            value: v,
                            groupValue: current,
                            onChanged: null,
                            activeColor: cl.accent,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(_labels[v]!,
                                style: TextStyle(fontSize: 15, color: cl.text, fontWeight: FontWeight.w600)),
                          ),
                          if (v == current)
                            const Icon(Icons.check, size: 18, color: Color(0xFF25D366)),
                        ],
                      ),
                    ),
                  )),
            ],
          ),
        );
      },
    );
    if (selected != null && selected != current && mounted) {
      _update(key, selected);
    }
  }



  Widget _toggleRow({
    required IconData icon,
    required String title,
    required String subtitle,
    required String key,
  }) {
    final on = _boolSetting(key);
    return RowItem(
      leading: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: NovaColors.of(context).surface2,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Icon(icon, size: 20, color: NovaColors.of(context).text),
      ),
      title: title,
      subtitle: subtitle,
      trailing: Switch(
        value: on,
        onChanged: (_) => _update(key, on ? 0 : 1),
        activeColor: NovaColors.of(context).accent,
      ),
      onTap: () => _update(key, on ? 0 : 1),
    );
  }

  Future<void> _toggleReadReceipts() async {
    final newVal = (_readReceipts ?? 1) == 1 ? 0 : 1;
    final res = await ApiService.put('/privacy', body: {'read_receipts': newVal});
    if (!mounted) return;
    if (res['success'] == true) {
      setState(() => _readReceipts = newVal);
      showToast(context, newVal == 1 ? 'تم تفعيل إيصالات القراءة' : 'تم إيقاف إيصالات القراءة');
    } else {
      showToast(context, res['message'] ?? 'فشل الحفظ');
    }
  }

  Future<void> _scanQr() async {
    String? uuid = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.black,
      builder: (context) => Container(
        height: MediaQuery.of(context).size.height * 0.8,
        child: Column(
          children: [
            AppBar(
              backgroundColor: Colors.transparent,
              elevation: 0,
              title: const Text('امسح رمز QR للربط', style: TextStyle(color: Colors.white)),
              leading: IconButton(
                icon: const Icon(Icons.close, color: Colors.white),
                onPressed: () => Navigator.pop(context),
              ),
            ),
            Expanded(
              child: MobileScanner(
                onDetect: (capture) {
                  final List<Barcode> barcodes = capture.barcodes;
                  for (final barcode in barcodes) {
                    final String? code = barcode.rawValue;
                    if (code != null && code.isNotEmpty) {
                      Navigator.pop(context, code);
                      break;
                    }
                  }
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(24.0),
              child: Text(
                'وجه الكاميرا نحو رمز QR المعروض في شاشة تسجيل الدخول على الجهاز الآخر',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white.withOpacity(0.7), fontSize: 14),
              ),
            ),
          ],
        ),
      ),
    );

    if (uuid == null || uuid.isEmpty) return;

    try {
      final res = await ApiService.post('/devices/link/authorize', body: {'session_uuid': uuid});
      if (!mounted) return;
      if (res['status_code'] == 401) return;
      
      if (res['success'] == true) {
        showToast(context, 'تم ربط الجهاز بنجاح');
      } else {
        showToast(context, res['message'] ?? 'فشل ربط الجهاز');
      }
    } catch (_) {
      if (mounted) showToast(context, 'تعذر الاتصال بالخادم');
    }
  }

  Widget _visibilityRow({
    required IconData icon,
    required String title,
    required String subtitle,
    required String key,
  }) {
    final rawValue = _settings[key];
    final String value = (rawValue is String) ? rawValue : 'everybody';
    
    return RowItem(
      leading: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: NovaColors.of(context).surface2,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Icon(icon, size: 20, color: NovaColors.of(context).text),
      ),
      title: title,
      subtitle: subtitle,
      trailing: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: NovaColors.of(context).accent.withOpacity(0.12),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(_labels[value] ?? (value == 'everyone' ? 'الجميع' : value),
            style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: NovaColors.of(context).accent)),
      ),
      onTap: () => _showVisibilitySheet(title, key, value),
    );
  }

  Widget _sectionTitle(String title) {
    final c = NovaColors.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 14, 4, 6),
      child: Text(title,
          style: TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700, color: c.muted)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final c = NovaColors.of(context);
    return Scaffold(
      backgroundColor: c.bg,
      appBar: AppBar(
        backgroundColor: c.surface,
        foregroundColor: c.text,
        elevation: 0,
        title: const Text('الخصوصية', style: TextStyle(fontWeight: FontWeight.w800)),
        centerTitle: true,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.error_outline, size: 44, color: c.muted),
                        const SizedBox(height: 12),
                        Text(_error!,
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 14, color: c.muted)),
                        const SizedBox(height: 16),
                        ElevatedButton.icon(
                          onPressed: _load,
                          icon: const Icon(Icons.refresh),
                          label: const Text('إعادة المحاولة'),
                        ),
                      ],
                    ),
                  ),
                )
              : SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 8),
                  _sectionTitle('من يمكنه رؤية معلوماتي'),
                  NovaCard(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Column(
                      children: [
                        _visibilityRow(
                          icon: Icons.access_time,
                          title: 'آخر ظهور',
                          subtitle: 'من يمكنه رؤية آخر ظهور لك',
                          key: 'last_seen_visibility',
                        ),
                        _toggleRow(
                          icon: Icons.cloud,
                          title: 'حالة النشاط (متصل)',
                          subtitle: 'من يمكنه رؤية أنك متصل الآن',
                          key: 'online_status',
                        ),
                        _visibilityRow(
                          icon: Icons.photo,
                          title: 'الصورة الشخصية',
                          subtitle: 'من يمكنه رؤية صورتك الشخصية',
                          key: 'photo_visibility',
                        ),
                        _visibilityRow(
                          icon: Icons.text_fields,
                          title: 'حالة الحالة (About)',
                          subtitle: 'من يمكنه رؤية حالتك النصية',
                          key: 'status_visibility',
                        ),
                        _visibilityRow(
                          icon: Icons.phone,
                          title: 'رقم الهاتف',
                          subtitle: 'من يمكنه رؤية رقم هاتفك',
                          key: 'phone_visibility',
                        ),
                        _visibilityRow(
                          icon: Icons.email,
                          title: 'البريد الإلكتروني',
                          subtitle: 'من يمكنه رؤية بريدك الإلكتروني',
                          key: 'email_visibility',
                        ),
                      ],
                    ),
                  ),
                  _sectionTitle('ربط الأجهزة'),
                  NovaCard(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Column(
                      children: [
                        RowItem(
                          leading: Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: NovaColors.of(context).surface2,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Icon(Icons.qr_code_scanner, size: 20, color: NovaColors.of(context).text),
                          ),
                          title: 'ربط جهاز جديد (QR)',
                          subtitle: 'الموافقة على فتح حسابك في متصفح أو جهاز آخر',
                          onTap: _scanQr,
                        ),
                      ],
                    ),
                  ),
                  _sectionTitle('من يمكنه التواصل معي'),
                  NovaCard(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Column(
                      children: [
                        _visibilityRow(
                          icon: Icons.message,
                          title: 'الرسائل',
                          subtitle: 'من يمكنه إرسال رسائل لي',
                          key: 'messages_from',
                        ),
                        _visibilityRow(
                          icon: Icons.call,
                          title: 'المكالمات',
                          subtitle: 'من يمكنه الاتصال بي',
                          key: 'calls_from',
                        ),
                        _visibilityRow(
                          icon: Icons.group,
                          title: 'المجموعات',
                          subtitle: 'من يمكنه إضافتي إلى المجموعات',
                          key: 'groups_from',
                        ),
                      ],
                    ),
                  ),
                  _sectionTitle('قابلية الاكتشاف'),
                  NovaCard(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Column(
                      children: [
                        _toggleRow(
                          icon: Icons.phone,
                          title: 'البحث برقم الهاتف',
                          subtitle: _boolSetting('find_by_phone')
                              ? 'يمكن للآخرين العثور عليك برقم هاتفك'
                              : 'لا يمكن العثور عليك برقم هاتفك',
                          key: 'find_by_phone',
                        ),
                        _toggleRow(
                          icon: Icons.email,
                          title: 'البحث بالبريد الإلكتروني',
                          subtitle: _boolSetting('find_by_email')
                              ? 'يمكن للآخرين العثور عليك ببريدك الإلكتروني'
                              : 'لا يمكن العثور عليك ببريدك الإلكتروني',
                          key: 'find_by_email',
                        ),

                      ],
                    ),
                  ),

                  _sectionTitle('إعدادات أخرى'),
                  NovaCard(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: Column(
                      children: [
                        RowItem(
                          leading: Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: c.surface2,
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Icon(Icons.check_circle_outline, size: 20, color: c.text),
                          ),
                          title: 'إيصالات القراءة',
                          subtitle: (_readReceipts ?? 1) == 1
                              ? 'مفعّلة: يرى الآخرون عندما تقرأ رسالتهم'
                              : 'معطلة: لن يعرف الآخرون متى قرأت رسائلهم',
                          trailing: Switch(
                            value: (_readReceipts ?? 1) == 1,
                            onChanged: (_) => _toggleReadReceipts(),
                            activeColor: c.accent,
                          ),
                          onTap: () => _toggleReadReceipts(),
                        ),
                        _toggleRow(
                          icon: Icons.phonelink,
                          title: 'السماح بالتواصل عبر الهاتف',
                          subtitle: _boolSetting('allow_by_phone')
                              ? 'يمكن للآخرين بدء محادثة معك من رقمك'
                              : 'لا يمكن بدء محادثة من رقمك',
                          key: 'allow_by_phone',
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text('ملاحظة',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: c.text)),
                  const SizedBox(height: 8),
                  Text(
                    'إذا اخترت "لا أحد" لآخر الظهور، لن تتمكن أنت أيضًا من رؤية آخر ظهور الآخرين. إعدادات "جهات الاتصال" تعتمد على حفظ الطرفين لبعضهما في جهات الاتصال.',
                    style: TextStyle(fontSize: 13, color: c.muted),
                  ),
                ],
              ),
            ),
    );
  }
}
