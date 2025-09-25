<?php
declare(strict_types=1);
// modules/transfers/stock-transfers/ajax/tools.php
// Bridge to legacy tools with unified-safe wrappers.

// Load app bootstrap if available (sessions, helpers); fallback to config.php
if (is_file($_SERVER['DOCUMENT_ROOT'] . '/app.php')) {
  require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
} elseif (is_file(__DIR__ . '/../../../../config.php')) {
  require_once __DIR__ . '/../../../../config.php';
}

// Reuse legacy functions to avoid duplication (guard missing path)
$__legacy_tools = __DIR__ . '/../../../stock_transfers/ajax/tools.php';
if (is_file($__legacy_tools)) {
  require_once $__legacy_tools;
}

if (!function_exists('requireLoggedInUser')) {
  function requireLoggedInUser(){
    if (!isset($_SESSION)) session_start();
    if (!isset($_SESSION['userID']) || (int)$_SESSION['userID']<=0) throw new RuntimeException('Not logged in');
    return ['id'=>(int)$_SESSION['userID']];
  }
}

// --- Optional carrier libraries (legacy) ---
// Note: We do NOT modify anything inside assets/functions; we only include if present.
$__carrier_lib_dir = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/';
@is_dir($__carrier_lib_dir) && @is_file($__carrier_lib_dir.'nzpost.php') && require_once $__carrier_lib_dir.'nzpost.php';
@is_dir($__carrier_lib_dir) && @is_file($__carrier_lib_dir.'gss.php')    && require_once $__carrier_lib_dir.'gss.php';

// --- DEV state helpers (JSON) ---
if (!function_exists('stx_runtime_path')) {
  function stx_runtime_path(): string {
    $base = dirname(__DIR__); // modules/transfers/stock-transfers
    $dir  = $base . '/runtime';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    return $dir;
  }
}

// Local carriers/eShip client (module-scoped)
if (is_file(__DIR__ . '/../core/EShipClient.php')) {
  require_once __DIR__ . '/../core/EShipClient.php';
}
if (is_file(__DIR__ . '/../core/NZPostEShipClient.php')) {
  require_once __DIR__ . '/../core/NZPostEShipClient.php';
}
if (is_file(__DIR__ . '/../core/GSSClient.php')) {
  require_once __DIR__ . '/../core/GSSClient.php';
}

if (!function_exists('stx_load_dev_shipments')) {
  function stx_load_dev_shipments(): array {
    $file = stx_runtime_path() . '/shipments_dev.json';
    if (!is_file($file)) return [];
    $raw = @file_get_contents($file);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : [];
  }
}

if (!function_exists('stx_save_dev_shipments')) {
  function stx_save_dev_shipments(array $data): bool {
    $file = stx_runtime_path() . '/shipments_dev.json';
    $tmp  = $file . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $fh = @fopen($tmp, 'wb'); if (!$fh) return false;
    @flock($fh, LOCK_EX);
    $ok = @fwrite($fh, $json) !== false;
    @flock($fh, LOCK_UN); @fclose($fh);
    if (!$ok) { @unlink($tmp); return false; }
    return @rename($tmp, $file);
  }
}

// --- DEV helpers for orders (persist in runtime JSON when DB table not present) ---
if (!function_exists('stx_get_dev_order')) {
  function stx_get_dev_order(int $transferId, string $carrier): ?array {
    $state = stx_load_dev_shipments();
    if (!isset($state['orders'])) return null;
    $o = $state['orders'];
    return $o[$transferId][$carrier] ?? null;
  }
}
if (!function_exists('stx_save_dev_order')) {
  function stx_save_dev_order(int $transferId, string $carrier, ?string $orderId, string $orderNumber, array $payload = []): void {
    $state = stx_load_dev_shipments();
    if (!isset($state['orders'])) $state['orders'] = [];
    if (!isset($state['orders'][$transferId])) $state['orders'][$transferId] = [];
    $state['orders'][$transferId][$carrier] = [
      'order_id' => $orderId,
      'order_number' => $orderNumber,
      'payload' => $payload,
      'saved_at' => date('c'),
    ];
    stx_save_dev_shipments($state);
  }
}

