<?php
declare(strict_types=1);

/**
 * STX PDO Statement Profiler — logs slow SQL for Transfers AJAX actions.
 * Requires tools.php to set PDO::ATTR_STATEMENT_CLASS to this class.
 */

class STXPDOStatement extends \PDOStatement
{
    protected function __construct() {}

    public function execute($input_parameters = null): bool
    {
        $t0 = microtime(true);
        $ok = false;
        try {
            $ok = $input_parameters === null ? parent::execute() : parent::execute($input_parameters);
            return $ok;
        } finally {
            // measure and log when slow
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $threshold = 100; // ms
            if ($ms >= $threshold && function_exists('stx_log_transfer_audit')) {
                try {
                    $uid = (int)($GLOBALS['__ajax_context']['uid'] ?? ($_SESSION['userID'] ?? 0));
                    $sql = property_exists($this, 'queryString') ? (string)$this->queryString : '';
                    if (strlen($sql) > 500) { $sql = substr($sql, 0, 500) . '…'; }
                    stx_log_transfer_audit([
                        'entity_type' => 'transfer',
                        'action' => 'sql',
                        'status' => $ok ? 'success' : 'error',
                        'actor_type' => 'system',
                        'actor_id' => (string)$uid,
                        'metadata' => ['sql_ms' => $ms, 'sql' => $sql],
                        'processing_time_ms' => $ms,
                    ]);
                } catch (\Throwable $e) {
                    error_log('[STXPDOStatement] '.$e->getMessage());
                }
            }
        }
    }
}
