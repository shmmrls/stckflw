<?php
/**
 * Store-Supplier Relationship Validation Functions
 * 
 * These functions ensure that stores can only create purchase orders
 * from suppliers they are registered with in the store_suppliers table.
 */

require_once 'config.php';

/**
 * Check if a store is registered with a supplier
 * 
 * @param int $store_id The store ID
 * @param int $supplier_id The supplier ID
 * @return bool True if the relationship exists and is active, false otherwise
 */
function isStoreRegisteredWithSupplier($store_id, $supplier_id) {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as is_valid 
            FROM store_suppliers 
            WHERE store_id = ? 
              AND supplier_id = ? 
              AND is_active = 1
        ");
        
        $stmt->bind_param('ii', $store_id, $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row['is_valid'] > 0;
        
    } catch (Exception $e) {
        error_log("Error checking store-supplier relationship: " . $e->getMessage());
        return false;
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
}

/**
 * Get all valid suppliers for a store
 * 
 * @param int $store_id The store ID
 * @return array Array of supplier information
 */
function getValidSuppliersForStore($store_id) {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                s.supplier_id,
                s.supplier_name,
                s.supplier_type,
                s.contact_person,
                s.contact_number as phone,
                s.email,
                ss.preferred_supplier,
                ss.credit_limit,
                ss.current_balance,
                ss.last_order_date
            FROM store_suppliers ss
            JOIN suppliers s ON ss.supplier_id = s.supplier_id
            WHERE ss.store_id = ? 
              AND ss.is_active = 1
              AND s.is_active = 1
            ORDER BY ss.preferred_supplier DESC, s.supplier_name ASC
        ");
        
        $stmt->bind_param('i', $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting valid suppliers for store: " . $e->getMessage());
        return [];
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
}

/**
 * Get all valid stores for a supplier
 * 
 * @param int $supplier_id The supplier ID
 * @return array Array of store information
 */
function getValidStoresForSupplier($supplier_id) {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                gs.store_id,
                gs.store_name,
                gs.business_address as location,
                gs.contact_number as phone,
                gs.email,
                ss.preferred_supplier,
                ss.credit_limit,
                ss.current_balance,
                ss.last_order_date
            FROM store_suppliers ss
            JOIN grocery_stores gs ON ss.store_id = gs.store_id
            WHERE ss.supplier_id = ? 
              AND ss.is_active = 1
              AND gs.is_active = 1
            ORDER BY gs.store_name ASC
        ");
        
        $stmt->bind_param('i', $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting valid stores for supplier: " . $e->getMessage());
        return [];
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
}

/**
 * Validate purchase order data before creation
 * 
 * @param array $po_data Purchase order data including store_id and supplier_id
 * @return array Validation result with 'valid' boolean and 'message' string
 */
function validatePurchaseOrderData($po_data) {
    $result = ['valid' => true, 'message' => ''];
    
    // Check required fields
    if (!isset($po_data['store_id']) || !isset($po_data['supplier_id'])) {
        $result['valid'] = false;
        $result['message'] = 'Store ID and Supplier ID are required';
        return $result;
    }
    
    $store_id = (int)$po_data['store_id'];
    $supplier_id = (int)$po_data['supplier_id'];
    
    // Validate store-supplier relationship
    if (!isStoreRegisteredWithSupplier($store_id, $supplier_id)) {
        $result['valid'] = false;
        $result['message'] = 'Store is not registered with this supplier or the relationship is not active';
        return $result;
    }
    
    return $result;
}

/**
 * Create a purchase order with validation
 * 
 * @param array $po_data Purchase order data
 * @return array Result with 'success' boolean, 'message' string, and 'po_id' if successful
 */
function createValidatedPurchaseOrder($po_data) {
    $result = ['success' => false, 'message' => '', 'po_id' => null];
    
    // Validate the data first
    $validation = validatePurchaseOrderData($po_data);
    if (!$validation['valid']) {
        $result['message'] = $validation['message'];
        return $result;
    }
    
    $conn = getDBConnection();
    
    try {
        $conn->begin_transaction();
        
        // Generate PO number
        $po_number = generatePONumber();
        
        // Insert the purchase order
        $stmt = $conn->prepare("
            INSERT INTO purchase_orders (
                po_number, store_id, supplier_id, order_date, 
                expected_delivery_date, status, created_by, 
                total_amount, grand_total, notes
            ) VALUES (
                ?, ?, ?, CURDATE(),
                DATE_ADD(CURDATE(), INTERVAL 7 DAY),
                'draft', ?, 0.00, 0.00, ?
            )
        ");
        
        $notes = $po_data['notes'] ?? null;
        $stmt->bind_param('siiss', 
            $po_number,
            $po_data['store_id'],
            $po_data['supplier_id'],
            $po_data['created_by'],
            $notes
        );
        
        $stmt->execute();
        $po_id = $conn->insert_id;
        
        $conn->commit();
        
        $result['success'] = true;
        $result['message'] = 'Purchase order created successfully';
        $result['po_id'] = $po_id;
        $result['po_number'] = $po_number;
        
    } catch (Exception $e) {
        $conn->rollback();
        $result['message'] = 'Database error: ' . $e->getMessage();
        error_log("Error creating purchase order: " . $e->getMessage());
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
    
    return $result;
}

/**
 * Generate a unique purchase order number
 * 
 * @return string The generated PO number
 */
function generatePONumber() {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM purchase_orders 
            WHERE YEAR(order_date) = YEAR(CURDATE())
        ");
        
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        
        return 'PO-' . date('Y') . '-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
        
    } catch (Exception $e) {
        error_log("Error generating PO number: " . $e->getMessage());
        return 'PO-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
}

/**
 * Register a store with a supplier
 * 
 * @param int $store_id The store ID
 * @param int $supplier_id The supplier ID
 * @param array $additional_data Additional data like credit_limit, preferred_supplier, etc.
 * @return array Result with 'success' boolean and 'message' string
 */
function registerStoreWithSupplier($store_id, $supplier_id, $additional_data = []) {
    $result = ['success' => false, 'message' => ''];
    
    $conn = getDBConnection();
    
    try {
        // Check if relationship already exists
        $stmt = $conn->prepare("
            SELECT COUNT(*) as exists_count 
            FROM store_suppliers 
            WHERE store_id = ? AND supplier_id = ?
        ");
        
        $stmt->bind_param('ii', $store_id, $supplier_id);
        $stmt->execute();
        $result_check = $stmt->get_result();
        $exists = $result_check->fetch_assoc()['exists_count'] > 0;
        
        if ($exists) {
            // Update existing relationship
            $stmt = $conn->prepare("
                UPDATE store_suppliers 
                SET is_active = 1,
                    preferred_supplier = ?,
                    credit_limit = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE store_id = ? AND supplier_id = ?
            ");
            
            $preferred = $additional_data['preferred_supplier'] ?? 0;
            $credit_limit = $additional_data['credit_limit'] ?? null;
            $notes = $additional_data['notes'] ?? null;
            
            $stmt->bind_param('idsii', $preferred, $credit_limit, $notes, $store_id, $supplier_id);
            $stmt->execute();
            
            $result['message'] = 'Store-supplier relationship updated successfully';
            
        } else {
            // Create new relationship
            $stmt = $conn->prepare("
                INSERT INTO store_suppliers (
                    store_id, supplier_id, is_active, preferred_supplier, 
                    credit_limit, notes
                ) VALUES (?, ?, 1, ?, ?, ?)
            ");
            
            $preferred = $additional_data['preferred_supplier'] ?? 0;
            $credit_limit = $additional_data['credit_limit'] ?? null;
            $notes = $additional_data['notes'] ?? null;
            
            $stmt->bind_param('iidss', $store_id, $supplier_id, $preferred, $credit_limit, $notes);
            $stmt->execute();
            
            $result['message'] = 'Store registered with supplier successfully';
        }
        
        $result['success'] = true;
        
    } catch (Exception $e) {
        $result['message'] = 'Database error: ' . $e->getMessage();
        error_log("Error registering store with supplier: " . $e->getMessage());
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
    
    return $result;
}

/**
 * Get supplier products available for a specific store
 * 
 * @param int $store_id The store ID
 * @param int $supplier_id The supplier ID
 * @return array Array of available products
 */
function getSupplierProductsForStore($store_id, $supplier_id) {
    $conn = getDBConnection();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                sp.supplier_product_id,
                sp.supplier_sku,
                sp.unit_price,
                sp.minimum_order_quantity,
                sp.lead_time_days,
                sp.is_available,
                pc.product_name,
                pc.description,
                pc.category_id,
                c.category_name
            FROM supplier_products sp
            JOIN product_catalog pc ON sp.catalog_id = pc.catalog_id
            JOIN categories c ON pc.category_id = c.category_id
            WHERE sp.supplier_id = ?
              AND sp.is_available = 1
            ORDER BY pc.product_name ASC
        ");
        
        $stmt->bind_param('i', $supplier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error getting supplier products for store: " . $e->getMessage());
        return [];
    } finally {
        if (isset($stmt)) $stmt->close();
        $conn->close();
    }
}

/**
 * Display validation error in user-friendly format
 * 
 * @param string $error_message The error message
 * @return string HTML-formatted error message
 */
function displayValidationError($error_message) {
    return "
    <div class='alert alert-danger alert-dismissible fade show' role='alert'>
        <strong>Validation Error:</strong> {$error_message}
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>
    ";
}

/**
 * Display success message in user-friendly format
 * 
 * @param string $success_message The success message
 * @return string HTML-formatted success message
 */
function displaySuccessMessage($success_message) {
    return "
    <div class='alert alert-success alert-dismissible fade show' role='alert'>
        <strong>Success:</strong> {$success_message}
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>
    ";
}
?>
