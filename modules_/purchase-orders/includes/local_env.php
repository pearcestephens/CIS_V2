<?php
declare(strict_types=1);

namespace CIS\PO;

/**
 * Self-contained env & path helpers for the PO module.
 * Namespaced => cannot collide with site-wide helpers.
 */

if (!function_exists(__NAMESPACE__.'\env')) {
    function env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        if ($v === false && array_key_exists($key, $_ENV))    $v = $_ENV[$key] ?? null;
        if ($v === false && array_key_exists($key, $_SERVER)) $v = $_SERVER[$key] ?? null;
        if ($v === false && array_key_exists($key, $GLOBALS)) $v = $GLOBALS[$key] ?? null;
        if ($v === false || $v === null || $v === '') return $default;
        return (string)$v;
    }
}

if (!function_exists(__NAMESPACE__.'\boolEnv')) {
    function boolEnv(string $key, bool $default = false): bool {
        $v = env($key, null);
        if ($v === null) return $default;
        $t = strtolower(trim($v));
        return in_array($t, ['1','true','yes','on','y','t'], true);
    }
}

if (!function_exists(__NAMESPACE__.'\intEnv')) {
    function intEnv(string $key, ?int $default = null): ?int {
        $v = env($key, null);
        if ($v === null) return $default;
        if (!preg_match('/^-?\d+$/', trim($v))) return $default;
        return (int)$v;
    }
}

if (!function_exists(__NAMESPACE__.'\basePath')) {
    function basePath(): string {
        static $base = null;
        if ($base !== null) return $base;
        // modules/purchase-orders/includes -> public_html
        $base = dirname(__DIR__, 2);
        return $base;
    }
}

if (!function_exists(__NAMESPACE__.'\path')) {
    function path(string $relative = ''): string {
        $base = basePath();
        $relative = ltrim($relative, '/\\');
        return $relative === '' ? $base : $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }
}
