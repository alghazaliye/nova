// ويب: عرض الفيديو عبر عنصر <video> HTML أصلي داخل HtmlElementView
// يستخدم dart:js_interop (extension types) ليكون متوافقًا مع dart2wasm (WASM GC).
// ملاحظة: هذا الملف يُستورد فقط على الويب (conditional import)
// لذا لا يؤثر على نسخة الموبايل.
import 'dart:js_interop';
import 'dart:ui_web' as ui_web;

import 'package:flutter/material.dart';

bool _registered = false;

/// عنصر HTMLStyleDeclaration عبر extension type (متوافق مع WASM).
extension type CSSStyleDeclaration._(JSObject _) implements JSObject {
  external set width(JSString value);
  external set height(JSString value);
  external set objectFit(JSString value);
}

/// عنصر HTMLVideoElement عبر extension type (متوافق مع WASM).
extension type HTMLVideoElement._(JSObject _) implements JSObject {
  external void load();
  external JSPromise<JSAny?> play();
  external void pause();

  external set src(JSString value);
  external JSString get src;
  external set autoplay(bool value);
  external CSSStyleDeclaration get style;
  external bool get paused;
}

/// عنصر HTMLDocument عبر extension type.
extension type HTMLDocument._(JSObject _) implements JSObject {
  external HTMLVideoElement createElementVideo();
}

@JS('document')
external HTMLDocument get _document;

@JS('HTMLVideoElement')
external HTMLVideoElement _createElementVideo(JSString tag);

class StoryVideoHelper {
  HTMLVideoElement? _element;

  void _ensureRegistered() {
    if (_registered) return;
    ui_web.platformViewRegistry.registerViewFactory(
      'nova_story_video',
      (int viewId) => _createElementVideo('video'.toJS),
    );
    _registered = true;
  }

  void createAndInsertVideo(String url) {
    _ensureRegistered();
    final video = _createElementVideo('video'.toJS);
    video.src = url.toJS;
    video.autoplay = true;
    video.style.width = '100%'.toJS;
    video.style.height = '100%'.toJS;
    video.style.objectFit = 'cover'.toJS;
    video.load();
    _element = video;
  }

  void play() {
    try {
      _element?.play();
    } catch (_) {}
  }

  void pause() {
    try {
      _element?.pause();
    } catch (_) {}
  }

  void removeVideo() {
    try {
      _element?.pause();
      _element?.src = ''.toJS;
      _element?.load();
    } catch (_) {}
  }

  bool isPlaying() {
    try {
      return _element != null && !_element!.paused;
    } catch (_) {
      return false;
    }
  }

  Widget buildWidget() {
    return const HtmlElementView(viewType: 'nova_story_video');
  }
}
