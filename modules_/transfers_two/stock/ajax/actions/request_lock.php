<?php
declare(strict_types=1);
require_once __DIR__ . '/../tools.php';
$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false, 'transfer_id required', 400);
try {
  $pdo = cis_pdo();
  $uid = (int)$__ajax_context['uid'];
  $uname = (string)($_SESSION['userName'] ?? $_SESSION['username'] ?? 'Staff');
  $u = $pdo->prepare('UPDATE transfer_locks SET requester_user_id = ?, requester_name = ?, requested_at = NOW() WHERE transfer_id = ?');
  $u->execute([$uid, $uname, $tid]);
  jresp(true, ['ok' => true]);
} catch (Throwable $e) { jresp(false, 'Request error', 500); }
