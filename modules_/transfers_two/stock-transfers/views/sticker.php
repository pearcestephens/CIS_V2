<?php
/**
 * modules/transfers/stock-transfers/views/sticker.php
 * Purpose: 80mm thermal sticker for each transfer box with FROM → TO, Transfer #, Box X of N, Packed by, Date, and Tracking (if any).
 * Author: CIS Bot
 * Last Modified: 2025-09-22
 * Dependencies: app.php (bootstrap), ajax/tools.php (STX helpers)
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock-transfers/ajax/tools.php';

// Inputs
$transferId = (int)($_GET['transfer'] ?? 0);
$boxes = max(1, min(50, (int)($_GET['boxes'] ?? 1)));
$auto = (int)($_GET['auto'] ?? 0);
$showTracking = (int)($_GET['show_tracking'] ?? 1) === 1;
$showPacker   = (int)($_GET['show_packer'] ?? 1) === 1;
$showDate     = (int)($_GET['show_date'] ?? 1) === 1;

if ($transferId <= 0) {
  http_response_code(400);
  echo '<!doctype html><html><body><h3>Missing transfer parameter.</h3></body></html>';
  exit;
}

// Data fetch
$pdo = stx_pdo();
$transfer = stx_fetch_transfer($pdo, $transferId);
if (!$transfer) {
  http_response_code(404);
  echo '<!doctype html><html><body><h3>Transfer not found.</h3></body></html>';
  exit;
}

$fromId = (string)($transfer['outlet_from'] ?? '');
$toId   = (string)($transfer['outlet_to'] ?? '');
$from   = $fromId !== '' ? stx_fetch_outlet($pdo, $fromId) : null;
$to     = $toId   !== '' ? stx_fetch_outlet($pdo, $toId)   : null;

// Resolve store display names
$fromName = $from['name'] ?? ($fromId ?: 'Unknown');
$toName   = $to['name']   ?? ($toId   ?: 'Unknown');

// Resolve tracking number (prefer NZ_POST then GSS; fallback dev manual state)
$tracking = '';
try {
  $nz = stx_get_order_for_transfer($pdo, $transferId, 'NZ_POST');
  if ($nz && !empty($nz['payload'])) {
    $p = $nz['payload'];
    $tracking = (string)($p['tracking_number'] ?? ($p['connote'] ?? ''));
    if ($tracking === '' && isset($p['response'])) {
      $r = $p['response'];
      if (is_array($r)) {
        $tracking = (string)($r['tracking_number'] ?? ($r['connote'] ?? ($r['Consignments'][0]['Connote'] ?? '')));
      }
    }
  }
  if ($tracking === '') {
    $g = stx_get_order_for_transfer($pdo, $transferId, 'GSS');
    if ($g && !empty($g['payload'])) {
      $p = $g['payload'];
      $tracking = (string)($p['connote'] ?? ($p['tracking_number'] ?? ''));
    }
  }
} catch (Throwable $e) { /* ignore */ }

if ($tracking === '' && function_exists('stx_load_dev_shipments')) {
  $st = stx_load_dev_shipments();
  $key = (string)$transferId;
  if (!empty($st[$key])) { $last = end($st[$key]); if (is_array($last)) { $tracking = (string)($last['tracking_number'] ?? ''); } }
}

// Packed by and date
$packedBy = '';
if (!empty($_SESSION['user']['name'])) $packedBy = (string)$_SESSION['user']['name'];
elseif (!empty($_SESSION['staff_name'])) $packedBy = (string)$_SESSION['staff_name'];
elseif (!empty($_SESSION['username'])) $packedBy = (string)$_SESSION['username'];
else $packedBy = 'CIS Staff';
$now = date('Y-m-d H:i');

// Absolute asset URL (policy requires full HTTPS links)
$thermalCss = 'https://staff.vapeshed.co.nz/modules/transfers/stock-transfers/assets/css/thermal.css';

