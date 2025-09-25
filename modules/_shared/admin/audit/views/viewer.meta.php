<?php
return [
  'title' => 'Audit Viewer',
  'subtitle' => 'Investigate actions and page telemetry',
  'layout' => 'full-bleed',
  'assets' => [
    'css' => ['https://staff.vapeshed.co.nz/modules/_shared/admin/audit/assets/css/viewer.css'],
    'js'  => [['https://staff.vapeshed.co.nz/modules/_shared/admin/audit/assets/js/viewer.js',['defer'=>true]]]
  ],
  'breadcrumb' => [
    ['label' => 'Home', 'href' => 'https://staff.vapeshed.co.nz/'],
    ['label' => 'Admin'],
    ['label' => 'Audit Viewer'],
  ],
];
