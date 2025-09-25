<?php
declare(strict_types=1);
return [
  'title' => 'Transfers',
  'subtitle' => 'Operations hub',
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Transfers'],
    ['label' => 'Dashboard'],
  ],
  'layout' => 'plain',
  'page_title' => 'Transfers — CIS',
  // Optional quick actions rendered on the right side of the breadcrumb
  'right' => <<<HTML
    <div class="btn-group" role="group" aria-label="Quick actions">
      <a class="btn" style="background:#9c27b0;border-radius:10px;color:#fff;" href="#" data-toggle="modal" data-target="#quickQtyChange">Quick Product Qty Change</a>
      <a class="btn ml-2" style="background:#8bc34a;border-radius:10px;color:#fff;" href="#" data-toggle="modal" data-target="#quickFloatCount">Store Cashup Calculator</a>
    </div>
  HTML,
  'assets' => [
    'js' => [ ['https://staff.vapeshed.co.nz/modules/_shared/assets/js/cis-shared.js', ['defer'=>true]] ],
  ],
];
