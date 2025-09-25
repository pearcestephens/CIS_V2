<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/JsonGuard.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/ApiResponder.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/HttpGuard.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/Validator.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/shipping/OutletRepo.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/shipping/ShippingCarrier.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/shipping/GSSAdapter.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/assets/functions/shipping/StarshipitAdapter.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/modules/transfers/stock/lib/AccessPolicy.php';

use Core\DB;
use PDO;
use Modules\Transfers\Stock\Services\ShipmentService;
use Modules\Transfers\Stock\Services\TrackingService;
use Modules\Transfers\Stock\Services\TransferLogger;
use Modules\Transfers\Stock\Lib\AccessPolicy;

HttpGuard::allowMethods(['POST']);
HttpGuard::sameOriginOr([]);
HttpGuard::rateLimit('label_create:'.(int)($_SESSION['userID']??0), 30, 60);
JsonGuard::csrfCheckOptional();
JsonGuard::idempotencyGuard();
HttpGuard::requireJsonContent();

if (empty($_SESSION['userID'])) ApiResponder::json(['success'=>false,'error'=>'Not authenticated'], 401);

$body = JsonGuard::readJson();

// --- Validate ----------------------------------------------------------------
try {
  $transferId = Validator::int($body['transfer_id'] ?? null, 1, PHP_INT_MAX, 'transfer_id');
  $carrierRaw = Validator::str($body['carrier'] ?? 'manual', 0, 32, 'carrier');
  $carrier    = strtolower($carrierRaw);   // 'nz_post' | 'gss' | 'manual'
  $mode       = Validator::str($body['delivery_mode'] ?? 'courier', 0, 32, 'delivery_mode'); // 'courier'|'driver_pickup'|'driver_drop'|'internal'
  $service    = isset($body['service_code']) ? Validator::str($body['service_code'], 0, 64, 'service_code') : '';

  if (!AccessPolicy::canAccessTransfer((int)$_SESSION['userID'], $transferId)) {
    ApiResponder::json(['success'=>false,'error'=>'Forbidden'], 403);
  }

  if (!is_array($body['packages'] ?? null) || count($body['packages']) === 0) {
    throw new InvalidArgumentException('packages required');
  }
  if (count($body['packages']) > 50) throw new InvalidArgumentException('too many packages (max 50)');

  // Normalize packages (allow satchels: dims can be zero if weight>0)
  $packages = [];
  foreach ($body['packages'] as $idx => $p) {
    $L = (float)($p['length_cm'] ?? $p['l_cm'] ?? 0);
    $W = (float)($p['width_cm']  ?? $p['w_cm'] ?? 0);
    $H = (float)($p['height_cm'] ?? $p['h_cm'] ?? 0);
    $KG= (float)($p['weight_kg'] ?? $p['kg'] ?? 0);
    if ($L<0 || $W<0 || $H<0 || $KG<0) throw new InvalidArgumentException("negative dims/weight at row ".($idx+1));
    if ($KG<=0) throw new InvalidArgumentException("weight_kg required (>0) at row ".($idx+1));
    $packages[] = [
      'name'      => (string)($p['name'] ?? 'Box'),
      'length_cm' => $L, 'width_cm'=>$W, 'height_cm'=>$H, 'weight_kg'=>$KG
    ];
  }

} catch (\Throwable $e) {
  ApiResponder::json(['success'=>false,'error'=>$e->getMessage()], 400);
}

// --- Load transfer + outlets -------------------------------------------------
$db = DB::instance();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$logger = new TransferLogger();

$tx = $db->prepare("SELECT id, outlet_from, outlet_to FROM transfers WHERE id=:id");
$tx->execute(['id'=>$transferId]);
$tr = $tx->fetch(PDO::FETCH_ASSOC);
if (!$tr) ApiResponder::json(['success'=>false,'error'=>'Transfer not found'], 404);

$from = outlet_by_vend_uuid((string)$tr['outlet_from']); // origin (credentials)
$to   = outlet_by_vend_uuid((string)$tr['outlet_to']);   // destination
if (!$from || !$to) ApiResponder::json(['success'=>false,'error'=>'Outlet lookup failed'], 404);

