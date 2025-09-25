<?php
/**
 * Juice Transfer Template Controller
 * Extended CIS template controller for juice transfer system
 * Location: /juice-transfer/core/JuiceTransferTemplateController.php
 * 
 * @author CIS System
 * @version 2.0
 * @extends CISTemplateController
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/utilities/CISTemplateController.php';

/**
 * JuiceTransferTemplateController
 * Specialized template controller for juice transfer management with advanced features
 */
class JuiceTransferTemplateController extends CISTemplateController {
    
    protected $juiceNavigation = [
        'dashboard' => [
            'title' => 'Dashboard',
            'url' => '/juice-transfer/juice_transfer_dashboard.php',
            'icon' => 'fas fa-tachometer-alt',
            'badge' => null
        ],
        'create' => [
            'title' => 'New Transfer',
            'url' => '/juice-transfer/juice_transfer_create.php',
            'icon' => 'fas fa-plus-circle',
            'badge' => null
        ],
        'transfers' => [
            'title' => 'All Transfers',
            'url' => '/juice-transfer/juice_transfer_list.php',
            'icon' => 'fas fa-exchange-alt',
            'badge' => null
        ],
        'receive' => [
            'title' => 'Receive Transfer',
            'url' => '/juice-transfer/juice_transfer_receive.php',
            'icon' => 'fas fa-check-circle',
            'badge' => null
        ],
        'quality' => [
            'title' => 'Quality Control',
            'url' => '/juice-transfer/juice_transfer_quality.php',
            'icon' => 'fas fa-clipboard-check',
            'badge' => null
        ],
        'batches' => [
            'title' => 'Batch Management',
            'url' => '/juice-transfer/juice_transfer_batches.php',
            'icon' => 'fas fa-boxes',
            'badge' => null
        ],
        'reports' => [
            'title' => 'Reports',
            'url' => '/juice-transfer/juice_transfer_reports.php',
            'icon' => 'fas fa-chart-line',
            'badge' => null
        ]
    ];
    
    /**
     * Render juice transfer page with navigation and branding
     */
    public function renderJuiceTransferPage($title, $content, $activeNav = null, $additionalCSS = [], $additionalJS = []) {
        // Add juice transfer specific assets
        $defaultCSS = [
            '/juice-transfer/assets/css/juice-transfer.css'
        ];
        
        $defaultJS = [
            '/juice-transfer/assets/js/juice-transfer.js'
        ];
        
        $allCSS = array_merge($defaultCSS, $additionalCSS);
        $allJS = array_merge($defaultJS, $additionalJS);
        
        // Build navigation with active state
        $navigation = $this->buildJuiceNavigation($activeNav);
        
        // Enhanced page data for juice transfers
        $pageData = [
            'system_title' => 'Juice Transfer Management',
            'page_title' => $title,
            'navigation' => $navigation,
            'content' => $content,
            'css_files' => $allCSS,
            'js_files' => $allJS,
            'body_class' => 'juice-transfer-system',
            'api_base' => '/juice-transfer/api/juice_transfer_api.php',
            'current_outlet' => $this->getCurrentOutlet(),
            'user_permissions' => $this->getUserPermissions(),
            'system_alerts' => $this->getSystemAlerts()
        ];
        
        return $this->renderPage($pageData);
    }
    
    /**
     * Build juice transfer navigation with badges and permissions
     */
    private function buildJuiceNavigation($activeNav = null) {
        $navigation = [];
        $permissions = $this->getUserPermissions();
        
        foreach ($this->juiceNavigation as $key => $nav) {
            // Check permissions
            if (!$this->hasPermission($key, $permissions)) {
                continue;
            }
            
            $navItem = $nav;
            $navItem['active'] = ($key === $activeNav);
            $navItem['badge'] = $this->getNavigationBadge($key);
            
            $navigation[$key] = $navItem;
        }
        
        return $navigation;
    }
    
