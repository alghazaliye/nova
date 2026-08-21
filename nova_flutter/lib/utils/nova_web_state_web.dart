import 'dart:js_interop';

import 'dart:js_interop_unsafe';
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
  // قراءة متساهلة عبر js_util.getProperty — يقرأ getters بشكل موثوق
  // في dart2js وwasm على عكس getProperty<JSAny> الذي يفشل مع location getters
  Object? propOf(Object? obj, String name) {
    if (obj == null) return null;
    try {
      final JSAny? r = (obj as JSObject)[name];
      if (r == null) return null;
      return r.dartify();
    } catch (_) {
      return null;
    }
  }
  String asStr(Object? v) {
    if (v == null) return '';
    if (v is String) return v;
    try {
      final str = (v as JSObject)['toString'];
      final called = (str as JSFunction).callAsConstructor<JSAny>();
      return (called as JSString).toDart;
    } catch (_) {
      return v.toString();
    }
  }

  // 1) window.locationHref (مستحيل عمليًا لكن للأمان)
  String h = asStr(propOf(_window, 'locationHref'));
  if (h.isNotEmpty) return h;
  // 2) window.location.href — المسار الأساسي
  final loc = propOf(_window, 'location');
  if (loc != null) {
    h = asStr(propOf(loc, 'href'));
    if (h.isNotEmpty) return h;
  }
  // 3) globalThis.window.location.href
  final w = propOf(_globalThis, 'window');
  if (w != null) {
    final loc2 = propOf(w, 'location');
    if (loc2 != null) {
      h = asStr(propOf(loc2, 'href'));
      if (h.isNotEmpty) return h;
    }
  }
  return '';
}

/// طبقة أخيرة: قراءة window.location.origin مباشرة عبر js_util.getProperty
/// (موثوق في dart2js وwasm بعكس getProperty<JSAny>)
String? webOriginFallbackImpl() {
  try {
    final JSObject loc = _window.getProperty<JSObject>('location'.toJS);
    final JSAny? o = loc['origin'];
    final s = (o is JSString) ? o.toDart : o?.dartify().toString();
    if (s != null && s is String && s.isNotEmpty && s.startsWith('http')) return s;
  } catch (_) {}
  return null;
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
