import 'dart:js_interop';
import 'dart:js_util' as js_util;
import 'dart:async';

@JS('window.location.href')
external JSString get _href;

@JS('window.__novaState')
external set _novaState(JSString v);

@JS('window.__novaChats')
external set _novaChats(JSString v);

void setNovaStateImpl(String value) {
  _novaState = value.toJS;
}

void setNovaChatsImpl(String value) {
  _novaChats = value.toJS;
}

String novaHrefImpl() {
  return _href.toDart;
}

// ═══ إشعارات المتصفح ═══
@JS('Notification')
external JSObject get _notification;

@JS('window.navigator.userAgent')
external JSString get _ua;

@JS('Notification.requestPermission')
external JSPromise<JSString> _requestPermission();

bool _notificationSupported() {
  try {
    return _notification != null;
  } catch (_) {
    return false;
  }
}

Future<bool> isNotificationGrantedImpl() async {
  if (!_notificationSupported()) return false;
  final permission = js_util.getProperty<JSString>(_notification, 'permission');
  return permission.toDart == 'granted';
}

Future<void> requestNotificationPermissionImpl() async {
  if (!_notificationSupported()) return;
  await _requestPermission().toDart;
}

void showNotificationImpl(String title, String body, {String? tag}) {
  if (!_notificationSupported()) return;
  try {
    js_util.callMethod(_notification, 'Notification', [
      title.toJS,
      js_util.jsify({'body': body, 'tag': tag ?? 'nova', 'icon': '/favicon.png'}),
    ]);
  } catch (_) {}
}
