<?php
declare(strict_types=1);
/**
 * modules/transfers/stock/core/view_helpers.php
 * Purpose: Module-scoped view helpers (no external template dependencies)
 */

// HTML escape
function stx_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Build absolute asset URL with required https://staff.vapeshed.co.nz prefix
function stx_asset_url(string $path): string {
  $path = '/' . ltrim($path, '/');
  return 'https://staff.vapeshed.co.nz' . $path;
}

// Cache-busting version query using filemtime when available
function stx_ver(string $rel): string {
  $abs = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/' . ltrim($rel, '/');
  try { return is_file($abs) ? (string)filemtime($abs) : (string)time(); } catch (Throwable $e) { return (string)time(); }
}

// Simple stacks for styles/scripts
$GLOBALS['STX_STYLES'] = $GLOBALS['STX_STYLES'] ?? [];
$GLOBALS['STX_SCRIPTS'] = $GLOBALS['STX_SCRIPTS'] ?? [];

function stx_style(string $path, array $attrs = []): void {
  $href = strpos($path, 'http') === 0 ? $path : stx_asset_url($path);
  $attrsStr = '';
  foreach ($attrs as $k => $v) { if ($v === true) $v = $k; $attrsStr .= ' ' . stx_e($k) . '="' . stx_e((string)$v) . '"'; }
  $GLOBALS['STX_STYLES'][] = '<link rel="stylesheet" href="' . stx_e($href) . '"' . $attrsStr . '>';
}

function stx_script(string $path, array $attrs = []): void {
  $src = strpos($path, 'http') === 0 ? $path : stx_asset_url($path);
  $attrsStr = '';
  foreach ($attrs as $k => $v) { if ($v === true) $v = $k; $attrsStr .= ' ' . stx_e($k) . '="' . stx_e((string)$v) . '"'; }
  $GLOBALS['STX_SCRIPTS'][] = '<script src="' . stx_e($src) . '"' . $attrsStr . '></script>';
}

function stx_render_styles(): void { echo implode("\n", $GLOBALS['STX_STYLES']); }
function stx_render_scripts(): void { echo implode("\n", $GLOBALS['STX_SCRIPTS']); }

// CSRF token helper (module-scoped)
function stx_csrf_token(): string {
  if (!isset($_SESSION)) { session_start(); }
  if (!empty($_SESSION['csrf_token'])) { return (string)$_SESSION['csrf_token']; }
  try { $tok = bin2hex(random_bytes(16)); } catch (Throwable $e) { $tok = sha1((string)microtime(true)); }
  $_SESSION['csrf_token'] = $tok;
  return $tok;
}

// Include a block from this module's blocks folder with optional variables
function stx_block(string $name, array $vars = []): void {
  $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
  $path = $doc . '/modules/transfers/stock/blocks/' . basename($name) . '.php';
  if (!is_file($path)) { echo "\n<!-- stx_block missing: " . stx_e($name) . " -->\n"; return; }
  if (!empty($vars)) { extract($vars, EXTR_SKIP); }
  // phpcs:ignore
  include $path;
}
