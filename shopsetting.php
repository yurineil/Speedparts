<?php
session_start();

// Check if user is logged in and is a mechanic
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["usertype"] !== "mechanic") {
    header("location: login.php");
    exit;
}

// Database connection
require "includes/db.php";

$user_id = $_SESSION["id"];
$email = $_SESSION["email"];
$message = '';
$shop_data = [];
$products = [];
$permit_err = '';

// Check if mechanic already has shop information
$stmt = $conn->prepare("SELECT * FROM mechanics WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $shop_data = $result->fetch_assoc();
}
$stmt->close();

// Get mechanic's products
$stmt = $conn->prepare("SELECT * FROM products WHERE mechanic_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

// Process shop information form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_shop') {
    $shop_name = trim($_POST['shop_name']);
    $shop_address = trim($_POST['shop_address']);
    $shop_description = trim($_POST['shop_description'] ?? '');
    $picture_path = $shop_data['picture'] ?? '';
    $business_permit_path = $shop_data['business_permit'] ?? '';

    // Handle shop image upload
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $file_name = basename($_FILES["picture"]["name"]);
        $target_file = $target_dir . time() . "_" . $file_name;

        if (move_uploaded_file($_FILES["picture"]["tmp_name"], $target_file)) {
            $picture_path = $target_file;
        } else {
            $message = '<div class="alert alert-danger">Error uploading picture.</div>';
        }
    }

    // Handle business permit upload
    if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] == 0) {
        $target_dir = "uploads/permits/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        // Check file type and size
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['business_permit']['type'], $allowed_types)) {
            $permit_err = "Only JPG, PNG, and PDF files are allowed.";
        } elseif ($_FILES['business_permit']['size'] > $max_size) {
            $permit_err = "File size must be less than 5MB.";
        } else {
            $file_name = basename($_FILES["business_permit"]["name"]);
            $target_file = $target_dir . time() . "_" . $file_name;

            if (move_uploaded_file($_FILES["business_permit"]["tmp_name"], $target_file)) {
                $business_permit_path = $target_file;
            } else {
                $permit_err = "Error uploading business permit.";
            }
        }
    }

    if (empty($shop_name)) {
        $message = '<div class="alert alert-danger">Shop name is required!</div>';
    } else {
        if (!empty($shop_data)) {
            // Update existing shop info
            $update = $conn->prepare("UPDATE mechanics SET shop_name = ?, shop_address = ?, shop_description = ?, picture = ?, business_permit = ? WHERE user_id = ?");
            $update->bind_param("sssssi", $shop_name, $shop_address, $shop_description, $picture_path, $business_permit_path, $user_id);
        } else {
            // Insert new shop info
            $insert = $conn->prepare("INSERT INTO mechanics (user_id, shop_name, shop_address, shop_description, picture, business_permit) VALUES (?, ?, ?, ?, ?, ?)");
            $insert->bind_param("isssss", $user_id, $shop_name, $shop_address, $shop_description, $picture_path, $business_permit_path);
        }

        if (isset($update) ? $update->execute() : $insert->execute()) {
            $message = '<div class="alert alert-success">Shop information '. (isset($update) ? 'updated' : 'saved') .' successfully!</div>';
            // Refresh shop data
            $stmt = $conn->prepare("SELECT * FROM mechanics WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $shop_data = $result->fetch_assoc();
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Error processing your request!</div>';
        }
        if (isset($update)) $update->close();
        if (isset($insert)) $insert->close();
    }
}

