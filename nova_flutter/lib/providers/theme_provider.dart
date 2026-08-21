import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// تفضيل الوضع الليلي — يُحفظ محليًا ويستمر عبر إعادة تشغيل التطبيق
class ThemeProvider extends ChangeNotifier {
  static const String _key = 'nova_dark_mode';
  bool _isDark = false;
  bool get isDark => _isDark;

  ThemeProvider() {
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    _isDark = prefs.getBool(_key) ?? false;
    notifyListeners();
  }

  Future<void> toggle() async {
    _isDark = !_isDark;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_key, _isDark);
    notifyListeners();
  }
}
