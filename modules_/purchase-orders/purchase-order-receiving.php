<?php
/**
 * Purchase Order Simple Receiving System - CIS Template
 * Purpose: Receive individual line items for purchase orders
 * Features: What's Arrived, Partial, Submit, FIX, RESET, UNLOCK
 * URL: ?id=12345 or ?po_id=12345 or ?purchase_order_id=12345
 * 
 * Access Controls:
 * - FIX: Available for 48 hours post-completion for all users; permanent for admin users (ID 1, 42)
 * - UNLOCK: Enables FIX for expired orders; admins can unlock any time, shows only after 48h
 * - RESET: Admin only (ID 1, 42) - completely resets order to initial state
 */
// Standard CIS bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
// Optional fallback if DB constants/connection not present
if (!defined('DB_HOST') && !isset($con)) {
  $fallback1 = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
  $fallback2 = dirname(__FILE__, 3) . '/config.php';
  if (file_exists($fallback1)) {
    require_once $fallback1; // defines $con and DB_* in legacy setups
  } elseif (file_exists($fallback2)) {
    require_once $fallback2;
  }
}

// ====== FEATURE FLAG: INVENTORY ADJUSTMENT ======
// Set to TRUE to enable "IN STOCK" column with live inventory adjustment capability
// Set to FALSE for normal receiving behavior without inventory adjustment
$ENABLE_INVENTORY_ADJUSTMENT = true;

// ====== DEBUG MODE FLAG ======
// Set to TRUE to skip all live Vend API updates (debug mode)
// Set to FALSE for normal operation with live Vend API updates
$DEBUG_MODE = true;

// ====== DRY RUN MODE FLAG ======
// Set to TRUE to skip ALL database updates and Vend API calls (testing only)
// Set to FALSE for normal operation
$DRY_RUN_MODE = true;
// ===============================================

// Get PO ID from URL parameters
$po_id = (int)($_GET['id'] ?? $_GET['po_id'] ?? $_GET['purchase_order_id'] ?? 0);

if (!$po_id) {
    die("Error: Purchase Order ID required. Please provide ?id=XXXXX in the URL.");
}

//######### AJAX BEGINS HERE #########

