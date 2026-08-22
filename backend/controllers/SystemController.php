<?php
/**
 * NOVA Messenger - System Controller
 * Advanced health checks and monitoring
 */

declare(strict_types=1);

class SystemController
{
    private PDO|TursoPdo $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * GET /api/v1/system/status
     * فحص حالة النظام بالكامل بما في ذلك قاعدة البيانات والسرعة
     */
    public function status(): void
    {
        $start = microtime(true);
        $dbStatus = 'offline';
        $dbLatency = 0;
        $dbType = trim($_ENV['DB_TYPE'] ?? 'sqlite');

        try {
            $dbStart = microtime(true);
            $this->pdo->query('SELECT 1')->execute();
            $dbEnd = microtime(true);
            
            $dbStatus = 'online';
            $dbLatency = round(($dbEnd - $dbStart) * 1000, 2);
        } catch (Exception $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        $end = microtime(true);
        $totalLatency = round(($end - $start) * 1000, 2);

        Response::success([
            'status' => 'ok',
            'timestamp' => date('c'),
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'database' => [
                'type' => $dbType,
                'status' => $dbStatus,
                'latency_ms' => $dbLatency
            ],
            'server' => [
                'load' => function_exists('sys_getloadavg') ? sys_getloadavg()[0] : 'n/a',
                'php_version' => PHP_VERSION,
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB'
            ],
            'api' => [
                'total_latency_ms' => $totalLatency
            ]
        ]);
    }
}