// Recipient address (destination by default; allow override)
$recipient = is_array($body['recipient'] ?? null) ? $body['recipient'] : outlet_address_block($to);
$options   = is_array($body['options'] ?? null) ? $body['options'] : [];

// Helper to convert cm/kg => mm/g
$toParcelRow = static function(array $p): array {
  return [
    'length_mm'    => $p['length_cm'] ? (int)round($p['length_cm']*10) : null,
    'width_mm'     => $p['width_cm']  ? (int)round($p['width_cm']*10)  : null,
    'height_mm'    => $p['height_cm'] ? (int)round($p['height_cm']*10) : null,
    'weight_grams' =>                 (int)round($p['weight_kg']*1000),
  ];
};

// INTERNAL / MANUAL: only write parcels (no external API) ---------------------
if ($mode !== 'courier' || $carrier === 'manual') {
  $svc = new ShipmentService();
  $result = $svc->createShipmentWithParcelsAndItems(
    transferId:  $transferId,
    deliveryMode: ($mode === 'courier' ? 'courier' : 'internal_drive'),
    carrierName:  strtoupper($carrier),
    itemsSent:    [],
    parcels:      array_map($toParcelRow, $packages),
    userId:       (int)$_SESSION['userID']
  );
  $logger->log('LABEL_CREATED', [
    'transfer_id'=>$transferId,
    'shipment_id'=>$result['shipment_id'] ?? null,
    'event_data'=>['carrier'=>'MANUAL','mode'=>$mode,'parcels'=>count($result['parcel_ids'] ?? [])]
  ]);
  ApiResponder::json(['success'=>true,'carrier'=>'manual','shipment_id'=>$result['shipment_id'] ?? null,'parcel_ids'=>$result['parcel_ids'] ?? []], 200);
}

// COURIER path ---------------------------------------------------------------
// Pick adapter & ensure outlet has credentials
if ($carrier === 'gss') {
  if (empty($from['gss_token'])) ApiResponder::json(['success'=>false,'error'=>'Origin outlet has no GSS token'], 400);
  $adapter = new GSSAdapter();
} elseif ($carrier === 'nz_post') {
  if (empty($from['nz_post_api_key']) || empty($from['nz_post_subscription_key'])) {
    ApiResponder::json(['success'=>false,'error'=>'Origin outlet has no Starshipit keys'], 400);
  }
  $adapter = new StarshipitAdapter(1.0); // cm
} else {
  ApiResponder::json(['success'=>false,'error'=>'Unsupported carrier'], 400);
}

// Try to map onto last PACKED shipment if parcel counts match (0 tracking)
$latestS = $db->prepare("SELECT id FROM transfer_shipments WHERE transfer_id=:tid AND status='packed' ORDER BY id DESC LIMIT 1");
$latestS->execute(['tid'=>$transferId]);
$shipmentId = (int)($latestS->fetchColumn() ?: 0);
$parcelIds  = [];

if ($shipmentId > 0) {
  $rows = $db->prepare("SELECT id, tracking_number FROM transfer_parcels WHERE shipment_id=:sid ORDER BY box_number ASC");
  $rows->execute(['sid'=>$shipmentId]);
  $parcels = $rows->fetchAll(PDO::FETCH_ASSOC);
  if ($parcels) {
    $untracked = array_values(array_filter($parcels, fn($r)=> empty($r['tracking_number'])));
    if (count($untracked) === count($packages)) {
      $parcelIds = array_map(fn($r)=>(int)$r['id'], $untracked);
      // Update known dims/weights before API call (optional)
      $upd = $db->prepare("UPDATE transfer_parcels SET length_mm=:L,width_mm=:W,height_mm=:H, weight_grams=:G, courier=:c, updated_at=NOW() WHERE id=:pid");
      foreach ($parcelIds as $i=>$pid) {
        $pr = $toParcelRow($packages[$i]);
        $upd->execute(['L'=>$pr['length_mm'],'W'=>$pr['width_mm'],'H'=>$pr['height_mm'],'G'=>$pr['weight_grams'],'c'=>strtoupper($carrier),'pid'=>$pid]);
      }
    }
  }
}

