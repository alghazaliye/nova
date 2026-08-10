<?php
/**
 * NOVA Messenger - JSON Response Helper
 */

declare(strict_types=1);

class Response
{
    public static function success(mixed $data = null, string $message = 'تم التنفيذ بنجاح', int $code = 200): never
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message = 'حدث خطأ', string $errorCode = 'ERROR', int $code = 400, mixed $errors = null): never
    {
        http_response_code($code);
        $body = [
            'success'    => false,
            'message'    => $message,
            'error_code' => $errorCode,
        ];
        if ($errors !== null) {
            $body['errors'] = $errors;
        }
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function unauthorized(string $message = 'غير مصرح لك بالوصول'): never
    {
        self::error($message, 'UNAUTHORIZED', 401);
    }

    public static function forbidden(string $message = 'ليس لديك صلاحية لتنفيذ هذا الإجراء'): never
    {
        self::error($message, 'FORBIDDEN', 403);
    }

    public static function notFound(string $message = 'العنصر المطلوب غير موجود'): never
    {
        self::error($message, 'NOT_FOUND', 404);
    }

    public static function validationError(array $errors): never
    {
        self::error('بيانات غير صحيحة', 'VALIDATION_ERROR', 422, $errors);
    }

    public static function paginated(array $items, int $total, int $page, int $limit): never
    {
        self::success([
            'items'       => $items,
            'pagination'  => [
                'total'        => $total,
                'page'         => $page,
                'limit'        => $limit,
                'total_pages'  => (int) ceil($total / $limit),
                'has_more'     => ($page * $limit) < $total,
            ],
        ]);
    }
}
