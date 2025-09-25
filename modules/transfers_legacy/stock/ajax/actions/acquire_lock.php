<?php
declare(strict_types=1);
require_once __DIR__ . '/../tools.php';

$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);

try {
  $pdo = cis_pdo();
  $uid = (int)$__ajax_context['uid'];
  $uname = (string)($_SESSION['userName'] ?? $_SESSION['username'] ?? 'Staff');

  // Clean up expired locks (short TTL: 2 minutes)
  $pdo->exec("DELETE FROM transfer_locks WHERE expires_at <= NOW() OR last_heartbeat <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)");

  // Check existing lock (including requester fields)
  $stmt = $pdo->prepare('SELECT owner_user_id, owner_name, last_heartbeat, expires_at, requester_user_id, requester_name, requested_at FROM transfer_locks WHERE transfer_id = ? LIMIT 1');
  $stmt->execute([$tid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && (int)$row['owner_user_id'] !== $uid) {
    // If current user is requester and 60s elapsed since request, auto-takeover
    $isRequester = isset($row['requester_user_id']) && (int)$row['requester_user_id'] === $uid;
    $elapsedOk = false;
    if (!empty($row['requested_at'])) {
      $chk = $pdo->query("SELECT TIMESTAMPDIFF(SECOND, " . $pdo->quote((string)$row['requested_at']) . ", NOW()) AS secs")->fetch(PDO::FETCH_ASSOC);
      $elapsedOk = $chk && (int)$chk['secs'] >= 60;
    }
    if ($isRequester && $elapsedOk) {
      $u = $pdo->prepare('UPDATE transfer_locks SET owner_user_id = ?, owner_name = ?, last_heartbeat = NOW(), requester_user_id = NULL, requester_name = NULL, requested_at = NULL WHERE transfer_id = ?');
      $u->execute([$uid, $uname, $tid]);
      jresp(true, [ 'locked' => true, 'owner' => [ 'id' => $uid, 'name' => $uname ], 'read_only' => false ]);
    }
    // Otherwise, still locked by another user
    jresp(true, [
      'locked' => true,
      'owner' => [ 'id' => (int)$row['owner_user_id'], 'name' => (string)$row['owner_name'] ],
      'expires_at' => (string)$row['expires_at'],
      'read_only' => true
    ]);
  }

  // Acquire or refresh
  if ($row) {
    $u = $pdo->prepare('UPDATE transfer_locks SET last_heartbeat = NOW(), owner_user_id = ?, owner_name = ? WHERE transfer_id = ?');
    $u->execute([$uid, $uname, $tid]);
  } else {
    $i = $pdo->prepare('INSERT INTO transfer_locks (transfer_id, owner_user_id, owner_name, acquired_at, last_heartbeat) VALUES (?, ?, ?, NOW(), NOW())');
    $i->execute([$tid, $uid, $uname]);
  }

  jresp(true, [ 'locked' => true, 'owner' => [ 'id' => $uid, 'name' => $uname ], 'read_only' => false ]);
} catch (Throwable $e) {
  jresp(false, 'Lock error: ' . $e->getMessage(), 500);
}
