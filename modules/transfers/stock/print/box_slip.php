<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/modules/transfers/stock/lib/AccessPolicy.php';

use Core\DB;
use PDO;
use Modules\Transfers\Stock\Lib\AccessPolicy;

if (empty($_SESSION['userID'])) { http_response_code(302); header('Location: /login.php'); exit; }

$tid = (int)($_GET['transfer'] ?? 0);
$shipmentParam = $_GET['shipment'] ?? 'latest';
if ($tid <= 0) { http_response_code(400); echo 'Missing transfer'; exit; }

if (!AccessPolicy::canAccessTransfer((int)$_SESSION['userID'], $tid)) {
  http_response_code(403); echo 'Forbidden'; exit;
}

$db = DB::instance();
$tx = $db->prepare("SELECT id, outlet_from, outlet_to FROM transfers WHERE id=:id");
$tx->execute(['id'=>$tid]);
$tr = $tx->fetch(PDO::FETCH_ASSOC);
if (!$tr) { http_response_code(404); echo 'Transfer not found'; exit; }

$shipmentId = 0;
if ($shipmentParam === 'latest') {
  $s = $db->prepare("SELECT id FROM transfer_shipments WHERE transfer_id=:tid ORDER BY id DESC LIMIT 1");
  $s->execute(['tid'=>$tid]);
  $shipmentId = (int)($s->fetchColumn() ?: 0);
} else {
  $shipmentId = (int)$shipmentParam;
}
if ($shipmentId <= 0) { http_response_code(404); echo 'Shipment not found'; exit; }

$p = $db->prepare("SELECT id, box_number, tracking_number, courier FROM transfer_parcels WHERE shipment_id=:sid ORDER BY box_number ASC");
$p->execute(['sid'=>$shipmentId]);
$parcels = $p->fetchAll(PDO::FETCH_ASSOC);
if (!$parcels) { http_response_code(404); echo 'No parcels'; exit; }

function outlet_name(string $vendUuid): string {
  $db = DB::instance();
  $st = $db->prepare("SELECT name FROM vend_outlets WHERE id=:id");
  $st->execute(['id'=>$vendUuid]);
  $n = $st->fetchColumn();
  return $n ?: 'UNKNOWN';
}

$fromName = outlet_name((string)$tr['outlet_from']);
$toName   = outlet_name((string)$tr['outlet_to']);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Box Slips</title>
<style>
  @page { size: 80mm auto; margin: 0; }
  body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif; }
  .slip { width: 76mm; padding: 4mm; border-bottom: 1px dashed #000; page-break-inside: avoid; }
  .title { font-weight: 800; font-size: 16px; letter-spacing: .3px; }
  .line  { font-size: 14px; margin-top: 2mm; }
  .small { font-size: 12px; color:#111; }
  .muted { color:#333; }
  .big   { font-size: 18px; font-weight: 800; }
</style>
</head>
<body>
<?php
$total = count($parcels);
foreach ($parcels as $pc):
  $boxNo = (int)$pc['box_number'];
  $trk   = trim((string)($pc['tracking_number'] ?? ''));
  $car   = strtoupper((string)($pc['courier'] ?? ''));
?>
  <div class="slip">
    <div class="title">TRANSFER #<?= htmlspecialchars((string)$tid) ?></div>
    <div class="line"><span class="muted">FROM:&nbsp;</span><span class="big"><?= htmlspecialchars($fromName) ?></span></div>
    <div class="line"><span class="muted">TO:&nbsp;&nbsp;&nbsp;&nbsp;</span><span class="big"><?= htmlspecialchars($toName) ?></span></div>
    <div class="line"><span class="muted">BOX:&nbsp;</span><span class="big"><?= $boxNo ?> of <?= $total ?></span></div>
    <?php if ($trk): ?>
      <div class="line"><span class="muted">TRACKING:&nbsp;</span><span class="small"><?= htmlspecialchars($trk) ?></span></div>
    <?php else: ?>
      <div class="line small muted">No tracking (<?= $car ?: 'INTERNAL' ?>)</div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<script>window.print();</script>
</body>
</html>
