<?php
/**
 * Package & Label Management Service with AI Integration
 * Purpose: Advanced package handling, volumetric calculations, and shipping label management
 * Author: CIS System
 * Dependencies: NZ Post/GSS APIs, NeuralBrainIntegration, ConfigurationManager
 */

require_once __DIR__ . '/../ConfigurationManager.php';
require_once __DIR__ . '/../ai/NeuroBrainIntegration.php';
require_once __DIR__ . '/../../assets/functions/config.php';

class PackageLabelService {
    private mysqli $con;
    private ConfigurationManager $config;
    private ?NeuralBrainIntegration $neural_brain;
    
    // Shipping carrier constants
    const CARRIERS = [
        'NZPOST' => 'NZ Post',
        'GSS' => 'GSS/NZ Couriers', 
        'MANUAL' => 'Manual Entry',
        'VAN' => 'Van Delivery'
    ];
    
    // Package dimension limits (cm)
    const MAX_DIMENSIONS = [
        'NZPOST' => ['length' => 105, 'width' => 105, 'height' => 105, 'weight' => 30000], // 30kg
        'GSS' => ['length' => 120, 'width' => 80, 'height' => 80, 'weight' => 35000], // 35kg
        'MANUAL' => ['length' => 200, 'width' => 200, 'height' => 200, 'weight' => 100000], // 100kg
        'VAN' => ['length' => 300, 'width' => 200, 'height' => 200, 'weight' => 500000] // 500kg
    ];
    
    // Volumetric weight divisors
    const VOLUMETRIC_DIVISORS = [
        'NZPOST' => 4000, // Standard NZ Post divisor
        'GSS' => 5000,    // GSS/Couriers divisor
        'MANUAL' => 6000, // Conservative manual
        'VAN' => 10000    // Van delivery (lower priority on weight)
    ];
    
    public function __construct() {
        $this->con = connectToSQL();
        $this->config = new ConfigurationManager($this->con);
        
        // Initialize Neural Brain for package optimization learning
        try {
            $this->neural_brain = init_neural_brain($this->con);
        } catch (Exception $e) {
            error_log("PackageService: Neural Brain initialization failed: " . $e->getMessage());
            $this->neural_brain = null;
        }
    }
    
    /**
     * Create optimized package configuration from transfer items
     */
    public function optimizePackaging(int $transfer_id, string $preferred_carrier = 'AUTO'): array {
        try {
            // Get transfer items with product dimensions/weights
            $items = $this->getTransferItemsWithDimensions($transfer_id);
            
            if (empty($items)) {
                throw new Exception("No items found for transfer {$transfer_id}");
            }
            
            // Calculate total volume and weight
            $total_stats = $this->calculateTotalStats($items);
            
            // Determine optimal carrier if auto-selection
            if ($preferred_carrier === 'AUTO') {
                $preferred_carrier = $this->selectOptimalCarrier($total_stats, $items);
            }
            
            // Generate package recommendations
            $packages = $this->generatePackageRecommendations($items, $preferred_carrier, $total_stats);
            
            // Store packaging insights in Neural Brain
            if ($this->neural_brain) {
                $this->neural_brain->storePattern(
                    "Package Optimization",
                    "Generated {$packages['package_count']} packages for transfer {$transfer_id} using {$preferred_carrier}",
                    [
                        'carrier' => $preferred_carrier,
                        'package_count' => $packages['package_count'],
                        'total_weight' => $total_stats['total_weight'],
                        'total_volume' => $total_stats['total_volume'],
                        'volumetric_weight' => $packages['volumetric_weight'],
                        'efficiency_score' => $packages['efficiency_score']
                    ],
                    0.8
                );
            }
            
            return [
                'success' => true,
                'transfer_id' => $transfer_id,
                'carrier' => $preferred_carrier,
                'packages' => $packages['packages'],
                'summary' => [
                    'package_count' => $packages['package_count'],
                    'total_weight_kg' => round($total_stats['total_weight'] / 1000, 2),
                    'total_volume_cm3' => $total_stats['total_volume'],
                    'volumetric_weight_kg' => round($packages['volumetric_weight'] / 1000, 2),
                    'chargeable_weight_kg' => round($packages['chargeable_weight'] / 1000, 2),
                    'efficiency_score' => $packages['efficiency_score'],
                    'estimated_cost' => $packages['estimated_cost']
                ],
                'recommendations' => $packages['recommendations']
            ];
            
        } catch (Exception $e) {
            error_log("Package optimization error: " . $e->getMessage());
            
            if ($this->neural_brain) {
                $this->neural_brain->storeError(
                    "Package optimization failed: " . $e->getMessage(),
                    "Review transfer items and product dimension data",
                    "Transfer ID: {$transfer_id}, Carrier: {$preferred_carrier}"
                );
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transfer_id' => $transfer_id
            ];
        }
    }
    
