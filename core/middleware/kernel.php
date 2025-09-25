<?php
declare(strict_types=1);

/**
 * core/middleware/kernel.php
 *
 * Lightweight HTTP middleware pipeline for CIS AJAX/JSON endpoints.
 * Provides:
 *  - mw_security_headers()
 *  - mw_trace()                              → adds X-Request-ID and places into $ctx['request_id']
 *  - mw_json_or_form_normalizer()            → $ctx['input'], $ctx['headers']
 *  - mw_csrf_or_api_key($testKey)
 *  - mw_validate_content_type($allowed)
 *  - mw_content_length_limit($bytes)
 *  - mw_rate_limit($bucket, $limit, $window)
 *  - mw_enforce_auth($role = null)
 *  - mw_idempotency() + mw_idem_store($ctx, $payload)
 */

// Compose a middleware stack into a single callable
function mw_pipeline(array $stack): callable
{
    return function (array $ctx) use ($stack) {
        $runner = array_reduce(
            array_reverse($stack),
            function ($next, $mw) {
                return function ($ctx) use ($next, $mw) {
                    return $mw($ctx, $next);
                };
            },
            function ($ctx) {
                return $ctx;
            }
        );
        return $runner($ctx);
    };
}

// Security headers for JSON APIs
function mw_security_headers(): callable
{
    return function (array $ctx, callable $next) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 0');
        header('Referrer-Policy: no-referrer');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: application/json; charset=utf-8');
        return $next($ctx);
    };
}

// Request tracing
function mw_trace(): callable
{
    return function (array $ctx, callable $next) {
        if (function_exists('cis_request_id')) {
            $rid = cis_request_id();
        } else {
            try {
                $rid = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $rid = substr(bin2hex(uniqid('', true)), 0, 32);
            }
            header('X-Request-ID:' . $rid);
        }
        $ctx['request_id'] = $rid;
        return $next($ctx);
    };
}

// Normalize JSON/FORM into $ctx['input'] and capture headers map
function mw_json_or_form_normalizer(): callable
{
    return function (array $ctx, callable $next) {
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);

        if (is_array($json)) {
            $in = $json;
        } else {
            $in = [];
            // common fields
            $in['action'] = (string)($_POST['action'] ?? ($_POST['ajax_action'] ?? ''));
            foreach (['transfer_id','carrier','outlet_from','notes','csrf','product_id','qty','idempotency_key'] as $k) {
                if (isset($_POST[$k])) $in[$k] = $_POST[$k];
            }
            if (isset($_POST['parcel_plan'])) {
                $pp = $_POST['parcel_plan'];
                if (is_string($pp)) {
                    $dec = json_decode($pp, true);
                    $in['parcel_plan'] = is_array($dec) ? $dec : $pp;
                } elseif (is_array($pp)) {
                    $in['parcel_plan'] = $pp;
                }
            }
        }

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (strpos($k, 'HTTP_') === 0) {
                $h = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$h] = $v;
            }
        }

        $ctx['input']   = is_array($in) ? $in : [];
        $ctx['headers'] = $headers;
        return $next($ctx);
    };
}

// CSRF for browsers, allow X-API-Key bypass for CLI/testing in non-prod
function mw_csrf_or_api_key(string $testKey): callable
{
    return function (array $ctx, callable $next) use ($testKey) {
        $headers = $ctx['headers'] ?? [];
        $apiKey  = $headers['x-api-key'] ?? '';

        // CLI/testing bypass
        if ($apiKey && hash_equals($testKey, (string)$apiKey)) {
            return $next($ctx);
        }

        if (!function_exists('cis_csrf_or_json_400')) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'CSRF helper missing']);
            exit;
        }
        cis_csrf_or_json_400();
        return $next($ctx);
    };
}

// Enforce content type on request (if provided)
function mw_validate_content_type(array $allowed): callable
{
    return function (array $ctx, callable $next) use ($allowed) {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($ct) {
            $ok = false;
            foreach ($allowed as $a) {
                if (stripos($ct, $a) === 0) { $ok = true; break; }
            }
            if (!$ok) {
                http_response_code(415);
                echo json_encode(['ok' => false, 'error' => 'Unsupported content type']);
                exit;
            }
        }
        return $next($ctx);
    };
}

