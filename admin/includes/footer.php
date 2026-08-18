  </div><!-- .content -->
</main><!-- .main -->
</div><!-- .layout -->

<?php
// ══════════════════════════════════════════════════════════════════════
// Admin JWT bootstrap for OTP pages (Bearer token API calls).
// The admin panel uses PHP sessions; API endpoints expect a JWT Bearer
// token. When a valid session exists, we mint (or reuse) a short-lived
// JWT server-side and expose it to localStorage('adminToken').
// ══════════════════════════════════════════════════════════════════════
$_novaJwt = '';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['admin_id']) && !empty($_SESSION['last_jwt_at']) && (time() - (int)$_SESSION['last_jwt_at']) < 300 && !empty($_SESSION['admin_jwt'])) {
    $_novaJwt = (string)$_SESSION['admin_jwt'];
} else {
    try {
        if (!class_exists('Database')) {
            ob_start();
            require_once __DIR__ . '/../../backend/config/app.php';
            ob_end_clean();
        }
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT a.id, a.name, a.email, a.role_id, r.name AS role_name FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.id = ? AND a.is_active = 1 LIMIT 1');
        $stmt->execute([(int)$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && class_exists('JwtHelper')) {
            $_novaJwt = JwtHelper::generate([
                'user_id'    => (int)$admin['id'],
                'role'       => 'admin',
                'admin_role' => $admin['role_name'],
                'iat'        => time(),
                'exp'        => time() + 6 * 3600,
            ]);
            $_SESSION['last_jwt_at'] = time();
            $_SESSION['admin_jwt']   = $_novaJwt;
        }
    } catch (Throwable $e) {

        $_novaJwt = '';
    }
}
?>
<script>
// Theme Toggling
function toggleTheme() {
  const body = document.body;
  const currentTheme = body.getAttribute('data-theme');
  if (currentTheme === 'dark') {
    body.removeAttribute('data-theme');
    localStorage.setItem('admin-theme', 'light');
  } else {
    body.setAttribute('data-theme', 'dark');
    localStorage.setItem('admin-theme', 'dark');
  }
}

// Persist server-minted admin JWT for API Bearer calls (OTP pages)
(function() {
  <?php if ($_novaJwt !== ''): ?>
  if ('<?php echo $_novaJwt; ?>' !== '') {
    localStorage.setItem('adminToken', '<?php echo $_novaJwt; ?>');
  }
  <?php endif; ?>
})();

// Load saved theme
(function() {
  const savedTheme = localStorage.getItem('admin-theme');
  if (savedTheme === 'dark') {
    document.body.setAttribute('data-theme', 'dark');
  }
})();

// Auto-hide alerts
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transition = '0.5s';
    setTimeout(() => el.style.display = 'none', 500);
  }, 4000);
});

// Confirm actions
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'هل أنت متأكد؟')) {
      e.preventDefault();
    }
  });
});
</script>
</body>
</html>
