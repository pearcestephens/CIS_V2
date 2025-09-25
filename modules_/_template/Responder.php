<?php declare(strict_types=1);

namespace Modules\Template;

final class Responder
{
    public static function jsonSuccess(array $data = [], array $meta = [], int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'data'    => $data,
            'meta'    => $meta,
        ], JSON_UNESCAPED_SLASHES);
    }

    public static function jsonError(string $code, string $message, array $meta = [], int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
            'meta'    => $meta,
        ], JSON_UNESCAPED_SLASHES);
    }
}
