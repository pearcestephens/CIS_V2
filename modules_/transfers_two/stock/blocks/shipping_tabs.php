<?php
?>
<section class="card mb-3">
  <div class="card-header py-2"><small class="text-muted text-uppercase">Shipping</small></div>
  <div class="card-body py-2">
    <?php tpl_block('printer'); ?>
    <ul class="nav nav-tabs stx-tabs mb-0" role="tablist">
      <li class="nav-item"><a class="nav-link active" data-tab="nzpost" href="#" role="tab">NZ Post</a></li>
      <li class="nav-item"><a class="nav-link" data-tab="gss" href="#" role="tab">GSS</a></li>
      <li class="nav-item"><a class="nav-link" data-tab="manual" href="#" role="tab">Manual</a></li>
      <li class="nav-item ml-auto"><a class="nav-link" data-tab="history" href="#" role="tab">History</a></li>
    </ul>
  </div>
</section>
