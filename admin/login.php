<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

// Redirect if already logged in
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['login_csrf'] ?? '', $csrf)) {
        $error = 'انتهت صلاحية النموذج، أعد المحاولة';
    }
    $login    = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$error && $login && $password) {
        $pdo  = getAdminDB();
        // القبول بالاسم أو البريد الإلكتروني
        $stmt = $pdo->prepare('SELECT id, name, password_hash, is_active FROM admins WHERE email = ? OR name = ? LIMIT 1');
        $stmt->execute([$login, $login]);
        $admin = $stmt->fetch();

        if ($admin && $admin['is_active'] && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['id'];
            session_regenerate_id(true);

            $pdo->prepare('UPDATE admins SET last_login_at = datetime('now') WHERE id = ?')->execute([$admin['id']]);

            header('Location: index.php');
            exit;
        } else {
            $error = 'بيانات الدخول أو كلمة المرور غير صحيحة';
        }
    } else {
        $error = 'يرجى إدخال الاسم أو البريد الإلكتروني وكلمة المرور';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تسجيل الدخول — <?= APP_NAME ?> Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Noto Kufi Arabic', system-ui, sans-serif;
      background: #0f1117;
      color: #f0f0f5;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-box {
      background: #1a1d27;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 20px;
      padding: 40px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.4);
    }
    .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
    .logo-icon {
      width: 48px; height: 48px;
      background: linear-gradient(135deg, #5b5ce2, #7c3aed);
      border-radius: 14px;
      display: grid; place-items: center;
      font-weight: 900; font-size: 22px; color: white;
    }
    .logo-text { font-size: 22px; font-weight: 700; }
    .logo-sub { font-size: 13px; color: #8b8fa8; }
    h2 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
    .subtitle { color: #8b8fa8; font-size: 14px; margin-bottom: 28px; }
    .form-group { margin-bottom: 18px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #8b8fa8; margin-bottom: 7px; }
    input {
      width: 100%; padding: 12px 16px;
      background: #232635; border: 1px solid rgba(255,255,255,0.08);
      border-radius: 12px; color: #f0f0f5; font-size: 15px;
      font-family: inherit; transition: 0.15s;
    }
    input:focus { outline: none; border-color: #5b5ce2; }
    .error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; padding: 12px 16px; border-radius: 10px; font-size: 14px; margin-bottom: 18px; }
    .btn-login {
      width: 100%; padding: 14px;
      background: linear-gradient(135deg, #5b5ce2, #7c3aed);
      border: none; border-radius: 12px;
      color: white; font-size: 16px; font-weight: 700;
      cursor: pointer; font-family: inherit; transition: 0.2s;
    }
    .btn-login:hover { opacity: 0.9; transform: translateY(-1px); }
  </style>
</head>
<body>
<div class="login-box">
  <div class="logo">
    <div class="logo-icon">N</div>
    <div>
      <div class="logo-text"><?= APP_NAME ?></div>
      <div class="logo-sub">لوحة التحكم</div>
    </div>
  </div>
  <h2>تسجيل الدخول</h2>
  <p class="subtitle">أدخل بيانات حسابك للوصول إلى لوحة التحكم</p>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-group">
      <label>الاسم أو البريد الإلكتروني</label>
      <input type="text" name="login" required placeholder="محمد أو admin@example.com"
             value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>كلمة المرور</label>
      <input type="password" name="password" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn-login">تسجيل الدخول</button>
  </form>
</div>
</body>
</html>
