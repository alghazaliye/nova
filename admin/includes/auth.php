<?php
/**
 * NOVA Messenger - Admin Auth Guard
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function requireAdminLogin(): array {
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }

    $pdo  = getAdminDB();
    $stmt = $pdo->prepare(
        'SELECT a.id, a.name, a.email, a.role_id, r.name AS role_name
         FROM admins a JOIN roles r ON r.id = a.role_id
         WHERE a.id = ? AND a.is_active = 1 LIMIT 1'
    );
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    return $admin;
}

function hasPermission(array $admin, string $permission): bool {
    // super_admin has full access to everything
    if (($admin['role_name'] ?? '') === 'super_admin') {
        return true;
    }

    $pdo  = getAdminDB();
    $stmt = $pdo->prepare(
        'SELECT 1 FROM role_permissions rp
         JOIN permissions p ON p.id = rp.permission_id
         WHERE rp.role_id = ? AND p.name = ? LIMIT 1'
    );
    $stmt->execute([$admin['role_id'], $permission]);
    return (bool)$stmt->fetch();
}

function requirePermission(array $admin, string $permission): void {
    if (!hasPermission($admin, $permission)) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}

function logAudit(array $admin, string $action, string $entityType = '', int $entityId = 0, string $description = ''): void {
    $pdo = getAdminDB();
    $pdo->prepare(
        'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, datetime("now"))'
    )->execute([
        $admin['id'], $action, $entityType, $entityId ?: null, $description,
        $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token mismatch');
    }
}
