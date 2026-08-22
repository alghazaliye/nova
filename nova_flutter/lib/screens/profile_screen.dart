import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:typed_data';
import 'package:flutter/foundation.dart' show kIsWeb;
import '../providers/auth_provider.dart';
import '../utils/nova_ui.dart';
import 'chats_screen.dart';

/// شاشة إعداد الملف الشخصي (الاسم إلزامي، البريد اختياري)
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _nameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  bool _busy = false;
  XFile? _imageFile;
  Uint8List? _webImage;

  Future<void> _pickImage() async {
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.gallery, maxWidth: 800, maxHeight: 800);
    if (image != null) {
      if (kIsWeb) {
        final bytes = await image.readAsBytes();
        setState(() {
          _imageFile = image;
          _webImage = bytes;
        });
      } else {
        setState(() => _imageFile = image);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final c = NovaColors.of(context);
    
    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: c.bg,
      appBar: AppBar(
        title: const Text('إعداد الملف الشخصي', style: TextStyle(fontWeight: FontWeight.w800)),
        centerTitle: true,
        elevation: 0,
        backgroundColor: c.surface,
        foregroundColor: c.text,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Stack(
                  children: [
                    Container(
                      width: 110,
                      height: 110,
                      decoration: BoxDecoration(
                        color: c.surface2,
                        shape: BoxShape.circle,
                        border: Border.all(color: c.accent, width: 2),
                      ),
                      child: ClipOval(
                        child: _webImage != null
                            ? Image.memory(_webImage!, fit: BoxFit.cover)
                            : NovaAvatar(
                                letter: auth.user?.name ?? '?',
                                imageUrl: auth.user?.avatar,
                                size: 110,
                              ),
                      ),
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: PressScale(
                        onTap: _pickImage,
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: c.accent,
                            shape: BoxShape.circle,
                            border: Border.all(color: c.surface, width: 2),
                          ),
                          child: const Icon(Icons.camera_alt, color: Colors.white, size: 20),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 40),
              Text('الاسم الظاهر', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: c.text)),
              const SizedBox(height: 8),
              TextField(
                controller: _nameCtrl,
                style: TextStyle(color: c.text),
                decoration: InputDecoration(
                  hintText: 'أدخل اسمك (إلزامي)',
                  hintStyle: TextStyle(color: c.muted),
                  filled: true,
                  fillColor: c.surface,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                ),
              ),
              const SizedBox(height: 20),
              Text('البريد الإلكتروني (اختياري)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: c.text)),
              const SizedBox(height: 8),
              TextField(
                controller: _emailCtrl,
                style: TextStyle(color: c.text),
                decoration: InputDecoration(
                  hintText: 'example@mail.com',
                  hintStyle: TextStyle(color: c.muted),
                  filled: true,
                  fillColor: c.surface,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                ),
                keyboardType: TextInputType.emailAddress,
              ),
              const SizedBox(height: 48),
              if (_busy)
                const Center(child: CircularProgressIndicator())
              else
                PressScale(
                  onTap: () async {
                    final name = _nameCtrl.text.trim();
                    if (name.length < 2) {
                      showToast(context, 'الاسم إلزامي (حرفين على الأقل)');
                      return;
                    }
                    
                    setState(() => _busy = true);
                    
                    // 1. رفع الصورة إذا تم اختيارها
                    if (_imageFile != null) {
                      final bytes = _webImage ?? await _imageFile!.readAsBytes();
                      final uploadOk = await auth.uploadAvatar(bytes, _imageFile!.name);
                      if (!uploadOk) {
                        setState(() => _busy = false);
                        if (mounted) showToast(context, auth.error ?? 'فشل رفع الصورة');
                        return;
                      }
                    }
                    
                    // 2. تحديث الاسم والبريد
                    final success = await auth.updateProfile(
                      name: name,
                      email: _emailCtrl.text.trim().isEmpty ? null : _emailCtrl.text.trim(),
                    );
                    
                    setState(() => _busy = false);
                    
                    if (success && mounted) {
                      Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(builder: (_) => const ChatsScreen()));
                    } else if (mounted) {
                      showToast(context, auth.error ?? 'فشل حفظ البيانات');
                    }
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    decoration: BoxDecoration(
                      color: c.accent,
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(color: c.accent.withOpacity(0.3), blurRadius: 12, offset: const Offset(0, 4)),
                      ],
                    ),
                    alignment: Alignment.center,
                    child: const Text('متابعة',
                        style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Colors.white)),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
