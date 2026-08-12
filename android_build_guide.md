# دليل بناء وتوليد ملف APK لتطبيق NOVA Messenger محلياً

بناءً على تحليل كود المصدر الخاص بمشروع **NOVA Messenger**، إليك الخطوات التقنية اللازمة لتوليد ملف APK على جهازك الشخصي.

---

### 1. المتطلبات الأساسية (Prerequisites)

يجب التأكد من تثبيت الأدوات التالية على جهازك:
*   **Java Development Kit (JDK):** الإصدار **17** (ضروري جداً لأن المشروع مهيأ للعمل مع هذا الإصدار).
*   **Android Studio:** يفضل إصدار حديث (مثل Hedgehog أو أحدث) لدعم Jetpack Compose و Kotlin DSL.
*   **Android SDK:** تأكد من تثبيت SDK Platform للإصدار 35 (Target SDK).

---

### 2. إعدادات ما قبل البناء (Configuration)

قبل البدء بعملية البناء، يجب إجراء التعديلات التالية في كود المصدر:

#### أ. ضبط عنوان الـ API:
افتح ملف `android/NOVAMessenger/app/build.gradle.kts` وقم بتعديل سطر `API_BASE_URL` ليشير إلى عنوان الخادم الخاص بك:
```kotlin
buildConfigField("String", "API_BASE_URL", "\"https://your-api-domain.com/api/v1/\"")
```

#### ب. إعداد Firebase (هام جداً):
المشروع يستخدم خدمات Firebase للإشعارات. بدون هذا الملف، سيفشل البناء:
1.  قم بإنشاء مشروع جديد في [Firebase Console](https://console.firebase.google.com/).
2.  أضف تطبيق Android باسم الحزمة `com.nova.messenger`.
3.  قم بتحميل ملف `google-services.json` وضعه في المجلد: `android/NOVAMessenger/app/`.

---

### 3. خطوات البناء باستخدام Android Studio (الطريقة الأسهل)

1.  افتح برنامج **Android Studio**.
2.  اختر **Open** وقم بالتوجه إلى مجلد `android/NOVAMessenger`.
3.  انتظر حتى ينتهي البرنامج من عملية **Gradle Sync** (تحميل المكتبات).
4.  من القائمة العلوية، اختر: **Build** > **Build Bundle(s) / APK(s)** > **Build APK(s)**.
5.  سيقوم البرنامج بمعالجة الكود وتوليد الملف.

---

### 4. خطوات البناء عبر سطر الأوامر (Command Line)

إذا كنت تفضل استخدام الـ Terminal، اتبع الآتي:
1.  افتح نافذة الأوامر داخل مجلد `android/NOVAMessenger`.
2.  قم بتنفيذ الأمر التالي (على Linux/Mac):
    ```bash
    ./gradlew assembleDebug
    ```
    أو على (Windows):
    ```powershell
    .\gradlew.bat assembleDebug
    ```

---

### 5. أين تجد ملف APK الناتج؟

بعد اكتمال عملية البناء بنجاح، ستجد ملف الـ APK في المسار التالي داخل مجلد المشروع:
`app/build/outputs/apk/debug/app-debug.apk`

---

### ملاحظات إضافية:
*   **نسخة الإنتاج (Release):** إذا أردت توليد نسخة للنشر، ستحتاج لإنشاء **KeyStore** واستخدام خيار `Generate Signed Bundle / APK`.
*   **الأخطاء الشائعة:** إذا واجهت خطأ يتعلق بـ `kapt` أو `Hilt`، تأكد من أن إصدار الـ JDK في إعدادات Android Studio هو 17 حصراً.
