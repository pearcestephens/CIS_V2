<?php
/**
 * File: core/session_probe.php
 * Purpose: Diagnostic endpoint to verify DB-backed session behaviour.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: bootstrap.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$_SESSION['probe_value'] = $_SESSION['probe_value'] ?? bin2hex(random_bytes(8));

echo json_encode([
    'session_id'   => session_id(),
    'probe_value'  => $_SESSION['probe_value'],
    'expires_in'   => ini_get('session.gc_maxlifetime'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
