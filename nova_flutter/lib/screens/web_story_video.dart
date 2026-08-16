// ويب: عرض الفيديو عبر عنصر <video> HTML أصلي داخل HtmlElementView
// يتجاوز مشاكل video_player مع wasm renderer في بعض المتصفحات.
// ملاحظة: هذا الملف يُستورد فقط على الويب (conditional import)
// لذا استيراد dart:html و dart:ui_web هنا آمن ولا يؤثر على نسخة الموبايل.
import 'dart:html' as html;
import 'dart:ui_web' as ui_web;

import 'package:flutter/material.dart';

bool _registered = false;

class StoryVideoHelper {
  html.VideoElement? _element;

  void _ensureRegistered() {
    if (_registered) return;
    ui_web.platformViewRegistry.registerViewFactory(
      'nova_story_video',
      (int viewId) => html.VideoElement(),
    );
    _registered = true;
  }

  void createAndInsertVideo(String url) {
    _ensureRegistered();
    final video = html.VideoElement()
      ..src = url
      ..autoplay = true
      ..style.width = '100%'
      ..style.height = '100%'
      ..style.objectFit = 'cover';
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
      _element?.src = '';
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
