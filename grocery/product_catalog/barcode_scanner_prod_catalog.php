<?php
require_once __DIR__ . '/../../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

// Get user's role
$user_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data['role'] !== 'grocery_admin') {
    die("Access denied. Only grocery admins can access this page.");
}

// Handle barcode lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    $barcode = trim($_POST['barcode']);
    
    // Look up in product catalog
    $stmt = $conn->prepare("
        SELECT pc.*, c.category_name 
        FROM product_catalog pc
        LEFT JOIN categories c ON pc.category_id = c.category_id
        WHERE pc.barcode = ?
    ");
    $stmt->bind_param("s", $barcode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        
        // Log scan history
        $log_stmt = $conn->prepare("
            INSERT INTO barcode_scan_history (user_id, barcode, scan_type, product_found) 
            VALUES (?, ?, 'lookup', 1)
        ");
        $log_stmt->bind_param("is", $user_id, $barcode);
        $log_stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'product' => $product,
            'exists' => true
        ]);
    } else {
        // Log failed scan
        $log_stmt = $conn->prepare("
            INSERT INTO barcode_scan_history (user_id, barcode, scan_type, product_found) 
            VALUES (?, ?, 'lookup', 0)
        ");
        $log_stmt->bind_param("is", $user_id, $barcode);
        $log_stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'exists' => false,
            'message' => 'Product not found in catalog. You can add it as a new product.'
        ]);
    }
    exit();
}

// Handle adding new product to catalog
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $barcode = trim($_POST['barcode']);
    $product_name = trim($_POST['product_name']);
    $brand = trim($_POST['brand']);
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $default_unit = trim($_POST['default_unit']);
    $shelf_life = !empty($_POST['shelf_life']) ? intval($_POST['shelf_life']) : null;
    $description = trim($_POST['description']);
    
    // Insert into product catalog
    $insert_stmt = $conn->prepare("
        INSERT INTO product_catalog 
        (barcode, product_name, brand, category_id, default_unit, typical_shelf_life_days, description, is_verified) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $insert_stmt->bind_param("sssisss", $barcode, $product_name, $brand, $category_id, $default_unit, $shelf_life, $description);
    
    if ($insert_stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Product added to catalog successfully!',
            'catalog_id' => $insert_stmt->insert_id
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error adding product to catalog: ' . $conn->error
        ]);
    }
    exit();
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/barcode.css">';
require_once __DIR__ . '/../../includes/header.php';

// Get categories for the form
$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js"></script>

