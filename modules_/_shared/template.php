<?php
/**
 * modules/_shared/template.php
 * Purpose: Minimal, pragmatic template helpers (safe includes, shared assets, breadcrumbs, assets).
 * Typical usage in a view:
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/_shared/template.php';
 *   tpl_shared_assets();
 *   tpl_block('header_bar');
 *   tpl_style('/modules/stock_transfers/assets/css/stock_transfers.css');
 *   tpl_script('/modules/stock_transfers/assets/js/outgoing.init.js', ['defer'=>true]);
 *   tpl_render_styles(); tpl_render_scripts();
 */

declare(strict_types=1);

// Bootstrap application context (sessions, autoloaders, config)
if (isset($_SERVER['DOCUMENT_ROOT'])) {
    $appPath = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/app.php';
    if (is_file($appPath)) {
        // phpcs:ignore
        require_once $appPath;
    }
}

// Constants
$__DOC = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($__DOC === '') {
    // Fallback for CLI contexts: attempt to derive DOC root two levels up from this file
    $__DOC = rtrim(dirname(__DIR__, 2), '/');
}
if (!defined('TPL_DOC_ROOT')) {
    define('TPL_DOC_ROOT', $__DOC);
}
if (!defined('TPL_MODULES_ROOT')) {
    define('TPL_MODULES_ROOT', TPL_DOC_ROOT . '/modules');
}

// --- Environment helpers ----------------------------------------------------
if (!defined('TPL_ENV_DEV')) {
    // Detect DEV via common flags; you can set APP_ENV/dev_mode in app config
    $tplDev = false;
    if ((defined('APP_ENV') && strtolower((string)constant('APP_ENV')) === 'dev') ||
        (defined('ENV') && strtolower((string)constant('ENV')) === 'dev') ||
        (!empty($GLOBALS['dev_mode'])) || (!empty($_ENV['APP_ENV']) && strtolower((string)$_ENV['APP_ENV']) === 'dev')) {
        $tplDev = true;
    }
    define('TPL_ENV_DEV', $tplDev);
}

// --- HTML escape helpers -----------------------------------------------------
function tpl_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function tpl_raw(string $html): string { return $html; }

// --- Asset stacks (styles/scripts) ------------------------------------------
$GLOBALS['TPL_STYLES'] = $GLOBALS['TPL_STYLES'] ?? [];
$GLOBALS['TPL_SCRIPTS'] = $GLOBALS['TPL_SCRIPTS'] ?? [];

function tpl_style(string $path, array $attrs = []): void
{
    $href = tpl_asset_url($path);
    $attrsStr = '';
    foreach ($attrs as $k => $v) { if ($v === true) $v = $k; $attrsStr .= ' ' . tpl_e($k) . '="' . tpl_e((string)$v) . '"'; }
    $GLOBALS['TPL_STYLES'][] = '<link rel="stylesheet" href="' . tpl_e($href) . '"' . $attrsStr . '>';
}

function tpl_script(string $path, array $attrs = []): void
{
    $src = tpl_asset_url($path);
    $attrsStr = '';
    foreach ($attrs as $k => $v) { if ($v === true) $v = $k; $attrsStr .= ' ' . tpl_e($k) . '="' . tpl_e((string)$v) . '"'; }
    $GLOBALS['TPL_SCRIPTS'][] = '<script src="' . tpl_e($src) . '"' . $attrsStr . '></script>';
}

function tpl_render_styles(): void { echo implode("\n", $GLOBALS['TPL_STYLES']); }
function tpl_render_scripts(): void { echo implode("\n", $GLOBALS['TPL_SCRIPTS']); }
function tpl_reset_assets(): void { $GLOBALS['TPL_STYLES'] = []; $GLOBALS['TPL_SCRIPTS'] = []; }

/**
 * Internal: get the caller file outside this helper.
 */
function _tpl_caller_file(): ?string
{
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $f) {
        if (!empty($f['file']) && $f['file'] !== __FILE__) {
            return $f['file'];
        }
    }
    return null;
}

