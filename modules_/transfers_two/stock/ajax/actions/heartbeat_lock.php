<?php
declare(strict_types=1);
require_once __DIR__ . '/../tools.php';
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);
try {
  $pdo = cis_pdo();
  $uid = (int)$__ajax_context['uid'];
  $u = $pdo->prepare('UPDATE transfer_locks SET last_heartbeat = NOW() WHERE transfer_id = ? AND owner_user_id = ?');
  $u->execute([$tid, $uid]);
  jresp(true, ['ok' => $u->rowCount() > 0]);
} catch (Throwable $e) { jresp(false, 'HB error', 500); }