// Handle AJAX requests for saving data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        // Check if order is completed before allowing any modifications (except unlock actions)
        if ($action !== 'unlock_order') {
            $status_check = mysqli_query($con, "SELECT status FROM purchase_orders WHERE purchase_order_id = $po_id");
            $order_status = mysqli_fetch_assoc($status_check);
            
            if ($order_status && $order_status['status'] == 1) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Cannot modify completed order. This purchase order has already been finalized.',
                    'readonly' => true
                ]);
                exit;
            }
        }
        
        if ($action === 'save_progress') {
            // Save individual product progress with correct field names AND update Vend live
            $product_id = $input['product_id'] ?? '';
            $qty_received = (int)($input['qty_received'] ?? 0);
            
            // Check for stock adjustment feature
            $adjust_stock = isset($input['adjust_stock']) && $input['adjust_stock'] === true;
            $new_stock_level = isset($input['new_stock_level']) ? (int)$input['new_stock_level'] : null;
            
            // Validate inputs
            if (empty($product_id)) {
                echo json_encode(['success' => false, 'message' => 'Product ID required']);
                exit;
            }
            
            // Get current qty_arrived to calculate difference for Vend update
            $current_qty_sql = "SELECT qty_arrived, po.outlet_id 
                               FROM purchase_order_line_items poli
                               JOIN purchase_orders po ON po.purchase_order_id = poli.purchase_order_id
                               WHERE poli.purchase_order_id = ? AND poli.product_id = ?";
            $current_stmt = mysqli_prepare($con, $current_qty_sql);
            mysqli_stmt_bind_param($current_stmt, "is", $po_id, $product_id);
            mysqli_stmt_execute($current_stmt);
            $current_result = mysqli_stmt_get_result($current_stmt);
            $current_data = mysqli_fetch_assoc($current_result);
            
            if (!$current_data) {
                echo json_encode(['success' => false, 'message' => 'Product not found in this purchase order']);
                exit;
            }
            
            $previous_qty = (int)$current_data['qty_arrived'];
            $outlet_id = $current_data['outlet_id'];
            $qty_difference = $qty_received - $previous_qty;
            
            // Update the database first
            $update_sql = "UPDATE purchase_order_line_items 
                          SET qty_arrived = ?, 
                              received_at = NOW(),
                              discrepancy_type = 'OK'
                          WHERE purchase_order_id = ? AND product_id = ?";
            
            if ($DRY_RUN_MODE) {
                // DRY RUN: Log what would happen but don't execute
                error_log("DRY RUN: Would execute: $update_sql with params: qty=$qty_received, po_id=$po_id, product_id=$product_id");
                $db_success = true; // Simulate success
            } else {
                $stmt = mysqli_prepare($con, $update_sql);
                mysqli_stmt_bind_param($stmt, "iis", $qty_received, $po_id, $product_id);
                $db_success = mysqli_stmt_execute($stmt);
            }
            
            if ($db_success) {
                $vend_success = true;
                $vend_message = '';
                $stock_message = '';
                
                // Update Vend inventory live if there's a quantity change
                if ($qty_difference != 0) {
                    if ($DRY_RUN_MODE) {
                        error_log("DRY RUN: Would call updateVendInventoryLevel($product_id, $outlet_id, $qty_difference)");
                        $vend_success = true; // Simulate success
                        $vend_message = "DRY RUN: Would update Vend inventory (+{$qty_difference})";
                    } else {
                        $vend_success = updateVendInventoryLevel($product_id, $outlet_id, $qty_difference);
                        if ($DEBUG_MODE) {
                            $vend_message = "DEBUG MODE: CIS updated (+{$qty_difference}), Vend API skipped";
                        } else {
                            $vend_message = $vend_success ? 
                                "Vend inventory updated (+{$qty_difference})" : 
                                "Database saved but Vend update failed";
                        }
                    }
                }
                
                // Handle stock level adjustment if enabled
                if ($ENABLE_INVENTORY_ADJUSTMENT && $adjust_stock && $new_stock_level !== null) {
                    // Get current inventory level from Vend
                    $current_stock_sql = "SELECT inventory_level FROM vend_inventory 
                                         WHERE product_id = ? AND outlet_id = ?";
                    $stock_stmt = mysqli_prepare($con, $current_stock_sql);
                    mysqli_stmt_bind_param($stock_stmt, "ss", $product_id, $outlet_id);
                    mysqli_stmt_execute($stock_stmt);
                    $stock_result = mysqli_stmt_get_result($stock_stmt);
                    $stock_data = mysqli_fetch_assoc($stock_result);
                    
                    if ($stock_data) {
                        $current_stock = (int)$stock_data['inventory_level'];
                        $stock_adjustment = $new_stock_level - $current_stock;
                        
                        if ($stock_adjustment != 0) {
                            // Update via Vend API for live inventory adjustment
                            $stock_success = updateVendInventoryLevel($product_id, $outlet_id, $stock_adjustment, true);
                            if ($DEBUG_MODE) {
                                $stock_message = " + DEBUG MODE: Stock adjusted to {$new_stock_level} (CIS only)";
                            } else {
                                if ($stock_success) {
                                    $stock_message = " + Stock adjusted to {$new_stock_level} (change: {$stock_adjustment})";
                                } else {
                                    $stock_message = " + Stock adjustment failed";
                                }
                            }
                        } else {
                            $stock_message = " + Stock level unchanged";
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Quick Save completed!' . ($vend_message ? ' ' . $vend_message : '') . $stock_message,
                    'vend_updated' => $vend_success,
                    'qty_change' => $qty_difference,
                    'stock_adjusted' => $adjust_stock
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
            }
            exit;
            
        } elseif ($action === 'undo_item') {
            // Undo/reset individual product back to unprocessed state AND reverse Vend inventory
            $product_id = $input['product_id'] ?? '';
            
            // Validate inputs
            if (empty($product_id)) {
                echo json_encode(['success' => false, 'message' => 'Product ID required']);
                exit;
            }
            
            // Get current qty_arrived and outlet_id to reverse Vend inventory
            $current_qty_sql = "SELECT qty_arrived, po.outlet_id 
                               FROM purchase_order_line_items poli
                               JOIN purchase_orders po ON po.purchase_order_id = poli.purchase_order_id
                               WHERE poli.purchase_order_id = ? AND poli.product_id = ?";
            $current_stmt = mysqli_prepare($con, $current_qty_sql);
            mysqli_stmt_bind_param($current_stmt, "is", $po_id, $product_id);
            mysqli_stmt_execute($current_stmt);
            $current_result = mysqli_stmt_get_result($current_stmt);
            $current_data = mysqli_fetch_assoc($current_result);
            
            if (!$current_data) {
                echo json_encode(['success' => false, 'message' => 'Product not found in this purchase order']);
                exit;
            }
            
            $current_qty = (int)$current_data['qty_arrived'];
            $outlet_id = $current_data['outlet_id'];
            
            // Reset the item - set qty_arrived to 0 and clear timestamps
            $undo_sql = "UPDATE purchase_order_line_items 
                        SET qty_arrived = 0, 
                            received_at = NULL,
                            discrepancy_type = 'OK'
                        WHERE purchase_order_id = ? AND product_id = ?";
            $stmt = mysqli_prepare($con, $undo_sql);
            mysqli_stmt_bind_param($stmt, "is", $po_id, $product_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $vend_success = true;
                $vend_message = '';
                $new_stock_level = null;
                
                // Reverse Vend inventory if there was a quantity to undo
                if ($current_qty > 0) {
                    $vend_success = updateVendInventoryLevel($product_id, $outlet_id, -$current_qty);
                    $vend_message = $vend_success ? 
                        " Vend inventory reversed (-{$current_qty})" : 
                        " Database reset but Vend reversal failed";
                }
                
                // Get updated inventory level after Vend update
                if ($vend_success) {
                    $stock_check_sql = "SELECT inventory_level FROM vend_inventory 
                                       WHERE product_id = ? AND outlet_id = ?";
                    $stock_stmt = mysqli_prepare($con, $stock_check_sql);
                    mysqli_stmt_bind_param($stock_stmt, "ss", $product_id, $outlet_id);
                    mysqli_stmt_execute($stock_stmt);
                    $stock_result = mysqli_stmt_get_result($stock_stmt);
                    $stock_data = mysqli_fetch_assoc($stock_result);
                    $new_stock_level = $stock_data ? (int)$stock_data['inventory_level'] : 0;
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Item reset successfully - you can now edit the quantity' . $vend_message,
                    'reset' => true,
                    'vend_updated' => $vend_success,
                    'qty_reversed' => $current_qty,
                    'new_stock_level' => $new_stock_level
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
            }
            exit;
            
        } elseif ($action === 'submit_partial') {
            // Mark as partial delivery
            $partial_sql = "UPDATE purchase_orders 
                           SET partial_delivery = 1, 
                               partial_delivery_time = NOW(),
                               partial_delivery_by = ?
                           WHERE purchase_order_id = ?";
            $stmt = mysqli_prepare($con, $partial_sql);
            $user_id = 18; // Default user ID - should be from session in production
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $po_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Queue Vend inventory updates for received items
                queueVendInventoryUpdates($po_id, $con);
                
                echo json_encode(['success' => true, 'message' => 'Partial delivery saved and queued for Vend sync']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
            }
            exit;
            
        } elseif ($action === 'submit_final') {
            // Complete the entire order
            $complete_sql = "UPDATE purchase_orders 
                            SET status = 1, 
                                completed_timestamp = NOW(),
                                completed_by = ?
                            WHERE purchase_order_id = ?";
            $stmt = mysqli_prepare($con, $complete_sql);
            $user_id = 18; // Default user ID - should be from session in production
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $po_id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Queue Vend inventory updates for all received items
                $updatesQueued = queueVendInventoryUpdates($po_id, $con);
                
                // Process high-priority queue items immediately
                if (function_exists('processVendQueueInstant')) {
                    processVendQueueInstant();
                }
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Order completed successfully!',
                    'vend_updates' => $updatesQueued,
                    'redirect' => "view_completed_order.php?id={$po_id}"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($con)]);
            }
            exit;
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
        }
        
        if ($action === 'update_live_stock') {
            // Handle live stock adjustment
            $product_id = $_POST['product_id'] ?? '';
            $new_stock = (int)($_POST['new_stock'] ?? 0);
            
            if (empty($product_id)) {
                echo json_encode(['success' => false, 'message' => 'Product ID required']);
                exit;
            }
            
            // Get outlet ID for this purchase order
            $outlet_sql = "SELECT outlet_id FROM purchase_orders WHERE purchase_order_id = ?";
            $outlet_stmt = mysqli_prepare($con, $outlet_sql);
            mysqli_stmt_bind_param($outlet_stmt, "i", $po_id);
            mysqli_stmt_execute($outlet_stmt);
            $outlet_result = mysqli_stmt_get_result($outlet_stmt);
            $outlet_data = mysqli_fetch_assoc($outlet_result);
            
            if (!$outlet_data) {
                echo json_encode(['success' => false, 'message' => 'Could not find purchase order']);
                exit;
            }
            
            $outlet_id = $outlet_data['outlet_id'];
            
            // Update the inventory level using our existing function
            $update_success = updateVendInventoryLevel($product_id, $outlet_id, $new_stock);
            
            if ($update_success) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Live stock updated to {$new_stock}",
                    'new_stock' => $new_stock
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Failed to update live stock. Please try again.'
                ]);
            }
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * Queue Vend inventory updates for all received items
 */
