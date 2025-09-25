<?php declare(strict_types=1);
/** Minimal, tweak as you go */
$current = $_GET['view'] ?? '';
$items = [
  ['label' => 'Dashboard', 'href' => '/cisv2/'],
  ['label' => 'Transfers', 'href' => '/cisv2/router.php?module=transfers/stock&view=pack'],
  ['label' => 'Receive',   'href' => '/cisv2/router.php?module=transfers/stock&view=receive'],
];
?>
<div class="p-3">
  <div class="mb-3 px-2 text-uppercase small opacity-75">Navigation</div>
  <?php foreach ($items as $it): ?>
    <a class="<?= (strpos($it['href'], 'view='.$current) !== false) ? 'active' : '' ?>" href="<?= htmlspecialchars($it['href'], ENT_QUOTES) ?>">
      <?= htmlspecialchars($it['label'], ENT_QUOTES) ?>
    </a>
  <?php endforeach; ?>
</div>
