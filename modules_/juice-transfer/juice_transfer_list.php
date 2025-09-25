<?php
/**
 * Juice Transfer List View
 * Display all juice transfers with filtering and searching
 * Location: /juice-transfer/juice_transfer_list.php
 * 
 * @author CIS System
 * @version 2.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/juice-transfer/core/JuiceTransferTemplateController.php';

// Initialize session and authentication
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: https://staff.vapeshed.co.nz/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Initialize template controller
$templateController = new JuiceTransferTemplateController();

// Get filter parameters
$filters = [
    'status' => $_GET['status'] ?? '',
    'outlet_id' => $_GET['outlet_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = ['1=1'];
$params = [];

if ($filters['status']) {
    $whereConditions[] = 'jt.status = ?';
    $params[] = $filters['status'];
}

if ($filters['outlet_id']) {
    $whereConditions[] = '(jt.from_outlet_id = ? OR jt.to_outlet_id = ?)';
    $params[] = $filters['outlet_id'];
    $params[] = $filters['outlet_id'];
}

if ($filters['date_from']) {
    $whereConditions[] = 'jt.created_at >= ?';
    $params[] = $filters['date_from'] . ' 00:00:00';
}

if ($filters['date_to']) {
    $whereConditions[] = 'jt.created_at <= ?';
    $params[] = $filters['date_to'] . ' 23:59:59';
}

if ($filters['priority']) {
    $whereConditions[] = 'jt.priority = ?';
    $params[] = $filters['priority'];
}

if ($filters['search']) {
    $whereConditions[] = '(
        jt.id LIKE ? OR 
        vo_from.name LIKE ? OR 
        vo_to.name LIKE ? OR
        jt.notes LIKE ? OR
        jt.tracking_number LIKE ?
    )';
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = implode(' AND ', $whereConditions);

try {
    // Get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT jt.id) as total
        FROM juice_transfers jt
        LEFT JOIN vend_outlets vo_from ON jt.from_outlet_id = vo_from.id
        LEFT JOIN vend_outlets vo_to ON jt.to_outlet_id = vo_to.id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Get transfers
    $stmt = $pdo->prepare("
        SELECT 
            jt.*,
            vo_from.name as from_outlet,
            vo_from.address as from_address,
            vo_to.name as to_outlet,
            vo_to.address as to_address,
            u.name as created_by_name,
            COUNT(DISTINCT jti.id) as item_count,
            COALESCE(SUM(jti.quantity_ml), 0) as total_volume_ml,
            COALESCE(SUM(jti.quantity_ml * jti.cost_per_ml), 0) as total_value
        FROM juice_transfers jt
        LEFT JOIN vend_outlets vo_from ON jt.from_outlet_id = vo_from.id
        LEFT JOIN vend_outlets vo_to ON jt.to_outlet_id = vo_to.id
        LEFT JOIN users u ON jt.created_by = u.id
        LEFT JOIN juice_transfer_items jti ON jt.id = jti.transfer_id
        WHERE $whereClause
        GROUP BY jt.id
        ORDER BY jt.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    
    $stmt->execute($params);
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get outlets for filter dropdown
    $outletsStmt = $pdo->query("
        SELECT id, name 
        FROM vend_outlets 
        WHERE status = 'active' 
        ORDER BY name
    ");
    $outlets = $outletsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error loading transfers: " . $e->getMessage());
    $transfers = [];
    $outlets = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Build content
ob_start();
?>

<div class="juice-transfer-list">
    <!-- Filters -->
    <div class="juice-form-container mb-4">
        <form method="GET" class="juice-filter-form">
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control juice-filter-select" data-filter-type="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="in_transit" <?php echo $filters['status'] === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                            <option value="delivered" <?php echo $filters['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="received" <?php echo $filters['status'] === 'received' ? 'selected' : ''; ?>>Received</option>
                            <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="outlet_id">Outlet</label>
                        <select name="outlet_id" id="outlet_id" class="form-control">
                            <option value="">All Outlets</option>
                            <?php foreach ($outlets as $outlet): ?>
                            <option value="<?php echo $outlet['id']; ?>" 
                                    <?php echo $filters['outlet_id'] == $outlet['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($outlet['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="priority">Priority</label>
                        <select name="priority" id="priority" class="form-control">
                            <option value="">All Priorities</option>
                            <option value="low" <?php echo $filters['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="normal" <?php echo $filters['priority'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo $filters['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $filters['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" 
                               value="<?php echo $filters['date_from']; ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo $filters['date_to']; ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control juice-search-input" 
                               placeholder="ID, outlet, notes..." 
                               value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button type="submit" class="juice-btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" class="juice-btn btn-secondary ml-2">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
                
                <div>
                    <span class="text-muted">
                        Showing <?php echo number_format($totalRecords); ?> transfers
                    </span>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Results -->
    <div class="juice-table-wrapper">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <h5 class="mb-0">
                <i class="fas fa-exchange-alt"></i> Juice Transfers
            </h5>
            
            <div>
                <div class="btn-group mr-2">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                            type="button" data-toggle="dropdown">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <a class="dropdown-item" href="?<?php echo http_build_query(array_merge($filters, ['export' => 'csv'])); ?>">
                            <i class="fas fa-file-csv"></i> Export as CSV
                        </a>
                        <a class="dropdown-item" href="?<?php echo http_build_query(array_merge($filters, ['export' => 'pdf'])); ?>">
                            <i class="fas fa-file-pdf"></i> Export as PDF
                        </a>
                    </div>
                </div>
                
                <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_create.php" 
                   class="juice-btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> New Transfer
                </a>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="juice-table juice-datatable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>From → To</th>
                        <th>Items</th>
                        <th>Volume</th>
                        <th>Value</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Created By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transfers)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle"></i> 
                            <?php if (array_filter($filters)): ?>
                                No transfers found matching your search criteria.
                            <?php else: ?>
                                No transfers found. <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_create.php">Create your first transfer</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transfers as $transfer): ?>
                    <tr class="transfer-row" data-transfer-id="<?php echo $transfer['id']; ?>">
                        <td class="transfer-id">
                            <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_view.php?id=<?php echo $transfer['id']; ?>" 
                               class="text-decoration-none">
                                #<?php echo str_pad($transfer['id'], 6, '0', STR_PAD_LEFT); ?>
                            </a>
                        </td>
                        
                        <td>
                            <div class="d-flex flex-column">
                                <div class="outlet-name"><?php echo htmlspecialchars($transfer['from_outlet']); ?></div>
                                <i class="fas fa-arrow-down text-muted small my-1"></i>
                                <div class="outlet-name"><?php echo htmlspecialchars($transfer['to_outlet']); ?></div>
                            </div>
                        </td>
                        
                        <td class="text-center">
                            <span class="font-weight-bold"><?php echo $transfer['item_count']; ?></span>
                            <?php if ($transfer['item_count'] > 1): ?>
                            <br><small class="text-muted">items</small>
                            <?php else: ?>
                            <br><small class="text-muted">item</small>
                            <?php endif; ?>
                        </td>
                        
                        <td class="volume-cell">
                            <?php echo $templateController->formatVolume($transfer['total_volume_ml']); ?>
                        </td>
                        
                        <td class="volume-cell">
                            $<?php echo number_format($transfer['total_value'], 2); ?>
                        </td>
                        
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="priority-indicator priority-<?php echo $transfer['priority']; ?>"></span>
                                <?php echo ucfirst($transfer['priority']); ?>
                            </div>
                        </td>
                        
                        <td class="status-cell">
                            <?php echo $templateController->renderStatusBadge($transfer['status']); ?>
                        </td>
                        
                        <td>
                            <div><?php echo date('M j, Y', strtotime($transfer['created_at'])); ?></div>
                            <div class="text-muted small"><?php echo date('g:i A', strtotime($transfer['created_at'])); ?></div>
                        </td>
                        
                        <td>
                            <div class="small"><?php echo htmlspecialchars($transfer['created_by_name'] ?? 'System'); ?></div>
                        </td>
                        
                        <td class="actions-cell">
                            <div class="btn-group">
                                <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_view.php?id=<?php echo $transfer['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" 
                                   title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if ($transfer['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-outline-success juice-action-btn" 
                                        data-action="approve" 
                                        data-transfer-id="<?php echo $transfer['id']; ?>" 
                                        title="Approve Transfer">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger juice-action-btn" 
                                        data-action="cancel" 
                                        data-transfer-id="<?php echo $transfer['id']; ?>" 
                                        title="Cancel Transfer">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php elseif ($transfer['status'] === 'in_transit'): ?>
                                <button class="btn btn-sm btn-outline-info juice-action-btn" 
                                        data-action="receive" 
                                        data-transfer-id="<?php echo $transfer['id']; ?>" 
                                        title="Mark as Received">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>
                                
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" data-toggle="dropdown" title="More Actions">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" 
                                           href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_print.php?id=<?php echo $transfer['id']; ?>" 
                                           target="_blank">
                                            <i class="fas fa-print"></i> Print
                                        </a>
                                        <a class="dropdown-item" 
                                           href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_duplicate.php?id=<?php echo $transfer['id']; ?>">
                                            <i class="fas fa-copy"></i> Duplicate
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-muted" 
                                           href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_history.php?id=<?php echo $transfer['id']; ?>">
                                            <i class="fas fa-history"></i> View History
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div>
                <small class="text-muted">
                    Showing <?php echo number_format($offset + 1); ?>-<?php echo number_format(min($offset + $limit, $totalRecords)); ?> 
                    of <?php echo number_format($totalRecords); ?> transfers
                </small>
            </div>
            
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $queryParams = array_filter($filters);
                    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
                    ?>
                    
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $page - 1])); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?php echo $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $page + 1])); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Handle bulk actions
    let selectedTransfers = [];
    
    // Checkbox selection
    $('.transfer-checkbox').on('change', function() {
        const transferId = $(this).data('transfer-id');
        if ($(this).is(':checked')) {
            if (!selectedTransfers.includes(transferId)) {
                selectedTransfers.push(transferId);
            }
        } else {
            selectedTransfers = selectedTransfers.filter(id => id !== transferId);
        }
        
        updateBulkActions();
    });
    
    // Update bulk action buttons
    function updateBulkActions() {
        const $bulkActions = $('.bulk-actions');
        if (selectedTransfers.length > 0) {
            $bulkActions.show();
            $('.selected-count').text(selectedTransfers.length);
        } else {
            $bulkActions.hide();
        }
    }
    
    // Action handlers
    $('.juice-action-btn').on('click', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const action = $btn.data('action');
        const transferId = $btn.data('transfer-id');
        
        let confirmMessage = '';
        let newStatus = '';
        
        switch (action) {
            case 'approve':
                confirmMessage = 'Are you sure you want to approve this transfer?';
                newStatus = 'approved';
                break;
            case 'cancel':
                confirmMessage = 'Are you sure you want to cancel this transfer? This action cannot be undone.';
                newStatus = 'cancelled';
                break;
            case 'receive':
                confirmMessage = 'Mark this transfer as received?';
                newStatus = 'received';
                break;
        }
        
        if (confirm(confirmMessage)) {
            JuiceTransfer.updateTransferStatus(transferId, newStatus);
        }
    });
    
    // Auto-submit filter form on certain changes
    $('#status, #outlet_id, #priority').on('change', function() {
        $(this).closest('form').submit();
    });
    
    // Enhanced search with debouncing
    let searchTimeout;
    $('#search').on('input', function() {
        clearTimeout(searchTimeout);
        const searchTerm = $(this).val();
        
        searchTimeout = setTimeout(() => {
            if (searchTerm.length >= 3 || searchTerm.length === 0) {
                $(this).closest('form').submit();
            }
        }, 500);
    });
});
</script>

<?php
$content = ob_get_clean();

// Additional CSS for list view
$additionalCSS = [
    'https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css',
    'https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css'
];

// Additional JS for list view
$additionalJS = [
    'https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js',
    'https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js',
    'https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js'
];

// Render the page
echo $templateController->renderJuiceTransferPage(
    'All Transfers',
    $content,
    'transfers',
    $additionalCSS,
    $additionalJS
);
?>
