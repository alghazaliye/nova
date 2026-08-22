import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/api_service.dart';
import 'nova_ui.dart';

/// أداة اختيار ورفع الصورة الشخصية
class NovaAvatarPicker {
  static Future<void> pick(BuildContext context) async {
    try {
      final picker = ImagePicker();
      final XFile? img = await picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 80,
      );
      if (img == null || !context.mounted) return;
      final bytes = await img.readAsBytes();
      final success = await context.read<AuthProvider>().uploadAvatar(bytes, img.name);
      if (!context.mounted) return;
      if (success) {
        showToast(context, 'تم تحديث الصورة الشخصية');
      } else {
        final err = context.read<AuthProvider>().error;
        showToast(context, err ?? 'فشل رفع الصورة');
      }
    } catch (e) {
      if (context.mounted) showToast(context, 'فشل اختيار الصورة');
    }
  }
}
