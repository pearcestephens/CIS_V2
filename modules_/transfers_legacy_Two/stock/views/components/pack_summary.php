<?php
declare(strict_types=1);

/** @var array $packMetricsData */

$m = $packMetricsData ?? ['items_count'=>0,'total_ship_units'=>0,'total_weight_g'=>0,'total_weight_kg'=>0];
?>

<div class="card mb-3">
	<div class="card-header d-flex justify-content-between align-items-center">
		<strong>Pack Summary</strong>
		<span class="text-muted small">Live</span>
	</div>
	<div class="card-body">
		<dl class="row mb-0 pack-summary">
			<dt class="col-7 text-muted">Line Items</dt>
			<dd class="col-5 text-end mono"><?= number_format((int)$m['items_count']) ?></dd>
			<dt class="col-7 text-muted">Total Ship Units</dt>
			<dd class="col-5 text-end mono"><?= number_format((int)$m['total_ship_units']) ?></dd>
			<dt class="col-7 text-muted">Total Weight (g)</dt>
			<dd class="col-5 text-end mono"><?= number_format((int)$m['total_weight_g']) ?></dd>
			<dt class="col-7 text-muted">Total Weight (kg)</dt>
			<dd class="col-5 text-end mono"><?= number_format((float)$m['total_weight_kg'], 2) ?></dd>
		</dl>
	</div>
</div>