// Process product form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_product') {
    $product_name = trim($_POST['product_name']);
    $product_price = trim($_POST['product_price']);
    $product_description = trim($_POST['product_description'] ?? '');
    $picture_path = '';

    // Handle product image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $file_name = basename($_FILES["product_image"]["name"]);
        $target_file = $target_dir . time() . "_" . $file_name;

        if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
            $picture_path = $target_file;
        } else {
            $message = '<div class="alert alert-danger">Error uploading product image.</div>';
        }
    }

    if (empty($product_name) || empty($product_price)) {
        $message = '<div class="alert alert-danger">Product name and price are required!</div>';
    } else {
        // Insert new product
        $insert = $conn->prepare("INSERT INTO products (mechanic_id, name, price, description, image) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("isdss", $user_id, $product_name, $product_price, $product_description, $picture_path);

        if ($insert->execute()) {
            $message = '<div class="alert alert-success">Product added successfully!</div>';
            // Refresh products list
            $stmt = $conn->prepare("SELECT * FROM products WHERE mechanic_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $products = [];
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Error adding product!</div>';
        }
        $insert->close();
    }
}

// Delete product
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_product') {
    $product_id = $_POST['product_id'];
    
    $delete = $conn->prepare("DELETE FROM products WHERE id = ? AND mechanic_id = ?");
    $delete->bind_param("ii", $product_id, $user_id);
    
    if ($delete->execute()) {
        $message = '<div class="alert alert-success">Product deleted successfully!</div>';
        // Refresh products list
        $stmt = $conn->prepare("SELECT * FROM products WHERE mechanic_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-danger">Error deleting product!</div>';
    }
    $delete->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/styles/nav.css">
    <link rel="stylesheet" href="/styles/shopproduct_img.css">
    <style>
        #map {
            height: 300px;
            width: 100%;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .map-container {
            position: relative;
            margin-bottom: 20px;
        }
        .location-preview {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        .location-preview i {
            color: #dc3545;
            margin-right: 5px;
        }
        .search-box {
            position: relative;
            margin-bottom: 15px;
        }
        .search-box input {
            padding-right: 40px;
        }
        .search-box button {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            background: none;
            border: none;
            padding: 8px 12px;
            color: #6c757d;
        }
        .search-box button:hover {
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <span class="logo-icon">🏍️</span>
            <span>Speed Parts</span>
        </div>
        <div class="nav-links">
            <a href="shop_appointment.php">Manage Appointment</a>
            <a href="shopsetting.php">Shop Setting</a>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <?php if (!empty($shop_data['picture'])): ?>
                    <img src="<?php echo $shop_data['picture']; ?>" alt="Shop Logo" class="avatar-image">
                <?php else: ?>
                    <?php echo substr($email, 0, 1); ?>
                <?php endif; ?>
            </div>
            <span>Shop</span>
            <a href="logout.php" class="logout-btn">Logout</a>  
        </div>
    </nav>

    <div class="container mt-5">
        <?php echo $message; ?>

        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="shop-tab" data-bs-toggle="tab" data-bs-target="#shop" type="button" role="tab" aria-controls="shop" aria-selected="true">
                    <i class="bi bi-shop"></i> Shop Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-controls="products" aria-selected="false">
                    <i class="bi bi-box-seam"></i> Products
                </button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <!-- Shop Information Tab -->
            <div class="tab-pane fade show active" id="shop" role="tabpanel" aria-labelledby="shop-tab">
                <div class="row">
                    <div class="col-md-4">
                        <!-- Shop Info Card -->
                        <div class="card mb-4">
                            <?php if (!empty($shop_data)): ?>
                                <?php if (!empty($shop_data['picture'])): ?>
                                    <img src="<?php echo $shop_data['picture']; ?>" class="card-img-top" alt="Shop Image" style="height: 200px; object-fit: cover;">
                                <?php endif; ?>
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($shop_data['shop_name']); ?></h5>
                                    <p class="card-text"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($shop_data['shop_address']); ?></p>
                                    <p class="card-text"><?php echo htmlspecialchars($shop_data['shop_description']); ?></p>
                                    
                                    <?php if (!empty($shop_data['business_permit'])): ?>
                                        <div class="permit-preview mt-3">
                                            <p class="mb-1"><strong>Business Permit:</strong></p>
                                            <a href="<?php echo $shop_data['business_permit']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View Permit
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($shop_data['latitude']) && !empty($shop_data['longitude'])): ?>
                                        <div class="location-preview mt-3">
                                            <p class="mb-1"><strong>Shop Location:</strong></p>
                                            <div id="preview-map" style="height: 150px; width: 100%; border-radius: 5px;"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="card-body">
                                    <p class="text-muted">No shop info uploaded yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <!-- Upload Form -->
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Update Shop Information</h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_shop">
                                    <div class="mb-3">
                                        <label for="shop_name" class="form-label">Shop Name</label>
                                        <input type="text" class="form-control" name="shop_name" id="shop_name" 
                                            value="<?php echo htmlspecialchars($shop_data['shop_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="shop_address" class="form-label">Location</label>
                                        <div class="search-box">
                                            <input type="text" class="form-control" name="shop_address" id="shop_address" 
                                                value="<?php echo htmlspecialchars($shop_data['shop_address'] ?? ''); ?>" required>
                                            <button type="button" id="search-location">
                                                <i class="bi bi-search"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Google Maps -->
                                    <div class="map-container">
                                        <div id="map"></div>
                                        <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($shop_data['latitude'] ?? ''); ?>">
                                        <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($shop_data['longitude'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="shop_description" class="form-label">Description</label>
                                        <textarea class="form-control" name="shop_description" id="shop_description" rows="3"
                                        ><?php echo htmlspecialchars($shop_data['shop_description'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="picture" class="form-label">Shop Logo/Image</label>
                                        <input class="form-control" type="file" id="picture" name="picture" accept="image/*">
                                    </div>
                                    
                                    <div class="permit-upload">
                                        <label>Business Permit</label>
                                        <div class="file-input-container">
                                            <input type="file" class="form-control" name="business_permit" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <p class="permit-description">Upload a copy of your business permit (JPG, PNG, or PDF format, max 5MB)</p>
                                        <?php if (!empty($permit_err)): ?>
                                            <span class="error-message"><?php echo $permit_err; ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($shop_data['business_permit'])): ?>
                                            <div class="permit-preview">
                                                <p class="mb-1"><strong>Current Permit:</strong></p>
                                                <a href="<?php echo $shop_data['business_permit']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View Current Permit
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Shop Info
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Tab -->
            <div class="tab-pane fade" id="products" role="tabpanel" aria-labelledby="products-tab">
                <!-- Add Product Form -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Add New Product</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_product">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="product_name" class="form-label">Product Name</label>
                                        <input type="text" class="form-control" name="product_name" id="product_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="product_price" class="form-label">Price (₱)</label>
                                        <input type="number" class="form-control" name="product_price" id="product_price" min="0.01" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="product_description" class="form-label">Description</label>
                                        <textarea class="form-control" name="product_description" id="product_description" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="product_image" class="form-label">Product Image</label>
                                        <input class="form-control" type="file" id="product_image" name="product_image" accept="image/*">
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-lg"></i> Add Product
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Products List -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-list-ul"></i> Products List</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> You haven't added any products yet. Add products using the form above.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($products as $product): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card product-card h-100">
                                            <div class="product-actions">
                                                <form action="" method="POST" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm rounded-circle">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <?php if (!empty($product['image'])): ?>
                                                <img src="<?php echo $product['image']; ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                            <?php else: ?>
                                                <div class="bg-light text-center p-5">
                                                    <i class="bi bi-image" style="font-size: 3rem;"></i>
                                                    <p>No image</p>
                                                </div>
                                            <?php endif; ?>

                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                                <div class="price-tag mb-2">₱<?php echo number_format($product['price'], 2); ?></div>
                                                <?php if (!empty($product['description'])): ?>
                                                    <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="card-footer bg-white d-flex align-items-center">
                                                <?php if (!empty($shop_data['picture'])): ?>
                                                    <img src="<?php echo $shop_data['picture']; ?>" class="shop-logo me-2" alt="Shop Logo">
                                                <?php endif; ?>
                                                <div>
                                                    <small class="text-muted">Sold by:</small>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($shop_data['shop_name'] ?? 'Your Shop'); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Google Maps JavaScript -->
    <script>
        // Initialize and set up Google Maps
       // Initialize and set up Google Maps
let map;
let marker;
let geocoder;

// Default coordinates (Philippines)
const defaultLat = 12.8797;
const defaultLng = 121.7740;

function initMap() {
    geocoder = new google.maps.Geocoder();
    
    // Get saved coordinates or use defaults
    const latField = document.getElementById('latitude');
    const lngField = document.getElementById('longitude');
    
    const lat = latField.value ? parseFloat(latField.value) : defaultLat;
    const lng = lngField.value ? parseFloat(lngField.value) : defaultLng;
    const zoom = latField.value ? 15 : 6; // Zoom in if coords exist
    
    // Create map
    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat, lng },
        zoom: zoom,
        mapTypeControl: false,
        streetViewControl: false
    });
    
    // Create marker if coordinates exist
    if (latField.value && lngField.value) {
        createMarker({ lat, lng });
    }
    
    // Map click event
    map.addListener("click", (e) => {
        const location = e.latLng;
        updateLocationFields(location);
        createMarker(location);
        
        // Reverse geocode to get address
        geocoder.geocode({ location: { lat: location.lat(), lng: location.lng() } }, (results, status) => {
            if (status === "OK" && results[0]) {
                document.getElementById("shop_address").value = results[0].formatted_address;
            }
        });
    });
    
    // Search button event
    document.getElementById("search-location").addEventListener("click", () => {
        const address = document.getElementById("shop_address").value;
        if (address) {
            geocodeAddress(address);
        }
    });
    
    // Enter key in address field
    document.getElementById("shop_address").addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
            e.preventDefault();
            const address = document.getElementById("shop_address").value;
            if (address) {
                geocodeAddress(address);
            }
        }
    });
    
    // Initialize preview map if coordinates exist
    if (document.getElementById("preview-map") && latField.value && lngField.value) {
        initPreviewMap(lat, lng);
    }
}

