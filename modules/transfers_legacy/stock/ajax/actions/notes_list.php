<?php
declare(strict_types=1);
/**
 * notes_list.php — list recent notes for a transfer
 */
if (!function_exists('jresp')) { http_response_code(500); echo json_encode(['success'=>false,'error'=>'Router missing']); exit; }

$transferId = (int)($_POST['transfer_id'] ?? 0);
$items = [];
try {
  if (function_exists('cis_pdo') && $transferId > 0) {
    $pdo = cis_pdo();
    $st = $pdo->prepare('SELECT id, note_text, created_by, created_at FROM transfer_notes WHERE transfer_id=:t ORDER BY id DESC LIMIT 200');
    $st->execute([':t'=>$transferId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  }
} catch (Throwable $e) {
  $items = [];
}

jresp(true, ['items'=>$items]);
