<?php
declare(strict_types=1);

/**
 * core/env.php
 *
 * Single source of truth for environment & path helpers.
 * Safe to include multiple times (guards on every function).
 *
 * Usage:
 *   require_once __DIR__ . '/env.php';
 *   cis_load_dotenv(dirname(__DIR__) . '/.env');   // optional
 *   $host = cis_env('DB_HOST', '127.0.0.1');
 */

if (!defined('CIS_ENV_LOADED')) {
    define('CIS_ENV_LOADED', true);
}

/** ---- basic getters ------------------------------------------------------ */

if (!function_exists('cis_env')) {
    /**
     * Get env var from getenv/$_ENV/$_SERVER/$GLOBALS, with default fallback.
     */
    function cis_env(string $key, ?string $default = null): ?string
    {
        // getenv() returns false if not set.
        $v = getenv($key);
        if ($v === false && array_key_exists($key, $_ENV))    $v = $_ENV[$key];
        if ($v === false && array_key_exists($key, $_SERVER)) $v = $_SERVER[$key];
        if ($v === false && array_key_exists($key, $GLOBALS)) $v = $GLOBALS[$key];

        if ($v === false || $v === null || $v === '') {
            return $default;
        }
        return (string)$v;
    }
}

if (!function_exists('cis_env_required')) {
    /**
     * Get required env; throws if missing/empty.
     */
    function cis_env_required(string $key): string
    {
        $v = cis_env($key, null);
        if ($v === null || $v === '') {
            throw new RuntimeException("Required env missing: {$key}");
        }
        return $v;
    }
}

if (!function_exists('cis_bool_env')) {
    /**
     * Parse booleanish env to bool (1/true/yes/on vs 0/false/no/off).
     */
    function cis_bool_env(string $key, bool $default = false): bool
    {
        $v = cis_env($key, null);
        if ($v === null) return $default;
        $t = strtolower(trim($v));
        if ($t === '' ) return $default;
        return in_array($t, ['1','true','yes','on','y','t'], true);
    }
}

if (!function_exists('cis_int_env')) {
    /**
     * Parse integer env with default.
     */
    function cis_int_env(string $key, ?int $default = null): ?int
    {
        $v = cis_env($key, null);
        if ($v === null) return $default;
        if (!preg_match('/^-?\d+$/', trim($v))) return $default;
        return (int)$v;
    }
}

if (!function_exists('cis_json_env')) {
    /**
     * Parse JSON env into array/object; returns $default on failure.
     */
    function cis_json_env(string $key, mixed $default = null, bool $assoc = true): mixed
    {
        $v = cis_env($key, null);
        if ($v === null) return $default;
        $decoded = json_decode($v, $assoc);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
    }
}

/** ---- .env loader (lightweight, optional) -------------------------------- */

if (!function_exists('cis_load_dotenv')) {
    /**
     * Load KEY=VALUE pairs from a .env file.
     * - Ignores blank lines and lines starting with '#'
     * - Supports simple quoted values "like this" or 'like this'
     * - Does not overwrite existing env unless $overwrite=true
     */
    function cis_load_dotenv(?string $path, bool $overwrite = false): void
    {
        static $loaded = [];
        if (!$path) return;
        $real = realpath($path);
        if ($real === false) return;
        if (isset($loaded[$real])) return; // idempotent

        $lines = @file($real, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;

            // split on first '=' only
            $pos = strpos($line, '=');
            if ($pos === false) continue;

            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));

            // strip optional surrounding quotes
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }

            // do not overwrite unless allowed
            $exists = (getenv($key) !== false) || array_key_exists($key, $_ENV);
            if ($exists && !$overwrite) continue;

            // export into all places
            putenv("{$key}={$val}");
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
            $GLOBALS[$key] = $val;
        }

        $loaded[$real] = true;
    }
}

/** ---- paths & project base ------------------------------------------------ */

if (!function_exists('cis_base_path')) {
    /**
     * Resolve the project base directory.
     * Assumes this file lives in {BASE}/core/env.php → BASE = dirname(__DIR__).
     */
    function cis_base_path(): string
    {
        static $base = null;
        if ($base !== null) return $base;
        $base = dirname(__DIR__); // e.g. /.../public_html
        return $base;
    }
}

if (!function_exists('cis_path')) {
    /**
     * Join a path relative to the project base:
     *   cis_path('modules/purchase-orders/cli/consume_inventory_adjusts.php')
     */
    function cis_path(string $relative = ''): string
    {
        $base = cis_base_path();
        if ($relative === '' || $relative === DIRECTORY_SEPARATOR) return $base;
        // Clean leading slashes to avoid // in the middle
        $relative = ltrim($relative, DIRECTORY_SEPARATOR);
        return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }
}
