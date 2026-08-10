<?php
/**
 * NOVA Messenger - JWT Helper (HS256)
 * Simple JWT implementation without external dependencies.
 */

declare(strict_types=1);

class JwtHelper
{
    private static function getSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }
        return $secret;
    }

    public static function generate(array $payload): string
    {
        $header  = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode($payload));
        $sig     = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );
        return "{$header}.{$payload}.{$sig}";
    }

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $sig] = $parts;

        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );

        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }

        $data = json_decode(self::base64UrlDecode($payload), true);

        if (!is_array($data)) {
            return null;
        }

        if (isset($data['exp']) && $data['exp'] < time()) {
            return null; // Token expired
        }

        return $data;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
