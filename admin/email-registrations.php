<?php
/**
 * NOVA Messenger Admin — تم دمج هذه الصفحة في registrations.php (طلبات التسجيل الموحدة)
 * تحويل تلقائي إلى الصفحة الجديدة.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
requireAdminLogin();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طلبات تسجيل البريد</title>
<style>
  body { font-family: 'Segoe UI', Tahoma, sans-serif; background:#f4f6fa; display:flex; align-items:center; justify-content:center; min-height:80vh; margin:0; }
  .box { background:#fff; border-radius:16px; padding:36px; text-align:center; box-shadow:0 6px 24px rgba(16,24,40,.08); max-width:380px; }
  .spinner { width:42px; height:42px; border:4px solid #e6e9f0; border-top-color:#4f46e5; border-radius:50%; animation:spin .9s linear infinite; margin:0 auto 18px; }
  @keyframes spin { to { transform:rotate(360deg); } }
  h2 { margin:0 0 6px; font-size:19px; color:#1a1a2e; }
  p { margin:0; color:#667085; font-size:14px; }
</style>
</head>
<body>
<div class="box">
  <div class="spinner"></div>
  <h2>نقلت هذه الصفحة</h2>
  <p>أُدمجت صفحة طلبات تسجيل البريد مع طلبات التسجيل في صفحة واحدة. جاري التحويل...</p>
</div>
<script>
// تبويب البريد في الصفحة الموحدة
setTimeout(() => { window.location.href = 'registrations.php?tab=email'; }, 900);
</script>
</body>
</html>
