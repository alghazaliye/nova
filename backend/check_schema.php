<?php
/**
 * NOVA Messenger - Turso Schema Verification Tool
 */
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

// Mock environment for testing if not set
if (empty($_ENV['DB_TYPE'])) {
    $_ENV['DB_TYPE'] = 'sqlite'; // Default to local for checking file existence
}

try {
    $db = Database::getInstance();
    $type = Database::getType();
    
    echo "--- Database Status ---\n";
    echo "Type: " . strtoupper($type) . "\n";
    echo "Status: ONLINE\n\n";

    $requiredTables = [
        'users' => ['id', 'name', 'phone', 'is_blocked'],
        'messages' => ['id', 'sender_id', 'conversation_id'],
        'conversations' => ['id', 'uuid', 'created_by'],
        'sessions' => ['id', 'user_id'],
        'user_devices' => ['id', 'user_id', 'device_uuid'],
        'user_subscriptions' => ['id', 'user_id', 'plan_id'],
        'user_bans' => ['id', 'user_id', 'banned_by'],
        'audit_logs' => ['id', 'admin_id', 'entity_type', 'entity_id']
    ];

    echo "--- Schema Audit ---\n";
    foreach ($requiredTables as $table => $columns) {
        echo "Table: {$table} ... ";
        try {
            // Check table existence
            $stmt = $db->query("SELECT 1 FROM {$table} LIMIT 1");
            echo "[OK]\n";
            
            // Check specific columns (simulated for SQLite/Turso compatibility)
            foreach ($columns as $col) {
                echo "  - Column: {$col} ... ";
                try {
                    $db->query("SELECT {$col} FROM {$table} LIMIT 1");
                    echo "[FOUND]\n";
                } catch (\Exception $e) {
                    echo "[MISSING]\n";
                }
            }
        } catch (\Exception $e) {
            echo "[NOT FOUND]\n";
        }
        echo "\n";
    }

} catch (\Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
