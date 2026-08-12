import 'nova_web_state.dart';

/// إشعارات المتصفح على الويب — يطلب الإذن مرة واحدة ثم يعرض إشعارًا
/// عند وصول رسالة جديدة أو مكالمة واردة (عند تشغيل الويب فقط)
class WebNotifier {
  static bool _requested = false;

  /// طلب إذن إشعارات المتصفح (يُستدعى عند تسجيل الدخول)
  static Future<void> requestPermission() async {
    try {
      if (_requested) return;
      _requested = true;
      await NovaWebState.requestNotificationPermission();
    } catch (_) {}
  }

  /// عرض إشعار متصفح
  static Future<void> show(String title, String body, {String? tag}) async {
    try {
      var granted = await NovaWebState.isNotificationGranted();
      if (!granted) {
        await requestPermission();
        granted = await NovaWebState.isNotificationGranted();
      }
      if (granted) {
        NovaWebState.showNotification(title, body, tag: tag);
      }
    } catch (_) {}
  }
}
