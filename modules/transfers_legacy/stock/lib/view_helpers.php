<?php
declare(strict_types=1);
/**
 * Local view helpers for Transfers/Stock module.
 * Avoids dependency on global shared template helpers.
 */

// Simple HTML escape
function mod_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

// Determine canonical base URL (prefer constants/globals, else default policy URL)
function mod_base_url(): string {
  $cands = [
    'BASE_URL','APP_BASE_URL','STAFF_BASE_URL','SITE_URL','WEBSITE_URL','CIS_BASE_URL','ROOT_URL'
  ];
  foreach ($cands as $c) {
    if (defined($c)) { $u = (string)constant($c); if ($u) return rtrim(preg_replace('#^http://#i','https://',$u), '/'); }
    if (!empty($GLOBALS[$c])) { $u = (string)$GLOBALS[$c]; if ($u) return rtrim(preg_replace('#^http://#i','https://',$u), '/'); }
  }
  $env = getenv('BASE_URL'); if ($env) return rtrim(preg_replace('#^http://#i','https://',$env), '/');
  return 'https://staff.vapeshed.co.nz';
}

// Build absolute asset URL
function mod_asset_url(string $path): string {
  $path = '/' . ltrim($path, '/');
  return mod_base_url() . $path;
}

// Version helper (mtime or time)
function mod_v(string $rel): string {
  $abs = ($_SERVER['DOCUMENT_ROOT'] ?? '') . $rel;
  try { return (is_file($abs) ? (string)filemtime($abs) : (string)time()); } catch (Throwable $e) { return (string)time(); }
}

// Asset stacks
$GLOBALS['MOD_STYLES'] = $GLOBALS['MOD_STYLES'] ?? [];
$GLOBALS['MOD_SCRIPTS'] = $GLOBALS['MOD_SCRIPTS'] ?? [];

function mod_style(string $path, array $attrs = []): void {
  $href = mod_asset_url($path);
  $attrsStr = '';
  foreach ($attrs as $k => $v) { if ($v === true) $v = $k; $attrsStr .= ' ' . mod_e($k) . '="' . mod_e((string)$v) . '"'; }
  $GLOBALS['MOD_STYLES'][] = '<link rel="stylesheet" href="' . mod_e($href) . '"' . $attrsStr . '>';
}

function mod_script(string $path, array $attrs = []): void {
  $src = mod_asset_url($path);
  $attrsStr = '';
  foreach ($attrs as $k => $v) { if ($v === true) $v = $k; $attrsStr .= ' ' . mod_e($k) . '="' . mod_e((string)$v) . '"'; }
  $GLOBALS['MOD_SCRIPTS'][] = '<script src="' . mod_e($src) . '"' . $attrsStr . '></script>';
}

function mod_render_styles(): void { echo implode("\n", $GLOBALS['MOD_STYLES']); }
function mod_render_scripts(): void { echo implode("\n", $GLOBALS['MOD_SCRIPTS']); }
