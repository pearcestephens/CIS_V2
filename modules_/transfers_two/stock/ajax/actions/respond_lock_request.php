<?php
declare(strict_types=1);
require_once __DIR__ . '/../tools.php';
$tid = (int)($_POST['transfer_id'] ?? 0);
$accept = isset($_POST['accept']) ? (int)$_POST['accept'] : 0;
if ($tid <= 0) jresp(false, 'transfer_id required', 400);
try {
  $pdo = cis_pdo();
  $uid = (int)$__ajax_context['uid'];
  if ($accept === 1) {
    // Owner relinquishes
    $d = $pdo->prepare('DELETE FROM transfer_locks WHERE transfer_id = ? AND owner_user_id = ?');
    $d->execute([$tid, $uid]);
    jresp(true, ['released' => $d->rowCount() > 0]);
  } else {
    // Decline: clear request fields only
    $u = $pdo->prepare('UPDATE transfer_locks SET requester_user_id = NULL, requester_name = NULL, requested_at = NULL WHERE transfer_id = ? AND owner_user_id = ?');
    $u->execute([$tid, $uid]);
    jresp(true, ['declined' => true]);
  }
} catch (Throwable $e) { jresp(false, 'Respond error', 500); }
