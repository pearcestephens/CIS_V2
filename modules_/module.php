<?php
/**
 * module.php — Friendly module router alias
 * Purpose: Clean, dev-safe entry point that delegates to the canonical CIS template shell.
 * Dependencies: app.php, modules/CIS_TEMPLATE.php
 * Security: No output. Pass-through only.
 */
declare(strict_types=1);

// Bootstrap the app first (sessions, config, autoloaders, security headers)
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Delegate to the canonical template
require_once __DIR__ . '/CIS_TEMPLATE.php';