// Body size limiter
function mw_content_length_limit(int $bytes): callable
{
    return function (array $ctx, callable $next) use ($bytes) {
        $len = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($len > 0 && $len > $bytes) {
            http_response_code(413);
            echo json_encode(['ok' => false, 'error' => 'Payload too large']);
            exit;
        }
        return $next($ctx);
    };
}

// Simple, file-backed rate limiter
function mw_rate_limit(string $bucket, int $limit, int $windowSec): callable
{
    return function (array $ctx, callable $next) use ($bucket, $limit, $windowSec) {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $dir = sys_get_temp_dir() . '/cis_rl';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);

        $slot = (int)floor(time() / $windowSec);
        $key  = sprintf('%s/%s_%s_%d', $dir, preg_replace('/[^a-z0-9_\-]/i', '', $bucket), $ip, $slot);

        $count = 0;
        if (is_file($key)) $count = (int)file_get_contents($key);
        if ($count >= $limit) {
            http_response_code(429);
            header('Retry-After: ' . $windowSec);
            echo json_encode(['ok' => false, 'error' => 'Rate limit exceeded']);
            exit;
        }
        file_put_contents($key, (string)($count + 1), LOCK_EX);
        return $next($ctx);
    };
}

// Session-based auth enforcement
function mw_enforce_auth(?string $role = null): callable
{
    return function (array $ctx, callable $next) use ($role) {
        if (function_exists('cis_is_logged_in') && !cis_is_logged_in()) {
            if (function_exists('cis_require_login')) {
                cis_require_login([]);
            }
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }
        // Optionally enforce a role gate here if your session exposes roles
        return $next($ctx);
    };
}

// Idempotency read-through cache (table: idempotency_keys)
function mw_idempotency(): callable
{
    return function (array $ctx, callable $next) {
        $headers = $ctx['headers'] ?? [];
        $key     = trim((string)($headers['idempotency-key'] ?? ''));
        if ($key === '') return $next($ctx);

        $input = $ctx['input'] ?? [];
        $hash  = hash('sha256', json_encode($input, JSON_UNESCAPED_SLASHES));

        try {
            $pdo = db();
            // accept either `idem_key` or legacy `key`
            $sel = $pdo->prepare('SELECT response_json, request_hash FROM idempotency_keys WHERE idem_key=:k OR `key`=:k LIMIT 1');
            $sel->execute([':k' => $key]);
            $row = $sel->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                // If same request body, replay
                if ((string)($row['request_hash'] ?? '') === $hash && !empty($row['response_json'])) {
                    http_response_code(200);
                    echo $row['response_json'];
                    exit;
                }
                // else: let the handler decide (most will 409), we just fall-through
            }
        } catch (Throwable $e) {
            return $next($ctx); // do not block on cache error
        }

        $ctx['__idem'] = ['key' => $key, 'hash' => $hash];
        return $next($ctx);
    };
}

// Persist idempotent response
function mw_idem_store(array $ctx, array $payload): void
{
    if (!isset($ctx['__idem'])) return;
    $meta = $ctx['__idem'];
    try {
        $pdo = db();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        // try primary column
        $ins = $pdo->prepare('INSERT INTO idempotency_keys (idem_key, request_hash, response_json) VALUES (:k, :h, :j)
                              ON DUPLICATE KEY UPDATE request_hash=VALUES(request_hash), response_json=VALUES(response_json)');
        $ins->execute([':k' => $meta['key'], ':h' => $meta['hash'], ':j' => $json]);
    } catch (Throwable $e) {
        try {
            // try legacy `key` column path
            $ins2 = $pdo->prepare('INSERT INTO idempotency_keys (`key`, request_hash, response_json) VALUES (:k, :h, :j)
                                   ON DUPLICATE KEY UPDATE request_hash=VALUES(request_hash), response_json=VALUES(response_json)');
            $ins2->execute([':k' => $meta['key'], ':h' => $meta['hash'], ':j' => $json]);
        } catch (Throwable $e2) {
            // swallow on purpose
        }
    }
}