    /**
     * Get dynamic navigation badges
     */
    private function getNavigationBadge($navKey) {
        try {
            switch ($navKey) {
                case 'dashboard':
                    return $this->getPendingTransfersCount();
                case 'receive':
                    return $this->getInTransitCount();
                case 'quality':
                    return $this->getQualityIssuesCount();
                default:
                    return null;
            }
        } catch (Exception $e) {
            error_log("Error getting navigation badge for $navKey: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get pending transfers count for badge
     */
    private function getPendingTransfersCount() {
        try {
            global $pdo;
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM juice_transfers 
                WHERE status = 'pending'
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = $result['count'] ?? 0;
            return $count > 0 ? [
                'text' => $count,
                'class' => 'badge-warning'
            ] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get in-transit transfers count
     */
    private function getInTransitCount() {
        try {
            global $pdo;
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM juice_transfers 
                WHERE status = 'in_transit'
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = $result['count'] ?? 0;
            return $count > 0 ? [
                'text' => $count,
                'class' => 'badge-info'
            ] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get quality issues count
     */
    private function getQualityIssuesCount() {
        try {
            global $pdo;
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM juice_quality_checks 
                WHERE status = 'failed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $count = $result['count'] ?? 0;
            return $count > 0 ? [
                'text' => $count,
                'class' => 'badge-danger'
            ] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get current user's outlet context
     */
    private function getCurrentOutlet() {
        if (isset($_SESSION['current_outlet_id'])) {
            try {
                global $pdo;
                $stmt = $pdo->prepare("
                    SELECT id, name, address, phone 
                    FROM vend_outlets 
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['current_outlet_id']]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                error_log("Error getting current outlet: " . $e->getMessage());
            }
        }
        return null;
    }
    
    /**
     * Get user permissions for juice transfer system
     */
    private function getUserPermissions() {
        $permissions = [
            'view_dashboard' => true,
            'create_transfer' => false,
            'approve_transfer' => false,
            'receive_transfer' => false,
            'quality_control' => false,
            'view_reports' => false,
            'manage_batches' => false
        ];
        
        if (isset($_SESSION['user_role'])) {
            switch ($_SESSION['user_role']) {
                case 'admin':
                case 'manager':
                    $permissions = array_map(function() { return true; }, $permissions);
                    break;
                case 'staff':
                    $permissions['create_transfer'] = true;
                    $permissions['receive_transfer'] = true;
                    $permissions['view_reports'] = true;
                    break;
                case 'quality_control':
                    $permissions['quality_control'] = true;
                    $permissions['view_reports'] = true;
                    break;
            }
        }
        
        return $permissions;
    }
    
    /**
     * Check if user has specific permission
     */
    private function hasPermission($action, $permissions = null) {
        if ($permissions === null) {
            $permissions = $this->getUserPermissions();
        }
        
        $permissionMap = [
            'dashboard' => 'view_dashboard',
            'create' => 'create_transfer',
            'transfers' => 'view_dashboard',
            'receive' => 'receive_transfer',
            'quality' => 'quality_control',
            'batches' => 'manage_batches',
            'reports' => 'view_reports'
        ];
        
        $permission = $permissionMap[$action] ?? 'view_dashboard';
        return $permissions[$permission] ?? false;
    }
    
    /**
     * Get system-wide alerts for juice transfers
     */
    private function getSystemAlerts() {
        $alerts = [];
        
        try {
            global $pdo;
            
            // Check for low stock alerts
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM juice_inventory ji
                JOIN juice_products jp ON ji.product_id = jp.id
                WHERE ji.quantity_ml <= jp.reorder_level
                AND jp.status = 'active'
            ");
            $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($lowStock > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "$lowStock products are running low on stock",
                    'link' => '/juice-transfer/juice_transfer_dashboard.php#low-stock'
                ];
            }
            
            // Check for expiring batches
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM juice_batches 
                WHERE expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
                AND expiry_date > NOW()
                AND status = 'active'
            ");
            $expiring = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($expiring > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "$expiring batches expire within 30 days",
                    'link' => '/juice-transfer/juice_transfer_batches.php#expiring'
                ];
            }
            
            // Check for failed quality checks
            $stmt = $pdo->query("
                SELECT COUNT(*) as count 
                FROM juice_quality_checks 
                WHERE status = 'failed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $qualityIssues = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($qualityIssues > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "$qualityIssues quality control failures this week",
                    'link' => '/juice-transfer/juice_transfer_quality.php'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error getting system alerts: " . $e->getMessage());
        }
        
        return $alerts;
    }
    
    /**
     * Render juice transfer form components
     */
    public function renderOutletSelector($selectedOutletId = null, $excludeOutletId = null, $name = 'outlet_id', $required = true) {
        try {
            global $pdo;
            
            $whereClause = "status = 'active'";
            $params = [];
            
            if ($excludeOutletId) {
                $whereClause .= " AND id != ?";
                $params[] = $excludeOutletId;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, name, address 
                FROM vend_outlets 
                WHERE $whereClause
                ORDER BY name
            ");
            $stmt->execute($params);
            $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $html = '<select name="' . htmlspecialchars($name) . '" class="form-control juice-outlet-selector"';
            if ($required) $html .= ' required';
            $html .= '>';
            $html .= '<option value="">Select Outlet...</option>';
            
            foreach ($outlets as $outlet) {
                $selected = ($outlet['id'] == $selectedOutletId) ? ' selected' : '';
                $html .= '<option value="' . $outlet['id'] . '"' . $selected . '>';
                $html .= htmlspecialchars($outlet['name']);
                if ($outlet['address']) {
                    $html .= ' - ' . htmlspecialchars($outlet['address']);
                }
                $html .= '</option>';
            }
            
            $html .= '</select>';
            return $html;
            
        } catch (Exception $e) {
            error_log("Error rendering outlet selector: " . $e->getMessage());
            return '<select name="' . htmlspecialchars($name) . '" class="form-control" disabled><option value="">Error loading outlets</option></select>';
        }
    }
    
    /**
     * Render product selector with stock levels
     */
    public function renderProductSelector($outletId = null, $selectedProductId = null, $name = 'product_id') {
        try {
            global $pdo;
            
            $whereClause = "jp.status = 'active'";
            $params = [];
            
            if ($outletId) {
                $whereClause .= " AND ji.outlet_id = ?";
                $params[] = $outletId;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    jp.*,
                    COALESCE(ji.quantity_ml, 0) as current_stock,
                    CASE 
                        WHEN ji.quantity_ml <= jp.reorder_level THEN 'low'
                        WHEN ji.quantity_ml <= (jp.reorder_level * 2) THEN 'medium'
                        ELSE 'good'
                    END as stock_status
                FROM juice_products jp
                LEFT JOIN juice_inventory ji ON jp.id = ji.product_id
                WHERE $whereClause
                ORDER BY jp.name
            ");
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $html = '<select name="' . htmlspecialchars($name) . '" class="form-control juice-product-selector">';
            $html .= '<option value="">Select Product...</option>';
            
            foreach ($products as $product) {
                $selected = ($product['id'] == $selectedProductId) ? ' selected' : '';
                $stockClass = 'stock-' . $product['stock_status'];
                
                $html .= '<option value="' . $product['id'] . '"' . $selected . ' class="' . $stockClass . '" ';
                $html .= 'data-stock="' . $product['current_stock'] . '" ';
                $html .= 'data-nicotine="' . $product['nicotine_strength'] . '" ';
                $html .= 'data-vg="' . $product['vg_ratio'] . '">';
                
                $html .= htmlspecialchars($product['name']);
                $html .= ' (' . number_format($product['current_stock']) . 'ml)';
                
                if ($product['nicotine_strength']) {
                    $html .= ' - ' . $product['nicotine_strength'] . 'mg';
                }
                
                $html .= '</option>';
            }
            
            $html .= '</select>';
            return $html;
            
        } catch (Exception $e) {
            error_log("Error rendering product selector: " . $e->getMessage());
            return '<select name="' . htmlspecialchars($name) . '" class="form-control" disabled><option value="">Error loading products</option></select>';
        }
    }
    
    /**
     * Render status badge
     */
    public function renderStatusBadge($status, $showText = true) {
        $statusConfig = [
            'pending' => ['class' => 'badge-warning', 'icon' => 'fas fa-clock', 'text' => 'Pending'],
            'approved' => ['class' => 'badge-success', 'icon' => 'fas fa-check', 'text' => 'Approved'],
            'in_transit' => ['class' => 'badge-info', 'icon' => 'fas fa-truck', 'text' => 'In Transit'],
            'delivered' => ['class' => 'badge-primary', 'icon' => 'fas fa-box', 'text' => 'Delivered'],
            'received' => ['class' => 'badge-success', 'icon' => 'fas fa-check-circle', 'text' => 'Received'],
            'cancelled' => ['class' => 'badge-danger', 'icon' => 'fas fa-times', 'text' => 'Cancelled']
        ];
        
        $config = $statusConfig[$status] ?? ['class' => 'badge-secondary', 'icon' => 'fas fa-question', 'text' => ucfirst($status)];
        
        $html = '<span class="badge ' . $config['class'] . '">';
        $html .= '<i class="' . $config['icon'] . '"></i>';
        if ($showText) {
            $html .= ' ' . $config['text'];
        }
        $html .= '</span>';
        
        return $html;
    }
    
    /**
     * Format volume for display
     */
    public function formatVolume($volumeMl) {
        if ($volumeMl >= 1000) {
            return number_format($volumeMl / 1000, 1) . 'L';
        }
        return number_format($volumeMl) . 'ml';
    }
    
    /**
     * Get juice transfer statistics for display
     */
    public function getJuiceTransferStats($outletId = null) {
        try {
            global $pdo;
            
            $whereClause = '1=1';
            $params = [];
            
            if ($outletId) {
                $whereClause .= ' AND (from_outlet_id = ? OR to_outlet_id = ?)';
                $params[] = $outletId;
                $params[] = $outletId;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(
                        (SELECT SUM(quantity_ml) FROM juice_transfer_items WHERE transfer_id = jt.id)
                    ) as total_volume
                FROM juice_transfers jt
                WHERE $whereClause
                GROUP BY status
            ");
            
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [];
            foreach ($results as $result) {
                $stats[$result['status']] = [
                    'count' => $result['count'],
                    'volume' => $result['total_volume'] ?? 0
                ];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error getting juice transfer stats: " . $e->getMessage());
            return [];
        }
    }
}
?>
