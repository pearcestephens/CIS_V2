<?php
/**
 * Filename: list_events.php
 * Action: admin.list_events
 * URL: https://staff.vapeshed.co.nz/modules/purchase-orders/ajax/handler.php
 * Purpose: Admin listing of PO events with pagination and optional PO filter.
 */
declare(strict_types=1);

$poId = isset($_POST['po_id']) ? (int)$_POST['po_id'] : 0;
$page = max(1, (int)($_POST['page'] ?? 1));
$size = min(200, max(10, (int)($_POST['size'] ?? 50)));
$off  = ($page - 1) * $size;

try {
    $pdo = po_pdo();
    if (!po_table_exists($pdo,'po_events')) po_jresp(true, ['rows'=>[], 'total'=>0, 'page'=>$page, 'size'=>$size]);
    $where = '';
    $args = [];
    if ($poId > 0) { $where = 'WHERE purchase_order_id = ?'; $args[] = $poId; }
    $sql = "SELECT event_id, purchase_order_id, event_type, event_data, created_by, created_at
            FROM po_events
            $where
            ORDER BY event_id DESC
            LIMIT $size OFFSET $off";
    $rows = $pdo->prepare($sql); $rows->execute($args);
    $data = $rows->fetchAll();
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM po_events " . ($where ?: ''));
    $cnt->execute($args);
    $total = (int)$cnt->fetchColumn();
    po_jresp(true, ['rows'=>$data, 'total'=>$total, 'page'=>$page, 'size'=>$size]);
} catch (Throwable $e) {
    error_log('[admin.list_events] '.$e->getMessage());
    po_jresp(false, ['code'=>'internal_error','message'=>'Failed to list events'], 500);
}
