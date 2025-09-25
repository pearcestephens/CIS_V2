<?php
declare(strict_types=1);
require_once __DIR__ . '/../tools.php';
$tid = (int)($_GET['transfer_id'] ?? $_POST['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);
try {
  $pdo = cis_pdo();
  // Auto-expire stale (2 minutes)
  $pdo->exec("DELETE FROM transfer_locks WHERE expires_at <= NOW() OR last_heartbeat <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
  $q = $pdo->prepare('SELECT owner_user_id, owner_name, requester_user_id, requester_name, requested_at, last_heartbeat, expires_at FROM transfer_locks WHERE transfer_id = ?');
  $q->execute([$tid]);
  $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
  if ($row && !empty($row['requested_at'])) {
    $chk = $pdo->query("SELECT TIMESTAMPDIFF(SECOND, " . $pdo->quote((string)$row['requested_at']) . ", NOW()) AS secs")->fetch(PDO::FETCH_ASSOC);
    $row['_secs_since_request'] = (int)($chk['secs'] ?? 0);
  }
  jresp(true, ['lock' => $row]);
} catch (Throwable $e) { jresp(false, 'Poll error', 500); }