function queueVendInventoryUpdates($po_id, $con) {
    $updates = [];
    
    // Get all received items for this PO
    $line_sql = "SELECT poli.*, po.outlet_id 
                 FROM purchase_order_line_items poli
                 JOIN purchase_orders po ON po.purchase_order_id = poli.purchase_order_id
                 WHERE poli.purchase_order_id = ? AND poli.qty_arrived > 0";
    
    $line_stmt = mysqli_prepare($con, $line_sql);
    mysqli_stmt_bind_param($line_stmt, "i", $po_id);
    mysqli_stmt_execute($line_stmt);
    $line_result = mysqli_stmt_get_result($line_stmt);
    
    while ($line = mysqli_fetch_assoc($line_result)) {
        // Use the correct VEND_UpdateProductQty function with proper Lightspeed format
        if (function_exists('VEND_UpdateProductQty')) {
            // Get current inventory level
            $current_stock_sql = "SELECT inventory_level FROM vend_inventory 
                                 WHERE product_id = ? AND outlet_id = ?";
            $stock_stmt = mysqli_prepare($con, $current_stock_sql);
            mysqli_stmt_bind_param($stock_stmt, "ss", $line['product_id'], $line['outlet_id']);
            mysqli_stmt_execute($stock_stmt);
            $stock_result = mysqli_stmt_get_result($stock_stmt);
            $stock_data = mysqli_fetch_assoc($stock_result);
            
            $current_stock = $stock_data ? (int)$stock_data['inventory_level'] : 0;
            $new_stock = $current_stock + $line['qty_arrived'];
            
            // Queue the update using correct Lightspeed API format
            $queue_id = VEND_UpdateProductQty(
                $line['product_id'], 
                $line['outlet_id'], 
                $new_stock, 
                'purchase_order_submit', 
                'inventory_adjustment'
            );
            
            if ($queue_id) {
                $updates[] = "Product {$line['product_id']}: +{$line['qty_arrived']} units queued (ID: {$queue_id})";
            } else {
                $updates[] = "Product {$line['product_id']}: FAILED to queue update";
            }
        } else {
            // Fallback: Direct inventory update in CIS only
            $vend_sql = "UPDATE vend_inventory 
                        SET inventory_level = inventory_level + ?
                        WHERE product_id = ? AND outlet_id = ?";
            $vend_stmt = mysqli_prepare($con, $vend_sql);
            mysqli_stmt_bind_param($vend_stmt, "iss", $line['qty_arrived'], $line['product_id'], $line['outlet_id']);
            mysqli_stmt_execute($vend_stmt);
            
            $updates[] = "Product {$line['product_id']}: +{$line['qty_arrived']} units updated directly (CIS only)";
        }
    }
    
    return $updates;
}

/**
 * Update Vend inventory level for a product at a specific outlet
 * @param string $product_id - Vend product ID
 * @param string $outlet_id - Outlet ID
 * @param int $adjustment - Quantity adjustment (positive or negative)
 * @param bool $is_stock_adjustment - Whether this is a stock level adjustment vs receiving
 * @return bool - Success status
 */
