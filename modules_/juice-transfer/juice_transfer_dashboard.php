<?php
/**
 * Juice Transfer Dashboard
 * Main dashboard for juice transfer management system
 * Location: /juice-transfer/juice_transfer_dashboard.php
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

// Get dashboard data
try {
    // Get transfer statistics
    $transferStats = [];
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            COALESCE(SUM((
                SELECT SUM(quantity_ml) 
                FROM juice_transfer_items 
                WHERE transfer_id = jt.id
            )), 0) as total_volume
        FROM juice_transfers jt
        GROUP BY status
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $transferStats[$row['status']] = [
            'count' => $row['count'],
            'volume' => $row['total_volume']
        ];
    }
    
    // Get recent transfers
    $recentTransfers = [];
    $stmt = $pdo->prepare("
        SELECT 
            jt.*,
            vo_from.name as from_outlet,
            vo_to.name as to_outlet,
            COUNT(jti.id) as item_count,
            COALESCE(SUM(jti.quantity_ml), 0) as total_volume_ml,
            COALESCE(SUM(jti.quantity_ml * jp.cost_per_ml), 0) as total_value
        FROM juice_transfers jt
        LEFT JOIN vend_outlets vo_from ON jt.from_outlet_id = vo_from.id
        LEFT JOIN vend_outlets vo_to ON jt.to_outlet_id = vo_to.id
        LEFT JOIN juice_transfer_items jti ON jt.id = jti.transfer_id
        LEFT JOIN juice_products jp ON jti.product_id = jp.id
        GROUP BY jt.id
        ORDER BY jt.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $recentTransfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock alerts
    $lowStockAlerts = [];
    $stmt = $pdo->prepare("
        SELECT 
            jp.*,
            ji.quantity_ml,
            ji.last_updated,
            vo.name as outlet_name,
            ROUND((ji.quantity_ml / NULLIF(jp.reorder_level, 0)) * 100, 1) as stock_percentage
        FROM juice_inventory ji
        JOIN juice_products jp ON ji.product_id = jp.id
        LEFT JOIN vend_outlets vo ON ji.outlet_id = vo.id
        WHERE ji.quantity_ml <= jp.reorder_level
        AND jp.status = 'active'
        ORDER BY stock_percentage ASC
        LIMIT 5
    ");
    
    $stmt->execute();
    $lowStockAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get quality issues
    $qualityIssues = [];
    $stmt = $pdo->prepare("
        SELECT 
            jqc.*,
            jp.name as product_name,
            jp.nicotine_strength,
            vo.name as outlet_name
        FROM juice_quality_checks jqc
        JOIN juice_products jp ON jqc.product_id = jp.id
        LEFT JOIN vend_outlets vo ON jqc.outlet_id = vo.id
        WHERE jqc.status = 'failed'
        AND jqc.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY jqc.created_at DESC
        LIMIT 5
    ");
    
    $stmt->execute();
    $qualityIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get expiring batches
    $expiringBatches = [];
    $stmt = $pdo->prepare("
        SELECT 
            jb.*,
            jp.name as product_name,
            vo.name as outlet_name,
            DATEDIFF(jb.expiry_date, NOW()) as days_until_expiry
        FROM juice_batches jb
        JOIN juice_products jp ON jb.product_id = jp.id
        LEFT JOIN vend_outlets vo ON jb.current_outlet_id = vo.id
        WHERE jb.expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
        AND jb.expiry_date > NOW()
        AND jb.status = 'active'
        ORDER BY jb.expiry_date ASC
        LIMIT 5
    ");
    
    $stmt->execute();
    $expiringBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    $transferStats = [];
    $recentTransfers = [];
    $lowStockAlerts = [];
    $qualityIssues = [];
    $expiringBatches = [];
}

// Build dashboard content
ob_start();
?>

<div class="juice-dashboard">
    <!-- Statistics Cards -->
    <div class="juice-stats-grid">
        <div class="juice-stat-card" data-stat="pending_transfers">
            <i class="fas fa-clock stat-icon"></i>
            <div class="stat-number"><?php echo $transferStats['pending']['count'] ?? 0; ?></div>
            <div class="stat-label">Pending Transfers</div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i> +<?php echo $templateController->formatVolume($transferStats['pending']['volume'] ?? 0); ?>
            </div>
        </div>
        
        <div class="juice-stat-card" data-stat="in_transit">
            <i class="fas fa-truck stat-icon"></i>
            <div class="stat-number"><?php echo $transferStats['in_transit']['count'] ?? 0; ?></div>
            <div class="stat-label">In Transit</div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i> <?php echo $templateController->formatVolume($transferStats['in_transit']['volume'] ?? 0); ?>
            </div>
        </div>
        
        <div class="juice-stat-card success" data-stat="completed">
            <i class="fas fa-check-circle stat-icon"></i>
            <div class="stat-number"><?php echo ($transferStats['received']['count'] ?? 0) + ($transferStats['delivered']['count'] ?? 0); ?></div>
            <div class="stat-label">Completed This Month</div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i> 12%
            </div>
        </div>
        
        <div class="juice-stat-card <?php echo count($lowStockAlerts) > 0 ? 'warning' : 'success'; ?>" data-stat="low_stock">
            <i class="fas fa-exclamation-triangle stat-icon"></i>
            <div class="stat-number"><?php echo count($lowStockAlerts); ?></div>
            <div class="stat-label">Low Stock Items</div>
            <?php if (count($lowStockAlerts) > 0): ?>
            <div class="stat-change negative">
                <i class="fas fa-arrow-down"></i> Needs attention
            </div>
            <?php else: ?>
            <div class="stat-change positive">
                <i class="fas fa-check"></i> All good
            </div>
            <?php endif; ?>
        </div>
        
        <div class="juice-stat-card <?php echo count($qualityIssues) > 0 ? 'danger' : 'success'; ?>" data-stat="quality_issues">
            <i class="fas fa-clipboard-check stat-icon"></i>
            <div class="stat-number"><?php echo count($qualityIssues); ?></div>
            <div class="stat-label">Quality Issues (7 days)</div>
            <?php if (count($qualityIssues) > 0): ?>
            <div class="stat-change negative">
                <i class="fas fa-exclamation"></i> Action needed
            </div>
            <?php else: ?>
            <div class="stat-change positive">
                <i class="fas fa-check"></i> No issues
            </div>
            <?php endif; ?>
        </div>
        
        <div class="juice-stat-card <?php echo count($expiringBatches) > 0 ? 'warning' : 'success'; ?>" data-stat="expiring_batches">
            <i class="fas fa-calendar-exclamation stat-icon"></i>
            <div class="stat-number"><?php echo count($expiringBatches); ?></div>
            <div class="stat-label">Expiring Batches (30 days)</div>
            <?php if (count($expiringBatches) > 0): ?>
            <div class="stat-change negative">
                <i class="fas fa-clock"></i> Monitor closely
            </div>
            <?php else: ?>
            <div class="stat-change positive">
                <i class="fas fa-check"></i> All fresh
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Transfers -->
    <div class="row mt-4">
        <div class="col-lg-8">
            <div class="juice-table-wrapper">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history"></i> Recent Transfers
                    </h5>
                    <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_list.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-list"></i> View All
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table class="juice-table">
                        <thead>
                            <tr>
                                <th>Transfer ID</th>
                                <th>From → To</th>
                                <th>Items</th>
                                <th>Volume</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransfers)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle"></i> No recent transfers found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentTransfers as $transfer): ?>
                            <tr class="transfer-row" data-transfer-id="<?php echo $transfer['id']; ?>">
                                <td class="transfer-id">#<?php echo str_pad($transfer['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div class="outlet-name"><?php echo htmlspecialchars($transfer['from_outlet']); ?></div>
                                    <i class="fas fa-arrow-right text-muted mx-2"></i>
                                    <div class="outlet-name"><?php echo htmlspecialchars($transfer['to_outlet']); ?></div>
                                </td>
                                <td class="text-center"><?php echo $transfer['item_count']; ?></td>
                                <td class="volume-cell"><?php echo $templateController->formatVolume($transfer['total_volume_ml']); ?></td>
                                <td class="volume-cell">$<?php echo number_format($transfer['total_value'], 2); ?></td>
                                <td class="status-cell">
                                    <?php echo $templateController->renderStatusBadge($transfer['status']); ?>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($transfer['created_at'])); ?>
                                    <div class="text-muted small"><?php echo date('g:i A', strtotime($transfer['created_at'])); ?></div>
                                </td>
                                <td class="actions-cell">
                                    <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_view.php?id=<?php echo $transfer['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($transfer['status'] === 'pending'): ?>
                                    <button class="btn btn-sm btn-outline-success juice-action-btn" 
                                            data-action="approve" data-transfer-id="<?php echo $transfer['id']; ?>" 
                                            title="Approve Transfer">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Low Stock Alerts -->
            <?php if (!empty($lowStockAlerts)): ?>
            <div class="juice-table-wrapper mb-4">
                <div class="card-header">
                    <h6 class="mb-0 text-warning">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($lowStockAlerts as $alert): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="font-weight-bold"><?php echo htmlspecialchars($alert['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($alert['outlet_name']); ?></div>
                                <?php if ($alert['nicotine_strength']): ?>
                                <span class="badge badge-secondary badge-sm"><?php echo $alert['nicotine_strength']; ?>mg</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <div class="font-weight-bold text-warning">
                                    <?php echo $templateController->formatVolume($alert['quantity_ml']); ?>
                                </div>
                                <div class="text-muted small"><?php echo round($alert['stock_percentage']); ?>% remaining</div>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning" 
                                 style="width: <?php echo min(100, $alert['stock_percentage']); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quality Issues -->
            <?php if (!empty($qualityIssues)): ?>
            <div class="juice-table-wrapper mb-4">
                <div class="card-header">
                    <h6 class="mb-0 text-danger">
                        <i class="fas fa-clipboard-check"></i> Quality Issues
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($qualityIssues as $issue): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="font-weight-bold"><?php echo htmlspecialchars($issue['product_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($issue['outlet_name']); ?></div>
                                <div class="text-danger small mt-1">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo htmlspecialchars($issue['issue_type']); ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="small text-muted">
                                    <?php echo date('M j', strtotime($issue['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($issue['notes']): ?>
                        <div class="mt-2 small text-muted">
                            <?php echo htmlspecialchars(substr($issue['notes'], 0, 100)); ?>
                            <?php if (strlen($issue['notes']) > 100): ?>...<?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Expiring Batches -->
            <?php if (!empty($expiringBatches)): ?>
            <div class="juice-table-wrapper">
                <div class="card-header">
                    <h6 class="mb-0 text-warning">
                        <i class="fas fa-calendar-exclamation"></i> Expiring Batches
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($expiringBatches as $batch): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="font-weight-bold"><?php echo htmlspecialchars($batch['product_name']); ?></div>
                                <div class="text-muted small">Batch #<?php echo htmlspecialchars($batch['batch_number']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($batch['outlet_name']); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-weight-bold <?php echo $batch['days_until_expiry'] <= 7 ? 'text-danger' : 'text-warning'; ?>">
                                    <?php echo $batch['days_until_expiry']; ?> days
                                </div>
                                <div class="small text-muted">
                                    <?php echo date('M j, Y', strtotime($batch['expiry_date'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <?php if ($batch['days_until_expiry'] <= 7): ?>
                            <span class="badge badge-danger badge-sm">Expires Soon</span>
                            <?php else: ?>
                            <span class="badge badge-warning badge-sm">Monitor</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="juice-form-container">
                <h5 class="mb-3">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
                
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_create.php" 
                           class="juice-btn btn-primary w-100">
                            <i class="fas fa-plus-circle"></i>
                            New Transfer
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_receive.php" 
                           class="juice-btn btn-success w-100">
                            <i class="fas fa-check-circle"></i>
                            Receive Transfer
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_quality.php" 
                           class="juice-btn btn-warning w-100">
                            <i class="fas fa-clipboard-check"></i>
                            Quality Control
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_reports.php" 
                           class="juice-btn btn-secondary w-100">
                            <i class="fas fa-chart-line"></i>
                            Generate Report
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Real-time update indicator -->
<div class="fixed-bottom p-3">
    <div class="text-right">
        <small class="text-muted last-updated">
            Last updated: <?php echo date('g:i:s A'); ?>
        </small>
    </div>
</div>

<script>
// Pass configuration to JavaScript
window.currentOutletId = <?php echo isset($_SESSION['current_outlet_id']) ? $_SESSION['current_outlet_id'] : 'null'; ?>;

// Initialize dashboard-specific functionality
$(document).ready(function() {
    // Enable real-time updates
    JuiceTransfer.startAutoRefresh();
    
    // Handle quick actions
    $('.juice-action-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const action = $btn.data('action');
        const transferId = $btn.data('transfer-id');
        
        if (action === 'approve') {
            if (confirm('Are you sure you want to approve this transfer?')) {
                JuiceTransfer.updateTransferStatus(transferId, 'approved');
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();

// Additional CSS for dashboard
$additionalCSS = [
    'https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css'
];

// Additional JS for dashboard
$additionalJS = [
    'https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js'
];

// Render the page
echo $templateController->renderJuiceTransferPage(
    'Dashboard',
    $content,
    'dashboard',
    $additionalCSS,
    $additionalJS
);
?>
