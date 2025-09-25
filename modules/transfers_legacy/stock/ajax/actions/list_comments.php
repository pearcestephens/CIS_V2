<?php
/**
 * list_comments.php - returns latest comments for a transfer
 */

declare(strict_types=1);

$tid = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
$limit = max(1, min(200, (int)($_POST['limit'] ?? $_GET['limit'] ?? 100)));
if ($tid <= 0) jresp(false, 'transfer_id required', 400);

try {
  if (!function_exists('cis_pdo')) { jresp(false, 'DB unavailable', 500); }
  $pdo = cis_pdo();
  // Use existing transfer_notes table; map columns to API shape
  $st = $pdo->prepare('SELECT id, transfer_id, created_by AS user_id, NULL AS username, note_text AS note, created_at FROM transfer_notes WHERE transfer_id = ? ORDER BY created_at DESC, id DESC LIMIT ?');
  $st->bindValue(1, $tid, PDO::PARAM_INT);
  $st->bindValue(2, $limit, PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  jresp(true, [ 'items' => $rows ]);
} catch (Throwable $e) {
  jresp(false, 'Server error', 500);
}
