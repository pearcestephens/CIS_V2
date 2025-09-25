<?php
/**
 * Compact box sticker label (e.g., 62x29mm) for label printers.
 * URL: https://staff.vapeshed.co.nz/modules/transfers/stock/print/box_sticker.php?transfer=123&box=1&boxes=3&w=1.2&p=E20&from=A&to=B&car=nzpost
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
$tid   = (int)($_GET['transfer'] ?? 0);
$box   = (int)($_GET['box'] ?? 1);
$boxes = max(1, (int)($_GET['boxes'] ?? 1));
$w     = trim((string)($_GET['w'] ?? ''));
$preset= trim((string)($_GET['p'] ?? ''));
$from  = trim((string)($_GET['from'] ?? ''));
$to    = trim((string)($_GET['to'] ?? ''));
$car   = strtolower((string)($_GET['car'] ?? 'manual'));
if ($tid <= 0) { http_response_code(400); echo 'Missing transfer'; exit; }
// Accent color by carrier
$accent = '#4455EE';
if ($car === 'nzpost') $accent = '#d32f2f';
elseif ($car === 'gss') $accent = '#2e7d32';
elseif ($car === 'manual') $accent = '#607d8b';
$brand = ($car==='nzpost') ? 'NZ Post' : (($car==='gss') ? 'NZ Couriers' : 'Manual');
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TX#<?php echo $tid; ?> · <?php echo $box; ?>/<?php echo $boxes; ?></title>
  <style>
    /* 62x29mm (Brother DK-11209-ish). Adjust in printers if needed. */
    @page { size: 62mm 29mm; margin: 2mm; }
    html, body { margin:0; padding:0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, 'Noto Sans', sans-serif; }
    .wrap { width: 58mm; height: 25mm; position: relative; }
  .bar { position:absolute; left:0; top:0; width:100%; height:4mm; background: <?php echo $accent; ?>; }
  .brand { position:absolute; top:0.5mm; right:1mm; background:#fff; color: <?php echo $accent; ?>; border:1px solid <?php echo $accent; ?>; border-radius: 3px; padding: 0 2mm; font-weight:700; font-size:7pt; line-height: 10pt; }
    .content { position:absolute; top:4mm; left:0; right:0; bottom:0; display:flex; align-items:center; }
    .left { flex:1; padding:1mm 2mm; }
    .right { width: 18mm; text-align:right; padding: 0 2mm; }
    .tx { font-weight:800; font-size: 12pt; letter-spacing:0.5px; }
    .bt { font-weight:700; font-size: 11pt; }
    .meta { font-size: 8pt; color:#333; }
    .wgt { font-weight:800; font-size: 11pt; }
    .preset { font-size: 8pt; color:#444; }
  </style>
</head>
<body onload="window.print()">
  <div class="wrap">
    <div class="bar"></div>
  <div class="brand"><?php echo htmlspecialchars($brand); ?></div>
    <div class="content">
      <div class="left">
        <div class="tx">TX#<?php echo $tid; ?></div>
        <div class="meta">From: <?php echo htmlspecialchars($from ?: ''); ?></div>
        <div class="meta">To: <?php echo htmlspecialchars($to ?: ''); ?></div>
        <div class="preset">Preset: <?php echo htmlspecialchars($preset ?: '-'); ?></div>
      </div>
      <div class="right">
        <div class="bt"><?php echo $box; ?>/<?php echo $boxes; ?></div>
        <div class="wgt"><?php echo ($w !== '' ? htmlspecialchars($w) : '—'); ?>kg</div>
      </div>
    </div>
  </div>
</body>
</html>
<?php
