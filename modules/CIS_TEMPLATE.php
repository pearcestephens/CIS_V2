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

$docRoot    = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$cisv2Root  = dirname(__DIR__);
$hasCisV2   = is_file($cisv2Root . '/bootstrap.php');
$runtimeRoot = $hasCisV2 ? $cisv2Root : $docRoot;

// ---- Core system ----
require_once $runtimeRoot . '/bootstrap.php';
require_once $runtimeRoot . '/core/security.php';
require_once $runtimeRoot . '/core/auth.php';

cis_require_login([
  '/login',
  '/assets/services/pipeline/monitor.php', // if you want the dashboard to call it anon
]);

require_once $runtimeRoot . '/core/meta.php';
require_once $runtimeRoot . '/core/error.php';      // <-- add (profiling + error capture)
require_once $runtimeRoot . '/core/ajax_proxy.php';
require_once $runtimeRoot . '/core/layout.php';


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
    $moduleRoots = [$cisv2Root . '/modules'];
    if ($docRoot && $docRoot !== $cisv2Root) {
        $moduleRoots[] = $docRoot . '/modules';
    }

    foreach ($moduleRoots as $rootPath) {
        $base = rtrim($rootPath, '/') . "/{$module}";
        $candidates = [
            $base . "/views/{$view}.php",
            $base . "/{$view}.php",
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                ob_start();
                include $file;
                $content = ob_get_clean();
                break 2;
            }
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
