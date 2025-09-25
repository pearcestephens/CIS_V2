<?php
declare(strict_types=1);

/**
 * /core/ajax_proxy.php
 * POST → routes to module ajax handlers.
 */

require_once $_SERVER['DOCUMENT_ROOT'].'/core/csrf.php';
cis_csrf_or_json_400();   // returns JSON 400 if bad

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;

$act = $_POST['ajax_action'] ?? ($_POST['action'] ?? null);
if (!$act) return;

$uri = $_SERVER['REQUEST_URI'] ?? '';
$module = '';
if (preg_match('#/module/([a-z0-9_\-]+)/#i', $uri, $m)) {
    $module = strtolower($m[1]);
} elseif (!empty($_GET['module'])) {
    $module = preg_replace('/[^a-z0-9_\-]/i','', (string)$_GET['module']);
}

if (strpos($module, '..') !== false) $module = '';

try {
    if ($module) {
        $handler = $_SERVER['DOCUMENT_ROOT'] . "/modules/{$module}/ajax/handler.php";
        if (is_file($handler)) { include $handler; exit; }
    }
    $fallback = $_SERVER['DOCUMENT_ROOT'] . "/modules/purchase-orders/ajax/handler.php";
    if (is_file($fallback)) { include $fallback; exit; }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>'invalid_handler']);
    exit;
} catch (Throwable $e) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'error'=>'ajax_proxy_error','msg'=>$e->getMessage()]);
    exit;
}
