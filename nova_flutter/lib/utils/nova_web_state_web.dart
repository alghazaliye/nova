import 'dart:js_interop';
import 'dart:js_interop_unsafe' as unsafe;

// ═══ JS interop موثوق في WASM/skwasm mode ═══
// نمط: external @JS getter لـ window (مفيد globalThis) ثم extensions من
// dart:js_interop_unsafe للتحكم الديناميكي.

@JS('window')
external JSObject get _window;

@JS('globalThis')
external JSObject get _globalThis;

void setNovaStateImpl(String value) {
  _window.setProperty('__novaState'.toJS, value.toJS);
}

void setNovaChatsImpl(String value) {
  _window.setProperty('__novaChats'.toJS, value.toJS);
}

String novaHrefImpl() {
  try {
    final loc = _window.getProperty<JSAny>('location'.toJS) as JSObject;
    return (loc.getProperty<JSAny>('href'.toJS) as JSString).toDart;
  } catch (_) {
    return '';
  }
}

// ═══ إشعارات المتصفح ═══
bool _notificationSupported() {
  try {
    return _window.has('Notification');
  } catch (_) {
    return false;
  }
}

Future<bool> isNotificationGrantedImpl() async {
  if (!_notificationSupported()) return false;
  try {
    final notif = _window.getProperty<JSAny>('Notification'.toJS) as JSObject;
    final perm = (notif.getProperty<JSAny>('permission'.toJS) as JSString).toDart;
    return perm == 'granted';
  } catch (_) {
    return false;
  }
}

Future<void> requestNotificationPermissionImpl() async {
  if (!_notificationSupported()) return;
  try {
    final notif = _window.getProperty<JSObject>('Notification'.toJS);
    notif.callMethod<JSAny?>('requestPermission'.toJS);
  } catch (_) {}
}

void showNotificationImpl(String title, String body, {String? tag}) {
  if (!_notificationSupported()) return;
  try {
    final notif = _window.getProperty<JSObject>('Notification'.toJS);
    final object = _globalThis.getProperty<JSObject>('Object'.toJS);
    final options = object.callMethod<JSObject>('create'.toJS, null);
    options
      ..setProperty('body'.toJS, body.toJS)
      ..setProperty('tag'.toJS, (tag ?? 'nova').toJS)
      ..setProperty('icon'.toJS, '/favicon.png'.toJS);
    (notif as JSFunction).callAsConstructor<JSObject>(title.toJS, options);
  } catch (_) {}
}
