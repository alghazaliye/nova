<?php
require_once __DIR__ . '/includes/config.php';
$sessId = session_id();
$_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_POST['_csrf'] ?? '';
$eq = hash_equals($_SESSION['login_csrf'], $csrf) ? 'CSRF_OK' : 'CSRF_FAIL';
echo "$eq | sess=$sessId | csrf_sent=[$csrf] stored=[{$_SESSION['login_csrf']}] | post=" . json_encode($_POST) . "\n";
