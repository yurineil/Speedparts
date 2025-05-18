<?php
session_start();

// Check if user is logged in, if not redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Get user data from session
$user_type = $_SESSION["usertype"];
$email = $_SESSION["email"];
$user_id = $_SESSION["id"];

// Database connection
require "includes/db.php";

// Determine if user is mechanic or customer
$is_mechanic = ($user_type == "mechanic");

// Load appointments based on user type
if ($is_mechanic) {
    // For mechanics - get their shop name first
    $shop_stmt = $conn->prepare("SELECT shop_name FROM mechanics WHERE user_id = ?");
    $shop_stmt->bind_param("i", $user_id);
    $shop_stmt->execute();
    $shop_result = $shop_stmt->get_result();
    
    if ($shop_result->num_rows > 0) {
        $shop_data = $shop_result->fetch_assoc();
        $shop_name = $shop_data['shop_name'];
        
        // Then get appointments for their shop
        $stmt = $conn->prepare("
            SELECT * FROM appointments 
            WHERE shop_name = ? 
            ORDER BY appointment_date DESC
        ");
        $stmt->bind_param("s", $shop_name);
        $stmt->execute();
        $appointments = $stmt->get_result();
        $stmt->close();
    } else {
        // Mechanic hasn't set up shop yet
        $appointments = false;
    }
    $shop_stmt->close();
} else {
    // For customers - show their appointments
    $stmt = $conn->prepare("
        SELECT * FROM appointments 
        WHERE customer_email = ? 
        ORDER BY appointment_date DESC
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $appointments = $stmt->get_result();
    $stmt->close();
}

// Handle status updates and deletion (for mechanics only)
if ($is_mechanic && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if (in_array($action, ['Accepted', 'Rejected', 'Delete'])) {
        // Make sure this mechanic owns this appointment
        $verify = $conn->prepare("
            SELECT a.id 
            FROM appointments a
            JOIN mechanics m ON a.shop_name = m.shop_name
            WHERE a.id = ? AND m.user_id = ?
        ");
        $verify->bind_param("ii", $id, $user_id);
        $verify->execute();
        $result = $verify->get_result();
        
        if ($result->num_rows > 0) {
            if ($action == 'Delete') {
                // Delete the appointment
                $delete = $conn->prepare("DELETE FROM appointments WHERE id = ?");
                $delete->bind_param("i", $id);
                $delete->execute();
                $delete->close();
                
                header("Location: shop_appointment.php?status=deleted");
                exit;
            } else {
                // Update appointment status
                $update = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
                $update->bind_param("si", $action, $id);
                $update->execute();
                $update->close();
                
                header("Location: shop_appointment.php?status=updated");
                exit;
            }
        }
        $verify->close();
    }
}

// Handle deletion for customers (they can only delete their own appointments)
if (!$is_mechanic && isset($_GET['action']) && $_GET['action'] == 'Delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Verify this appointment belongs to the customer
    $verify = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND customer_email = ?");
    $verify->bind_param("is", $id, $email);
    $verify->execute();
    $result = $verify->get_result();
    
    if ($result->num_rows > 0) {
        // Delete the appointment
        $delete = $conn->prepare("DELETE FROM appointments WHERE id = ?");
        $delete->bind_param("i", $id);
        $delete->execute();
        $delete->close();
        
        header("Location: shop_appointment.php?status=deleted");
        exit;
    }
    $verify->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/nav.css">
</head>
<body>
<nav class="navbar">
        <div class="logo">
            <span class="logo-icon">🏍️</span>
            <span>Speed Parts</span>
        </div>
        <div class="nav-links">
            <?php if ($is_mechanic): ?>
                <a href="shop_appointment.php">Manage Appointment</a>
                <a href="shopsetting.php">Shop Setting</a>
              
            <?php else: ?>
             
           
            <?php endif; ?>
        </div>
        <div class="user-info">
            <span><?php echo $is_mechanic ? "Shop" : "Customer"; ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>  
        </div>
    </nav>
    
    <div class="container mt-5 pt-5">
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] == 'updated'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Appointment status updated successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php elseif ($_GET['status'] == 'deleted'): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    Appointment deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    
        <h2>
            <?php echo $is_mechanic ? 'Appointment Requests' : 'My Appointments'; ?>
        </h2>
        
        <?php if ($appointments && $appointments->num_rows > 0): ?>
            <table class="table table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Shop</th>
                        <th>Customer</th>
                        <th>Contact Number</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $appointments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['shop_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['appointment_date']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $row['status'] == 'Accepted' ? 'bg-success' : 
                                        ($row['status'] == 'Rejected' ? 'bg-danger' : 'bg-warning'); 
                                ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_mechanic && $row['status'] == 'Pending'): ?>
                                    <a href="?action=Accepted&id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm">Accept</a>
                                    <a href="?action=Rejected&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm">Reject</a>
                                <?php endif; ?>
                                
                                <?php if (($is_mechanic && ($row['status'] == 'Accepted' || $row['status'] == 'Rejected')) || !$is_mechanic): ?>
                                    <a href="?action=Delete&id=<?php echo $row['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this appointment?')">
                                        Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">
                <?php if (!$is_mechanic): ?>
                    No appointments found. <a href="book_appointment.php" class="alert-link">Book an appointment</a>
                <?php elseif (!$appointments): ?>
                    You need to set up your shop first. <a href="shopsetting.php" class="alert-link">Set up shop</a>
                <?php else: ?>
                    No appointments found.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>