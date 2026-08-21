import 'dart:async';

/// Stub لغير الويب (mobile/desktop)
void setNovaStateImpl(String value) {}
void setNovaChatsImpl(String value) {}
String novaHrefImpl() => '';
String? webOriginFallbackImpl() => null;
Future<bool> isNotificationGrantedImpl() async => false;
Future<void> requestNotificationPermissionImpl() async {}
void showNotificationImpl(String title, String body, {String? tag}) {}
void enablePresenceListenersImpl() {}
void setNovaStateTokenImpl(String? value) {}
