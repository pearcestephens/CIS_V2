<?php
declare(strict_types=1);
// Stock module AJAX tools — minimal helpers only; no legacy includes
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
// Optional query profiler for slow SQL auditing
$__stx_qp = __DIR__ . '/QueryProfiler.php';
if (is_file($__stx_qp)) { require_once $__stx_qp; }
if (!function_exists('requireLoggedInUser')) {
  function requireLoggedInUser(){
    if (!isset($_SESSION)) session_start();
    if (!isset($_SESSION['userID']) || (int)$_SESSION['userID']<=0) throw new RuntimeException('Not logged in');
    return ['id'=>(int)$_SESSION['userID']];
  }
}
if (!function_exists('verifyCSRFToken')) {
  function verifyCSRFToken($token): bool {
    if (!isset($_SESSION)) session_start();
    $t = (string)$token;
    $s = (string)($_SESSION['csrf_token'] ?? '');
    return $t !== '' && $s !== '' && hash_equals($s, $t);
  }
}

// --- DB access helper (prefers cis_pdo if available) ---
if (!function_exists('stx_db')) {
  function stx_db(): \PDO {
    static $pdo = null;
    if ($pdo instanceof \PDO) return $pdo;
    if (function_exists('cis_pdo')) {
      /** @var \PDO $p */
      $p = cis_pdo();
      $p->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      if (class_exists('STXPDOStatement')) {
        try { $p->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['STXPDOStatement']); } catch (\Throwable $e) { /* ignore */ }
      }
      return $pdo = $p;
    }
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
      $GLOBALS['pdo']->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      if (class_exists('STXPDOStatement')) {
        try { $GLOBALS['pdo']->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['STXPDOStatement']); } catch (\Throwable $e) { /* ignore */ }
      }
      return $pdo = $GLOBALS['pdo'];
    }
    throw new \RuntimeException('No PDO available');
  }
}

// --- Logging helpers ---
if (!function_exists('stx_log_transfer_event')) {
  /**
   * Insert a row into transfer_logs
   * @param array $ctx Keys: transfer_id, shipment_id, item_id, parcel_id, staff_transfer_id, event_type, event_data,
   *                    actor_user_id, actor_role, severity, source_system, trace_id, customer_id
   */
  function stx_log_transfer_event(array $ctx): void {
    try {
      $pdo = stx_db();
      $sql = 'INSERT INTO transfer_logs (
        transfer_id, shipment_id, item_id, parcel_id, staff_transfer_id, event_type, event_data,
        actor_user_id, actor_role, severity, source_system, trace_id, customer_id, created_at
      ) VALUES (
        :transfer_id, :shipment_id, :item_id, :parcel_id, :staff_transfer_id, :event_type, :event_data,
        :actor_user_id, :actor_role, :severity, :source_system, :trace_id, :customer_id, NOW()
      )';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':transfer_id'      => $ctx['transfer_id']      ?? null,
        ':shipment_id'      => $ctx['shipment_id']      ?? null,
        ':item_id'          => $ctx['item_id']          ?? null,
        ':parcel_id'        => $ctx['parcel_id']        ?? null,
        ':staff_transfer_id'=> $ctx['staff_transfer_id']?? null,
        ':event_type'       => (string)($ctx['event_type'] ?? ''),
        ':event_data'       => is_string($ctx['event_data'] ?? null) ? $ctx['event_data'] : json_encode($ctx['event_data'] ?? null, JSON_UNESCAPED_SLASHES),
        ':actor_user_id'    => $ctx['actor_user_id']    ?? null,
        ':actor_role'       => (string)($ctx['actor_role'] ?? ''),
        ':severity'         => (string)($ctx['severity'] ?? 'info'),
        ':source_system'    => (string)($ctx['source_system'] ?? 'cis.transfers'),
        ':trace_id'         => (string)($ctx['trace_id'] ?? ''),
        ':customer_id'      => $ctx['customer_id']      ?? null,
      ]);
    } catch (\Throwable $e) {
      error_log('[stx_log_transfer_event] '.$e->getMessage());
    }
  }
}

