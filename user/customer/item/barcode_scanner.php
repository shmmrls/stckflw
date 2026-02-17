<?php
require_once __DIR__ . '/../../../includes/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = getCurrentUserId();

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
            'product' => $product
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
            'message' => 'Product not found in catalog'
        ]);
    }
    exit();
}

$pageCss = '<link rel="stylesheet" href="' . htmlspecialchars($baseUrl) . '/includes/style/pages/barcode.css">';
require_once __DIR__ . '/../../../includes/header.php';
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js"></script>

<main class="scanner-page">
    <div class="scanner-container">
        
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Barcode Scanner</h1>
                    <p class="page-subtitle">Scan products to quickly add them to your inventory</p>
                </div>
                <div class="header-actions">
                    <a href="add_item.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Add Item
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
                <div class="success-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="result-title">Product Found!</div>
            </div>

            <div class="product-details">
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

            <div class="result-actions">
                <button id="add-to-inventory" class="btn-success">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add to Inventory
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
    let currentProduct = null;
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
    
    // Check if camera is available
    async function checkCameraAvailability() {
        try {
            // Check if mediaDevices is supported
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
            // Request permission with mobile-friendly constraints
            const constraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 }
                }
            };
            
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            stream.getTracks().forEach(track => track.stop());
            
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(device => device.kind === 'videoinput');
            
            if (videoDevices.length === 0) {
                throw new Error('No video input devices found');
            }
            
            // Try to find device with matching facing mode
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
            
            // Fallback: prefer back camera for 'environment', front for 'user'
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
            // Check if camera is available first
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
            
            // Try to get camera permission and device ID
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
                
                // Vibrate on mobile devices for better feedback
                if ('vibrate' in navigator) {
                    navigator.vibrate(200);
                }
                
                codeReader.reset();
                const videoElement = document.getElementById('camera-feed');
                videoElement.pause();
                
                lookupBarcode(result.text);
            }
            if (err && !(err instanceof ZXing.NotFoundException)) {
                console.error('Scanning error:', err);
            }
        }, {
            // Mobile-friendly constraints
            constraints: {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1280, max: 1920 },
                    height: { ideal: 720, max: 1080 }
                }
            }
        }).catch(error => {
            console.error('Failed to start scanning:', error);
            isScanning = false;
            stopCamera();
            
            // Show specific error message based on error type
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
        fetch('barcode_scanner.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'barcode=' + encodeURIComponent(barcode)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentProduct = data.product;
                currentProduct.barcode = barcode;
                displayProduct(data.product, barcode);
                showSuccess('Barcode: ' + barcode + ' - Product found!');
                stopCamera();
            } else {
                showError(data.message + ' (Barcode: ' + barcode + ')');
                currentProduct = { barcode: barcode };
                setTimeout(() => {
                    if (!isScanning) startScanning();
                }, 2000);
            }
        })
        .catch(error => {
            showError('Error looking up product');
            console.error(error);
        });
    }
    
    function displayProduct(product, barcode) {
        document.getElementById('product-name').textContent = product.product_name;
        document.getElementById('product-brand').textContent = product.brand || 'N/A';
        document.getElementById('product-category').textContent = product.category_name || 'N/A';
        document.getElementById('product-unit').textContent = product.default_unit;
        document.getElementById('product-shelf-life').textContent = product.typical_shelf_life_days ? product.typical_shelf_life_days + ' days' : 'N/A';
        document.getElementById('product-barcode').textContent = barcode;
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
    
    document.getElementById('add-to-inventory').addEventListener('click', function() {
        if (currentProduct) {
            const params = new URLSearchParams({
                barcode: currentProduct.barcode,
                product_name: currentProduct.product_name || '',
                category_id: currentProduct.category_id || '',
                catalog_id: currentProduct.catalog_id || '',
                unit: currentProduct.default_unit || 'pcs',
                shelf_life: currentProduct.typical_shelf_life_days || ''
            });
            window.location.href = 'add_item.php?' + params.toString();
        }
    });
    
    document.getElementById('scan-another').addEventListener('click', function() {
        document.getElementById('product-result').style.display = 'none';
        document.getElementById('scanner-result').style.display = 'none';
        document.getElementById('scanner-error').style.display = 'none';
        currentProduct = null;
        startCamera();
    });
    
    window.addEventListener('beforeunload', () => {
        stopCamera();
    });
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
<?php $conn->close(); ?>