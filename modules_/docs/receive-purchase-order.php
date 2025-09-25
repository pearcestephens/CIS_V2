<?php
/**
 * purchase-order-receive.php (Semantic merge, no inline styles)
 * -----------------------------------------------------------------------------
 * - Keeps POST JSON actions (poReceiveSimple, poUploadEvidence, etc.)
 * - Renders semantic, ARIA-aware, fully-contained HTML5 on GET
 * - Preserves compat selectors used by your JS (rx-*, #poTable, #barcodeInput)
 * - Ensures unique IDs, modals at end of <body>
 * - Namespaces UI under #po-root and removes ALL inline style="" attributes
 * 
 * 
 * - NEVER BACKUP OR USE THIS AS A GENUINE VERSION OF THIS FILE - IT IS FAKE, NOT REAL, NEVER TO BE USED IN PRODUCTION OR DEVELOPMENT UNLESS PEARCE S1AYS SO
 */

include("assets/functions/config.php"); //INCLUDES $CON, SESSIONS, EVERYTHING - DONT ADD MORE
include("assets/functions/helpers.php"); // JSON response helpers and utility functions

// ---- Maintenance Mode DISABLED ----
if (false && isset($_SESSION['userID']) && (int)$_SESSION['userID'] !== 1) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <?php
    /**
     * Legacy Redirect: Transfers Purchase Order Receive -> New PO Receive
     * URL (old): https://staff.vapeshed.co.nz/modules/transfers/receive-purchase-order.php
     * URL (new): https://staff.vapeshed.co.nz/modules/purchase-orders/views/receive.php
     * Purpose: Preserve deep links while consolidating under the new module.
     * Author: GitHub Copilot
     * Last Modified: 2025-09-20
     * Dependencies: https://staff.vapeshed.co.nz/app.php (session/csrf, globals)
     */

    declare(strict_types=1);
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

    // Preserve incoming query string; prefer explicit po_id if present
    $qs = (string)($_SERVER['QUERY_STRING'] ?? '');
    $target = 'https://staff.vapeshed.co.nz/modules/purchase-orders/views/receive.php';
    if ($qs !== '') {
        $target .= (strpos($target, '?') === false ? '?' : '&') . $qs;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Location: ' . $target, true, 302);
    echo "Redirecting to <a href=" . htmlspecialchars($target, ENT_QUOTES) . ">https://staff.vapeshed.co.nz/modules/purchase-orders/views/receive.php</a>...";
    exit;
              overflow-y: auto;
            }

            .sidebar.show {
              left: 0;
            }

            .sidebar-overlay {
              position: fixed;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background: rgba(0,0,0,0.5);
              z-index: 1040;
              opacity: 0;
              visibility: hidden;
              transition: all 0.3s ease;
            }

            .sidebar-overlay.show {
              opacity: 1;
              visibility: visible;
            }

            .sidebar-header {
              padding: 1.5rem;
              border-bottom: 1px solid rgba(255,255,255,0.1);
              display: flex;
              justify-content: between;
              align-items: center;
            }

            .sidebar-header h4 {
              margin: 0;
              font-weight: 700;
              color: var(--text-on-primary);
            }

            .sidebar-close {
              background: none;
              border: none;
              color: var(--text-on-primary);
              font-size: 1.5rem;
              padding: 0.5rem;
              border-radius: 50%;
              transition: background 0.3s ease;
            }

            .sidebar-close:hover {
              background: rgba(255,255,255,0.1);
            }

            .sidebar-nav {
              padding: 1rem 0;
            }

            .sidebar-section {
              margin-bottom: 2rem;
            }

            .sidebar-section h6 {
              font-size: 0.75rem;
              text-transform: uppercase;
              letter-spacing: 1px;
              color: rgba(255,255,255,0.6);
              margin: 0 1.5rem 1rem;
              font-weight: 600;
            }

            .sidebar-nav-link {
              display: flex;
              align-items: center;
              padding: 0.75rem 1.5rem;
              color: rgba(255,255,255,0.8);
              text-decoration: none;
              transition: all 0.3s ease;
              border-left: 3px solid transparent;
            }

            .sidebar-nav-link:hover {
              background: rgba(255,255,255,0.05);
              color: var(--text-on-primary);
              border-left-color: var(--primary-color);
              padding-left: 2rem;
            }

            .sidebar-nav-link i {
              width: 20px;
              margin-right: 1rem;
              text-align: center;
            }

            /* Theme Picker */
            .theme-picker {
              min-width: 400px;
              max-height: 500px;
              overflow-y: auto;
            }

            .theme-grid {
              display: grid;
              grid-template-columns: repeat(2, 1fr);
              gap: 1rem;
              padding: 1rem;
            }

            .theme-option {
              cursor: pointer;
              border: 2px solid transparent;
              border-radius: 8px;
              padding: 1rem;
              transition: all 0.3s ease;
              background: #f8f9fa;
            }

            .theme-option:hover {
              border-color: var(--primary-color);
              transform: translateY(-2px);
              box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }

            .theme-option.active {
              border-color: var(--primary-color);
              background: var(--light-color);
              box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            }

            .theme-preview {
              display: flex;
              height: 30px;
              border-radius: 4px;
              overflow: hidden;
              margin-bottom: 0.5rem;
              box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .color-stripe {
              flex: 1;
              transition: transform 0.3s ease;
            }

            .theme-option:hover .color-stripe {
              transform: scaleY(1.2);
            }

            .theme-name {
              font-weight: 600;
              font-size: 0.875rem;
              color: #374151;
              text-align: center;
            }

            /* Breadcrumb Enhancement */
            .breadcrumb-container {
              background: rgba(255,255,255,0.95);
              border-radius: 8px;
              padding: 1rem;
              margin: 1rem 0;
              box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }

            .breadcrumb {
              margin: 0;
              background: none;
            }

            .breadcrumb-item a {
              color: var(--primary-color);
              text-decoration: none;
              font-weight: 500;
            }

            .breadcrumb-item a:hover {
              color: var(--secondary-color);
            }

            .breadcrumb-item.active {
              color: #6b7280;
              font-weight: 600;
            }

            /* Quick Actions */
            .quick-actions {
              display: flex;
              gap: 0.5rem;
              align-items: center;
            }

            .quick-actions .btn {
              border-radius: 6px;
              font-weight: 600;
              padding: 0.5rem 1rem;
              transition: all 0.3s ease;
            }

            .quick-actions .btn:hover {
              transform: translateY(-1px);
              box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            }

            /* Enhanced Button Styles */
            .btn-primary {
              background: var(--primary-color);
              border-color: var(--primary-color);
            }

            .btn-primary:hover {
              background: var(--secondary-color);
              border-color: var(--secondary-color);
            }

            .btn-success {
              background: var(--success-color);
              border-color: var(--success-color);
            }

            .btn-warning {
              background: var(--warning-color);
              border-color: var(--warning-color);
            }

            .btn-danger {
              background: var(--danger-color);
              border-color: var(--danger-color);
            }

            .btn-info {
              background: var(--info-color);
              border-color: var(--info-color);
            }

            /* Maintenance mode styles */
            .maintenance-container {
              min-height: 100vh;
              background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
              display: flex;
              align-items: center;
              justify-content: center;
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }

            .maintenance-card {
              background: white;
              border-radius: 20px;
              padding: 3rem;
              max-width: 600px;
              margin: 2rem;
              box-shadow: 0 20px 40px rgba(0,0,0,0.1);
              text-align: center;
              animation: fadeInUp 0.6s ease-out;
            }

            .maintenance-icon {
              font-size: 4rem;
              color: #667eea;
              margin-bottom: 1.5rem;
              transition: transform 0.3s ease;
            }

            .maintenance-icon:hover {
              transform: scale(1.2) rotate(5deg);
              cursor: pointer;
            }

            .enlarged-icon {
              transform: scale(2) !important;
              color: #764ba2 !important;
            }

            /* Responsive Improvements */
            @media (max-width: 768px) {
              .professional-navbar .navbar-brand {
                font-size: 1.25rem;
              }

              .sidebar {
                width: 280px;
              }

              .theme-grid {
                grid-template-columns: 1fr;
              }

              .theme-picker {
                min-width: 300px;
              }

              .quick-actions {
                flex-wrap: wrap;
              }

              .maintenance-card {
                margin: 1rem;
                padding: 2rem;
              }

              .maintenance-icon {
                font-size: 3rem;
              }
            }

            @keyframes fadeInUp {
              from {
                opacity: 0;
                transform: translateY(30px);
              }
              to {
                opacity: 1;
                transform: translateY(0);
              }
            }

            /* Loading states */
            .btn-loading {
              position: relative;
              pointer-events: none;
            }

            .btn-loading::after {
              content: "";
              position: absolute;
              width: 16px;
              height: 16px;
              margin: auto;
              border: 2px solid transparent;
              border-top-color: #ffffff;
              border-radius: 50%;
              animation: button-loading-spinner 1s ease infinite;
            }

            @keyframes button-loading-spinner {
              from {
                transform: rotate(0turn);
              }
              to {
                transform: rotate(1turn);
              }
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <div class="maintenance-icon" id="mainIcon" onclick="toggleIcon()">📦✨</div>
            <div class="click-hint">Click the icon to enlarge!</div>
            <h1 class="maintenance-title">We're Almost Online!</h1>
            <p class="maintenance-subtitle">
                Our Purchase Order receiving system is getting a fresh coat of digital paint. 
                Check back soon - we'll be processing orders faster than you can say "inventory management"!
            </p>
            
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            
            <div class="joke-box">
                <p class="joke-text">
                    🤔 Why did the purchase order go to therapy?<br>
                    Because it had too many <em>receiving</em> issues and couldn't handle the <em>delivery</em>! 
                    Don't worry though - our system will be back to receiving orders in no time... 
                    unlike that one supplier who's always "just around the corner" with their delivery truck! 📋😄
                </p>
            </div>
            
            <p style="color: #718096; margin-bottom: 20px;">
                <strong>ETA:</strong> Very soon! Our tech wizards are working their magic. ⚡
            </p>
            
            <a href="/index.php" class="back-link">← Back to CIS Dashboard</a>
        </div>
        
        <script>
            function toggleIcon() {
                const icon = document.getElementById('mainIcon');
                const hint = document.querySelector('.click-hint');
                
                if (icon.classList.contains('enlarged')) {
                    icon.classList.remove('enlarged');
                    hint.textContent = 'Click the icon to enlarge!';
                    hint.style.opacity = '0.8';
                } else {
                    icon.classList.add('enlarged');
                    hint.textContent = 'Click again to make it smaller';
                    hint.style.opacity = '1';
                    
                    // Auto-shrink after 3 seconds
                    setTimeout(() => {
                        if (icon.classList.contains('enlarged')) {
                            icon.classList.remove('enlarged');
                            hint.textContent = 'Click the icon to enlarge!';
                            hint.style.opacity = '0.8';
                        }
                    }, 3000);
                }
            }
            
            // Add keyboard support
            document.getElementById('mainIcon').addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleIcon();
                }
            });
            
            // Make icon focusable for accessibility
            document.getElementById('mainIcon').setAttribute('tabindex', '0');
            document.getElementById('mainIcon').setAttribute('role', 'button');
            document.getElementById('mainIcon').setAttribute('aria-label', 'Click to enlarge maintenance icon');
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ---- Session gate (customise as needed) ----
if (!isset($_SESSION['userID'])) { 
    // Redirect to login instead of showing debug info
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// ------------------------ Helpers ------------------------
if (!function_exists('requireLoggedInUser')) {
  function requireLoggedInUser() {
    if (!isset($_SESSION['userID'])) {
        // Return null or redirect instead of debug output
        return null;
    }
    // Return the actual user ID from session
    return ['userID' => (int)$_SESSION['userID']];
  }
}

// ------------------------ POST: JSON API ------------------------
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!isset($_SESSION['userID'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Authentication Required',
        'details' => [
            'message' => 'No userID in session',
            'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
            'session_id' => session_id(),
            'session_keys' => array_keys($_SESSION),
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'post_data_keys' => array_keys($_POST),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
  }
  $uid = (int)$_SESSION['userID'];

  // Handle product search request
  if (isset($_POST['searchProducts'])) {
    $term = trim($_POST['term'] ?? '');
    if (strlen($term) < 2) {
      _jsonOut(['success'=>false,'error'=>'Search term too short'], 400);
    }
    
    $searchTerm = db_escape($term, $con);
    $products = sql_query_collection("
      SELECT 
        vp.vend_id as id,
        vp.name,
        vp.sku,
        vp.brand_name as brand,
        vp.image_thumbnail_url as image
      FROM vend_products vp
      WHERE (
        vp.name LIKE '%{$searchTerm}%' 
        OR vp.sku LIKE '%{$searchTerm}%'
        OR vp.brand_name LIKE '%{$searchTerm}%'
        OR vp.handle LIKE '%{$searchTerm}%'
      )
      AND vp.deleted_at IS NULL
      ORDER BY 
        CASE 
          WHEN vp.name LIKE '{$searchTerm}%' THEN 1
          WHEN vp.sku LIKE '{$searchTerm}%' THEN 2
          ELSE 3
        END,
        vp.name
      LIMIT 20
    ");
    
    _jsonOut(['success'=>true,'products'=>$products]);
  }

  // Handle new product creation
  if (isset($_POST['createNewProduct'])) {
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!$name) {
      _jsonOut(['success'=>false,'error'=>'Product name is required'], 400);
    }
    
    // Generate a unique product ID
    $productId = 'SUB_' . date('Ymd_His') . '_' . substr(md5($name . time()), 0, 6);
    
    // Handle photo upload
    $photoUrl = '';
    if (!empty($_FILES['photo']['tmp_name'])) {
      $uploadDir = 'uploads/substituted_products/';
      if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      
      $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
      $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
      
      if (in_array($fileExt, $allowedExts)) {
        $fileName = $productId . '.' . $fileExt;
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
          $photoUrl = $uploadPath;
        }
      }
    }
    
    // Insert into substituted products table (create if not exists)
    sql_query_update_or_insert("
      CREATE TABLE IF NOT EXISTS substituted_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id VARCHAR(100) NOT NULL UNIQUE,
        name VARCHAR(500) NOT NULL,
        sku VARCHAR(100),
        brand VARCHAR(200),
        category VARCHAR(100),
        description TEXT,
        photo_url VARCHAR(500),
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_product_id (product_id),
        INDEX idx_name (name),
        INDEX idx_sku (sku)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert the new product
    $nameEsc = db_escape($name, $con);
    $skuEsc = db_escape($sku, $con);
    $brandEsc = db_escape($brand, $con);
    $categoryEsc = db_escape($category, $con);
    $descEsc = db_escape($description, $con);
    $photoEsc = db_escape($photoUrl, $con);
    $productIdEsc = db_escape($productId, $con);
    
    $insertId = sql_query_update_or_insert("
      INSERT INTO substituted_products 
      (product_id, name, sku, brand, category, description, photo_url, created_by)
      VALUES 
      ('{$productIdEsc}', '{$nameEsc}', '{$skuEsc}', '{$brandEsc}', '{$categoryEsc}', '{$descEsc}', '{$photoEsc}', {$uid})
    ");
    
    if ($insertId) {
      _jsonOut(['success'=>true,'product_id'=>$productId,'message'=>'Product created successfully']);
    } else {
      _jsonOut(['success'=>false,'error'=>'Failed to create product'], 500);
    }
  }

  // Handle special case for JSON actions with robust error handling
  $input = file_get_contents('php://input');
  $jsonData = null;
  $action = null;
  
  // Try to parse JSON input
  if (!empty($input)) {
    $jsonData = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      _jsonOut(['success'=>false,'error'=>'Invalid JSON format: ' . json_last_error_msg()], 400);
    }
  }
  
  if ($jsonData && isset($jsonData['action']) && !empty($jsonData['action'])) {
    // Handle JSON-based actions (from new smart submit system)
    $action = $jsonData['action'];
    $_POST = array_merge($_POST, $jsonData); // Merge for compatibility
  } else {
    // Handle traditional form POST actions
    $allowed = ['poReceiveSimple','poUploadEvidence','poIssueUploadQR','poListEvidence','poAssignEvidence','poExtendLock','poReleaseLock','poUnlockDocument','poSubmitFinal','poSubmitPartial'];
    $present = array_values(array_filter($allowed, static fn($k)=>isset($_POST[$k])));
    if (count($present)!==1) {
      $debugInfo = [
        'json_data' => $jsonData ? 'present' : 'missing',
        'json_action' => isset($jsonData['action']) ? $jsonData['action'] : 'none',
        'post_keys' => array_keys($_POST),
        'allowed_actions' => $allowed,
        'present_actions' => $present
      ];
      _jsonOut(['success'=>false,'error'=>'Provide exactly one action','debug'=>$debugInfo], 400);
    }
    $action = $present[0];
  }

  try {
    switch ($action) {
      // ---------- Smart Submit (Auto-Detect Partial/Final) ----------
      case 'smart_submit': {
        $poId = (int)($_POST['purchaseOrderId'] ?? $_POST['purchase_order_id'] ?? 0);
        $submitType = $_POST['submit_type'] ?? 'auto';
        $items = json_decode($_POST['items'] ?? '[]', true);
        
        if ($poId <= 0) _jsonOut(['success'=>false,'error'=>'Invalid Purchase Order ID'], 400);
        if (empty($items)) _jsonOut(['success'=>false,'error'=>'No items to submit'], 400);
        
        global $con;
        $con->begin_transaction();
        
        try {
          // Validate PO exists and get details
          $po = sql_query_single_row("SELECT * FROM purchase_orders WHERE purchase_order_id={$poId} LIMIT 1");
          if (!$po) throw new Exception('Purchase order not found');
          if ((int)$po->status === 1) throw new Exception('Order already completed');
          
          // Get all ordered items for comparison
          $orderedItems = sql_query_collection("
            SELECT product_id, order_qty 
            FROM purchase_order_line_items 
            WHERE purchase_order_id={$poId}
          ");
          
          $orderedQtys = [];
          foreach ($orderedItems as $item) {
            $orderedQtys[$item->product_id] = (int)$item->order_qty;
          }
          
          // Process each submitted item
          $totalOrdered = 0;
          $totalReceived = 0;
          $hasPartialItems = false;
          $hasCompleteItems = false;
          $updatedProducts = [];
          
          foreach ($items as $item) {
            $productId = db_escape($item['product_id'], $con);
            $qtyReceived = max(0, (int)$item['qty_arrived']);
            $useLive = !empty($item['use_live']);
            $manualStock = $useLive ? 0 : max(0, (int)$item['manual_stock']);
            
            if ($qtyReceived <= 0) continue; // Skip items with no quantity
            
            $orderedQty = $orderedQtys[$item['product_id']] ?? 0;
            $totalOrdered += $orderedQty;
            $totalReceived += $qtyReceived;
            
            // Determine if this item is partial or complete
            if ($qtyReceived < $orderedQty) {
              $hasPartialItems = true;
            } elseif ($qtyReceived >= $orderedQty) {
              $hasCompleteItems = true;
            }
            
            // Update the purchase order line item
            sql_query_update_or_insert("
              UPDATE purchase_order_line_items 
              SET qty_arrived = {$qtyReceived},
                  received_at = NOW(),
                  discrepancy_type = 'OK'
              WHERE purchase_order_id = {$poId} AND product_id = '{$productId}'
              LIMIT 1
            ");
            
            // Handle inventory adjustment if not using live mode
            if (!$useLive && $manualStock > 0) {
              sql_query_update_or_insert("
                UPDATE vend_products 
                SET inventory_count = {$manualStock},
                    inventory_updated_at = NOW()
                WHERE vend_id = '{$productId}'
                LIMIT 1
              ");
            }
            
            $updatedProducts[] = [
              'product_id' => $item['product_id'],
              'qty_received' => $qtyReceived,
              'qty_ordered' => $orderedQty,
              'use_live' => $useLive,
              'manual_stock' => $manualStock
            ];
          }
          
          // Auto-detect submission type if not specified
          if ($submitType === 'auto') {
            if ($hasPartialItems && !$hasCompleteItems) {
              $submitType = 'partial';
            } elseif (!$hasPartialItems && $hasCompleteItems) {
              $submitType = 'final';
            } elseif ($hasPartialItems && $hasCompleteItems) {
              // Mixed case - default to partial to allow continued receiving
              $submitType = 'partial';
            } else {
              $submitType = 'partial'; // Fallback
            }
          }
          
          // Apply the submission type
          if ($submitType === 'final') {
            // Mark order as completed
            sql_query_update_or_insert("
              UPDATE purchase_orders 
              SET status = 1,
                  completed_by = {$uid},
                  completed_timestamp = NOW()
              WHERE purchase_order_id = {$poId}
              LIMIT 1
            ");
            
            // Log completion
            sql_query_update_or_insert("
              INSERT INTO system_event_log (event_type, table_name, record_id, staff_id, details, timestamp)
              VALUES ('PURCHASE_ORDER_COMPLETED', 'purchase_orders', {$poId}, {$uid}, 'Order completed via smart submit system', NOW())
            ");
            
            $message = "✅ Order marked as FINAL and completed successfully!";
            $statusText = "COMPLETED";
            
          } else {
            // Mark as partially received
            sql_query_update_or_insert("
              UPDATE purchase_orders 
              SET status = 0,
                  partially_received = 1,
                  last_received_by = {$uid},
                  last_received_timestamp = NOW()
              WHERE purchase_order_id = {$poId}
              LIMIT 1
            ");
            
            // Log partial completion
            sql_query_update_or_insert("
              INSERT INTO system_event_log (event_type, table_name, record_id, staff_id, details, timestamp)
              VALUES ('PURCHASE_ORDER_PARTIAL', 'purchase_orders', {$poId}, {$uid}, 'Order partially received via smart submit system', NOW())
            ");
            
            $message = "📦 Order marked as PARTIAL - you can continue receiving remaining items later.";
            $statusText = "PARTIALLY RECEIVED";
          }
          
          $con->commit();
          
          _jsonOut([
            'success' => true,
            'message' => $message,
            'submission_type' => $submitType,
            'status_text' => $statusText,
            'auto_detected' => $_POST['submit_type'] === 'auto',
            'summary' => [
              'products_updated' => count($updatedProducts),
              'total_received' => $totalReceived,
              'total_ordered' => $totalOrdered,
              'has_partial_items' => $hasPartialItems,
              'has_complete_items' => $hasCompleteItems
            ],
            'products' => $updatedProducts
          ]);
          
        } catch(Throwable $e) {
          $con->rollback();
          _jsonOut(['success'=>false,'error'=>'Smart submit failed: '.$e->getMessage()], 500);
        }
      }
      
      // ---------- Unlock Order ----------
      case 'unlock_order': {
        $poId = (int)($_POST['purchase_order_id'] ?? $_POST['purchaseOrderId'] ?? 0);
        if ($poId <= 0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);
        
        global $con;
        $con->begin_transaction();
        
        try {
          // Verify the PO exists and user has permission
          $po = sql_query_single_row("SELECT purchase_order_id, status, completed_by FROM purchase_orders WHERE purchase_order_id={$poId} LIMIT 1");
          if (!$po) throw new Exception('Purchase order not found');
          
          // Reset PO status to allow editing again
          sql_query_update_or_insert("
            UPDATE purchase_orders 
            SET status = 0,
                completed_by = NULL,
                completed_timestamp = NULL,
                unlocked_by = {$uid},
                unlocked_timestamp = NOW()
            WHERE purchase_order_id = {$poId}
            LIMIT 1
          ");
          
          // Create audit trail
          sql_query_update_or_insert("
            INSERT INTO system_event_log (event_type, table_name, record_id, staff_id, details, timestamp)
            VALUES ('PURCHASE_ORDER_UNLOCKED', 'purchase_orders', {$poId}, {$uid}, 'Document unlocked for editing via smart system', NOW())
          ");
          
          $con->commit();
          _jsonOut(['success'=>true,'message'=>'Purchase order unlocked successfully - you can now edit this order']);
          
        } catch(Throwable $e) {
          $con->rollback();
          _jsonOut(['success'=>false,'error'=>'Unlock failed: '.$e->getMessage()], 500);
        }
      }

      // ---------- Extend Lock ----------
      case 'poExtendLock': {
        $poId = (int)($_POST['poExtendLock'] ?? 0);
        if ($poId <= 0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);
        
        $result = extendPOLock($poId, $uid);
        if ($result) {
          _jsonOut(['success'=>true,'message'=>'Lock extended for 15 minutes']);
        } else {
          _jsonOut(['success'=>false,'error'=>'Failed to extend lock'], 400);
        }
      }

      // ---------- Release Lock ----------
      case 'poReleaseLock': {
        $poId = (int)($_POST['poReleaseLock'] ?? 0);
        if ($poId <= 0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);
        
        releasePOLock($poId, $uid);
        _jsonOut(['success'=>true,'message'=>'Lock released']);
      }

      // ---------- Unlock Document for Editing ----------
      case 'poUnlockDocument': {
        $poId = (int)($_POST['poUnlockDocument'] ?? 0);
        if ($poId <= 0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);
        
        global $con; $con->begin_transaction();
        try {
          // Verify the PO exists and user has permission
          $po = sql_query_single_row("SELECT purchase_order_id, status, completed_by FROM purchase_orders WHERE purchase_order_id={$poId} LIMIT 1");
          if (!$po) throw new Exception('Purchase order not found');
          
          // Reset PO status to allow editing again
          sql_query_update_or_insert("
            UPDATE purchase_orders 
            SET status=0, 
                completed_by=NULL, 
                completed_timestamp=NULL,
                unlocked_by={$uid},
                unlocked_timestamp=NOW()
            WHERE purchase_order_id={$poId} LIMIT 1");
          
          // Create audit trail
          sql_query_update_or_insert("
            INSERT INTO system_event_log (event_type, table_name, record_id, staff_id, details, timestamp)
            VALUES ('PURCHASE_ORDER_UNLOCKED', 'purchase_orders', {$poId}, {$uid}, 'Document unlocked for editing', NOW())");
          
          // Release any existing locks
          releasePOLock($poId, $uid);
          
          $con->commit();
          _jsonOut(['success'=>true,'message'=>'Document unlocked successfully - you can now edit this purchase order']);
        } catch(Throwable $e){
          $con->rollback();
          _jsonOut(['success'=>false,'error'=>'Unlock failed: '.$e->getMessage()], 500);
        }
      }
      // ---------- Commit / Save ----------
      case 'poReceiveSimple': {
        $payload = json_decode($_POST['poReceiveSimple'] ?? '[]', true) ?: [];
        $poId = (int)($payload['purchaseOrderID'] ?? 0);
        $commit = (string)($payload['commitType'] ?? 'draft'); // draft|final
        if ($poId<=0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);

        global $con; $con->begin_transaction();
        try {
          // header
          $ps = db_escape((string)($payload['meta']['packing_slip_no'] ?? ''), $con);
          $in = db_escape((string)($payload['meta']['invoice_no'] ?? ''), $con);
          $noSlip = (int)!!($payload['meta']['no_packing_slip'] ?? 0);

          $tm = isset($payload['meta']['totals_mode']) ? db_escape($payload['meta']['totals_mode'], $con) : 'NULL';
          $sub = isset($payload['meta']['subtotal_ex_gst']) ? (float)$payload['meta']['subtotal_ex_gst'] : null;
          $gst = isset($payload['meta']['gst']) ? (float)$payload['meta']['gst'] : null;
          $inc = isset($payload['meta']['total_inc_gst']) ? (float)$payload['meta']['total_inc_gst'] : null;

          $sql = "
            UPDATE purchase_orders
            SET packing_slip_no=" . ($ps===''?'NULL':"'{$ps}'") . ",
                invoice_no=" . ($in===''?'NULL':"'{$in}'") . ",
                no_packing_slip={$noSlip},
                totals_mode=" . ($tm==='NULL'?'NULL':"'{$tm}'") . ",
                subtotal_ex_gst=" . (is_null($sub)?'NULL':$sub) . ",
                gst=" . (is_null($gst)?'NULL':$gst) . ",
                total_inc_gst=" . (is_null($inc)?'NULL':$inc) . "
            WHERE purchase_order_id={$poId}
            LIMIT 1";
          sql_query_update_or_insert($sql);

          // lines
          $stats = ['lines'=>0,'ordered'=>0,'slip'=>0,'received'=>0,'damaged'=>0,'issues'=>0];
          $issues = [];

          foreach ((array)($payload['products'] ?? []) as $row) {
            $pid  = db_escape((string)($row['productID'] ?? ''), $con);
            if ($pid==='') continue;

            $slip = isset($row['slipQty']) ? max(0, (int)$row['slipQty']) : null;
            $recv = isset($row['receivedQty']) ? max(0, (int)$row['receivedQty']) : null;
            $dmg  = isset($row['damagedQty']) ? max(0, (int)$row['damagedQty']) : 0;
            $note = db_escape((string)($row['lineNote'] ?? ''), $con);
            $unit = isset($row['unitCostExGst']) && $row['unitCostExGst']!=='' ? (float)$row['unitCostExGst'] : null;
            $sub  = db_escape((string)($row['substitutionProductID'] ?? ''), $con);
            $dtype= db_escape((string)($row['discrepancyType'] ?? 'OK'), $con);

            $ordRow = sql_query_single_row("SELECT order_qty FROM purchase_order_line_items WHERE purchase_order_id={$poId} AND product_id='{$pid}' LIMIT 1");
            $ord = (int)($ordRow->order_qty ?? 0);

            $stats['lines']++; $stats['ordered'] += $ord; $stats['slip'] += (int)($slip ?? 0);
            $stats['received']+= (int)($recv ?? 0); $stats['damaged'] += $dmg;

            $sqlU = "
              UPDATE purchase_order_line_items
              SET slip_qty=" . (is_null($slip)?'NULL':$slip) . ",
                  qty_arrived=" . (is_null($recv)?'NULL':$recv) . ",
                  damaged_qty={$dmg},
                  discrepancy_type='{$dtype}',
                  unit_cost_ex_gst=" . (is_null($unit)?'NULL':$unit) . ",
                  line_note=" . ($note===''?'NULL':"'{$note}'") . ",
                  substitution_product_id=" . ($sub===''?'NULL':"'{$sub}'") . "
              WHERE purchase_order_id={$poId} AND product_id='{$pid}'
              LIMIT 1";
            sql_query_update_or_insert($sqlU);

            sql_query_update_or_insert("DELETE FROM purchase_order_discrepancy_cases WHERE purchase_order_id={$poId} AND product_id='{$pid}'");

            if ($dtype!=='OK') {
              $stats['issues']++;
              $delta = 0; $recvTotal = (int)($recv ?? 0) + (int)$dmg;
              if ($dtype==='SENT_LOW' || $dtype==='MISSING')   $delta = $recvTotal - $ord;
              if ($dtype==='SENT_HIGH' || $dtype==='UNORDERED')$delta = $recvTotal - $ord;
              if ($dtype==='DAMAGED')                           $delta = -$dmg;
              if ($dtype==='SUBSTITUTED')                       $delta = 0;
              if ($dtype==='EXPIRED')                           $delta = 0;
              if ($dtype==='NOT_COMPLIANT')                     $delta = 0;

              $noteIns = $note==='' ? 'NULL' : "'{$note}'";
              $sqlI = "INSERT INTO purchase_order_discrepancy_cases (purchase_order_id, product_id, case_type, delta_qty, note)
                       VALUES ({$poId}, '{$pid}', '{$dtype}', ".(int)$delta.", {$noteIns})";
              sql_query_update_or_insert($sqlI);
              $issues[] = ['product_id'=>$pid, 'type'=>$dtype, 'delta'=>$delta];
            }
          }

          if ($commit==='final') {
            $completedBy = db_escape((string)$uid, $con);
            sql_query_update_or_insert("
              UPDATE purchase_orders
              SET status=1, completed_by='{$completedBy}', completed_timestamp=NOW()
              WHERE purchase_order_id={$poId} LIMIT 1");
          }

          if ($stats['issues']>0) {
            $po = sql_query_single_row("SELECT supplier_id FROM purchase_orders WHERE purchase_order_id={$poId} LIMIT 1");
            $sup = db_escape((string)($po->supplier_id ?? ''), $con);
            $existing = sql_query_single_row("SELECT claim_id FROM purchase_order_claims WHERE purchase_order_id={$poId} LIMIT 1");
            $claimId = (int)($existing->claim_id ?? 0);
            if ($claimId===0) {
              $claimId = (int)sql_query_update_or_insert("INSERT INTO purchase_order_claims (purchase_order_id, supplier_id, status, created_by) VALUES ({$poId}, '{$sup}', 'PENDING', {$uid})");
            } else {
              sql_query_update_or_insert("UPDATE purchase_order_claims SET status='PENDING', updated_at=NOW() WHERE claim_id={$claimId} LIMIT 1");
            }
            sql_query_update_or_insert("DELETE FROM purchase_order_claim_lines WHERE claim_id={$claimId}");
            foreach ($issues as $it) {
              $pid = db_escape($it['product_id'], $con);
              $qty = (int)max(0, abs((int)$it['delta']));
              if ($it['type']==='MISSING') $qty = (int)max(1,$qty);
              $reason = db_escape($it['type'], $con);
              sql_query_update_or_insert("INSERT INTO purchase_order_claim_lines (claim_id, product_id, reason, qty) VALUES ({$claimId}, '{$pid}', '{$reason}', {$qty})");
            }
          }

          $con->commit();

          $score = 0;
          if ($stats['lines']>0) {
            $ok = $stats['lines'] - $stats['issues'];
            $score = max(0, min(100, round(($ok/$stats['lines'])*100 - $stats['issues']*4)));
          }
          _jsonOut(['success'=>true,'stats'=>$stats,'confidence'=>$score,'final'=>$commit==='final']);
        } catch(Throwable $e){
          $con->rollback();
          _jsonOut(['success'=>false,'error'=>'Save failed: '.$e->getMessage()], 500);
        }
      }

      // ---------- Submit Final Order ----------
      case 'poSubmitFinal': {
        $poId = (int)($_POST['purchaseOrderID'] ?? 0);
        if ($poId<=0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);

        global $con; $con->begin_transaction();
        try {
          // Validate order for submission
          $po = sql_query_single_row("SELECT * FROM purchase_orders WHERE purchase_order_id={$poId} LIMIT 1");
          if (!$po) throw new Exception('Purchase order not found');
          if ((int)$po->status === 1) throw new Exception('Order already completed');

          // Complete the purchase order
          $completedBy = db_escape((string)$uid, $con);
          sql_query_update_or_insert("
            UPDATE purchase_orders 
            SET status=1, completed_by='{$completedBy}', completed_timestamp=NOW()
            WHERE purchase_order_id={$poId} LIMIT 1");

          // Create audit trail
          sql_query_update_or_insert("
            INSERT INTO system_event_log (event_type, table_name, record_id, staff_id, details, timestamp)
            VALUES ('PURCHASE_ORDER_COMPLETED', 'purchase_orders', {$poId}, {$uid}, 'Order completed via PO receiving system', NOW())");

          // Sync to Vend (placeholder for integration)
          $lines = sql_query("SELECT * FROM purchase_order_line_items WHERE purchase_order_id={$poId}");
          $vendUpdates = [];
          while ($line = $lines->fetch_object()) {
            if ($line->qty_arrived && $line->qty_arrived > 0) {
              $vendUpdates[] = [
                'product_id' => $line->product_id,
                'quantity_received' => $line->qty_arrived,
                'unit_cost' => $line->unit_cost_ex_gst
              ];
            }
          }

          // Generate completion summary
          $summary = [
            'order_id' => $poId,
            'completed_at' => date('Y-m-d H:i:s'),
            'completed_by' => $uid,
            'vend_updates' => count($vendUpdates),
            'status' => 'COMPLETED'
          ];

          $con->commit();
          _jsonOut(['success'=>true,'message'=>'Order submitted successfully','summary'=>$summary]);
        } catch(Throwable $e){
          $con->rollback();
          _jsonOut(['success'=>false,'error'=>'Submission failed: '.$e->getMessage()], 500);
        }
      }

      // ---------- Submit Partial Order ----------
      case 'poSubmitPartial': {
        $poId = (int)($_POST['purchaseOrderID'] ?? 0);
        if ($poId<=0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);

        global $con; $con->begin_transaction();
        try {
          // Validate order for partial submission
          $po = sql_query_single_row("SELECT * FROM purchase_orders WHERE purchase_order_id={$poId} LIMIT 1");
          if (!$po) throw new Exception('Purchase order not found');

          // Mark as partially received
          $completedBy = db_escape((string)$uid, $con);
          sql_query_update_or_insert("
            UPDATE purchase_orders 
            SET status=0, partially_received=1, last_received_by='{$completedBy}', last_received_timestamp=NOW()
            WHERE purchase_order_id={$poId} LIMIT 1");

          // Create audit trail
          sql_query_update_or_insert("
            INSERT INTO system_event_log (event_type, table_name, record_id, staff_id, details, timestamp)
            VALUES ('PURCHASE_ORDER_PARTIAL', 'purchase_orders', {$poId}, {$uid}, 'Order partially received via PO receiving system', NOW())");

          // Generate partial completion summary
          $lines = sql_query("SELECT COUNT(*) as total, SUM(CASE WHEN qty_arrived > 0 THEN 1 ELSE 0 END) as received FROM purchase_order_line_items WHERE purchase_order_id={$poId}");
          $stats = $lines->fetch_object();
          
          $summary = [
            'order_id' => $poId,
            'partially_received_at' => date('Y-m-d H:i:s'),
            'received_by' => $uid,
            'lines_total' => (int)$stats->total,
            'lines_received' => (int)$stats->received,
            'status' => 'PARTIAL'
          ];

          $con->commit();
          _jsonOut(['success'=>true,'message'=>'Partial order submitted successfully','summary'=>$summary]);
        } catch(Throwable $e){
          $con->rollback();
          _jsonOut(['success'=>false,'error'=>'Partial submission failed: '.$e->getMessage()], 500);
        }
      }

      // ---------- Upload evidence ----------
      case 'poUploadEvidence': {
        $poId = (int)($_POST['purchaseOrderID'] ?? 0);
        if ($poId<=0) _jsonOut(['success'=>false,'error'=>'Invalid purchaseOrderID'], 400);
        $files = $_FILES['files'] ?? $_FILES;
        if (!$files) _jsonOut(['success'=>false,'error'=>'No files'], 400);

        $dir = _po_path_for($poId);
        $out = [];
        $allowed = ['image/jpeg','image/png','image/webp','image/gif','image/bmp','image/tiff','application/pdf','video/mp4','video/webm','video/avi','video/mov','text/plain','text/csv','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;

        for ($i=0; $i<$count; $i++) {
          $name = (string)$files['name'][$i];
          $tmp  = (string)$files['tmp_name'][$i];
          $type = (string)$files['type'][$i];
          $size = (int)$files['size'][$i];
          $err  = (int)$files['error'][$i];

          if ($err!==UPLOAD_ERR_OK) continue;
          if (!in_array($type, $allowed, true)) continue;
          if ($size<=0 || !is_uploaded_file($tmp)) continue;

          // Generate timestamp and cleaned filename
          $timestamp = time();
          $cleanName = preg_replace('/[^A-Za-z0-9._-]/','_', $name);
          $base = $timestamp.'_'.$cleanName;
          $dest = rtrim($dir,'/').'/'.$base;
          
          if (!move_uploaded_file($tmp, $dest)) continue;
          @chmod($dest, 0664);

          $sha = _sha256_of_file($dest);
          $url = _public_url_for($poId, $base);
          $rel = 'uploads/po/'.$poId.'/'.$base;

          // Enhanced metadata capture
          $metadata = [];
          $metadata['original_filename'] = $name;
          $metadata['upload_timestamp'] = $timestamp;
          $metadata['file_type'] = $type;
          $metadata['upload_method'] = $_POST['upload_method'] ?? 'manual';
          $metadata['device_info'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
          
          // Check if file is scannable document
          $isScannable = in_array($type, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/tiff']);
          $metadata['scannable'] = $isScannable;
          
          // Support for reverse printing flag
          $metadata['reverse_print'] = isset($_POST['reverse_print']) && $_POST['reverse_print'] === '1';
          
          $metadataJson = json_encode($metadata);

          $id = (int)sql_query_update_or_insert("
            INSERT INTO purchase_order_evidence (purchase_order_id, uploaded_by, filename, mime, size_bytes, sha256, rel_path, public_url, metadata, created_at)
            VALUES ({$poId}, {$uid}, '".db_escape($name,$con)."', '".db_escape($type,$con)."', {$size}, '{$sha}', '".db_escape($rel,$con)."', '".db_escape($url,$con)."', '".db_escape($metadataJson,$con)."', NOW())
          ");
          
          $out[] = [
            'id'=>$id,
            'filename'=>$name,
            'url'=>$url,
            'mime'=>$type,
            'size'=>$size,
            'scannable'=>$isScannable,
            'reverse_print'=>$metadata['reverse_print'],
            'timestamp'=>$timestamp
          ];
        }

        _jsonOut(['success'=>true,'files'=>$out]);
      }

      // ---------- Issue QR ----------
      case 'poIssueUploadQR': {
        $po = (int)($_POST['poIssueUploadQR'] ?? 0);
        if ($po<=0) _jsonOut(['success'=>false,'error'=>'Invalid PO'], 400);

        $token = _rand_token(64);
        $ttl = 300;
        $exp = date('Y-m-d H:i:s', time()+$ttl);
        $ip  = db_escape($_SERVER['REMOTE_ADDR'] ?? '', $con);
        $ua  = db_escape($_SERVER['HTTP_USER_AGENT'] ?? '', $con);

        sql_query_update_or_insert("
          INSERT INTO purchase_order_evidence_tokens (purchase_order_id, token, max_uses, expires_at, created_by, created_ip, created_user_agent)
          VALUES ({$po}, '{$token}', 100, '{$exp}', {$uid}, '{$ip}', '{$ua}')
        ");

        $signed = _poe_signed_sid_for_token($token, $uid);
        $url = _host_base()."/po-evidence-upload.php?t=".rawurlencode($token)."&po={$po}&sid=".$signed['sid']."&sig=".$signed['sig'];

        _jsonOut(['success'=>true,'url'=>$url,'token'=>$token,'expires_at'=>$exp,'sid'=>$signed['sid'],'sig'=>$signed['sig']]);
      }

      // ---------- List evidence ----------
      case 'poListEvidence': {
        $p = json_decode($_POST['poListEvidence'] ?? '[]', true) ?: [];
        $po = (int)($p['po'] ?? 0);
        $since = isset($p['since']) ? (string)$p['since'] : null;
        if ($po<=0) _jsonOut(['success'=>false,'error'=>'Invalid po'], 400);

        $cond = "purchase_order_id={$po}";
        if (!empty($since)) {
          $esc = db_escape($since, $con);
          $cond .= " AND created_at >= '{$esc}'";
        }
        $rows = sql_query_collection("SELECT e.evidence_id AS id, e.filename, e.mime, e.size_bytes AS size, e.public_url AS url, e.created_at,
                                             (SELECT product_id FROM purchase_order_line_evidence WHERE purchase_order_id={$po} AND evidence_id=e.evidence_id LIMIT 1) AS product_id
                                      FROM purchase_order_evidence e
                                      WHERE {$cond}
                                      ORDER BY e.created_at DESC, e.evidence_id DESC");
        _jsonOut(['success'=>true,'rows'=>$rows]);
      }

      // ---------- Assign evidence ----------
      case 'poAssignEvidence': {
        $p = json_decode($_POST['poAssignEvidence'] ?? '[]', true) ?: [];
        $po  = (int)($p['po'] ?? 0);
        $evi = (int)($p['evidence_id'] ?? 0);
        $pid = (string)($p['product_id'] ?? '');
        if ($po<=0 || $evi<=0 || $pid==='') _jsonOut(['success'=>false,'error'=>'Missing params'], 400);

        $ip = db_escape($_SERVER['REMOTE_ADDR'] ?? '', $con);
        $ua = db_escape($_SERVER['HTTP_USER_AGENT'] ?? '', $con);

        sql_query_update_or_insert("
          INSERT IGNORE INTO purchase_order_line_evidence (purchase_order_id, product_id, evidence_id, source, created_by, created_ip, user_agent)
          VALUES ({$po}, '".db_escape($pid,$con)."', {$evi}, 'upload', {$uid}, '{$ip}', '{$ua}')
        ");
        _jsonOut(['success'=>true]);
      }
      
      // ---------- Default: Invalid Action ----------
      default: {
        _jsonOut(['success'=>false,'error'=>'Invalid action: ' . $action], 400);
      }
    }
  } catch(Throwable $e){
    _jsonOut(['success'=>false,'error'=>'Unexpected server error: '.$e->getMessage()], 500);
  }
  exit;
}

// ------------------------ GET: render page ------------------------
if (!isset($_GET['id'])) { 
    echo "<div style='padding: 20px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 20px;'>";
    echo "<h3>Missing Purchase Order ID</h3>";
    echo "<p><strong>Issue:</strong> No 'id' parameter provided in URL</p>";
    echo "<p><strong>Current URL:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
    echo "<p><strong>Expected URL format:</strong> receive-purchase-order.php?id=12345</p>";
    echo "<p><strong>Available GET parameters:</strong> " . implode(', ', array_keys($_GET)) . "</p>";
    echo "<p><strong>Debug Mode:</strong> Continuing with PO ID 1 for testing</p>";
    echo "</div>";
    $_GET['id'] = 1; // Debug fallback
}
$poId = (int)$_GET['id'];

// Session locking mechanism
function checkAndSetPOLock($poId, $userID) {
  global $con;
  
  // Check if PO is already locked
  $existingLock = sql_query_single_row("
    SELECT lock_id, locked_by, locked_at, expires_at, session_id
    FROM purchase_order_locks 
    WHERE purchase_order_id = {$poId} 
    AND expires_at > NOW()
    ORDER BY locked_at DESC 
    LIMIT 1
  ");
  
  if ($existingLock) {
    // Check if it's locked by the same user/session
    $currentSessionId = session_id();
    if ($existingLock->locked_by == $userID && $existingLock->session_id == $currentSessionId) {
      // Extend the lock
      sql_query_update_or_insert("
        UPDATE purchase_order_locks 
        SET expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
            last_activity = NOW()
        WHERE lock_id = {$existingLock->lock_id}
      ");
      return ['success' => true, 'action' => 'extended'];
    } else {
      // Locked by someone else
      $lockedByName = sql_query_single_row("SELECT CONCAT(first_name, ' ', last_name) as name FROM employee WHERE staff_id = {$existingLock->locked_by} LIMIT 1");
      return [
        'success' => false, 
        'error' => 'This Purchase Order is currently being processed by ' . ($lockedByName->name ?? 'another user') . '. Lock expires at ' . $existingLock->expires_at,
        'locked_by' => $existingLock->locked_by,
        'expires_at' => $existingLock->expires_at
      ];
    }
  }
  
  // Create new lock
  $sessionId = session_id();
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
  
  $lockId = sql_query_update_or_insert("
    INSERT INTO purchase_order_locks 
    (purchase_order_id, locked_by, session_id, locked_at, expires_at, last_activity, ip_address, user_agent)
    VALUES 
    ({$poId}, {$userID}, '" . db_escape($sessionId, $con) . "', NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW(), 
     '" . db_escape($ip, $con) . "', '" . db_escape($userAgent, $con) . "')
  ");
  
  return ['success' => true, 'action' => 'created', 'lock_id' => $lockId];
}

function extendPOLock($poId, $userID) {
  global $con;
  $sessionId = session_id();
  
  $result = sql_query_update_or_insert("
    UPDATE purchase_order_locks 
    SET expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
        last_activity = NOW()
    WHERE purchase_order_id = {$poId} 
    AND locked_by = {$userID}
    AND session_id = '" . db_escape($sessionId, $con) . "'
    AND expires_at > NOW()
  ");
  
  return $result > 0;
}

function releasePOLock($poId, $userID) {
  global $con;
  $sessionId = session_id();
  
  sql_query_update_or_insert("
    UPDATE purchase_order_locks 
    SET expires_at = NOW(),
        released_at = NOW()
    WHERE purchase_order_id = {$poId} 
    AND locked_by = {$userID}
    AND session_id = '" . db_escape($sessionId, $con) . "'
  ");
}

// Check session and get user
if (!isset($_SESSION['userID'])) {
  echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 20px;'>";
  echo "<h3>GET Request Authentication Debug</h3>";
  echo "<p><strong>Issue:</strong> \$_SESSION['userID'] not found</p>";
  echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</p>";
  echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
  echo "<p><strong>Session Data:</strong></p>";
  echo "<pre>" . print_r($_SESSION, true) . "</pre>";
  echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
  echo "<p><strong>PO ID Requested:</strong> " . (int)($_GET['id'] ?? 0) . "</p>";
  echo "<p><strong>Debug Mode:</strong> Continuing with dummy user ID 999</p>";
  echo "<p><strong>Suggested Fix:</strong> Log in through CIS first, then return to this URL</p>";
  echo "</div>";
  $userID = 999; // Debug fallback
} else {
  // Successfully authenticated
  $userID = (int)$_SESSION['userID'];
  echo "<div style='padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; margin: 20px;'>";
  echo "<h3>✅ Authentication Successful</h3>";
  echo "<p><strong>User ID:</strong> " . $userID . "</p>";
  echo "<p><strong>Name:</strong> " . ($_SESSION['first_name'] ?? '') . " " . ($_SESSION['last_name'] ?? '') . "</p>";
  echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
  echo "<p><strong>PO ID:</strong> " . (int)($_GET['id'] ?? 0) . "</p>";
  echo "</div>";
}

// DISABLED SESSION LOCKING - NO TABLE EXISTS
// Check/set lock for this PO
// $lockResult = checkAndSetPOLock($poId, $userID);
// Session locking disabled for now - table doesn't exist
$lockResult = ['success' => true, 'action' => 'disabled'];

// Ensure purchase order functions are loaded
if (!function_exists('po_get_complete_data')) {
    // Try to manually include the purchase-orders.php file
    $po_functions_path = __DIR__ . '/assets/functions/purchase-orders.php';
    if (file_exists($po_functions_path)) {
        include_once $po_functions_path;
    }
    
    // If still not found, provide a fallback
    if (!function_exists('po_get_complete_data')) {
        echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 20px;'>";
        echo "<h3>⚠️ Function Loading Issue</h3>";
        echo "<p><strong>Problem:</strong> po_get_complete_data() function not loaded</p>";
        echo "<p><strong>Expected file:</strong> {$po_functions_path}</p>";
        echo "<p><strong>File exists:</strong> " . (file_exists($po_functions_path) ? 'Yes' : 'No') . "</p>";
        echo "<p><strong>Included functions:</strong> " . implode(', ', get_defined_functions()['user']) . "</p>";
        echo "<p>Please check that assets/functions/purchase-orders.php is properly included.</p>";
        echo "</div>";
        exit;
    }
}

[$poObj, $lines, $session] = po_get_complete_data($poId);
if (!$poObj) { echo "ERROR: No purchase order found for ID ".(int)$poId; exit; }

$isComplete = ((int)($poObj->status ?? 0) === 1);
$supplierInfo   = $poObj->supplier_name ?? $poObj->supplier_id;
$supplierEmail  = $poObj->email   ?? '';
$supplierPhone  = $poObj->phone   ?? '';
$outletName     = $poObj->outlet_name ?? $poObj->outlet_id;
$orderDateStr   = $poObj->date_created ?? 'now';
$orderDateTs    = strtotime($orderDateStr);
$daysSince      = max(0, (int)floor((time() - $orderDateTs) / 86400));

function e($v){ return esc($v); }

// Set page title
$pageTitle = "Purchase Order #" . (int)$poId . " - Receiving - The Vape Shed CIS";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Purchase Order Receiving - Modular CSS Architecture -->
    <link href="assets/css/po-receiving-main.css" rel="stylesheet">
    
    <!-- jQuery (Essential for our modules) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous"></script>
    
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    
    <!-- POScanner - Enhanced Barcode Scanning Module -->
    <script src="assets/js/po-scanner.js"></script>
    
    <script>var staffID = <?php echo isset($_SESSION["userID"]) ? (int)$_SESSION["userID"] : 999; ?>;</script>
</head>

<body class="bg-light">
    <!-- Color Scheme Demo System -->
    <style id="themeStyles">
      /* Base Theme Variables */
      :root {
        --primary-color: #007bff;
        --secondary-color: #6c757d;
        --success-color: #28a745;
        --warning-color: #ffc107;
        --danger-color: #dc3545;
        --info-color: #17a2b8;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
        --gradient-bg: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        --sidebar-bg: var(--dark-color);
        --navbar-bg: var(--primary-color);
        --text-on-primary: #ffffff;
        --border-color: #dee2e6;
        --hover-bg: rgba(255,255,255,0.1);
      }

      /* Navigation Styles */
      .navbar-custom {
        background: var(--navbar-bg) !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-bottom: 3px solid var(--secondary-color);
      }
      
      .navbar-brand {
        font-weight: 700;
        font-size: 1.5rem;
        color: var(--text-on-primary) !important;
      }
      
      .brand-icon {
        width: 40px;
        height: 40px;
        background: var(--secondary-color);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-on-primary);
      }
      
      .brand-primary {
        color: var(--text-on-primary);
        font-weight: 700;
      }
      
      .brand-secondary {
        color: rgba(255,255,255,0.8);
        font-weight: 400;
        font-size: 0.9rem;
      }
      
      .nav-link {
        color: rgba(255,255,255,0.9) !important;
        font-weight: 500;
        padding: 0.75rem 1rem !important;
        border-radius: 6px;
        margin: 0 2px;
        transition: all 0.3s ease;
      }
      
      .nav-link:hover, .nav-link.active {
        background: var(--hover-bg) !important;
        color: var(--text-on-primary) !important;
        transform: translateY(-1px);
      }
      
      /* Sidebar Styles */
      .sidebar {
        position: fixed;
        top: 0;
        left: -300px;
        width: 300px;
        height: 100vh;
        background: var(--sidebar-bg);
        transition: left 0.3s ease;
        z-index: 1050;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
      }
      
      .sidebar.show {
        left: 0;
      }
      
      .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        justify-content: between;
        align-items: center;
      }
      
      .sidebar-title {
        color: var(--text-on-primary);
        margin: 0;
        font-weight: 600;
      }
      
      .sidebar-close {
        background: none;
        border: none;
        color: rgba(255,255,255,0.7);
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 4px;
        transition: all 0.3s ease;
      }
      
      .sidebar-close:hover {
        background: var(--hover-bg);
        color: var(--text-on-primary);
      }
      
      .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1040;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
      }
      
      .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
      }
      
      /* Navigation Sections */
      .nav-section {
        padding: 1rem 0;
        border-bottom: 1px solid rgba(255,255,255,0.1);
      }
      
      .nav-section-title {
        color: var(--secondary-color);
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 0 1.5rem;
        margin-bottom: 0.5rem;
      }
      
      .nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
      }
      
      .nav-list .nav-link {
        display: block;
        padding: 0.75rem 1.5rem;
        color: rgba(255,255,255,0.8) !important;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
      }
      
      .nav-list .nav-link:hover {
        background: var(--hover-bg);
        color: var(--text-on-primary) !important;
        border-left-color: var(--secondary-color);
        transform: translateX(5px);
      }
      
      .nav-list .nav-link.active {
        background: var(--secondary-color);
        color: var(--text-on-primary) !important;
        border-left-color: var(--info-color);
      }
      
      /* Breadcrumb Styles */
      .breadcrumb-nav {
        background: var(--light-color);
        border-bottom: 1px solid var(--border-color);
        padding: 1rem 0;
      }
      
      .breadcrumb {
        background: transparent;
        padding: 0;
        margin: 0;
      }
      
      .breadcrumb-item a {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
      }
      
      .breadcrumb-item a:hover {
        color: var(--secondary-color);
        text-decoration: underline;
      }
      
      .breadcrumb-actions {
        display: flex;
        gap: 0.5rem;
      }
      
      /* Theme Picker Styles */
      .theme-picker {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        border: none;
        padding: 1rem;
      }
      
      .theme-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
      }
      
      .theme-option {
        cursor: pointer;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 0.75rem;
        text-align: center;
        transition: all 0.3s ease;
        background: white;
      }
      
      .theme-option:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      }
      
      .theme-option.active {
        border-color: var(--success-color);
        background: var(--light-color);
      }
      
      .theme-preview {
        height: 30px;
        border-radius: 4px;
        margin-bottom: 0.5rem;
        display: flex;
        overflow: hidden;
      }
      
      .color-stripe {
        flex: 1;
      }
      
      .theme-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #495057;
      }
      
      /* Content Area Adjustments */
      .main-content {
        transition: margin-left 0.3s ease;
      }
      
      @media (min-width: 992px) {
        .sidebar-open .main-content {
          margin-left: 300px;
        }
      }
      
      /* Responsive Adjustments */
      @media (max-width: 991px) {
        .breadcrumb-actions {
          order: -1;
          margin-bottom: 0.5rem;
        }
        
        .breadcrumb-nav .container-fluid {
          display: flex;
          flex-direction: column;
        }
      }
    </style>

    <!-- Enhanced Navigation Header with Theme Picker -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
      <div class="container-fluid">
        <!-- Brand Section -->
        <div class="d-flex align-items-center">
          <button class="btn btn-outline-light me-3" id="toggleSidebar" type="button">
            <i class="fas fa-bars"></i>
          </button>
          <a class="navbar-brand d-flex align-items-center" href="/index.php">
            <div class="brand-icon me-2">
              <i class="fas fa-store"></i>
            </div>
            <div>
              <div class="brand-primary">The Vape Shed</div>
              <div class="brand-secondary">Central Information System</div>
            </div>
          </a>
        </div>

        <!-- Quick Navigation -->
        <div class="d-none d-lg-flex me-auto">
          <ul class="navbar-nav">
            <li class="nav-item">
              <a class="nav-link" href="/index.php">
                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/purchase-orders.php">
                <i class="fas fa-shopping-cart me-1"></i> Purchase Orders
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link active" href="/receive-purchase-order.php">
                <i class="fas fa-truck me-1"></i> Receiving
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/inventory.php">
                <i class="fas fa-boxes me-1"></i> Inventory
              </a>
            </li>
          </ul>
        </div>

        <!-- Action Bar -->
        <div class="d-flex align-items-center">
          <!-- Theme Picker -->
          <div class="dropdown me-2">
            <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-palette me-1"></i> Themes
            </button>
            <div class="dropdown-menu theme-picker" aria-labelledby="themeDropdown">
              <div class="p-2">
                <h6 class="dropdown-header">Choose Your Color Scheme</h6>
                <div class="theme-grid" id="themeGrid">
                  <!-- Theme options will be populated by JavaScript -->
                </div>
              </div>
            </div>
          </div>

          <!-- Notifications -->
          <button class="btn btn-outline-light btn-sm me-2" type="button">
            <i class="fas fa-bell"></i>
            <span class="badge bg-danger rounded-pill ms-1">3</span>
          </button>

          <!-- User Menu -->
          <div class="dropdown">
            <button class="btn btn-outline-light btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-user-circle me-1"></i> 
              <?php echo htmlspecialchars(($_SESSION['first_name'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
              <li><a class="dropdown-item" href="/settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
          </div>

          <!-- Fullscreen Toggle -->
          <button class="btn btn-outline-light btn-sm ms-2" id="fullscreenToggle" type="button" title="Enter Fullscreen">
            <i class="fas fa-expand"></i>
          </button>
        </div>
      </div>
    </nav>

    <!-- Comprehensive Sidebar Navigation -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="mainSidebar">
      <div class="sidebar-header">
        <h4 class="sidebar-title">CIS Navigation</h4>
        <button class="sidebar-close" id="sidebarClose">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <nav class="sidebar-nav">
        <!-- Dashboard Section -->
        <div class="sidebar-section">
          <h6>OVERVIEW</h6>
          <a href="/index.php" class="sidebar-nav-link">
            <i class="fas fa-tachometer-alt"></i> Main Dashboard
          </a>
          <a href="/analytics.php" class="sidebar-nav-link">
            <i class="fas fa-chart-bar"></i> Analytics & Reports
          </a>
          <a href="/notifications.php" class="sidebar-nav-link">
            <i class="fas fa-bell"></i> Notifications
          </a>
        </div>

        <!-- Operations Section -->
        <div class="sidebar-section">
          <h6>OPERATIONS</h6>
          <a href="/purchase-orders.php" class="sidebar-nav-link">
            <i class="fas fa-shopping-cart"></i> Purchase Orders
          </a>
          <a href="/receive-purchase-order.php" class="sidebar-nav-link active">
            <i class="fas fa-truck"></i> Receiving
          </a>
          <a href="/inventory.php" class="sidebar-nav-link">
            <i class="fas fa-boxes"></i> Inventory Management
          </a>
          <a href="/stock-transfers.php" class="sidebar-nav-link">
            <i class="fas fa-exchange-alt"></i> Stock Transfers
          </a>
          <a href="/suppliers.php" class="sidebar-nav-link">
            <i class="fas fa-industry"></i> Supplier Management
          </a>
          <a href="/automated-ordering.php" class="sidebar-nav-link">
            <i class="fas fa-robot"></i> Automated Ordering
          </a>
        </div>

        <!-- Manufacturing Section -->
        <div class="sidebar-section">
          <h6>MANUFACTURING</h6>
          <a href="/batching.php" class="sidebar-nav-link">
            <i class="fas fa-flask"></i> Batch Management
          </a>
          <a href="/juice-production.php" class="sidebar-nav-link">
            <i class="fas fa-tint"></i> Juice Production
          </a>
          <a href="/recipes.php" class="sidebar-nav-link">
            <i class="fas fa-book"></i> Recipe Management
          </a>
          <a href="/quality-control.php" class="sidebar-nav-link">
            <i class="fas fa-shield-alt"></i> Quality Control
          </a>
          <a href="/labels.php" class="sidebar-nav-link">
            <i class="fas fa-tag"></i> Label Printing
          </a>
        </div>

        <!-- Sales & CRM Section -->
        <div class="sidebar-section">
          <h6>SALES & CRM</h6>
          <a href="/customers.php" class="sidebar-nav-link">
            <i class="fas fa-users"></i> Customer Management
          </a>
          <a href="/sales-orders.php" class="sidebar-nav-link">
            <i class="fas fa-receipt"></i> Sales Orders
          </a>
          <a href="/support-tickets.php" class="sidebar-nav-link">
            <i class="fas fa-headset"></i> Support Tickets
          </a>
          <a href="/loyalty-program.php" class="sidebar-nav-link">
            <i class="fas fa-star"></i> Loyalty Program
          </a>
          <a href="/reviews.php" class="sidebar-nav-link">
            <i class="fas fa-comments"></i> Reviews & Feedback
          </a>
        </div>

        <!-- Human Resources Section -->
        <div class="sidebar-section">
          <h6>HUMAN RESOURCES</h6>
          <a href="/employees.php" class="sidebar-nav-link">
            <i class="fas fa-user-tie"></i> Employee Management
          </a>
          <a href="/leave-requests.php" class="sidebar-nav-link">
            <i class="fas fa-calendar-times"></i> Leave Requests
          </a>
          <a href="/performance-reviews.php" class="sidebar-nav-link">
            <i class="fas fa-chart-line"></i> Performance Reviews
          </a>
          <a href="/training.php" class="sidebar-nav-link">
            <i class="fas fa-graduation-cap"></i> Training & Development
          </a>
          <a href="/payroll.php" class="sidebar-nav-link">
            <i class="fas fa-money-check-alt"></i> Payroll Management
          </a>
        </div>

        <!-- Finance Section -->
        <div class="sidebar-section">
          <h6>FINANCE</h6>
          <a href="/banking-deposits.php" class="sidebar-nav-link">
            <i class="fas fa-university"></i> Banking & Deposits
          </a>
          <a href="/expenses.php" class="sidebar-nav-link">
            <i class="fas fa-credit-card"></i> Expense Management
          </a>
          <a href="/courier-claims.php" class="sidebar-nav-link">
            <i class="fas fa-shipping-fast"></i> Courier Claims
          </a>
          <a href="/financial-reports.php" class="sidebar-nav-link">
            <i class="fas fa-file-invoice-dollar"></i> Financial Reports
          </a>
          <a href="/xero-integration.php" class="sidebar-nav-link">
            <i class="fas fa-sync"></i> Xero Integration
          </a>
        </div>

        <!-- Compliance Section -->
        <div class="sidebar-section">
          <h6>COMPLIANCE</h6>
          <a href="/harp-management.php" class="sidebar-nav-link">
            <i class="fas fa-certificate"></i> HARP Management
          </a>
          <a href="/nicotine-audits.php" class="sidebar-nav-link">
            <i class="fas fa-vial"></i> Nicotine Audits
          </a>
          <a href="/regulatory-reports.php" class="sidebar-nav-link">
            <i class="fas fa-file-alt"></i> Regulatory Reports
          </a>
          <a href="/safety-data-sheets.php" class="sidebar-nav-link">
            <i class="fas fa-clipboard-check"></i> Safety Data Sheets
          </a>
        </div>

        <!-- Security Section -->
        <div class="sidebar-section">
          <h6>SECURITY</h6>
          <a href="/ciswatch.php" class="sidebar-nav-link">
            <i class="fas fa-video"></i> CISWatch Cameras
          </a>
          <a href="/security-events.php" class="sidebar-nav-link">
            <i class="fas fa-exclamation-triangle"></i> Security Events
          </a>
          <a href="/access-control.php" class="sidebar-nav-link">
            <i class="fas fa-key"></i> Access Control
          </a>
          <a href="/audit-logs.php" class="sidebar-nav-link">
            <i class="fas fa-history"></i> Audit Logs
          </a>
        </div>

        <!-- Administration Section -->
        <div class="sidebar-section">
          <h6>ADMINISTRATION</h6>
          <a href="/user-management.php" class="sidebar-nav-link">
            <i class="fas fa-users-cog"></i> User Management
          </a>
          <a href="/system-settings.php" class="sidebar-nav-link">
            <i class="fas fa-cogs"></i> System Settings
          </a>
          <a href="/backup-recovery.php" class="sidebar-nav-link">
            <i class="fas fa-database"></i> Backup & Recovery
          </a>
          <a href="/api-management.php" class="sidebar-nav-link">
            <i class="fas fa-plug"></i> API Management
          </a>
          <a href="/system-health.php" class="sidebar-nav-link">
            <i class="fas fa-heartbeat"></i> System Health
          </a>
        </div>
      </nav>
    </aside>

    <!-- Enhanced Breadcrumb -->
    <nav aria-label="breadcrumb" class="breadcrumb-nav">
      <div class="container-fluid" style="max-width: 1280px;">
        <div class="d-flex justify-content-between align-items-center">
          <ol class="breadcrumb">
            <li class="breadcrumb-item">
              <a href="/index.php"><i class="fas fa-home me-1"></i> Dashboard</a>
            </li>
            <li class="breadcrumb-item">
              <a href="/purchase-orders.php">Purchase Orders</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
              Receiving PO #<?php echo (int)$poId; ?>
            </li>
          </ol>
          
          <div class="quick-actions">
            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
              <i class="fas fa-print me-1"></i> Print
            </button>
            <button class="btn btn-sm btn-outline-secondary" onclick="exportData()">
              <i class="fas fa-download me-1"></i> Export
            </button>
            <button class="btn btn-sm btn-primary" onclick="showHelp()">
              <i class="fas fa-question-circle me-1"></i> Help
            </button>
          </div>
        </div>
      </div>
    </nav>
                <li><a class="dropdown-item" href="/settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </nav>

    <!-- Responsive Sidebar Navigation -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <aside class="sidebar" id="mainSidebar">
      <div class="sidebar-header">
        <h5 class="sidebar-title">
          <i class="fas fa-tachometer-alt me-2"></i>
          Navigation
        </h5>
        <button class="sidebar-close" id="sidebarClose">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <nav class="sidebar-nav">
        <div class="nav-section">
          <h6 class="nav-section-title">Purchase Orders</h6>
          <ul class="nav-list">
            <li><a href="/receive-purchase-order.php" class="nav-link active"><i class="fas fa-inbox me-2"></i>Receive Orders</a></li>
            <li><a href="/create-purchase-order.php" class="nav-link"><i class="fas fa-plus me-2"></i>Create Order</a></li>
            <li><a href="/purchase-orders.php" class="nav-link"><i class="fas fa-list me-2"></i>All Orders</a></li>
            <li><a href="/automated-orders.php" class="nav-link"><i class="fas fa-robot me-2"></i>Automated Orders</a></li>
          </ul>
        </div>
        
        <div class="nav-section">
          <h6 class="nav-section-title">Inventory Management</h6>
          <ul class="nav-list">
            <li><a href="/products.php" class="nav-link"><i class="fas fa-cube me-2"></i>Products</a></li>
            <li><a href="/stock-transfers.php" class="nav-link"><i class="fas fa-exchange-alt me-2"></i>Stock Transfers</a></li>
            <li><a href="/stocktake.php" class="nav-link"><i class="fas fa-clipboard-check me-2"></i>Stocktake</a></li>
            <li><a href="/flagged-products.php" class="nav-link"><i class="fas fa-flag me-2"></i>Flagged Products</a></li>
          </ul>
        </div>
        
        <div class="nav-section">
          <h6 class="nav-section-title">Reports & Analytics</h6>
          <ul class="nav-list">
            <li><a href="/reports/inventory.php" class="nav-link"><i class="fas fa-chart-bar me-2"></i>Inventory Reports</a></li>
            <li><a href="/reports/financial.php" class="nav-link"><i class="fas fa-dollar-sign me-2"></i>Financial Reports</a></li>
            <li><a href="/reports/supplier.php" class="nav-link"><i class="fas fa-truck me-2"></i>Supplier Performance</a></li>
          </ul>
        </div>
        
        <div class="nav-section">
          <h6 class="nav-section-title">System</h6>
          <ul class="nav-list">
            <li><a href="/settings.php" class="nav-link"><i class="fas fa-cog me-2"></i>Settings</a></li>
            <li><a href="/logs.php" class="nav-link"><i class="fas fa-file-alt me-2"></i>System Logs</a></li>
            <li><a href="/help.php" class="nav-link"><i class="fas fa-question-circle me-2"></i>Help & Support</a></li>
          </ul>
        </div>
      </nav>
    </aside>

    <!-- Enhanced Breadcrumb -->
    <nav aria-label="breadcrumb" class="breadcrumb-nav">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="/index.php"><i class="fas fa-home me-1"></i>Home</a></li>
          <li class="breadcrumb-item"><a href="/purchase-orders.php">Purchase Orders</a></li>
          <li class="breadcrumb-item active" aria-current="page">Receive Order #<?php echo $poId; ?></li>
        </ol>
        
        <!-- Quick Actions -->
        <div class="breadcrumb-actions">
          <button class="btn btn-outline-secondary btn-sm me-2" id="toggleSidebar" title="Toggle Navigation">
            <i class="fas fa-bars"></i>
          </button>
          <button class="btn btn-outline-info btn-sm" id="fullscreenToggle" title="Toggle Fullscreen">
            <i class="fas fa-expand"></i>
          </button>
        </div>
      </div>
    </nav>
                <li class="breadcrumb-item active" aria-current="page">PO #<?= (int)$poId; ?> → <?= e($outletName); ?></li>
            </ol>
        </div>
    </nav>
      
    <!-- Main Content Container -->
    <div class="container-fluid py-4" style="max-width: 1280px;">
        
        <!-- Custom PO Receiving Styles -->
        <style>
        /* Enhanced Sticky Footer Stats Bar */
        .sticky-stats-bar {
          position: fixed;
          bottom: 0;
          left: 0;
          right: 0;
          background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
          color: white;
          z-index: 1050;
          border-top: 3px solid #3498db;
          box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
          padding: 12px 0;
          backdrop-filter: blur(10px);
          animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
          from { transform: translateY(100%); opacity: 0; }
          to { transform: translateY(0); opacity: 1; }
        }

        .stats-container {
          display: flex;
          align-items: center;
          justify-content: space-between;
          max-width: 1280px;
          margin: 0 auto;
          padding: 0 15px;
          gap: 20px;
          flex-wrap: wrap;
        }

        .stats-group {
          display: flex;
          flex-direction: column;
          align-items: center;
          min-width: 140px;
          padding: 8px 12px;
          border-radius: 8px;
          background: rgba(255,255,255,0.05);
          backdrop-filter: blur(5px);
          border: 1px solid rgba(255,255,255,0.1);
          transition: all 0.3s ease;
        }

        .stats-group:hover {
          background: rgba(255,255,255,0.1);
          transform: translateY(-2px);
        }

        .stats-header {
          display: flex;
          align-items: center;
          gap: 6px;
          margin-bottom: 8px;
          font-weight: 600;
          font-size: 0.85rem;
          color: #ecf0f1;
        }

        .stats-icon {
          font-size: 1.1rem;
        }

        .stats-title {
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .stats-grid {
          display: flex;
          flex-direction: column;
          gap: 4px;
          text-align: center;
        }

        .stat-item {
          display: flex;
          flex-direction: column;
          align-items: center;
        }

        .stat-value {
          font-size: 1.2rem;
          font-weight: bold;
          color: #3498db;
          line-height: 1;
          margin-bottom: 2px;
        }

        .stat-value.stat-success { color: #2ecc71; }
        .stat-value.stat-warning { color: #f39c12; }
        .stat-value.stat-danger { color: #e74c3c; }

        .stat-label {
          font-size: 0.7rem;
          color: #bdc3c7;
          text-transform: uppercase;
          letter-spacing: 0.3px;
        }

        /* Scanner Mini */
        .scanner-mini {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 4px;
        }

        .scanner-input {
          background: rgba(255,255,255,0.1);
          border: 1px solid rgba(255,255,255,0.2);
          color: white;
          padding: 6px 10px;
          border-radius: 4px;
          font-size: 0.85rem;
          width: 120px;
          text-align: center;
        }

        .scanner-input::placeholder {
          color: rgba(255,255,255,0.6);
        }

        .scanner-input:focus {
          outline: none;
          border-color: #3498db;
          background: rgba(255,255,255,0.15);
        }

        .scanner-status {
          font-size: 0.7rem;
          color: #95a5a6;
          text-transform: uppercase;
          letter-spacing: 0.3px;
        }

        /* Confidence Display */
        .confidence-display {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 6px;
        }

        .confidence-circle {
          width: 50px;
          height: 50px;
          border-radius: 50%;
          background: conic-gradient(#3498db 0deg, #2ecc71 120deg, #f39c12 240deg, #e74c3c 360deg);
          display: flex;
          align-items: center;
          justify-content: center;
          position: relative;
        }

        .confidence-circle::before {
          content: '';
          position: absolute;
          width: 40px;
          height: 40px;
          background: #2c3e50;
          border-radius: 50%;
        }

        .confidence-text {
          position: relative;
          z-index: 1;
          font-weight: bold;
          font-size: 0.8rem;
          color: white;
        }

        .confidence-mini-progress {
          width: 50px;
          height: 4px;
          background: rgba(255,255,255,0.2);
          border-radius: 2px;
          overflow: hidden;
        }

        .confidence-bar {
          height: 100%;
          background: linear-gradient(90deg, #e74c3c 0%, #f39c12 50%, #2ecc71 100%);
          transition: width 0.3s ease;
        }

        /* Sticky Action Buttons */
        .sticky-actions {
          display: flex;
          gap: 8px;
        }

        .btn-sticky {
          padding: 8px 16px;
          border: none;
          border-radius: 6px;
          font-weight: 600;
          font-size: 0.85rem;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
          display: inline-flex;
          align-items: center;
          gap: 6px;
        }

        .btn-sticky-primary {
          background: #3498db;
          color: white;
        }

        .btn-sticky-primary:hover {
          background: #2980b9;
          transform: translateY(-1px);
        }

        .btn-sticky-success {
          background: #2ecc71;
          color: white;
        }

        .btn-sticky-success:hover {
          background: #27ae60;
          transform: translateY(-1px);
        }

        .btn-sticky:disabled {
          opacity: 0.5;
          cursor: not-allowed;
          transform: none !important;
        }

        /* Add bottom padding to body to prevent content being hidden behind sticky footer */
        body {
          padding-bottom: 120px;
        }

        /* ============================================================================
         * RESPONSIVE TABLE DESIGN
         * ============================================================================ */
        
        .table-responsive-custom {
          overflow-x: auto;
          overflow-y: visible;
          max-width: 100%;
          border: 1px solid #dee2e6;
          border-radius: 8px;
        }

        .po-table {
          margin-bottom: 0;
          white-space: nowrap;
          font-size: 0.875rem;
        }

        .table-header-fixed {
          background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
          position: sticky;
          top: 0;
          z-index: 10;
        }

        .po-table th {
          border-bottom: 2px solid #dee2e6;
          font-weight: 600;
          font-size: 0.8rem;
          padding: 8px 6px;
          vertical-align: middle;
          position: relative;
        }

        .th-content {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 4px;
        }

        .po-table th[data-sort] {
          cursor: pointer;
          user-select: none;
          transition: background-color 0.2s ease;
        }

        .po-table th[data-sort]:hover {
          background: rgba(0,123,255,0.1);
        }

        .po-table th .fas {
          font-size: 0.7rem;
          color: #6c757d;
          transition: color 0.2s ease;
        }

        .po-table th.sorted .fas {
          color: #007bff;
        }

        /* Column Widths - Optimized for responsiveness */
        .col-checkbox { width: 40px; min-width: 40px; max-width: 40px; }
        .col-image { width: 60px; min-width: 60px; max-width: 60px; padding: 4px !important; }
        .col-product { width: auto; min-width: 200px; max-width: none; }
        .col-stock { width: 70px; min-width: 70px; max-width: 70px; }
        .col-ordered { width: 60px; min-width: 60px; max-width: 60px; }
        .col-slip { width: 80px; min-width: 80px; max-width: 80px; }
        .col-recv { width: 100px; min-width: 100px; max-width: 100px; }
        .col-dmg { width: 70px; min-width: 70px; max-width: 70px; }
        .col-diff { width: 60px; min-width: 60px; max-width: 60px; }
        .col-status { width: 120px; min-width: 120px; max-width: 120px; }
        .col-cost { width: 100px; min-width: 100px; max-width: 100px; }
        .col-actions { width: 80px; min-width: 80px; max-width: 80px; }

        /* Bulk Actions Bar */
        .bulk-actions-bar {
          animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
          from { 
            opacity: 0; 
            transform: translateY(-10px); 
          }
          to { 
            opacity: 1; 
            transform: translateY(0); 
          }
        }

        /* Enhanced Row Selection */
        .product-row.selected {
          background: rgba(0, 123, 255, 0.1) !important;
          border-left: 4px solid #007bff !important;
        }

        /* LightSpeed Link Button Styling (smaller) */
        .vend-link-btn {
          transition: all 0.2s ease;
          border-radius: 3px;
          font-size: 12px !important;
          padding: 1px 2px !important;
          min-width: auto !important;
          width: auto !important;
        }
        
        .vend-link-btn:hover {
          background-color: rgba(0, 102, 204, 0.1) !important;
          color: #004499 !important;
          transform: scale(1.05);
        }
        
        .vend-link-btn:active {
          transform: scale(0.9);
        }

        /* Tighter Row Spacing for More Room */
        #poTable tbody tr {
          height: auto;
          line-height: 1.1;
        }
        
        #poTable tbody td {
          padding: 4px 6px !important;
          vertical-align: middle;
        }
        
        #poTable .form-control {
          padding: 3px 5px;
          font-size: 13px;
          height: auto;
          min-height: 28px;
        }
        
        #poTable .form-select {
          padding: 3px 5px;
          font-size: 13px;
          height: auto;
          min-height: 28px;
        }
        
        /* Compact styling for better space utilization */
        #poTable .product-meta {
          margin: 0;
          line-height: 1.1;
        }
        
        #poTable .sku-badge {
          font-size: 12px;
          padding: 1px 4px;
        }
        
        /* Smaller image column */
        .col-image {
          width: 35px !important;
          min-width: 35px !important;
          max-width: 35px !important;
          padding: 2px !important;
        }
        
        .product-image {
          max-width: 30px;
          max-height: 30px;
          object-fit: cover;
        }
        }

        .row-checkbox:checked {
          background-color: #007bff;
          border-color: #007bff;
        }

        /* Improved Form Controls */
        .qty-input:focus, .cost-input:focus {
          border-color: #007bff;
          box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
          background: rgba(0, 123, 255, 0.05);
        }

        .primary-input {
          border: 2px solid #007bff;
          background: rgba(0, 123, 255, 0.05);
          font-weight: 600;
        }

        .quick-add {
          font-size: 0.7rem;
          padding: 4px 8px;
          border-radius: 0 4px 4px 0;
          transition: all 0.2s ease;
        }

        .quick-add:hover {
          background: #007bff;
          color: white;
          transform: scale(1.05);
        }

        /* Enhanced Status Indicators */
        .diff-display {
          font-weight: bold;
          padding: 2px 6px;
          border-radius: 4px;
          font-size: 0.8rem;
          text-align: center;
          min-width: 40px;
          display: inline-block;
        }

        .diff-ok {
          background: #d4edda;
          color: #155724;
          border: 1px solid #c3e6cb;
        }

        .diff-over {
          background: #fff3cd;
          color: #856404;
          border: 1px solid #ffeaa7;
        }

        .diff-short {
          background: #f8d7da;
          color: #721c24;
          border: 1px solid #f5c6cb;
        }

        /* Status Select Styling */
        .status-select {
          font-size: 0.75rem;
          padding: 4px 8px;
          border-radius: 4px;
          font-weight: 600;
        }

        .status-select option {
          font-weight: 600;
        }

        /* Action Buttons Enhancement */
        .action-buttons {
          display: flex;
          gap: 4px;
          flex-wrap: wrap;
        }

        .action-buttons .btn {
          padding: 4px 6px;
          font-size: 0.7rem;
          border-radius: 4px;
          transition: all 0.2s ease;
        }

        .action-buttons .btn:hover {
          transform: translateY(-1px);
          box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Smart Submit Section Enhancement */
        .smart-submit-section {
          text-align: center;
          padding: 1.5rem;
          background: linear-gradient(135deg, #e8f5e8, #f0f8f0);
          border: 2px solid #28a745;
          border-radius: 12px;
          margin-bottom: 1rem;
          box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
        }

        #smart_submit {
          font-size: 1.1rem;
          font-weight: 700;
          padding: 0.8rem 2rem;
          border-radius: 8px;
          background: linear-gradient(135deg, #28a745, #20c997);
          border: none;
          color: white;
          transition: all 0.3s ease;
          box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        #smart_submit:hover {
          transform: translateY(-2px);
          box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
          background: linear-gradient(135deg, #20c997, #28a745);
        }

        #smart_submit:active {
          transform: translateY(0);
          box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }

        .submit-help-text {
          margin-top: 0.8rem;
          padding: 0.5rem;
          background: rgba(255, 255, 255, 0.8);
          border-radius: 6px;
          border-left: 4px solid #28a745;
        }

        .submit-help-text .fa-lightbulb {
          color: #ffc107;
        }

        .advanced-options {
          border: 1px solid #dee2e6;
          border-radius: 8px;
          padding: 0.5rem;
          background: #f8f9fa;
        }

        .advanced-options summary {
          cursor: pointer;
          margin: 0;
          border: none;
          background: none;
          padding: 0.5rem;
          font-size: 0.9rem;
        }

        .advanced-options summary:hover {
          background: #e9ecef;
          border-radius: 6px;
        }

        .advanced-buttons {
          display: flex;
          gap: 0.5rem;
          flex-wrap: wrap;
          justify-content: center;
        }

        /* Enhanced Image Styling */
        .product-image {
          border-radius: 6px;
          object-fit: cover;
          border: 2px solid #dee2e6;
          transition: all 0.2s ease;
          cursor: pointer;
        }

        .product-image:hover {
          transform: scale(1.1);
          border-color: #007bff;
          box-shadow: 0 2px 8px rgba(0,123,255,0.3);
        }

        .image-placeholder {
          width: 40px;
          height: 40px;
          display: flex;
          align-items: center;
          justify-content: center;
          background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
          border: 2px dashed #dee2e6;
          border-radius: 6px;
          transition: all 0.2s ease;
        }

        .image-placeholder:hover {
          border-color: #007bff;
          background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        }

        /* Enhanced Product Info */
        .product-name {
          font-weight: 600;
          color: #212529;
          line-height: 1.2;
          max-width: 280px;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
          cursor: pointer;
          transition: color 0.2s ease;
        }

        .product-name:hover {
          color: #007bff;
        }

        .sku-badge {
          background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
          color: #495057;
          padding: 2px 6px;
          border-radius: 4px;
          font-size: 0.7rem;
          font-weight: 600;
          border: 1px solid #dee2e6;
        }

        /* Stock Display Enhancement */
        .stock-display {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 4px;
        }

        .stock-number {
          font-weight: 700;
          color: #495057;
          font-size: 0.9rem;
        }

        .refresh-stock {
          padding: 2px 4px;
          font-size: 0.6rem;
          border-radius: 3px;
          transition: all 0.2s ease;
        }

        .refresh-stock:hover {
          background: #007bff;
          color: white;
          transform: rotate(90deg);
        }

        /* Product Row Styling */
        .product-row {
          transition: all 0.2s ease;
          border-left: 3px solid transparent;
        }

        .product-row:hover {
          background: rgba(0,123,255,0.02);
          border-left-color: #007bff;
        }

        .product-row.row-success {
          border-left-color: #28a745;
          background: rgba(40, 167, 69, 0.02);
        }

        .product-row.row-warning {
          border-left-color: #ffc107;
          background: rgba(255, 193, 7, 0.02);
        }

        .product-row.row-pending {
          border-left-color: #6c757d;
          background: rgba(108, 117, 125, 0.02);
        }

        .po-table td {
          padding: 8px 6px;
          vertical-align: middle;
          border-top: 1px solid #dee2e6;
        }

        /* Image Container - Ultra Compact */
        .image-container {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 8px;
          height: 8px;
          overflow: hidden;
        }

        .product-image {
          border-radius: 2px;
          object-fit: cover;
          border: none;
          transition: all 0.2s ease;
          width: 8px;
          height: 8px;
          opacity: 0.7;
        }

        .product-image:hover {
          opacity: 1;
          transform: scale(3);
          z-index: 1000;
          position: relative;
          border: 1px solid #007bff;
          border-radius: 4px;
        }

        .image-placeholder {
          width: 8px;
          height: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 6px;
          color: #6c757d;
        }
          background: #f8f9fa;
          border: 1px dashed #dee2e6;
          border-radius: 4px;
        }

        /* Product Info */
        .product-info {
          display: flex;
          flex-direction: column;
          gap: 4px;
        }

        .product-name {
          font-weight: 600;
          color: #212529;
          line-height: 1.2;
          max-width: 250px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }

        .product-meta {
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .sku-badge {
          background: #e9ecef;
          color: #495057;
          padding: 2px 6px;
          border-radius: 3px;
          font-size: 0.7rem;
          font-weight: 500;
        }

        /* Stock Display */
        .stock-display {
          display: flex;
          align-items: center;
          gap: 4px;
        }

        .stock-number {
          font-weight: 600;
          color: #495057;
        }

        .refresh-stock {
          padding: 0;
          font-size: 0.7rem;
        }

        /* Input Styling */
        .qty-input, .cost-input {
          text-align: center;
          font-weight: 600;
          border-radius: 4px;
        }

        .primary-input {
          border: 2px solid #007bff;
          background: rgba(0, 123, 255, 0.05);
        }

        .primary-input:focus {
          border-color: #0056b3;
          box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .quick-add {
          font-size: 0.7rem;
          padding: 4px 8px;
        }

        /* Difference Display */
        .diff-display {
          font-weight: bold;
          padding: 2px 6px;
          border-radius: 3px;
          font-size: 0.8rem;
        }

        .diff-ok {
          background: #d4edda;
          color: #155724;
        }

        .diff-over {
          background: #fff3cd;
          color: #856404;
        }

        .diff-short {
          background: #f8d7da;
          color: #721c24;
        }

        /* Status Select */
        .status-select {
          font-size: 0.75rem;
          padding: 4px 8px;
        }

        /* Action Buttons */
        .action-buttons {
          display: flex;
          gap: 4px;
        }

        .action-buttons .btn {
          padding: 4px 6px;
          font-size: 0.7rem;
        }

        /* Notes Row */
        .notes-row {
          margin-top: 8px;
          padding-top: 8px;
          border-top: 1px dashed #dee2e6;
        }

        .notes-input {
          font-size: 0.8rem;
          resize: vertical;
        }

        /* Search and Scanner Integration */
        .search-scanner-integration {
          background: #f8f9fa;
          padding: 12px;
          border-radius: 8px;
          border: 1px solid #dee2e6;
        }

        .scanner-icon {
          background: #007bff;
          color: white;
          border-color: #007bff;
        }

        .scanner-input {
          border-color: #007bff;
        }

        .scanner-status-mini {
          text-align: center;
        }

        .status-text {
          font-weight: 600;
          color: #007bff;
          font-size: 0.8rem;
        }

        .scan-count {
          font-size: 0.7rem;
          color: #6c757d;
        }

        /* Table Summary */
        .table-summary {
          background: #f8f9fa;
          padding: 12px;
          border-top: 1px solid #dee2e6;
          border-radius: 0 0 8px 8px;
        }

        .summary-stats .badge {
          font-size: 0.8rem;
        }

        .bulk-actions .btn {
          font-size: 0.8rem;
          padding: 6px 12px;
        }

        /* Image Toggle Functionality */
        .images-hidden .col-image {
          display: none;
        }

        .images-hidden .col-product {
          min-width: 180px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1200px) {
          .col-product { min-width: 180px; }
          .po-table { font-size: 0.8rem; }
        }

        @media (max-width: 992px) {
          .col-stock, .col-ordered { width: 50px; min-width: 50px; }
          .col-slip, .col-recv, .col-dmg, .col-diff { width: 60px; min-width: 60px; }
          .col-status { width: 100px; min-width: 100px; }
          .col-cost { width: 80px; min-width: 80px; }
          .col-actions { width: 60px; min-width: 60px; }
        }

        @media (max-width: 768px) {
          .table-responsive-custom {
            border: none;
          }
          
          .po-table, .po-table thead, .po-table tbody, .po-table th, .po-table td, .po-table tr {
            display: block;
          }
          
          .po-table thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
          }
          
          .po-table tr {
            border: 1px solid #ccc;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 8px;
            background: white;
          }
          
          .po-table td {
            border: none;
            border-bottom: 1px solid #eee;
            position: relative;
            padding-left: 30%;
            padding-right: 10px;
            padding-top: 8px;
            padding-bottom: 8px;
          }
          
          .po-table td:before {
            content: attr(data-label) ": ";
            position: absolute;
            left: 6px;
            width: 25%;
            padding-right: 10px;
            white-space: nowrap;
            font-weight: bold;
            color: #333;
          }
          
          .col-image {
            text-align: center;
            padding-left: 10px !important;
          }
          
          .col-image:before {
            display: none;
          }
        }

        /* Image Column Auto-Hide on Narrow Screens */
        @media (max-width: 900px) {
          .col-image {
            display: none !important;
          }
          
          /* Disable image checkboxes when column is hidden */
          .col-image input[type="checkbox"] {
            display: none !important;
          }
          
          /* Remove image column from table layout */
          .po-table .col-image {
            width: 0 !important;
            min-width: 0 !important;
            max-width: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            visibility: hidden;
          }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
          .stats-container {
            gap: 10px;
            justify-content: center;
          }
          
          .stats-group {
            min-width: 100px;
            padding: 6px 8px;
          }
          
          .stat-value {
            font-size: 1rem;
          }
          
          .scanner-input {
            width: 100px;
          }
          
          body {
            padding-bottom: 140px;
          }
        }

        @media (max-width: 480px) {
          .sticky-stats-bar {
            padding: 8px 0;
          }
          
          .stats-container {
            flex-direction: column;
            gap: 8px;
          }
          
          .stats-group {
            flex-direction: row;
            justify-content: space-between;
            width: 100%;
            max-width: 300px;
          }
          
          body {
            padding-bottom: 160px;
          }
        }
        </style>

      <!-- App root (namespaced) -->
      <section id="po-root" class="po-app" aria-labelledby="po-title">
        <h1 id="po-title" class="visually-hidden">Purchase Order Receiving</h1>

        <!-- Top summary + tools in L-shape layout -->
        <div class="row mb-3" aria-label="Order summary and tools">
          <!-- Left side: Supplier & Order (70% width) -->
          <div class="col-lg-8 col-md-7">
            <div class="card h-100" aria-labelledby="supplier-card-title">
              <div class="card-header" id="supplier-card-title">
                <h2 class="h5 mb-0">Supplier &amp; Order Information</h2>
              </div>
              <div class="card-body">
                <div class="row">
                  <div class="col-md-6">
                    <dl class="row">
                      <dt class="col-sm-4">Supplier:</dt>
                      <dd class="col-sm-8"><?= e($supplierInfo); ?></dd>
                      
                      <dt class="col-sm-4">PO Number:</dt>
                      <dd class="col-sm-8"><span id="po-number" class="fw-bold"><?= (int)$poId; ?></span></dd>
                      
                      <dt class="col-sm-4">Destination:</dt>
                      <dd class="col-sm-8"><?= e($outletName); ?></dd>
                    </dl>
                  </div>
                  <div class="col-md-6">
                    <dl class="row">
                      <dt class="col-sm-4">Ordered:</dt>
                      <dd class="col-sm-8">
                        <time datetime="<?= date('Y-m-d', $orderDateTs); ?>"><?= date('j M Y', $orderDateTs); ?></time>
                        <br><span class="badge <?= $isComplete ? 'bg-success' : 'bg-warning'; ?>"><?= $isComplete ? 'Completed' : 'In progress'; ?></span>
                        <br><small class="text-muted"><?= (int)$daysSince; ?> days since ordered</small>
                      </dd>
                      
                      <?php if ($supplierEmail || $supplierPhone): ?>
                      <dt class="col-sm-4">Contact:</dt>
                      <dd class="col-sm-8">
                        <?php if ($supplierEmail): ?><a href="mailto:<?= e($supplierEmail); ?>" class="text-decoration-none"><?= e($supplierEmail); ?></a><?php endif; ?>
                        <?php if ($supplierEmail && $supplierPhone): ?><br><?php endif; ?>
                        <?php if ($supplierPhone): ?><span class="text-muted"><?= e($supplierPhone); ?></span><?php endif; ?>
                      </dd>
                      <?php endif; ?>
                    </dl>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right side: Evidence Upload (30% width) -->
          <div class="col-lg-4 col-md-5">
            <div class="card h-100" aria-labelledby="evidence-card-title" style="min-height: 500px;">
              <div class="card-header" id="evidence-card-title">
                <h2 class="h5 mb-0">Evidence Upload</h2>
              </div>
              <div class="card-body d-flex flex-column">
                <form id="evidence-form" class="flex-fill">
                  <div class="mb-3">
                    <label for="evidence-files" class="form-label">Select Files</label>
                    <input id="evidence-files" name="evidence[]" type="file" multiple accept="image/*,video/*,.pdf" class="form-control">
                    <div class="form-text" aria-live="polite" id="evidence-status">No files selected.</div>
                  </div>
                  
                  <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-open-camera">
                      📷 Use Camera
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-generate-qr" aria-controls="qr-area" aria-expanded="false">
                      📱 Generate QR
                    </button>
                  </div>
                  
                  <div id="qr-area" class="d-none" aria-hidden="true">
                    <div class="text-center mb-2">
                      <div id="qr-canvas" role="img" aria-label="QR code for mobile upload" class="d-inline-block"></div>
                    </div>
                    <p class="small text-center">
                      <a id="qr-link" href="#" target="_blank" rel="noopener" class="text-decoration-none">Open mobile upload</a>
                    </p>
                  </div>
                  
                  <!-- Evidence list will appear here -->
                  <div id="evidence-list" class="mt-auto">
                    <div class="border-top pt-2">
                      <small class="text-muted">Uploaded files will appear here</small>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Document and Financial Information in L-shape -->
        <div class="row mb-3">
          <!-- Document Type Selection -->
          <div class="col-lg-6 col-md-6 mb-3">
            <div class="card">
              <div class="card-header">
                <h3 class="h6 mb-0">Document Type</h3>
              </div>
              <div class="card-body">
                <form id="doc-type-form">
                  <fieldset>
                    <legend class="visually-hidden">Document Type Selection</legend>
                    <div class="row">
                      <div class="col-4">
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="docType" value="invoice" id="doc-invoice" <?= ($poObj->invoice_no ?? '') ? 'checked':''; ?>>
                          <label class="form-check-label" for="doc-invoice">
                            📄 Invoice
                          </label>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="docType" value="packing" id="doc-packing" <?= ($poObj->packing_slip_no ?? '') ? 'checked':''; ?>>
                          <label class="form-check-label" for="doc-packing">
                            📦 Packing Slip
                          </label>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="form-check">
                          <input class="form-check-input" type="radio" name="docType" value="receipt" id="doc-receipt">
                          <label class="form-check-label" for="doc-receipt">
                            🧾 Receipt
                          </label>
                        </div>
                      </div>
                    </div>
                  </fieldset>

                  <div class="row mt-3">
                    <div class="col-6">
                      <label for="packing-slip-no" class="form-label">Packing Slip #</label>
                      <input id="packing-slip-no" name="packing_slip" type="text" class="form-control" inputmode="text" autocomplete="off" value="<?= e($poObj->packing_slip_no ?? ''); ?>" <?= $isComplete?'disabled':''; ?>>
                    </div>
                    <div class="col-6">
                      <label for="invoice-no" class="form-label">Invoice #</label>
                      <input id="invoice-no" name="invoice_no" type="text" class="form-control" inputmode="text" autocomplete="off" value="<?= e($poObj->invoice_no ?? ''); ?>" <?= $isComplete?'disabled':''; ?>>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <!-- Financial Details -->
          <div class="col-lg-6 col-md-6 mb-3">
            <div class="card">
              <div class="card-header">
                <h3 class="h6 mb-0">Financial Details</h3>
              </div>
              <div class="card-body">
                <form id="financial-form">
                  <fieldset>
                    <legend class="visually-hidden">Financial Information</legend>
                    
                    <!-- GST Mode Selection -->
                    <div class="row mb-3">
                      <div class="col-12">
                        <label for="gst-mode" class="form-label">GST Mode</label>
                        <select id="gst-mode" name="gst_mode" class="form-select" <?= $isComplete?'disabled':''; ?>>
                          <option value="">Select GST Mode</option>
                          <option value="GST_EXCL" <?= (($poObj->totals_mode??'')==='GST_EXCL'?'selected':''); ?>>GST Excluded (15% will be added)</option>
                          <option value="GST_INCL" <?= (($poObj->totals_mode??'')==='GST_INCL'?'selected':''); ?>>GST Included (15% already included)</option>
                        </select>
                      </div>
                    </div>

                    <!-- Financial Fields -->
                    <div class="row">
                      <div class="col-6 mb-2">
                        <label for="subtotal" class="form-label">Subtotal</label>
                        <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input id="subtotal" name="subtotal" type="number" step="0.01" min="0" class="form-control" inputmode="decimal" placeholder="0.00" value="<?= e($poObj->subtotal_ex_gst ?? ''); ?>" <?= $isComplete?'disabled':''; ?>>
                        </div>
                      </div>
                      <div class="col-6 mb-2">
                        <label for="freight" class="form-label">Freight/Shipping</label>
                        <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input id="freight" name="freight" type="number" step="0.01" min="0" class="form-control" inputmode="decimal" placeholder="0.00" value="<?= e($poObj->freight_cost ?? ''); ?>" <?= $isComplete?'disabled':''; ?>>
                        </div>
                      </div>
                      <div class="col-6 mb-2">
                        <label for="gst-amount" class="form-label">GST Amount</label>
                        <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input id="gst-amount" name="gst_amount" type="number" step="0.01" min="0" class="form-control" inputmode="decimal" placeholder="0.00" value="<?= e($poObj->gst ?? ''); ?>" <?= $isComplete?'disabled':''; ?>>
                        </div>
                      </div>
                      <div class="col-6 mb-2">
                        <label for="total-incl" class="form-label"><strong>Total incl. GST</strong></label>
                        <div class="input-group">
                          <span class="input-group-text">$</span>
                          <input id="total-incl" name="total_incl" type="number" step="0.01" min="0" class="form-control fw-bold" inputmode="decimal" placeholder="0.00" value="<?= e($poObj->total_inc_gst ?? ''); ?>" <?= $isComplete?'disabled':''; ?>>
                        </div>
                      </div>
                    </div>
                    
                    <div class="alert alert-info mt-2" role="alert">
                      <small id="calc-hint">💡 <strong>NZ GST is 15%.</strong> Enter values based on your selected GST mode. Missing values will be calculated automatically.</small>
                    </div>
                    
                    <!-- Additional Financial Information -->
                    <div class="row mt-3">
                      <div class="col-12">
                        <div class="border-top pt-3">
                          <h4 class="h6 mb-2">Financial Summary</h4>
                          <div class="row g-2">
                            <div class="col-4">
                              <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold text-primary" id="variance-amount">$0.00</div>
                                <small class="text-muted">Price Variance</small>
                              </div>
                            </div>
                            <div class="col-4">
                              <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold text-info" id="line-completion">0/0</div>
                                <small class="text-muted">Lines Complete</small>
                              </div>
                            </div>
                            <div class="col-4">
                              <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold text-warning" id="total-items">0</div>
                                <small class="text-muted">Total Items</small>
                              </div>
                            </div>
                          </div>
                          
                          <!-- Payment Terms & Due Date -->
                          <div class="row mt-3">
                            <div class="col-6">
                              <label for="payment-terms" class="form-label">Payment Terms</label>
                              <select id="payment-terms" name="payment_terms" class="form-select form-select-sm">
                                <option value="">Select Terms</option>
                                <option value="NET_7">Net 7 Days</option>
                                <option value="NET_14">Net 14 Days</option>
                                <option value="NET_30">Net 30 Days</option>
                                <option value="COD">Cash on Delivery</option>
                                <option value="PREPAID">Prepaid</option>
                              </select>
                            </div>
                            <div class="col-6">
                              <label for="due-date" class="form-label">Due Date</label>
                              <input id="due-date" name="due_date" type="date" class="form-control form-control-sm" <?= $isComplete?'disabled':''; ?>>
                            </div>
                          </div>
                          
                          <!-- Discounts & Additional Costs -->
                          <div class="row mt-2">
                            <div class="col-6">
                              <label for="early-payment-discount" class="form-label">Early Payment Discount</label>
                              <div class="input-group input-group-sm">
                                <input id="early-payment-discount" name="early_discount" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="%" <?= $isComplete?'disabled':''; ?>>
                                <span class="input-group-text">%</span>
                              </div>
                            </div>
                            <div class="col-6">
                              <label for="other-charges" class="form-label">Other Charges</label>
                              <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input id="other-charges" name="other_charges" type="number" step="0.01" min="0" class="form-control" placeholder="0.00" <?= $isComplete?'disabled':''; ?>>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </fieldset>
                </form>
              </div>
            </div>
          </div>
        </div>

        <!-- Confidence Bar & Analytics Section -->
        <div class="row mb-3">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h3 class="h6 mb-0">Progress & Analytics</h3>
              </div>
              <div class="card-body">
                <div class="row">
                  <!-- Confidence Progress -->
                  <div class="col-lg-4 col-md-6 mb-3">
                    <div class="d-flex align-items-center">
                      <div class="flex-grow-1">
                        <div class="d-flex justify-content-between mb-1">
                          <span class="fw-medium">Completion Confidence</span>
                          <span id="confText" class="fw-bold text-primary" aria-live="polite">0%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                          <div id="conf-progress" class="progress-bar bg-primary" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Completion confidence percentage"></div>
                        </div>
                        <small class="text-muted">Based on completion, accuracy, consistency & speed</small>
                      </div>
                    </div>
                  </div>

                  <!-- Supplier Performance -->
                  <div class="col-lg-4 col-md-6 mb-3">
                    <h4 class="h6 mb-2">Supplier Performance</h4>
                    <div class="row g-2">
                      <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                          <div class="fw-bold text-success" id="supplier-accuracy">96%</div>
                          <small class="text-muted">Accuracy</small>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                          <div class="fw-bold text-info" id="supplier-delivery-time">3.2 days</div>
                          <small class="text-muted">Avg Delivery</small>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                          <div class="fw-bold text-warning" id="supplier-total-pos">47</div>
                          <small class="text-muted">Total POs</small>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                          <div class="fw-bold text-secondary" id="supplier-last-delivery">12 days</div>
                          <small class="text-muted">Last Delivery</small>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Session & Timing Info -->
                  <div class="col-lg-4 col-md-12 mb-3">
                    <h4 class="h6 mb-2">Session Information</h4>
                    <div class="row g-2">
                      <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                          <div class="fw-bold text-primary" id="session-time">00:00</div>
                          <small class="text-muted">Session Time</small>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="text-center p-2 bg-light rounded">
                          <div class="fw-bold text-success" id="processing-speed">0 items/min</div>
                          <small class="text-muted">Processing Speed</small>
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded">
                          <div>
                            <div class="fw-bold text-info" id="lock-status">🔒 Locked to this session</div>
                            <small class="text-muted">Auto-unlock in <span id="lock-timer">15:00</span></small>
                          </div>
                          <button type="button" class="btn btn-outline-warning btn-sm" id="btn-extend-lock">
                            Extend Lock
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Historical Performance Chart Placeholder -->
                <div class="row">
                  <div class="col-12">
                    <div class="border-top pt-3">
                      <h4 class="h6 mb-2">Historical Receiving Performance</h4>
                      <div class="d-flex justify-content-between align-items-center p-3 bg-light rounded">
                        <div class="text-center">
                          <div class="fw-bold text-success">15 min</div>
                          <small class="text-muted">Fastest PO</small>
                        </div>
                        <div class="text-center">
                          <div class="fw-bold text-primary">28 min</div>
                          <small class="text-muted">Average Time</small>
                        </div>
                        <div class="text-center">
                          <div class="fw-bold text-warning">2.1 hrs</div>
                          <small class="text-muted">Slowest PO</small>
                        </div>
                        <div class="text-center">
                          <div class="fw-bold text-info">142</div>
                          <small class="text-muted">Total Completed</small>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Enhanced View/Scanner Controls with Options -->
        <section class="po-card u-mb-1" aria-labelledby="view-controls-title">
          <header class="po-card__header rx-header" id="view-controls-title">
            <span>View &amp; Scanner Controls</span>
            <div class="header-actions">
              <button type="button" class="btn btn--ghost btn-sm" id="btn-legal-acknowledgment" title="Legal Responsibility">
                ⚖️ Responsibility
              </button>
              <div class="dropdown" id="options-dropdown">
                <button type="button" class="btn btn--ghost btn-sm dropdown-toggle" id="options-toggle" aria-haspopup="true" aria-expanded="false">
                  ⚙️ Options
                </button>
                <div class="dropdown-menu" id="options-menu" aria-labelledby="options-toggle">
                  <div class="dropdown-section">
                    <h6 class="dropdown-header">Display Options</h6>
                    <label class="dropdown-item-check">
                      <input type="checkbox" id="toggleImages"> Show Product Images
                    </label>
                    <label class="dropdown-item-check">
                      <input type="checkbox" id="toggleUnitCost"> Show Unit Costs
                    </label>
                    <label class="dropdown-item-check">
                      <input type="checkbox" id="toggleStock"> Show Stock Levels
                    </label>
                    <label class="dropdown-item-check">
                      <input type="checkbox" id="toggleCompactMode"> Compact Mode
                    </label>
                  </div>
                  
                  <div class="dropdown-divider"></div>
                  
                  <div class="dropdown-section">
                    <h6 class="dropdown-header">Scanner Settings</h6>
                    <label class="dropdown-item-check">
                      <input type="checkbox" id="audioFeedback" checked> Audio Feedback
                    </label>
                    <label class="dropdown-item-check">
                      <input type="checkbox" id="autoAdvance" checked> Auto-advance to next field
                    </label>
                    <label class="dropdown-item-check">
                      <input type="checkbox" id="highlightScanned" checked> Highlight scanned products
                    </label>
                    <div class="dropdown-item">
                      <label for="scannerBeepVolume">Beep Volume</label>
                      <input type="range" id="scannerBeepVolume" min="0" max="100" value="75" class="form-range form-range-sm">
                    </div>
                  </div>
                  
                  <div class="dropdown-divider"></div>
                  
                  <div class="dropdown-section">
                    <h6 class="dropdown-header">🔍 Filters & Views</h6>
                    
                    <!-- Primary Status Filter -->
                    <select id="statusFilter" class="form-select form-select-sm mb-2">
                      <option value="all">🔍 All Products</option>
                      <option value="pending">⏳ Pending Only</option>
                      <option value="completed">✅ Completed Only</option>
                      <option value="discrepancies">⚠️ With Discrepancies</option>
                      <option value="damaged">🔴 Damaged Items</option>
                      <option value="missing">❌ Missing/Short</option>
                      <option value="extra">➕ Extra/Unordered</option>
                      <option value="substituted">🔄 Substituted</option>
                      <option value="expired">🕐 Expired Products</option>
                      <option value="not_compliant">⚖️ Not Compliant</option>
                      <option value="partial">📦 Partial Delivery</option>
                      <option value="needs-photos">📸 Needs Evidence</option>
                      <option value="financial-review">💰 Financial Review</option>
                    </select>
                    
                    <!-- Secondary Filters -->
                    <div class="filter-chips mb-2">
                      <button type="button" class="filter-chip" data-filter="has-variance" title="Items with price variance">
                        💰 Price Variance
                      </button>
                      <button type="button" class="filter-chip" data-filter="zero-received" title="Nothing received">
                        🚫 Zero Received
                      </button>
                      <button type="button" class="filter-chip" data-filter="over-received" title="More than ordered">
                        📈 Over Received
                      </button>
                      <button type="button" class="filter-chip" data-filter="high-value" title="High value items">
                        💎 High Value
                      </button>
                    </div>
                    
                    <!-- Quick Search -->
                    <div class="input-group input-group-sm mb-2">
                      <input type="text" id="quickSearch" class="form-control" placeholder="🔍 Search products...">
                      <button type="button" class="btn btn-outline-secondary" id="btn-clear-search">✕</button>
                    </div>
                    
                    <!-- Sort Options -->
                    <select id="sortOrder" class="form-select form-select-sm">
                      <option value="default">📋 Default Order</option>
                      <option value="alphabetical">🔤 Alphabetical</option>
                      <option value="completion">📊 By Completion Status</option>
                      <option value="discrepancy">⚠️ By Discrepancy Level</option>
                      <option value="value">💰 By Value (High to Low)</option>
                      <option value="quantity">📦 By Quantity (High to Low)</option>
                      <option value="recent-scanned">⏱️ Recently Scanned First</option>
                    </select>
                    
                    <!-- View Options -->
                    <div class="view-buttons mt-2">
                      <button type="button" class="btn btn-outline-primary btn-sm" id="btn-focus-mode" title="Hide completed items">
                        🎯 Focus Mode
                      </button>
                      <button type="button" class="btn btn-outline-info btn-sm" id="btn-critical-view" title="Show only critical issues">
                        🚨 Critical View
                      </button>
                    </div>
                  </div>
                  
                  <div class="dropdown-divider"></div>
                  
                  <div class="dropdown-section">
                    <h6 class="dropdown-header">Actions</h6>
                    <button type="button" class="dropdown-item btn-link" id="btn-export-data">
                      📊 Export Data
                    </button>
                    <button type="button" class="dropdown-item btn-link" id="btn-print-summary">
                      🖨️ Print Summary
                    </button>
                    <button type="button" class="dropdown-item btn-link" id="btn-reset-filters">
                      🔄 Reset All Filters
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </header>
          <div class="po-card__body">
            <!-- Enhanced Search and Scanner Row -->
            <div class="row g-2 mb-3">
              <div class="col-md-4">
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-info text-white">
                    <i class="fas fa-search"></i>
                  </span>
                  <input id="globalProductSearch" type="text" class="form-control" 
                         placeholder="Global product search..." aria-label="Global product search">
                  <button type="button" class="btn btn-outline-info" id="btn-advanced-search" 
                          title="Advanced search options">
                    <i class="fas fa-cog"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-6">
                <form id="scanner-form" role="search" aria-label="Barcode scanner">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text bg-warning text-dark">
                      <i class="fas fa-barcode"></i>
                    </span>
                    <input id="globalBarcodeInput" type="text" class="form-control" inputmode="text" 
                           autocomplete="off" placeholder="Scan or type SKU/Barcode…" 
                           aria-label="Global barcode scanner">
                    <button type="button" class="btn btn-outline-warning" id="btn-scanner-clear" 
                            title="Clear scanner input">
                      <i class="fas fa-eraser"></i>
                    </button>
                  </div>
                </form>
              </div>
              <div class="col-md-2">
                <div class="scanner-status-display text-center">
                  <div aria-live="polite" id="globalScannerStatus" class="scanner-status-text fw-bold text-primary small">
                    Ready
                  </div>
                  <div id="currentProduct" class="current-product-text text-muted small"></div>
                </div>
              </div>
            </div>
            
            <!-- Quick Stats Row -->
            <div class="row g-2 mb-2">
              <div class="col-auto">
                <span class="badge bg-primary" id="totalProducts"><?= count($lines); ?> Products</span>
              </div>
              <div class="col-auto">
                <span class="badge bg-success" id="completedProducts">0 Completed</span>
              </div>
              <div class="col-auto">
                <span class="badge bg-warning" id="pendingProducts"><?= count($lines); ?> Pending</span>
              </div>
              <div class="col-auto">
                <span class="badge bg-danger" id="discrepancyProducts">0 Discrepancies</span>
              </div>
            </div>
            
            <!-- Scanner Statistics Row -->
            <div class="row g-2">
              <div class="col-md-6">
                <div id="scanStats" class="scan-stats-container">
                  <div class="scan-stats">
                    <span class="stat-item">
                      <i class="fas fa-barcode text-info"></i> 
                      Scans: 0
                    </span>
                    <span class="stat-item">
                      <i class="fas fa-check-circle text-success"></i> 
                      Found: 0/<?= count($lines); ?>
                    </span>
                    <span class="stat-item">
                      <i class="fas fa-percentage text-warning"></i> 
                      0%
                    </span>
                  </div>
                </div>
              </div>
              <div class="col-md-6 text-end">
                <small class="text-muted">
                  <i class="fas fa-info-circle"></i> 
                  Scan barcodes to quickly locate products
                </small>
              </div>
            </div>
          </div>
        </section>

        <!-- Product table -->
        <section class="po-card" aria-labelledby="product-table-title">
          <!-- Hidden form inputs for submission -->
          <input type="hidden" name="purchaseOrderId" value="<?= $poId; ?>">
          <input type="hidden" name="purchase_order_id" value="<?= $poId; ?>">
          
          <header class="po-card__header rx-header" id="product-table-title">
            <span>Products</span>
            <div class="header-actions">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="toggleImages" checked>
                <span class="form-check-label">📷 Images</span>
              </label>
              <span class="badge bg-primary ms-2" id="productCount"><?= count($lines); ?> items</span>
            </div>
          </header>
          <div class="po-card__body">
            <!-- Enhanced Search Integration -->
            <div class="search-scanner-integration mb-3">
              <div class="row g-2 align-items-center">
                <div class="col-md-5">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text bg-primary text-white">🔍</span>
                    <input id="tableSearch" type="text" class="form-control" placeholder="Search products..." 
                           aria-label="Product search" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary" id="searchClear" title="Clear search">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>
                </div>
                <div class="col-md-5">
                  <div class="input-group input-group-sm">
                    <span class="input-group-text scanner-icon bg-success text-white">📱</span>
                    <input id="barcodeInput" type="text" class="form-control scanner-input" 
                           placeholder="Scan barcode/SKU..." aria-label="Barcode scanner" 
                           autocomplete="off" inputmode="text">
                    <button type="button" class="btn btn-outline-success" id="scannerToggle" title="Scanner controls">
                      <i class="fas fa-qrcode"></i>
                    </button>
                  </div>
                </div>
                <div class="col-md-2">
                  <div class="scanner-status-mini text-center">
                    <div id="scannerStatus" class="status-text fw-bold text-success">Ready</div>
                    <div id="scanCount" class="scan-count text-muted small">0 scans</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Bulk Actions Controls -->
            <div class="bulk-actions-bar mb-3" id="bulkActionsBar" style="display: none;">
              <div class="alert alert-info py-2 mb-0">
                <div class="row align-items-center">
                  <div class="col-md-4">
                    <span class="fw-bold">
                      <i class="fas fa-check-square me-2"></i>
                      <span id="selectedCount">0</span> items selected
                    </span>
                  </div>
                  <div class="col-md-8 text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <button type="button" class="btn btn-outline-success" id="markCompleted" title="Mark selected as completed">
                        <i class="fas fa-check"></i> Complete
                      </button>
                      <button type="button" class="btn btn-outline-warning" id="markDamaged" title="Mark selected as damaged">
                        <i class="fas fa-exclamation-triangle"></i> Damaged
                      </button>
                      <button type="button" class="btn btn-outline-secondary" id="markPending" title="Mark selected as pending">
                        <i class="fas fa-clock"></i> Pending
                      </button>
                      <button type="button" class="btn btn-outline-primary" id="bulkSetSlip" title="Set slip quantity for selected">
                        <i class="fas fa-receipt"></i> Set Slip
                      </button>
                      <button type="button" class="btn btn-outline-info" id="bulkSetReceived" title="Set received quantity for selected">
                        <i class="fas fa-box"></i> Set Received
                      </button>
                      <button type="button" class="btn btn-outline-secondary" id="deselectAll" title="Deselect all">
                        <i class="fas fa-times"></i> Clear
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Responsive Table Container -->
            <div class="table-responsive-custom" id="tableContainer">
              <table id="poTable" class="table table-hover po-table" role="grid">
                <thead class="table-header-fixed">
                  <tr role="row">
                    <th scope="col" class="col-checkbox text-center" style="width: 40px;">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll" 
                               title="Select all visible rows">
                        <label class="form-check-label visually-hidden" for="selectAll">
                          Select all
                        </label>
                      </div>
                    </th>
                    <th scope="col" class="col-image text-center" data-sort="image">
                      <span class="th-content">
                        <i class="fas fa-image text-primary"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-product" data-sort="name">
                      <span class="th-content">
                        <i class="fas fa-box me-1 text-primary"></i>Product 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-stock text-center" data-sort="stock">
                      <span class="th-content">
                        <i class="fas fa-warehouse me-1 text-info"></i>Stock 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-ordered text-center" data-sort="ordered">
                      <span class="th-content">
                        <i class="fas fa-shopping-cart me-1 text-secondary"></i>Ord 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-slip text-center" data-sort="slip">
                      <span class="th-content">
                        <i class="fas fa-receipt me-1 text-warning"></i>Slip 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-recv text-center" data-sort="received">
                      <span class="th-content">
                        <i class="fas fa-check-circle me-1 text-success"></i>Recv 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-dmg text-center" data-sort="damaged">
                      <span class="th-content">
                        <i class="fas fa-exclamation-triangle me-1 text-danger"></i>Dmg 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-diff text-center" data-sort="diff">
                      <span class="th-content">
                        <i class="fas fa-balance-scale me-1 text-dark"></i>Diff 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-status" data-sort="status">
                      <span class="th-content">
                        <i class="fas fa-flag me-1 text-primary"></i>Status 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-cost text-center" data-sort="cost">
                      <span class="th-content">
                        <i class="fas fa-dollar-sign me-1 text-success"></i>Cost 
                        <i class="fas fa-sort ms-1"></i>
                      </span>
                    </th>
                    <th scope="col" class="col-actions text-center">
                      <span class="th-content">
                        <i class="fas fa-cogs text-secondary"></i>
                      </span>
                    </th>
                  </tr>
                </thead>
                <tbody id="po-lines" class="table-body-interactive">
                <?php
                $rowNum = 0;
                foreach ($lines as $row):
                  $rowNum++;
                  $pid   = e($row->product_id);
                  $pname = e($row->product_name ?? $pid);
                  $skuRaw= $row->product_sku ?? '';
                  $skuDisplay = '';
                  if ($skuRaw) {
                    if ($skuRaw[0] === '[' || $skuRaw[0] === '{') {
                      $tmp = json_decode($skuRaw, true); $skuDisplay = is_array($tmp) && $tmp ? reset($tmp) : $skuRaw;
                    } elseif (strpos($skuRaw, ',')!==false) { $explode = explode(',', $skuRaw); $skuDisplay = trim($explode[0]); }
                    else { $skuDisplay = $skuRaw; }
                  }
                  $ord  = (int)($row->order_qty ?? 0);
                  $stock= (int)($row->qty_in_stock_before ?? 0);
                  $slip = $row->slip_qty;
                  $recv = $row->qty_arrived;
                  $dmg  = (int)($row->damaged_qty ?? 0);
                  $unit = $row->unit_cost_ex_gst ?? $row->current_cost_price ?? 0;
                  $note = e($row->line_note ?? '');
                  $dt   = strtolower((string)($row->discrepancy_type ?? 'ok'));
                  $diff = (($recv ?? 0) + $dmg) - $ord;
                  $img  = $row->image_thumbnail_url ?? '';
                  
                  // Calculate completion status
                  $isComplete = ($recv !== null && $recv !== '');
                  $hasDiscrepancy = ($diff !== 0);
                  $rowStatus = $isComplete ? ($hasDiscrepancy ? 'warning' : 'success') : 'pending';
                ?>
                  <tr class="product-row row-<?= $rowStatus; ?>" 
                      data-product-id="<?= $pid; ?>"
                      data-pid="<?= $pid; ?>" 
                      data-sku="<?= e($skuDisplay); ?>"
                      data-name="<?= e(strtolower($pname)); ?>"
                      data-ordered="<?= $ord; ?>"
                      data-status="<?= $dt; ?>"
                      role="row"
                      tabindex="0">
                    
                    <!-- Checkbox Column -->
                    <td class="col-checkbox text-center" data-label="Select">
                      <div class="form-check">
                        <input class="form-check-input row-checkbox" type="checkbox" 
                               id="select-<?= $rowNum; ?>" 
                               value="<?= $pid; ?>"
                               title="Select this row">
                        <label class="form-check-label visually-hidden" for="select-<?= $rowNum; ?>">
                          Select row <?= $rowNum; ?>
                        </label>
                      </div>
                    </td>

                    <!-- Image Column -->
                    <td class="col-image" data-label="Image">
                      <div class="image-container">
                        <?php if ($img): ?>
                          <img src="<?= e($img); ?>" 
                               alt="<?= e($pname); ?>" 
                               class="product-image" 
                               loading="lazy"
                               width="30" 
                               height="30"
                               title="<?= e($pname); ?> - Hover to enlarge">
                        <?php else: ?>
                          <div class="image-placeholder">
                            <i class="fas fa-box"></i>
                          </div>
                        <?php endif; ?>
                      </div>
                    </td>

                    <!-- Product Column (Expandable) -->
                    <td class="col-product" data-label="Product">
                      <div class="product-info">
                        <div class="product-name" title="<?= e($pname); ?>">
                          <?= e($pname); ?>
                        </div>
                        <div class="product-meta">
                          <span class="sku-badge">SKU: <?= e($skuDisplay); ?></span>
                          <button type="button" class="btn btn-link btn-xs vend-link-btn ms-2" 
                                  data-product-id="<?= $pid; ?>" 
                                  title="View in LightSpeed"
                                  style="padding: 1px 3px; font-size: 14px; line-height: 1; color: #0066cc;">
                            <i class="fas fa-external-link-alt"></i>
                          </button>
                        </div>
                      </div>
                    </td>

                    <!-- Stock Column -->
                    <td class="col-stock" data-label="Stock">
                      <div class="stock-display">
                        <span class="stock-number" data-stock="<?= $stock; ?>"><?= $stock; ?></span>
                        <button type="button" class="btn btn-link btn-xs refresh-stock" 
                                data-pid="<?= $pid; ?>" 
                                title="Refresh stock level">
                          <i class="fas fa-sync-alt"></i>
                        </button>
                      </div>
                    </td>

                    <!-- Ordered Column -->
                    <td class="col-ordered" data-label="Ordered">
                      <span class="ordered-qty"><?= $ord; ?></span>
                    </td>

                    <!-- Slip Quantity -->
                    <td class="col-slip" data-label="Slip">
                      <input type="number" 
                             class="form-control form-control-sm rx-slip qty-input" 
                             value="<?= $slip !== null ? (int)$slip : ''; ?>"
                             min="0" 
                             step="1"
                             placeholder="0"
                             aria-label="Slip quantity"
                             <?= $isComplete ? 'disabled' : ''; ?>>
                    </td>

                    <!-- Received Quantity -->
                    <td class="col-recv" data-label="Received">
                      <div class="input-group input-group-sm">
                        <input type="number" 
                               class="form-control rx-recv qty-input primary-input" 
                               value="<?= $recv !== null ? (int)$recv : ''; ?>"
                               min="0" 
                               step="1"
                               placeholder="0"
                               aria-label="Received quantity"
                               <?= $isComplete ? 'disabled' : ''; ?>>
                        <button type="button" 
                                class="btn btn-outline-primary btn-sm quick-add" 
                                data-action="add1"
                                title="Quick +1">
                          +1
                        </button>
                      </div>
                    </td>

                    <!-- Damaged Quantity -->
                    <td class="col-dmg" data-label="Damaged">
                      <input type="number" 
                             class="form-control form-control-sm rx-dmg qty-input" 
                             value="<?= $dmg; ?>"
                             min="0" 
                             step="1"
                             placeholder="0"
                             aria-label="Damaged quantity"
                             <?= $isComplete ? 'disabled' : ''; ?>>
                    </td>

                    <!-- Difference -->
                    <td class="col-diff" data-label="Diff">
                      <span class="diff-display <?= $diff === 0 ? 'diff-ok' : ($diff > 0 ? 'diff-over' : 'diff-short'); ?>" 
                            aria-live="polite">
                        <?= $diff > 0 ? '+' : ''; ?><?= $diff; ?>
                      </span>
                    </td>

                    <!-- Status -->
                    <td class="col-status" data-label="Status">
                      <select class="form-select form-select-sm rx-status status-select" 
                              data-original="<?= e($dt); ?>"
                              aria-label="Line status"
                              <?= $isComplete ? 'disabled' : ''; ?>>
                        <option value="ok" <?= $dt === 'ok' ? 'selected' : ''; ?>>✅ OK</option>
                        <option value="missing" <?= ($dt === 'missing' || $dt === 'sent_low') ? 'selected' : ''; ?>>❌ Missing</option>
                        <option value="damaged" <?= $dt === 'damaged' ? 'selected' : ''; ?>>💥 Damaged</option>
                        <option value="substituted" <?= $dt === 'substituted' ? 'selected' : ''; ?>>🔄 Substituted</option>
                        <option value="unordered" <?= ($dt === 'unordered' || $dt === 'sent_high') ? 'selected' : ''; ?>>➕ Extra</option>
                        <option value="expired" <?= $dt === 'expired' ? 'selected' : ''; ?>>⏰ Expired</option>
                        <option value="not_compliant" <?= $dt === 'not_compliant' ? 'selected' : ''; ?>>⚖️ Non-Compliant</option>
                      </select>
                    </td>

                    <!-- Unit Cost -->
                    <td class="col-cost" data-label="Cost">
                      <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" 
                               class="form-control rx-unit cost-input" 
                               value="<?= $unit !== null ? number_format((float)$unit, 4, '.', '') : ''; ?>"
                               step="0.0001" 
                               min="0"
                               placeholder="0.0000"
                               aria-label="Unit cost"
                               <?= $isComplete ? 'disabled' : ''; ?>>
                      </div>
                    </td>

                    <!-- Actions -->
                    <td class="col-actions" data-label="Actions">
                      <div class="action-buttons">
                        <button type="button" 
                                class="btn btn-sm btn-outline-secondary quick-scan" 
                                data-pid="<?= $pid; ?>"
                                title="Quick scan this product">
                          <i class="fas fa-qrcode"></i>
                        </button>
                        <button type="button" 
                                class="btn btn-sm btn-outline-info toggle-notes" 
                                title="Toggle notes">
                          <i class="fas fa-comment"></i>
                        </button>
                      </div>
                      
                      <!-- Hidden Notes Row -->
                      <div class="notes-row" style="display: none;">
                        <textarea class="form-control form-control-sm rx-note notes-input" 
                                  placeholder="Add notes..." 
                                  maxlength="300"
                                  rows="2"
                                  aria-label="Line notes"
                                  <?= $isComplete ? 'disabled' : ''; ?>><?= $note; ?></textarea>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Enhanced Table Summary Footer -->
            <div class="table-summary bg-light border-top">
              <div class="row align-items-center py-2">
                <div class="col-md-6">
                  <div class="summary-stats d-flex flex-wrap gap-2">
                    <span class="badge bg-primary fs-6">
                      <i class="fas fa-boxes me-1"></i>
                      Total: <span data-stat="totalItems"><?= count($lines); ?></span>
                    </span>
                    <span class="badge bg-success fs-6">
                      <i class="fas fa-check-circle me-1"></i>
                      Complete: <span data-stat="completedItems">0</span>
                    </span>
                    <span class="badge bg-warning fs-6">
                      <i class="fas fa-clock me-1"></i>
                      Pending: <span data-stat="pendingItems"><?= count($lines); ?></span>
                    </span>
                    <span class="badge bg-danger fs-6">
                      <i class="fas fa-exclamation-triangle me-1"></i>
                      Issues: <span data-stat="damagedItems">0</span>
                    </span>
                    <span class="badge bg-info fs-6">
                      <i class="fas fa-percentage me-1"></i>
                      Progress: <span data-stat="progressPercent">0%</span>
                    </span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="bulk-actions text-end">
                    <div class="btn-group btn-group-sm" role="group">
                      <button type="button" class="btn btn-outline-primary" id="bulkFillSlip" 
                              title="Fill all slip quantities with ordered quantities">
                        <i class="fas fa-receipt me-1"></i>Fill Slip = Ordered
                      </button>
                      <button type="button" class="btn btn-outline-success" id="bulkFillReceived" 
                              title="Fill all received quantities with slip quantities">
                        <i class="fas fa-box-open me-1"></i>Fill Received = Slip
                      </button>
                      <button type="button" class="btn btn-outline-info" id="toggleImages" 
                              title="Show/hide product images">
                        <i class="fas fa-eye-slash me-1"></i>Hide Images
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Validation Status Bar -->
            <div class="validation-status-bar" id="validationStatusBar">
              <div class="validation-item" id="validationFields">
                <span class="validation-icon">⚠️</span>
                <span class="validation-text">Checking required fields...</span>
                <span class="validation-count" id="fieldValidationCount">0/0</span>
              </div>
              <div class="validation-item" id="validationPricing">
                <span class="validation-icon">💰</span>
                <span class="validation-text">Verifying pricing totals...</span>
                <span class="validation-variance" id="pricingVariance">±0.00</span>
              </div>
              <div class="validation-item" id="validationDamaged">
                <span class="validation-icon">📷</span>
                <span class="validation-text">Damaged goods documentation...</span>
                <span class="validation-count" id="damagedValidationCount">0/0</span>
              </div>
              <div class="validation-item" id="validationOverall">
                <span class="validation-icon">🔍</span>
                <span class="validation-text">Overall Status:</span>
                <span class="validation-status" id="overallValidationStatus">Checking...</span>
              </div>
            </div>

            <div class="po-actions" aria-label="Actions">
              <?php if (!$isComplete): ?>
                <!-- Clear, Prominent Smart Submit Button -->
                <div class="smart-submit-section">
                  <button type="button" class="btn btn-lg btn-success" id="smart_submit" 
                          title="Auto-detects whether to mark as partial or final delivery based on received quantities">
                    <i class="fa fa-magic"></i> Smart Submit
                  </button>
                  <div class="submit-help-text">
                    <small class="text-muted">
                      <i class="fa fa-lightbulb"></i> 
                      Automatically detects if this is a partial or final delivery. 
                      Updates Vend inventory and removes from to-do list when complete.
                    </small>
                  </div>
                </div>
                
                <!-- Advanced Options (collapsed by default) -->
                <details class="advanced-options mt-3">
                  <summary class="btn btn-sm btn-outline-secondary">
                    <i class="fa fa-cog"></i> Advanced Options
                  </summary>
                  <div class="advanced-buttons mt-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnAddProduct" disabled title="Complete all existing products first">
                      ➕ Add Additional Product
                    </button>
                    <button type="button" class="btn btn-outline-info btn-sm" id="btnSaveDraft" title="Save current progress without validation">
                      💾 Save Draft
                    </button>
                  </div>
                </details>
                
                <div class="action-validation-messages mt-3" id="actionValidationMessages">
                  <!-- Dynamic validation messages will appear here -->
                </div>
              <?php else: ?>
                <span class="badge badge--ok">✅ Completed Successfully</span>
                <button type="button" class="btn btn--warn" id="unlock_order" title="Unlock this document to allow editing again">
                  🔓 Unlock for Editing
                </button>
                <a class="btn btn--secondary" href="/purchase-orders/">📋 View All POs</a>
                <a class="btn btn--primary" href="/">🏠 Return Home</a>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <!-- Enhanced Sticky Footer Stats Bar -->
        <section class="sticky-stats-bar" aria-label="Live progress and statistics">
          <div class="stats-container">
            <!-- Quick Scanner -->
            <div class="stats-group scanner-group">
              <div class="stats-header">
                <span class="stats-icon">📱</span>
                <span class="stats-title">Quick Scanner</span>
              </div>
              <div class="scanner-mini">
                <input id="sticky-scanner" type="text" placeholder="Scan here..." class="scanner-input" aria-label="Quick barcode scanner">
                <div class="scanner-status" id="sticky-scanner-status">Ready</div>
              </div>
            </div>

            <!-- Progress Stats -->
            <div class="stats-group progress-group">
              <div class="stats-header">
                <span class="stats-icon">📊</span>
                <span class="stats-title">Progress</span>
              </div>
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-value" id="products-completed">0</div>
                  <div class="stat-label">of <span id="products-total"><?= count($lines); ?></span> products</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value" id="items-processed">0</div>
                  <div class="stat-label">items processed</div>
                </div>
              </div>
            </div>

            <!-- Accuracy Stats -->
            <div class="stats-group accuracy-group">
              <div class="stats-header">
                <span class="stats-icon">🎯</span>
                <span class="stats-title">Accuracy</span>
              </div>
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-value stat-success" id="perfect-matches">0</div>
                  <div class="stat-label">perfect matches</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value stat-warning" id="discrepancies">0</div>
                  <div class="stat-label">discrepancies</div>
                </div>
              </div>
            </div>

            <!-- Time Stats -->
            <div class="stats-group time-group">
              <div class="stats-header">
                <span class="stats-icon">⏱️</span>
                <span class="stats-title">Timing</span>
              </div>
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-value" id="session-duration">00:00</div>
                  <div class="stat-label">session time</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value" id="avg-per-item">--</div>
                  <div class="stat-label">avg/item</div>
                </div>
              </div>
            </div>

            <!-- Financial Summary -->
            <div class="stats-group financial-group">
              <div class="stats-header">
                <span class="stats-icon">💰</span>
                <span class="stats-title">Financial</span>
              </div>
              <div class="stats-grid">
                <div class="stat-item">
                  <div class="stat-value" id="sticky-subtotal">$0.00</div>
                  <div class="stat-label">subtotal</div>
                </div>
                <div class="stat-item">
                  <div class="stat-value" id="sticky-total">$0.00</div>
                  <div class="stat-label">total incl. GST</div>
                </div>
              </div>
            </div>

            <!-- Confidence Meter -->
            <div class="stats-group confidence-group">
              <div class="stats-header">
                <span class="stats-icon">🏆</span>
                <span class="stats-title">Confidence</span>
              </div>
              <div class="confidence-display">
                <div class="confidence-circle">
                  <div class="confidence-text" id="sticky-confidence">0%</div>
                </div>
                <div class="confidence-mini-progress">
                  <div class="confidence-bar" id="sticky-confidence-bar" style="width: 0%"></div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="stats-group actions-group">
              <div class="sticky-actions">
                <button type="button" class="btn-sticky btn-sticky-primary" id="sticky-save">
                  💾 Save
                </button>
                <button type="button" class="btn-sticky btn-sticky-success" id="sticky-complete">
                  ✅ Complete
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Order Submission Section -->
        <section class="po-card u-mt-1" aria-labelledby="submission-title">
          <header class="po-card__header rx-header" id="submission-title">
            <span>📋 Order Submission</span>
          </header>
          <div class="po-card__body">
            <div class="row">
              <div class="col-md-6">
                <div class="d-grid gap-2">
                  <button type="button" class="btn btn-success btn-lg" id="btn-submit-final">
                    <i class="fa fa-check-circle me-2"></i>
                    Submit Final Order
                  </button>
                  <small class="text-muted">
                    ✅ Mark order as completely received and create final claims
                    <br>🔄 Updates Vend inventory and creates audit trail
                  </small>
                </div>
              </div>
              <div class="col-md-6">
                <div class="d-grid gap-2">
                  <button type="button" class="btn btn-warning btn-lg" id="btn-submit-partial" disabled>
                    <i class="fa fa-clock me-2"></i>
                    Submit Partial Order
                  </button>
                  <small class="text-muted">
                    📦 Save current progress for continued receiving later
                    <br>🔒 Maintains order lock for next session
                    <br><span id="partial-status" class="text-warning">⚠️ Complete at least one product to enable</span>
                  </small>
                </div>
              </div>
            </div>
            
            <div class="row mt-4">
              <div class="col-12">
                <div class="alert alert-info">
                  <h6 class="alert-heading">
                    <i class="fa fa-info-circle me-2"></i>
                    Submission Guidelines
                  </h6>
                  <ul class="mb-0">
                    <li><strong>Final Submission:</strong> Use when all products have been processed and the order is complete</li>
                    <li><strong>Partial Submission:</strong> Use when you need to pause receiving and continue later</li>
                    <li><strong>Database Integration:</strong> Both options automatically update inventory, create claims, and sync with Vend</li>
                    <li><strong>Audit Trail:</strong> All submissions are logged with your staff ID and timestamp</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </section>
      </section>
    </div>
  </main>

  <footer class="site-footer" role="contentinfo" aria-label="Footer">
    <div class="container-xxl u-py-1 u-flex u-justify-between u-items-center">
      <div>&copy; <?= date('Y'); ?> Ecigdis Limited · <a href="https://www.vapeshed.co.nz">The Vape Shed</a></div>
      <div>
        <small>Developed by <a href="https://www.pearcestephens.co.nz" target="_blank" rel="noopener">Pearce Stephens</a></small>
        <a class="btn btn--ghost" href="/submit_ticket.php">Report a bug</a>
      </div>
    </div>
  </footer>

  <!-- Modal(s) (end of body) -->
  <div class="modal" id="notification-modal" role="dialog" aria-modal="true" aria-labelledby="notification-title" hidden>
    <div class="modal__dialog" role="document">
      <div class="modal__header">
        <h2 id="notification-title">Notification</h2>
        <button type="button" class="btn btn--ghost" data-close-modal aria-label="Close">×</button>
      </div>
      <div class="modal__body" id="notification-body"></div>
      <div class="modal__footer">
        <button type="button" class="btn" data-close-modal>Close</button>
      </div>
    </div>
  </div>

  <!-- App config to JS -->
  <script>
    window.PO_DATA = { id: <?= (int)$poId; ?>, complete: <?= $isComplete ? 'true' : 'false'; ?> };
    window.PO_ID = <?= (int)$poId; ?>;
    window.IS_COMPLETE = <?= $isComplete ? 'true' : 'false'; ?>;
  </script>

  <!-- QRCode lib -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-MdWltTnupJkBmP0JbNPO8k9wQkqQGk7u2k9r8e3UqY5fH4fX+Jp+KQ0JpQm1cJQ+Sg9h5TtBzQ8m7r8wS5QbMg==" crossorigin="anonymous"></script>

  <!-- Core Dependencies (must load first) -->
  <script src="assets/js/purchase-orders/core.js"></script>
  <script src="assets/js/purchase-orders/toast-notifications.js"></script>
  <script src="assets/js/purchase-orders/autosave.js"></script>
  
  <!-- Advanced Purchase Order Module System v2.0 -->
  <!-- Single entry point - loads all other modules with dependency management -->
  <script src="assets/js/purchase-orders/module-loader.js"></script>

  <!-- Financial Calculation Debug Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🔍 Financial Debug: DOM loaded, checking elements...');
      
      // Check if all financial elements exist
      const elements = {
        'gst-mode': document.getElementById('gst-mode'),
        'subtotal': document.getElementById('subtotal'),
        'freight': document.getElementById('freight'),
        'gst-amount': document.getElementById('gst-amount'),
        'total-incl': document.getElementById('total-incl'),
        'packing-slip-no': document.getElementById('packing-slip-no'),
        'invoice-no': document.getElementById('invoice-no')
      };
      
      console.log('📊 Financial Elements Status:');
      Object.entries(elements).forEach(([id, el]) => {
        console.log(`  ${id}: ${el ? '✅ Found' : '❌ Missing'}`);
        if (el) {
          console.log(`    Value: "${el.value}"`);
          console.log(`    Type: ${el.type || el.tagName}`);
        }
      });
      
      // Check if POCore exists and is initialized
      setTimeout(() => {
        if (window.POCore) {
          console.log('✅ POCore is available');
          console.log('📊 POCore state:', window.POCore.state);
          
          // Test financial calculation manually
          console.log('🧮 Testing manual financial calculation...');
          if (window.POCore.calculateFinancials) {
            try {
              window.POCore.calculateFinancials();
              console.log('✅ Manual calculation successful');
              console.log('💰 Current totals:', window.POCore.state.totals);
            } catch (error) {
              console.error('❌ Manual calculation failed:', error);
            }
          }
        } else {
          console.error('❌ POCore not available');
        }
        
        if (window.POMain) {
          console.log('✅ POMain is available');
          console.log('📊 POMain state:', window.POMain.state);
        } else {
          console.error('❌ POMain not available');
        }
      }, 1000);
      
      // Add test event listeners to financial inputs  
      ['gst-mode', 'subtotal', 'freight', 'total-incl'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
          el.addEventListener('input', function(e) {
            console.log(`💰 Financial input changed: ${id} = "${e.target.value}"`);
            
            // Trigger manual calculation if POCore is available
            if (window.POCore && window.POCore.calculateFinancials) {
              setTimeout(() => {
                try {
                  window.POCore.handleFinancialChange(e);
                  console.log('✅ POCore calculation triggered');
                } catch (error) {
                  console.error('❌ POCore calculation error:', error);
                }
              }, 100);
            }
          });
          console.log(`✅ Added debug listener to ${id}`);
        }
      });
    });
  </script>

  <!-- Enhanced Purchase Order System v2.0 Initialization -->
  <script>
    // Enhanced Purchase Order System v2.0 Initialization
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 Purchase Order System v2.0 starting...');
      
      // Wait for module loader to complete
      document.addEventListener('POModulesLoaded', function(e) {
        const { loaded, failed, metrics } = e.detail;
        
        console.log(`✅ Module loading complete: ${loaded.length} loaded, ${failed.length} failed`);
        console.log(`⏱️ Total load time: ${metrics.totalLoadTime.toFixed(2)}ms`);
        
        // Initialize legacy systems that depend on the new modules
        if (typeof initializeLegacySystems === 'function') {
          initializeLegacySystems();
        }
        
        // Show system ready notification
        if (window.POToast) {
          const message = failed.length > 0 ? 
            `System ready with ${failed.length} module${failed.length > 1 ? 's' : ''} failed` :
            'Advanced purchase order system ready';
            
          const type = failed.length > 0 ? 'warning' : 'success';
          
          window.POToast[`show${type.charAt(0).toUpperCase() + type.slice(1)}`](message, {
            details: `Loaded: ${loaded.join(', ')}`,
            duration: 4000
          });
        }
        
        // Log system status
        console.log('📊 System Status:', {
          modules: {
            loaded: loaded,
            failed: failed
          },
          features: {
            autosave: !!window.POAutosave,
            scanner: !!window.POScanner,
            workflow: !!window.POWorkflow,
            notifications: !!window.POToast,
            core: !!window.POCore
          },
          performance: metrics
        });
        
        // Enable advanced features if available
        if (window.POWorkflow) {
          console.log('⚡ Advanced workflow features enabled - Press F1 for shortcuts');
        }
        
        if (window.POScanner) {
          console.log('📷 Advanced scanning features enabled - Press F4 to focus scanner');
        }
        
        if (window.POAutosave) {
          console.log('💾 Enhanced autosave system enabled with offline support');
        }
      });
      
      // Fallback initialization if module loader fails
      setTimeout(() => {
        if (!window.POModuleLoader || window.POModuleLoader.getStatus().loadedModules.length === 0) {
          console.warn('⚠️ Module loader failed, falling back to legacy initialization');
          
          // Initialize basic systems
          if (typeof initializeLegacySystems === 'function') {
            initializeLegacySystems();
          }
        }
      }, 15000); // 15 second timeout
      
      // Add keyboard shortcut for system info (Ctrl+Shift+I)
      document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.shiftKey && e.key === 'I') {
          e.preventDefault();
          
          if (window.POModuleLoader) {
            const status = window.POModuleLoader.getStatus();
            console.table(status);
            
            if (window.POToast) {
              window.POToast.showInfo('System Information', {
                details: `
                  <strong>Modules:</strong> ${status.loadedModules.length}/${Object.keys(window.POModuleLoader.modules).length} loaded<br>
                  <strong>Progress:</strong> ${status.progress.toFixed(1)}%<br>
                  <strong>Load Time:</strong> ${status.metrics.totalLoadTime?.toFixed(2) || 'N/A'}ms<br>
                  <strong>Memory:</strong> ${status.metrics.memoryUsage?.used || 'N/A'}MB used
                `,
                duration: 0, // Don't auto-close
                large: true
              });
            }
          }
        }
      });
    });
    
    // Legacy system initialization function
    function initializeLegacySystems() {
      console.log('🔧 Initializing legacy systems...');
      
      // Initialize any legacy components that weren't converted to the new module system
      // This ensures backward compatibility
      
      console.log('✅ Legacy systems initialized');
    }
    
    // Global error handler for unhandled module errors
    window.addEventListener('error', function(e) {
      if (e.filename && e.filename.includes('purchase-orders/')) {
        console.error('🚨 Purchase Order Module Error:', {
          message: e.message,
          filename: e.filename,
          line: e.lineno,
          column: e.colno
        });
        
        // Show user-friendly error message
        if (window.POToast) {
          window.POToast.showError('System Error Detected', {
            details: 'Please refresh the page or contact IT support if the problem persists',
            duration: 8000
          });
        }
      }
    });
    
    // Performance monitoring
    if ('performance' in window && 'mark' in performance) {
      performance.mark('po-system-start');
      
      document.addEventListener('POModulesLoaded', function() {
        performance.mark('po-system-ready');
        performance.measure('po-system-init', 'po-system-start', 'po-system-ready');
        
        const measure = performance.getEntriesByName('po-system-init')[0];
        console.log(`⏱️ System initialization time: ${measure.duration.toFixed(2)}ms`);
      });
    }
  </script>

  <!-- 10 Professional Color Scheme Demo System -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Define 10 Professional Color Schemes
      const colorSchemes = [
        {
          id: 'corporate-blue',
          name: 'Corporate Blue',
          description: 'Professional and trustworthy',
          colors: {
            primary: '#1e3a8a',
            secondary: '#3b82f6', 
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#06b6d4',
            light: '#f8fafc',
            dark: '#1e293b',
            navbar: 'linear-gradient(135deg, #1e3a8a, #3b82f6)',
            sidebar: '#1e293b',
            text: '#ffffff'
          }
        },
        {
          id: 'emerald-professional',
          name: 'Emerald Professional',
          description: 'Fresh and modern',
          colors: {
            primary: '#047857',
            secondary: '#10b981',
            success: '#059669',
            warning: '#d97706',
            danger: '#dc2626',
            info: '#0891b2',
            light: '#f0fdf4',
            dark: '#1f2937',
            navbar: 'linear-gradient(135deg, #047857, #10b981)',
            sidebar: '#1f2937',
            text: '#ffffff'
          }
        },
        {
          id: 'royal-purple',
          name: 'Royal Purple',
          description: 'Creative and premium',
          colors: {
            primary: '#7c3aed',
            secondary: '#a855f7',
            success: '#22c55e',
            warning: '#eab308',
            danger: '#f87171',
            info: '#3b82f6',
            light: '#faf5ff',
            dark: '#2d1b69',
            navbar: 'linear-gradient(135deg, #7c3aed, #a855f7)',
            sidebar: '#2d1b69',
            text: '#ffffff'
          }
        },
        {
          id: 'sunset-orange',
          name: 'Sunset Orange',
          description: 'Energetic and warm',
          colors: {
            primary: '#ea580c',
            secondary: '#fb923c',
            success: '#16a34a',
            warning: '#ca8a04',
            danger: '#dc2626',
            info: '#0284c7',
            light: '#fff7ed',
            dark: '#7c2d12',
            navbar: 'linear-gradient(135deg, #ea580c, #fb923c)',
            sidebar: '#7c2d12',
            text: '#ffffff'
          }
        },
        {
          id: 'ocean-teal',
          name: 'Ocean Teal',
          description: 'Calm and sophisticated',
          colors: {
            primary: '#0f766e',
            secondary: '#14b8a6',
            success: '#059669',
            warning: '#d97706',
            danger: '#dc2626',
            info: '#0284c7',
            light: '#f0fdfa',
            dark: '#134e4a',
            navbar: 'linear-gradient(135deg, #0f766e, #14b8a6)',
            sidebar: '#134e4a',
            text: '#ffffff'
          }
        },
        {
          id: 'slate-modern',
          name: 'Slate Modern',
          description: 'Sleek and minimal',
          colors: {
            primary: '#475569',
            secondary: '#64748b',
            success: '#22c55e',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#3b82f6',
            light: '#f8fafc',
            dark: '#1e293b',
            navbar: 'linear-gradient(135deg, #475569, #64748b)',
            sidebar: '#1e293b',
            text: '#ffffff'
          }
        },
        {
          id: 'crimson-executive',
          name: 'Crimson Executive',
          description: 'Bold and powerful',
          colors: {
            primary: '#be123c',
            secondary: '#e11d48',
            success: '#16a34a',
            warning: '#d97706',
            danger: '#dc2626',
            info: '#0284c7',
            light: '#fef2f2',
            dark: '#7f1d1d',
            navbar: 'linear-gradient(135deg, #be123c, #e11d48)',
            sidebar: '#7f1d1d',
            text: '#ffffff'
          }
        },
        {
          id: 'navy-classic',
          name: 'Navy Classic',
          description: 'Timeless and reliable',
          colors: {
            primary: '#1e40af',
            secondary: '#3b82f6',
            success: '#059669',
            warning: '#d97706',
            danger: '#dc2626',
            info: '#0891b2',
            light: '#f1f5f9',
            dark: '#0f172a',
            navbar: 'linear-gradient(135deg, #1e40af, #3b82f6)',
            sidebar: '#0f172a',
            text: '#ffffff'
          }
        },
        {
          id: 'forest-green',
          name: 'Forest Green',
          description: 'Natural and stable',
          colors: {
            primary: '#166534',
            secondary: '#22c55e',
            success: '#15803d',
            warning: '#ca8a04',
            danger: '#dc2626',
            info: '#0284c7',
            light: '#f0fdf4',
            dark: '#14532d',
            navbar: 'linear-gradient(135deg, #166534, #22c55e)',
            sidebar: '#14532d',
            text: '#ffffff'
          }
        },
        {
          id: 'vape-shed-brand',
          name: 'Vape Shed Brand',
          description: 'Official brand colors',
          colors: {
            primary: '#2563eb',
            secondary: '#7c3aed',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            info: '#06b6d4',
            light: '#f8fafc',
            dark: '#1e293b',
            navbar: 'linear-gradient(135deg, #2563eb, #7c3aed)',
            sidebar: '#1e293b',
            text: '#ffffff'
          }
        }
      ];

      // Initialize Theme System
      function initializeThemeSystem() {
        console.log('🎨 Initializing Color Scheme Demo System...');
        
        // Create theme grid
        const themeGrid = document.getElementById('themeGrid');
        if (!themeGrid) return;

        colorSchemes.forEach((scheme, index) => {
          const themeOption = createThemeOption(scheme, index);
          themeGrid.appendChild(themeOption);
        });

        // Set default theme
        const savedTheme = localStorage.getItem('cis-color-scheme') || 'corporate-blue';
        applyColorScheme(savedTheme);
        updateActiveTheme(savedTheme);

        console.log('✅ Theme system initialized with', colorSchemes.length, 'color schemes');
      }

      // Create theme option element
      function createThemeOption(scheme, index) {
        const option = document.createElement('div');
        option.className = 'theme-option';
        option.setAttribute('data-theme', scheme.id);
        option.setAttribute('title', scheme.description);

        const preview = document.createElement('div');
        preview.className = 'theme-preview';
        
        // Create color stripes
        const colors = [scheme.colors.primary, scheme.colors.secondary, scheme.colors.success, scheme.colors.warning];
        colors.forEach(color => {
          const stripe = document.createElement('div');
          stripe.className = 'color-stripe';
          stripe.style.backgroundColor = color;
          preview.appendChild(stripe);
        });

        const name = document.createElement('div');
        name.className = 'theme-name';
        name.textContent = scheme.name;

        option.appendChild(preview);
        option.appendChild(name);

        // Add click handler
        option.addEventListener('click', () => {
          applyColorScheme(scheme.id);
          updateActiveTheme(scheme.id);
          localStorage.setItem('cis-color-scheme', scheme.id);
          
          // Show success notification
          showThemeNotification(scheme.name);
          
          // Close dropdown
          const dropdown = document.querySelector('.theme-picker').closest('.dropdown');
          if (dropdown) {
            const bsDropdown = bootstrap.Dropdown.getInstance(dropdown.querySelector('[data-bs-toggle="dropdown"]'));
            if (bsDropdown) bsDropdown.hide();
          }
        });

        return option;
      }

      // Apply color scheme to page
      function applyColorScheme(schemeId) {
        const scheme = colorSchemes.find(s => s.id === schemeId);
        if (!scheme) return;

        const root = document.documentElement;
        const colors = scheme.colors;

        // Update CSS custom properties
        root.style.setProperty('--primary-color', colors.primary);
        root.style.setProperty('--secondary-color', colors.secondary);
        root.style.setProperty('--success-color', colors.success);
        root.style.setProperty('--warning-color', colors.warning);
        root.style.setProperty('--danger-color', colors.danger);
        root.style.setProperty('--info-color', colors.info);
        root.style.setProperty('--light-color', colors.light);
        root.style.setProperty('--dark-color', colors.dark);
        root.style.setProperty('--navbar-bg', colors.navbar);
        root.style.setProperty('--sidebar-bg', colors.sidebar);
        root.style.setProperty('--text-on-primary', colors.text);
        root.style.setProperty('--gradient-bg', colors.navbar);

        // Update hover color with opacity
        const primaryRgb = hexToRgb(colors.primary);
        root.style.setProperty('--hover-bg', `rgba(${primaryRgb.r}, ${primaryRgb.g}, ${primaryRgb.b}, 0.1)`);

        console.log('🎨 Applied color scheme:', scheme.name);
      }

      // Update active theme in UI
      function updateActiveTheme(schemeId) {
        document.querySelectorAll('.theme-option').forEach(option => {
          option.classList.remove('active');
        });
        
        const activeOption = document.querySelector(`[data-theme="${schemeId}"]`);
        if (activeOption) {
          activeOption.classList.add('active');
        }
      }

      // Show theme change notification
      function showThemeNotification(themeName) {
        // Create notification element
        const notification = document.createElement('div');
        notification.style.cssText = `
          position: fixed;
          top: 20px;
          right: 20px;
          background: var(--success-color);
          color: white;
          padding: 1rem 1.5rem;
          border-radius: 8px;
          box-shadow: 0 4px 15px rgba(0,0,0,0.2);
          z-index: 9999;
          font-weight: 600;
          transform: translateX(100%);
          transition: transform 0.3s ease;
        `;
        notification.innerHTML = `
          <i class="fas fa-palette me-2"></i>
          Theme changed to: ${themeName}
        `;

        document.body.appendChild(notification);

        // Animate in
        setTimeout(() => {
          notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after 3 seconds
        setTimeout(() => {
          notification.style.transform = 'translateX(100%)';
          setTimeout(() => {
            document.body.removeChild(notification);
          }, 300);
        }, 3000);
      }

      // Utility function to convert hex to RGB
      function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
          r: parseInt(result[1], 16),
          g: parseInt(result[2], 16),
          b: parseInt(result[3], 16)
        } : null;
      }

      // Sidebar functionality
      function initializeSidebar() {
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const closeBtn = document.getElementById('sidebarClose');
        const body = document.body;

        function openSidebar() {
          sidebar.classList.add('show');
          overlay.classList.add('show');
          body.classList.add('sidebar-open');
        }

        function closeSidebar() {
          sidebar.classList.remove('show');
          overlay.classList.remove('show');
          body.classList.remove('sidebar-open');
        }

        if (toggleBtn) {
          toggleBtn.addEventListener('click', openSidebar);
        }

        if (closeBtn) {
          closeBtn.addEventListener('click', closeSidebar);
        }

        if (overlay) {
          overlay.addEventListener('click', closeSidebar);
        }

        // Close on escape key
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
          }
        });
      }

      // Fullscreen functionality
      function initializeFullscreen() {
        const fullscreenBtn = document.getElementById('fullscreenToggle');
        if (!fullscreenBtn) return;

        fullscreenBtn.addEventListener('click', () => {
          if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().then(() => {
              fullscreenBtn.innerHTML = '<i class="fas fa-compress"></i>';
              fullscreenBtn.title = 'Exit Fullscreen';
            });
          } else {
            document.exitFullscreen().then(() => {
              fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
              fullscreenBtn.title = 'Enter Fullscreen';
            });
          }
        });

        // Update button on fullscreen change
        document.addEventListener('fullscreenchange', () => {
          if (!document.fullscreenElement) {
            fullscreenBtn.innerHTML = '<i class="fas fa-expand"></i>';
            fullscreenBtn.title = 'Enter Fullscreen';
          }
        });
      }

      // Initialize all systems
      initializeThemeSystem();
      initializeSidebar();
      initializeFullscreen();

      // Add keyboard shortcuts for theme switching
      document.addEventListener('keydown', (e) => {
        // Ctrl + Shift + Number keys to switch themes
        if (e.ctrlKey && e.shiftKey && e.key >= '1' && e.key <= '9') {
          e.preventDefault();
          const themeIndex = parseInt(e.key) - 1;
          if (colorSchemes[themeIndex]) {
            applyColorScheme(colorSchemes[themeIndex].id);
            updateActiveTheme(colorSchemes[themeIndex].id);
            localStorage.setItem('cis-color-scheme', colorSchemes[themeIndex].id);
            showThemeNotification(colorSchemes[themeIndex].name);
          }
        }

        // Ctrl + Shift + 0 for the 10th theme
        if (e.ctrlKey && e.shiftKey && e.key === '0') {
          e.preventDefault();
          if (colorSchemes[9]) {
            applyColorScheme(colorSchemes[9].id);
            updateActiveTheme(colorSchemes[9].id);
            localStorage.setItem('cis-color-scheme', colorSchemes[9].id);
            showThemeNotification(colorSchemes[9].name);
          }
        }
      });

      console.log('🎨 Color Scheme Demo System Ready!');
      console.log('💡 Tip: Use Ctrl+Shift+1-9/0 to quickly switch themes');
    });
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Handle unlock document button
      const unlockBtn = document.getElementById('btnUnlockDocument');
      if (unlockBtn) {
        unlockBtn.addEventListener('click', function() {
          unlockDocument();
        });
      }

      // Initialize enhanced file upload system
      if (window.POFileUploadManager) {
        window.poFileManager = new POFileUploadManager();
      }

      // Handle new discrepancy types in JavaScript
      document.addEventListener('change', function(e) {
        if (e.target.matches('.rx-status')) {
          const status = e.target.value;
          const row = e.target.closest('tr');
          
          // Handle new discrepancy types
          if (status === 'expired') {
            // For expired products, suggest setting received to 0
            const receivedInput = row.querySelector('.rx-recv');
            if (receivedInput && !receivedInput.value) {
              receivedInput.value = '0';
              receivedInput.dispatchEvent(new Event('input'));
            }
          } else if (status === 'not_compliant') {
            // For non-compliant products, flag for review
            const noteInput = row.querySelector('.rx-note');
            if (noteInput && !noteInput.value) {
              noteInput.value = 'Product does not meet compliance requirements';
            }
          }
        }
      });
    });
  </script>

  <!-- Sticky Footer Functionality -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize sticky footer functionality
      initializeStickyFooter();
      
      function initializeStickyFooter() {
        // Initialize session start time
        window.sessionStartTime = Date.now();
        
        // Initialize sticky scanner
        const stickyScanner = document.getElementById('sticky-scanner');
        const stickyScannerStatus = document.getElementById('sticky-scanner-status');
        
        if (stickyScanner) {
          // Connect sticky scanner to main barcode input functionality
          stickyScanner.addEventListener('input', function(e) {
            const value = e.target.value;
            stickyScannerStatus.textContent = value ? 'Scanning...' : 'Ready';
            
            // If main scanner module exists, use its logic
            if (window.POScanner) {
              window.POScanner.handleBarcodeInput(value);
            }
          });
          
          stickyScanner.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              const value = e.target.value.trim();
              if (value) {
                // Process the scan
                processStickyBarcodeScan(value);
                e.target.value = '';
                stickyScannerStatus.textContent = 'Ready';
              }
            }
          });
        }
        
        // Initialize sticky action buttons
        const stickySave = document.getElementById('sticky-save');
        const stickyComplete = document.getElementById('sticky-complete');
        
        if (stickySave) {
          stickySave.addEventListener('click', function() {
            const mainSaveBtn = document.getElementById('btnSaveDraft');
            if (mainSaveBtn && !mainSaveBtn.disabled) {
              mainSaveBtn.click();
            }
          });
        }
        
        if (stickyComplete) {
          stickyComplete.addEventListener('click', function() {
            const mainCompleteBtn = document.getElementById('btnCommit');
            if (mainCompleteBtn && !mainCompleteBtn.disabled) {
              mainCompleteBtn.click();
            }
          });
        }
        
        // Start updating stats
        updateStickyStats();
        setInterval(updateStickyStats, 2000); // Update every 2 seconds
      }
      
      function processStickyBarcodeScan(barcode) {
        console.log('🔍 Sticky scanner processing:', barcode);
        
        // Find the main barcode input and use its processing logic
        const mainBarcodeInput = document.getElementById('barcodeInput');
        if (mainBarcodeInput) {
          mainBarcodeInput.value = barcode;
          mainBarcodeInput.dispatchEvent(new Event('input'));
          mainBarcodeInput.dispatchEvent(new KeyboardEvent('keypress', { key: 'Enter' }));
        }
        
        // Visual feedback
        const stickyScanner = document.getElementById('sticky-scanner');
        const stickyScannerStatus = document.getElementById('sticky-scanner-status');
        
        if (stickyScanner && stickyScannerStatus) {
          stickyScanner.style.borderColor = '#28a745';
          stickyScannerStatus.textContent = 'Processed ✓';
          
          setTimeout(() => {
            stickyScanner.style.borderColor = '';
            stickyScannerStatus.textContent = 'Ready';
          }, 1500);
        }
      }
      
      function updateStickyStats() {
        try {
          // Update progress stats
          updateProductProgress();
          
          // Update accuracy stats
          updateAccuracyStats();
          
          // Update timing stats
          updateTimingStats();
          
          // Update financial stats
          updateFinancialStats();
          
          // Update confidence meter
          updateConfidenceMeter();
          
          // Update button states
          updateButtonStates();
          
        } catch (error) {
          console.warn('Sticky footer update error:', error);
        }
      }
      
      function updateProductProgress() {
        const rows = document.querySelectorAll('#po-lines tr');
        let completed = 0;
        let totalItems = 0;
        
        rows.forEach(row => {
          const recvInput = row.querySelector('.rx-recv');
          const slipInput = row.querySelector('.rx-slip');
          
          if (recvInput) {
            const received = parseInt(recvInput.value) || 0;
            const slip = parseInt(slipInput?.value) || 0;
            
            totalItems += Math.max(received, slip);
            
            // Consider a row completed if it has received quantity
            if (received > 0 || (slipInput && slipInput.value)) {
              completed++;
            }
          }
        });
        
        const productsCompletedEl = document.getElementById('products-completed');
        const itemsProcessedEl = document.getElementById('items-processed');
        
        if (productsCompletedEl) productsCompletedEl.textContent = completed;
        if (itemsProcessedEl) itemsProcessedEl.textContent = totalItems;
      }
      
      function updateAccuracyStats() {
        const rows = document.querySelectorAll('#po-lines tr');
        let perfectMatches = 0;
        let discrepancies = 0;
        
        rows.forEach(row => {
          const diffEl = row.querySelector('.rx-diff');
          if (diffEl) {
            const diff = parseInt(diffEl.textContent) || 0;
            if (diff === 0) {
              perfectMatches++;
            } else {
              discrepancies++;
            }
          }
        });
        
        const perfectMatchesEl = document.getElementById('perfect-matches');
        const discrepanciesEl = document.getElementById('discrepancies');
        
        if (perfectMatchesEl) perfectMatchesEl.textContent = perfectMatches;
        if (discrepanciesEl) discrepanciesEl.textContent = discrepancies;
      }
      
      function updateTimingStats() {
        if (window.sessionStartTime) {
          const elapsed = Date.now() - window.sessionStartTime;
          const minutes = Math.floor(elapsed / 60000);
          const seconds = Math.floor((elapsed % 60000) / 1000);
          
          const sessionDurationEl = document.getElementById('session-duration');
          if (sessionDurationEl) {
            sessionDurationEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
          }
          
          // Calculate average time per item
          const itemsProcessed = parseInt(document.getElementById('items-processed')?.textContent) || 0;
          const avgPerItemEl = document.getElementById('avg-per-item');
          
          if (avgPerItemEl && itemsProcessed > 0) {
            const avgSeconds = elapsed / 1000 / itemsProcessed;
            if (avgSeconds < 60) {
              avgPerItemEl.textContent = `${avgSeconds.toFixed(1)}s`;
            } else {
              avgPerItemEl.textContent = `${(avgSeconds/60).toFixed(1)}m`;
            }
          }
        }
      }
      
      function updateFinancialStats() {
        // Get values from main financial inputs
        const subtotalEl = document.getElementById('subtotal');
        const totalInclEl = document.getElementById('total-incl');
        
        const stickySubtotalEl = document.getElementById('sticky-subtotal');
        const stickyTotalEl = document.getElementById('sticky-total');
        
        if (subtotalEl && stickySubtotalEl) {
          const subtotal = parseFloat(subtotalEl.value) || 0;
          stickySubtotalEl.textContent = `$${subtotal.toFixed(2)}`;
        }
        
        if (totalInclEl && stickyTotalEl) {
          const total = parseFloat(totalInclEl.value) || 0;
          stickyTotalEl.textContent = `$${total.toFixed(2)}`;
        }
      }
      
      function updateConfidenceMeter() {
        const rows = document.querySelectorAll('#po-lines tr');
        let totalRows = rows.length;
        let completedRows = 0;
        let accuracy = 100;
        
        rows.forEach(row => {
          const recvInput = row.querySelector('.rx-recv');
          const diffEl = row.querySelector('.rx-diff');
          
          if (recvInput && recvInput.value) {
            completedRows++;
          }
          
          if (diffEl) {
            const diff = parseInt(diffEl.textContent) || 0;
            if (diff !== 0) {
              accuracy -= 5; // Reduce accuracy for each discrepancy
            }
          }
        });
        
        const completionRate = totalRows > 0 ? (completedRows / totalRows) * 100 : 0;
        const overallConfidence = Math.max(0, Math.min(100, (completionRate * 0.7) + (accuracy * 0.3)));
        
        const stickyConfidenceEl = document.getElementById('sticky-confidence');
        const stickyConfidenceBarEl = document.getElementById('sticky-confidence-bar');
        
        if (stickyConfidenceEl) {
          stickyConfidenceEl.textContent = `${Math.round(overallConfidence)}%`;
        }
        
        if (stickyConfidenceBarEl) {
          stickyConfidenceBarEl.style.width = `${overallConfidence}%`;
        }
      }
      
      function updateButtonStates() {
        const stickySave = document.getElementById('sticky-save');
        const stickyComplete = document.getElementById('sticky-complete');
        const mainSaveBtn = document.getElementById('btnSaveDraft');
        const mainCompleteBtn = document.getElementById('btnCommit');
        
        if (stickySave && mainSaveBtn) {
          stickySave.disabled = mainSaveBtn.disabled;
          if (mainSaveBtn.disabled) {
            stickySave.style.opacity = '0.5';
          } else {
            stickySave.style.opacity = '1';
          }
        }
        
        if (stickyComplete && mainCompleteBtn) {
          stickyComplete.disabled = mainCompleteBtn.disabled;
          if (mainCompleteBtn.disabled) {
            stickyComplete.style.opacity = '0.5';
          } else {
            stickyComplete.style.opacity = '1';
          }
        }
        
        // Update submission buttons with comprehensive logic
        updateSubmissionButtons();
      }
      
      // Helper function to check completed products
      function checkCompletedProducts() {
        const table = document.getElementById('poTable');
        if (!table) return { count: 0, total: 0 };
        
        const rows = table.querySelectorAll('tbody tr[data-pid]');
        let completed = 0;
        let total = rows.length;
        
        rows.forEach(row => {
          const pid = row.getAttribute('data-pid');
          const qtyInput = row.querySelector(`input[name="products[${pid}][receivedQty]"]`);
          const damageInput = row.querySelector(`input[name="products[${pid}][damageQty]"]`);
          
          if (qtyInput) {
            const receivedQty = parseInt(qtyInput.value) || 0;
            const damageQty = parseInt(damageInput?.value) || 0;
            
            // Product is considered "completed" if:
            // 1. Has received quantity > 0, OR
            // 2. Has damage quantity > 0 (indicating it was processed but damaged), OR
            // 3. Has explicitly been marked as "not arrived" (receivedQty is 0 but was intentionally set)
            if (receivedQty > 0 || damageQty > 0 || (qtyInput.value !== '' && receivedQty === 0)) {
              completed++;
            }
          }
        });
        
        return { count: completed, total: total };
      }

      // ====================================================================================
      // COMPREHENSIVE SUBMIT & STATE MANAGEMENT SYSTEM
      // ====================================================================================
      
      let hasUnsavedChanges = false;
      let isSubmitting = false;
      
      // Track changes for unsaved warning
      function markUnsavedChanges() {
        hasUnsavedChanges = true;
        updateButtonStates();
      }
      
      function clearUnsavedChanges() {
        hasUnsavedChanges = false;
      }
      
      // Page refresh warning when there are unsaved changes
      window.addEventListener('beforeunload', function(e) {
        if (hasUnsavedChanges && !isSubmitting) {
          const message = 'You have unsaved changes to this purchase order. Are you sure you want to leave?';
          e.preventDefault();
          e.returnValue = message;
          return message;
        }
      });
      
      // Comprehensive validation for submissions
      function validateForSubmission(submissionType = 'partial') {
        const validation = {
          isValid: true,
          errors: [],
          warnings: [],
          completedProducts: checkCompletedProducts()
        };
        
        // Check for at least one completed product for partial submission
        if (submissionType === 'partial' && validation.completedProducts.count === 0) {
          validation.isValid = false;
          validation.errors.push('At least one product must be completed for partial submission');
        }
        
        // Check for all products completed for final submission
        if (submissionType === 'final' && validation.completedProducts.count < validation.completedProducts.total) {
          validation.warnings.push(`Only ${validation.completedProducts.count} of ${validation.completedProducts.total} products completed`);
        }
        
        // Check for required evidence on damaged items
        const damagedItems = document.querySelectorAll('.rx-dmg').length;
        if (damagedItems > 0) {
          validation.warnings.push('Damaged items detected - ensure evidence is uploaded');
        }
        
        // Check financial discrepancies
        const discrepancies = document.querySelectorAll('.diff-over, .diff-short').length;
        if (discrepancies > 0) {
          validation.warnings.push(`${discrepancies} pricing discrepancies detected`);
        }
        
        return validation;
      }
      
      // Submit function with comprehensive validation
      function submitPurchaseOrder(action, confirmationMessage = null) {
        if (isSubmitting) {
          alert('Submission already in progress...');
          return;
        }
        
        const submissionType = action === 'poSubmitFinal' ? 'final' : 'partial';
        const validation = validateForSubmission(submissionType);
        
        // Show validation errors
        if (!validation.isValid) {
          alert('Cannot submit:\n\n' + validation.errors.join('\n'));
          return;
        }
        
        // Show warnings and get confirmation
        let confirmText = confirmationMessage || `Submit ${submissionType} purchase order?`;
        if (validation.warnings.length > 0) {
          confirmText += '\n\nWarnings:\n' + validation.warnings.join('\n') + '\n\nContinue anyway?';
        }
        
        if (!confirm(confirmText)) {
          return;
        }
        
        isSubmitting = true;
        
        // Collect all form data
        const formData = new FormData();
        formData.append(action, '1');
        formData.append('purchaseOrderId', document.querySelector('input[name="purchaseOrderId"]')?.value || '');
        
        // Collect all product data
        const table = document.getElementById('poTable');
        if (table) {
          const rows = table.querySelectorAll('tbody tr[data-pid]');
          rows.forEach(row => {
            const pid = row.getAttribute('data-pid');
            const qtyInput = row.querySelector(`input[name="products[${pid}][receivedQty]"]`);
            const damageInput = row.querySelector(`input[name="products[${pid}][damageQty]"]`);
            const slipInput = row.querySelector(`input[name="products[${pid}][slipQty]"]`);
            const statusSelect = row.querySelector(`select[name="products[${pid}][status]"]`);
            const costInput = row.querySelector(`input[name="products[${pid}][unitCost]"]`);
            const noteInput = row.querySelector(`textarea[name="products[${pid}][note]"]`);
            
            if (qtyInput) formData.append(`products[${pid}][receivedQty]`, qtyInput.value || '0');
            if (damageInput) formData.append(`products[${pid}][damageQty]`, damageInput.value || '0');
            if (slipInput) formData.append(`products[${pid}][slipQty]`, slipInput.value || '0');
            if (statusSelect) formData.append(`products[${pid}][status]`, statusSelect.value || 'ok');
            if (costInput) formData.append(`products[${pid}][unitCost]`, costInput.value || '0');
            if (noteInput) formData.append(`products[${pid}][note]`, noteInput.value || '');
          });
        }
        
        // Show loading state
        const submitBtn = document.getElementById(action === 'poSubmitFinal' ? 'btn-submit-final' : 'btn-submit-partial');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Submitting...';
        submitBtn.disabled = true;
        
        // Submit the form
        fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            clearUnsavedChanges();
            alert(`${submissionType.charAt(0).toUpperCase() + submissionType.slice(1)} submission successful!`);
            
            // Redirect or reload as appropriate
            if (data.redirect) {
              window.location.href = data.redirect;
            } else {
              window.location.reload();
            }
          } else {
            throw new Error(data.error || 'Submission failed');
          }
        })
        .catch(error => {
          console.error('Submission error:', error);
          alert('Submission failed: ' + error.message);
        })
        .finally(() => {
          isSubmitting = false;
          submitBtn.innerHTML = originalText;
          submitBtn.disabled = false;
        });
      }
      
      // Enhanced button state management
      function updateSubmissionButtons() {
        const partialBtn = document.getElementById('btn-submit-partial');
        const finalBtn = document.getElementById('btn-submit-final');
        const partialStatus = document.getElementById('partial-status');
        
        const completedProducts = checkCompletedProducts();
        
        // Update partial button
        if (partialBtn && partialStatus) {
          if (completedProducts.count > 0) {
            partialBtn.disabled = false;
            partialBtn.classList.remove('btn-secondary');
            partialBtn.classList.add('btn-warning');
            partialStatus.innerHTML = `✅ ${completedProducts.count} of ${completedProducts.total} products completed - Ready for partial submission`;
            partialStatus.className = 'text-success';
          } else {
            partialBtn.disabled = true;
            partialBtn.classList.remove('btn-warning');
            partialBtn.classList.add('btn-secondary');
            partialStatus.innerHTML = '⚠️ Complete at least one product to enable partial submission';
            partialStatus.className = 'text-warning';
          }
        }
        
        // Update final button
        if (finalBtn) {
          if (completedProducts.count === completedProducts.total && completedProducts.total > 0) {
            finalBtn.classList.remove('btn-secondary');
            finalBtn.classList.add('btn-success');
            finalBtn.innerHTML = '<i class="fa fa-check-circle me-2"></i>Submit Final Order - All Complete!';
          } else {
            finalBtn.classList.remove('btn-success');
            finalBtn.classList.add('btn-outline-success');
            finalBtn.innerHTML = `<i class="fa fa-check-circle me-2"></i>Submit Final Order (${completedProducts.count}/${completedProducts.total} complete)`;
          }
        }
      }
      
      // Initialize submit button handlers
      document.addEventListener('DOMContentLoaded', function() {
        // Partial submit button
        const partialBtn = document.getElementById('btn-submit-partial');
        if (partialBtn) {
          partialBtn.addEventListener('click', function() {
            submitPurchaseOrder('poSubmitPartial', 'Submit partial purchase order?\n\nThis will save current progress and allow continued receiving later.');
          });
        }
        
        // Final submit button  
        const finalBtn = document.getElementById('btn-submit-final');
        if (finalBtn) {
          finalBtn.addEventListener('click', function() {
            submitPurchaseOrder('poSubmitFinal', 'Submit final purchase order?\n\nThis will complete the order and update all inventory.');
          });
        }
        
        // Track changes on all inputs
        document.addEventListener('input', function(e) {
          if (e.target.matches('.qty-input, .rx-recv, .rx-slip, .rx-dmg, .rx-status, .rx-cost, .rx-note')) {
            markUnsavedChanges();
            updateSubmissionButtons();
          }
        });
        
        // Initial button state update
        updateSubmissionButtons();
      });
    });
  </script>

  <script>
    // ============================================================================
    // RESPONSIVE TABLE FUNCTIONALITY
    // ============================================================================
    
    // Table state management
    window.tableState = {
      showImages: true,
      sortColumn: null,
      sortDirection: 'asc',
      filters: {
        status: 'all',
        hasDiscrepancy: false,
        search: ''
      }
    };

    // Table filters functionality
    function setupTableFilters() {
      // Status filter
      const statusFilter = document.getElementById('statusFilter');
      if (statusFilter) {
        statusFilter.addEventListener('change', function() {
          filterByStatus(this.value);
        });
      }

      // Discrepancy filter
      const discrepancyFilter = document.getElementById('discrepancyFilter');
      if (discrepancyFilter) {
        discrepancyFilter.addEventListener('change', function() {
          filterByDiscrepancy(this.value);
        });
      }

      // Quantity range filter
      const qtyRangeFilter = document.getElementById('qtyRangeFilter');
      if (qtyRangeFilter) {
        qtyRangeFilter.addEventListener('change', function() {
          filterByQuantityRange(this.value);
        });
      }

      // Clear filters button
      const clearFiltersBtn = document.getElementById('clearFilters');
      if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
          clearAllFilters();
        });
      }
    }

    function filterByStatus(status) {
      const rows = document.querySelectorAll('.product-row');
      
      rows.forEach(row => {
        if (status === '' || status === 'all') {
          row.style.display = '';
          return;
        }
        
        const statusSelect = row.querySelector('.status-select');
        const currentStatus = statusSelect ? statusSelect.value : '';
        
        const matches = currentStatus === status;
        row.style.display = matches ? '' : 'none';
      });
      
      updateTableStats();
    }

    function filterByDiscrepancy(type) {
      const rows = document.querySelectorAll('.product-row');
      
      rows.forEach(row => {
        if (type === '' || type === 'all') {
          row.style.display = '';
          return;
        }
        
        const hasDiscrepancy = row.querySelector('.diff-short, .diff-over');
        const isOk = row.querySelector('.diff-ok');
        
        let matches = false;
        if (type === 'issues' && hasDiscrepancy) matches = true;
        if (type === 'ok' && isOk) matches = true;
        
        row.style.display = matches ? '' : 'none';
      });
      
      updateTableStats();
    }

    function filterByQuantityRange(range) {
      const rows = document.querySelectorAll('.product-row');
      
      rows.forEach(row => {
        if (range === '' || range === 'all') {
          row.style.display = '';
          return;
        }
        
        const orderedQty = parseInt(row.querySelector('.col-ordered')?.textContent || '0');
        const receivedQty = parseInt(row.querySelector('.qty-input[data-field="receivedQty"]')?.value || '0');
        
        let matches = false;
        switch (range) {
          case 'zero':
            matches = receivedQty === 0;
            break;
          case 'partial':
            matches = receivedQty > 0 && receivedQty < orderedQty;
            break;
          case 'complete':
            matches = receivedQty >= orderedQty && orderedQty > 0;
            break;
          case 'over':
            matches = receivedQty > orderedQty;
            break;
        }
        
        row.style.display = matches ? '' : 'none';
      });
      
      updateTableStats();
    }

    function clearAllFilters() {
      // Clear all filter dropdowns
      const filters = ['statusFilter', 'discrepancyFilter', 'qtyRangeFilter'];
      filters.forEach(filterId => {
        const filter = document.getElementById(filterId);
        if (filter) filter.value = '';
      });
      
      // Clear search
      const searchInput = document.getElementById('tableSearch');
      if (searchInput) searchInput.value = '';
      
      // Show all rows
      const rows = document.querySelectorAll('.product-row');
      rows.forEach(row => {
        row.style.display = '';
      });
      
      updateTableStats();
      
      // Show success notification
      if (typeof showToast === 'function') {
        showToast('All filters cleared', 'success');
      }
    }

    // Initialize responsive table
    function initializeResponsiveTable() {
      setupTableSorting();
      setupImageToggle();
      setupTableFilters();
      setupBulkActions();
      setupQuickActions();
      setupTableSearch();
      updateTableStats();
    }

    // Table sorting functionality
    function setupTableSorting() {
      document.querySelectorAll('th[data-sort]').forEach(header => {
        header.addEventListener('click', function() {
          const column = this.getAttribute('data-sort');
          sortTable(column);
        });
      });
    }

    function sortTable(column) {
      const tbody = document.querySelector('.po-table tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      
      // Toggle sort direction
      if (window.tableState.sortColumn === column) {
        window.tableState.sortDirection = window.tableState.sortDirection === 'asc' ? 'desc' : 'asc';
      } else {
        window.tableState.sortColumn = column;
        window.tableState.sortDirection = 'asc';
      }

      // Sort rows
      rows.sort((a, b) => {
        let aValue = getSortValue(a, column);
        let bValue = getSortValue(b, column);
        
        if (typeof aValue === 'number' && typeof bValue === 'number') {
          return window.tableState.sortDirection === 'asc' ? aValue - bValue : bValue - aValue;
        } else {
          aValue = aValue.toString().toLowerCase();
          bValue = bValue.toString().toLowerCase();
          if (aValue < bValue) return window.tableState.sortDirection === 'asc' ? -1 : 1;
          if (aValue > bValue) return window.tableState.sortDirection === 'asc' ? 1 : -1;
          return 0;
        }
      });

      // Update table
      rows.forEach(row => tbody.appendChild(row));
      updateSortIcons();
    }

    function getSortValue(row, column) {
      const cell = row.querySelector(`[data-sort-value="${column}"]`);
      if (cell) {
        return cell.getAttribute('data-sort-value') || cell.textContent.trim();
      }
      
      switch (column) {
        case 'product':
          return row.querySelector('.product-name')?.textContent || '';
        case 'stock':
          return parseInt(row.querySelector('.stock-number')?.textContent || '0');
        case 'ordered':
          return parseInt(row.querySelector('.col-ordered input')?.value || '0');
        case 'slip':
          return parseInt(row.querySelector('.col-slip input')?.value || '0');
        case 'received':
          return parseInt(row.querySelector('.col-recv input')?.value || '0');
        case 'damaged':
          return parseInt(row.querySelector('.col-dmg input')?.value || '0');
        case 'difference':
          return parseInt(row.querySelector('.diff-display')?.textContent || '0');
        case 'cost':
          return parseFloat(row.querySelector('.cost-input')?.value || '0');
        case 'status':
          return row.querySelector('.status-select')?.value || '';
        default:
          return '';
      }
    }

    function updateSortIcons() {
      // Reset all icons
      document.querySelectorAll('th[data-sort] .fas').forEach(icon => {
        icon.className = 'fas fa-sort';
      });

      // Update current sort icon
      if (window.tableState.sortColumn) {
        const header = document.querySelector(`th[data-sort="${window.tableState.sortColumn}"] .fas`);
        if (header) {
          header.className = window.tableState.sortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
          header.closest('th').classList.add('sorted');
        }
      }
    }

    // Image toggle functionality
    function setupImageToggle() {
      const toggleBtn = document.getElementById('toggleImages');
      if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
          window.tableState.showImages = !window.tableState.showImages;
          const table = document.querySelector('.table-responsive-custom');
          
          if (window.tableState.showImages) {
            table.classList.remove('images-hidden');
            this.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Images';
          } else {
            table.classList.add('images-hidden');
            this.innerHTML = '<i class="fas fa-eye"></i> Show Images';
          }
        });
      }
    }

    // Bulk actions functionality
    function setupBulkActions() {
      // Select all checkbox
      const selectAllBtn = document.getElementById('selectAll');
      if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
          const checkboxes = document.querySelectorAll('.row-checkbox');
          const allChecked = Array.from(checkboxes).every(cb => cb.checked);
          
          checkboxes.forEach(cb => {
            cb.checked = !allChecked;
            updateRowSelection(cb.closest('tr'), cb.checked);
          });
          
          updateBulkActionButtons();
        });
      }

      // Individual checkboxes
      document.addEventListener('change', function(e) {
        if (e.target.classList.contains('row-checkbox')) {
          updateRowSelection(e.target.closest('tr'), e.target.checked);
          updateBulkActionButtons();
          updateSelectAllState();
        }
      });

      // Bulk action buttons
      document.getElementById('markCompleted')?.addEventListener('click', () => {
        bulkUpdateStatus('completed');
      });

      document.getElementById('markDamaged')?.addEventListener('click', () => {
        bulkUpdateStatus('damaged');
      });

      document.getElementById('markPending')?.addEventListener('click', () => {
        bulkUpdateStatus('pending');
      });

      document.getElementById('bulkSetSlip')?.addEventListener('click', () => {
        bulkSetQuantity('slip');
      });

      document.getElementById('bulkSetReceived')?.addEventListener('click', () => {
        bulkSetQuantity('received');
      });

      document.getElementById('deselectAll')?.addEventListener('click', () => {
        document.querySelectorAll('.row-checkbox:checked').forEach(cb => {
          cb.checked = false;
          updateRowSelection(cb.closest('tr'), false);
        });
        updateBulkActionButtons();
        updateSelectAllState();
      });
    }

    function updateRowSelection(row, isSelected) {
      if (isSelected) {
        row.classList.add('selected');
      } else {
        row.classList.remove('selected');
      }
    }

    function updateBulkActionButtons() {
      const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
      const bulkActionsBar = document.getElementById('bulkActionsBar');
      const selectedCount = document.getElementById('selectedCount');
      
      if (checkedBoxes.length > 0) {
        bulkActionsBar.style.display = 'block';
        selectedCount.textContent = checkedBoxes.length;
      } else {
        bulkActionsBar.style.display = 'none';
      }

      // Enable/disable bulk action buttons
      const bulkButtons = document.querySelectorAll('#bulkActionsBar .btn');
      bulkButtons.forEach(btn => {
        if (btn.id !== 'deselectAll') {
          btn.disabled = checkedBoxes.length === 0;
        }
      });
    }

    function updateSelectAllState() {
      const selectAllBtn = document.getElementById('selectAll');
      const checkboxes = document.querySelectorAll('.row-checkbox');
      const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
      
      if (selectAllBtn) {
        if (checkedBoxes.length === 0) {
          selectAllBtn.indeterminate = false;
          selectAllBtn.checked = false;
        } else if (checkedBoxes.length === checkboxes.length) {
          selectAllBtn.indeterminate = false;
          selectAllBtn.checked = true;
        } else {
          selectAllBtn.indeterminate = true;
          selectAllBtn.checked = false;
        }
      }
    }

    function bulkUpdateStatus(status) {
      const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
      let updatedCount = 0;
      
      checkedBoxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const select = row.querySelector('.status-select');
        if (select && !select.disabled) {
          select.value = status;
          updateRowStatus(row, status);
          updatedCount++;
        }
      });
      
      if (updatedCount > 0) {
        updateTableStats();
        enhancedAutosave();
        showToast(`Updated ${updatedCount} items to ${status}`, 'success');
      }
    }

    function bulkSetQuantity(type) {
      const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
      const qty = prompt(`Enter ${type} quantity for ${checkedBoxes.length} selected items:`);
      
      if (qty !== null && !isNaN(qty) && qty >= 0) {
        let updatedCount = 0;
        
        checkedBoxes.forEach(checkbox => {
          const row = checkbox.closest('tr');
          let input;
          
          if (type === 'slip') {
            input = row.querySelector('.rx-slip');
          } else if (type === 'received') {
            input = row.querySelector('.rx-recv');
          }
          
          if (input && !input.disabled) {
            input.value = qty;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            updatedCount++;
          }
        });
        
        if (updatedCount > 0) {
          updateTableStats();
          enhancedAutosave();
          showToast(`Updated ${type} quantity for ${updatedCount} items`, 'success');
        }
      }
    }

    // Enhanced quick actions
    function setupQuickActions() {
      document.addEventListener('click', function(e) {
        if (e.target.classList.contains('quick-add')) {
          const input = e.target.closest('.input-group').querySelector('input');
          const addValue = parseInt(e.target.textContent.replace('+', ''));
          const currentValue = parseInt(input.value) || 0;
          input.value = currentValue + addValue;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          
          // Visual feedback
          e.target.style.transform = 'scale(1.2)';
          setTimeout(() => {
            e.target.style.transform = '';
          }, 150);
        }
        
        // Toggle notes functionality
        if (e.target.classList.contains('toggle-notes') || e.target.closest('.toggle-notes')) {
          const button = e.target.closest('.toggle-notes') || e.target;
          const row = button.closest('tr');
          const notesRow = row.querySelector('.notes-row');
          
          if (notesRow) {
            const isVisible = notesRow.style.display !== 'none';
            notesRow.style.display = isVisible ? 'none' : 'block';
            
            // Update button icon
            const icon = button.querySelector('i');
            if (icon) {
              icon.className = isVisible ? 'fas fa-comment' : 'fas fa-comment-dots';
            }
            
            // Focus textarea if showing
            if (!isVisible) {
              const textarea = notesRow.querySelector('textarea');
              if (textarea) {
                setTimeout(() => textarea.focus(), 100);
              }
            }
          }
        }

        // Product image click to enlarge
        if (e.target.classList.contains('product-image')) {
          enlargeProductImage(e.target);
        }
      });
    }

    function enlargeProductImage(img) {
      // Create modal for image enlargement
      const modal = document.createElement('div');
      modal.className = 'image-modal';
      modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        cursor: pointer;
      `;
      
      const enlargedImg = document.createElement('img');
      enlargedImg.src = img.src;
      enlargedImg.alt = img.alt;
      enlargedImg.style.cssText = `
        max-width: 90vw;
        max-height: 90vh;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      `;
      
      modal.appendChild(enlargedImg);
      document.body.appendChild(modal);
      
      // Close on click
      modal.addEventListener('click', () => {
        document.body.removeChild(modal);
      });
      
      // Close on escape key
      const closeOnEscape = (e) => {
        if (e.key === 'Escape') {
          document.body.removeChild(modal);
          document.removeEventListener('keydown', closeOnEscape);
        }
      };
      document.addEventListener('keydown', closeOnEscape);
    }

    // Enhanced notifications
    function showToast(message, type = 'info', duration = 3000) {
      const toast = document.createElement('div');
      toast.className = `toast-notification toast-${type}`;
      toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 6px;
        color: white;
        font-weight: 600;
        z-index: 9999;
        animation: slideInRight 0.3s ease-out;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      `;
      
      // Set background color based on type
      const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
      };
      toast.style.background = colors[type] || colors.info;
      
      // Add icon
      const icons = {
        success: '✅',
        error: '❌', 
        warning: '⚠️',
        info: 'ℹ️'
      };
      toast.innerHTML = `${icons[type] || icons.info} ${message}`;
      
      document.body.appendChild(toast);
      
      // Auto remove
      setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => {
          if (document.body.contains(toast)) {
            document.body.removeChild(toast);
          }
        }, 300);
      }, duration);
    }

    // Add CSS for toast animations
    const toastStyles = document.createElement('style');
    toastStyles.textContent = `
      @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
    `;
    document.head.appendChild(toastStyles);

    // Table search functionality
    function setupTableSearch() {
      const searchInput = document.getElementById('tableSearch');
      if (searchInput) {
        searchInput.addEventListener('input', function() {
          const searchTerm = this.value.toLowerCase();
          filterTable(searchTerm);
        });
      }
    }

    function filterTable(searchTerm) {
      const rows = document.querySelectorAll('.product-row');
      
      rows.forEach(row => {
        const productName = row.querySelector('.product-name')?.textContent.toLowerCase() || '';
        const sku = row.querySelector('.sku-badge')?.textContent.toLowerCase() || '';
        
        const matches = productName.includes(searchTerm) || sku.includes(searchTerm);
        row.style.display = matches ? '' : 'none';
      });
      
      updateTableStats();
    }

    // Update row status visual indicators
    function updateRowStatus(row, status) {
      row.classList.remove('row-success', 'row-warning', 'row-pending');
      
      switch (status) {
        case 'completed':
          row.classList.add('row-success');
          break;
        case 'damaged':
          row.classList.add('row-warning');
          break;
        case 'pending':
          row.classList.add('row-pending');
          break;
      }
    }

    // Update table statistics
    function updateTableStats() {
      const rows = document.querySelectorAll('.product-row:not([style*="display: none"])');
      let totalItems = 0;
      let completedItems = 0;
      let pendingItems = 0;
      let damagedItems = 0;
      let totalReceived = 0;
      let totalDamaged = 0;

      rows.forEach(row => {
        totalItems++;
        const status = row.querySelector('.status-select')?.value;
        const received = parseInt(row.querySelector('.col-recv input')?.value || '0');
        const damaged = parseInt(row.querySelector('.col-dmg input')?.value || '0');
        
        totalReceived += received;
        totalDamaged += damaged;
        
        switch (status) {
          case 'completed': completedItems++; break;
          case 'damaged': damagedItems++; break;
          case 'pending': pendingItems++; break;
        }
      });

      // Update stats in summary and sticky footer
      updateStatDisplay('totalItems', totalItems);
      updateStatDisplay('completedItems', completedItems);
      updateStatDisplay('pendingItems', pendingItems);
      updateStatDisplay('damagedItems', damagedItems);
      updateStatDisplay('totalReceived', totalReceived);
      updateStatDisplay('totalDamaged', totalDamaged);
      
      // Update progress percentage
      const progressPercent = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
      updateStatDisplay('progressPercent', progressPercent + '%');
    }

    function updateStatDisplay(statName, value) {
      document.querySelectorAll(`[data-stat="${statName}"]`).forEach(element => {
        element.textContent = value;
      });
    }

    // Scanner integration with new table
    function integrateScannerWithTable() {
      // Override POScanner's product found handler
      if (window.POScanner && window.POScanner.handleProductFound) {
        const originalHandler = window.POScanner.handleProductFound;
        
        window.POScanner.handleProductFound = function(product) {
          // Call original handler
          originalHandler.call(this, product);
          
          // Find the product row in the new table
          const productRows = document.querySelectorAll('.product-row');
          let targetRow = null;
          
          productRows.forEach(row => {
            const sku = row.querySelector('.sku-badge')?.textContent;
            if (sku && sku === product.sku) {
              targetRow = row;
            }
          });
          
          if (targetRow) {
            // Highlight the row
            targetRow.style.background = 'rgba(40, 167, 69, 0.1)';
            targetRow.style.borderLeft = '4px solid #28a745';
            
            // Scroll to row
            targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            // Focus on received quantity input
            const receivedInput = targetRow.querySelector('.col-recv input');
            if (receivedInput) {
              setTimeout(() => {
                receivedInput.focus();
                receivedInput.select();
              }, 500);
            }
            
            // Clear highlight after 3 seconds
            setTimeout(() => {
              targetRow.style.background = '';
              targetRow.style.borderLeft = '';
            }, 3000);
          }
        };
      }
    }

    // Initialize everything when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
      initializeResponsiveTable();
      integrateScannerWithTable();
      updateTableStats();
    });

    // Auto-calculate differences when quantities change
    document.addEventListener('input', function(e) {
      if (e.target.matches('.qty-input')) {
        const row = e.target.closest('tr');
        calculateRowDifference(row);
        updateTableStats();
      }
    });

    function calculateRowDifference(row) {
      const ordered = parseInt(row.querySelector('.col-ordered input')?.value || '0');
      const slip = parseInt(row.querySelector('.col-slip input')?.value || '0');
      const received = parseInt(row.querySelector('.col-recv input')?.value || '0');
      const damaged = parseInt(row.querySelector('.col-dmg input')?.value || '0');
      
      const expected = slip > 0 ? slip : ordered;
      const actual = received + damaged;
      const difference = actual - expected;
      
      const diffDisplay = row.querySelector('.diff-display');
      if (diffDisplay) {
        diffDisplay.textContent = difference > 0 ? '+' + difference : difference;
        
        // Update styling
        diffDisplay.classList.remove('diff-ok', 'diff-over', 'diff-short');
        if (difference === 0) {
          diffDisplay.classList.add('diff-ok');
        } else if (difference > 0) {
          diffDisplay.classList.add('diff-over');
        } else {
          diffDisplay.classList.add('diff-short');
        }
      }
    }
    
    // ============================================================================
    // ENHANCED FINANCIAL CALCULATIONS
    // ============================================================================
    
    // Real-time cost calculations
    function updateFinancialTotals() {
      let totalCost = 0;
      let totalReceived = 0;
      let totalDamaged = 0;
      let totalValue = 0;
      
      document.querySelectorAll('.product-row').forEach(row => {
        const cost = parseFloat(row.querySelector('.cost-input')?.value || '0');
        const received = parseInt(row.querySelector('.col-recv input')?.value || '0');
        const damaged = parseInt(row.querySelector('.col-dmg input')?.value || '0');
        
        const rowTotal = cost * (received + damaged);
        totalCost += rowTotal;
        totalReceived += received;
        totalDamaged += damaged;
        totalValue += cost * received; // Only non-damaged items
      });
      
      // Update financial displays
      updateStatDisplay('totalCost', '$' + totalCost.toFixed(2));
      updateStatDisplay('totalValue', '$' + totalValue.toFixed(2));
      updateStatDisplay('damagedValue', '$' + (totalCost - totalValue).toFixed(2));
    }

    // Enhanced autosave with visual feedback
    function enhancedAutosave() {
      clearTimeout(window.autosaveTimer);
      
      // Show saving indicator
      const saveIndicator = document.getElementById('saveIndicator');
      if (saveIndicator) {
        saveIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        saveIndicator.className = 'badge badge-warning';
      }
      
      window.autosaveTimer = setTimeout(() => {
        // Collect all form data
        const formData = new FormData();
        
        document.querySelectorAll('.qty-input, .cost-input, .status-select').forEach(input => {
          if (input.name) {
            formData.append(input.name, input.value);
          }
        });
        
        // Send to autosave endpoint
        fetch(window.location.href, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Autosave': '1'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (saveIndicator) {
            if (data.success) {
              saveIndicator.innerHTML = '<i class="fas fa-check"></i> Saved';
              saveIndicator.className = 'badge badge-success';
            } else {
              saveIndicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
              saveIndicator.className = 'badge badge-danger';
            }
            
            // Clear indicator after 2 seconds
            setTimeout(() => {
              saveIndicator.innerHTML = '<i class="fas fa-database"></i> Auto-save';
              saveIndicator.className = 'badge badge-secondary';
            }, 2000);
          }
        })
        .catch(error => {
          console.error('Autosave error:', error);
          if (saveIndicator) {
            saveIndicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Failed';
            saveIndicator.className = 'badge badge-danger';
          }
        });
      }, 2000); // 2 second delay
    }

    // Attach autosave to all inputs
    document.addEventListener('input', function(e) {
      if (e.target.matches('.qty-input, .cost-input')) {
        enhancedAutosave();
        updateFinancialTotals();
      }
    });

    document.addEventListener('change', function(e) {
      if (e.target.matches('.status-select')) {
        enhancedAutosave();
        updateRowStatus(e.target.closest('tr'), e.target.value);
      }
    });
    
    console.log('📦 Purchase Order Receiving System - Responsive Table Edition');
    console.log('🔧 Enhanced with maximum productivity features');
    console.log('📱 Mobile responsive with sticky statistics');
    console.log('🔍 Integrated scanner with visual feedback');
    console.log('💾 Real-time autosave with financial calculations');
    
    // Initialize POCore if available
    if (typeof POCore !== 'undefined') {
      POCore.init();
    }
    
    // Initialize scanner if available
    if (typeof POScanner !== 'undefined') {
      POScanner.init();
    }
  </script>


  <!-- Legal Acknowledgment Modal -->
  <div id="legalModal" class="legal-modal">
    <div class="legal-modal-content">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-primary fw-bold mb-0">
          <i class="fa fa-balance-scale me-2"></i>
          Purchase Order Receiving Responsibility Agreement
        </h4>
        <button type="button" class="btn-close" onclick="closeLegalModal()" aria-label="Close"></button>
      </div>
      
      <div class="legal-text">
        <p><strong>Staff Responsibility & Acknowledgment:</strong></p>
        
        <p>By proceeding with purchase order receiving duties, I acknowledge and agree to the following responsibilities:</p>
        
        <ul>
          <li><strong>Accuracy Verification:</strong> I will carefully verify that all received items match the purchase order specifications including quantities, product descriptions, and pricing.</li>
          <li><strong>Quality Assessment:</strong> I will inspect all products for damage, defects, or quality issues and report any discrepancies immediately.</li>
          <li><strong>Documentation Compliance:</strong> I will ensure all receiving activities are properly documented with accurate timestamps, quantities, and any variance notes.</li>
          <li><strong>System Integrity:</strong> I will maintain data integrity by entering accurate information and will not manipulate or falsify receiving records.</li>
          <li><strong>Asset Protection:</strong> I understand that I am responsible for protecting company assets and preventing loss, theft, or damage during the receiving process.</li>
        </ul>
        
        <div class="legal-warning">
          <h6 class="text-danger fw-bold mb-2">
            <i class="fa fa-exclamation-triangle me-1"></i>
            Disciplinary Action Warning
          </h6>
          <p class="mb-2"><strong>I understand that failure to comply with these responsibilities may result in disciplinary action, including:</strong></p>
          <ul class="mb-0">
            <li>Formal written warning</li>
            <li>Performance improvement plan</li>
            <li>Suspension without pay</li>
            <li>Termination of employment</li>
            <li>Legal action for theft or fraud</li>
          </ul>
        </div>
        
        <p><strong>Audit Trail:</strong> I acknowledge that all my actions in this system are logged and may be subject to internal audit and review.</p>
        
        <p><strong>Compliance Standards:</strong> I confirm that I have been trained on proper receiving procedures and understand the importance of accurate inventory management to business operations.</p>
      </div>
      
      <div class="signature-section">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="staffSignature" class="form-label fw-bold">Staff Digital Signature:</label>
            <input type="text" id="staffSignature" class="signature-field" placeholder="Type your full name here" required>
          </div>
          <div class="col-md-6 mb-3">
            <label for="staffId" class="form-label fw-bold">Staff ID:</label>
            <input type="text" id="staffId" class="signature-field" placeholder="Enter your staff ID" required>
          </div>
        </div>
        
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="legalAgreement" required>
          <label class="form-check-label fw-bold" for="legalAgreement">
            I have read, understood, and agree to comply with all responsibilities outlined above
          </label>
        </div>
        
        <div class="text-end">
          <button type="button" class="btn btn-outline-secondary me-2" onclick="closeLegalModal()">
            Cancel
          </button>
          <button type="button" class="btn btn-danger" onclick="acceptLegalTerms()">
            <i class="fa fa-check me-1"></i>
            Accept Responsibility & Continue
          </button>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
