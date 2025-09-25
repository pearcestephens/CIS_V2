<?php
declare(strict_types=1);

// Transfer-aware config: strictly use per-store keys from vend_outlets (outlet_from of transfer)
$transferId = (int)($_POST['transfer_id'] ?? $_GET['transfer_id'] ?? 0);
$hasNz = false; $hasGss = false; $store = null; $defaults = ['max_box_weight_kg'=>15];
if ($transferId > 0) {
  try {
    if (!function_exists('stx_db')) { throw new RuntimeException('DB unavailable'); }
    $pdo = stx_db();
    // Fetch transfer to determine origin store (outlet_from)
    $tr = null;
    try {
      $stmt = $pdo->prepare('SELECT outlet_from, outlet_to FROM transfers WHERE id = ?');
      $stmt->execute([$transferId]);
      $tr = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) { /* ignore */ }
    if (!$tr) {
      try {
        $stmt = $pdo->prepare('SELECT outlet_from, outlet_to FROM stock_transfers WHERE transfer_id = ?');
        $stmt->execute([$transferId]);
        $tr = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
      } catch (Throwable $e) { /* ignore */ }
    }
    if ($tr && !empty($tr['outlet_from'])) {
      // Fetch store row with courier creds
      $stmt2 = $pdo->prepare('SELECT id, name, website_outlet_id, nz_post_api_key, nz_post_subscription_key, gss_token, default_printer_name, default_label_paper FROM vend_outlets WHERE id = ?');
      $stmt2->execute([(string)$tr['outlet_from']]);
      $store = $stmt2->fetch(PDO::FETCH_ASSOC) ?: null;
      if ($store) {
        $hasNz = !empty($store['nz_post_api_key']) || !empty($store['nz_post_subscription_key']);
        $hasGss = !empty($store['gss_token']);
        // Optional defaults from store config
        if (!empty($store['default_label_paper']) && is_numeric($store['default_label_paper'])) {
          $defaults['max_box_weight_kg'] = max(1, (int)$store['default_label_paper']);
        }
      }
    }
  } catch (Throwable $e) {
    error_log('[get_printers_config] transfer-aware lookup failed: ' . $e->getMessage());
  }
}

// No environment or wrapper fallbacks by policy — only vend_outlets contains tokens.

$default = 'none';
if ($hasNz && $hasGss) { $default = 'nzpost'; }
elseif ($hasNz) { $default = 'nzpost'; }
elseif ($hasGss) { $default = 'gss'; }

$resp = [
  'has_nzpost' => (bool)$hasNz,
  'has_gss' => (bool)$hasGss,
  'default' => $default,
  'defaults' => $defaults,
];
if ($store) {
  $resp['store'] = [
    'id' => (string)($store['id'] ?? ''),
    'website_outlet_id' => (int)($store['website_outlet_id'] ?? 0),
    'name' => (string)($store['name'] ?? ''),
  ];
}

jresp(true, $resp);