    /**
     * Create shipping labels for packages
     */
    public function createShippingLabels(int $transfer_id, array $package_data, array $shipping_details): array {
        try {
            $results = [];
            $carrier = $package_data['carrier'] ?? 'MANUAL';
            
            // Validate shipping details
            $this->validateShippingDetails($shipping_details);
            
            foreach ($package_data['packages'] as $index => $package) {
                $label_result = $this->createSingleLabel($transfer_id, $package, $shipping_details, $carrier, $index + 1);
                $results[] = $label_result;
                
                // Store label in database
                $this->storeLabelRecord($transfer_id, $label_result, $package, $carrier);
            }
            
            // Learn from labeling patterns
            if ($this->neural_brain) {
                $success_count = count(array_filter($results, fn($r) => $r['success']));
                $this->neural_brain->storePattern(
                    "Label Generation",
                    "Created {$success_count}/{count($results)} labels for transfer {$transfer_id}",
                    [
                        'carrier' => $carrier,
                        'package_count' => count($results),
                        'success_rate' => $success_count / count($results),
                        'service_type' => $shipping_details['service_type'] ?? 'standard'
                    ],
                    $success_count === count($results) ? 0.9 : 0.6
                );
            }
            
            return [
                'success' => true,
                'transfer_id' => $transfer_id,
                'carrier' => $carrier,
                'labels_created' => count(array_filter($results, fn($r) => $r['success'])),
                'total_packages' => count($results),
                'labels' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Label creation error: " . $e->getMessage());
            
            if ($this->neural_brain) {
                $this->neural_brain->storeError(
                    "Label creation failed: " . $e->getMessage(),
                    "Check carrier API credentials and shipping details",
                    "Transfer ID: {$transfer_id}"
                );
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transfer_id' => $transfer_id
            ];
        }
    }
    
    /**
     * Get package consolidation recommendations
     */
    public function getConsolidationRecommendations(array $package_data): array {
        $recommendations = [];
        $packages = $package_data['packages'] ?? [];
        
        if (count($packages) <= 1) {
            return ['consolidation_possible' => false, 'recommendations' => []];
        }
        
        // Check for under-utilized packages
        $underutilized = array_filter($packages, function($pkg) {
            $utilization = $this->calculatePackageUtilization($pkg);
            return $utilization < 0.6; // Less than 60% utilized
        });
        
        if (count($underutilized) >= 2) {
            $recommendations[] = [
                'type' => 'consolidation',
                'message' => count($underutilized) . ' packages are under-utilized and could potentially be consolidated',
                'affected_packages' => array_keys($underutilized),
                'potential_savings' => $this->estimateConsolidationSavings($underutilized)
            ];
        }
        
        // Check for oversized packages
        $oversized = array_filter($packages, function($pkg) {
            return $this->isPackageOversized($pkg, $package_data['carrier'] ?? 'NZPOST');
        });
        
        if (!empty($oversized)) {
            $recommendations[] = [
                'type' => 'split_required',
                'message' => count($oversized) . ' packages exceed carrier limits and must be split',
                'affected_packages' => array_keys($oversized),
                'required_action' => 'Package splitting required before shipping'
            ];
        }
        
        // Check for carrier optimization
        $carrier_rec = $this->getCarrierOptimizationRecommendation($package_data);
        if ($carrier_rec) {
            $recommendations[] = $carrier_rec;
        }
        
        return [
            'consolidation_possible' => !empty($recommendations),
            'recommendations' => $recommendations,
            'total_packages' => count($packages),
            'optimization_score' => $this->calculateOverallOptimizationScore($packages)
        ];
    }
    
    /**
     * Generate box labels with transfer information
     */
    public function generateBoxLabels(int $transfer_id, array $package_data): array {
        try {
            // Get transfer details
            $transfer = $this->getTransferDetails($transfer_id);
            if (!$transfer) {
                throw new Exception("Transfer {$transfer_id} not found");
            }
            
            $labels = [];
            $total_packages = count($package_data['packages'] ?? []);
            
            foreach ($package_data['packages'] as $index => $package) {
                $package_number = $index + 1;
                
                $label_data = [
                    'transfer_number' => $transfer['transfer_number'] ?? "T-{$transfer_id}",
                    'package_number' => $package_number,
                    'total_packages' => $total_packages,
                    'from_store' => $transfer['from_store_name'] ?? 'Unknown',
                    'to_store' => $transfer['to_store_name'] ?? 'Unknown',
                    'created_date' => date('Y-m-d'),
                    'created_by' => $_SESSION['username'] ?? 'System',
                    'package_weight_kg' => round(($package['weight_grams'] ?? 0) / 1000, 2),
                    'dimensions_cm' => [
                        'length' => $package['length_cm'] ?? 0,
                        'width' => $package['width_cm'] ?? 0,
                        'height' => $package['height_cm'] ?? 0
                    ],
                    'contents_summary' => $this->generateContentsSummary($package['items'] ?? []),
                    'special_instructions' => $package['special_instructions'] ?? '',
                    'tracking_number' => $package['tracking_number'] ?? '',
                    'carrier' => $package_data['carrier'] ?? 'MANUAL'
                ];
                
                // Generate barcode for package
                $label_data['package_barcode'] = $this->generatePackageBarcode($transfer_id, $package_number);
                
                // Generate QR code with tracking info
                $label_data['qr_code_data'] = json_encode([
                    'transfer_id' => $transfer_id,
                    'package_num' => $package_number,
                    'tracking' => $package['tracking_number'] ?? '',
                    'url' => "https://staff.vapeshed.co.nz/transfers/view.php?id={$transfer_id}"
                ]);
                
                $labels[] = $label_data;
            }
            
            return [
                'success' => true,
                'transfer_id' => $transfer_id,
                'labels' => $labels,
                'print_ready' => true
            ];
            
        } catch (Exception $e) {
            error_log("Box label generation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transfer_id' => $transfer_id
            ];
        }
    }
    
    /**
     * Track package status and update records
     */
    public function updatePackageTracking(int $transfer_id, string $tracking_number, array $tracking_data): array {
        try {
            // Update tracking record
            $stmt = $this->con->prepare("
                UPDATE transfer_packages 
                SET tracking_status = ?, 
                    tracking_data_json = ?, 
                    last_tracking_update = NOW()
                WHERE transfer_id = ? AND tracking_number = ?
            ");
            
            $tracking_status = $tracking_data['status'] ?? 'unknown';
            $tracking_json = json_encode($tracking_data);
            
            $stmt->bind_param('ssis', $tracking_status, $tracking_json, $transfer_id, $tracking_number);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update tracking: " . $stmt->error);
            }
            
            // Log tracking event
            $this->logTrackingEvent($transfer_id, $tracking_number, $tracking_data);
            
            // Store tracking patterns in Neural Brain
            if ($this->neural_brain) {
                $this->neural_brain->storePattern(
                    "Tracking Update",
                    "Package tracking updated for transfer {$transfer_id}: {$tracking_status}",
                    [
                        'tracking_status' => $tracking_status,
                        'carrier' => $tracking_data['carrier'] ?? 'unknown',
                        'delivery_days' => $tracking_data['delivery_days'] ?? null,
                        'update_source' => $tracking_data['source'] ?? 'manual'
                    ],
                    0.7
                );
            }
            
            return [
                'success' => true,
                'transfer_id' => $transfer_id,
                'tracking_number' => $tracking_number,
                'status' => $tracking_status,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Package tracking update error: " . $e->getMessage());
            
            if ($this->neural_brain) {
                $this->neural_brain->storeError(
                    "Package tracking update failed: " . $e->getMessage(),
                    "Check tracking number and carrier integration",
                    "Transfer ID: {$transfer_id}, Tracking: {$tracking_number}"
                );
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'transfer_id' => $transfer_id,
                'tracking_number' => $tracking_number
            ];
        }
    }
    
    // Private helper methods
    
    private function getTransferItemsWithDimensions(int $transfer_id): array {
        $stmt = $this->con->prepare("
            SELECT 
                ti.*,
                p.product_name,
                p.sku,
                COALESCE(cw.avg_weight_grams, 500) as weight_grams_per_unit,
                COALESCE(cw.avg_volume_cm3, 100) as volume_cm3_per_unit
            FROM transfer_items ti
            LEFT JOIN products p ON ti.product_id = p.product_id
            LEFT JOIN category_weights cw ON p.category_code = cw.category_code
            WHERE ti.transfer_id = ? AND ti.quantity > 0
        ");
        
        $stmt->bind_param('i', $transfer_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function calculateTotalStats(array $items): array {
        $total_weight = 0;
        $total_volume = 0;
        $total_items = 0;
        
        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 1;
            $total_weight += ($item['weight_grams_per_unit'] ?? 500) * $quantity;
            $total_volume += ($item['volume_cm3_per_unit'] ?? 100) * $quantity;
            $total_items += $quantity;
        }
        
        return [
            'total_weight' => $total_weight, // in grams
            'total_volume' => $total_volume, // in cm³
            'total_items' => $total_items
        ];
    }
    
    private function selectOptimalCarrier(array $total_stats, array $items): string {
        $scores = [];
        
        foreach (self::CARRIERS as $carrier_code => $carrier_name) {
            $score = 0;
            $limits = self::MAX_DIMENSIONS[$carrier_code];
            
            // Weight score (lower weight = higher score for some carriers)
            if ($total_stats['total_weight'] <= $limits['weight']) {
                $score += 10;
            }
            
            // Volume efficiency score
            $volumetric_weight = $total_stats['total_volume'] / self::VOLUMETRIC_DIVISORS[$carrier_code];
            if ($volumetric_weight < $total_stats['total_weight']) {
                $score += 5; // Actual weight is chargeable (good)
            }
            
            // Carrier-specific bonuses
            switch ($carrier_code) {
                case 'NZPOST':
                    $score += 5; // Reliable tracking
                    break;
                case 'GSS':
                    $score += 3; // Good for heavier items
                    break;
                case 'VAN':
                    if ($total_stats['total_weight'] > 10000) { // > 10kg
                        $score += 7; // Best for heavy items
                    }
                    break;
            }
            
            $scores[$carrier_code] = $score;
        }
        
        return array_search(max($scores), $scores) ?: 'NZPOST';
    }
    
    private function generatePackageRecommendations(array $items, string $carrier, array $total_stats): array {
        $packages = [];
        $limits = self::MAX_DIMENSIONS[$carrier];
        $volumetric_divisor = self::VOLUMETRIC_DIVISORS[$carrier];
        
        // Simple bin-packing algorithm (can be enhanced)
        $current_package = [
            'weight_grams' => 0,
            'volume_cm3' => 0,
            'items' => [],
            'length_cm' => 0,
            'width_cm' => 0,
            'height_cm' => 0
        ];
        
        foreach ($items as $item) {
            $item_weight = ($item['weight_grams_per_unit'] ?? 500) * ($item['quantity'] ?? 1);
            $item_volume = ($item['volume_cm3_per_unit'] ?? 100) * ($item['quantity'] ?? 1);
            
            // Check if item fits in current package
            if (($current_package['weight_grams'] + $item_weight) <= $limits['weight'] &&
                ($current_package['volume_cm3'] + $item_volume) <= ($limits['length'] * $limits['width'] * $limits['height'])) {
                
                // Add to current package
                $current_package['weight_grams'] += $item_weight;
                $current_package['volume_cm3'] += $item_volume;
                $current_package['items'][] = $item;
                
            } else {
                // Start new package
                if (!empty($current_package['items'])) {
                    $packages[] = $this->finalizePackage($current_package, $carrier);
                }
                
                $current_package = [
                    'weight_grams' => $item_weight,
                    'volume_cm3' => $item_volume,
                    'items' => [$item],
                    'length_cm' => 0,
                    'width_cm' => 0,
                    'height_cm' => 0
                ];
            }
        }
        
        // Add final package
        if (!empty($current_package['items'])) {
            $packages[] = $this->finalizePackage($current_package, $carrier);
        }
        
        // Calculate metrics
        $total_volumetric_weight = 0;
        $total_actual_weight = 0;
        
        foreach ($packages as $package) {
            $vol_weight = $package['volume_cm3'] / $volumetric_divisor;
            $total_volumetric_weight += $vol_weight;
            $total_actual_weight += $package['weight_grams'];
        }
        
        $chargeable_weight = max($total_actual_weight, $total_volumetric_weight * 1000);
        $efficiency_score = min(1.0, $total_actual_weight / $chargeable_weight);
        
        return [
            'packages' => $packages,
            'package_count' => count($packages),
            'volumetric_weight' => $total_volumetric_weight * 1000, // Convert to grams
            'chargeable_weight' => $chargeable_weight,
            'efficiency_score' => round($efficiency_score, 2),
            'estimated_cost' => $this->estimateShippingCost($packages, $carrier),
            'recommendations' => $this->generatePackageRecommendations_recommendations($packages, $efficiency_score)
        ];
    }
    
    private function finalizePackage(array $package_data, string $carrier): array {
        // Calculate optimal dimensions based on volume
        $volume = $package_data['volume_cm3'];
        $cube_root = pow($volume, 1/3);
        
        // Use standard box ratios (length:width:height = 1.5:1.2:1)
        $package_data['length_cm'] = round($cube_root * 1.5);
        $package_data['width_cm'] = round($cube_root * 1.2);
        $package_data['height_cm'] = round($cube_root);
        
        // Ensure within carrier limits
        $limits = self::MAX_DIMENSIONS[$carrier];
        $package_data['length_cm'] = min($package_data['length_cm'], $limits['length']);
        $package_data['width_cm'] = min($package_data['width_cm'], $limits['width']);
        $package_data['height_cm'] = min($package_data['height_cm'], $limits['height']);
        
        return $package_data;
    }
    
    private function estimateShippingCost(array $packages, string $carrier): float {
        // Simplified cost estimation (should integrate with actual carrier APIs)
        $base_costs = [
            'NZPOST' => 8.50,
            'GSS' => 7.20,
            'MANUAL' => 0.00,
            'VAN' => 15.00
        ];
        
        $base_cost = $base_costs[$carrier] ?? 10.00;
        $total_cost = 0;
        
        foreach ($packages as $package) {
            $weight_kg = $package['weight_grams'] / 1000;
            $volumetric_kg = $package['volume_cm3'] / self::VOLUMETRIC_DIVISORS[$carrier] / 1000;
            $chargeable_kg = max($weight_kg, $volumetric_kg);
            
            $package_cost = $base_cost + ($chargeable_kg * 2.50); // $2.50 per kg
            $total_cost += $package_cost;
        }
        
        return round($total_cost, 2);
    }
    
    private function generatePackageRecommendations_recommendations(array $packages, float $efficiency_score): array {
        $recommendations = [];
        
        if ($efficiency_score < 0.7) {
            $recommendations[] = "Package efficiency is low ({$efficiency_score}%). Consider consolidating items to reduce volumetric weight charges.";
        }
        
        if (count($packages) > 3) {
            $recommendations[] = "Multiple packages detected. Consider using van delivery for better rates on bulk shipments.";
        }
        
        foreach ($packages as $i => $package) {
            if ($package['weight_grams'] > 25000) { // > 25kg
                $recommendations[] = "Package " . ($i + 1) . " is heavy ({$package['weight_grams']}g). Consider splitting for easier handling.";
            }
        }
        
        return $recommendations;
    }
    
    private function createSingleLabel(int $transfer_id, array $package, array $shipping_details, string $carrier, int $package_number): array {
        // This would integrate with actual carrier APIs
        switch ($carrier) {
            case 'NZPOST':
                return $this->createNZPostLabel($transfer_id, $package, $shipping_details, $package_number);
            case 'GSS':
                return $this->createGSSLabel($transfer_id, $package, $shipping_details, $package_number);
            default:
                return $this->createManualLabel($transfer_id, $package, $shipping_details, $package_number);
        }
    }
    
    private function createNZPostLabel(int $transfer_id, array $package, array $shipping_details, int $package_number): array {
        // Placeholder for NZ Post API integration
        return [
            'success' => true,
            'tracking_number' => 'NZP' . time() . rand(100, 999),
            'label_url' => "https://api.nzpost.co.nz/labels/{$transfer_id}_{$package_number}.pdf",
            'carrier' => 'NZPOST',
            'package_number' => $package_number,
            'estimated_delivery' => date('Y-m-d', strtotime('+3 days'))
        ];
    }
    
    private function createGSSLabel(int $transfer_id, array $package, array $shipping_details, int $package_number): array {
        // Placeholder for GSS API integration
        return [
            'success' => true,
            'tracking_number' => 'GSS' . time() . rand(100, 999),
            'label_url' => "https://api.gss.co.nz/labels/{$transfer_id}_{$package_number}.pdf",
            'carrier' => 'GSS',
            'package_number' => $package_number,
            'estimated_delivery' => date('Y-m-d', strtotime('+2 days'))
        ];
    }
    
    private function createManualLabel(int $transfer_id, array $package, array $shipping_details, int $package_number): array {
        return [
            'success' => true,
            'tracking_number' => 'MAN' . $transfer_id . '-' . $package_number,
            'label_url' => null, // Manual labels generated locally
            'carrier' => 'MANUAL',
            'package_number' => $package_number,
            'estimated_delivery' => $shipping_details['expected_delivery'] ?? date('Y-m-d', strtotime('+5 days'))
        ];
    }
    
    private function validateShippingDetails(array $details): void {
        $required = ['sender_name', 'sender_address', 'recipient_name', 'recipient_address'];
        
        foreach ($required as $field) {
            if (empty($details[$field])) {
                throw new Exception("Required shipping field '{$field}' is missing");
            }
        }
    }
    
    private function storeLabelRecord(int $transfer_id, array $label_result, array $package, string $carrier): void {
        if (!$label_result['success']) return;
        
        $stmt = $this->con->prepare("
            INSERT INTO transfer_packages 
            (transfer_id, package_number, tracking_number, carrier, weight_grams, 
             length_cm, width_cm, height_cm, label_url, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param('iissiiiis',
            $transfer_id,
            $label_result['package_number'],
            $label_result['tracking_number'],
            $carrier,
            $package['weight_grams'],
            $package['length_cm'],
            $package['width_cm'],
            $package['height_cm'],
            $label_result['label_url']
        );
        
        $stmt->execute();
    }
    
    private function getTransferDetails(int $transfer_id): ?array {
        $stmt = $this->con->prepare("
            SELECT t.*, 
                   f.name as from_store_name,
                   to_store.name as to_store_name
            FROM transfers t
            LEFT JOIN vend_outlets f ON t.from_outlet_id = f.id
            LEFT JOIN vend_outlets to_store ON t.to_outlet_id = to_store.id
            WHERE t.transfer_id = ?
        ");
        
        $stmt->bind_param('i', $transfer_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    private function generateContentsSummary(array $items): string {
        if (empty($items)) return 'Mixed items';
        
        $categories = [];
        foreach ($items as $item) {
            $category = $item['category'] ?? 'General';
            $categories[$category] = ($categories[$category] ?? 0) + ($item['quantity'] ?? 1);
        }
        
        $summary_parts = [];
        foreach ($categories as $cat => $qty) {
            $summary_parts[] = "{$qty}x {$cat}";
        }
        
        return implode(', ', array_slice($summary_parts, 0, 3)) . (count($summary_parts) > 3 ? '...' : '');
    }
    
    private function generatePackageBarcode(int $transfer_id, int $package_number): string {
        return 'T' . str_pad($transfer_id, 6, '0', STR_PAD_LEFT) . 'P' . str_pad($package_number, 2, '0', STR_PAD_LEFT);
    }
    
    private function logTrackingEvent(int $transfer_id, string $tracking_number, array $tracking_data): void {
        $stmt = $this->con->prepare("
            INSERT INTO transfer_tracking_events 
            (transfer_id, tracking_number, event_type, event_data_json, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $event_type = $tracking_data['status'] ?? 'status_update';
        $event_json = json_encode($tracking_data);
        
        $stmt->bind_param('isss', $transfer_id, $tracking_number, $event_type, $event_json);
        $stmt->execute();
    }
    
    private function calculatePackageUtilization(array $package): float {
        $max_volume = self::MAX_DIMENSIONS['NZPOST']['length'] * 
                     self::MAX_DIMENSIONS['NZPOST']['width'] * 
                     self::MAX_DIMENSIONS['NZPOST']['height'];
        
        return ($package['volume_cm3'] ?? 0) / $max_volume;
    }
    
    private function isPackageOversized(array $package, string $carrier): bool {
        $limits = self::MAX_DIMENSIONS[$carrier];
        
        return ($package['weight_grams'] ?? 0) > $limits['weight'] ||
               ($package['length_cm'] ?? 0) > $limits['length'] ||
               ($package['width_cm'] ?? 0) > $limits['width'] ||
               ($package['height_cm'] ?? 0) > $limits['height'];
    }
    
    private function estimateConsolidationSavings(array $packages): float {
        // Estimate savings from consolidating packages
        $current_cost = count($packages) * 8.50; // Base cost per package
        $consolidated_cost = 8.50 + (count($packages) - 1) * 2.00; // Additional weight charges
        
        return max(0, $current_cost - $consolidated_cost);
    }
    
    private function getCarrierOptimizationRecommendation(array $package_data): ?array {
        $current_carrier = $package_data['carrier'] ?? 'NZPOST';
        
        // Simple carrier optimization logic
        $total_weight = array_sum(array_column($package_data['packages'], 'weight_grams'));
        
        if ($total_weight > 20000 && $current_carrier !== 'VAN') { // > 20kg
            return [
                'type' => 'carrier_optimization',
                'message' => 'Heavy shipment detected. Van delivery may be more cost-effective.',
                'recommended_carrier' => 'VAN',
                'estimated_savings' => 15.00
            ];
        }
        
        return null;
    }
    
    private function calculateOverallOptimizationScore(array $packages): float {
        if (empty($packages)) return 0.0;
        
        $total_utilization = 0;
        foreach ($packages as $package) {
            $total_utilization += $this->calculatePackageUtilization($package);
        }
        
        return round($total_utilization / count($packages), 2);
    }
}
?>
