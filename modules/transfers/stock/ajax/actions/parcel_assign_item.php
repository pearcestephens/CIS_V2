<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');
try {
  cis_require_login();
  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tid = (int)($in['transfer_id'] ?? 0);
  $parcelId = (int)($in['parcel_id'] ?? 0);
  $itemId   = (int)($in['item_id'] ?? 0);
  $qty      = (int)($in['qty'] ?? -1);
  if ($tid<=0 || $parcelId<=0 || $itemId<=0 || $qty<0) throw new InvalidArgumentException('Bad input');

  $pdo = db_rw(); $pdo->beginTransaction();

  // Ownership & bounds
  $ok = $pdo->prepare("SELECT s.transfer_id FROM transfer_parcels p JOIN transfer_shipments s ON s.id=p.shipment_id WHERE p.id=:pid");
  $ok->execute([':pid'=>$parcelId]);
  $tOfParcel = (int)$ok->fetchColumn();
  if ($tOfParcel !== $tid) throw new RuntimeException('Parcel not in transfer');

  $ti = $pdo->prepare("SELECT qty_requested, qty_sent_total FROM transfer_items WHERE id=:id AND transfer_id=:tid FOR UPDATE");
  $ti->execute([':id'=>$itemId, ':tid'=>$tid]);
  $row = $ti->fetch();
  if (!$row) throw new RuntimeException('Item not found');

  if ($qty > (int)$row['qty_sent_total']) throw new InvalidArgumentException('Qty exceeds sent total');

  // Upsert
  $ins = $pdo->prepare("INSERT INTO transfer_parcel_items (parcel_id,item_id,qty,qty_received)
                        VALUES (:pid,:iid,:q,0)
                        ON DUPLICATE KEY UPDATE qty=VALUES(qty)");
  $ins->execute([':pid'=>$parcelId, ':iid'=>$itemId, ':q'=>$qty]);

  cis_log('INFO','transfers','parcel.assign', ['transfer_id'=>$tid,'parcel_id'=>$parcelId,'item_id'=>$itemId,'qty'=>$qty]);

  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
