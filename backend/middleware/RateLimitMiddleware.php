<?php
/**
 * NOVA Messenger - Rate Limit Middleware
 * Uses APCu if available, otherwise falls back to file-based rate limiting.
 */

declare(strict_types=1);

class RateLimitMiddleware
{
    public static function check(string $identifier, int $maxRequests = 60, int $windowSeconds = 60): void
    {
        $key   = 'rate_limit_' . md5($identifier);
        $now   = time();
        $limit = (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? $maxRequests);
        $window = (int)($_ENV['RATE_LIMIT_WINDOW_SECONDS'] ?? $windowSeconds);

        if (function_exists('apcu_fetch')) {
            $data = apcu_fetch($key);
            if ($data === false) {
                apcu_store($key, ['count' => 1, 'start' => $now], $window);
                return;
            }
            if ($data['count'] >= $limit) {
                header('Retry-After: ' . ($data['start'] + $window - $now));
                Response::error('تجاوزت الحد المسموح به من الطلبات. يرجى الانتظار قليلاً', 'RATE_LIMIT_EXCEEDED', 429);
            }
            apcu_store($key, ['count' => $data['count'] + 1, 'start' => $data['start']], $window);
            return;
        }

        // Portable fallback for XAMPP/shared hosting: an atomic file counter.
        $directory = $_ENV['RATE_LIMIT_STORAGE_PATH'] ?? dirname(__DIR__) . '/storage/rate-limit';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('Rate limiter storage cannot be created: ' . $directory);
            Response::error('تعذر تفعيل حماية الطلبات', 'RATE_LIMIT_UNAVAILABLE', 503);
        }

        $file = $directory . DIRECTORY_SEPARATOR . $key . '.json';
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            Response::error('تعذر تفعيل حماية الطلبات', 'RATE_LIMIT_UNAVAILABLE', 503);
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                Response::error('تعذر تفعيل حماية الطلبات', 'RATE_LIMIT_UNAVAILABLE', 503);
            }
            $contents = stream_get_contents($handle);
            $data = json_decode($contents ?: '', true);
            if (!is_array($data) || ($now - (int)($data['start'] ?? 0)) >= $window) {
                $data = ['count' => 0, 'start' => $now];
            }
            if ((int)$data['count'] >= $limit) {
                $retryAfter = max(1, (int)$data['start'] + $window - $now);
                header('Retry-After: ' . $retryAfter);
                Response::error('تم تجاوز الحد المسموح من الطلبات. يرجى الانتظار قليلًا', 'RATE_LIMIT_EXCEEDED', 429);
            }
            $data['count']++;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function checkByIp(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (filter_var($_ENV['TRUST_PROXY'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($forwarded !== '') {
                $ip = trim(explode(',', $forwarded)[0]);
            }
        }
        self::check($ip);
    }
}
