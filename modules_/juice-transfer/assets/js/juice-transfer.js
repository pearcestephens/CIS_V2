/**
 * Juice Transfer System JavaScript Framework
 * Enhanced functionality for juice transfer management
 * Version: 2.0
 * Dependencies: jQuery 3.x, Bootstrap 4.x
 */

(function($) {
    'use strict';
    
    /**
     * Main JuiceTransfer class
     */
    window.JuiceTransfer = {
        
        // Configuration
        config: {
            apiBase: '/juice-transfer/api/juice_transfer_api.php',
            refreshInterval: 30000, // 30 seconds
            maxRetries: 3,
            retryDelay: 1000
        },
        
        // State management
        state: {
            currentOutletId: null,
            selectedProducts: {},
            formData: {},
            refreshTimer: null,
            activeRequests: new Set()
        },
        
        /**
         * Initialize the system
         */
        init: function() {
            console.log('Initializing Juice Transfer System...');
            
            // Set current outlet from session or page data
            this.state.currentOutletId = window.currentOutletId || null;
            
            // Bind global events
            this.bindEvents();
            
            // Initialize components
            this.initializeComponents();
            
            // Start auto-refresh if on dashboard
            if (window.location.pathname.includes('dashboard')) {
                this.startAutoRefresh();
            }
            
            // Initialize form validation
            this.initializeValidation();
            
            console.log('Juice Transfer System initialized successfully');
        },
        
        /**
         * Bind global event handlers
         */
        bindEvents: function() {
            // Form submission handlers
            $(document).on('submit', '.juice-transfer-form', this.handleFormSubmit.bind(this));
            
            // Dynamic item management
            $(document).on('click', '.add-item-button', this.addTransferItem.bind(this));
            $(document).on('click', '.item-remove', this.removeTransferItem.bind(this));
            
            // Outlet selection changes
            $(document).on('change', '.juice-outlet-selector', this.handleOutletChange.bind(this));
            
            // Product selection changes
            $(document).on('change', '.juice-product-selector', this.handleProductChange.bind(this));
            
            // Batch selection changes
            $(document).on('change', '.juice-batch-selector', this.handleBatchChange.bind(this));
            
            // Quantity input changes
            $(document).on('input change', '.quantity-input', this.handleQuantityChange.bind(this));
            
            // Action button clicks
            $(document).on('click', '.juice-action-btn', this.handleActionClick.bind(this));
            
            // Search and filter changes
            $(document).on('input', '.juice-search-input', this.debounce(this.handleSearch.bind(this), 300));
            $(document).on('change', '.juice-filter-select', this.handleFilterChange.bind(this));
            
            // Status update buttons
            $(document).on('click', '.status-update-btn', this.handleStatusUpdate.bind(this));
            
            // Print functionality
            $(document).on('click', '.print-transfer-btn', this.handlePrintTransfer.bind(this));
            
            // Page unload warning for unsaved changes
            $(window).on('beforeunload', this.handlePageUnload.bind(this));
            
            // Keyboard shortcuts
            $(document).on('keydown', this.handleKeyboard.bind(this));
            
            console.log('Global events bound successfully');
        },
        
        /**
         * Initialize page components
         */
        initializeComponents: function() {
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Initialize modals
            $('.modal').on('shown.bs.modal', function() {
                $(this).find('input, select, textarea').first().focus();
            });
            
            // Initialize date pickers
            if ($.fn.datepicker) {
                $('.date-picker').datepicker({
                    format: 'yyyy-mm-dd',
                    autoclose: true,
                    todayHighlight: true
                });
            }
            
            // Initialize select2 for better dropdowns
            if ($.fn.select2) {
                $('.juice-select2').select2({
                    theme: 'bootstrap4',
                    width: '100%'
                });
            }
            
            // Initialize DataTables if present
            if ($.fn.DataTable && $('.juice-datatable').length) {
                $('.juice-datatable').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[0, 'desc']],
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                    language: {
                        search: "Search transfers:",
                        lengthMenu: "Show _MENU_ transfers per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ transfers"
                    }
                });
            }
            
            // Auto-calculate totals on form pages
            this.calculateFormTotals();
            
            console.log('Components initialized successfully');
        },
        
        /**
         * Initialize form validation
         */
        initializeValidation: function() {
            // Custom validation rules
            if ($.validator) {
                $.validator.addMethod('validVolume', function(value, element) {
                    if (!value) return true; // Let required handle empty values
                    const volume = parseFloat(value);
                    return volume > 0 && volume <= 10000; // Max 10L per item
                }, 'Please enter a valid volume between 1ml and 10,000ml');
                
                $.validator.addMethod('stockAvailable', function(value, element) {
                    const $productSelect = $(element).closest('.juice-item-row').find('.juice-product-selector');
                    const availableStock = parseFloat($productSelect.find(':selected').data('stock') || 0);
                    const requestedVolume = parseFloat(value || 0);
                    return requestedVolume <= availableStock;
                }, 'Requested volume exceeds available stock');
                
                // Apply validation to forms
                $('.juice-transfer-form').each(function() {
                    $(this).validate({
                        errorClass: 'is-invalid',
                        validClass: 'is-valid',
                        errorPlacement: function(error, element) {
                            error.addClass('invalid-feedback');
                            element.closest('.form-group').append(error);
                        },
                        highlight: function(element) {
                            $(element).addClass('is-invalid').removeClass('is-valid');
                        },
                        unhighlight: function(element) {
                            $(element).addClass('is-valid').removeClass('is-invalid');
                        }
                    });
                });
            }
        },
        
        /**
         * Start dashboard auto-refresh
         */
        startAutoRefresh: function() {
            this.state.refreshTimer = setInterval(() => {
                this.refreshDashboardData();
            }, this.config.refreshInterval);
            
            console.log('Auto-refresh started');
        },
        
        /**
         * Stop auto-refresh
         */
        stopAutoRefresh: function() {
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
                this.state.refreshTimer = null;
                console.log('Auto-refresh stopped');
            }
        },
        
        /**
         * Refresh dashboard data
         */
        refreshDashboardData: function() {
            this.apiRequest('GET', 'dashboard')
                .then(response => {
                    this.updateDashboardStats(response.stats);
                    this.updateRecentTransfers(response.recent_transfers);
                    this.updateAlerts(response.low_stock_alerts, response.quality_issues);
                    
                    // Update timestamp
                    $('.last-updated').text('Last updated: ' + new Date().toLocaleTimeString());
                })
                .catch(error => {
                    console.error('Failed to refresh dashboard:', error);
                });
        },
        
        /**
         * Update dashboard statistics
         */
        updateDashboardStats: function(stats) {
            Object.keys(stats).forEach(key => {
                const $statCard = $(`.stat-card[data-stat="${key}"]`);
                if ($statCard.length) {
                    const $number = $statCard.find('.stat-number');
                    const oldValue = parseInt($number.text()) || 0;
                    const newValue = stats[key];
                    
                    // Animate value change
                    this.animateNumber($number, oldValue, newValue);
                    
                    // Update trend indicator
                    this.updateTrendIndicator($statCard, oldValue, newValue);
                }
            });
        },
        
        /**
         * Update recent transfers list
         */
        updateRecentTransfers: function(transfers) {
            const $container = $('#recent-transfers-list');
            if ($container.length && transfers) {
                let html = '';
                transfers.forEach(transfer => {
                    html += this.renderTransferListItem(transfer);
                });
                $container.html(html);
            }
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = this.collectFormData($form);
            
            // Validate form
            if ($form.valid && !$form.valid()) {
                this.showNotification('Please fix the form errors before submitting', 'error');
                return;
            }
            
            // Show loading state
            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            // Determine API endpoint and method
            const endpoint = $form.data('endpoint') || 'transfers';
            const method = $form.data('method') || 'POST';
            
            this.apiRequest(method, endpoint, formData)
                .then(response => {
                    this.showNotification(response.message || 'Operation completed successfully', 'success');
                    
                    // Reset form if creation was successful
                    if (method === 'POST' && response.transfer_id) {
                        $form[0].reset();
                        this.clearTransferItems();
                    }
                    
                    // Redirect if specified
                    const redirectUrl = $form.data('redirect');
                    if (redirectUrl) {
                        setTimeout(() => {
                            window.location.href = redirectUrl.replace('{id}', response.transfer_id || '');
                        }, 1500);
                    }
                })
                .catch(error => {
                    this.showNotification(error.message || 'An error occurred', 'error');
                })
                .finally(() => {
                    $submitBtn.prop('disabled', false).text(originalText);
                });
        },
        
        /**
         * Add new transfer item
         */
        addTransferItem: function(e) {
            e.preventDefault();
            
            const $container = $('.juice-items-list');
            const itemCount = $container.find('.juice-item-row').length;
            const itemHtml = this.generateItemRowHtml(itemCount);
            
            $container.append(itemHtml);
            
            // Initialize new row components
            const $newRow = $container.find('.juice-item-row').last();
            $newRow.find('.juice-select2').select2({
                theme: 'bootstrap4',
                width: '100%'
            });
            
            // Focus on first input
            $newRow.find('select, input').first().focus();
            
            this.calculateFormTotals();
        },
        
        /**
         * Remove transfer item
         */
        removeTransferItem: function(e) {
            e.preventDefault();
            
            const $row = $(e.target).closest('.juice-item-row');
            
            // Confirm removal if item has data
            if ($row.find('input, select').filter(function() { return $(this).val(); }).length > 0) {
                if (!confirm('Are you sure you want to remove this item?')) {
                    return;
                }
            }
            
            $row.fadeOut(300, function() {
                $(this).remove();
                JuiceTransfer.calculateFormTotals();
                JuiceTransfer.renumberItems();
            });
        },
        
        /**
         * Handle outlet change
         */
        handleOutletChange: function(e) {
            const $select = $(e.target);
            const outletId = $select.val();
            
            // Update state
            if ($select.hasClass('from-outlet')) {
                this.state.currentOutletId = outletId;
            }
            
            // Refresh product dropdowns for this outlet
            this.refreshProductSelectors(outletId);
            
            // Clear form totals
            this.calculateFormTotals();
        },
        
        /**
         * Handle product change
         */
        handleProductChange: function(e) {
            const $select = $(e.target);
            const productId = $select.val();
            const $row = $select.closest('.juice-item-row');
            
            if (productId) {
                // Update product info display
                this.updateProductInfo($row, $select.find(':selected'));
                
                // Load available batches
                this.loadBatches($row, productId);
                
                // Validate quantity against stock
                this.validateQuantity($row);
            } else {
                // Clear dependent fields
                $row.find('.batch-selector').empty().append('<option value="">Select product first...</option>');
                $row.find('.product-info').hide();
            }
            
            this.calculateFormTotals();
        },
        
        /**
         * Handle batch change
         */
        handleBatchChange: function(e) {
            const $select = $(e.target);
            const batchId = $select.val();
            const $row = $select.closest('.juice-item-row');
            
            if (batchId) {
                // Update batch info display
                this.updateBatchInfo($row, $select.find(':selected'));
            }
            
            this.calculateFormTotals();
        },
        
        /**
         * Handle quantity change
         */
        handleQuantityChange: function(e) {
            const $input = $(e.target);
            const $row = $input.closest('.juice-item-row');
            
            // Validate quantity
            this.validateQuantity($row);
            
            // Update row total
            this.calculateRowTotal($row);
            
            // Update form totals
            this.calculateFormTotals();
        },
        
        /**
         * Validate quantity against available stock
         */
        validateQuantity: function($row) {
            const $quantityInput = $row.find('.quantity-input');
            const $productSelect = $row.find('.juice-product-selector');
            const quantity = parseFloat($quantityInput.val() || 0);
            const availableStock = parseFloat($productSelect.find(':selected').data('stock') || 0);
            
            if (quantity > availableStock) {
                $quantityInput.addClass('is-invalid');
                $row.find('.stock-warning').remove();
                $row.append(`
                    <div class="alert alert-warning stock-warning mt-2">
                        <i class="fas fa-exclamation-triangle"></i>
                        Requested ${this.formatVolume(quantity)} exceeds available stock of ${this.formatVolume(availableStock)}
                    </div>
                `);
            } else {
                $quantityInput.removeClass('is-invalid');
                $row.find('.stock-warning').remove();
            }
        },
        
        /**
         * Calculate form totals
         */
        calculateFormTotals: function() {
            let totalVolume = 0;
            let totalValue = 0;
            let totalItems = 0;
            
            $('.juice-item-row').each(function() {
                const $row = $(this);
                const quantity = parseFloat($row.find('.quantity-input').val() || 0);
                const costPerMl = parseFloat($row.find('.juice-product-selector :selected').data('cost') || 0);
                
                if (quantity > 0) {
                    totalVolume += quantity;
                    totalValue += quantity * costPerMl;
                    totalItems++;
                }
            });
            
            // Update display
            $('.total-items').text(totalItems);
            $('.total-volume').text(this.formatVolume(totalVolume));
            $('.total-value').text('$' + totalValue.toFixed(2));
            
            // Update hidden form fields
            $('#total_items').val(totalItems);
            $('#total_volume_ml').val(totalVolume);
            $('#total_value').val(totalValue.toFixed(2));
        },
        
        /**
         * Calculate individual row total
         */
        calculateRowTotal: function($row) {
            const quantity = parseFloat($row.find('.quantity-input').val() || 0);
            const costPerMl = parseFloat($row.find('.juice-product-selector :selected').data('cost') || 0);
            const rowTotal = quantity * costPerMl;
            
            $row.find('.row-total').text('$' + rowTotal.toFixed(2));
        },
        
        /**
         * Load batches for selected product
         */
        loadBatches: function($row, productId) {
            const $batchSelect = $row.find('.juice-batch-selector');
            $batchSelect.prop('disabled', true).html('<option value="">Loading...</option>');
            
            this.apiRequest('GET', 'batches', { product_id: productId })
                .then(response => {
                    let html = '<option value="">Select batch (optional)...</option>';
                    
                    if (response.batches && response.batches.length > 0) {
                        response.batches.forEach(batch => {
                            const expiryStatus = batch.days_until_expiry < 30 ? ' (expires soon)' : '';
                            html += `<option value="${batch.id}" 
                                        data-expiry="${batch.expiry_date}"
                                        data-production="${batch.production_date}">
                                        Batch ${batch.batch_number}${expiryStatus}
                                     </option>`;
                        });
                    }
                    
                    $batchSelect.prop('disabled', false).html(html);
                })
                .catch(error => {
                    console.error('Error loading batches:', error);
                    $batchSelect.prop('disabled', false).html('<option value="">Error loading batches</option>');
                });
        },
        
        /**
         * Refresh product selectors
         */
        refreshProductSelectors: function(outletId) {
            const $selectors = $('.juice-product-selector');
            
            if (!outletId) {
                $selectors.prop('disabled', true).html('<option value="">Select outlet first...</option>');
                return;
            }
            
            $selectors.prop('disabled', true).html('<option value="">Loading...</option>');
            
            this.apiRequest('GET', 'products', { outlet_id: outletId })
                .then(response => {
                    let html = '<option value="">Select Product...</option>';
                    
                    if (response.products && response.products.length > 0) {
                        response.products.forEach(product => {
                            const stockClass = `stock-${product.stock_status}`;
                            html += `<option value="${product.id}" 
                                        class="${stockClass}"
                                        data-stock="${product.current_stock}"
                                        data-cost="${product.cost_per_ml || 0}"
                                        data-nicotine="${product.nicotine_strength || 0}"
                                        data-vg="${product.vg_ratio || 0}">
                                        ${product.name} (${JuiceTransfer.formatVolume(product.current_stock)})
                                        ${product.nicotine_strength ? ' - ' + product.nicotine_strength + 'mg' : ''}
                                     </option>`;
                        });
                    }
                    
                    $selectors.prop('disabled', false).html(html);
                })
                .catch(error => {
                    console.error('Error loading products:', error);
                    $selectors.prop('disabled', false).html('<option value="">Error loading products</option>');
                });
        },
        
        /**
         * Handle action button clicks
         */
        handleActionClick: function(e) {
            e.preventDefault();
            
            const $btn = $(e.target).closest('.juice-action-btn');
            const action = $btn.data('action');
            const transferId = $btn.data('transfer-id');
            
            switch (action) {
                case 'view':
                    this.viewTransfer(transferId);
                    break;
                case 'edit':
                    this.editTransfer(transferId);
                    break;
                case 'approve':
                    this.approveTransfer(transferId);
                    break;
                case 'cancel':
                    this.cancelTransfer(transferId);
                    break;
                case 'receive':
                    this.receiveTransfer(transferId);
                    break;
                default:
                    console.warn('Unknown action:', action);
            }
        },
        
        /**
         * Handle status updates
         */
        handleStatusUpdate: function(e) {
            e.preventDefault();
            
            const $btn = $(e.target);
            const transferId = $btn.data('transfer-id');
            const newStatus = $btn.data('status');
            
            if (confirm(`Are you sure you want to update this transfer to "${newStatus}"?`)) {
                this.updateTransferStatus(transferId, newStatus);
            }
        },
        
        /**
         * Update transfer status via API
         */
        updateTransferStatus: function(transferId, status) {
            const data = { status: status };
            
            this.apiRequest('PUT', `transfers?id=${transferId}`, data)
                .then(response => {
                    this.showNotification('Transfer status updated successfully', 'success');
                    
                    // Update UI
                    $(`.transfer-row[data-transfer-id="${transferId}"] .status-badge`)
                        .removeClass()
                        .addClass(`status-badge status-${status}`)
                        .html(`<i class="fas fa-${this.getStatusIcon(status)}"></i> ${status.replace('_', ' ').toUpperCase()}`);
                    
                    // Refresh if on dashboard
                    if (window.location.pathname.includes('dashboard')) {
                        setTimeout(() => this.refreshDashboardData(), 1000);
                    }
                })
                .catch(error => {
                    this.showNotification(error.message || 'Failed to update status', 'error');
                });
        },
        
        /**
         * Make API request with retry logic
         */
        apiRequest: function(method, endpoint, data = null, retryCount = 0) {
            const requestId = Date.now() + Math.random();
            this.state.activeRequests.add(requestId);
            
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            if (['POST', 'PUT', 'PATCH'].includes(method) && data) {
                options.body = JSON.stringify(data);
            }
            
            let url = `${this.config.apiBase}?endpoint=${endpoint}`;
            if (method === 'GET' && data) {
                const params = new URLSearchParams(data);
                url += '&' + params.toString();
            }
            
            return fetch(url, options)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    this.state.activeRequests.delete(requestId);
                    
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    return data;
                })
                .catch(error => {
                    this.state.activeRequests.delete(requestId);
                    
                    // Retry logic for network errors
                    if (retryCount < this.config.maxRetries && 
                        (error.name === 'TypeError' || error.message.includes('fetch'))) {
                        
                        console.warn(`API request failed, retrying (${retryCount + 1}/${this.config.maxRetries})...`);
                        
                        return new Promise((resolve) => {
                            setTimeout(() => {
                                resolve(this.apiRequest(method, endpoint, data, retryCount + 1));
                            }, this.config.retryDelay * Math.pow(2, retryCount));
                        });
                    }
                    
                    throw error;
                });
        },
        
        /**
         * Show notification to user
         */
        showNotification: function(message, type = 'info', duration = 5000) {
            // Remove existing notifications
            $('.juice-notification').fadeOut(200, function() {
                $(this).remove();
            });
            
            // Create new notification
            const alertClass = type === 'error' ? 'alert-danger' : `alert-${type}`;
            const iconClass = {
                'success': 'fa-check-circle',
                'error': 'fa-exclamation-circle',
                'warning': 'fa-exclamation-triangle',
                'info': 'fa-info-circle'
            }[type] || 'fa-info-circle';
            
            const $notification = $(`
                <div class="juice-notification alert ${alertClass} alert-dismissible fade show" 
                     style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;">
                    <i class="fas ${iconClass}"></i>
                    <span class="ml-2">${message}</span>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            `);
            
            $('body').append($notification);
            
            // Auto-dismiss
            if (duration > 0) {
                setTimeout(() => {
                    $notification.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, duration);
            }
        },
        
        /**
         * Format volume for display
         */
        formatVolume: function(volumeMl) {
            if (volumeMl >= 1000) {
                return (volumeMl / 1000).toFixed(1) + 'L';
            }
            return Math.round(volumeMl) + 'ml';
        },
        
        /**
         * Format currency
         */
        formatCurrency: function(amount) {
            return '$' + parseFloat(amount).toFixed(2);
        },
        
        /**
         * Get status icon
         */
        getStatusIcon: function(status) {
            const icons = {
                'pending': 'clock',
                'approved': 'check',
                'in_transit': 'truck',
                'delivered': 'box',
                'received': 'check-circle',
                'cancelled': 'times'
            };
            
            return icons[status] || 'question';
        },
        
        /**
         * Debounce function for search inputs
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
        
        /**
         * Handle search input
         */
        handleSearch: function(e) {
            const searchTerm = $(e.target).val().toLowerCase();
            const $table = $(e.target).closest('.juice-table-wrapper').find('table');
            
            $table.find('tbody tr').each(function() {
                const $row = $(this);
                const text = $row.text().toLowerCase();
                
                if (text.includes(searchTerm)) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },
        
        /**
         * Handle filter changes
         */
        handleFilterChange: function(e) {
            const $select = $(e.target);
            const filterValue = $select.val();
            const filterType = $select.data('filter-type');
            
            // Implement filtering logic based on filter type
            this.applyTableFilter(filterType, filterValue);
        },
        
        /**
         * Apply table filters
         */
        applyTableFilter: function(filterType, value) {
            const $table = $('.juice-datatable');
            
            if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
                // Use DataTables API for filtering
                const table = $table.DataTable();
                
                if (value) {
                    table.column(filterType).search(value).draw();
                } else {
                    table.column(filterType).search('').draw();
                }
            }
        },
        
        /**
         * Handle keyboard shortcuts
         */
        handleKeyboard: function(e) {
            // Ctrl/Cmd + N for new transfer
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                const newTransferUrl = $('.juice-nav a[href*="create"]').attr('href');
                if (newTransferUrl) {
                    window.location.href = newTransferUrl;
                }
            }
            
            // Ctrl/Cmd + S for save (if on form page)
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const $form = $('.juice-transfer-form');
                if ($form.length) {
                    e.preventDefault();
                    $form.submit();
                }
            }
        },
        
        /**
         * Handle page unload
         */
        handlePageUnload: function(e) {
            // Warn about unsaved changes
            if (this.hasUnsavedChanges()) {
                const message = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        },
        
        /**
         * Check for unsaved changes
         */
        hasUnsavedChanges: function() {
            const $form = $('.juice-transfer-form');
            if ($form.length === 0) return false;
            
            // Simple check - see if any form fields have values
            return $form.find('input, select, textarea').filter(function() {
                return $(this).val() && $(this).val() !== $(this).attr('data-original-value');
            }).length > 0;
        },
        
        /**
         * Animate number changes
         */
        animateNumber: function($element, from, to, duration = 1000) {
            const start = performance.now();
            
            const animate = (currentTime) => {
                const elapsed = currentTime - start;
                const progress = Math.min(elapsed / duration, 1);
                const current = Math.round(from + (to - from) * progress);
                
                $element.text(current.toLocaleString());
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };
            
            requestAnimationFrame(animate);
        },
        
        /**
         * Generate item row HTML
         */
        generateItemRowHtml: function(index) {
            return `
                <div class="juice-item-row" data-index="${index}">
                    <button type="button" class="item-remove">
                        <i class="fas fa-times"></i>
                    </button>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Product <span class="text-danger">*</span></label>
                                <select name="items[${index}][product_id]" 
                                        class="form-control juice-product-selector juice-select2" 
                                        required>
                                    <option value="">Select Product...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Batch (Optional)</label>
                                <select name="items[${index}][batch_id]" 
                                        class="form-control juice-batch-selector">
                                    <option value="">Select product first...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quantity (ml) <span class="text-danger">*</span></label>
                                <input type="number" 
                                       name="items[${index}][quantity_ml]" 
                                       class="form-control quantity-input" 
                                       min="1" 
                                       step="1" 
                                       required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Quality Grade</label>
                                <select name="items[${index}][quality_grade]" class="form-control">
                                    <option value="A">A - Premium</option>
                                    <option value="B">B - Standard</option>
                                    <option value="C">C - Budget</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>Total</label>
                                <div class="form-control-plaintext row-total">$0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="items[${index}][notes]" 
                                          class="form-control" 
                                          rows="2" 
                                          placeholder="Additional notes for this item..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="product-info" style="display: none;">
                        <!-- Product details will be populated here -->
                    </div>
                </div>
            `;
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        JuiceTransfer.init();
    });
    
    // Clean up on page unload
    $(window).on('beforeunload', function() {
        JuiceTransfer.stopAutoRefresh();
    });
    
})(jQuery);
