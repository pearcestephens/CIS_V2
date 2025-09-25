<?php
declare(strict_types=1);

/**
 * cisv2/router.php
 * Routes ?module=&view=&... to corresponding controller/view.
 * Example:
 *   /cisv2/router.php?module=transfers/stock&view=pack&transfer=123
 */

require __DIR__.'/bootstrap.php';

$module = trim((string)($_GET['module'] ?? ''));
$view   = trim((string)($_GET['view']   ?? ''));

// Very tight allowlist (expand as you add modules)
$allowModules = [
    'transfers/stock' => [
        'views'       => ['pack'],
        'controllers' => ['pack','dispatch'],
    ]
];

if (!isset($allowModules[$module])) {
    http_response_code(404); exit('Unknown module');
}
if ($view !== '' && !in_array($view, $allowModules[$module]['views'], true)) {
    http_response_code(404); exit('Unknown view');
}

// Resolve controller (default to same as view)
$controller = ($view !== '') ? $view : 'dispatch';

// Build paths
$modPath = CISV2_ROOT."/modules/{$module}";
$ctlPath = $modPath."/controllers/{$controller}.php";
$viewPath= $modPath."/views/{$view}.php";
$metaPath= $modPath."/views/{$view}.meta.php";

// Run controller -> must set $content and $meta (array)
if (!is_file($ctlPath)) { http_response_code(404); exit('Controller not found'); }

$meta = $content = null;

/** Provide minimal execution context */
$ctx = [
    'pdo'    => $GLOBALS['cisv2']['pdo'],
    'env'    => $GLOBALS['cisv2']['env'],
    'user'   => $_SESSION['user'] ?? null, // align to legacy user object if present
    'params' => $_GET,
];

// Include controller (it should populate $content & $meta)
require $ctlPath;

if (!is_array($meta)) {
    // Try meta file if controller didn't set it
    if (is_file($metaPath)) {
        $meta = (static function() use ($metaPath) { return require $metaPath; })();
    } else {
        $meta = ['title' => 'CIS'];
    }
}

if (!is_string($content)) {
    // Fallback to view file render
    if (is_file($viewPath)) {
        ob_start();
        $params = $_GET; // make $params visible in view
        require $viewPath;
        $content = (string)ob_get_clean();
    } else {
        $content = '<div class="alert alert-warning">No view content.</div>';
    }
}

cis_render_layout($meta, $content);
