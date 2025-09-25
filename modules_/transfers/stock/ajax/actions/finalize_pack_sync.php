<?php declare(strict_types=1);

require_once dirname(__DIR__, 6) . '/bootstrap.php';
require_once dirname(__DIR__, 1) . '/bootstrap.php';

use Modules\Template\Responder;

header('Content-Type: application/json');

$requestId = bin2hex(random_bytes(8));

try {
  cis_require_login();

  if (function_exists('cis_vend_writes_disabled') && cis_vend_writes_disabled()) {
    throw new RuntimeException('Temporarily disabled due to system health (Vend)');
  }

  $input = json_decode(file_get_contents('php://input') ?: '{}', true, 512, JSON_THROW_ON_ERROR);
  $transferId = (int)($input['transfer_id'] ?? 0);
  if ($transferId <= 0) {
    throw new InvalidArgumentException('transfer_id is required');
  }

  $actorId = (int)($_SESSION['user_id'] ?? $_SESSION['userID'] ?? 0);
  $services = transfers_stock_services();
  $totals = $services->packing()->finalize($transferId, $actorId);

  cis_log('INFO', 'transfers', 'pack.finalized.sync', [
    'transfer_id' => $transferId,
    'boxes'       => $totals['boxes'],
    'weight_g'    => $totals['weight_g'],
    'actor_id'    => $actorId,
  ]);

  Responder::jsonSuccess([
    'transfer_id' => $transferId,
    'totals'      => $totals,
  ], ['request_id' => $requestId]);
} catch (InvalidArgumentException $e) {
  Responder::jsonError('transfer_finalize_invalid', $e->getMessage(), ['request_id' => $requestId], 422);
} catch (RuntimeException $e) {
  Responder::jsonError('transfer_finalize_conflict', $e->getMessage(), ['request_id' => $requestId], 409);
} catch (Throwable $e) {
  Responder::jsonError('transfer_finalize_error', $e->getMessage(), ['request_id' => $requestId], 500);
}
