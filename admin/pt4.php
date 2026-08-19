<?php
require_once __DIR__ . '/includes/config.php';
$login = trim($_POST['login'] ?? '');
$password = $_POST['password'] ?? '';
echo "login=[$login] password_len=" . strlen($password) . " postkeys=" . implode(',', array_keys($_POST)) . " method=" . $_SERVER['REQUEST_METHOD'] . "\n";
