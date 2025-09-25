<?php declare(strict_types=1);
require_once dirname(__DIR__, 3).'/bootstrap.php';
require_once dirname(__DIR__, 3).'/core/queue/queue.php';

function reserve_job(PDO $pdo): ?array {
  $pdo->beginTransaction();
  $row = $pdo->query("SELECT * FROM queue_jobs
                      WHERE status='queued' AND available_at<=NOW()
                      ORDER BY priority ASC, id ASC LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
  if (!$row) { $pdo->commit(); return null; }
  $u = $pdo->prepare("UPDATE queue_jobs SET status='running', reserved_at=NOW(), worker_id=:w WHERE id=:id");
  $u->execute([':w'=>gethostname()?:'worker', ':id'=>$row['id']]);
  $pdo->commit();
  return $row;
}

function finish_job(PDO $pdo, int $id, string $status, ?string $err=null): void {
  if ($status==='done') {
    $u = $pdo->prepare("UPDATE queue_jobs SET status='done', finished_at=NOW(), last_error=NULL, updated_at=NOW() WHERE id=:id");
    $u->execute([':id'=>$id]);
  } else {
    // retry or dead
    $job = $pdo->query("SELECT attempts,max_attempts FROM queue_jobs WHERE id=".(int)$id)->fetch();
    $attempts = (int)$job['attempts'] + 1;
    if ($attempts >= (int)$job['max_attempts']) {
      $pdo->prepare("UPDATE queue_jobs SET status='dead', attempts=:a, last_error=:e, finished_at=NOW(), updated_at=NOW() WHERE id=:id")
          ->execute([':a'=>$attempts, ':e'=>$err, ':id'=>$id]);
    } else {
      $backoff = min(1800, (30 * (2 ** $attempts)) + random_int(0,10));
      $pdo->prepare("UPDATE queue_jobs SET status='queued', attempts=:a, last_error=:e, available_at=DATE_ADD(NOW(), INTERVAL :s SECOND),
                     reserved_at=NULL, worker_id=NULL, updated_at=NOW() WHERE id=:id")
          ->execute([':a'=>$attempts, ':e'=>$err, ':s'=>$backoff, ':id'=>$id]);
    }
  }
}

function run_labels_create(PDO $pdo, array $job): void {
  $p = json_decode($job['payload_json'], true);
  $tid = (int)($p['transfer_id'] ?? 0);
  $carrier = (string)($p['carrier'] ?? '');
  $boxes = $p['boxes'] ?? [];
  if ($tid<=0 || !$carrier || !is_array($boxes) || !$boxes) throw new RuntimeException('bad payload');

  // Ensure a shipment exists
  $s = $pdo->prepare("SELECT id FROM transfer_shipments WHERE transfer_id=:t ORDER BY id ASC LIMIT 1");
  $s->execute([':t'=>$tid]); $shipmentId = (int)($s->fetchColumn() ?: 0);
  if (!$shipmentId) {
    $insS = $pdo->prepare("INSERT INTO transfer_shipments (transfer_id, delivery_mode, status) VALUES (:t,'courier','packed')");
    $insS->execute([':t'=>$tid]); $shipmentId = (int)$pdo->lastInsertId();
  }

  // Create/ensure parcel rows
  foreach ($boxes as $b) {
    $box = (int)($b['box_number'] ?? 0);
    if ($box<=0) continue;
    $w = (int)($b['weight_grams'] ?? 0);
    $len = (int)($b['length_mm'] ?? 0); $wid = (int)($b['width_mm'] ?? 0); $hei = (int)($b['height_mm'] ?? 0);
    $pdo->prepare("INSERT INTO transfer_parcels (shipment_id, box_number, courier, weight_grams, length_mm, width_mm, height_mm, status, created_at)
                   VALUES (:s,:b,:c,:wg,:l,:w,:h,'pending',NOW())
                   ON DUPLICATE KEY UPDATE courier=VALUES(courier), weight_grams=VALUES(weight_grams),
                   length_mm=VALUES(length_mm), width_mm=VALUES(width_mm), height_mm=VALUES(height_mm), updated_at=NOW()")
        ->execute([':s'=>$shipmentId, ':b'=>$box, ':c'=>$carrier, ':wg'=>$w, ':l'=>$len, ':w'=>$wid, ':h'=>$hei]);
  }

  // Call carrier API — stubbed here; plug your real integration
  // On success: set tracking + label_url, status='labelled'
  $parcels = $pdo->prepare("SELECT id, box_number FROM transfer_parcels WHERE shipment_id=:s ORDER BY box_number ASC");
  $parcels->execute([':s'=>$shipmentId]);
  foreach ($parcels as $pRow) {
    $tracking = strtoupper($carrier).'-'.$tid.'-'.$pRow['box_number'].'-'.substr(hash('crc32b',(string)$pRow['id']),0,6);
    $labelUrl = '/labels/'.$tid.'/box_'.$pRow['box_number'].'.pdf'; // save real URL from API
    $pdo->prepare("UPDATE transfer_parcels SET tracking_number=:tn, label_url=:lu, status='labelled', updated_at=NOW() WHERE id=:id")
        ->execute([':tn'=>$tracking, ':lu'=>$labelUrl, ':id'=>$pRow['id']]);

    // event
    $pdo->prepare("INSERT INTO transfer_tracking_events (transfer_id, parcel_id, tracking_number, carrier, event_code, event_text, occurred_at)
                   VALUES (:t,:p,:tn,:c,'LABEL_CREATED','Label created',NOW())")
        ->execute([':t'=>$tid, ':p'=>$pRow['id'], ':tn'=>$tracking, ':c'=>$carrier]);
  }

  // Write or upsert transfer_carrier_orders snapshot
  $pdo->prepare("INSERT INTO transfer_carrier_orders (transfer_id,carrier,order_id,order_number,payload)
                 VALUES (:t,:c,:oid,:onum,:payload)
                 ON DUPLICATE KEY UPDATE order_id=VALUES(order_id), payload=VALUES(payload), updated_at=NOW()")
      ->execute([
        ':t'=>$tid, ':c'=>$carrier, ':oid'=>'ORD-'.$tid, ':onum'=>'TR-'.$tid,
        ':payload'=>json_encode(['boxes'=>$boxes], JSON_UNESCAPED_SLASHES)
      ]);

  cis_log('INFO','transfers','labels.created', ['transfer_id'=>$tid,'carrier'=>$carrier,'boxes'=>count($boxes)]);
}

function run_finalize_pack(PDO $pdo, array $job): void {
  $p = json_decode($job['payload_json'], true);
  $tid = (int)($p['transfer_id'] ?? 0);
  if ($tid<=0) throw new RuntimeException('bad payload');

  // compute totals
  $totW = (int)$pdo->query("SELECT COALESCE(SUM(weight_grams),0) FROM transfer_parcels p
                             JOIN transfer_shipments s ON s.id=p.shipment_id
                            WHERE s.transfer_id=".(int)$tid)->fetchColumn();
  $totB = (int)$pdo->query("SELECT COUNT(*) FROM transfer_parcels p
                             JOIN transfer_shipments s ON s.id=p.shipment_id
                            WHERE s.transfer_id=".(int)$tid)->fetchColumn();

  // set transfer totals and state
  $u = $pdo->prepare("UPDATE transfers SET total_boxes=:b, total_weight_g=:w, state='PACKAGED' WHERE id=:t");
  $u->execute([':b'=>$totB, ':w'=>$totW, ':t'=>$tid]);

  // shipment status becomes 'packed'
  $pdo->prepare("UPDATE transfer_shipments SET status='packed', packed_at=NOW(), packed_by=:u WHERE transfer_id=:t")
      ->execute([':u'=>(int)($p['requested_by']??0), ':t'=>$tid]);

  cis_log('INFO','transfers','pack.finalized', ['transfer_id'=>$tid,'boxes'=>$totB,'weight_g'=>$totW]);
}

$pdo = db_rw();
while (true) {
  $job = reserve_job($pdo);
  if (!$job) { sleep(2); continue; }
  try {
    switch ($job['job_type']) {
      case 'labels.create':          run_labels_create($pdo, $job); break;
      case 'transfer.finalize_pack': run_finalize_pack($pdo, $job); break;
      default: throw new RuntimeException('unknown job '.$job['job_type']);
    }
    finish_job($pdo, (int)$job['id'], 'done');
  } catch (Throwable $e) {
    finish_job($pdo, (int)$job['id'], 'failed', mb_substr($e->getMessage(),0,400));
    cis_log('ERROR','queue',$job['job_type'].' failed', ['job_id'=>$job['id'],'err'=>$e->getMessage()]);
  }
}
