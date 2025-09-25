<?php
declare(strict_types=1);

/**
 * core/csrf.php
 *
 * CSRF token helpers for CIS.
 * - cis_csrf_token(): returns session token (creates if missing)
 * - cis_verify_csrf(): checks POST/HEADER/GET token against session
 * - cis_csrf_or_json_400(): enforces CSRF for mutating requests or allows
 *   DEV/API-key bypass for trusted tools/CLI.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * Return the session CSRF token (create if missing).
 */
function cis_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $_SESSION['csrf_token'] = substr(bin2hex(uniqid('', true)), 0, 32);
        }
    }
    // Compatibility alias some legacy code expects:
    $_SESSION['csrf'] = $_SESSION['csrf_token'];
    return (string) $_SESSION['csrf_token'];
}

/**
 * Verify the incoming token (POST/HEADER/GET) against the session token.
 */
function cis_verify_csrf(): bool
{
    $token = $_POST['csrf']
        ?? $_POST['csrf_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')
        ?? ($_GET['csrf_token'] ?? '')
        ?? '';

    $sessCore = $_SESSION['csrf_token'] ?? '';
    $sessAlt  = $_SESSION['csrf'] ?? '';
    $sess     = $sessCore !== '' ? $sessCore : $sessAlt;

    if ($sess === '' || $token === '') {
        return false;
    }
    return hash_equals((string) $sess, (string) $token);
}

/**
 * Enforce CSRF (JSON 400 on fail) for mutating HTTP methods.
 * Allows a DEV/API-key bypass for CLI/self-tests.
 */
function cis_csrf_or_json_400(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }
    if (cis_verify_csrf()) {
        return;
    }

    // DEV/API key bypass (e.g., TEST-CLI-KEY-123 or ENV DEV_API_KEY)
    $devBypass = getenv('DEV_API_KEY') ?: ($_ENV['DEV_API_KEY'] ?? null);
    $hdrKey    = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $legacyKey = defined('CLI_TEST_KEY') ? constant('CLI_TEST_KEY') : 'TEST-CLI-KEY-123';

    if (($devBypass && hash_equals($devBypass, (string)$hdrKey)) || ($legacyKey && hash_equals($legacyKey, (string)$hdrKey))) {
        return;
    }

    // JSON envelope
    if (function_exists('cis_json')) {
        cis_json(false, null, ['code' => 'csrf_failed', 'message' => 'Invalid CSRF token'], 400);
    }
    http_response_code(400);
    header('Content-Type:application/json;charset=utf-8');
    echo json_encode(['success' => false, 'error' => ['code' => 'csrf_failed', 'message' => 'Invalid CSRF token']]);
    exit;
}