function updateVendInventoryLevel($product_id, $outlet_id, $adjustment, $is_stock_adjustment = false) {
    global $con, $DEBUG_MODE;
    
    // In debug mode, only update CIS database, skip Vend API
    if ($DEBUG_MODE) {
        error_log("DEBUG MODE: Skipping Vend API update for product {$product_id}, adjustment: {$adjustment}");
        
        // Update only local CIS vend_inventory table
        $update_sql = $is_stock_adjustment ? 
            "UPDATE vend_inventory SET inventory_level = ? WHERE product_id = ? AND outlet_id = ?" :
            "UPDATE vend_inventory SET inventory_level = inventory_level + ? WHERE product_id = ? AND outlet_id = ?";
        
        $stmt = mysqli_prepare($con, $update_sql);
        if ($is_stock_adjustment) {
            mysqli_stmt_bind_param($stmt, "iss", $adjustment, $product_id, $outlet_id);
        } else {
            mysqli_stmt_bind_param($stmt, "iss", $adjustment, $product_id, $outlet_id);
        }
        
        $result = mysqli_stmt_execute($stmt);
        error_log("DEBUG MODE: CIS inventory update " . ($result ? "successful" : "failed"));
        return $result;
    }
    
    // Normal mode: Update both CIS and Vend API
    try {
        // First update local CIS database
        $update_sql = $is_stock_adjustment ? 
            "UPDATE vend_inventory SET inventory_level = ? WHERE product_id = ? AND outlet_id = ?" :
            "UPDATE vend_inventory SET inventory_level = inventory_level + ? WHERE product_id = ? AND outlet_id = ?";
        
        $stmt = mysqli_prepare($con, $update_sql);
        if ($is_stock_adjustment) {
            mysqli_stmt_bind_param($stmt, "iss", $adjustment, $product_id, $outlet_id);
        } else {
            mysqli_stmt_bind_param($stmt, "iss", $adjustment, $product_id, $outlet_id);
        }
        
        $cis_success = mysqli_stmt_execute($stmt);
        
        // Then update via Vend API using correct Lightspeed format
        if (function_exists('VEND_UpdateProductQty')) {
            // Calculate the new quantity level
            if ($is_stock_adjustment) {
                // For stock adjustments, set to exact amount
                $new_qty = $adjustment;
            } else {
                // For receiving adjustments, get current stock and add adjustment
                $current_stock_sql = "SELECT inventory_level FROM vend_inventory 
                                     WHERE product_id = ? AND outlet_id = ?";
                $stock_stmt = mysqli_prepare($con, $current_stock_sql);
                mysqli_stmt_bind_param($stock_stmt, "ss", $product_id, $outlet_id);
                mysqli_stmt_execute($stock_stmt);
                $stock_result = mysqli_stmt_get_result($stock_stmt);
                $stock_data = mysqli_fetch_assoc($stock_result);
                
                $current_stock = $stock_data ? (int)$stock_data['inventory_level'] : 0;
                $new_qty = $current_stock + $adjustment;
            }
            
            // Use the fixed VEND_UpdateProductQty function with correct Lightspeed format
            $queue_id = VEND_UpdateProductQty($product_id, $outlet_id, $new_qty, 'purchase_order_receiving', 'quick_save');
            $vend_success = ($queue_id !== false);
            
            if ($vend_success) {
                error_log("SUCCESS: Quick Save queued Vend update for product {$product_id}, queue ID: {$queue_id}");
            } else {
                error_log("ERROR: Quick Save failed to queue Vend update for product {$product_id}");
            }
        } else {
            $vend_success = false;
            error_log("WARNING: VEND_UpdateProductQty function not available");
        }
        
        return $cis_success; // Return true if at least CIS update succeeded
        
    } catch (Exception $e) {
        error_log("Error in updateVendInventoryLevel: " . $e->getMessage());
        return false;
    }
}

// Get purchase order data with proper supplier lookup
$order_sql = "SELECT po.*, 
                     COALESCE(vs.name, po.supplier_name_cache, 'Unknown Supplier') as supplier_name,
                     vo.name as outlet_name,
                     u.first_name,
                     u.last_name,
                     CONCAT(u.first_name, ' ', u.last_name) as completed_by_name,
                     vs.contact_name as supplier_contact,
                     vs.email as supplier_email,
                     vs.phone as supplier_phone
              FROM purchase_orders po
              LEFT JOIN vend_outlets vo ON vo.id = po.outlet_id
              LEFT JOIN users u ON u.id = po.completed_by
              LEFT JOIN vend_suppliers vs ON vs.id = po.supplier_id
              WHERE po.purchase_order_id = ?";

$stmt = mysqli_prepare($con, $order_sql);
mysqli_stmt_bind_param($stmt, "i", $po_id);
mysqli_stmt_execute($stmt);
$order_result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_object($order_result);

if (!$order) {
    die("Error: Purchase Order #{$po_id} not found in database.");
}

// Check if order is completed (read-only mode)
$is_readonly = ($order->status == 1);

// Get current user for reset functionality
$current_user_id = $_SESSION['userID'] ?? 0;
// Only allow reset for admin users (1, 42) AND only when order is completed
$can_reset = $is_readonly && in_array($current_user_id, [1, 42]);

// Calculate unlock permissions for completed orders
$can_unlock = false;
$show_unlock_button = false;
$unlock_reason = '';
$hours_since_completion = 0;

if ($is_readonly && $order->completed_timestamp) {
    $completed_time = strtotime($order->completed_timestamp);
    $hours_since_completion = (time() - $completed_time) / 3600;
    
    if (in_array($current_user_id, [1, 42])) {
        // Admin users - permanent access, show button after 48 hours (since it's useful then)
        $can_unlock = true;
        $show_unlock_button = ($hours_since_completion > 48);
        $unlock_reason = 'Administrator access - permanent unlock privileges';
    } elseif ($hours_since_completion <= 48) {
        // Regular users - 48 hour window, hide button for first 48 hours (achieves nothing)
        $can_unlock = true;
        $show_unlock_button = false; // Hidden since FIX is already available
        $remaining_hours = round(48 - $hours_since_completion, 1);
        $unlock_reason = "48-hour edit window active - {$remaining_hours} hours remaining";
    } else {
        // Regular users - window expired
        $can_unlock = false;
        $show_unlock_button = true; // Show button but it will be disabled
        $unlock_reason = 'Edit window expired. Only administrators can unlock orders older than 48 hours.';
    }
}

