<?php
header('Content-Type: text/plain; charset=utf-8');
echo "method=" . $_SERVER['REQUEST_METHOD'] . "\n";
echo "login=" . ($_POST['login'] ?? '(empty)') . "\n";
echo "password=" . ($_POST['password'] ?? '(empty)') . "\n";
echo "raw=" . file_get_contents('php://input') . "\n";