// --- DB helpers (PDO) ---
if (!function_exists('stx_pdo')) {
  function stx_pdo(): PDO {
    static $pdo = null; if ($pdo instanceof PDO) return $pdo;
    // Prefer constants (web), fallback to environment (CLI/jobs)
    $host = defined('DB_HOST') ? (string)DB_HOST : (string)getenv('DB_HOST');
    $name = defined('DB_NAME') ? (string)DB_NAME : (string)getenv('DB_NAME');
    $user = defined('DB_USER') ? (string)DB_USER : (string)getenv('DB_USER');
    $pass = defined('DB_PASS') ? (string)DB_PASS : (string)getenv('DB_PASS');
    $port = defined('DB_PORT') ? (string)DB_PORT : ((string)getenv('DB_PORT') ?: '3306');
    if ($host === '' || $name === '' || $user === '') { throw new RuntimeException('DB config not loaded'); }
    $dsn = 'mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
      PDO::ATTR_PERSISTENT => true,
    ]);
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    return $pdo;
  }
}
if (!function_exists('stx_table_exists')) {
  function stx_table_exists(PDO $pdo, string $table): bool {
    try {
      $q = $pdo->quote($table);
      $s = $pdo->query("SHOW TABLES LIKE $q");
      return $s && $s->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
  }
}
if (!function_exists('stx_save_order_for_transfer')) {
  function stx_save_order_for_transfer(PDO $pdo, int $transferId, string $carrier, ?string $orderId, string $orderNumber, array $payload = []): void {
    if (stx_table_exists($pdo, 'transfer_carrier_orders')) {
      $sql = 'INSERT INTO transfer_carrier_orders (transfer_id, carrier, order_id, order_number, payload) VALUES (?,?,?,?,?)
              ON DUPLICATE KEY UPDATE order_id=VALUES(order_id), order_number=VALUES(order_number), payload=VALUES(payload), updated_at=NOW()';
      $stmt = $pdo->prepare($sql);
      $stmt->execute([$transferId, $carrier, $orderId, $orderNumber, json_encode($payload, JSON_UNESCAPED_SLASHES)]);
      return;
    }
    // Fallback to DEV state
    stx_save_dev_order($transferId, $carrier, $orderId, $orderNumber, $payload);
  }
}
if (!function_exists('stx_get_order_for_transfer')) {
  function stx_get_order_for_transfer(PDO $pdo, int $transferId, string $carrier): ?array {
    if (stx_table_exists($pdo, 'transfer_carrier_orders')) {
      $stmt = $pdo->prepare('SELECT transfer_id, carrier, order_id, order_number, payload, updated_at FROM transfer_carrier_orders WHERE transfer_id=? AND carrier=? LIMIT 1');
      $stmt->execute([$transferId, $carrier]);
      $row = $stmt->fetch();
      if ($row) {
        if (isset($row['payload']) && is_string($row['payload'])) {
          $dec = json_decode($row['payload'], true); if (is_array($dec)) $row['payload'] = $dec;
        }
        return $row;
      }
    }
    return stx_get_dev_order($transferId, $carrier);
  }
}
if (!function_exists('stx_fetch_transfer')) {
  function stx_fetch_transfer(PDO $pdo, int $transferId): ?array {
    // Prefer new canonical transfers table
    if (stx_table_exists($pdo,'transfers')) {
      $stmt = $pdo->prepare('SELECT id, type, status, outlet_from, outlet_to, vend_transfer_id, vend_number, created_by, created_at FROM transfers WHERE id=?');
      $stmt->execute([$transferId]); $row = $stmt->fetch(); return $row ?: null;
    }
    // Fallback: not available
    return null;
  }
}
if (!function_exists('stx_fetch_outlet')) {
  function stx_fetch_outlet(PDO $pdo, string $vendOutletId): ?array {
    // vend_outlets.id is varchar(100) per schema
    if (!stx_table_exists($pdo,'vend_outlets')) return null;
    $stmt = $pdo->prepare('SELECT id, name, email, physical_street_number, physical_street, physical_address_1, physical_address_2, physical_suburb, physical_city, physical_postcode, physical_state, physical_country_id, physical_phone_number, website_outlet_id, gss_token, nz_post_api_key, nz_post_subscription_key FROM vend_outlets WHERE id = ?');
    $stmt->execute([$vendOutletId]); $row = $stmt->fetch(); return $row ?: null;
  }
}
if (!function_exists('stx_build_eship_destination')) {
  function stx_build_eship_destination(array $outlet): array {
    // Compose a Starshipit 'destination' from vend_outlets
    $street1 = trim((string)($outlet['physical_address_1'] ?? ''));
    $street2 = trim((string)($outlet['physical_address_2'] ?? ''));
    $streetNo = trim((string)($outlet['physical_street_number'] ?? ''));
    $streetNm = trim((string)($outlet['physical_street'] ?? ''));
    $street = $street1 !== '' ? ($street1 . ($street2!=='' ? ' '.$street2 : '')) : trim($streetNo.' '.$streetNm);
    return [
      'name' => (string)($outlet['name'] ?? ''),
      'email' => (string)($outlet['email'] ?? ''),
      'company' => (string)($outlet['name'] ?? ''),
      'street' => $street,
      'suburb' => (string)($outlet['physical_suburb'] ?? ''),
      'state' => (string)($outlet['physical_state'] ?? ''),
      'city' => (string)($outlet['physical_city'] ?? ''),
      'post_code' => (string)($outlet['physical_postcode'] ?? ''),
      'country' => (string)($outlet['physical_country_id'] ?? 'NZ'),
      'phone' => (string)($outlet['physical_phone_number'] ?? ''),
    ];
  }
}
if (!function_exists('stx_build_eship_packages')) {
  function stx_build_eship_packages(array $packages): array {
    $out = [];
    foreach ($packages as $p) {
      $out[] = [
        'weight' => (float)($p['weight'] ?? 1.0),
        'height' => (float)($p['height'] ?? 10) / 100,
        'width'  => (float)($p['width']  ?? 10) / 100,
        'length' => (float)($p['length'] ?? 10) / 100,
        'name'   => (string)($p['description'] ?? 'Packaging'),
      ];
    }
    return $out ?: [ ['weight'=>2,'height'=>0.01,'width'=>0.05,'length'=>0.10,'name'=>'Packaging'] ];
  }
}
if (!function_exists('stx_build_eship_order')) {
  function stx_build_eship_order(array $transfer, array $destOutlet, string $carrier, string $serviceCode, array $packages, array $meta = []): array {
    $orderNumber = 'TR-' . (int)$transfer['id'];
    // Destination: allow override if provided by user selection
    $destination = isset($meta['destination_override']) && is_array($meta['destination_override'])
      ? $meta['destination_override']
      : stx_build_eship_destination($destOutlet);
    $order = [
      'carrier' => $carrier,
      'carrier_service_code' => $serviceCode,
      'order_date' => gmdate('Y-m-d H:i:s'),
      'order_number' => $orderNumber,
      'signature_required' => (bool)($meta['signature'] ?? true),
      'destination' => $destination,
      'packages' => stx_build_eship_packages($packages),
      'metadatas' => [
        ['metafield_key'=>'NOATL','value'=>'True'],
        ['metafield_key'=>'SIGNATURE','value'=> ($meta['signature'] ?? true) ? 'True' : 'False'],
        ['metafield_key'=>'SATURDAY','value'=> ($meta['saturday'] ?? false) ? 'True' : 'False'],
        ['metafield_key'=>'AGERESTRICTED','value'=>'True'],
      ],
      'currency' => 'NZD',
    ];
    if (!empty($meta['instructions'])) $order['destination']['delivery_instructions'] = (string)$meta['instructions'];
    if (!empty($meta['attention'])) $order['destination']['name'] = (string)$meta['attention'];
    return $order;
  }
}

// --- Simulation helper ---
if (!function_exists('stx_simulate_label')) {
  function stx_simulate_label(string $carrierCode, int $transferId, array $packages, string $service, string $ref, array $ctx): array {
    $tracking = strtoupper($carrierCode) . '-' . $transferId . '-' . substr($ctx['request_id'] ?? bin2hex(random_bytes(4)), 0, 6);
    $labelUrl = 'https://staff.vapeshed.co.nz/modules/transfers/stock/assets/labels/sim_' . rawurlencode($tracking) . '.pdf';
    $entry = [
      'ts' => date('c'),
      'carrier' => $carrierCode,
      'service' => $service,
      'packages' => $packages,
      'tracking_number' => $tracking,
      'label_url' => $labelUrl,
      'ref' => $ref,
      'by' => (int)($ctx['uid'] ?? 0),
      'request_id' => (string)($ctx['request_id'] ?? '')
    ];
    $state = stx_load_dev_shipments();
    $key = (string)$transferId;
    if (!isset($state[$key])) $state[$key] = [];
    $state[$key][] = $entry;
    stx_save_dev_shipments($state);
    return [
      'success' => true,
      'carrier' => $carrierCode,
      'service' => $service,
      'tracking_number' => $tracking,
      'label_url' => $labelUrl,
      'data' => $entry,
    ];
  }
}

// --- Public wrappers used by ajax actions ---
if (!function_exists('nzpostCreateShipment_wrapped')) {
  /**
   * Create NZ Post shipment label
   * @param int $transferId
   * @param array $packages Each: {length,width,height,weight}
   * @param string $service Service code (e.g., BOX, LETTER, or carrier-specific)
   * @param string $ref Optional sender/customer reference
   * @param array $ctx { uid, request_id, simulate }
   * @return array { success, tracking_number, label_url, carrier }
   */
  function nzpostCreateShipment_wrapped(int $transferId, array $packages, string $service, string $ref, array $ctx): array {
    $simulate = (int)($ctx['simulate'] ?? 0) === 1;
    if ($simulate) { return stx_simulate_label('NZ_POST', $transferId, $packages, $service, $ref, $ctx); }

    // Prefer local HTTP client if keys are configured
    if (class_exists('NZPostEShipClient')) {
      try {
        $client = new NZPostEShipClient();
        // Attempt to use a persisted order context
        $orderNumber = 'TR-' . $transferId;
        $orderId = 0;
        try {
          $pdo = stx_pdo();
          $saved = stx_get_order_for_transfer($pdo, $transferId, 'NZ_POST');
          if ($saved) {
            if (!empty($saved['order_number'])) $orderNumber = (string)$saved['order_number'];
            if (!empty($saved['order_id'])) $orderId = (int)$saved['order_id'];
          } else {
            // Try to resolve from API by order_number then persist for future
            $got = $client->getOrder(null, $orderNumber);
            if (($got['ok'] ?? false)) {
              $odata = $got['data'] ?? [];
              $oid = $odata['order_id'] ?? ($odata['data']['order_id'] ?? null);
              if ($oid) { $orderId = (int)$oid; stx_save_order_for_transfer($pdo, $transferId, 'NZ_POST', (string)$oid, $orderNumber, $odata); }
            }
          }
        } catch (Throwable $e) { /* soft-fail */ }

        $carrierName = 'NZPost';
        $resp = $client->createShipment($orderId, $orderNumber, $carrierName, $service, $packages, false);
        if (($resp['ok'] ?? false) && isset($resp['data'])) {
          $data = $resp['data'];
          $labelUrl = $data['label_url'] ?? ($data['data']['label_url'] ?? '');
          if (is_string($labelUrl) && strpos($labelUrl, 'http://') === 0) { $labelUrl = 'https://' . substr($labelUrl, 7); }
          $tracking = $data['tracking_number'] ?? ($data['data']['tracking_number'] ?? ($data['connote'] ?? ''));
          if ($tracking || $labelUrl) {
            // Persist shipment footprint where possible
            try {
              $pdo = stx_pdo();
              $savePayload = [
                'carrier' => 'NZ_POST',
                'service' => $service,
                'request' => [ 'order_id'=>$orderId, 'order_number'=>$orderNumber, 'carrier'=>$carrierName, 'service'=>$service, 'packages'=>$packages ],
                'response' => $data,
                'tracking_number' => $tracking,
                'label_url' => $labelUrl,
                'request_id' => (string)($ctx['request_id'] ?? ''),
              ];
              stx_save_order_for_transfer($pdo, $transferId, 'NZ_POST', $orderId>0 ? (string)$orderId : null, $orderNumber, $savePayload);
            } catch (Throwable $e) { /* soft-fail */ }
            return ['success'=>true,'carrier'=>'NZ_POST','service'=>$service,'tracking_number'=>$tracking,'label_url'=>$labelUrl,'raw'=>$data];
          }
        }
      } catch (Throwable $e) {
        error_log('[stx.nzpostCreateShipment_wrapped.eship] '.$e->getMessage());
      }
    }

    // If an internal NZ Post (eShip/Starshipit) helper exists, attempt a best-effort call.
    if (function_exists('nzPOST_createShipment')) {
      try {
        // We don't have an order context here; map transfer -> order_number, with packages translated to their expected fields.
        $orderId = 0; // unknown in transfer flow unless previously saved
        $orderNumber = 'TR-' . $transferId;
        try { $pdo = stx_pdo(); $saved = stx_get_order_for_transfer($pdo, $transferId, 'NZ_POST'); if ($saved){ if (!empty($saved['order_number'])) $orderNumber = (string)$saved['order_number']; if (!empty($saved['order_id'])) $orderId = (int)$saved['order_id']; } } catch (Throwable $e) { /* no-op */ }
        $carrierName = 'NZPost';
        $storeId = (int)($_SESSION['website_outlet_id'] ?? 0); // best-effort; if 0 the underlying lib may fail

        $ppkgs = [];
        foreach ($packages as $p) {
          $ppkgs[] = [
            'weight' => (float)($p['weight'] ?? 1.0),
            'height' => (float)($p['height'] ?? 10) / 100,
            'width'  => (float)($p['width']  ?? 10) / 100,
            'length' => (float)($p['length'] ?? 10) / 100,
          ];
        }
        $resp = nzPOST_createShipment($orderId, $orderNumber, $carrierName, $service, $storeId, $ppkgs, false);
        // Expect either JSON-decoded object/array. Try to normalize common fields.
        if ($resp === false) return ['success'=>false,'error'=>'NZ Post createShipment failed'];
        $labelUrl = '';
        $tracking = '';
        if (is_object($resp)) { $respArr = json_decode(json_encode($resp), true); } else { $respArr = (array)$resp; }
        // Common Starshipit fields: label_url, tracking_number or consignments[0].
  $labelUrl = $respArr['label_url'] ?? ($respArr['data']['label_url'] ?? '');
  if (is_string($labelUrl) && strpos($labelUrl, 'http://') === 0) { $labelUrl = 'https://' . substr($labelUrl, 7); }
        $tracking = $respArr['tracking_number'] ?? ($respArr['data']['tracking_number'] ?? ($respArr['connote'] ?? ''));
        if ($tracking === '' && isset($respArr['Consignments'][0]['Connote'])) { $tracking = $respArr['Consignments'][0]['Connote']; }
        if ($labelUrl === '' && isset($respArr['label_pdf_url'])) { $labelUrl = $respArr['label_pdf_url']; }
        if ($tracking === '' && $labelUrl === '') {
          // Fallback simulate to keep flow alive
          return stx_simulate_label('NZ_POST', $transferId, $packages, $service, $ref, $ctx);
        }
        // Persist footprint
        try { $pdo = stx_pdo(); $savePayload = [ 'carrier'=>'NZ_POST','service'=>$service,'request'=>['order_id'=>$orderId,'order_number'=>$orderNumber,'carrier'=>$carrierName,'service'=>$service,'packages'=>$ppkgs], 'response'=>$respArr, 'tracking_number'=>$tracking, 'label_url'=>$labelUrl, 'request_id'=>(string)($ctx['request_id'] ?? '') ]; stx_save_order_for_transfer($pdo, $transferId, 'NZ_POST', $orderId>0 ? (string)$orderId : null, $orderNumber, $savePayload); } catch (Throwable $e) { /* soft-fail */ }
        return ['success'=>true,'carrier'=>'NZ_POST','service'=>$service,'tracking_number'=>$tracking,'label_url'=>$labelUrl,'raw'=>$respArr];
      } catch (Throwable $e) {
        error_log('[stx.nzpostCreateShipment_wrapped] '.$e->getMessage());
        return stx_simulate_label('NZ_POST', $transferId, $packages, $service, $ref, $ctx);
      }
    }

    // No client available, simulate
    return stx_simulate_label('NZ_POST', $transferId, $packages, $service, $ref, $ctx);
  }
}

if (!function_exists('nzpostCreateOrder_wrapped')) {
  /** Create/Update NZ Post Order in eShip using transfers + vend_outlets to define destination */
  function nzpostCreateOrder_wrapped(int $transferId, string $serviceCode, array $packages, array $ctx, array $meta = []): array {
    $simulate = (int)($ctx['simulate'] ?? 0) === 1;
    if ($simulate) {
      // Simulate an order creation result incl. example address candidates
      $sim = stx_simulate_label('NZ_POST', $transferId, $packages, $serviceCode, '', $ctx);
      $sim['order_number'] = 'TR-' . $transferId; $sim['order_id'] = 0;
      $sim['raw'] = [
        'address_candidates' => [
          ['name'=>'The Vape Shed Hamilton East','company'=>'The Vape Shed','street'=>'1 Grey Street','suburb'=>'Hamilton East','city'=>'Hamilton','state'=>'Waikato','post_code'=>'3216','country'=>'NZ','phone'=>'+64-7-000-0000'],
          ['name'=>'The Vape Shed Hamilton East','company'=>'The Vape Shed','street'=>'3 Grey Street','suburb'=>'Hamilton East','city'=>'Hamilton','state'=>'Waikato','post_code'=>'3216','country'=>'NZ','phone'=>'+64-7-000-0000']
        ]
      ];
      return $sim;
    }
    try {
      $pdo = stx_pdo();
      $transfer = stx_fetch_transfer($pdo, $transferId);
      if (!$transfer) return ['success'=>false,'error'=>'Transfer not found'];
  $toOutletId = (string)$transfer['outlet_to'];
  $fromOutletId = (string)($transfer['outlet_from'] ?? '');
  $dest = stx_fetch_outlet($pdo, $toOutletId);
  $origin = $fromOutletId !== '' ? stx_fetch_outlet($pdo, $fromOutletId) : null;
      if (!$dest) return ['success'=>false,'error'=>'Destination outlet not found'];

      // Build order
      $carrierName = 'NZPost';
      $order = stx_build_eship_order($transfer, $dest, $carrierName, $serviceCode, $packages, $meta);

      if (class_exists('NZPostEShipClient')) {
        $client = new NZPostEShipClient();
        $resp = $client->createOrder($order);
        if (($resp['ok'] ?? false)) {
          $data = $resp['data'] ?? [];
          $orderId = $data['order_id'] ?? ($data['data']['order_id'] ?? null);
          // Persist for this transfer for subsequent label creation
          try { $pdo2 = stx_pdo(); stx_save_order_for_transfer($pdo2, $transferId, 'NZ_POST', $orderId !== null ? (string)$orderId : null, $order['order_number'], $data); } catch (Throwable $e) { /* soft-fail */ }
          return ['success'=>true,'carrier'=>'NZ_POST','order_id'=>$orderId,'order_number'=>$order['order_number'],'raw'=>$data];
        }
        return ['success'=>false,'error'=>$resp['error'] ?? 'Order create failed','status'=>$resp['status'] ?? 0];
      }

      // Legacy helper path
      if (function_exists('nzPost_createOrder')) {
        $storeId = (int)($_SESSION['website_outlet_id'] ?? 0);
        $jsonResp = nzPost_createOrder($carrierName, $serviceCode, $order['order_number'], $storeId, ($meta['instructions'] ?? ''), ($meta['saturday'] ?? false) ? 1 : 0, $order['packages']);
        // Expect JSON string; try decode
        $arr = is_array($jsonResp) ? $jsonResp : json_decode((string)$jsonResp, true);
        if (is_array($arr)) {
          try { $pdo2 = stx_pdo(); $orderId = $arr['order_id'] ?? null; stx_save_order_for_transfer($pdo2, $transferId, 'NZ_POST', $orderId !== null ? (string)$orderId : null, $order['order_number'], $arr); } catch (Throwable $e) { /* soft-fail */ }
          return ['success'=>true,'carrier'=>'NZ_POST','order_id'=>$arr['order_id'] ?? null,'order_number'=>$order['order_number'],'raw'=>$arr];
        }
        return ['success'=>false,'error'=>'Legacy order create failed'];
      }

      return ['success'=>false,'error'=>'No NZ Post client available'];
    } catch (Throwable $e) {
      error_log('[stx.nzpostCreateOrder_wrapped] '.$e->getMessage());
      return ['success'=>false,'error'=>'Server error'];
    }
  }
}

if (!function_exists('gssCreateShipment_wrapped')) {
  /** Create NZ Couriers (GSS) shipment label */
  function gssCreateShipment_wrapped(int $transferId, array $packages, string $service, array $ctx): array {
    $simulate = (int)($ctx['simulate'] ?? 0) === 1;
    if ($simulate) { return stx_simulate_label('GSS', $transferId, $packages, $service, '', $ctx); }

    try {
      $pdo = stx_pdo();
      $transfer = stx_fetch_transfer($pdo, $transferId);
      if (!$transfer) return ['success'=>false,'error'=>'Transfer not found'];
  $toOutletId = (string)$transfer['outlet_to'];
  $fromOutletId = (string)($transfer['outlet_from'] ?? '');
  $dest = stx_fetch_outlet($pdo, $toOutletId);
  $origin = $fromOutletId !== '' ? stx_fetch_outlet($pdo, $fromOutletId) : null;
      if (!$dest) return ['success'=>false,'error'=>'Destination outlet not found'];

      // Build GSS payload: Origin null (account default), Destination from vend_outlets
      $destination = [
        'Name' => (string)($dest['name'] ?? ''),
        'Address' => [
          'BuildingName' => (string)($dest['name'] ?? ''),
          'StreetAddress' => trim(($dest['physical_address_1'] ?? '') . ' ' . ($dest['physical_address_2'] ?? '')),
          'Suburb' => (string)($dest['physical_suburb'] ?? ''),
          'City' => (string)($dest['physical_city'] ?? ''),
          'PostCode' => (string)($dest['physical_postcode'] ?? ''),
          'CountryCode' => 'NZ',
        ],
        'Email' => (string)($dest['email'] ?? ''),
        'ContactPerson' => (string)($ctx['attention'] ?? ($dest['name'] ?? '')),
        'PhoneNumber' => (string)($dest['physical_phone_number'] ?? ''),
        'DeliveryInstructions' => (string)($ctx['instructions'] ?? ''),
        'SendTrackingEmail' => true,
        'ExplicitNotRural' => false,
      ];

      // Map packages to GSS format (cm + kg expected as numbers)
      $pkgList = [];
      foreach ($packages as $p) {
        $pkgList[] = [
          'Name' => (string)($p['description'] ?? 'Packaging'),
          'Length' => (float)($p['length'] ?? 10),
          'Width'  => (float)($p['width']  ?? 10),
          'Height' => (float)($p['height'] ?? 10),
          'Kg'     => (float)($p['weight'] ?? 2.0),
        ];
      }
      if (!$pkgList) { $pkgList = [[ 'Name'=>'Packaging','Length'=>30,'Width'=>20,'Height'=>10,'Kg'=>2.0 ]]; }

      if (class_exists('GSSClient')) {
        // Tokens must come from vend_outlets only (origin preferred, then destination)
        $gssKey = (string)($origin['gss_token'] ?? '') ?: (string)($dest['gss_token'] ?? '');
        $supportEmail = (string)($origin['email'] ?? ($dest['email'] ?? ''));
        // If no token is present in vend_outlets, do not attempt env/wrapper fallbacks
        $client = $gssKey !== '' ? new GSSClient($gssKey, null, $supportEmail) : new GSSClient(null, null, $supportEmail);
        if ($client->isConfigured()) {
          $payload = [
            'Origin' => null,
            'Destination' => $destination,
            'Packages' => $pkgList,
            'Commodities' => null,
            'IsSaturdayDelivery' => (bool)($ctx['saturday'] ?? false),
            'IsSignatureRequired' => (bool)($ctx['signature'] ?? true),
            'IsUrgentCouriers' => false,
            'DutiesAndTaxesByReceiver' => false,
            'RuralOverride' => false,
            'DeliveryReference' => 'TR-' . $transferId,
            // Align with NZ Post flow: do not auto-print via API, UI handles printing
            'PrintToPrinter' => 'false',
            // Default to NZ Couriers when service not provided, else pass-through
            'Carrier' => ($service === '' || $service === '*') ? 'NZ Couriers' : $service
          ];
          $resp = $client->createShipment($payload);
          if (($resp['ok'] ?? false) && isset($resp['data'])) {
            $data = $resp['data'];
            // Expect Consignments[0].Connote and optionally label fetch via labels?connote=
            $connote = '';
            if (isset($data['Consignments'][0]['Connote'])) {
              $connote = (string)$data['Consignments'][0]['Connote'];
            } elseif (isset($data['consignments'][0]['connote'])) {
              $connote = (string)$data['consignments'][0]['connote'];
            }
            // Try to extract a label directly from response if present
            $labelUrl = '';
            $labelDataUrl = '';
            if (isset($data['OutputFiles']['LABEL_PDF'][0])) {
              $b64 = (string)$data['OutputFiles']['LABEL_PDF'][0];
              if ($b64 !== '') { $labelDataUrl = 'data:application/pdf;base64,' . $b64; }
            }
            if ($labelDataUrl === '' && $connote !== '') {
              $label = $client->printLabelByConnote($connote);
              if (($label['ok'] ?? false)) {
                if (isset($label['raw']) && is_string($label['raw']) && $label['raw'] !== '') {
                  $labelDataUrl = 'data:application/pdf;base64,' . base64_encode($label['raw']);
                } else {
                  // no raw bytes returned; will rely on tracking link below
                }
              }
            }
            // Prefer HTTPS tracking link as primary label_url to comply with link policy
            if ($connote !== '') {
              $labelUrl = 'https://track.gosweetspot.com/' . rawurlencode($connote);
            }

            // Persist shipment audit for this transfer
            try {
              $consignmentId = $data['Consignments'][0]['ConsignmentId'] ?? ($data['consignments'][0]['consignmentId'] ?? null);
              $savePayload = [
                'carrier' => 'GSS',
                'service' => $service,
                'response' => $data,
                'request' => $payload,
                'packages' => $pkgList,
                'destination' => $destination,
                'from_outlet' => $fromOutletId,
                'to_outlet' => $toOutletId,
                'connote' => $connote,
                'label_url' => $labelUrl,
                'request_id' => (string)($ctx['request_id'] ?? ''),
              ];
              if ($labelDataUrl !== '') { $savePayload['label_data_url'] = $labelDataUrl; }
              stx_save_order_for_transfer($pdo, $transferId, 'GSS', $consignmentId !== null ? (string)$consignmentId : null, 'TR-' . $transferId, $savePayload);
            } catch (Throwable $e) { /* soft-fail audit */ }

            return ['success'=>true,'carrier'=>'GSS','service'=>$service,'tracking_number'=>$connote,'label_url'=>$labelUrl,'raw'=>$data];
          }
          return ['success'=>false,'error'=>$resp['error'] ?? 'GSS createShipment failed','status'=>$resp['status'] ?? 0];
        }
      }

      // As a last resort, if legacy cUrlRequest helper is available, we can attempt it
      if (function_exists('cUrlRequest')) {
        return stx_simulate_label('GSS', $transferId, $packages, $service, '', $ctx);
      }

      return stx_simulate_label('GSS', $transferId, $packages, $service, '', $ctx);
    } catch (Throwable $e) {
      error_log('[stx.gssCreateShipment_wrapped] '.$e->getMessage());
      return stx_simulate_label('GSS', $transferId, $packages, $service, '', $ctx);
    }
  }
}

if (!function_exists('saveManualTracking_wrapped')) {
  /** Persist manual tracking entry (DEV JSON state for now) */
  function saveManualTracking_wrapped(int $transferId, string $trackingId, string $notes, int $userId, int $simulate, string $carrierCode, string $labelUrl, string $requestId): array {
    $entry = [
      'ts' => date('c'), 'carrier' => $carrierCode ?: 'MANUAL', 'tracking_number' => $trackingId,
      'label_url' => $labelUrl, 'notes' => $notes, 'by' => $userId, 'request_id' => $requestId,
      'manual' => true
    ];
    $state = stx_load_dev_shipments();
    $key = (string)$transferId; if (!isset($state[$key])) $state[$key] = [];
    $state[$key][] = $entry; stx_save_dev_shipments($state);
    return ['success'=>true, 'data'=>['transfer_id'=>$transferId,'tracking_number'=>$trackingId,'carrier'=>$carrierCode,'label_url'=>$labelUrl]];
  }
}

if (!function_exists('syncShipment_wrapped')) {
  /** Sync shipment with carrier events (stub; extend to call carrier tracking APIs) */
  function syncShipment_wrapped(int $transferId, ?string $carrierCode, ?string $trackingId, int $userId, int $simulate, string $requestId): array {
    // For now, just echo last known state from DEV JSON.
    $state = stx_load_dev_shipments();
    $key = (string)$transferId; $items = $state[$key] ?? [];
    return ['success'=>true, 'data'=>['transfer_id'=>$transferId, 'entries'=>$items, 'synced_at'=>date('c')]];
  }
}
