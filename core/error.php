<?php
declare(strict_types=1);

/**
 * /core/error.php
 * Central error/exception capture + lightweight profiling.
 */

require_once __DIR__ . '/bootstrap.php';

set_error_handler(function (int $severity, string $message, string $file = '', int $line = 0) {
    if (!(error_reporting() & $severity)) return false;
    cis_log_error('PHP_ERROR', $message, $file, $line, $severity);
    return true;
});

set_exception_handler(function (\Throwable $e) {
    cis_log_error('PHP_EXCEPTION', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getCode(), $e->getTraceAsString());
    if (!headers_sent()) http_response_code(500);
    // For HTML, you could render a friendly error page here. For JSON handlers, the handler returns a clean JSON.
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        cis_log_error('PHP_FATAL', $err['message'], $err['file'], $err['line'], $err['type']);
        if (!headers_sent()) http_response_code(500);
    }
});

/** Persist error to file and (best-effort) DB table system_error_log */
function cis_log_error(string $kind, string $message, string $file, int $line, $code = null, string $trace = ''): void {
    $row = [
        'ts'      => date('c'),
        'kind'    => $kind,
        'message' => $message,
        'file'    => $file,
        'line'    => $line,
        'code'    => (string)$code,
    ];
    $log = '['.$row['ts'].'] '.$kind.' '.$row['message'].' ('.$row['file'].':'.$row['line'].') code='.$row['code'].PHP_EOL;
    @file_put_contents(sys_get_temp_dir().'/cis_error.log', $log, FILE_APPEND);

    try {
        $pdo = db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_error_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                kind VARCHAR(64),
                message TEXT,
                file VARCHAR(255),
                line INT,
                code VARCHAR(32)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $q = $pdo->prepare("INSERT INTO system_error_log (kind, message, file, line, code) VALUES (:k,:m,:f,:l,:c)");
        $q->execute([':k'=>$kind, ':m'=>$message, ':f'=>$file, ':l'=>$line, ':c'=>(string)$code]);
    } catch (\Throwable $e) {
        // ignore DB failures
    }
}

/** Profiling accumulator + flush to DB (system_profiling_log) */
function cis_profile_flush(array $extra = []): void {
    try {
        $pdo = db();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS system_profiling_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                endpoint VARCHAR(128),
                ms INT,
                meta_json JSON
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $endpoint = (string)($extra['endpoint'] ?? 'unknown');
        $ms = (int)($extra['ms'] ?? 0);
        $meta = json_encode($extra, JSON_UNESCAPED_SLASHES);
        $q = $pdo->prepare("INSERT INTO system_profiling_log (endpoint, ms, meta_json) VALUES (:e,:m,:j)");
        $q->execute([':e'=>$endpoint, ':m'=>$ms, ':j'=>$meta]);
    } catch (\Throwable $e) {
        // ignore
    }
}
