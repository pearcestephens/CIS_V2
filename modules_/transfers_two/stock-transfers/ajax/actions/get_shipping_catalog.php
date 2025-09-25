<?php
// get_shipping_catalog.php (unified)
// Returns carrier catalog: services, containers, surcharges. Fallbacks to JSON assets.

try {
  $base = realpath(__DIR__ . '/../../'); // actions/ -> ajax/ -> up one = ajax; up one more = stock-transfers
  $jsonPath = realpath($base . '/../assets/data/shipping_pricing.json');
  if (!$jsonPath || !is_readable($jsonPath)) { jresp(false, 'Pricing JSON not found', 500); }
  $raw = file_get_contents($jsonPath);
  $data = json_decode($raw, true);
  if (!is_array($data)) { jresp(false, 'Invalid pricing JSON', 500); }

  $resp = [
    'carriers' => [
      [
        'code' => 'NZ_POST',
        'name' => 'NZ Post',
        'services' => array_merge(
          [['code'=>'LETTER','name'=>'Standard Mail Letter']],
          [['code'=>'BOX','name'=>'Post Box']]
        ),
        'containers' => array_merge(
          isset($data['nzpost']['letters']) ? $data['nzpost']['letters'] : [],
          isset($data['nzpost']['boxes']) ? $data['nzpost']['boxes'] : []
        ),
        'surcharges' => []
      ],
      [
        'code' => 'GSS',
        'name' => 'NZ Couriers (GSS)',
        'services' => isset($data['gss']['tickets']) ? $data['gss']['tickets'] : [],
        'containers' => [],
        'surcharges' => isset($data['gss']['surcharges']) ? $data['gss']['surcharges'] : []
      ]
    ]
  ];
  $resp['request_id'] = $__ajax_context['request_id'];
  jresp(true, $resp);
} catch (Throwable $e) {
  error_log('[transfers.stock-transfers.get_shipping_catalog]['.$__ajax_context['request_id'].'] '.$e->getMessage());
  jresp(false, 'Server error', 500);
}
