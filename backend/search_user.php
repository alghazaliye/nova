<?php
/**
 * NOVA Messenger - User Search Tool
 */
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$phone = '+96656876786378';

try {
    $db = Database::getInstance();
    $type = Database::getType();
    
    echo "Active Database: " . strtoupper($type) . "\n";
    
    $stmt = $db->prepare("SELECT id, name, phone, created_at FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "USER FOUND:\n";
        echo "ID: " . $user['id'] . "\n";
        echo "Name: " . ($user['name'] ?: 'N/A') . "\n";
        echo "Phone: " . $user['phone'] . "\n";
        echo "Created At: " . $user['created_at'] . "\n";
    } else {
        echo "USER NOT FOUND in " . strtoupper($type) . "\n";
        
        // If not found in current, check if we are in local but should be Turso
        if ($type === 'sqlite') {
            echo "Note: You are currently checking the local SQLite database. If the user was created on Render, they might be in Turso.\n";
        }
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
