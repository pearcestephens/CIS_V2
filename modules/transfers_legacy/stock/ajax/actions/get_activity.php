<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/DevState.php';

// Try to build an activity feed from real DB tables when available; fallback to DevState
$items = [];
$outletIds = [];

try {
  if (function_exists('cis_pdo')) {
    $pdo = cis_pdo();
    // Probe table existence
    $hasTransfers = false; $hasLogs = false; $hasAudit = false; $hasNotes = false;
    try { $pdo->query('SELECT 1 FROM transfers LIMIT 1'); $hasTransfers = true; } catch (Throwable $e) {}
    try { $pdo->query('SELECT 1 FROM transfer_logs LIMIT 1'); $hasLogs = true; } catch (Throwable $e) {}
    try { $pdo->query('SELECT 1 FROM transfer_audit_log LIMIT 1'); $hasAudit = true; } catch (Throwable $e) {}
    try { $pdo->query('SELECT 1 FROM transfer_notes LIMIT 1'); $hasNotes = true; } catch (Throwable $e) {}

    if ($hasTransfers) {
      // Subqueries for latest timestamps per transfer from logs/audits/notes
      $joins = [];
      if ($hasLogs)  { $joins[] = 'LEFT JOIN (SELECT transfer_id, MAX(created_at) AS last_log_at FROM transfer_logs GROUP BY transfer_id) lg ON lg.transfer_id = t.id'; }
      if ($hasAudit) { $joins[] = 'LEFT JOIN (SELECT transfer_id, MAX(created_at) AS last_audit_at FROM transfer_audit_log GROUP BY transfer_id) au ON au.transfer_id = t.id'; }
      if ($hasNotes) { $joins[] = 'LEFT JOIN (SELECT transfer_id, MAX(created_at) AS last_note_at FROM transfer_notes GROUP BY transfer_id) nt ON nt.transfer_id = t.id'; }
      $joinsSql = $joins ? ("\n" . implode("\n", $joins)) : '';

      // Latest detailed rows for audit and logs to expose event/action + actor
      $auditDetailJoin = '';
      $logDetailJoin = '';
      if ($hasAudit) {
        $auditDetailJoin = "LEFT JOIN (
            SELECT tal.transfer_id, tal.action AS au_action, tal.actor_id AS au_actor_id, tal.created_at AS au_created_at
            FROM transfer_audit_log tal
            JOIN (
              SELECT transfer_id, MAX(created_at) AS mx
              FROM transfer_audit_log
              GROUP BY transfer_id
            ) m ON m.transfer_id = tal.transfer_id AND m.mx = tal.created_at
        ) aul ON aul.transfer_id = t.id";
      }
      if ($hasLogs) {
        $logDetailJoin = "LEFT JOIN (
            SELECT tl.transfer_id, tl.event_type AS lg_event_type, tl.actor_user_id AS lg_actor_id, tl.severity AS lg_severity, tl.created_at AS lg_created_at
            FROM transfer_logs tl
            JOIN (
              SELECT transfer_id, MAX(created_at) AS mx
              FROM transfer_logs
              GROUP BY transfer_id
            ) m ON m.transfer_id = tl.transfer_id AND m.mx = tl.created_at
        ) lgl ON lgl.transfer_id = t.id";
      }

      // Compose query: latest activity per transfer ordered by newest first, plus last event fields
      $sql = "SELECT t.id AS transfer_id, t.status AS state, t.outlet_from AS `from`, t.outlet_to AS `to`,
                     GREATEST(
                       COALESCE(t.updated_at, t.created_at, '1970-01-01'),
                       COALESCE(lg.last_log_at, '1970-01-01'),
                       COALESCE(au.last_audit_at, '1970-01-01'),
                       COALESCE(nt.last_note_at, '1970-01-01')
                     ) AS latest_at,
                     aul.au_action, aul.au_actor_id, aul.au_created_at,
                     lgl.lg_event_type, lgl.lg_actor_id, lgl.lg_severity, lgl.lg_created_at
              FROM transfers t
              $joinsSql
              $auditDetailJoin
              $logDetailJoin
              ORDER BY latest_at DESC
              LIMIT 50";
      $stmt = $pdo->query($sql);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as $r) {
        $from = (string)($r['from'] ?? '');
        $to   = (string)($r['to'] ?? '');
        if ($from !== '') $outletIds[$from] = true;
        if ($to !== '') $outletIds[$to] = true;
        // Decide last event display (prefer the one with the newer timestamp between audit and log)
        $auAt = isset($r['au_created_at']) ? (string)$r['au_created_at'] : '';
        $lgAt = isset($r['lg_created_at']) ? (string)$r['lg_created_at'] : '';
        $lastEvent = '';
        $actorId = null;
        if ($auAt !== '' || $lgAt !== '') {
          if ($lgAt > $auAt) { $lastEvent = (string)($r['lg_event_type'] ?? ''); $actorId = $r['lg_actor_id'] ?? null; }
          else { $lastEvent = (string)($r['au_action'] ?? ''); $actorId = $r['au_actor_id'] ?? null; }
        }
        $items[] = [
          'transfer_id' => (int)($r['transfer_id'] ?? 0),
          'state'       => (string)($r['state'] ?? ''),
          'latest_at'   => (string)($r['latest_at'] ?? ''),
          'from'        => $from,
          'to'          => $to,
          'flag_count'  => 0,
          'last_event'  => $lastEvent,
          'actor_id'    => $actorId !== null ? (string)$actorId : null,
        ];
      }

      // Enrich outlet names for display
      if (!empty($outletIds)) {
        $ids = array_keys($outletIds);
        $chunk = array_slice($ids, 0, 500);
        if (count($chunk) > 0) {
          $ph = implode(',', array_fill(0, count($chunk), '?'));
          $st = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($ph)");
          $st->execute($chunk);
          $omap = [];
          while ($row = $st->fetch(PDO::FETCH_ASSOC)) { $omap[(string)$row['id']] = (string)($row['name'] ?? ''); }
          foreach ($items as &$it) {
            $it['from_name'] = $omap[(string)($it['from'] ?? '')] ?? '';
            $it['to_name']   = $omap[(string)($it['to'] ?? '')] ?? '';
          }
          unset($it);
        }
      }

      jresp(true, ['items' => $items], 200);
      return;
    }
  }
} catch (Throwable $e) {
  // Fall through to DevState below on any errors
}