/**
 * Internal: resolve a block specification to an absolute file path.
 * Accepts either a relative path (contains '/' or ends with .php) or a short name (e.g., 'items_table').
 */
function _tpl_resolve_block_path(string $callerFile, string $spec): ?string
{
    $base = dirname($callerFile);
    $isPath = (strpos($spec, '/') !== false) || (substr($spec, -4) === '.php');
    if ($isPath) {
        $target = realpath($base . '/' . ltrim($spec, '/'));
        return $target !== false ? $target : null;
    }

    // Name-based resolution (no slashes): prefer common patterns
    $name = $spec;
    $candidates = [];
    $baseDirName = basename($base);
    // Typical: views -> ../blocks/name.php
    if ($baseDirName === 'views') {
        $candidates[] = $base . '/../blocks/' . $name . '.php';
    }
    // If called from a block, prefer same folder
    if ($baseDirName === 'blocks') {
        $candidates[] = $base . '/' . $name . '.php';
    }
    // Generic fallbacks
    $candidates[] = $base . '/blocks/' . $name . '.php';
    $candidates[] = dirname($base) . '/blocks/' . $name . '.php';

    foreach ($candidates as $cand) {
        $rp = realpath($cand);
        if ($rp !== false) { return $rp; }
    }
    return null;
}

/**
 * Safely include a file using a path relative to a current file.
 * Guards against traversal outside /modules.
 *
 * @param string $currentFile __FILE__ of the calling script
 * @param string $relative    Relative path (e.g., '../blocks/items_table.php')
 */
function tpl_include(string $currentFile, string $relative): void
{
    $base = dirname($currentFile);
    $target = realpath($base . '/' . ltrim($relative, '/'));
    if ($target === false) { return; }
    // Ensure target is under /modules
    if (strpos($target, TPL_MODULES_ROOT . '/') !== 0 && $target !== TPL_MODULES_ROOT) { return; }
    // phpcs:ignore
    include $target;
}

/**
 * Check if a block exists under /modules.
 * Can be called as:
 *   tpl_block_exists('../blocks/foo.php')
 *   tpl_block_exists(__FILE__, '../blocks/foo.php') // backwards compatible
 */
function tpl_block_exists(string $relativeOrCaller, ?string $relative = null): bool
{
    if ($relative === null) {
        $callerFile = _tpl_caller_file() ?? (TPL_MODULES_ROOT . '/index.php');
        $target = _tpl_resolve_block_path($callerFile, $relativeOrCaller);
    } else {
        $callerFile = $relativeOrCaller;
        $target = _tpl_resolve_block_path($callerFile, $relative);
    }
    if (!$target) { return false; }
    return (strpos($target, TPL_MODULES_ROOT . '/') === 0 || $target === TPL_MODULES_ROOT) && is_file($target);
}

/**
 * Include a block file with optional scoped variables.
 *
 * Call styles supported (keep it simple, no magic beyond this):
 *   1) New:    tpl_block('name'|relativePath [, array $vars [, bool $returnBuffer ]])
 *   2) Legacy: tpl_block(__FILE__, 'name'|relativePath [, array $vars [, bool $returnBuffer ]])
 * Returns buffered string when $returnBuffer is true; otherwise echoes and returns null.
 */
