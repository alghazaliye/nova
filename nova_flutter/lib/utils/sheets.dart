import 'package:flutter/material.dart';
import '../utils/nova_ui.dart';

/// شيت الإرفاق: الكاميرا/المعرض/مستند تذهب للوظيفة الحقيقية، الباقي توست.
void openAttachSheet(BuildContext context,
    {required VoidCallback onImage, required VoidCallback onDocument}) {
  _openSheet(
    context,
    'إرفاق',
    [
      (Icons.camera_alt, 'الكاميرا', onImage),
      (Icons.photo_library_outlined, 'المعرض', onImage),
      (Icons.description_outlined, 'مستند', onDocument),
      (Icons.location_on_outlined, 'الموقع', () => showToast(context, 'قريبًا ✨')),
      (Icons.person_outline, 'جهة اتصال', () => showToast(context, 'قريبًا ✨')),
      (Icons.poll_outlined, 'استطلاع', () => showToast(context, 'قريبًا ✨')),
    ],
  );
}

/// شيت خيارات المحادثة (من رأس المحادثة)
void openChatOptionsSheet(BuildContext context,
    {VoidCallback? onSearch}) {
  _openSheet(
    context,
    'خيارات المحادثة',
    [
      (Icons.notifications_off_outlined, 'كتم الإشعارات', () => showToast(context, 'قريبًا ✨')),
      (Icons.push_pin_outlined, 'تثبيت المحادثة', () => showToast(context, 'قريبًا ✨')),
      if (onSearch != null) (Icons.search, 'بحث', onSearch),
      (Icons.delete_outline, 'حذف المحادثة', () => showToast(context, 'قريبًا ✨')),
    ],
  );
}

void _openSheet(BuildContext context, String title, List<(IconData, String, VoidCallback)> items) {
  showModalBottomSheet(
    context: context,
    backgroundColor: Colors.transparent,
    barrierColor: Colors.black.withOpacity(0.42),
    builder: (ctx) {
      final c = NovaColors.of(ctx);
      return Container(
        decoration: BoxDecoration(
          color: c.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(25)),
        ),
        padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.paddingOf(ctx).bottom + 20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                  width: 42, height: 4,
                  decoration: BoxDecoration(color: c.line, borderRadius: BorderRadius.circular(5))),
            ),
            const SizedBox(height: 15),
            Text(title, style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: c.text)),
            const SizedBox(height: 14),
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 3,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.45,
              children: [
                for (final it in items)
                  PressScale(
                    onTap: () {
                      Navigator.pop(ctx);
                      it.$3();
                    },
                    child: Container(
                      decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(18)),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(it.$1, size: 23, color: c.text),
                          const SizedBox(height: 6),
                          Text(it.$2,
                              style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: c.text)),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ),
      );
    },
  );
}
