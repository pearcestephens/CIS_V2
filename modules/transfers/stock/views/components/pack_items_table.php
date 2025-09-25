<?php
declare(strict_types=1);

/** @var array $packItemsData */

$items = $packItemsData ?? [];
?>

<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center">
		<strong>Items to Pack</strong>
		<span class="text-muted small"><?= number_format(count($items)) ?> lines</span>
	</div>
	<div class="card-body p-0">
		<div class="table-responsive">
			<table class="table table-sm mb-0" id="pack-items-table">
				<thead>
					<tr>
						<th style="width:40%">Product</th>
						<th style="width:10%" class="text-end">Requested</th>
						<th style="width:15%" class="text-end">Ship Units</th>
						<th style="width:15%" class="text-end">Unit Weight (g)</th>
						<th style="width:20%" class="text-end">Total Weight (g)</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!$items): ?>
						<tr><td colspan="5" class="text-muted text-center py-4">No items loaded yet.</td></tr>
					<?php else: ?>
						<?php foreach ($items as $row):
							$shipUnits = (int)($row['suggested_ship_units'] ?? ($row['requested_qty'] ?? 0));
							$unitG     = (int)($row['unit_g'] ?? 0);
							$totalG    = $shipUnits * $unitG;
						?>
							<tr data-item-id="<?= (int)$row['id'] ?>" data-product-id="<?= (int)$row['product_id'] ?>">
								<td>
									<div class="mono small text-uppercase"><?= htmlspecialchars($row['sku'] ?? $row['product_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
									<div><?= htmlspecialchars($row['name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
								</td>
								<td class="text-end"><?= number_format((int)($row['requested_qty'] ?? 0)) ?></td>
								<td class="text-end"><?= number_format($shipUnits) ?></td>
								<td class="text-end"><?= number_format($unitG) ?></td>
								<td class="text-end"><?= number_format($totalG) ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>