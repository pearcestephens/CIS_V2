<?php
declare(strict_types=1);
/**
 * https://staff.vapeshed.co.nz/modules/services/print/next.php
 * Stub endpoint: returns 501 until wired to print_jobs.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
header('Content-Type: application/json; charset=utf-8');
http_response_code(501);
echo json_encode(['ok'=>false,'error'=>'Print service not implemented','request_id'=>($_SERVER['HTTP_X_REQUEST_ID'] ?? null)]);
