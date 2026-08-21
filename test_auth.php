<?php
$_ENV["JWT_SECRET"] = "nova-dev-secret-key-2026-xyz";
$_ENV["APP_ENV"] = "development";
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOjE0MCwiaWF0IjoxNzg3MzE5MzY1LCJleHAiOjE3ODc0MTg0NjUsImp0aSI6ImFkYjE4OTE4ODg2NWNiODgifQ.JJlPanLpxHUGDNbdwOU0q1958RA-KSa9l3BOE1P4bZk";
require_once "/home/ubuntu/nova_new/backend/config/app.php";
try {
    $auth = AuthMiddleware::authenticate();
    echo "Auth Success: " . json_encode($auth) . "\n";
} catch (Exception $e) {
    echo "Auth Failed: " . $e->getMessage() . "\n";
}
