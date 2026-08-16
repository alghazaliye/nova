import 'dart:js_interop';
import 'dart:js_interop_unsafe' as unsafe;
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
external JSString? get _ua;

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
  final permission = _notification.getProperty<JSString>('permission'.toJS);
  return permission.toDart == 'granted';
}

Future<void> requestNotificationPermissionImpl() async {
  if (!_notificationSupported()) return;
  await _requestPermission().toDart;
}

void showNotificationImpl(String title, String body, {String? tag}) {
  if (!_notificationSupported()) return;
  try {
    final options = JSObject()
      ..setProperty('body'.toJS, body.toJS)
      ..setProperty('tag'.toJS, (tag ?? 'nova').toJS)
      ..setProperty('icon'.toJS, '/favicon.png'.toJS);
    globalContext.callMethod('Notification'.toJS, title.toJS, options);
  } catch (_) {}
}
