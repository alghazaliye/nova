# حالة WASM build — Aug 16 2026 (06:40)

## مكتشف ومثبت:
1. **مشكلة base href حلّت** ✅: index.html كان `<base href="/">` بينما app تحت /web_app/ — أُصلح إلى `/web_app/`
2. البناء الجديد يعمل: OtpScreen تظهر بسرعة، bootstrap.js 200، wasm 2927KB حُمّل
3. **المشكلة المتبقية**: auto-verify لا يعمل في WASM — otp_screen._fillAutoOtp يعتمد على `novaHref()` (JS interop) الذي يرجع '' في WASM (catch يبتلع) → الحقل فارغ وno API request

## الحل المطلوب الآن (جذري — تجنب JS interop):
- main.dart يعرف otp بالفعل من `Uri.base.queryParameters['otp']` (native، يعمل في WASM)
- تعديل OtpScreen constructor: إضافة optional `String? autoOtp`
- main.dart: `target = OtpScreen(phone: p, isRegister: false, autoOtp: otp);` (في كل الأماكن السطور 136-143)
- otp_screen._fillAutoOtp: استخدم `widget.autoOtp ?? ''` بدل novaHref() (احتفظ novaHref كـ fallback)
- ملاحظة: OtpScreen يستورد من chats_screen/profile etc — لا شيء آخر يحتاج تعديل

## بعد التعديل:
```bash
cd /home/ubuntu/nova_new/nova_flutter && export PATH=/home/ubuntu/flutter/bin:$PATH
flutter build web --wasm --release --no-tree-shake-icons 2>&1 | tail -3
# ثم: rm -rf /home/ubuntu/nova_new/web_app/* && cp -r nova_flutter/build/web/* web_app/
# sed -i 's|<base href="/">|<base href="/web_app/">|' index.html (مهم!)
# توليد br/gz: for f in $(find . -type f \( -name "*.js" -o -name "*.mjs" -o -name "*.wasm" -o -name "*.html" -o -name "*.json" -o -name "*.woff2" -o -name "*.bin" \)); do gzip -k -f "$f" 2>/dev/null; brotli -k -f "$f" 2>/dev/null; done
# لا حاجة لنسخ skwasm (يُحمّل من gstatic CDN)
# الخادم يعمل PID 23209 على 8080 — لا حاجة لإعادة تشغيله
```

## حالة أخرى مهمة:
- web_app الآن 15MB (مع .br/.gz) — حجم مقبول
- الخادم: router.php مع Brotli + COOP/COEP لملفات web_app
- اختبار عبر: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966501234567&otp=123456
- الحقل "1" الظاهر في DOM سابقًا كان من browser autofill من جلسة قديمة — بعد reload القيمة ""

## بعد النجاح الكامل:
1. رفع git: `cd /home/ubuntu/nova_new && git add -A && git commit -m "WASM web build + Brotli + lighter web app (15MB)" && git push && git tag v5.0.2 && git push origin v5.0.2`
   - تأكد .gitignore يستبعد: .env, build/, nova_flutter/.gradle/, .dart_tool/, .idea/, local.properties
   - web_app مضغوط (15MB) مقبول للرفع
2. تسليم للمستخدم:
   - أحمد: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966501234567&otp=123456
   - سارة: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/web_app/?phone=%2B966502345678&otp=123456
   - Admin: https://8080-i0y331w4apy035oayp9qf-247b94fb.us3.manus.computer/admin/ (admin@nova-messenger.com / Admin@1234)
   - APK: /home/ubuntu/nova_new/Nova_Messenger.apk

## معلومات أساسية:
- OTP test: 123456 | users: أحمد(1)+966501234567, سارة(2)+966502345678, محمد(3), نور(4), خالد(5)
- DB: nova_user/nova2026 على 127.0.0.1:3306
- Flutter SDK: /home/ubuntu/flutter (3.47.0), Android SDK: /home/ubuntu/Android
- APK built successfully earlier (57MB)
