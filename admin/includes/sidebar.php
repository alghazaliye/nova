<?php
// Get pending reports count for badge

// Polyfill: avoid fatal error when php-mbstring is not installed
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null) {
        if ($length === null) {
            return substr($str, $start);
        }
        return substr($str, $start, $length);
    }
}
$pdo = getAdminDB();
$pendingReports = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
    $pendingReports = (int)$stmt->fetchColumn();
} catch (\Throwable $e) {}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
function navActive(string $page, string $current): string {
    return $page === $current ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="logo">N</div>
    <div><b><?= APP_NAME ?> Admin</b><small>لوحة إدارة المنصة</small></div>
  </div>

  <nav class="nav">
    <div class="navtitle">الرئيسية</div>
    <a href="index.php" class="<?= navActive('index', $currentPage) ?>">
      <span>▦</span> لوحة التحكم
    </a>
    <a href="users.php" class="<?= navActive('users', $currentPage) ?>">
      <span>♙</span> المستخدمون
    </a>
    <a href="groups.php" class="<?= navActive('groups', $currentPage) ?>">
      <span>♟</span> المجموعات
    </a>
    <a href="chats.php" class="<?= navActive('chats', $currentPage) ?>">
      <span>✉</span> المحادثات
    </a>
    <a href="message-edits.php" class="<?= navActive('message-edits', $currentPage) ?>">
      <span>✎</span> الرسائل المعدلة
    </a>
    <a href="message-deletions.php" class="<?= navActive('message-deletions', $currentPage) ?>">
      <span>🗑</span> الرسائل المحذوفة
    </a>
    <a href="subscriptions.php" class="<?= navActive('subscriptions', $currentPage) ?>">
      <span>✓</span> الحسابات المميزة
    </a>

    <div class="navtitle">التفاعل</div>
    <a href="calls.php" class="<?= navActive('calls', $currentPage) ?>">
      <span>☎</span> المكالمات
    </a>
    <a href="stories.php" class="<?= navActive('stories', $currentPage) ?>">
      <span>◌</span> الحالات
    </a>
    <a href="reports.php" class="<?= navActive('reports', $currentPage) ?>">
      <span>⚑</span> البلاغات
      <?php if ($pendingReports > 0): ?>
        <em class="count"><?= $pendingReports ?></em>
      <?php endif; ?>
    </a>
    <a href="notifications.php" class="<?= navActive('notifications', $currentPage) ?>">
      <span>♢</span> الإشعارات
    </a>

    <div class="navtitle">النظام</div>
    <a href="monitoring.php" class="<?= navActive('monitoring', $currentPage) ?>">
      <span>📊</span> المراقبة الحية
    </a>
    <a href="admins.php" class="<?= navActive('admins', $currentPage) ?>">
      <span>♙</span> المشرفون والصلاحيات
    </a>
    <a href="services.php" class="<?= navActive('services', $currentPage) ?>">
      <span>🔧</span> إعدادات الخدمات
    </a>
    <a href="audit.php" class="<?= navActive('audit', $currentPage) ?>">
      <span>␷</span> سجل العمليات
    </a>
    <a href="storage.php" class="<?= navActive('storage', $currentPage) ?>">
      <span>▤</span> إحصائيات الوسائط
    </a>
    <a href="settings.php" class="<?= navActive('settings', $currentPage) ?>">
      <span>⚙</span> الإعدادات
    </a>
  </nav>

  <div style="margin-top:auto; padding:10px 0;">
    <div style="display:flex; align-items:center; gap:10px; padding:10px; background:var(--surface2); border-radius:14px;">
      <div class="avatar" style="width:34px; height:34px; font-size:14px;"><?= mb_substr($admin['name'], 0, 1) ?></div>
      <div style="flex:1; min-width:0;">
        <div style="font-size:12px; font-weight:800; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($admin['name']) ?></div>
        <div style="font-size:10px; color:var(--muted);"><?= htmlspecialchars($admin['role_name']) ?></div>
      </div>
      <a href="logout.php" style="color:var(--muted); font-size:16px;" title="تسجيل الخروج">⏻</a>
    </div>
  </div>
</aside>

<main class="main">
  <header class="top">
    <button class="menu" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <h1 id="topTitle"><?= htmlspecialchars($pageTitle ?? 'لوحة التحكم') ?></h1>
    <div class="search">
      <span>⌕</span>
      <input placeholder="بحث سريع..." oninput="if(typeof quickSearch === 'function') quickSearch(this.value)">
    </div>
    <div class="top-actions">
      <button class="icon" onclick="alert('لا توجد إشعارات جديدة')">♢</button>
      <button class="icon" onclick="toggleTheme()">☾</button>
      <a href="settings.php" class="icon" style="text-decoration:none;">⚙</a>
    </div>
  </header>
  <div class="content">