// Determine if FIX buttons should be available
$fix_available = false;
if ($is_readonly) {
    if (in_array($current_user_id, [1, 42])) {
        // Admin users - always available
        $fix_available = true;
    } elseif ($hours_since_completion <= 48) {
        // Regular users - 48 hour window
        $fix_available = true;
    }
    // If unlocked via UNLOCK button, this will be handled by JavaScript
}

// Get purchase order line items
$items_sql = "SELECT poli.*, 
                     vp.name as product_name,
                     vp.sku,
                     vp.handle as vend_id,
                     vp.image_url,
                     vi.inventory_level as current_stock
              FROM purchase_order_line_items poli
              LEFT JOIN vend_products vp ON vp.id = poli.product_id
              LEFT JOIN vend_inventory vi ON vi.product_id = poli.product_id AND vi.outlet_id = ?
              WHERE poli.purchase_order_id = ?
              ORDER BY COALESCE(vp.name, 'Unknown Product')";

$items_stmt = mysqli_prepare($con, $items_sql);
mysqli_stmt_bind_param($items_stmt, "si", $order->outlet_id, $po_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);

//######### AJAX ENDS HERE #########

//######### HEADER BEGINS HERE ######### -->

include("assets/template/html-header.php");
include("assets/template/header.php");

//######### HEADER ENDS HERE #########
?>

