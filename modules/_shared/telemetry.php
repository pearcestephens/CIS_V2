<?php
declare(strict_types=1);

/**
 * CIS Telemetry — Unified Dispatcher
 *
 * Responsibilities:
 *  - Page view logging (GET).
 *  - Performance profiling (PHP execution, memory).
 *  - Ingest frontend telemetry.js batches (mouse, idle, devtools).
 *  - Route into Security Service + Module sinks.
 *  - Write into DB logs: user_activity_log, system_profiling_log.
 */

require_once $_SERVER['DOCUMENT_ROOT'].'/core/bootstrap.php';

/**
 * DB helpers (replace with your CIS db() wrapper if present)
 */
function tdb(): \PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    return $pdo;
}

/**
 * Ingest frontend telemetry.js events
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw,true);
    if ($data && isset($data['events'])) {
        $uid = $_SESSION['userID'] ?? 0;
        $sid = $data['session'] ?? session_id();
        foreach($data['events'] as $e){
            $stmt = tdb()->prepare("
                INSERT INTO user_activity_log (user_id, session_id, page, event_type, event_data)
                VALUES (:uid,:sid,:page,:etype,:edata)
            ");
            $stmt->execute([
                'uid'   => $uid,
                'sid'   => $sid,
                'page'  => $e['page'] ?? '',
                'etype' => $e['event_type'] ?? '',
                'edata' => json_encode($e['data'] ?? []),
            ]);
        }
        exit("OK");
    }
}

/**
 * Page view logging
 */
if (!function_exists('cis_log_page_view')) {
    function cis_log_page_view(string $module, string $view, array $ctx=[]): void {
        try {
            $uid   = (int)($_SESSION['userID'] ?? 0);
            $role  = (string)($_SESSION['role'] ?? ($_SESSION['userRole'] ?? 'user'));
            $uri   = $_SERVER['REQUEST_URI'] ?? '';
            $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sid   = session_id();

            $payload = array_merge([
                'module'=>$module,'view'=>$view,
                'user_id'=>$uid,'role'=>$role,
                'uri'=>$uri,'ip'=>$ip,'ua'=>$ua,'session_id'=>$sid
            ],$ctx);

            // Security service plugin
            $secFile = $_SERVER['DOCUMENT_ROOT'].'/assets/services/security/page_view.php';
            if (is_file($secFile)) {
                require_once $secFile;
                if (function_exists('sec_page_view_log')) sec_page_view_log($payload);
            }

            // Module sinks
            if (strpos($module,'transfers')===0) {
                $stx = $_SERVER['DOCUMENT_ROOT'].'/modules/transfers/stock/core/Logger.php';
                if (is_file($stx)) { require_once $stx; if (function_exists('stx_log_page_view')) stx_log_page_view($payload); }
            }
            if (strpos($module,'purchase-orders')===0) {
                $po = $_SERVER['DOCUMENT_ROOT'].'/modules/purchase-orders/core/Logger.php';
                if (is_file($po)) { require_once $po; if (function_exists('po_log_page_view')) po_log_page_view($payload); }
            }

        } catch (\Throwable $e) {
            error_log('[cis_log_page_view] '.$e->getMessage());
        }
    }
}

/**
 * Page performance logging (server-side)
 */
if (!function_exists('cis_log_page_perf')) {
    function cis_log_page_perf(string $module, string $view, int $ms, array $ctx=[]): void {
        try {
            $payload = array_merge([
                'module'=>$module,'view'=>$view,'ms'=>$ms,
                'peak_memory'=>memory_get_peak_usage(true),
                'uri'=>$_SERVER['REQUEST_URI'] ?? ''
            ],$ctx);

            // Security service plugin
            $secFile = $_SERVER['DOCUMENT_ROOT'].'/assets/services/security/page_perf.php';
            if (is_file($secFile)) {
                require_once $secFile;
                if (function_exists('sec_page_perf_log')) sec_page_perf_log($payload);
            }

            // DB system profiling log
            $stmt = tdb()->prepare("
                INSERT INTO system_profiling_log
                (session_id,user_id,endpoint,php_time_ms,memory_mb,created_at)
                VALUES (:sid,:uid,:ep,:ms,:mem,NOW())
            ");
            $stmt->execute([
                'sid'=>session_id(),
                'uid'=>$_SESSION['userID'] ?? 0,
                'ep'=>$_SERVER['REQUEST_URI'] ?? '',
                'ms'=>$ms,
                'mem'=>round($payload['peak_memory']/1048576,2),
            ]);

            // Module sinks (optional)
            if (strpos($module,'transfers')===0) {
                $stx = $_SERVER['DOCUMENT_ROOT'].'/modules/transfers/stock/core/Logger.php';
                if (is_file($stx)) { require_once $stx; if (function_exists('stx_log_page_perf')) stx_log_page_perf($payload); }
            }
            if (strpos($module,'purchase-orders')===0) {
                $po = $_SERVER['DOCUMENT_ROOT'].'/modules/purchase-orders/core/Logger.php';
                if (is_file($po)) { require_once $po; if (function_exists('po_log_page_perf')) po_log_page_perf($payload); }
            }

        } catch (\Throwable $e) {
            error_log('[cis_log_page_perf] '.$e->getMessage());
        }
    }
}

/**
 * Profiler starter (registers shutdown hook)
 */
if (!function_exists('cis_profiler_start')) {
    function cis_profiler_start(string $module, string $view, array $ctx=[]): void {
        $GLOBALS['__cis_prof_start'] = microtime(true);
        $GLOBALS['__cis_prof_mod'] = $module;
        $GLOBALS['__cis_prof_view'] = $view;
        $GLOBALS['__cis_prof_ctx'] = $ctx;
        register_shutdown_function(static function () {
            $start = (float)($GLOBALS['__cis_prof_start'] ?? microtime(true));
            $ms = (int)round((microtime(true)-$start)*1000);
            $m = (string)($GLOBALS['__cis_prof_mod'] ?? '');
            $v = (string)($GLOBALS['__cis_prof_view'] ?? '');
            $c = (array)($GLOBALS['__cis_prof_ctx'] ?? []);
            if ($m && $v && function_exists('cis_log_page_perf')) {
                cis_log_page_perf($m,$v,$ms,$c);
            }
        });
    }
}