if (!function_exists('stx_log_transfer_audit')) {
  /**
   * Insert a row into transfer_audit_log
   * @param array $ctx Keys: entity_type, entity_pk, transfer_pk, transfer_id, vend_consignment_id, vend_transfer_id,
   *                   action, status, actor_type, actor_id, outlet_from, outlet_to, data_before, data_after,
   *                   metadata, error_details, processing_time_ms, api_response, session_id, ip_address, user_agent
   */
  function stx_log_transfer_audit(array $ctx): void {
    try {
      $pdo = stx_db();
      $sql = 'INSERT INTO transfer_audit_log (
        entity_type, entity_pk, transfer_pk, transfer_id, vend_consignment_id, vend_transfer_id,
        action, status, actor_type, actor_id, outlet_from, outlet_to, data_before, data_after,
        metadata, error_details, processing_time_ms, api_response, session_id, ip_address, user_agent, created_at
      ) VALUES (
        :entity_type, :entity_pk, :transfer_pk, :transfer_id, :vend_consignment_id, :vend_transfer_id,
        :action, :status, :actor_type, :actor_id, :outlet_from, :outlet_to, :data_before, :data_after,
        :metadata, :error_details, :processing_time_ms, :api_response, :session_id, :ip_address, :user_agent, NOW()
      )';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([
        ':entity_type'         => (string)($ctx['entity_type'] ?? 'transfer'),
        ':entity_pk'           => $ctx['entity_pk']           ?? null,
        ':transfer_pk'         => $ctx['transfer_pk']         ?? null,
        ':transfer_id'         => $ctx['transfer_id']         ?? null,
        ':vend_consignment_id' => $ctx['vend_consignment_id'] ?? null,
        ':vend_transfer_id'    => $ctx['vend_transfer_id']    ?? null,
        ':action'              => (string)($ctx['action'] ?? ''),
        ':status'              => (string)($ctx['status'] ?? ''),
        ':actor_type'          => (string)($ctx['actor_type'] ?? 'user'),
        ':actor_id'            => (string)($ctx['actor_id'] ?? ''),
        ':outlet_from'         => $ctx['outlet_from']         ?? null,
        ':outlet_to'           => $ctx['outlet_to']           ?? null,
        ':data_before'         => is_string($ctx['data_before'] ?? null) ? $ctx['data_before'] : json_encode($ctx['data_before'] ?? null, JSON_UNESCAPED_SLASHES),
        ':data_after'          => is_string($ctx['data_after'] ?? null) ? $ctx['data_after'] : json_encode($ctx['data_after'] ?? null, JSON_UNESCAPED_SLASHES),
        ':metadata'            => is_string($ctx['metadata'] ?? null) ? $ctx['metadata'] : json_encode($ctx['metadata'] ?? null, JSON_UNESCAPED_SLASHES),
        ':error_details'       => is_string($ctx['error_details'] ?? null) ? $ctx['error_details'] : json_encode($ctx['error_details'] ?? null, JSON_UNESCAPED_SLASHES),
        ':processing_time_ms'  => (int)($ctx['processing_time_ms'] ?? 0),
        ':api_response'        => is_string($ctx['api_response'] ?? null) ? $ctx['api_response'] : json_encode($ctx['api_response'] ?? null, JSON_UNESCAPED_SLASHES),
        ':session_id'          => (string)($ctx['session_id'] ?? ''),
        ':ip_address'          => (string)($ctx['ip_address'] ?? ''),
        ':user_agent'          => (string)($ctx['user_agent'] ?? ''),
      ]);
    } catch (\Throwable $e) {
      error_log('[stx_log_transfer_audit] '.$e->getMessage());
    }
  }
}

