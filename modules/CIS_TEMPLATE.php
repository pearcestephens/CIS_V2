<?php
declare(strict_types=1);

/**
 * CIS_TEMPLATE.php
 *
 * Global entrypoint for CIS module views.
 * 
 * Slim version — delegates to /core/ for bootstrap, security, meta,
 * ajax handling, and layout. Views provide content only.
 */

$root = $_SERVER['DOCUMENT_ROOT'];

// ---- Core system ----
require_once $root.'/core/bootstrap.php';
require_once $root.'/core/security.php';
require_once $root.'/core/auth.php';

cis_require_login([
  '/login',
  '/assets/services/pipeline/monitor.php', // if you want the dashboard to call it anon
]);

require_once $root.'/core/meta.php';
require_once $root.'/core/error.php';      // <-- add (profiling + error capture)
require_once $root.'/core/ajax_proxy.php';
require_once $root.'/core/layout.php';


// ---- Resolve module + view ----
$module = '';
$view   = '';
$uri = $_SERVER['REQUEST_URI'] ?? '';

if (preg_match('#/module/([a-z0-9_\-/]+)/([a-z0-9_\-]+)#i', $uri, $m)) {
    $module = strtolower($m[1]);
    $view   = strtolower($m[2]);
} else {
    if (!empty($_GET['module'])) {
        $module = preg_replace('/[^a-z0-9_\-\/]/i', '', (string)$_GET['module']);
    }
    if (!empty($_GET['view'])) {
        $view = preg_replace('/[^a-z0-9_\-]/i', '', (string)$_GET['view']);
    }
}

// ---- Load metadata ----
$meta = cis_resolve_meta($module, $view);

// ---- Load view content ----
$content = '';
if ($module && $view) {
    $base = $root . "/modules/{$module}";
    $candidates = [
        $base . "/views/{$view}.php",
        $base . "/{$view}.php",
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            ob_start();
            include $file;
            $content = ob_get_clean();
            break;
        }
    }
    if ($content === '') {
        $content = '<div class="text-muted">Module view not found.</div>';
    }
} else {
    $content = '<div class="text-muted">No module/view specified.</div>';
}

// ---- Render full layout ----
cis_render_layout($meta, $content);
