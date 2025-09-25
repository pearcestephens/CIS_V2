<?php
/**
 * Payroll > Wage Discrepancies (List View)
 * Route: /module/human_resources/payroll/discrepancies/list
 */
?>
<div class="alert alert-info">
  <i class="fa fa-info-circle"></i> Review payroll mismatches below.
</div>

<form id="discrepancy-filter" class="form-inline mb-3">
  <input type="date" name="from" class="form-control mr-2">
  <input type="date" name="to" class="form-control mr-2">
  <button type="submit" class="btn btn-primary">Filter</button>
</form>

<div class="table-responsive">
  <table class="table table-striped" id="discrepancy-table">
    <thead>
      <tr>
        <th>Staff</th>
        <th>Date</th>
        <th>Expected</th>
        <th>Actual</th>
        <th>Variance</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <!-- Populated by JS -->
    </tbody>
  </table>
</div>
