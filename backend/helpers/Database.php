<?php
declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dbPath = __DIR__ . '/../config/nova.sqlite';
            $isNew = !file_exists($dbPath);
            
            self::$instance = new PDO("sqlite:" . $dbPath);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            if ($isNew) {
                self::initSchema(self::$instance);
            }
        }
        return self::$instance;
    }

    private static function initSchema(PDO $db): void
    {
        // سيتم استدعاء السكربت الخاص بإنشاء الجداول هنا إذا لزم الأمر
    }
}
