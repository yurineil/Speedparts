<?php 
session_start(); 

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Get user email from session
$email = $_SESSION["email"];

$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$db = "project_db"; 
$conn = new mysqli($host, $user, $pass, $db);  

// Check for any notifications about status changes
$notifications = [];
$notification_query = $conn->prepare("
    SELECT * FROM appointments 
    WHERE customer_email = ? 
    AND (status = 'Accepted' OR status = 'Rejected')
    AND notification_seen = 0
");
$notification_query->bind_param("s", $email);
$notification_query->execute();
$result = $notification_query->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
    
    // Mark notification as seen
    $update = $conn->prepare("UPDATE appointments SET notification_seen = 1 WHERE id = ?");
    $update->bind_param("i", $row['id']);
    $update->execute();
}

// Fetch all mechanics for dropdown 
$shops = $conn->query("SELECT * FROM mechanics");  

// Handle form submission 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = $_POST['customer_name'];
    $appointment_date = $_POST['appointment_date'];
    $shop_id = $_POST['shop_id'];
    $contact_number = $_POST['contact_number']; // New field
    
    // Fetch shop name based on selected ID
    $shop = $conn->query("SELECT shop_name FROM mechanics WHERE id = $shop_id")->fetch_assoc();
    $shop_name = $shop['shop_name'];
    
    $stmt = $conn->prepare("INSERT INTO appointments (shop_name, customer_name, customer_email, appointment_date, contact_number, status, created_at, notification_seen) VALUES (?, ?, ?, ?, ?, 'Pending', NOW(), 0)");
    $stmt->bind_param("sssss", $shop_name, $customer_name, $email, $appointment_date, $contact_number);
    $stmt->execute();
    
    header("Location: bookAppointment.php?success=1");
    exit(); 
}
?>

<!DOCTYPE html>s
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/bookappointment.css" />
</head>
<body>

<nav class="navbar">
    <div class="logo">
      <span class="logo-icon">🏍️</span>
      <span>Speed Parts</span>
    </div>
    <div class="nav-links">
      <a href="costumer_dashboard.php">Shops</a>
      <a href="bookAppointment.php">Book Appointment</a>
      <a href="shop_appointment.php"></a>
    </div>
    <div class="user-info">
       <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="container mt-5">
    <h3 class="mb-4">Book an Appointment</h3>
    
    <!-- Display status notifications -->
    <?php if (!empty($notifications)): ?>
        <?php foreach ($notifications as $notification): ?>
            <div class="alert <?php echo $notification['status'] == 'Accepted' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
                Your appointment with <?php echo htmlspecialchars($notification['shop_name']); ?> 
                scheduled for <?php echo htmlspecialchars($notification['appointment_date']); ?> 
                has been <strong><?php echo strtolower($notification['status']); ?></strong>.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            Appointment booked successfully! You'll be notified when the shop responds.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Select Shop</label>
            <select name="shop_id" class="form-control" required>
                <option value="">-- Select a Shop --</option>
                <?php while ($row = $shops->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>">
                        <?php echo htmlspecialchars($row['shop_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Customer Name</label>
            <input type="text" name="customer_name" class="form-control" required>
        </div>
        
        <div class="mb-3">
            <label class="form-label">Appointment Date</label>
            <input type="date" name="appointment_date" class="form-control" required 
                   min="<?php echo date('Y-m-d'); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" required>
        </div>
        
        <button type="submit" class="btn btn-success">Submit Appointment</button>
    </form>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>