function tpl_block($arg1, $arg2 = null, $arg3 = null, $arg4 = null): ?string
{
    // Parameter resolution
    $callerFile = null; $spec = null; $vars = []; $returnBuffer = false;
    if (is_string($arg1) && (is_null($arg2) || is_array($arg2) || is_bool($arg2))) {
        // New style
        $spec = (string)$arg1;
        $callerFile = _tpl_caller_file() ?? (TPL_MODULES_ROOT . '/index.php');
        if (is_array($arg2)) { $vars = $arg2; }
        if (is_bool($arg2)) { $returnBuffer = $arg2; }
        if (is_array($arg3)) { $vars = $arg3; }
        if (is_bool($arg3)) { $returnBuffer = $arg3; }
        if (is_bool($arg4)) { $returnBuffer = $arg4; }
    } else {
        // Legacy style
        $callerFile = (string)$arg1;
        $spec = (string)$arg2;
        if (is_array($arg3)) { $vars = $arg3; }
        if (is_bool($arg3)) { $returnBuffer = $arg3; }
        if (is_bool($arg4)) { $returnBuffer = $arg4; }
    }

    $target = _tpl_resolve_block_path($callerFile, $spec);
    if (!$target) {
        if (TPL_ENV_DEV) { echo "\n<!-- tpl_block: missing '" . tpl_e($spec) . "' for caller '" . tpl_e(basename((string)$callerFile)) . "' -->\n"; }
        return $returnBuffer ? '' : null;
    }
    if (strpos($target, TPL_MODULES_ROOT . '/') !== 0 && $target !== TPL_MODULES_ROOT) { return $returnBuffer ? '' : null; }

    // Extract variables into local scope for the block
    if (!empty($vars)) {
        $safe = [];
        foreach ($vars as $k => $v) { if (is_string($k) && $k !== '') { $safe[$k] = $v; } }
        if (!empty($safe)) { extract($safe, EXTR_SKIP); }
    }

    if ($returnBuffer) {
        ob_start();
        // phpcs:ignore
        include $target;
        return ob_get_clean();
    }
    // phpcs:ignore
    include $target;
    return null;
}

/** In DEV, emit a comment with the resolved absolute path for a given block name/path. */
function tpl_debug_block_resolve(string $nameOrPath): void
{
    if (!TPL_ENV_DEV) { return; }
    $caller = _tpl_caller_file() ?? (TPL_MODULES_ROOT . '/index.php');
    $rp = _tpl_resolve_block_path($caller, $nameOrPath);
    echo "\n<!-- tpl_block resolved: '" . tpl_e($nameOrPath) . "' => '" . tpl_e((string)$rp) . "' -->\n";
}

/**
 * Require an absolute path under the document root (once).
 * Example: tpl_require('/modules/stock-transfer/views/shared/components.php')
 *
 * @param string $absPath starting with '/'
 */
function tpl_require(string $absPath): void
{
    $absPath = '/' . ltrim($absPath, '/');
    $full = TPL_DOC_ROOT . $absPath;
    if (!is_file($full)) { return; }
    // Restrict to /modules/* or /assets/* for safety
    $ok = (strpos($full, TPL_MODULES_ROOT . '/') === 0) || (strpos($full, TPL_DOC_ROOT . '/assets/') === 0);
    if (!$ok) { return; }
    // phpcs:ignore
    require_once $full;
}

/**
 * Load shared base assets (CSS/JS) and shared PHP components.
 * Centralizes the include path so modules can call a single function.
 */
function tpl_shared_assets(?callable $loader = null): void
{
    // Allow a global override (callable) so future shared helpers can swap loader without touching all callers
    if ($loader === null && isset($GLOBALS['TPL_SHARED_ASSETS_LOADER']) && is_callable($GLOBALS['TPL_SHARED_ASSETS_LOADER'])) {
        $loader = $GLOBALS['TPL_SHARED_ASSETS_LOADER'];
    }
    if ($loader) {
        try { $loader(); return; } catch (Throwable $e) { /* fallback to default */ }
    }
    // Default: idempotent includes for current shared location
    tpl_require('/modules/stock-transfer/views/shared/include_shared_assets.php');
    tpl_require('/modules/stock-transfer/views/shared/components.php');
}

/**
 * Register a global shared assets loader to be used by tpl_shared_assets().
 */
function tpl_register_shared_assets_loader(callable $loader): void
{
    $GLOBALS['TPL_SHARED_ASSETS_LOADER'] = $loader;
}

