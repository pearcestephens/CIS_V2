<?php
declare(strict_types=1);
/**
 * handler.php — __MODULE_NAME__ AJAX Router
 * Route: https://staff.vapeshed.co.nz/modules/__MODULE_SLUG__/ajax/handler.php
 */
require_once __DIR__ . '/tools.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mt_json(false, ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'POST only']);
    exit;
}

mt_require_login();
mt_verify_csrf();

$action = $_POST['action'] ?? '';
if (!$action) {
    mt_json(false, ['code' => 'NO_ACTION', 'message' => 'Missing action']);
    exit;
}

$map = [
    '__MODULE_SLUG__.ping' => ['ping.php', 'mt_ping'],
    '__MODULE_SLUG__.ai_generate' => ['ai_generate.php', 'mt_ai_generate'],
    '__MODULE_SLUG__.ai_save_drafts' => ['ai_save_drafts.php', 'mt_ai_save_drafts'],
    // Add your actions: '__MODULE_SLUG__.do_something' => function_name or [file, function]
];

if (!isset($map[$action])) {
    mt_json(false, ['code' => 'UNKNOWN_ACTION', 'message' => 'Unknown action: ' . $action]);
    exit;
}

try {
    $result = mt_retry(function () use ($map, $action) {
        $handler = $map[$action];
        if (is_callable($handler)) {
            return $handler();
        }
        if (is_array($handler) && count($handler) === 2) {
            [$file, $fn] = $handler;
            require_once __DIR__ . '/actions/' . $file;
            return $fn();
        }
        throw new RuntimeException('Invalid action mapping');
    });
    mt_json(true, $result);
} catch (Throwable $e) {
    mt_json(false, ['code' => 'SERVER_ERROR', 'message' => 'Unhandled error', 'detail' => $e->getMessage()]);
}
