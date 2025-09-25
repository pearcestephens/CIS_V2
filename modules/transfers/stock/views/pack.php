<?php
/**
 * File: modules/transfers/stock/views/pack.php
 * Purpose: Render a minimal packing UI for CIS v2 router prototype.
 * Author: GitHub Copilot
 * Last Modified: 2025-09-25
 * Dependencies: None
 */
declare(strict_types=1);

$transferId = (int) ($_GET['transfer'] ?? 0);

$rows = [
    ['sku' => 'ABC-001', 'name' => 'Sample A', 'req' => 10, 'packed' => 0],
    ['sku' => 'ABC-002', 'name' => 'Sample B', 'req' => 5, 'packed' => 0],
];
?>
<h3>Pack Transfer #<?= htmlspecialchars((string) $transferId, ENT_QUOTES, 'UTF-8') ?></h3>
<table class="table">
  <thead>
    <tr>
      <th>SKU</th>
      <th>Product</th>
      <th>Req</th>
      <th>Packed</th>
    </tr>
  </thead>
  <tbody id="rows">
  <?php foreach ($rows as $row): ?>
    <tr data-sku="<?= htmlspecialchars($row['sku'], ENT_QUOTES, 'UTF-8') ?>">
      <td><code><?= htmlspecialchars($row['sku'], ENT_QUOTES, 'UTF-8') ?></code></td>
      <td><?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?></td>
      <td class="text-end"><?= (int) $row['req'] ?></td>
      <td class="text-end">
        <input type="number" class="packed" min="0" step="1" value="<?= (int) $row['packed'] ?>">
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<button id="finalize">Finalize Pack</button>
<div id="msg" style="margin-top:10px;"></div>
<script>
document.getElementById('finalize').addEventListener('click', async () => {
  const rows = Array.from(document.querySelectorAll('#rows tr')).map(tr => ({
    sku: tr.dataset.sku,
    packed: Number(tr.querySelector('.packed').value || 0),
  }));

  const response = await fetch('/modules/transfers/stock/ajax/handler.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({
      action: 'finalize',
      transfer_id: <?= (int) $transferId ?>,
      lines: rows,
    }),
  });

  const payload = await response.json();
  document.getElementById('msg').textContent = payload.ok ? 'Packed OK' : (payload.error || 'Failed');
});
</script>