/**
 * Render breadcrumbs using the shared stx_breadcrumb if available.
 * Fallback: prints a very minimal breadcrumb without links.
 *
 * @param array<int,array{label:string,href?:string}> $items
 * @param string $rightHtml
 */
function tpl_breadcrumb(array $items, string $rightHtml = ''): void
{
    if (function_exists('stx_breadcrumb')) {
        stx_breadcrumb($items, $rightHtml);
        return;
    }
    // Fallback minimal
    echo '<nav aria-label="breadcrumb" class="c-subheader px-3"><ol class="breadcrumb mb-0">';
    $last = count($items) - 1;
    foreach ($items as $i => $it) {
        $label = htmlspecialchars((string)($it['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        if ($i === $last) {
            echo '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            echo '<li class="breadcrumb-item">' . $label . '</li>';
        }
    }
    if ($rightHtml !== '') { echo '<li class="breadcrumb-menu d-md-down-none">' . $rightHtml . '</li>'; }
    echo '</ol></nav>';
}

/**
 * Get the canonical base URL from app configuration if exposed, else safe default.
 */
function tpl_base_url(): string
{
    // Helper to normalize any candidate to HTTPS base URL without trailing slash
    $norm = static function ($v): ?string {
        if (!is_string($v) || $v === '') { return null; }
        $u = trim($v);
        // If array-like (e.g., ['base_url'=>...]) skip
        if ($u === '' || strpos($u, ' ') !== false) { /* allow spaces? */ }
        // Ensure scheme
        if (stripos($u, 'http://') === 0) { $u = 'https://' . substr($u, 7); }
        elseif (stripos($u, 'https://') !== 0) { $u = 'https://' . ltrim($u, '/'); }
        return rtrim($u, '/');
    };

    // Preferred: well-known constants
    $consts = ['BASE_URL','APP_BASE_URL','STAFF_BASE_URL','SITE_URL','WEBSITE_URL','CIS_BASE_URL','ROOT_URL'];
    foreach ($consts as $c) {
        if (defined($c)) { $n = $norm(constant($c)); if ($n) return $n; }
    }
    // Globals (scalar)
    $globals = ['BASE_URL','APP_BASE_URL','STAFF_BASE_URL','SITE_URL','WEBSITE_URL','CIS_BASE_URL','ROOT_URL'];
    foreach ($globals as $g) {
        if (!empty($GLOBALS[$g])) { $n = $norm($GLOBALS[$g]); if ($n) return $n; }
    }
    // Common config arrays
    $arrayCandidates = [
        ['config','base_url'],
        ['CONFIG','base_url'],
        ['settings','base_url'],
        ['APP','base_url'],
        ['app','base_url'],
    ];
    foreach ($arrayCandidates as $pair) {
        [$root,$key] = $pair;
        if (!empty($GLOBALS[$root]) && is_array($GLOBALS[$root]) && !empty($GLOBALS[$root][$key])) {
            $n = $norm($GLOBALS[$root][$key]); if ($n) return $n;
        }
    }
    // Env variable (last resort for dev/stage)
    if (!empty($_ENV['BASE_URL'])) { $n = $norm($_ENV['BASE_URL']); if ($n) return $n; }
    if (!empty(getenv('BASE_URL'))) { $n = $norm((string)getenv('BASE_URL')); if ($n) return $n; }
    // Fallback to policy default
    return 'https://staff.vapeshed.co.nz';
}

/**
 * Build an absolute asset URL using the canonical base URL.
 */
function tpl_asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    return tpl_base_url() . $path;
}

/**
 * Fetch CSRF token from existing app/session context if available.
 */
function tpl_csrf_token(): string
{
    if (function_exists('getCSRFToken')) {
        try { return (string)getCSRFToken(); } catch (Throwable $e) { /* ignore */ }
    }
    if (isset($_SESSION) && !empty($_SESSION['csrf_token'])) {
        return (string)$_SESSION['csrf_token'];
    }
    return '';
}