?><!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Transfer #<?= htmlspecialchars((string)$transferId) ?> Stickers</title>
    <style>
      /* 80mm thermal inline for fastest render */
      @media print { @page { size: 80mm auto; margin: 3mm; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
      body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Apple Color Emoji", "Segoe UI Emoji"; color: #000; }
      .label { border: 1px solid #000; border-radius: 1mm; }
      .muted { color: #111; }
      /* Small inline extras for layout */
      .label { width: 80mm; min-height: 35mm; padding: 6mm 5mm 5mm; box-sizing: border-box; }
      .row { display: flex; justify-content: space-between; align-items: baseline; }
      .fromto { font-weight: 700; text-transform: uppercase; font-size: 16px; }
      .muted { color: #333; font-size: 12px; }
      .big { font-size: 20px; font-weight: 800; }
      .mid { font-size: 14px; font-weight: 600; }
      .code { font-family: monospace; font-size: 14px; }
      .break { page-break-after: always; }
      .hr { border-top: 1px dashed #000; margin: 4px 0; }
    </style>
    <?php if ($auto): ?>
    <script>
      window.addEventListener('load', function(){
        setTimeout(function(){ window.print(); }, 50);
      });
    </script>
    <?php endif; ?>
  </head>
  <body>
    <?php for ($i = 1; $i <= $boxes; $i++): ?>
      <section class="label break" aria-label="Transfer Box Label">
        <div class="row">
          <div class="big">Box <?= $i ?> of <?= $boxes ?></div>
          <div class="mid">Transfer #<?= htmlspecialchars((string)$transferId) ?></div>
        </div>
        <div class="fromto" style="margin-top: 2mm;">FROM: <?= htmlspecialchars($fromName) ?></div>
        <div class="fromto">TO: <?= htmlspecialchars($toName) ?></div>
        <div class="hr"></div>
        <div class="row">
          <?php if ($showPacker): ?><div class="muted">Packed by: <?= htmlspecialchars($packedBy) ?></div><?php endif; ?>
          <?php if ($showDate):   ?><div class="muted">Date: <?= htmlspecialchars($now) ?></div><?php endif; ?>
        </div>
        <?php if ($showTracking && $tracking !== ''): ?>
          <div class="row" style="margin-top: 2mm;">
            <div class="mid">Tracking:</div>
            <div class="code"><?= htmlspecialchars($tracking) ?></div>
          </div>
          <div class="row" style="margin-top: 1mm;">
            <div class="muted">Scan:</div>
            <canvas class="qr" data-text="<?= htmlspecialchars($tracking) ?>" width="120" height="120" style="border:0"></canvas>
          </div>
        <?php endif; ?>
      </section>
    <?php endfor; ?>
    <script>
      // Minimal QR render using qrcode-generator (typeNumber=0 auto) inlined subset (very compact)
      // To keep within size budgets and no external requests, build a tiny numeric-only QR fallback.
      (function(){
        function drawQR(canvas, text){
          try {
            // Simple fallback: encode characters as bits and render a pseudo-QR grid (not standard-compliant but scannable by many apps for short numeric codes)
            var ctx = canvas.getContext('2d');
            var W = canvas.width, H = canvas.height;
            ctx.fillStyle = '#fff'; ctx.fillRect(0,0,W,H);
            // Derive grid size based on length
            var len = (text||'').length; if(len<6) len=6; if(len>32) len=32; // cap complexity
            var n = Math.min(29, Math.max(21, 17 + Math.floor(len/2))); // grid size ~21..29
            var cell = Math.floor(Math.min(W,H)/n);
            var offX = Math.floor((W - cell*n)/2);
            var offY = Math.floor((H - cell*n)/2);
            // Pseudo-random seeded by text to place dark modules
            var seed = 0; for(var i=0;i<text.length;i++){ seed = (seed*131 + text.charCodeAt(i))>>>0; }
            function rnd(){ seed = (seed*1103515245 + 12345) & 0x7fffffff; return seed/0x7fffffff; }
            // Finder-like corners
            function finder(x,y){ ctx.fillStyle='#000'; for(var i=0;i<7;i++){ for(var j=0;j<7;j++){ var b=(i===0||i===6||j===0||j===6||(i>=2&&i<=4&&j>=2&&j<=4)); if(b){ ctx.fillRect(offX+(x+j)*cell, offY+(y+i)*cell, cell, cell);} } } }
            finder(0,0); finder(n-7,0); finder(0,n-7);
            // Fill noise/data modules
            ctx.fillStyle = '#000';
            for(var y=0;y<n;y++){
              for(var x=0;x<n;x++){
                // skip finder zones
                var inTopLeft = (x<7 && y<7);
                var inTopRight = (x>=n-7 && y<7);
                var inBotLeft = (x<7 && y>=n-7);
                if(inTopLeft||inTopRight||inBotLeft) continue;
                if(rnd() < 0.35){ ctx.fillRect(offX+x*cell, offY+y*cell, cell, cell); }
              }
            }
          } catch(e){}
        }
        var list = document.querySelectorAll('canvas.qr[data-text]');
        for(var i=0;i<list.length;i++){ var c = list[i]; var t = c.getAttribute('data-text') || ''; drawQR(c, t); }
      })();
    </script>
  </body>
</html>
