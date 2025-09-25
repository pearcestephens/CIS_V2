<?php
/**
 * record_shipment.php
 * Persist shipment details (tracking, label_url, packages) for a transfer.
 * - In production: call saveShipment_wrapped if available.
 * - In non-prod/DEV fallback: append into testing/.state.json under shipments.
 */

declare(strict_types=1);

$tid = (int)($_POST['transfer_id'] ?? 0);
if ($tid <= 0) jresp(false,'Missing transfer_id',400);

$carrier   = (string)($_POST['carrier'] ?? '');
$reference = (string)($_POST['reference'] ?? '');
$tracking  = (string)($_POST['tracking_number'] ?? ($_POST['tracking'] ?? ''));
$labelUrl  = (string)($_POST['label_url'] ?? '');
$parcelsRaw = $_POST['parcels'] ?? '[]';
$packages = is_string($parcelsRaw) ? json_decode($parcelsRaw, true) : (is_array($parcelsRaw) ? $parcelsRaw : []);
if (!is_array($packages)) { $packages = []; }
// optional contents plan (box index => [{pid,name,qty}])
$contentsPlanRaw = $_POST['contents_plan'] ?? '{}';
$contentsPlan = is_string($contentsPlanRaw) ? json_decode($contentsPlanRaw, true) : (is_array($contentsPlanRaw) ? $contentsPlanRaw : []);
if (!is_array($contentsPlan)) { $contentsPlan = []; }

// Try production wrapper first
if (function_exists('saveShipment_wrapped')) {
  try {
    // Detect supported parameter count to avoid PHP 8 ArgumentCountError
    $ref = new ReflectionFunction('saveShipment_wrapped');
    $paramCount = $ref->getNumberOfParameters();
    if ($paramCount >= 10) {
      $ok = saveShipment_wrapped($tid, $carrier, $tracking, $reference, $labelUrl, $packages, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id'], $contentsPlan);
    } else {
      $ok = saveShipment_wrapped($tid, $carrier, $tracking, $reference, $labelUrl, $packages, $__ajax_context['uid'], (int)$__ajax_context['simulate'], $__ajax_context['request_id']);
    }
    if (!($ok['success'] ?? false)) jresp(false, $ok['error']['message'] ?? ($ok['error'] ?? 'Failed to record shipment'));
    $data = (array)($ok['data'] ?? []);
    $data['request_id'] = $__ajax_context['request_id'];
    jresp(true, $data);
  } catch (Throwable $e) {
    error_log('[transfers.stock.record_shipment]['.$__ajax_context['request_id'].'] '.$e->getMessage());
    // Fall through to DEV store below
  }
}

// DEV fallback: write to testing/.state.json
$stateFile = realpath(__DIR__ . '/../../testing') ?: (__DIR__ . '/../../testing');
if (!is_dir($stateFile)) { @mkdir($stateFile, 0775, true); }
$statePath = rtrim($stateFile,'/').'/'.'.state.json';
$current = [];
if (is_file($statePath)) { $json = @file_get_contents($statePath); if ($json) { $arr = json_decode($json, true); if (is_array($arr)) $current = $arr; } }

if (!isset($current['shipments'])) { $current['shipments'] = []; }
$shipmentId = 'shp_'.bin2hex(random_bytes(6));
$boxCount = max(1, is_array($packages) ? count($packages) : (int)$packages);
$rec = [
  'shipment_id' => $shipmentId,
  'transfer_id' => $tid,
  'carrier' => $carrier,
  'reference' => $reference,
  'tracking_number' => $tracking,
  'label_url' => $labelUrl,
  'packages' => $packages,
  'contents_plan' => $contentsPlan,
  'box_count' => $boxCount,
  'created_by' => (int)$__ajax_context['uid'],
  'request_id' => (string)$__ajax_context['request_id'],
  'created_at' => date('c'),
];
$current['shipments'][] = $rec;
@file_put_contents($statePath, json_encode($current, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

jresp(true, ['shipment_id'=>$shipmentId, 'box_count'=>$boxCount, 'request_id'=>$__ajax_context['request_id']]);
