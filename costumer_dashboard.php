<?php session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["usertype"] !== "customer") {
  // Redirect to login page if not logged in or not a customer
  header("location: login.php");
  exit;
}

// Database connection
require "includes/db.php";

// Get all mechanics who have uploaded shop info
$shops = $conn->query("SELECT * FROM mechanics WHERE shop_name IS NOT NULL ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/styles/customer_dashboard.css" />
 
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
     
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </nav>
  <!-- Shop Cards -->
  <div class="container mt-5">
    <h3 class="mb-4">All Available Shops</h3>
    <div class="row">
      <?php if ($shops->num_rows > 0): ?>
        <?php while ($shop = $shops->fetch_assoc()): ?>
          <div class="col-md-4 mb-4">
            <div class="card h-100 shadow">
              <?php if (!empty($shop['picture'])): ?>
                <img src="<?php echo htmlspecialchars($shop['picture']); ?>" class="card-img-top" alt="Shop Image" />
              <?php else: ?>
                <img src="default-shop.jpg" class="card-img-top" alt="Default Shop Image" />
              <?php endif; ?>
              <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo htmlspecialchars($shop['shop_name']); ?></h5>
                <p class="card-text">
                  <i class="fas fa-map-marker-alt me-2 text-danger"></i>
                  <?php echo htmlspecialchars($shop['shop_address'] ?? 'No address provided'); ?>
                </p>
                <?php if (!empty($shop['business_permit'])): ?>
                  <div class="permit-badge">
                    <i class="fas fa-certificate text-success"></i>
                    <span>Verified Business</span>
                    <a href="<?php echo htmlspecialchars($shop['business_permit']); ?>" target="_blank" class="small">View Permit</a>
                  </div>
                <?php endif; ?>
                <p class="card-text text-muted flex-grow-1">
                  <?php echo nl2br(htmlspecialchars($shop['shop_description'])); ?>
                </p>
                <a href="customerViewProduct.php?mechanic_id=<?php echo $shop['user_id']; ?>" class="btn btn-primary mt-auto">View Shop Products</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-center">No shops available at the moment.</p>
      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>