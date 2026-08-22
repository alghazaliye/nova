<?php
declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

try {
    $pdo = Database::getInstance();
    $dbType = Database::getType();
    echo "Database Type: " . $dbType . "\n";
    
    $tables = [];
    if ($dbType === 'mysql' || (isset($_ENV['DB_TYPE']) && $_ENV['DB_TYPE'] === 'turso')) {
        // For Turso (libSQL) or MySQL
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        if (!$stmt) {
            // Try MySQL style if that fails
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } else {
        // SQLite
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo "Tables Found: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "\nTable: $table\n";
        try {
            $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll();
            foreach ($cols as $col) {
                echo "  - {$col['name']} ({$col['type']})" . ($col['notnull'] ? ' NOT NULL' : '') . ($col['dflt_value'] !== null ? " DEFAULT {$col['dflt_value']}" : "") . "\n";
            }
        } catch (Exception $e) {
            echo "  Error reading columns: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Audit Failed: " . $e->getMessage() . "\n";
}