<!-- Purchase Order Receiving Styles -->
<link rel="stylesheet" href="assets/css/purchase-order-receiving_1.css">

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("assets/template/sidemenu.php"); ?>
    <main class="main">
      <!-- Breadcrumb -->
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">
          <a href="incoming-shipment-view.php">Incoming Goods</a>
        </li>
        <li class="breadcrumb-item active">Purchase Order Receiving</li>
        <!-- Breadcrumb Menu-->
        <li class="breadcrumb-menu d-md-down-none">
          <?php include('assets/template/quick-product-search.php'); ?>
        </li>
      </ol>
      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="row">
            <div class="col mainbody">
              <div class="card">
                <div class="card-header <?php echo $is_readonly ? 'readonly-mode' : ''; ?>">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h4 class="card-title mb-1">
                        Purchase Order Receiving #<?php echo htmlspecialchars($order->po_number ?? 'PO-' . str_pad($order->purchase_order_id, 6, '0', STR_PAD_LEFT)); ?>
                        <?php if ($is_readonly): ?>
                          <span class="badge badge-success ml-2">
                            <i class="fa fa-check-circle"></i> COMPLETED
                          </span>
                        <?php endif; ?>
                      </h4>
                      <div class="small text-muted">
                        <strong>Supplier:</strong> <?php echo htmlspecialchars($order->supplier_name ?? $order->supplier_name_cache ?? 'N/A'); ?> | 
                        <strong>Outlet:</strong> <?php echo htmlspecialchars($order->outlet_name ?? 'N/A'); ?>
                        <?php if ($is_readonly): ?>
                          | <strong>Completed:</strong> <?php echo date('d/m/Y H:i', strtotime($order->completed_timestamp)); ?>
                        <?php endif; ?>
                      </div>
                    </div>
                    
                    <?php if ($can_reset || $show_unlock_button): ?>
                      <div class="admin-buttons-simple">
                        <?php if ($show_unlock_button): ?>
                          <button type="button" class="btn btn-outline-danger btn-sm" id="unlock_order" 
                                  title="<?php echo htmlspecialchars($unlock_reason); ?>"
                                  <?php echo !$can_unlock ? 'disabled' : ''; ?>>
                            <i class="fa fa-unlock"></i> ADMIN UNLOCK
                          </button>
                        <?php endif; ?>
                        
                        <?php if ($can_reset): ?>
                          <button type="button" class="btn btn-outline-danger btn-sm ml-2" id="reset_order" 
                                  title="ADMIN: Unlock purchase order for editing (keeps all values)">
                            <i class="fa fa-unlock"></i> ADMIN UNLOCK
                          </button>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  
                  <?php if ($is_readonly): ?>
                    <div class="alert alert-info readonly-banner mt-2 mb-0">
                      <i class="fa fa-lock"></i> This purchase order has been completed and is now read-only.
                      No further modifications can be made.
                    </div>
                  <?php endif; ?>
                  
                  <?php if ($DEBUG_MODE): ?>
                    <div class="alert alert-warning debug-banner mt-2 mb-0">
                      <i class="fa fa-bug"></i> <strong>DEBUG MODE ACTIVE</strong> - CIS database will be updated, but Vend API calls are disabled.
                    </div>
                  <?php endif; ?>
                </div>
                <div class="card-body">
                  <!-- Scanner Section -->
                  <?php if (!$is_readonly): ?>
                    <div class="row mb-4" id="scanner-section">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="barcode_input">
                            <i class="fa fa-barcode"></i> Scan Barcode or Enter Product Code
                          </label>
                          <div class="input-group">
        <input type="text" 
          id="barcode_input" 
          class="form-control form-control-lg" 
          placeholder="Scan barcode here..." 
                                   autocomplete="off"
                                   autofocus>
                            <div class="input-group-append">
                              <button class="btn btn-primary" type="button" id="manual_search">
                                <i class="fa fa-search"></i> Search
                              </button>
                            </div>
                          </div>
                          <small class="form-text text-muted">
                            Scan product barcode or manually search for products to receive
                          </small>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="receiving-stats">
                          <h6>Receiving Progress</h6>
                          <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar" role="progressbar" id="progress_bar">
                              <span id="progress_text">0% Complete</span>
                            </div>
                          </div>
                          <small class="text-muted">
                            <span id="items_received">0</span> of <span id="total_items"><?php echo mysqli_num_rows($items_result); mysqli_data_seek($items_result, 0); ?></span> items received
                          </small>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- Purchase Order Items Table -->
                  <div class="table-responsive">
                    <table class="table table-striped table-sm" id="receiving_table">
                      <thead class="thead-dark">
                        <tr>
                          <th style="width: 60px;">Image</th>
                          <th>Product</th>
                          <th>Expected</th>
                          <?php if ($ENABLE_INVENTORY_ADJUSTMENT): ?>
                            <th class="text-center">
                              In Stock 
                              <i class="fa fa-question-circle text-muted ml-1" 
                                 data-toggle="tooltip" 
                                 data-placement="top"
                                 title="Live Mode allows instant stock adjustments to Vend during receiving. Useful for: • Correcting messy stock from previous issues • Performing stock takes during delivery • Handling damaged/missing items in real-time • Ensuring accurate inventory before finalizing orders. Check 'Live' then edit the number to update Vend immediately."
                                 style="font-size: 12px; cursor: help;"></i>
                            </th>
                          <?php endif; ?>
                          <th>Received</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php 
                        $total_expected = 0; 
                        $total_received = 0;
                        while ($item = mysqli_fetch_object($items_result)): 
                          $total_expected += $item->order_qty;
                          $total_received += $item->qty_arrived;
                          
                          $status_class = '';
                          $status_text = 'Pending';
                          
                          if ($item->qty_arrived > 0) {
                            if ($item->qty_arrived >= $item->order_qty) {
                              $status_class = 'badge-success';
                              $status_text = 'Complete';
                            } else {
                              $status_class = 'badge-warning';
                              $status_text = 'Partial';
                            }
                          } else {
                            $status_class = 'badge-secondary';
                            $status_text = 'Pending';
                          }
                        ?>
                        <tr data-product-id="<?php echo $item->product_id; ?>" data-expected="<?php echo $item->order_qty; ?>"<?php echo ($item->qty_arrived > 0) ? ' class="completed-row"' : ''; ?>>
                          <td class="text-center" style="width: 60px;">
                            <?php if (!empty($item->image_url)): ?>
                              <?php 
                              // Check if it's Vend's default placeholder image
                              $is_default_image = strpos($item->image_url, 'no-image-white-standard.png') !== false || 
                                                 strpos($item->image_url, 'placeholder/product') !== false ||
                                                 strpos($item->image_url, 'default') !== false ||
                                                 strpos($item->image_url, 'no-image') !== false;
                              ?>
                              <?php if (!$is_default_image): ?>
                                <img src="<?php echo htmlspecialchars($item->image_url); ?>" 
                                     alt="Product Image" 
                                     class="product-thumbnail" 
                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; cursor: pointer;"
                                     onmouseover="showImageHover(this, '<?php echo htmlspecialchars($item->image_url); ?>')"
                                     onmouseout="hideImageHover()">
                              <?php else: ?>
                                <!-- Empty space for default images - no icon -->
                                <div style="width: 40px; height: 40px;"></div>
                              <?php endif; ?>
                            <?php else: ?>
                              <!-- Empty space when no image URL - no icon -->
                              <div style="width: 40px; height: 40px;"></div>
                            <?php endif; ?>
                          </td>
                          <td>
                            <strong><?php echo htmlspecialchars($item->product_name); ?></strong>
                            <br>
                            <small class="text-muted">
                              SKU: <?php echo htmlspecialchars($item->sku ?: $item->vend_id ?: 'N/A'); ?>
                              <span class="sku-icons">
                                <a href="https://vapeshed.retail.lightspeed.app/product/<?php echo urlencode($item->product_id); ?>/update" target="_blank" class="sku-icon-btn sku-icon-btn-sm" title="Lightspeed Product" data-toggle="tooltip">
                                  <i class="fa fa-link"></i>
                                </a>
                                <a href="https://www.vapeshed.co.nz/products/<?php echo urlencode($item->vend_id); ?>" target="_blank" class="sku-icon-btn sku-icon-btn-sm" title="VapeShed Website" data-toggle="tooltip">
                                  <i class="fa fa-globe"></i>
                                </a>
                                <?php if (!empty($item->image_url)): ?>
                                  <button type="button" class="sku-icon-btn sku-icon-btn-sm image-btn" title="View Larger Image" data-toggle="modal" data-target="#imageModal_<?php echo $item->product_id; ?>">
                                    <i class="fa fa-image"></i>
                                  </button>
                                  <!-- Modal for larger image -->
                                  <div class="modal fade" id="imageModal_<?php echo $item->product_id; ?>" tabindex="-1" role="dialog" aria-labelledby="imageModalLabel_<?php echo $item->product_id; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                      <div class="modal-content">
                                        <div class="modal-header">
                                          <h5 class="modal-title" id="imageModalLabel_<?php echo $item->product_id; ?>">Product Image</h5>
                                          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                          </button>
                                        </div>
                                        <div class="modal-body text-center">
                                          <img src="<?php echo htmlspecialchars($item->image_url); ?>" alt="Product Image" class="img-fluid rounded shadow">
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                <?php endif; ?>
                              </span>
                              <?php if ($item->qty_arrived > 0): ?>
                                | <span class="text-success"><i class="fa fa-check-circle"></i> Quick Saved</span>
                              <?php endif; ?>
                            </small>
                          </td>
                          <td>
                            <span class="badge badge-info"><?php echo number_format($item->order_qty); ?></span>
                          </td>
                          <?php if ($ENABLE_INVENTORY_ADJUSTMENT): ?>
                          <td class="text-center">
                            <?php if ($is_readonly): ?>
                              <span class="badge badge-warning readonly-stock"><?php echo number_format($item->current_stock ?: 0); ?></span>
                            <?php else: ?>
                              <div class="d-flex flex-column align-items-center">
            <input type="number" class="form-control form-control-sm stock-input text-center-input mb-1" 
              value="<?php echo $item->current_stock ?: 0; ?>" 
              min="0" 
              data-product-id="<?php echo $item->product_id; ?>" 
              data-original-stock="<?php echo $item->current_stock ?: 0; ?>"
              style="width: 70px;" 
              title="Click to edit stock level"
              disabled>
                                <div class="live-checkbox-container">
                                  <label class="mb-0 d-flex align-items-center" style="font-size: 11px;">
                                    <input type="checkbox" class="use-live-checkbox mr-1" data-product-id="<?php echo $item->product_id; ?>" title="Enable Live Stock Updates" checked>
                                    <span class="text-muted">Live</span>
                                  </label>
                                </div>
                              </div>
                            <?php endif; ?>
                          </td>
                          <?php endif; ?>
                          <td>
                            <?php if ($is_readonly): ?>
                              <span class="badge badge-primary"><?php echo number_format($item->qty_arrived); ?></span>
                            <?php else: ?>
                              <input type="number" class="form-control form-control-sm qty-input text-center-input" value="<?php echo $item->qty_arrived; ?>" min="0" max="<?php echo $item->order_qty * 2; ?>" data-product-id="<?php echo $item->product_id; ?>" style="width: 80px;" <?php echo ($item->qty_arrived > 0) ? 'disabled' : ''; ?>>
                            <?php endif; ?>
                          </td>
                          <td>
                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                          </td>
                          <td>
                            <?php if ($is_readonly): ?>
                              <?php if ($item->qty_arrived > 0 && $fix_available): ?>
                                <button class="btn btn-sm btn-outline-warning fix-item" data-product-id="<?php echo $item->product_id; ?>" title="Fix/Edit this completed item">
                                  <i class="fa fa-wrench"></i> FIX
                                </button>
                              <?php elseif ($item->qty_arrived > 0 && !$fix_available): ?>
                                <button class="btn btn-sm btn-outline-secondary fix-item" data-product-id="<?php echo $item->product_id; ?>" title="Fix window expired - use UNLOCK ORDER to enable editing" disabled>
                                  <i class="fa fa-lock"></i> LOCKED
                                </button>
                              <?php else: ?>
                                <span class="text-muted">-</span>
                              <?php endif; ?>
                            <?php else: ?>
                              <?php if ($item->qty_arrived > 0): ?>
                                <button class="btn btn-sm btn-warning undo-item" data-product-id="<?php echo $item->product_id; ?>" title="Undo - Reset this item to unprocessed">
                                  <i class="fa fa-undo"></i> UNDO
                                </button>
                              <?php else: ?>
                                <button class="btn btn-sm btn-success save-item" data-product-id="<?php echo $item->product_id; ?>" title="Quick Save - Saves to database and updates Vend inventory live">
                                  <i class="fa fa-check"></i> Quick Save
                                </button>
                              <?php endif; ?>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <?php endwhile; ?>
                      </tbody>
                      <tfoot class="thead-light">
                        <tr>
                          <th colspan="2" style="color: #fff;">TOTALS</th>
                          <th><span style="color: #fff; font-weight: 600; font-size: 14px;"><?php echo number_format($total_expected); ?></span></th>
                          <?php if ($ENABLE_INVENTORY_ADJUSTMENT): ?>
                            <th class="text-center">
                              <?php
                              // Calculate total current stock
                              $total_current_stock = 0;
                              mysqli_data_seek($items_result, 0); // Reset result pointer
                              while ($item = mysqli_fetch_object($items_result)) {
                                $total_current_stock += $item->current_stock ?: 0;
                              }
                              mysqli_data_seek($items_result, 0); // Reset again for use elsewhere
                              ?>
                              <span style="color: #fff; font-weight: 600; font-size: 14px;"><?php echo number_format($total_current_stock); ?></span>
                            </th>
                          <?php endif; ?>
                          <th class="text-center"><span style="color: #fff; font-weight: 600; font-size: 14px;" id="total_received_display"><?php echo number_format($total_received); ?></span></th>
                          <th colspan="2">
                            <?php if ($is_readonly && $order->status == 1): ?>
                              <span class="badge badge-success" style="color: #fff;">Completed</span>
                            <?php else: ?>
                              <span class="badge <?php echo $total_received >= $total_expected ? 'badge-success' : 'badge-warning'; ?>" style="color: #fff;">
                                <?php echo $total_received >= $total_expected ? 'Complete' : 'Partial'; ?>
                              </span>
                            <?php endif; ?>
                          </th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>

                  <!-- Action Buttons -->
                  <?php if (!$is_readonly): ?>
                    <div class="row mt-4" id="action-bar">
                      <div class="col-md-12 text-center">
                        <button type="button" class="btn btn-primary btn-lg" id="smart_submit" 
                                title="Submit Purchase Order">
                          <i class="fa fa-save"></i> Submit Purchase Order
                        </button>
                        
                        <div class="mt-3">
                          <a href="/" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left"></i> Return To Main Dashboard
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="row mt-4">
                      <div class="col-md-12 text-center">
                        <div class="alert alert-success">
                          <h5><i class="fa fa-check-circle"></i> Order Completed Successfully</h5>
                          <p class="mb-0">This purchase order was completed on 
                            <strong><?php echo date('d/m/Y \a\t H:i', strtotime($order->completed_timestamp)); ?></strong>
                            <?php if ($order->completed_by): ?>
                              by <strong><?php echo htmlspecialchars($order->completed_by); ?></strong>
                            <?php endif; ?>
                          </p>
                        </div>
                        
                        <div class="mt-3">
                          <a href="incoming-shipment-view.php" class="btn btn-primary">
                            <i class="fa fa-arrow-left"></i> Back to Incoming Orders
                          </a>
                          <a href="purchase-order-receiving.php?id=<?php echo $po_id; ?>&format=pdf" class="btn btn-outline-info">
                            <i class="fa fa-file-pdf-o"></i> Download Receipt
                          </a>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <!-- Debug Info (Development Only) -->
                  <?php if (isset($_GET['debug'])): ?>
                    <div class="mt-4">
                      <h6>Debug Information</h6>
                      <pre class="bg-light p-3">
