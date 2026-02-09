<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

// Verify user is grocery admin
if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $baseUrl . '/user/customer/dashboard.php');
    exit();
}

$conn = getDBConnection();

$supplier_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get supplier details
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();

if (!$supplier) {
    header('Location: view_suppliers.php');
    exit();
}

// Get supplier performance metrics
$perf_stmt = $conn->prepare("
    SELECT * FROM v_supplier_performance 
    WHERE supplier_id = ?
");
$perf_stmt->bind_param("i", $supplier_id);
$perf_stmt->execute();
$performance = $perf_stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_name = trim($_POST['supplier_name']);
    $supplier_type = $_POST['supplier_type'];
    $company_name = trim($_POST['company_name']);
    $contact_person = trim($_POST['contact_person']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $payment_terms = $_POST['payment_terms'];
    $minimum_order_amount = !empty($_POST['minimum_order_amount']) ? $_POST['minimum_order_amount'] : null;
    $delivery_schedule = trim($_POST['delivery_schedule']);
    $tin_number = trim($_POST['tin_number']);
    $rating = !empty($_POST['rating']) ? $_POST['rating'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $notes = trim($_POST['notes']);
    
    // Validate
    if (empty($supplier_name) || empty($supplier_type)) {
        $error = "Supplier name and type are required.";
    } else {
        // Update supplier
        $update_stmt = $conn->prepare("
            UPDATE suppliers 
            SET supplier_name = ?, supplier_type = ?, company_name = ?, contact_person = ?,
                contact_number = ?, email = ?, address = ?, payment_terms = ?,
                minimum_order_amount = ?, delivery_schedule = ?, tin_number = ?,
                rating = ?, is_active = ?, notes = ?
            WHERE supplier_id = ?
        ");
        $update_stmt->bind_param(
            "ssssssssdsssdii",
            $supplier_name, $supplier_type, $company_name, $contact_person,
            $contact_number, $email, $address, $payment_terms, $minimum_order_amount,
            $delivery_schedule, $tin_number, $rating, $is_active, $notes, $supplier_id
        );
        
        if ($update_stmt->execute()) {
            $success = "Supplier updated successfully!";
            // Refresh supplier data
            $stmt->execute();
            $supplier = $stmt->get_result()->fetch_assoc();
            // Refresh performance data
            $perf_stmt->execute();
            $performance = $perf_stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update supplier. Please try again.";
        }
    }
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/suppliers.css">';
require_once __DIR__ . '/../../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="suppliers-page">
    <div class="suppliers-container form-container">
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Edit Supplier</h1>
                    <p class="page-subtitle">Update supplier information and performance</p>
                </div>
                <div class="header-actions">
                    <a href="view_suppliers.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Suppliers
                    </a>
                </div>
            </div>
        </div>

        <!-- Performance Metrics (if available) -->
        <?php if ($performance && $performance['total_purchase_orders'] > 0): ?>
        <div class="performance-section">
            <div class="section-header">
                <h2 class="section-title">Performance Metrics</h2>
                <p class="section-description">Historical supplier performance data</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?php echo number_format($performance['total_purchase_orders']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Items Supplied</div>
                    <div class="stat-value"><?php echo number_format($performance['total_items_supplied']); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Purchase Value</div>
                    <div class="stat-value">₱<?php echo number_format($performance['total_purchase_value'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">On-Time Deliveries</div>
                    <div class="stat-value">
                        <?php 
                        $total = $performance['on_time_deliveries'] + $performance['late_deliveries'];
                        $percentage = $total > 0 ? ($performance['on_time_deliveries'] / $total * 100) : 0;
                        echo number_format($percentage, 1) . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" class="supplier-form">
            
            <!-- Basic Information -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Basic Information</h2>
                    <p class="section-description">Essential supplier details</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Supplier Name *</label>
                        <input type="text" name="supplier_name" class="form-input" 
                               placeholder="Enter supplier name" 
                               value="<?php echo htmlspecialchars($supplier['supplier_name']); ?>" required>
                        <small class="form-hint">Official business or vendor name</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Supplier Type *</label>
                        <select name="supplier_type" class="form-select" required>
                            <option value="">Select type</option>
                            <option value="manufacturer" <?php echo ($supplier['supplier_type'] === 'manufacturer') ? 'selected' : ''; ?>>Manufacturer</option>
                            <option value="distributor" <?php echo ($supplier['supplier_type'] === 'distributor') ? 'selected' : ''; ?>>Distributor</option>
                            <option value="wholesaler" <?php echo ($supplier['supplier_type'] === 'wholesaler') ? 'selected' : ''; ?>>Wholesaler</option>
                            <option value="local_supplier" <?php echo ($supplier['supplier_type'] === 'local_supplier') ? 'selected' : ''; ?>>Local Supplier</option>
                        </select>
                        <small class="form-hint">Classification of supplier</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-input" 
                               placeholder="Legal company name" 
                               value="<?php echo htmlspecialchars($supplier['company_name'] ?? ''); ?>">
                        <small class="form-hint">Official registered company name</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Supplier Rating</label>
                        <input type="number" name="rating" class="form-input" 
                               step="0.1" min="1" max="5" 
                               placeholder="1.0 - 5.0" 
                               value="<?php echo htmlspecialchars($supplier['rating'] ?? ''); ?>">
                        <small class="form-hint">Performance rating (1-5 scale)</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                            <input type="checkbox" name="is_active" id="is_active" 
                                   <?php echo $supplier['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" style="margin: 0; font-weight: normal;">Active Supplier</label>
                        </div>
                        <small class="form-hint">Uncheck to deactivate supplier</small>
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Contact Information</h2>
                    <p class="section-description">How to reach this supplier</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-input" 
                               placeholder="Primary contact name" 
                               value="<?php echo htmlspecialchars($supplier['contact_person'] ?? ''); ?>">
                        <small class="form-hint">Main point of contact</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <div class="input-with-icon">
                            <span class="input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                            </span>
                            <input type="text" name="contact_number" class="form-input with-icon" 
                                   placeholder="+63 912 345 6789" 
                                   value="<?php echo htmlspecialchars($supplier['contact_number'] ?? ''); ?>">
                        </div>
                        <small class="form-hint">Phone number for orders and inquiries</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-with-icon">
                            <span class="input-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                </svg>
                            </span>
                            <input type="email" name="email" class="form-input with-icon" 
                                   placeholder="supplier@example.com" 
                                   value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
                        </div>
                        <small class="form-hint">Email for orders and communications</small>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Business Address</label>
                        <textarea name="address" class="form-input" rows="3" 
                                  placeholder="Complete business address"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                        <small class="form-hint">Physical location of the supplier</small>
                    </div>
                </div>
            </div>

            <!-- Business Terms -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Business Terms</h2>
                    <p class="section-description">Payment and ordering conditions</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Payment Terms</label>
                        <select name="payment_terms" class="form-select">
                            <option value="Cash on Delivery" <?php echo ($supplier['payment_terms'] === 'Cash on Delivery') ? 'selected' : ''; ?>>Cash on Delivery (COD)</option>
                            <option value="Net 7" <?php echo ($supplier['payment_terms'] === 'Net 7') ? 'selected' : ''; ?>>Net 7 Days</option>
                            <option value="Net 15" <?php echo ($supplier['payment_terms'] === 'Net 15') ? 'selected' : ''; ?>>Net 15 Days</option>
                            <option value="Net 30" <?php echo ($supplier['payment_terms'] === 'Net 30') ? 'selected' : ''; ?>>Net 30 Days</option>
                            <option value="Net 45" <?php echo ($supplier['payment_terms'] === 'Net 45') ? 'selected' : ''; ?>>Net 45 Days</option>
                            <option value="Net 60" <?php echo ($supplier['payment_terms'] === 'Net 60') ? 'selected' : ''; ?>>Net 60 Days</option>
                            <option value="Advance Payment" <?php echo ($supplier['payment_terms'] === 'Advance Payment') ? 'selected' : ''; ?>>Advance Payment</option>
                        </select>
                        <small class="form-hint">Payment schedule agreement</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Minimum Order Amount</label>
                        <div class="input-with-icon">
                            <span class="input-icon">₱</span>
                            <input type="number" name="minimum_order_amount" class="form-input with-icon" 
                                   step="0.01" min="0" placeholder="0.00" 
                                   value="<?php echo htmlspecialchars($supplier['minimum_order_amount'] ?? ''); ?>">
                        </div>
                        <small class="form-hint">Minimum order value required</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Delivery Schedule</label>
                        <input type="text" name="delivery_schedule" class="form-input" 
                               placeholder="e.g., Mon/Wed/Fri, Daily, Weekly" 
                               value="<?php echo htmlspecialchars($supplier['delivery_schedule'] ?? ''); ?>">
                        <small class="form-hint">Regular delivery days or frequency</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">TIN Number</label>
                        <input type="text" name="tin_number" class="form-input" 
                               placeholder="Tax Identification Number" 
                               value="<?php echo htmlspecialchars($supplier['tin_number'] ?? ''); ?>">
                        <small class="form-hint">For tax and billing purposes</small>
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Additional Notes</h2>
                    <p class="section-description">Any other important information</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-input" rows="4" 
                                  placeholder="Special requirements, preferences, or other relevant information"><?php echo htmlspecialchars($supplier['notes'] ?? ''); ?></textarea>
                        <small class="form-hint">Internal notes about this supplier</small>
                    </div>
                </div>
            </div>

            <!-- Metadata -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Metadata</h2>
                    <p class="section-description">System information</p>
                </div>
                
                <div class="metadata-grid">
                    <div class="metadata-item">
                        <div class="metadata-label">Supplier ID</div>
                        <div class="metadata-value"><?php echo $supplier['supplier_id']; ?></div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">Created</div>
                        <div class="metadata-value"><?php echo date('F d, Y \a\t g:i A', strtotime($supplier['created_at'])); ?></div>
                    </div>
                    <div class="metadata-item">
                        <div class="metadata-label">Last Updated</div>
                        <div class="metadata-value"><?php echo date('F d, Y \a\t g:i A', strtotime($supplier['updated_at'])); ?></div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Update Supplier
                </button>
                <a href="view_suppliers.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<style>
.performance-section {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 20px;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}
.stat-card {
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 20px;
    text-align: center;
}
.stat-label {
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.6);
    margin-bottom: 10px;
}
.stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #0a0a0a;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>