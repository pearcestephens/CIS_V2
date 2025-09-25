<?php
/**
 * 80mm box slip printable receipt
 * URL: /modules/transfers/stock/print/box_slip.php?transfer=123&box=1&from=Outlet+A&to=Outlet+B
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$tid = (int)($_GET['transfer'] ?? 0);
$box = (int)($_GET['box'] ?? 1);
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$car = strtolower((string)($_GET['car'] ?? ''));
if ($tid <= 0) { http_response_code(400); echo 'Missing transfer'; exit; }
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Transfer #<?php echo $tid; ?> · Box <?php echo $box; ?></title>
  <style>
    @page { size: 80mm auto; margin: 2mm; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, 'Noto Sans', sans-serif; margin: 0; }
    .wrap { width: 76mm; padding: 2mm; }
  .title { font-size: 22px; font-weight: 700; text-align:center; letter-spacing:0.5px; }
  .accent { height: 3px; background: linear-gradient(90deg,#6C5CE7,#00B894,#00CEC9); margin: 6px 0 2px; border-radius: 2px; }
  .brand { display:inline-block; margin: 2px auto 0; padding: 0 6px; border-radius: 4px; border: 1px solid #999; font-size: 11px; font-weight:700; }
    .big { font-size: 24px; font-weight: 700; text-align:center; }
    .muted { color: #444; font-size: 12px; text-align:center; }
    .row { margin: 6px 0; text-align:center; }
    .label { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
    .value { font-size: 18px; font-weight: 700; }
    .hr { border-top: 1px dashed #000; margin: 8px 0; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; }
    .qr { text-align:center; margin-top: 8px; }
  </style>
</head>
<body onload="window.print()">
  <div class="wrap">
    <div class="title">Stock Transfer</div>
    <div class="accent"></div>
    <div class="big mono">#<?php echo $tid; ?> · BOX <?php echo $box; ?></div>
    <?php 
      $brand = '';
      if ($car==='nzpost') $brand = 'NZ Post'; elseif ($car==='gss') $brand = 'NZ Couriers';
      if ($brand) echo '<div class="brand">'.htmlspecialchars($brand).'</div>';
    ?>
    <div class="hr"></div>
    <div class="row">
      <div class="label">From</div>
      <div class="value"><?php echo htmlspecialchars($from ?: ''); ?></div>
    </div>
    <div class="row">
      <div class="label">To</div>
      <div class="value"><?php echo htmlspecialchars($to ?: ''); ?></div>
    </div>
    <div class="hr"></div>
    <?php
    // DEV mode: try to read contents plan from testing/.state.json latest shipment for this transfer
    $contents = [];
    try {
      $testingDir = realpath(__DIR__ . '/../testing') ?: (__DIR__ . '/../testing');
      $statePath = rtrim($testingDir, '/').'/'.'.state.json';
      if (is_file($statePath)) {
        $json = @file_get_contents($statePath);
        if ($json) {
          $arr = json_decode($json, true);
          if (is_array($arr) && !empty($arr['shipments'])) {
            // pick the latest shipment for this transfer id
            $latest = null;
            foreach ($arr['shipments'] as $sh) { if ((int)($sh['transfer_id'] ?? 0) === $tid) { $latest = $sh; } }
            if ($latest && !empty($latest['contents_plan']) && is_array($latest['contents_plan'])) {
              $boxIdx = (string)max(1, $box);
              $contents = $latest['contents_plan'][$boxIdx] ?? [];
            }
          }
        }
      }
    } catch (Throwable $e) { /* ignore for print fallback */ }
    ?>
    <?php if (!empty($contents)) { ?>
      <div class="row"><div class="label">Contents</div></div>
      <div class="row" style="text-align:left;">
        <div>
          <?php foreach ($contents as $line) { $n = (string)($line['name'] ?? 'Item'); $q = (int)($line['qty'] ?? 0); ?>
            <div class="mono">x<?php echo $q; ?> · <?php echo htmlspecialchars($n); ?></div>
          <?php } ?>
        </div>
      </div>
      <div class="hr"></div>
    <?php } ?>
    <div class="muted">Place this slip on the box. Print on 80mm receipt printer.</div>
    <div class="qr">
      <div class="muted">Scan: /transfers/stock/outgoing.php?transfer=<?php echo $tid; ?></div>
    </div>
  </div>
</body>
</html>
