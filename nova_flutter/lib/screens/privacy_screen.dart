import 'package:flutter/material.dart';
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
  Map<String, dynamic> _settings = {};
  int? _readReceipts;

  static const _labels = {
    'everybody': 'الجميع',
    'contacts': 'جهات الاتصال',
    'nobody': 'لا أحد',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await ApiService.get('/privacy');
      if (mounted && res['success'] == true && res['data'] != null) {
        setState(() {
          _settings = Map<String, dynamic>.from(res['data'] as Map<String, dynamic>);
          _readReceipts = int.tryParse((_settings['read_receipts'] ?? 1).toString());
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _update(String key, String value) async {
    final payload = <String, dynamic>{key: value};
    final res = await ApiService.put('/privacy', body: payload);
    if (!mounted) return;
    if (res['success'] == true) {
      _load();
      showToast(context, 'تم الحفظ');
    } else {
      showToast(context, res['message'] ?? 'فشل الحفظ');
    }
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

  Widget _visibilityRow({
    required IconData icon,
    required String title,
    required String subtitle,
    required String key,
  }) {
    final value = _settings[key] ?? 'everybody';
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
        child: Text(_labels[value] ?? value,
            style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: NovaColors.of(context).accent)),
      ),
      onTap: () => _showVisibilitySheet(title, key, value),
    );
  }

  Future<void> _showVisibilitySheet(String title, String key, String current) async {
    final choices = ['everybody', 'contacts', 'nobody'];
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
              ...choices.map((v) => PressScale(
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
          : SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 8),
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
                        _visibilityRow(
                          icon: Icons.photo,
                          title: 'الصورة الشخصية',
                          subtitle: 'من يمكنه رؤية صورتك الشخصية',
                          key: 'photo_visibility',
                        ),
                        _visibilityRow(
                          icon: Icons.text_fields,
                          title: 'حالة النشاط (متصل)',
                          subtitle: 'من يمكنه رؤية أنك متصل الآن',
                          key: 'status_visibility',
                        ),
                        const Divider(height: 1),
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
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text('ملاحظة',
                      style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: c.text)),
                  const SizedBox(height: 8),
                  Text(
                    'إذا اخترت \"لا أحد\" لآخر الظهور، لن تتمكن أنت أيضًا من رؤية آخر ظهور الآخرين.',
                    style: TextStyle(fontSize: 13, color: c.muted),
                  ),
                ],
              ),
            ),
    );
  }
}