function createMarker(location) {
    // Remove existing marker if any
    if (marker) {
        marker.setMap(null);
    }
    
    // Create new marker
    marker = new google.maps.Marker({
        position: location,
        map: map,
        draggable: true,
        animation: google.maps.Animation.DROP
    });
    
    // Marker drag event
    marker.addListener("dragend", () => {
        const position = marker.getPosition();
        updateLocationFields(position);
        
        // Update address field
        geocoder.geocode({ location: { lat: position.lat(), lng: position.lng() } }, (results, status) => {
            if (status === "OK" && results[0]) {
                document.getElementById("shop_address").value = results[0].formatted_address;
            }
        });
    });
}

function updateLocationFields(location) {
    document.getElementById("latitude").value = location.lat();
    document.getElementById("longitude").value = location.lng();
}

function geocodeAddress(address) {
    geocoder.geocode({ address: address }, (results, status) => {
        if (status === "OK" && results[0]) {
            const location = results[0].geometry.location;
            
            // Update map and marker
            map.setCenter(location);
            map.setZoom(15);
            createMarker(location);
            
            // Update fields
            updateLocationFields(location);
        } else {
            alert("Geocode was not successful for the following reason: " + status);
        }
    });
}

function initPreviewMap(lat, lng) {
    const previewMap = new google.maps.Map(document.getElementById("preview-map"), {
        center: { lat: lat, lng: lng },
        zoom: 15,
        mapTypeControl: false,
        streetViewControl: false,
        zoomControl: false,
        fullscreenControl: false
    }); 
    
    new google.maps.Marker({
        position: { lat: lat, lng: lng },
        map: previewMap
    });
}

// Load Google Maps API
function loadGoogleMaps() {
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=AIzaSyA3ELfd8uwdOJ_pThw8FwhXWicXG-dtaFU&callback=initMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', loadGoogleMaps);
    </script>
    
    <!-- Replace YOUR_API_KEY with your actual Google Maps API key -->
    <script async defer src="https://localhost/Copy-of-The-Project.com/maps/api/js?key=AIzaSyA3ELfd8uwdOJ_pThw8FwhXWicXG-dtaFU&callback=initMap"></script>
</body>
</html>