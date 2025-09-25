<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';

if (!function_exists('pdo')) { // convenience alias
    function pdo(): \PDO { return db(); }
}
if (!function_exists('json_ok')) {
    function json_ok(array $data = [], int $status = 200): never { cis_json(true, $data, null, $status); }
}
if (!function_exists('json_err')) {
    function json_err(string $code, string $message, int $status = 400, array $details = []): never {
        cis_json(false, null, ['code'=>$code, 'message'=>$message, 'details'=>$details ?: null], $status);
    }
}