// Build adapter ctx (IMPORTANT: include full origin outlet row)
$ctx = [
  'order_id'    => $transferId,
  'outlet'      => $from,                    // <-- credentials source
  'service_code'=> $service,
  'options'     => [
    'signature'    => !empty($options['signature']),
    'saturday'     => !empty($options['saturday']),
    'atl'          => !empty($options['atl']),
    'instructions' => (string)($options['instructions'] ?? ''),
    'printer'      => (string)($options['printer'] ?? ''),
    'delivery_ref' => 'TR-'.$transferId
  ],
  'recipient'   => $recipient,
  'packages'    => $packages
];

// Call carrier API to generate labels / tracking
try {
  $res = $adapter->createShipment($ctx);
} catch (\Throwable $e) {
  ApiResponder::json(['success'=>false,'error'=>'Carrier exception: '.$e->getMessage()], 502);
}
if (!$res || empty($res['ok'])) {
  ApiResponder::json(['success'=>false,'error'=>$res['error'] ?? 'Carrier error','carrier'=>$carrier,'raw'=>$res['raw'] ?? null], 502);
}

// If no parcel pool mapped, create a fresh shipment & parcels now
if (!$parcelIds) {
  $svc = new ShipmentService();
  $result = $svc->createShipmentWithParcelsAndItems(
    transferId:  $transferId,
    deliveryMode:'courier',
    carrierName: strtoupper($carrier),
    itemsSent:   [],
    parcels:     array_map($toParcelRow, $packages),
    userId:      (int)$_SESSION['userID']
  );
  $shipmentId = (int)($result['shipment_id'] ?? 0);
  $parcelIds  = $result['parcel_ids'] ?? [];
}

// Map returned tracking to parcelIds in order
$tracks = $res['tracking'] ?? [];
$trackingSvc = new TrackingService();
$upd = $db->prepare("UPDATE transfer_parcels SET courier=:c, label_url=:label, updated_at=NOW() WHERE id=:pid");

for ($i=0; $i<count($parcelIds); $i++) {
  $pid  = (int)$parcelIds[$i];
  $code = $tracks[$i]['code'] ?? null;
  if ($code) $trackingSvc->setParcelTracking($pid, $code, strtoupper($carrier), $transferId);

  $labelUrl = $tracks[$i]['url'] ?? null; // (Starshipit returns tracking URL; GSS label printed via profile or explicit call)
  $upd->execute(['c'=>strtoupper($carrier),'label'=>$labelUrl, 'pid'=>$pid]);
}

// Optional metric (best-effort)
try {
  $raw  = $res['raw'] ?? null;
  $cost = (float)($raw->TotalCost ?? 0.0); // only when carrier returns it
  $meta = ['carrier'=>$carrier,'service'=>$service,'parcels'=>count($parcelIds)];
  $db->prepare(
    "INSERT INTO transfer_queue_metrics
       (metric_type, queue_name, job_type, value, unit, metadata, outlet_from, outlet_to, worker_id, recorded_at)
     VALUES ('shipping_cost','shipping','label', :val, 'NZD', :meta, :from, :to, :uid, NOW())"
  )->execute([
    'val'=>$cost, 'meta'=>json_encode($meta, JSON_UNESCAPED_SLASHES),
    'from'=>$from['id'] ?? null, 'to'=>$to['id'] ?? null, 'uid'=>(string)($_SESSION['userID'] ?? '')
  ]);
} catch (\Throwable $e) { /* ignore metric failures */ }

// Log
$logger->log('LABEL_CREATED', [
  'transfer_id'=>$transferId,
  'shipment_id'=>$shipmentId,
  'event_data'=>['carrier'=>$carrier,'service'=>$service,'parcels'=>count($parcelIds)]
]);

ApiResponder::json([
  'success'=>true,
  'carrier'=>$carrier,
  'shipment_id'=>$shipmentId,
  'parcel_ids'=>$parcelIds,
  'tracking'=>$tracks
], 200);
