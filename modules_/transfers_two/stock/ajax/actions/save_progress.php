<?php
declare(strict_types=1);
require_once __DIR__ . '/../tools.php';
$tid = (int)($_POST['transfer_id'] ?? 0);
$payload = (string)($_POST['items'] ?? '');
if ($tid<=0) jresp(false,'transfer_id required',400);
try {
  $pdo = cis_pdo();
  $uid = (int)$__ajax_context['uid'];
  if ($payload === '') { jresp(true, ['saved'=>false,'empty'=>true]); }
  // Save JSON snapshot for live progress
  $ins = $pdo->prepare('INSERT INTO transfer_edit_snapshots (transfer_id, snapshot_json, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE snapshot_json = VALUES(snapshot_json), updated_by = VALUES(updated_by), updated_at = NOW(), version = version + 1');
  $ins->execute([$tid, $payload, $uid]);
  jresp(true, ['saved'=>true]);
} catch (Throwable $e) { jresp(false,'Save error',500); }
