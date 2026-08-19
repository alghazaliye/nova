<?php
/**
 * NOVA Messenger - Authentication Middleware
 * Validates Bearer Token from Authorization header.
 */

declare(strict_types=1);

class AuthMiddleware
{
    /**
     * Authenticate the request and return the authenticated user array.
     * Terminates with 401 if authentication fails.
     */
    public static function authenticate(): array
    {
        $authHeader = nova_get_auth_header() ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('يجب تسجيل الدخول أولاً');
        }

        $token   = substr($authHeader, 7);
        $payload = JwtHelper::verify($token);

        if ($payload === null) {
            Response::unauthorized('الجلسة منتهية أو غير صالحة، يرجى تسجيل الدخول مجدداً');
        }

        // Verify session still exists and is not revoked
        $pdo  = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT s.id, s.revoked_at, u.id AS user_id, u.uuid, u.name, u.phone, u.is_blocked
             FROM sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.token_hash = ? AND s.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $session = $stmt->fetch();

        if (!$session) {
            Response::unauthorized('الجلسة غير موجودة أو منتهية');
        }

        if ($session['revoked_at'] !== null) {
            Response::unauthorized('تم إلغاء هذه الجلسة');
        }

        if ($session['is_blocked']) {
            Response::forbidden('تم حظر حسابك. يرجى التواصل مع الدعم الفني');
        }

        return $session;
    }

    /**
     * Optionally authenticate - returns null if no token provided.
     */
    public static function optionalAuth(): ?array
    {
        $authHeader = nova_get_auth_header() ?? '';
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        return self::authenticate();
    }
}
