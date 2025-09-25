<?php
/**
 * Juice Transfer Creation Form
 * Create new juice transfers between outlets
 * Location: /juice-transfer/juice_transfer_create.php
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required = ['from_outlet_id', 'to_outlet_id', 'items'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
        // Validate items
        if (empty($_POST['items']) || !is_array($_POST['items'])) {
            throw new Exception('At least one item must be specified');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Create transfer record
        $stmt = $pdo->prepare("
            INSERT INTO juice_transfers (
                from_outlet_id, to_outlet_id, transfer_type, priority, 
                shipping_method, expected_delivery, notes, 
                created_by, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $_POST['from_outlet_id'],
            $_POST['to_outlet_id'],
            $_POST['transfer_type'] ?? 'standard',
            $_POST['priority'] ?? 'normal',
            $_POST['shipping_method'] ?? 'courier',
            $_POST['expected_delivery'] ?? null,
            $_POST['notes'] ?? null,
            $_SESSION['user_id']
        ]);
        
        $transferId = $pdo->lastInsertId();
        
        // Add transfer items
        $totalItems = 0;
        $totalVolume = 0;
        $totalValue = 0;
        
        foreach ($_POST['items'] as $item) {
            if (empty($item['product_id']) || empty($item['quantity_ml'])) {
                continue; // Skip empty items
            }
            
            // Get product cost
            $stmt = $pdo->prepare("SELECT cost_per_ml FROM juice_products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $costPerMl = $product['cost_per_ml'] ?? 0;
            
            // Insert transfer item
            $stmt = $pdo->prepare("
                INSERT INTO juice_transfer_items (
                    transfer_id, product_id, batch_id, quantity_ml, 
                    quality_grade, notes, cost_per_ml
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $transferId,
                $item['product_id'],
                $item['batch_id'] ?? null,
                $item['quantity_ml'],
                $item['quality_grade'] ?? 'A',
                $item['notes'] ?? null,
                $costPerMl
            ]);
            
            // Update inventory at source outlet
            $stmt = $pdo->prepare("
                UPDATE juice_inventory 
                SET quantity_ml = quantity_ml - ?, last_updated = NOW()
                WHERE outlet_id = ? AND product_id = ?
                AND quantity_ml >= ?
            ");
            
            $stmt->execute([
                $item['quantity_ml'],
                $_POST['from_outlet_id'],
                $item['product_id'],
                $item['quantity_ml']
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Insufficient stock for product ID " . $item['product_id']);
            }
            
            $totalItems++;
            $totalVolume += $item['quantity_ml'];
            $totalValue += $item['quantity_ml'] * $costPerMl;
        }
        
        if ($totalItems === 0) {
            throw new Exception('No valid items were added to the transfer');
        }
        
        // Update transfer totals
        $stmt = $pdo->prepare("
            UPDATE juice_transfers 
            SET total_items = ?, total_volume_ml = ?, total_value = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$totalItems, $totalVolume, $totalValue, $transferId]);
        
        // Log the activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (
                user_id, action, table_name, record_id, 
                details, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            'CREATE',
            'juice_transfers',
            $transferId,
            "Created juice transfer from outlet {$_POST['from_outlet_id']} to {$_POST['to_outlet_id']} with {$totalItems} items"
        ]);
        
        $pdo->commit();
        
        // Success message and redirect
        $_SESSION['success_message'] = "Transfer #" . str_pad($transferId, 6, '0', STR_PAD_LEFT) . " created successfully";
        header('Location: https://staff.vapeshed.co.nz/juice-transfer/juice_transfer_view.php?id=' . $transferId);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = $e->getMessage();
        error_log("Transfer creation error: " . $error_message);
    }
}

// Get outlets for form
try {
    $stmt = $pdo->query("
        SELECT id, name, address 
        FROM vend_outlets 
        WHERE status = 'active' 
        ORDER BY name
    ");
    $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $outlets = [];
    error_log("Error loading outlets: " . $e->getMessage());
}

// Build form content
ob_start();
?>

<div class="juice-transfer-create">
    <?php if (isset($error_message)): ?>
    <div class="juice-alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div class="alert-content">
            <div class="alert-title">Error Creating Transfer</div>
            <div class="alert-message"><?php echo htmlspecialchars($error_message); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="juice-alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div class="alert-content">
            <div class="alert-title">Success</div>
            <div class="alert-message"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" class="juice-transfer-form" data-endpoint="transfers" data-method="POST">
        <!-- Transfer Details Section -->
        <div class="juice-form-container">
            <div class="juice-form-section">
                <h4><i class="fas fa-info-circle"></i> Transfer Details</h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="from_outlet_id" class="required">From Outlet</label>
                            <?php echo $templateController->renderOutletSelector($_POST['from_outlet_id'] ?? null, null, 'from_outlet_id'); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="to_outlet_id" class="required">To Outlet</label>
                            <?php echo $templateController->renderOutletSelector($_POST['to_outlet_id'] ?? null, $_POST['from_outlet_id'] ?? null, 'to_outlet_id'); ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="transfer_type">Transfer Type</label>
                            <select name="transfer_type" id="transfer_type" class="form-control">
                                <option value="standard" <?php echo ($_POST['transfer_type'] ?? '') === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="urgent" <?php echo ($_POST['transfer_type'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="emergency" <?php echo ($_POST['transfer_type'] ?? '') === 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                <option value="scheduled" <?php echo ($_POST['transfer_type'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select name="priority" id="priority" class="form-control">
                                <option value="low" <?php echo ($_POST['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                <option value="normal" <?php echo ($_POST['priority'] ?? 'normal') === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="high" <?php echo ($_POST['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="shipping_method">Shipping Method</label>
                            <select name="shipping_method" id="shipping_method" class="form-control">
                                <option value="courier" <?php echo ($_POST['shipping_method'] ?? 'courier') === 'courier' ? 'selected' : ''; ?>>Courier</option>
                                <option value="pickup" <?php echo ($_POST['shipping_method'] ?? '') === 'pickup' ? 'selected' : ''; ?>>Pickup</option>
                                <option value="delivery" <?php echo ($_POST['shipping_method'] ?? '') === 'delivery' ? 'selected' : ''; ?>>Direct Delivery</option>
                                <option value="mail" <?php echo ($_POST['shipping_method'] ?? '') === 'mail' ? 'selected' : ''; ?>>Mail</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="expected_delivery">Expected Delivery Date</label>
                            <input type="date" 
                                   name="expected_delivery" 
                                   id="expected_delivery" 
                                   class="form-control"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo $_POST['expected_delivery'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="tracking_number">Tracking Number (Optional)</label>
                            <input type="text" 
                                   name="tracking_number" 
                                   id="tracking_number" 
                                   class="form-control"
                                   placeholder="Enter tracking number..."
                                   value="<?php echo $_POST['tracking_number'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Transfer Notes</label>
                    <textarea name="notes" 
                              id="notes" 
                              class="form-control" 
                              rows="3"
                              placeholder="Enter any special instructions or notes for this transfer..."><?php echo $_POST['notes'] ?? ''; ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Transfer Items Section -->
        <div class="juice-form-container">
            <div class="juice-form-section">
                <h4><i class="fas fa-boxes"></i> Transfer Items</h4>
                
                <div class="juice-items-list">
                    <!-- Dynamic items will be added here -->
                    <div class="text-center py-4">
                        <i class="fas fa-plus-circle text-muted" style="font-size: 3em;"></i>
                        <p class="text-muted mt-3">Click "Add Item" below to start building your transfer</p>
                    </div>
                </div>
                
                <button type="button" class="add-item-button">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
        </div>
        
        <!-- Transfer Summary -->
        <div class="juice-form-container">
            <div class="juice-form-section">
                <h4><i class="fas fa-calculator"></i> Transfer Summary</h4>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="juice-stat-card">
                            <div class="stat-number total-items">0</div>
                            <div class="stat-label">Total Items</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="juice-stat-card">
                            <div class="stat-number total-volume">0ml</div>
                            <div class="stat-label">Total Volume</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="juice-stat-card">
                            <div class="stat-number total-value">$0.00</div>
                            <div class="stat-label">Total Value</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="juice-stat-card">
                            <div class="stat-number" id="estimated-weight">0g</div>
                            <div class="stat-label">Est. Weight</div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden fields for totals -->
                <input type="hidden" name="total_items" id="total_items" value="0">
                <input type="hidden" name="total_volume_ml" id="total_volume_ml" value="0">
                <input type="hidden" name="total_value" id="total_value" value="0">
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="juice-form-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button type="button" class="juice-btn btn-secondary" onclick="window.history.back();">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </button>
                </div>
                
                <div>
                    <button type="button" class="juice-btn btn-outline-primary mr-2" id="save-draft">
                        <i class="fas fa-save"></i> Save Draft
                    </button>
                    <button type="submit" class="juice-btn btn-success">
                        <i class="fas fa-paper-plane"></i> Create Transfer
                    </button>
                </div>
            </div>
            
            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    This transfer will be created with "Pending" status and will require approval before processing.
                </small>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Initialize form with one empty item
    setTimeout(function() {
        $('.add-item-button').click();
    }, 100);
    
    // Handle outlet changes to refresh product dropdowns
    $(document).on('change', 'select[name="from_outlet_id"]', function() {
        const outletId = $(this).val();
        JuiceTransfer.refreshProductSelectors(outletId);
        
        // Update to_outlet options to exclude selected from_outlet
        const $toSelect = $('select[name="to_outlet_id"]');
        $toSelect.find('option').prop('disabled', false);
        if (outletId) {
            $toSelect.find(`option[value="${outletId}"]`).prop('disabled', true);
        }
    });
    
    // Auto-calculate estimated weight
    function calculateEstimatedWeight() {
        const totalVolume = parseFloat($('#total_volume_ml').val() || 0);
        // Assume average density of 1.2g/ml for e-juice
        const estimatedWeight = Math.round(totalVolume * 1.2);
        $('#estimated-weight').text(estimatedWeight + 'g');
    }
    
    // Update weight calculation when totals change
    $(document).on('change', '#total_volume_ml', calculateEstimatedWeight);
    
    // Save draft functionality
    $('#save-draft').on('click', function() {
        const formData = JuiceTransfer.collectFormData($('.juice-transfer-form'));
        localStorage.setItem('juice_transfer_draft', JSON.stringify(formData));
        JuiceTransfer.showNotification('Draft saved locally', 'success');
    });
    
    // Load draft on page load
    const savedDraft = localStorage.getItem('juice_transfer_draft');
    if (savedDraft && confirm('A draft transfer was found. Would you like to load it?')) {
        try {
            const draftData = JSON.parse(savedDraft);
            // Populate form with draft data
            Object.keys(draftData).forEach(key => {
                const $input = $(`[name="${key}"]`);
                if ($input.length) {
                    $input.val(draftData[key]);
                }
            });
        } catch (e) {
            console.error('Error loading draft:', e);
        }
    }
    
    // Clear draft on successful submission
    $('.juice-transfer-form').on('submit', function() {
        localStorage.removeItem('juice_transfer_draft');
    });
    
    // Validation enhancement
    $('.juice-transfer-form').on('submit', function(e) {
        const itemCount = $('.juice-item-row').length;
        if (itemCount === 0) {
            e.preventDefault();
            JuiceTransfer.showNotification('Please add at least one item to the transfer', 'error');
            return false;
        }
        
        // Check if from and to outlets are different
        const fromOutlet = $('select[name="from_outlet_id"]').val();
        const toOutlet = $('select[name="to_outlet_id"]').val();
        
        if (fromOutlet === toOutlet) {
            e.preventDefault();
            JuiceTransfer.showNotification('From and To outlets cannot be the same', 'error');
            return false;
        }
        
        return true;
    });
});
</script>

<?php
$content = ob_get_clean();

// Additional CSS for creation form
$additionalCSS = [
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
    'https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css'
];

// Additional JS for creation form
$additionalJS = [
    'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js'
];

// Render the page
echo $templateController->renderJuiceTransferPage(
    'Create New Transfer',
    $content,
    'create',
    $additionalCSS,
    $additionalJS
);
?>
