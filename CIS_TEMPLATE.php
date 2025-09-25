<?php
declare(strict_types=1);

/**
 * CIS_TEMPLATE.php
 * Slim global entrypoint for CIS module views (supports nested modules).
 * - Boots core (security/login/meta/layout)
 * - Locates /modules/{module}/views/{view}.php (module can include slashes)
 * - Renders via cis_render_layout(), or a safe CISv2 fallback layout
 */

$root = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__, 1);

// ---- Core system ----
require_once $root . '/core/bootstrap.php';
require_once $root . '/core/security.php';
require_once $root . '/core/auth.php';
cis_require_login([
  '/login',
]);

require_once $root . '/core/meta.php';
require_once $root . '/core/error.php';
require_once $root . '/core/ajax_proxy.php';
// layout is optional (we have a fallback below)
@require_once $root . '/core/layout.php';

// ---- Resolve module + view ----
$uri  = (string)($_SERVER['REQUEST_URI'] ?? '');
$module = '';
$view   = '';

if (preg_match('#/module/([a-z0-9_\-/]+)/([a-z0-9_\-]+)#i', $uri, $m)) {
  $module = strtolower($m[1]);
  $view   = strtolower($m[2]);
} else {
  if (!empty($_GET['module'])) $module = preg_replace('/[^a-z0-9_\-\/]/i', '', (string)$_GET['module']);
  if (!empty($_GET['view']))   $view   = preg_replace('/[^a-z0-9_\-]/i', '', (string)$_GET['view']);
}

$module = trim($module, '/');

// ---- Build meta (hide breadcrumbs for pack/receive) ----
$meta = function_exists('cis_resolve_meta') ? cis_resolve_meta($module, $view) : [];
if (is_array($meta)) {
  if (preg_match('#^transfers(?:/stock)?$#i', $module) && in_array($view, ['pack','receive'], true)) {
    $meta['show_breadcrumb'] = false;
    if (empty($meta['title'])) $meta['title'] = ($view === 'pack' ? 'Pack Transfer' : 'Receive Transfer');
  }
}

// ---- Load view content ----
$content = '';
if ($module !== '' && $view !== '') {
  $base = $root . '/modules/' . $module;
  $candidates = [
    $base . '/views/' . $view . '.php',
    $base . '/' . $view . '.php',
  ];
  foreach ($candidates as $file) {
    if (is_file($file)) {
      ob_start();
      include $file;
      $content = (string)ob_get_clean();
      break;
    }
  }
  if ($content === '') {
    $content = '<div class="container mt-4"><div class="alert alert-warning">Module view not found.</div></div>';
  }
} else {
  $content = '<div class="container mt-4"><div class="text-muted">No module/view specified.</div></div>';
}

// ---- Render via core layout if available ----
if (function_exists('cis_render_layout')) {
  cis_render_layout($meta, $content);
  exit;
}

/**
 * Fallback CISv2 layout (header + sidebar + main + footer)
 * Only used if core/layout.php didn’t provide cis_render_layout().
 */
$tplRoots = [
  $root . '/assets/templates/cisv2',
  $root . '/assets/template/cisv2', // compatibility
];

$tpl = [
  'html_header' => null,
  'header'      => null,
  'sidemenu'    => null,
  'footer'      => null,
  'html_footer' => null,
];

foreach ($tplRoots as $tr) {
  if (is_file($tr . '/html-header.php') && $tpl['html_header'] === null) $tpl['html_header'] = $tr . '/html-header.php';
  if (is_file($tr . '/header.php')      && $tpl['header']      === null) $tpl['header']      = $tr . '/header.php';
  if (is_file($tr . '/sidemenu.php')    && $tpl['sidemenu']    === null) $tpl['sidemenu']    = $tr . '/sidemenu.php';
  if (is_file($tr . '/footer.php')      && $tpl['footer']      === null) $tpl['footer']      = $tr . '/footer.php';
  if (is_file($tr . '/html-footer.php') && $tpl['html_footer'] === null) $tpl['html_footer'] = $tr . '/html-footer.php';
}

$PAGE_TITLE = isset($meta['title']) ? (string)$meta['title'] : 'CIS';
$GLOBALS['PAGE_TITLE'] = $PAGE_TITLE;

if ($tpl['html_header']) include $tpl['html_header'];
if ($tpl['header'])      include $tpl['header'];
?>
<div class="app-body">
  <?php if ($tpl['sidemenu']) include $tpl['sidemenu']; ?>
  <main class="main">
    <div class="container-fluid">
      <div class="fade-in">
        <?= $content ?>
      </div>
    </div>
  </main>
  <?php if ($tpl['footer']) include $tpl['footer']; ?>
</div>
<?php if ($tpl['html_footer']) include $tpl['html_footer']; ?>
