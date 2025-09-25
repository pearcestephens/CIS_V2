<?php
declare(strict_types=1);

/**
 * core/auth.php
 *
 * Minimal auth helpers with guards to avoid clashing with legacy
 * definitions in /assets/functions/config.php.
 */

if (!function_exists('cis_is_logged_in')) {
    function cis_is_logged_in(): bool {
        return !empty($_SESSION['userID']);
    }
}

if (!function_exists('cis_require_login')) {
    function cis_require_login(array $except = []): void {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        if (in_array($uri, $except, true)) return;

        if (empty($_SESSION['userID'])) {
            $rt = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: /login?return_to=' . $rt, true, 302);
            exit;
        }
    }
}