PO ID: <?php echo $po_id; ?>

Order Status: <?php echo $order->status; ?>

Read-Only Mode: <?php echo $is_readonly ? 'YES' : 'NO'; ?>

Current User ID: <?php echo $current_user_id; ?>

Is Admin User: <?php echo in_array($current_user_id, [1, 42]) ? 'YES (ID: ' . $current_user_id . ')' : 'NO'; ?>

Can Reset: <?php echo $can_reset ? 'YES' : 'NO'; ?>

Show Unlock Button: <?php echo $show_unlock_button ? 'YES' : 'NO'; ?>

Fix Available: <?php echo isset($fix_available) ? ($fix_available ? 'YES' : 'NO') : 'NOT SET'; ?>

Can Unlock: <?php echo $can_unlock ? 'YES' : 'NO'; ?>

Expected Items: <?php echo $total_expected; ?>

Received Items: <?php echo $total_received; ?>

Order Completion Status:
- Order Status: <?php echo $order->status; ?> (<?php echo $order->status == 1 ? 'COMPLETED' : 'PENDING'; ?>)
- Read-Only Mode: <?php echo $is_readonly ? 'YES' : 'NO'; ?>
- Completed Timestamp: <?php echo $order->completed_timestamp ?: 'NULL/EMPTY'; ?>