if (!function_exists('stx_log_action_envelope')) {
  /**
   * Convenience: log both event + audit for the current AJAX action.
   */
  function stx_log_action_envelope(bool $ok, $payload, int $code): void {
    try {
      $action   = $_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '';
      $reqId    = $GLOBALS['reqId'] ?? ($GLOBALS['__ajax_context']['request_id'] ?? '');
      $start    = $GLOBALS['__stx_start_ts'] ?? microtime(true);
      $ms       = (int) round((microtime(true) - (float)$start) * 1000);
      $uid      = (int)($GLOBALS['__ajax_context']['uid'] ?? ($_SESSION['userID'] ?? 0));
      $actorTyp = !empty($GLOBALS['__ajax_context']['internal']) ? 'api' : 'user';
      $role     = $_SESSION['role'] ?? ($_SESSION['userRole'] ?? 'user');
      $transferId = (int)($_POST['transfer_id'] ?? $_POST['id'] ?? $_POST['tid'] ?? 0);
      $shipmentId = (int)($_POST['shipment_id'] ?? 0);
      $itemId     = (int)($_POST['item_id'] ?? 0);
      $parcelId   = (int)($_POST['parcel_id'] ?? 0);
      $staffId    = (int)($_POST['staff_transfer_id'] ?? 0);
      $from       = $_POST['from'] ?? $_POST['outlet_from'] ?? null;
      $to         = $_POST['to']   ?? $_POST['outlet_to']   ?? null;
      $sessId     = session_id();
      $ip         = $_SERVER['REMOTE_ADDR']   ?? '';
      $ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
      // Pick up any provided before/after snapshots from the action
      $before    = $GLOBALS['__stx_audit_before'] ?? null;
      $after     = $GLOBALS['__stx_audit_after']  ?? null;

      // transfer_logs
      stx_log_transfer_event([
        'transfer_id'       => $transferId ?: null,
        'shipment_id'       => $shipmentId ?: null,
        'item_id'           => $itemId ?: null,
        'parcel_id'         => $parcelId ?: null,
        'staff_transfer_id' => $staffId ?: null,
        'event_type'        => $action,
        'event_data'        => ['code'=>$code,'ok'=>$ok,'payload'=>$ok ? null : $payload,'before'=>$before,'after'=>$after],
        'actor_user_id'     => $uid ?: null,
        'actor_role'        => (string)$role,
        'severity'          => $ok ? 'info' : 'error',
        'source_system'     => 'cis.transfers',
        'trace_id'          => (string)$reqId,
        'customer_id'       => $_POST['customer_id'] ?? null,
      ]);

      // transfer_audit_log
      stx_log_transfer_audit([
        'entity_type'        => 'transfer',
        'entity_pk'          => $transferId ?: null,
        'transfer_pk'        => $transferId ?: null,
        'transfer_id'        => $transferId ?: null,
        'vend_consignment_id'=> $_POST['vend_consignment_id'] ?? null,
        'vend_transfer_id'   => $_POST['vend_transfer_id'] ?? null,
        'action'             => (string)$action,
        'status'             => $ok ? 'success' : 'error',
        'actor_type'         => $actorTyp,
        'actor_id'           => (string)($uid ?: ''),
        'outlet_from'        => $from,
        'outlet_to'          => $to,
        'data_before'        => $before,
        'data_after'         => $after,
        'metadata'           => ['request_id'=>$reqId, 'ajax_action'=>$action],
        'error_details'      => $ok ? null : (is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES)),
        'processing_time_ms' => $ms,
        'api_response'       => $ok ? (is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES) : (is_string($payload) ? $payload : null)) : null,
        'session_id'         => $sessId,
        'ip_address'         => $ip,
        'user_agent'         => $ua,
      ]);
    } catch (\Throwable $e) {
      error_log('[stx_log_action_envelope] '.$e->getMessage());
    }
  }
}

// --- Audit snapshot helpers (to be called by action scripts) ---
if (!function_exists('stx_set_audit_snapshots')) {
  /**
   * Provide before/after state snapshots for the current action.
   * These will be included in transfer_audit_log.data_before/data_after.
   * @param mixed $before
   * @param mixed $after
   */
  function stx_set_audit_snapshots($before, $after): void {
    $GLOBALS['__stx_audit_before'] = $before;
    $GLOBALS['__stx_audit_after']  = $after;
  }
}
