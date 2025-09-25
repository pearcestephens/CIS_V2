<?php
declare(strict_types=1);
require_once __DIR__ . '/../tools.php';
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);
try {
  $pdo = cis_pdo();
  $uid = (int)$__ajax_context['uid'];
  $d = $pdo->prepare('DELETE FROM transfer_locks WHERE transfer_id = ? AND owner_user_id = ?');
  $d->execute([$tid, $uid]);
  jresp(true, ['released' => $d->rowCount() > 0]);
} catch (Throwable $e) { jresp(false, 'Release error', 500); }