<?php if ($order->status == 1): ?>
<?php if ($order->completed_timestamp): ?>
Completed: <?php echo date('Y-m-d H:i:s', strtotime($order->completed_timestamp)); ?>

Hours Since Completion: <?php echo round($hours_since_completion, 1); ?> hours
<?php else: ?>
⚠️ WARNING: Order marked complete (status=1) but no completion timestamp!
Hours Since Completion: Cannot calculate (no timestamp)
<?php endif; ?>

Unlock Reason: <?php echo $unlock_reason ?: 'Not set'; ?>
<?php else: ?>
Order Status: PENDING (not completed)
<?php endif; ?>

Button Logic Summary:
- Admin Reset Button (#reset_order): <?php echo $can_reset ? 'VISIBLE' : 'HIDDEN'; ?> (admin + completed order)
- Unlock Button (#unlock_order): <?php echo $show_unlock_button ? 'VISIBLE' : 'HIDDEN'; ?> (based on 48h logic)
- Fix Buttons: <?php echo isset($fix_available) ? ($fix_available ? 'VISIBLE' : 'HIDDEN') : 'NOT SET'; ?> (admin always / users <48h)

Detailed Button Analysis:
<?php if ($can_reset): ?>
✅ RESET BUTTON SHOWING: You're admin (<?php echo $current_user_id; ?>) + order completed
<?php else: ?>
❌ RESET BUTTON HIDDEN: <?php echo !in_array($current_user_id, [1, 42]) ? 'Not admin user' : 'Order not completed'; ?>
<?php endif; ?>

<?php if ($show_unlock_button): ?>
✅ UNLOCK BUTTON SHOWING: <?php echo $can_unlock ? 'Enabled' : 'Disabled'; ?> - <?php echo $unlock_reason; ?>
<?php else: ?>
❌ UNLOCK BUTTON HIDDEN: <?php 
if (!$is_readonly) {
    echo 'Order not completed yet';
} elseif (in_array($current_user_id, [1, 42]) && $hours_since_completion <= 48) {
    echo 'Admin user within 48h (button not needed)';
} elseif (!in_array($current_user_id, [1, 42]) && $hours_since_completion <= 48) {
    echo 'Regular user within 48h (FIX available directly)';
} else {
    echo 'Unknown condition';
}
?>
<?php endif; ?>
                      </pre>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <!--/.row-->
        </div>
      </div>
    </main>
    <!-- ######### FOOTER BEGINS HERE ######### -->
    <?php include("assets/template/personalisation-menu.php"); ?>
  </div>

   <!-- ######### EXTERNAL CSS ######### -->
   <link rel="stylesheet" href="/assets/css/purchase_order_temp_page.css">
   <!-- ######### CSS ENDS HERE ######### -->

  <?php include("assets/template/html-footer.php"); ?>
  <?php include("assets/template/footer.php"); ?>
  <!-- ######### FOOTER ENDS HERE ######### -->

  <!-- ######### JAVASCRIPT BEGINS HERE ######### -->
  <script>
    // Pass PHP variables to JavaScript
    window.purchaseOrderReadonly = <?php echo $is_readonly ? 'true' : 'false'; ?>;
  </script>
  <script src="assets/js/purchase-order-receiving_1.js"></script>
  <!-- ######### JAVASCRIPT ENDS HERE ######### -->
</body>
</html>


</body>
</html>
