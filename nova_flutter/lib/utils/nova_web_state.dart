/// حالة ويب NOVA للتشخيص الآلي (screenshots) — conditional import
/// main.dart يستورد هذا الملف عبر conditional imports.
import 'nova_web_state_stub.dart' if (dart.library.html) 'nova_web_state_web.dart';

void setNovaState(String value) => setNovaStateImpl(value);
void setNovaChats(String value) => setNovaChatsImpl(value);
String novaHref() => novaHrefImpl();

// إشعارات المتصفح (ويب فقط)
Future<bool> NovaWebStateIsNotificationGranted() => isNotificationGrantedImpl();
Future<void> NovaWebStateRequestNotificationPermission() => requestNotificationPermissionImpl();
void NovaWebStateShowNotification(String title, String body, {String? tag}) =>
    showNotificationImpl(title, body, tag: tag);

/// واجهة ثابتة لاستخدامها من أي مكان بدون استيراد conditional imports
abstract class NovaWebState {
  static Future<bool> isNotificationGranted() => isNotificationGrantedImpl();
  static Future<void> requestNotificationPermission() => requestNotificationPermissionImpl();
  static void showNotification(String title, String body, {String? tag}) =>
      showNotificationImpl(title, body, tag: tag);
}
