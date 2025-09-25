<?php declare(strict_types=1);

/**
 * cisv2/router.php
 * Minimal CIS router: ?module=&view=
 * Example:
 *   /cisv2/router.php?module=transfers/stock&view=pack&transfer=123
 */

require_once __DIR__.'/bootstrap.php';

$module = preg_replace('~[^a-z0-9/_]+~i', '', $_GET['module'] ?? '');
$view   = preg_replace('~[^a-z0-9_]+~i',  '', $_GET['view']   ?? 'index');

// Allowlist modules/views (expand as needed)
$allowed = [
    'transfers/stock' => ['pack','receive','dispatch'],
];

if (!isset($allowed[$module])) {
    http_response_code(404); exit("Unknown module: $module");
}
if (!in_array($view, $allowed[$module], true)) {
    http_response_code(404); exit("Unknown view: $view");
}

$modPath = __DIR__."/modules/$module";
$ctlPath = "$modPath/controllers/$view/index.php";
$viewPath= "$modPath/views/$view.php";
$metaPath= "$modPath/views/$view.meta.php";

if (!is_file($ctlPath)) {
    http_response_code(404); exit("Controller not found: $ctlPath");
}

// --- Run controller ---
$meta = ['title'=>'CIS']; $content = '';
$ctx  = [
    'pdo'    => $GLOBALS['cisv2']['pdo'],
    'env'    => $GLOBALS['cisv2']['env'],
    'user'   => $_SESSION['user'] ?? null,
    'params' => $_GET,
];

ob_start();
require $ctlPath;   // controller can override $meta/$content
if (!$content && is_file($viewPath)) {
    require $viewPath;
}
$content = ob_get_clean() ?: $content;

// Fallback meta
if ($meta === ['title'=>'CIS'] && is_file($metaPath)) {
    $meta = require $metaPath;
}

// --- Render ---
cis_render_layout($meta, $content);
