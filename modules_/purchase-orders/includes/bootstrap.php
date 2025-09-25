<?php
declare(strict_types=1);

/**
 * modules/purchase-orders/includes/bootstrap.php
 * Safe, self-contained module bootstrap: errors, timezone, session, request-id,
 * JSON envelopes, CSRF, canonical JSON, idempotency helpers, and DB access.
 * - No global app includes (prevents cross-site redeclare fatals)
 * - All helpers guarded with function_exists
 * - Will reuse a preloaded global \db() if it exists (optional)
 */

// ---- base runtime hardening ----
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Pacific/Auckland');

// Security header for JSON endpoints
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/*
 * (Optional) local env helpers that are NAMESPACEd (no collisions):
 * require_once __DIR__ . '/local_env.php';
 * use function CIS\PO\env;
 * use function CIS\PO\path;
 */

// -------------------- helpers (guarded) --------------------

if (!function_exists('req_id')) {
    /** Ensure a request-id and echo it back for correlation */
    function req_id(): string {
        $hdr = null;
        foreach (['HTTP_X_REQUEST_ID', 'X_REQUEST_ID'] as $k) {
            if (!empty($_SERVER[$k])) { $hdr = $_SERVER[$k]; break; }
        }
        if (!$hdr) { $hdr = 'req-' . bin2hex(random_bytes(8)); }
        header('X-Request-ID: ' . $hdr);
        return $hdr;
    }
}

if (!function_exists('header_get')) {
    /** Get a header value case-insensitively */
    function header_get(string $name): ?string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
}

if (!function_exists('json_success')) {
    /** Standard JSON envelope: { success, data, request_id } */
    function json_success(array $data = [], int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'    => true,
            'data'       => $data,
            'request_id' => req_id(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }
}

if (!function_exists('json_error')) {
    /** Standard error envelope: { success:false, error:{code,message,meta}, request_id } */
    function json_error(string $message, string $code = 'error', array $meta = [], int $http = 400): never {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'    => false,
            'error'      => ['code' => $code, 'message' => $message, 'meta' => $meta],
            'request_id' => req_id(),
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }
}

if (!function_exists('require_post')) {
    /** Require POST */
    function require_post(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_error('POST required', 'method_not_allowed', [], 405);
        }
    }
}

if (!function_exists('require_csrf')) {
    /** CSRF: require X-CSRF-Token header matching session token (set by tools.php?action=csrf) */
    function require_csrf(): void {
        $hdr = header_get('X-CSRF-Token') ?? '';
        $ses = $_SESSION['csrf'] ?? '';
        if (!$hdr || !$ses || !hash_equals($ses, $hdr)) {
            json_error('CSRF validation failed', 'csrf_forbidden', [], 403);
        }
    }
}

if (!function_exists('canonical_json')) {
    /** Canonical JSON for hashing the "intent" to enforce idempotency */
    function canonical_json(array $payload): string {
        $normalize = function ($v) use (&$normalize) {
            if (is_array($v)) {
                $isAssoc = array_keys($v) !== range(0, count($v) - 1);
                if ($isAssoc) {
                    ksort($v);
                    foreach ($v as $k => $vv) { $v[$k] = $normalize($vv); }
                    return $v;
                }
                return array_map($normalize, $v);
            }
            return $v;
        };
        $norm = $normalize($payload);
        return json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}

if (!function_exists('db')) {
    /**
     * PDO getter: reuse an already-loaded global \db() if available, else build from env/constants.
     * NOTE: We do NOT include any global site files here to avoid redeclare collisions.
     */
    function db(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) { return $pdo; }

        // If the host app already bootstrapped and exposed \db(), reuse it
        if (function_exists('\\db')) {
            $maybe = \db();
            if ($maybe instanceof PDO) { $pdo = $maybe; return $pdo; }
        }
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $pdo = $GLOBALS['pdo']; return $pdo;
        }

        // Fallback to env/constants (adjust names if your host uses different ones)
        $host = getenv('DB_HOST')    ?: (defined('DB_HOST') ? DB_HOST : '127.0.0.1');
        $name = getenv('DB_NAME')    ?: (defined('DB_NAME') ? DB_NAME : 'cis');
        $user = getenv('DB_USER')    ?: (defined('DB_USER') ? DB_USER : 'root');
        $pass = getenv('DB_PASS')    ?: (defined('DB_PASS') ? DB_PASS : '');
        $dsn  = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+12:00'",
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    }
}

if (!function_exists('idem_lookup')) {
    /** Idempotency lookup (table: idempotency_keys) */
    function idem_lookup(PDO $pdo, string $key): ?array {
        $sql = "SELECT request_hash, response_json FROM idempotency_keys WHERE `key` = :k LIMIT 1";
        try {
            $row = $pdo->prepare($sql);
            $row->execute([':k' => $key]);
            $r = $row->fetch();
            return $r ? ['request_hash' => $r['request_hash'], 'response_json' => $r['response_json']] : null;
        } catch (Throwable $e) {
            // Fallback for alt column name (`idem_key`)
            $sql2 = "SELECT request_hash, response_json FROM idempotency_keys WHERE `idem_key` = :k LIMIT 1";
            $row2 = $pdo->prepare($sql2);
            $row2->execute([':k' => $key]);
            $r2 = $row2->fetch();
            return $r2 ? ['request_hash' => $r2['request_hash'], 'response_json' => $r2['response_json']] : null;
        }
    }
}

if (!function_exists('idem_store')) {
    /** Store idempotent response (table: idempotency_keys) */
    function idem_store(PDO $pdo, string $key, string $hash, array $responseEnvelope): void {
        $json = json_encode($responseEnvelope, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $tried = false;
        while (true) {
            try {
                $sql = "INSERT INTO idempotency_keys (`key`, request_hash, response_json, created_at)
                        VALUES (:k, :h, :r, NOW())
                        ON DUPLICATE KEY UPDATE request_hash = VALUES(request_hash), response_json = VALUES(response_json)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':k' => $key, ':h' => $hash, ':r' => $json]);
                return;
            } catch (Throwable $e) {
                if ($tried) { throw $e; }
                $tried = true;
                // Fallback variant
                $sql2 = "INSERT INTO idempotency_keys (`idem_key`, request_hash, response_json, created_at)
                         VALUES (:k, :h, :r, NOW())
                         ON DUPLICATE KEY UPDATE request_hash = VALUES(request_hash), response_json = VALUES(response_json)";
                $stmt2 = $pdo->prepare($sql2);
                $stmt2->execute([':k' => $key, ':h' => $hash, ':r' => $json]);
                return;
            }
        }
    }
}

if (!function_exists('build_success')) {
    /** Build a success envelope without emitting (for idempotent store) */
    function build_success(array $data, string $reqId): array {
        return ['success' => true, 'data' => $data, 'request_id' => $reqId];
    }
}
