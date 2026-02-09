<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

// Verify user is grocery admin
if ($_SESSION['role'] !== 'grocery_admin') {
    header('Location: ' . $GLOBALS['baseUrl'] . '/user/customer/dashboard.php');
    exit();
}

$conn = getDBConnection();
$user_id = getCurrentUserId();

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;

// Fetch supplier details
$supplier = null;
if ($supplier_id > 0) {
    $query = "SELECT * FROM suppliers WHERE supplier_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $supplier = $result->fetch_assoc();
    
    if (!$supplier) {
        header("Location: ../suppliers/view_suppliers.php");
        exit();
    }
}

$errors = [];
$success = '';
$import_results = [];

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension !== 'csv') {
            $errors[] = "Only CSV files are allowed.";
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            
            if ($handle !== false) {
                $header = fgetcsv($handle); // Read header row
                $row_number = 1;
                $imported = 0;
                $failed = 0;
                
                // Validate header
                $expected_headers = ['product_name', 'brand', 'category', 'supplier_sku', 'unit_price', 'unit_size', 'minimum_order_quantity', 'lead_time_days'];
                
                while (($data = fgetcsv($handle)) !== false) {
                    $row_number++;
                    
                    try {
                        // Map CSV columns
                        $product_name = trim($data[0]);
                        $brand = trim($data[1]);
                        $category_name = trim($data[2]);
                        $supplier_sku = trim($data[3]);
                        $unit_price = floatval($data[4]);
                        $unit_size = trim($data[5]);
                        $minimum_order_quantity = intval($data[6]);
                        $lead_time_days = !empty($data[7]) ? intval($data[7]) : null;
                        
                        // Validate required fields
                        if (empty($product_name) || $unit_price <= 0 || $minimum_order_quantity < 1) {
                            $import_results[] = [
                                'row' => $row_number,
                                'status' => 'failed',
                                'product' => $product_name,
                                'message' => 'Missing required fields or invalid values'
                            ];
                            $failed++;
                            continue;
                        }
                        
                        // Find category ID
                        $category_id = null;
                        if (!empty($category_name)) {
                            $query = "SELECT category_id FROM categories WHERE category_name = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("s", $category_name);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $category = $result->fetch_assoc();
                            if ($category) {
                                $category_id = $category['category_id'];
                            }
                        }
                        
                        // Insert product
                        $query = "INSERT INTO supplier_products 
                                 (supplier_id, supplier_sku, product_name, brand, category_id, 
                                  unit_price, unit_size, minimum_order_quantity, lead_time_days, 
                                  is_available, last_price_update) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE())";
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("issssidis", $supplier_id, $supplier_sku, $product_name, $brand, $category_id, $unit_price, $unit_size, $minimum_order_quantity, $lead_time_days);
                        $stmt->execute();
                        
                        $import_results[] = [
                            'row' => $row_number,
                            'status' => 'success',
                            'product' => $product_name,
                            'message' => 'Imported successfully'
                        ];
                        $imported++;
                        
                    } catch (Exception $e) {
                        $import_results[] = [
                            'row' => $row_number,
                            'status' => 'failed',
                            'product' => $product_name ?? 'Unknown',
                            'message' => $e->getMessage()
                        ];
                        $failed++;
                    }
                }
                
                fclose($handle);
                
                $success = "Import completed: {$imported} products imported, {$failed} failed.";
                
            } else {
                $errors[] = "Unable to read the CSV file.";
            }
        }
    } else {
        $errors[] = "File upload error: " . $file['error'];
    }
}

// Set page title for header
$pageTitle = "Bulk Import Supplier Products - StockFlow";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../includes/style/pages/supplier_products1.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h2>Bulk Import Supplier Products</h2>
                <?php if ($supplier): ?>
                <p class="text-muted mb-0">Importing products for: <strong><?php echo htmlspecialchars($supplier['supplier_name']); ?></strong></p>
                <?php endif; ?>
            </div>
            <a href="view_supplier_products.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Products
            </a>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> 
            <span><?php echo htmlspecialchars($success); ?></span>
        </div>
        <?php endif; ?>

        <!-- Import Results -->
        <?php if (!empty($import_results)): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-list-check"></i> Import Results</h5>
            </div>
            <div class="card-body">
                <div class="import-results">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Row</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($import_results as $result): ?>
                            <tr>
                                <td><?php echo $result['row']; ?></td>
                                <td><?php echo htmlspecialchars($result['product']); ?></td>
                                <td>
                                    <?php if ($result['status'] === 'success'): ?>
                                        <span class="badge bg-success">Success</span>
                                    <?php else: ?>
                                        <span class="badge badge-unavailable">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($result['message']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CSV Template -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-file-earmark-spreadsheet"></i> CSV Template</h5>
            </div>
            <div class="card-body">
                <p style="margin-bottom: 20px; color: rgba(0,0,0,0.7);">Download and fill in the CSV template with your product information:</p>
                
                <div class="csv-template">
                    <h6>Required Columns:</h6>
                    <ol>
                        <li><strong>product_name</strong> - Name of the product (required)</li>
                        <li><strong>brand</strong> - Brand name (optional)</li>
                        <li><strong>category</strong> - Category name (must match existing categories)</li>
                        <li><strong>supplier_sku</strong> - Your product SKU/code (optional)</li>
                        <li><strong>unit_price</strong> - Price per unit in PHP (required, must be > 0)</li>
                        <li><strong>unit_size</strong> - Size/quantity per unit (e.g., "1L", "500g", "12pcs")</li>
                        <li><strong>minimum_order_quantity</strong> - Minimum order quantity (required, must be >= 1)</li>
                        <li><strong>lead_time_days</strong> - Delivery lead time in days (optional)</li>
                    </ol>
                </div>

                <a href="download_template.php" class="btn btn-success">
                    <i class="bi bi-download"></i> Download CSV Template
                </a>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>Sample CSV format:</strong>
                        <pre>product_name,brand,category,supplier_sku,unit_price,unit_size,minimum_order_quantity,lead_time_days
Alaska Evaporated Milk,Alaska,Dairy,ALASKA-EVAP-370,42.50,370ml can,24,2
Bear Brand Milk,Bear Brand,Dairy,NESTLE-BEAR-300,38.00,300ml can,24,3</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-upload"></i> Upload CSV File</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File</label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <small class="text-muted">Maximum file size: 5MB</small>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div>
                            <strong>Important:</strong>
                            <ul style="margin: 8px 0 0 0; padding-left: 20px;">
                                <li>Ensure your CSV file follows the template format exactly</li>
                                <li>Category names must match existing categories in the system</li>
                                <li>All prices should be in Philippine Peso (₱)</li>
                                <li>Product names should be unique within this supplier</li>
                            </ul>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload"></i> Upload and Import
                        </button>
                        <a href="view_supplier_products.php?supplier_id=<?php echo $supplier_id; ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>