<?php
/**
 * Return carrier service catalog + containers/pricing using shared JSON fallback.
 * Mirrors stock-transfers implementation so UI can fetch available services/prices.
 */
declare(strict_types=1);

try {
  // Prefer a local assets/data path if present; otherwise reuse stock-transfers shared file
  $baseAjax = realpath(__DIR__ . '/..');
  $localJson = $baseAjax ? realpath($baseAjax . '/../assets/data/shipping_pricing.json') : false;
  $fallback = realpath($_SERVER['DOCUMENT_ROOT'] . '/modules/transfers/stock-transfers/assets/data/shipping_pricing.json');
  $jsonPath = ($localJson && is_readable($localJson)) ? $localJson : $fallback;
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
  $resp['request_id'] = $GLOBALS['__ajax_context']['request_id'] ?? ($GLOBALS['reqId'] ?? '');
  jresp(true, $resp);
} catch (Throwable $e) {
  error_log('[transfers.stock.get_shipping_catalog]['.($GLOBALS['__ajax_context']['request_id'] ?? '').'] '.$e->getMessage());
  jresp(false, 'Server error', 500);
}