<main class="scanner-page">
    <div class="scanner-container">
        
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Product Catalog Scanner</h1>
                    <p class="page-subtitle">Scan and manage products in the master catalog</p>
                </div>
                <div class="header-actions">
                    <a href="view_product_catalog.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Catalog
                    </a>
                </div>
            </div>
        </div>

        <div id="scanner-result" class="alert alert-success" style="display: none;"></div>
        <div id="scanner-error" class="alert alert-error" style="display: none;"></div>

        <div class="scanner-section">
            <div class="section-header">
                <h2 class="section-title">Camera Scanner</h2>
                <p class="section-description">Point your camera at a product barcode</p>
            </div>

            <div id="scanner-wrapper">
                <video id="camera-feed" playsinline></video>
                <canvas id="capture-canvas"></canvas>
                <div class="scanner-overlay">
                    <div class="scanner-line"></div>
                </div>
                <div class="scan-status" id="scan-result">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    Scanning...
                </div>
            </div>

            <div class="scanner-controls">
                <button id="start-scan" class="btn-primary">
                    Start Camera
                </button>
                <button id="stop-scan" class="btn-danger" style="display: none;">
                    Stop Camera
                </button>
                <button id="switch-camera" class="btn-switch" style="display: none;">
                    Switch Camera
                </button>
            </div>
        </div>

        <div class="manual-section">
            <div class="section-header">
                <h2 class="section-title">Manual Entry</h2>
                <p class="section-description">Enter barcode number directly</p>
            </div>

            <form id="manual-form" class="manual-form">
                <div class="form-group">
                    <label class="form-label">Barcode Number</label>
                    <div class="barcode-input-wrapper">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <input type="text" 
                               id="manual-barcode" 
                               class="barcode-input"
                               placeholder="Enter barcode number" 
                               autocomplete="off">
                    </div>
                </div>
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    Look Up Product
                </button>
            </form>
        </div>

        <div id="product-result" class="product-result" style="display: none;">
            <div class="result-header">
                <div class="success-icon" id="result-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="result-title" id="result-title">Product Found!</div>
            </div>

            <div class="product-details" id="existing-product-details">
                <div class="detail-row">
                    <span class="detail-label">Product Name</span>
                    <span class="detail-value" id="product-name"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Brand</span>
                    <span class="detail-value" id="product-brand"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Category</span>
                    <span class="detail-value" id="product-category"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Default Unit</span>
                    <span class="detail-value" id="product-unit"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Shelf Life</span>
                    <span class="detail-value" id="product-shelf-life"></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Barcode</span>
                    <span class="detail-value barcode-value" id="product-barcode"></span>
                </div>
            </div>

            <form id="add-product-form" style="display: none;">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Barcode</label>
                        <input type="text" id="new-barcode" class="form-input" readonly>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Product Name*</label>
                        <input type="text" id="new-product-name" class="form-input" required placeholder="Enter product name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <input type="text" id="new-brand" class="form-input" placeholder="Enter brand name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select id="new-category" class="form-select">
                            <option value="">Select Category</option>
                            <?php while ($cat = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Unit*</label>
                        <select id="new-unit" class="form-select" required>
                            <option value="pcs">Pieces</option>
                            <option value="kg">Kilograms</option>
                            <option value="g">Grams</option>
                            <option value="L">Liters</option>
                            <option value="mL">Milliliters</option>
                            <option value="box">Box</option>
                            <option value="pack">Pack</option>
                            <option value="can">Can</option>
                            <option value="bottle">Bottle</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Typical Shelf Life (days)</label>
                        <input type="number" id="new-shelf-life" class="form-input" min="0" placeholder="e.g., 365">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Description</label>
                        <textarea id="new-description" class="form-textarea" rows="3" placeholder="Enter product description (optional)"></textarea>
                    </div>
                </div>
            </form>

            <div class="result-actions">
                <button id="add-to-catalog" class="btn-success" style="display: none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add to Catalog
                </button>
                <button id="scan-another" class="btn-secondary-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>
                    </svg>
                    Scan Another
                </button>
            </div>
        </div>

    </div>
</main>

<script>
    let codeReader;
    let currentDeviceId = null;
    let currentBarcode = null;
    let facingMode = 'environment';
    let isScanning = false;
    
    function initBarcodeReader() {
        if (typeof ZXing !== 'undefined') {
            codeReader = new ZXing.BrowserMultiFormatReader();
            console.log('ZXing code reader initialized');
        } else {
            console.error('ZXing library not loaded');
            showError('Barcode scanner library failed to load. Please refresh the page.');
        }
    }
    
    async function checkCameraAvailability() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
                console.error('MediaDevices API not supported');
                return false;
            }
            
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === 'videoinput');
            return videoDevices.length > 0;
        } catch (error) {
            console.error('Error checking camera availability:', error);
            return false;
        }
    }
    
    async function getDeviceId() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach(track => track.stop());
            
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === 'videoinput');
            
            if (videoDevices.length === 0) {
                throw new Error('No video input devices found');
            }
            
            for (const device of videoDevices) {
                try {
                    const capabilities = device.getCapabilities ? device.getCapabilities() : {};
                    if (capabilities.facingMode && capabilities.facingMode.includes(facingMode)) {
                        return device.deviceId;
                    }
                } catch (e) {
                    console.log('Could not get capabilities for device:', device.label);
                }
            }
            
            if (facingMode === 'environment' && videoDevices.length > 1) {
                return videoDevices[videoDevices.length - 1].deviceId;
            }
            
            return videoDevices[0].deviceId;
        } catch (error) {
            console.error('Error getting device ID:', error);
            throw error;
        }
    }

    async function startCamera() {
        try {
            const cameraAvailable = await checkCameraAvailability();
            
            if (!cameraAvailable) {
                showError('No camera detected. Please connect a camera or use manual entry below.');
                return;
            }
            
            if (!codeReader) {
                initBarcodeReader();
            }
            
            if (!codeReader) {
                showError('Failed to initialize barcode scanner. Please refresh the page.');
                return;
            }
            
            try {
                currentDeviceId = await getDeviceId();
            } catch (permissionError) {
                console.error('Permission error:', permissionError);
                if (permissionError.name === 'NotAllowedError') {
                    showError('Camera access denied. Please enable camera permissions in your browser settings.');
                } else if (permissionError.name === 'NotFoundError') {
                    showError('No camera found. Please connect a camera or use manual entry below.');
                } else {
                    showError('Unable to access camera. Please check your camera connection and permissions.');
                }
                return;
            }
            
            if (!currentDeviceId) {
                showError('No camera available. Please ensure a camera is connected and permissions are granted.');
                return;
            }
            
            document.getElementById('start-scan').style.display = 'none';
            document.getElementById('stop-scan').style.display = 'inline-block';
            document.getElementById('switch-camera').style.display = 'inline-block';
            
            startScanning();
            
        } catch (error) {
            console.error('Error accessing camera:', error);
            showError('Camera not available. Please use manual entry below or check your camera connection.');
        }
    }
    
    function stopCamera() {
        isScanning = false;
        
        if (codeReader) {
            codeReader.reset();
        }
        
        const videoElement = document.getElementById('camera-feed');
        if (videoElement.srcObject) {
            const tracks = videoElement.srcObject.getTracks();
            tracks.forEach(track => track.stop());
        }
        videoElement.pause();
        videoElement.srcObject = null;
        videoElement.load();
        
        document.getElementById('start-scan').style.display = 'inline-block';
        document.getElementById('stop-scan').style.display = 'none';
        document.getElementById('switch-camera').style.display = 'none';
        document.getElementById('scan-result').style.display = 'none';
    }
    
    async function switchCamera() {
        if (!codeReader) {
            console.error('Code reader not initialized');
            return;
        }
        
        stopCamera();
        facingMode = facingMode === 'environment' ? 'user' : 'environment';
        
        setTimeout(async () => {
            try {
                currentDeviceId = await getDeviceId();
                document.getElementById('start-scan').style.display = 'none';
                document.getElementById('stop-scan').style.display = 'inline-block';
                document.getElementById('switch-camera').style.display = 'inline-block';
                startScanning();
            } catch (error) {
                console.error('Error switching camera:', error);
                showError('Failed to switch camera. The requested camera may not be available.');
                document.getElementById('start-scan').style.display = 'inline-block';
                document.getElementById('stop-scan').style.display = 'none';
                document.getElementById('switch-camera').style.display = 'none';
            }
        }, 100);
    }
    
    function startScanning() {
        if (!codeReader) {
            console.error('Code reader not initialized');
            showError('Barcode scanner not ready. Please refresh the page.');
            return;
        }
        
        if (isScanning) {
            console.log('Already scanning, skipping');
            return;
        }
        
        isScanning = true;
        const scanResultEl = document.getElementById('scan-result');
        scanResultEl.style.display = 'flex';
        scanResultEl.className = 'scan-status';
        scanResultEl.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            Scanning...
        `;
        
        const videoElement = document.getElementById('camera-feed');
        videoElement.pause();
        
        codeReader.decodeFromVideoDevice(currentDeviceId, videoElement, (result, err) => {
            if (result) {
                const scanResultEl = document.getElementById('scan-result');
                scanResultEl.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Barcode Detected!
                `;
                scanResultEl.className = 'scan-status success';
                isScanning = false;
                
                codeReader.reset();
                const videoElement = document.getElementById('camera-feed');
                videoElement.pause();
                
                lookupBarcode(result.text);
            }
            if (err && !(err instanceof ZXing.NotFoundException)) {
                console.error('Scanning error:', err);
            }
        }).catch(error => {
            console.error('Failed to start scanning:', error);
            isScanning = false;
            stopCamera();
            
            if (error.name === 'NotAllowedError') {
                showError('Camera access denied. Please enable camera permissions in your browser settings.');
            } else if (error.name === 'NotFoundError') {
                showError('No camera found. Please connect a camera or use manual entry below.');
            } else if (error.name === 'NotReadableError') {
                showError('Camera is already in use by another application. Please close other apps using the camera.');
            } else if (error.name === 'OverconstrainedError') {
                showError('Unable to use the requested camera. Try switching cameras or use manual entry.');
            } else {
                showError('Failed to start camera. Please use manual entry below.');
            }
        });
    }
    
    function lookupBarcode(barcode) {
        currentBarcode = barcode;
        
        fetch('barcode_scanner_prod_catalog.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'barcode=' + encodeURIComponent(barcode)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.exists) {
                displayExistingProduct(data.product, barcode);
                showSuccess('Product already exists in catalog (Barcode: ' + barcode + ')');
                stopCamera();
            } else {
                displayNewProductForm(barcode);
                showError('Product not found. Please add it to the catalog.');
                stopCamera();
            }
        })
        .catch(error => {
            showError('Error looking up product');
            console.error(error);
        });
    }
    
    function displayExistingProduct(product, barcode) {
        document.getElementById('result-title').textContent = 'Product Already in Catalog';
        document.getElementById('result-icon').innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
        `;
        
        document.getElementById('product-name').textContent = product.product_name;
        document.getElementById('product-brand').textContent = product.brand || 'N/A';
        document.getElementById('product-category').textContent = product.category_name || 'N/A';
        document.getElementById('product-unit').textContent = product.default_unit;
        document.getElementById('product-shelf-life').textContent = product.typical_shelf_life_days ? product.typical_shelf_life_days + ' days' : 'N/A';
        document.getElementById('product-barcode').textContent = barcode;
        
        document.getElementById('existing-product-details').style.display = 'block';
        document.getElementById('add-product-form').style.display = 'none';
        document.getElementById('add-to-catalog').style.display = 'none';
        document.getElementById('product-result').style.display = 'block';
        
        document.getElementById('product-result').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    function displayNewProductForm(barcode) {
        document.getElementById('result-title').textContent = 'Add New Product to Catalog';
        document.getElementById('result-icon').innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
        `;
        
        document.getElementById('new-barcode').value = barcode;
        document.getElementById('new-product-name').value = '';
        document.getElementById('new-brand').value = '';
        document.getElementById('new-category').value = '';
        document.getElementById('new-unit').value = 'pcs';
        document.getElementById('new-shelf-life').value = '';
        document.getElementById('new-description').value = '';
        
        document.getElementById('existing-product-details').style.display = 'none';
        document.getElementById('add-product-form').style.display = 'block';
        document.getElementById('add-to-catalog').style.display = 'inline-block';
        document.getElementById('product-result').style.display = 'block';
        
        document.getElementById('product-result').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    function showSuccess(message) {
        const el = document.getElementById('scanner-result');
        el.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            ${message}
        `;
        el.style.display = 'flex';
        document.getElementById('scanner-error').style.display = 'none';
    }
    
    function showError(message) {
        const el = document.getElementById('scanner-error');
        el.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            ${message}
        `;
        el.style.display = 'flex';
        document.getElementById('scanner-result').style.display = 'none';
    }
    
    document.getElementById('start-scan').addEventListener('click', startCamera);
    document.getElementById('stop-scan').addEventListener('click', stopCamera);
    document.getElementById('switch-camera').addEventListener('click', switchCamera);
    
    document.getElementById('manual-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const barcode = document.getElementById('manual-barcode').value.trim();
        if (barcode) {
            lookupBarcode(barcode);
        }
    });
    
    document.getElementById('add-to-catalog').addEventListener('click', function() {
        const productName = document.getElementById('new-product-name').value.trim();
        
        if (!productName) {
            alert('Please enter a product name');
            return;
        }
        
        const formData = new URLSearchParams({
            add_product: '1',
            barcode: currentBarcode,
            product_name: productName,
            brand: document.getElementById('new-brand').value.trim(),
            category_id: document.getElementById('new-category').value,
            default_unit: document.getElementById('new-unit').value,
            shelf_life: document.getElementById('new-shelf-life').value,
            description: document.getElementById('new-description').value.trim()
        });
        
        fetch('barcode_scanner_prod_catalog.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSuccess(data.message);
                setTimeout(() => {
                    document.getElementById('product-result').style.display = 'none';
                    document.getElementById('scanner-result').style.display = 'none';
                    currentBarcode = null;
                    startCamera();
                }, 2000);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            showError('Error adding product to catalog');
            console.error(error);
        });
    });
    
    document.getElementById('scan-another').addEventListener('click', function() {
        document.getElementById('product-result').style.display = 'none';
        document.getElementById('scanner-result').style.display = 'none';
        document.getElementById('scanner-error').style.display = 'none';
        currentBarcode = null;
        startCamera();
    });
    
    window.addEventListener('beforeunload', () => {
        stopCamera();
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php $conn->close(); ?>