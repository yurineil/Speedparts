<?php
session_start();
require "includes/db.php";

// Check if user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
  header("location: login.php");
  exit;
}

if (!isset($_GET['mechanic_id'])) {
    die("Invalid mechanic ID.");
}

$mechanic_id = intval($_GET['mechanic_id']);

// Get mechanic shop info
$stmt = $conn->prepare("SELECT shop_name FROM mechanics WHERE user_id = ?");
$stmt->bind_param("i", $mechanic_id);
$stmt->execute();
$shop_result = $stmt->get_result();
$shop = $shop_result->fetch_assoc();
$stmt->close();

// Get mechanic's products
$stmt = $conn->prepare("SELECT * FROM products WHERE mechanic_id = ?");
$stmt->bind_param("i", $mechanic_id);
$stmt->execute();
$product_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($shop['shop_name'] ?? 'Shop'); ?> - Products</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="/styles/customerview.css">
  
</head>
<body>
<!-- Navbar -->
<nav class="navbar">
    <div class="logo">
        <span class="logo-icon">🏍️</span>
        <span>Speed Parts</span>
    </div>
    <div class="nav-links">
        <a href="costumer_dashboard.php">Shops</a>
        <a href="bookAppointment.php">Book Appointment</a>
        
    </div>
    <div class="user-info">
        <div class="user-avatar">
            <?php
            $email = $_SESSION['email'] ?? 'C';
            echo strtoupper(substr($email, 0, 1));
            ?>
        </div>
    
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
 <br><br><br>
<!-- Product Display -->
<div class="container mt-5">
  <?php if ($shop): ?>
    <h3 class="mb-4"><?php echo htmlspecialchars($shop['shop_name']); ?>'s Products</h3>
  <?php else: ?>
    <div class="alert alert-danger">Shop not found.</div>
  <?php endif; ?>
  
  <div class="row">
    <?php if ($product_result->num_rows > 0): ?>
      <?php while ($product = $product_result->fetch_assoc()): ?>
        <div class="col-md-4 mb-4">
          <div class="card h-100 shadow">
            <?php if (!empty($product['image'])): ?>
              <img src="<?php echo htmlspecialchars($product['image']); ?>" class="card-img-top" alt="Product Image">
            <?php else: ?>
              <img src="default-product.jpg" class="card-img-top" alt="Default Product Image">
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
              <p class="card-text" style="color: #28a745; font-size: 1.5rem; font-weight: bold;">
    ₱<?php echo number_format($product['price'], 2); ?>
</p>
              <p class="card-text text-muted flex-grow-1">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
              </p>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-info">No products available for this shop.</div>
      </div>
    <?php endif; ?>
  </div>

  <a href="costumer_dashboard.php" class="btn btn-secondary mt-3">Back to Shops</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
