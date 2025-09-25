<?php
/**
 * /assets/templates/cisv2/overall-view.php
 *
 * CIS overall dashboard view.
 * Displays store overview, stock accuracy, quick actions, and summaries.
 */
?>
<section id="overallView" class="cis-overall-view container-fluid">

  <!-- Row: Store overview -->
  <div class="row mb-4">
    <div class="col">
      <h4>Store Overview</h4>
      <div class="card">
        <div class="card-body">
          <p class="text-muted">Overview data goes here.</p>
          <!-- Hook: module output -->
          <?php if (!empty($overviewContent)) echo $overviewContent; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Row: Stock accuracy + actions -->
  <div class="row mb-4">
    <div class="col-md-6">
      <h5>Stock Accuracy</h5>
      <div class="card">
        <div class="card-body">
          <a href="/module/transfers/stock/activity" class="btn btn-outline-primary btn-sm">
            View Accuracy Reports
          </a>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <h5>Quick Actions</h5>
      <div class="card">
        <div class="card-body">
          <a href="/module/transfers/stock/pack" class="btn btn-success btn-sm">
            New Stock Pack
          </a>
          <a href="/module/purchase-orders/list" class="btn btn-secondary btn-sm">
            Manage Purchase Orders
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Row: Other widgets -->
  <div class="row">
    <div class="col">
      <h5>System Summary</h5>
      <div class="card">
        <div class="card-body">
          <p class="text-muted">System summary metrics will appear here.</p>
        </div>
      </div>
    </div>
  </div>

</section>