// Fallback: DevState snapshot when DB tables are unavailable
$all = DevState::loadAll();
foreach ($all as $tid => $row) {
  $id = (int)$tid;
  $times = [
    (string)($row['last_touched_at'] ?? ''),
    (string)($row['last_edited_at'] ?? ''),
    (string)($row['last_opened_at'] ?? ''),
    (string)($row['updated_at'] ?? ''),
  ];
  $latest = '';
  foreach ($times as $t) { if ($t !== '' && $t > $latest) { $latest = $t; } }
  $from = (string)($row['outlet_from'] ?? '');
  $to = (string)($row['outlet_to'] ?? '');
  if ($from !== '') $outletIds[$from] = true;
  if ($to !== '') $outletIds[$to] = true;
  $items[] = [
    'transfer_id' => $id,
    'state' => (string)($row['state'] ?? ''),
    'latest_at' => $latest,
    'from' => $from,
    'to' => $to,
    'flag_count' => (int)(is_array($row['inaccuracies'] ?? null) ? count($row['inaccuracies']) : (int)($row['inaccuracies'] ?? 0)),
  ];
}
usort($items, function($a,$b){ return strcmp($b['latest_at'], $a['latest_at']); });
if (count($items) > 20) { $items = array_slice($items, 0, 20); }

// Optional enrichment for outlet names
try {
  if (function_exists('cis_pdo') && !empty($items)) {
    $idList = array_keys($outletIds);
    if (count($idList) > 0) {
      $chunk = array_slice($idList, 0, 500);
      $placeholders = implode(',', array_fill(0, count($chunk), '?'));
      $pdo = cis_pdo();
      $stmt = $pdo->prepare("SELECT id, name FROM vend_outlets WHERE id IN ($placeholders)");
      $stmt->execute($chunk);
      $map = [];
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $map[(string)$row['id']] = (string)($row['name'] ?? ''); }
      foreach ($items as &$it) {
        $it['from_name'] = isset($map[(string)($it['from'] ?? '')]) ? $map[(string)$it['from']] : '';
        $it['to_name'] = isset($map[(string)($it['to'] ?? '')]) ? $map[(string)$it['to']] : '';
      }
      unset($it);
    }
  }
} catch (Throwable $e) { /* soft-fail */ }

jresp(true, ['items' => $items], 200);
