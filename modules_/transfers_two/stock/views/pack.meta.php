<?php
declare(strict_types=1);
/**
 * View Meta: transfers/stock:pack (consolidated)
 * Enriches subtitle with outlet names when available.
 */

$__tid_keys = ['transfer','transfer_id','id','tid','t'];
$tid = 0; foreach ($__tid_keys as $__k) { if (isset($_GET[$__k]) && (int)$_GET[$__k] > 0) { $tid = (int)$_GET[$__k]; break; } }

$fromName = '';
$toName = '';
try {
  if ($tid > 0 && function_exists('cis_pdo')) {
    $pdo = cis_pdo();
    // Prefer canonical transfers table; fall back to legacy
    $stmt = $pdo->prepare('SELECT outlet_from, outlet_to FROM transfers WHERE id = ?');
    $stmt->execute([$tid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) {
      $stmt = $pdo->prepare('SELECT outlet_from, outlet_to FROM stock_transfers WHERE transfer_id = ?');
      $stmt->execute([$tid]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if ($row) {
      $from = (string)($row['outlet_from'] ?? '');
      $to   = (string)($row['outlet_to'] ?? '');
      if ($from !== '' || $to !== '') {
        $ids = [];
        if ($from !== '') $ids[$from] = true;
        if ($to !== '') $ids[$to] = true;
        if ($ids) {
          $list = array_keys($ids);
          $ph = implode(',', array_fill(0, count($list), '?'));
          $q = $pdo->prepare("SELECT id,name FROM vend_outlets WHERE id IN ($ph)");
          $q->execute($list);
          $map = [];
          while ($r = $q->fetch(PDO::FETCH_ASSOC)) { $map[(string)$r['id']] = (string)($r['name'] ?? ''); }
          $fromName = $map[$from] ?? $from;
          $toName   = $map[$to]   ?? $to;
        }
      }
    }
  }
} catch (Throwable $e) { /* ignore meta enrichment errors */ }

$title = $tid > 0 ? ('Pack Transfer #'.$tid) : 'Pack Transfer';
$subtitle = ($fromName !== '' || $toName !== '') ? ($fromName.' → '.$toName) : '';

return [
  'title' => $title,
  'subtitle' => $subtitle,
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Transfers', 'href' => 'https://staff.vapeshed.co.nz/modules/transfers/dashboard.php'],
    ['label' => 'Stock', 'href' => 'https://staff.vapeshed.co.nz/modules/transfers/stock/dashboard.php'],
    ['label' => $tid > 0 ? ('Pack #'.$tid) : 'Pack'],
  ],
  'layout' => 'card',
  'page_title' => $title.' — CIS',
  'assets' => [
    // View enqueues assets itself via tpl_style/tpl_script to ensure versioned URLs.
  ],
];
