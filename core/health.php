<?php
declare(strict_types=1);

/**
 * /core/health.php
 *
 * Unified, safe health aggregator for monitoring.
 * - Always returns HTTP 200 with a JSON envelope.
 * - Never pulls app.php or auth flows (safe for uptime probes).
 * - Emits X-Request-ID for tracing.
 *
 * Probes:
 *   - Purchase Orders:   /modules/purchase-orders/ajax/handler.php?ajax_action=health
 *   - Transfers (Pack):  /modules/transfers/stock/ajax/handler.php?action=health
 *   - Queue Service:     /assets/services/queue/public/health.php
 *   - Queue Webhooks:    /assets/services/queue/public/webhook.health.php
 *
 * Add/adjust endpoints as you grow. Keep them lightweight.
 */

// ---- headers (safe) ----
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// ---- request id ----
function cis_reqid(): string {
    static $rid = null;
    if ($rid !== null) return $rid;
    try { $rid = bin2hex(random_bytes(16)); } catch (Throwable $e) { $rid = substr(bin2hex(uniqid('', true)), 0, 32); }
    header('X-Request-ID: ' . $rid);
    return $rid;
}
cis_reqid();

// ---- config (edit if your base path changes) ----
$host   = (isset($_SERVER['HTTP_HOST']) && is_string($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . $host;

// Keep each probe fast (~2s) and tolerant.
$endpoints = [
    'po_health'         => $base . '/modules/purchase-orders/ajax/handler.php?ajax_action=health',
    'transfers_stock'   => $base . '/modules/transfers/stock/ajax/handler.php?action=health',
    'transfers_receive' => $base . '/modules/transfers/receive/ajax/handler.php?action=health',
    'queue_health'      => $base . '/assets/services/queue/public/health.php',
    'webhook_health'    => $base . '/assets/services/queue/public/webhook.health.php',
];

// ---- fetch helper ----
function fetch_json(string $url, int $timeoutSec = 3): array {
    $ts0 = microtime(true);
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeoutSec,
            'ignore_errors' => true,
            'header'        => "Accept: application/json\r\nUser-Agent: CISHealth/1.0\r\n",
        ],
        'ssl'  => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw   = @file_get_contents($url, false, $ctx);
    $code  = 0;
    // $http_response_header is a special variable populated by file_get_contents; use correct scope
    $hdrs  = [];
    if (isset($http_response_header)) {
        // bring into local scope
        $hdrs = $http_response_header;
    }
    foreach ($hdrs as $h) {
        // Match HTTP/1.1, HTTP/2, HTTP/2.0
        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d+)/', $h, $m)) { $code = (int)$m[1]; break; }
    }
    $ms = (int)round((microtime(true) - $ts0) * 1000);

    $json = null;
    if (is_string($raw) && $raw !== '') {
        $tmp = json_decode($raw, true);
        if (is_array($tmp)) $json = $tmp;
    }

    // Determine 'healthy' in a tolerant way:
    // Accepts:
    //  - { ok: true } or { success: true }
    //  - { data: { status: "healthy" } } or { status: "healthy" }
    $healthy = false;
    if (is_array($json)) {
        if (!empty($json['ok']) || !empty($json['success'])) {
            $healthy = true;
        }
        if (!$healthy) {
            $data = $json['data'] ?? [];
            $stat = $json['status'] ?? ($data['status'] ?? null);
            if (is_string($stat) && stripos($stat, 'healthy') !== false) $healthy = true;
        }
    }

    // Decide final healthy status; if code unavailable (0), rely on body signal
    $isHealthy = ($code === 0) ? $healthy : (($code >= 200 && $code < 400) && $healthy);

    return [
        'url'     => $url,
        'http'    => $code,
        'ms'      => $ms,
        'body'    => $json,
        'healthy' => $isHealthy,
    ];
}

// ---- run probes ----
$results = [];
$overallHealthy = true;

foreach ($endpoints as $key => $url) {
    try {
        $res = fetch_json($url, 3);
        $results[$key] = $res;
        if (!$res['healthy']) $overallHealthy = false;
    } catch (Throwable $e) {
        $results[$key] = [
            'url'     => $url,
            'http'    => 0,
            'ms'      => 0,
            'body'    => null,
            'healthy' => false,
            'error'   => $e->getMessage(),
        ];
        $overallHealthy = false;
    }
}

// ---- optional self diag (fast DB ping w/out pulling app.php) ----
// If you want a DB probe here, inject your PDO bootstrap safely or skip to keep this page tiny.

// ---- payload ----
$payload = [
    'ok'         => true,                // aggregator always returns 200 for monitors
    'system'     => 'CIS',
    'request_id' => cis_reqid(),
    'generated'  => gmdate('c'),
    'overall'    => $overallHealthy ? 'HEALTHY' : 'DEGRADED',
    'checks'     => $results,
];

// ---- emit ----
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
