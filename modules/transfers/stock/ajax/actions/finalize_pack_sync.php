<?php declare(strict_types=1);
require_once dirname(__DIR__, 6).'/bootstrap.php';
header('Content-Type: application/json');

try {
  cis_require_login();
  if (function_exists('cis_vend_writes_disabled') && cis_vend_writes_disabled()) {
    throw new RuntimeException('Temporarily disabled due to system health (Vend)');
  }

  $in = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $tid = (int)($in['transfer_id'] ?? 0);
  if ($tid<=0) throw new InvalidArgumentException('Bad transfer id');

  $pdo = db_rw(); $pdo->beginTransaction();

  // Totals from parcels
  $totW = (int)$pdo->query("SELECT COALESCE(SUM(p.weight_grams),0)
                            FROM transfer_parcels p
                            JOIN transfer_shipments s ON s.id=p.shipment_id
                            WHERE s.transfer_id=".(int)$tid)->fetchColumn();
  $totB = (int)$pdo->query("SELECT COUNT(*)
                            FROM transfer_parcels p
                            JOIN transfer_shipments s ON s.id=p.shipment_id
                            WHERE s.transfer_id=".(int)$tid)->fetchColumn();

  // State transitions
  $pdo->prepare("UPDATE transfers SET total_boxes=:b, total_weight_g=:w, state='PACKAGED' WHERE id=:t")
      ->execute([':b'=>$totB, ':w'=>$totW, ':t'=>$tid]);

  $pdo->prepare("UPDATE transfer_shipments SET status='packed', packed_at=NOW(), packed_by=:u WHERE transfer_id=:t")
      ->execute([':u'=>(int)($_SESSION['user_id']??0), ':t'=>$tid]);

  $pdo->commit();
  cis_log('INFO','transfers','pack.finalized.sync', ['transfer_id'=>$tid,'boxes'=>$totB,'weight_g'=>$totW]);

  echo json_encode(['ok'=>true, 'totals'=>['boxes'=>$totB,'weight_g'=>$totW]]);
} catch (Throwable $e) {
  if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
