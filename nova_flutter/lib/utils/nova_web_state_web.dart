import 'dart:js_interop';

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
