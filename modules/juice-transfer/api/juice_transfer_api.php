<?php
/**
 * Juice Transfer API Backend
 * RESTful API for juice transfer management system
 * Location: /juice-transfer/api/juice_transfer_api.php
 * 
 * @author CIS System
 * @version 2.0
 * @requires RockSolidVendQueue, ConsolidatedVendAPI, MySQL 8.0+
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/connection.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/utilities/ConsolidatedVendAPI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/cron/RockSolidVendQueue.php';

/**
 * Juice Transfer API Controller
 * Comprehensive RESTful API for juice transfer operations
 */
class JuiceTransferAPI {
    private $db;
    private $vendAPI;
    private $queue;
    private $requestMethod;
    private $endpoint;
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
        $this->vendAPI = ConsolidatedVendAPI::getInstance();
        $this->queue = new RockSolidVendQueue($pdo);
        
        // Set JSON response headers
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        
        // Handle preflight OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->endpoint = $_GET['endpoint'] ?? '';
        
        // Simple authentication (extend with proper token validation)
        if (!$this->authenticate()) {
            $this->sendResponse(['error' => 'Authentication required'], 401);
            exit;
        }
    }
    
    /**
     * Simple authentication check
     */
    private function authenticate() {
        // For development - accept test token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($authHeader === 'Bearer test-token') {
            return true;
        }
        
        // Check session authentication
        session_start();
        return isset($_SESSION['user_id']) && $_SESSION['user_role'];
    }
    
    /**
     * Main API router
     */
    public function processRequest() {
        try {
            switch ($this->endpoint) {
                case 'dashboard':
                    return $this->handleDashboard();
                case 'transfers':
                    return $this->handleTransfers();
                case 'products':
                    return $this->handleProducts();
                case 'batches':
                    return $this->handleBatches();
                case 'inventory':
                    return $this->handleInventory();
                case 'quality-control':
                    return $this->handleQualityControl();
                case 'reports':
                    return $this->handleReports();
                default:
                    $this->sendResponse(['error' => 'Endpoint not found'], 404);
            }
        } catch (Exception $e) {
            error_log("Juice Transfer API Error: " . $e->getMessage());
            $this->sendResponse(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Dashboard data endpoint
     */
    private function handleDashboard() {
        if ($this->requestMethod !== 'GET') {
            $this->sendResponse(['error' => 'Method not allowed'], 405);
            return;
        }
        
        $dashboardData = [
            'stats' => $this->getDashboardStats(),
            'recent_transfers' => $this->getRecentTransfers(),
            'low_stock_alerts' => $this->getLowStockAlerts(),
            'quality_issues' => $this->getQualityIssues(),
            'system_health' => $this->getSystemHealth()
        ];
        
        $this->sendResponse($dashboardData);
    }
    
    /**
     * Get dashboard statistics
     */
    private function getDashboardStats() {
        try {
            $stats = [];
            
            // Total transfers this month
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM juice_transfers 
                WHERE MONTH(created_at) = MONTH(NOW()) 
                AND YEAR(created_at) = YEAR(NOW())
            ");
            $stats['total_transfers_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Pending transfers
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM juice_transfers 
                WHERE status = 'pending'
            ");
            $stats['pending_transfers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // In transit
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM juice_transfers 
                WHERE status = 'in_transit'
            ");
            $stats['in_transit'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Low stock items
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM juice_inventory ji
                JOIN juice_products jp ON ji.product_id = jp.id
                WHERE ji.quantity_ml <= jp.reorder_level
                AND jp.status = 'active'
            ");
            $stats['low_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Quality issues (last 7 days)
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM juice_quality_checks 
                WHERE status = 'failed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stats['quality_issues'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Total value in transit
            $stmt = $this->db->query("
                SELECT COALESCE(SUM(jti.quantity_ml * jp.cost_per_ml), 0) as total_value
                FROM juice_transfers jt
                JOIN juice_transfer_items jti ON jt.id = jti.transfer_id
                JOIN juice_products jp ON jti.product_id = jp.id
                WHERE jt.status = 'in_transit'
            ");
            $stats['transit_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting dashboard stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent transfers
     */
    private function getRecentTransfers($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    jt.*,
                    vo_from.name as from_outlet,
                    vo_to.name as to_outlet,
                    COUNT(jti.id) as item_count,
                    SUM(jti.quantity_ml) as total_volume_ml
                FROM juice_transfers jt
                LEFT JOIN vend_outlets vo_from ON jt.from_outlet_id = vo_from.id
                LEFT JOIN vend_outlets vo_to ON jt.to_outlet_id = vo_to.id
                LEFT JOIN juice_transfer_items jti ON jt.id = jti.transfer_id
                GROUP BY jt.id
                ORDER BY jt.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting recent transfers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get low stock alerts
     */
    private function getLowStockAlerts($limit = 5) {
        try {
            $stmt = $this->db->prepare("
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
                ORDER BY stock_percentage ASC, ji.last_updated ASC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting low stock alerts: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get quality issues
     */
    private function getQualityIssues($limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    jqc.*,
                    jp.name as product_name,
                    jp.nicotine_strength,
                    jp.vg_ratio,
                    vo.name as outlet_name
                FROM juice_quality_checks jqc
                JOIN juice_products jp ON jqc.product_id = jp.id
                LEFT JOIN vend_outlets vo ON jqc.outlet_id = vo.id
                WHERE jqc.status = 'failed'
                ORDER BY jqc.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting quality issues: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Handle transfer operations
     */
    private function handleTransfers() {
        switch ($this->requestMethod) {
            case 'GET':
                if (isset($_GET['id'])) {
                    return $this->getTransfer($_GET['id']);
                }
                return $this->getAllTransfers();
                
            case 'POST':
                return $this->createTransfer();
                
            case 'PUT':
                if (isset($_GET['id'])) {
                    return $this->updateTransfer($_GET['id']);
                }
                $this->sendResponse(['error' => 'Transfer ID required'], 400);
                break;
                
            case 'DELETE':
                if (isset($_GET['id'])) {
                    return $this->deleteTransfer($_GET['id']);
                }
                $this->sendResponse(['error' => 'Transfer ID required'], 400);
                break;
                
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get single transfer with items
     */
    private function getTransfer($transferId) {
        try {
            // Get transfer details
            $stmt = $this->db->prepare("
                SELECT 
                    jt.*,
                    vo_from.name as from_outlet,
                    vo_from.address as from_address,
                    vo_to.name as to_outlet,
                    vo_to.address as to_address
                FROM juice_transfers jt
                LEFT JOIN vend_outlets vo_from ON jt.from_outlet_id = vo_from.id
                LEFT JOIN vend_outlets vo_to ON jt.to_outlet_id = vo_to.id
                WHERE jt.id = ?
            ");
            
            $stmt->execute([$transferId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transfer) {
                $this->sendResponse(['error' => 'Transfer not found'], 404);
                return;
            }
            
            // Get transfer items
            $stmt = $this->db->prepare("
                SELECT 
                    jti.*,
                    jp.name as product_name,
                    jp.flavor_profile,
                    jp.nicotine_strength,
                    jp.vg_ratio,
                    jp.cost_per_ml,
                    jb.batch_number,
                    jb.expiry_date,
                    jb.production_date
                FROM juice_transfer_items jti
                JOIN juice_products jp ON jti.product_id = jp.id
                LEFT JOIN juice_batches jb ON jti.batch_id = jb.id
                WHERE jti.transfer_id = ?
                ORDER BY jp.name, jb.batch_number
            ");
            
            $stmt->execute([$transferId]);
            $transfer['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $transfer['total_items'] = count($transfer['items']);
            $transfer['total_volume_ml'] = array_sum(array_column($transfer['items'], 'quantity_ml'));
            $transfer['total_value'] = array_sum(array_map(function($item) {
                return $item['quantity_ml'] * $item['cost_per_ml'];
            }, $transfer['items']));
            
            $this->sendResponse(['transfer' => $transfer]);
        } catch (Exception $e) {
            error_log("Error getting transfer: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to retrieve transfer'], 500);
        }
    }
    
    /**
     * Get all transfers with filtering
     */
    private function getAllTransfers() {
        try {
            $whereClause = '1=1';
            $params = [];
            
            // Apply filters
            if (isset($_GET['status'])) {
                $whereClause .= ' AND jt.status = ?';
                $params[] = $_GET['status'];
            }
            
            if (isset($_GET['outlet_id'])) {
                $whereClause .= ' AND (jt.from_outlet_id = ? OR jt.to_outlet_id = ?)';
                $params[] = $_GET['outlet_id'];
                $params[] = $_GET['outlet_id'];
            }
            
            if (isset($_GET['date_from'])) {
                $whereClause .= ' AND jt.created_at >= ?';
                $params[] = $_GET['date_from'];
            }
            
            if (isset($_GET['date_to'])) {
                $whereClause .= ' AND jt.created_at <= ?';
                $params[] = $_GET['date_to'] . ' 23:59:59';
            }
            
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $stmt = $this->db->prepare("
                SELECT 
                    jt.*,
                    vo_from.name as from_outlet,
                    vo_to.name as to_outlet,
                    COUNT(jti.id) as item_count,
                    SUM(jti.quantity_ml) as total_volume_ml
                FROM juice_transfers jt
                LEFT JOIN vend_outlets vo_from ON jt.from_outlet_id = vo_from.id
                LEFT JOIN vend_outlets vo_to ON jt.to_outlet_id = vo_to.id
                LEFT JOIN juice_transfer_items jti ON jt.id = jti.transfer_id
                WHERE $whereClause
                GROUP BY jt.id
                ORDER BY jt.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            
            $stmt->execute($params);
            $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendResponse(['transfers' => $transfers]);
        } catch (Exception $e) {
            error_log("Error getting transfers: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to retrieve transfers'], 500);
        }
    }
    
    /**
     * Create new transfer
     */
    private function createTransfer() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required = ['from_outlet_id', 'to_outlet_id', 'items'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->sendResponse(['error' => "Field '$field' is required"], 400);
                    return;
                }
            }
            
            if (empty($data['items']) || !is_array($data['items'])) {
                $this->sendResponse(['error' => 'Transfer must include items'], 400);
                return;
            }
            
            $this->db->beginTransaction();
            
            try {
                // Create transfer record
                $stmt = $this->db->prepare("
                    INSERT INTO juice_transfers (
                        from_outlet_id, to_outlet_id, transfer_type, priority, 
                        shipping_method, expected_delivery, notes, 
                        created_by, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $data['from_outlet_id'],
                    $data['to_outlet_id'],
                    $data['transfer_type'] ?? 'standard',
                    $data['priority'] ?? 'normal',
                    $data['shipping_method'] ?? 'courier',
                    $data['expected_delivery'] ?? null,
                    $data['notes'] ?? null,
                    $_SESSION['user_id'] ?? 1
                ]);
                
                $transferId = $this->db->lastInsertId();
                
                // Add transfer items
                foreach ($data['items'] as $item) {
                    if (empty($item['product_id']) || empty($item['quantity_ml'])) {
                        throw new Exception('Invalid item data');
                    }
                    
                    $stmt = $this->db->prepare("
                        INSERT INTO juice_transfer_items (
                            transfer_id, product_id, batch_id, quantity_ml, 
                            quality_grade, notes
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $transferId,
                        $item['product_id'],
                        $item['batch_id'] ?? null,
                        $item['quantity_ml'],
                        $item['quality_grade'] ?? 'A',
                        $item['notes'] ?? null
                    ]);
                    
                    // Update source outlet inventory
                    $this->updateInventory(
                        $data['from_outlet_id'],
                        $item['product_id'],
                        -$item['quantity_ml'],
                        $item['batch_id'] ?? null
                    );
                }
                
                // Queue notification task
                $this->queue->addTask([
                    'type' => 'juice_transfer_notification',
                    'transfer_id' => $transferId,
                    'action' => 'created'
                ]);
                
                $this->db->commit();
                
                $this->sendResponse([
                    'message' => 'Transfer created successfully',
                    'transfer_id' => $transferId
                ], 201);
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            error_log("Error creating transfer: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to create transfer: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Update inventory levels
     */
    private function updateInventory($outletId, $productId, $quantityChange, $batchId = null) {
        try {
            // Check if inventory record exists
            $stmt = $this->db->prepare("
                SELECT id, quantity_ml 
                FROM juice_inventory 
                WHERE outlet_id = ? AND product_id = ?
            ");
            
            $stmt->execute([$outletId, $productId]);
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($inventory) {
                // Update existing inventory
                $newQuantity = max(0, $inventory['quantity_ml'] + $quantityChange);
                
                $stmt = $this->db->prepare("
                    UPDATE juice_inventory 
                    SET quantity_ml = ?, last_updated = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([$newQuantity, $inventory['id']]);
            } else if ($quantityChange > 0) {
                // Create new inventory record for positive additions
                $stmt = $this->db->prepare("
                    INSERT INTO juice_inventory (
                        outlet_id, product_id, quantity_ml, last_updated
                    ) VALUES (?, ?, ?, NOW())
                ");
                
                $stmt->execute([$outletId, $productId, $quantityChange]);
            }
            
        } catch (Exception $e) {
            error_log("Error updating inventory: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle product operations
     */
    private function handleProducts() {
        switch ($this->requestMethod) {
            case 'GET':
                return $this->getProducts();
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get products with filtering
     */
    private function getProducts() {
        try {
            $whereClause = "jp.status = 'active'";
            $params = [];
            
            if (isset($_GET['outlet_id'])) {
                $whereClause .= ' AND ji.outlet_id = ?';
                $params[] = $_GET['outlet_id'];
            }
            
            if (isset($_GET['category'])) {
                $whereClause .= ' AND jp.category = ?';
                $params[] = $_GET['category'];
            }
            
            if (isset($_GET['low_stock_only']) && $_GET['low_stock_only'] === 'true') {
                $whereClause .= ' AND ji.quantity_ml <= jp.reorder_level';
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    jp.*,
                    ji.quantity_ml,
                    ji.last_updated as inventory_updated,
                    vo.name as outlet_name,
                    COALESCE(ji.quantity_ml, 0) as current_stock,
                    CASE 
                        WHEN ji.quantity_ml <= jp.reorder_level THEN 'low'
                        WHEN ji.quantity_ml <= (jp.reorder_level * 2) THEN 'medium'
                        ELSE 'good'
                    END as stock_status
                FROM juice_products jp
                LEFT JOIN juice_inventory ji ON jp.id = ji.product_id
                LEFT JOIN vend_outlets vo ON ji.outlet_id = vo.id
                WHERE $whereClause
                ORDER BY jp.name, vo.name
            ");
            
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendResponse(['products' => $products]);
        } catch (Exception $e) {
            error_log("Error getting products: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to retrieve products'], 500);
        }
    }
    
    /**
     * Handle batch operations
     */
    private function handleBatches() {
        switch ($this->requestMethod) {
            case 'GET':
                return $this->getBatches();
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get batches with filtering
     */
    private function getBatches() {
        try {
            $whereClause = '1=1';
            $params = [];
            
            if (isset($_GET['product_id'])) {
                $whereClause .= ' AND jb.product_id = ?';
                $params[] = $_GET['product_id'];
            }
            
            if (isset($_GET['outlet_id'])) {
                $whereClause .= ' AND jb.current_outlet_id = ?';
                $params[] = $_GET['outlet_id'];
            }
            
            if (isset($_GET['expiring_soon']) && $_GET['expiring_soon'] === 'true') {
                $whereClause .= ' AND jb.expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)';
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    jb.*,
                    jp.name as product_name,
                    jp.flavor_profile,
                    jp.nicotine_strength,
                    vo.name as outlet_name,
                    DATEDIFF(jb.expiry_date, NOW()) as days_until_expiry
                FROM juice_batches jb
                JOIN juice_products jp ON jb.product_id = jp.id
                LEFT JOIN vend_outlets vo ON jb.current_outlet_id = vo.id
                WHERE $whereClause
                ORDER BY jb.expiry_date ASC, jp.name
            ");
            
            $stmt->execute($params);
            $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendResponse(['batches' => $batches]);
        } catch (Exception $e) {
            error_log("Error getting batches: " . $e->getMessage());
            $this->sendResponse(['error' => 'Failed to retrieve batches'], 500);
        }
    }
    
    /**
     * System health check
     */
    private function getSystemHealth() {
        try {
            return [
                'database' => $this->checkDatabaseHealth(),
                'vend_api' => $this->checkVendHealth(),
                'queue_system' => $this->checkQueueHealth(),
                'storage_space' => $this->checkStorageSpace()
            ];
        } catch (Exception $e) {
            error_log("System health check error: " . $e->getMessage());
            return [
                'database' => false,
                'vend_api' => false,
                'queue_system' => false,
                'storage_space' => false
            ];
        }
    }
    
    private function checkDatabaseHealth() {
        try {
            $stmt = $this->db->query("SELECT 1");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function checkVendHealth() {
        try {
            return $this->vendAPI->healthCheck();
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function checkQueueHealth() {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM vend_queue_v2 WHERE status = 'pending'");
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function checkStorageSpace() {
        try {
            $freeSpace = disk_free_space('/');
            $totalSpace = disk_total_space('/');
            $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            return $usedPercent < 90; // Alert if more than 90% used
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        
        if ($statusCode >= 400) {
            $response = ['error' => $data['error'] ?? 'An error occurred'];
        } else {
            $response = $data;
        }
        
        $response['timestamp'] = date('c');
        $response['status'] = $statusCode;
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}

// Initialize and process API request
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || isset($_GET['endpoint'])) {
    $api = new JuiceTransferAPI();
    $api->processRequest();
} else {
    // API documentation endpoint
    header('Content-Type: application/json');
    echo json_encode([
        'name' => 'Juice Transfer API',
        'version' => '2.0',
        'description' => 'RESTful API for juice transfer management',
        'endpoints' => [
            'GET /dashboard' => 'Get dashboard data and statistics',
            'GET /transfers' => 'Get all transfers with optional filtering',
            'GET /transfers?id={id}' => 'Get specific transfer details',
            'POST /transfers' => 'Create new transfer',
            'PUT /transfers?id={id}' => 'Update existing transfer',
            'DELETE /transfers?id={id}' => 'Delete transfer',
            'GET /products' => 'Get products with inventory levels',
            'GET /batches' => 'Get batch information',
            'GET /inventory' => 'Get inventory levels',
            'GET /quality-control' => 'Get quality control data',
            'GET /reports' => 'Generate various reports'
        ],
        'authentication' => 'Bearer token or session-based',
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}
?>
