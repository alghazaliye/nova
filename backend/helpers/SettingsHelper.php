<?php
/**
 * SettingsHelper — shared helpers for enforcing admin-controlled app settings.
 *
 * Admin settings live in the `app_settings` table and gate features:
 *   allow_stories / allow_groups / allow_calls  → feature flags (0 = disabled)
 *   story_duration_hrs / edit_time_limit_minutes / delete_time_limit_minutes → limits
 *
 * Usage from any controller:
 *   SettingsHelper::enforceFeature($this->pdo, 'allow_calls');
 *   $limit = SettingsHelper::getSetting($this->pdo, 'edit_time_limit_minutes', '0');
 */
declare(strict_types=1);

class SettingsHelper
{
    /**
     * Fetch an app setting value (string). Returns $default when missing.
     */
    public static function getSetting(PDO $pdo, string $key, string $default = ''): string
    {
        static $cache = [];
        $cacheKey = $key;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        try {
            $stmt = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $value = $row ? (string)($row['setting_value'] ?? $default) : $default;
        } catch (\Throwable $e) {
            error_log('SettingsHelper::getSetting error: ' . $e->getMessage());
            $value = $default;
        }
        $cache[$cacheKey] = $value;
        return $value;
    }

    /**
     * Block the request with FEATURE_DISABLED (HTTP 503) if the given
     * app setting is not "1". The admin panel toggles allow_stories,
     * allow_groups and allow_calls, so this makes the backend the
     * single source of truth (a direct POST/curl is rejected too).
     */
    public static function enforceFeature(PDO $pdo, string $settingKey, string $featureName): void
    {
        $value = self::getSetting($pdo, $settingKey, '1');
        if ($value !== '1') {
            Response::error(
                "ميزة {$featureName} غير مفعّلة من قبل الإدارة",
                'FEATURE_DISABLED',
                503
            );
        }
    }
}
