<?php
session_start();

// --- DB CONNECTION ---
require "includes/db.php";

// --- HANDLE ADD USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $usertype = $_POST['usertype'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Check if email already exists
    $check = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        echo "EmailExists";
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (phone, email, password, usertype) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $phone, $email, $password, $usertype);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            echo "UserAdded";
        } else {
            echo "Failed";
        }
    }
    exit();
}

// --- HANDLE DELETE USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_email'])) {
    $email = $_POST['delete_email'];
    $stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? "Deleted" : "Failed";
    exit();
}

// --- HANDLE DELETE SHOP ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shop_id'])) {
    $shop_id = $_POST['delete_shop_id'];
    $stmt = $conn->prepare("DELETE FROM mechanics WHERE id = ?");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? "ShopDeleted" : "ShopDeleteFailed";
    exit();
}

// --- FETCH USERS ---
$users = [];
$sql = "SELECT phone, email, usertype FROM users";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

// --- FETCH SHOPS FROM MECHANICS TABLE (UPDATED TO INCLUDE BUSINESS PERMIT) ---
$shops = [];
$shopQuery = "
    SELECT 
        m.id, 
        m.shop_name, 
        m.shop_address AS location, 
        m.picture, 
        m.business_permit, 
        u.email
    FROM mechanics m
    LEFT JOIN users u ON m.user_id = userid
    ORDER BY m.id DESC
";

$shopResult = $conn->query($shopQuery);
if ($shopResult && $shopResult->num_rows > 0) {
    $shops = $shopResult->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADMIN DASHBOARD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/nav.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        .permit-preview {
            max-width: 100px;
            max-height: 100px;
            overflow: hidden;
        }
        .permit-preview img {
            width: 100%;
            height: auto;
        }
        .pdf-icon {
            font-size: 3rem;
            color: #d32f2f;
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">
      <span class="logo-icon">🏍️</span>
      <span>Speed Parts Admin</span>
    </div>
  
    <div class="text-center">
        <a href="generate_users_pdf.php" class="btn btn-primary">Download User Report</a>
    </div>
    <div class="user-info">
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>

<div class="container mt-4">
    <div id="liveAlert"></div>

    <!-- Add User Form (unchanged) -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Add New User</h5>
                    <button class="btn btn-light btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#addUserForm">
                        <i class="bi bi-plus"></i> Toggle Form
                    </button>
                </div>
                <div class="card-body collapse" id="addUserForm">
                    <form id="newUserForm">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="usertype" class="form-label">User Type</label>
                                <select class="form-select" id="usertype" name="usertype" required>
                                    <option value="customer">Customer</option>
                                    <option value="mechanic">Mechanic</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-1 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100">Add</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Registered Users Table (unchanged) -->
    <div class="card mb-5">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Registered Users</h4>
        </div>
        <div class="card-body">
            <table id="userTable" class="table table-striped">
                <thead>
                    <tr>
                        <th>Phone Number</th>
                        <th>Email</th>
                        <th>Usertype</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody style="font-size: 13px;">
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['usertype']) ?></td>
                                <td>
                                    <button class="btn btn-danger btn-sm deleteUserBtn">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

   <!-- Updated Shops Table with Business Permit and Email Column -->
<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Registered Shops</h4>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Shop Name</th>
                    <th>Location</th>
                    <th>Email</th> <!-- Added Shop Email Header -->
                    <th>Shop Profile</th>
                    <th>Business Permit</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($shops)): ?>
                    <?php foreach ($shops as $shop): ?>
                        <tr data-shop-id="<?= $shop['id'] ?>">
                            <td><?= htmlspecialchars($shop['shop_name']) ?></td>
                            <td><?= htmlspecialchars($shop['location']) ?></td>
                            <td><?= htmlspecialchars($shop['email']) ?></td> <!-- Shop Email Cell -->
                            <td>
                                <?php if (!empty($shop['picture'])): ?>
                                    <img src="<?= htmlspecialchars($shop['picture']) ?>" alt="Shop" width="100">
                                <?php else: ?>
                                    <span class="text-muted">No image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($shop['business_permit'])): ?>
                                    <?php 
                                        $permit_ext = pathinfo($shop['business_permit'], PATHINFO_EXTENSION);
                                        if (in_array(strtolower($permit_ext), ['jpg', 'jpeg', 'png', 'gif'])): 
                                    ?>
                                        <a href="<?= htmlspecialchars($shop['business_permit']) ?>" target="_blank">
                                            <img src="<?= htmlspecialchars($shop['business_permit']) ?>" class="permit-preview" alt="Business Permit">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= htmlspecialchars($shop['business_permit']) ?>" target="_blank" class="text-danger">
                                            <i class="bi bi-file-earmark-pdf pdf-icon"></i>
                                            <div>View PDF</div>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No permit</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-danger btn-sm deleteShopBtn">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-muted">No shops uploaded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {
    // Add new user (unchanged)
    $("#newUserForm").submit(function (e) {
        e.preventDefault();
        let formData = {
            add_user: true,
            phone: $("#phone").val(),
            email: $("#email").val(),
            usertype: $("#usertype").val(),
            password: $("#password").val()
        };

        $.post("<?= $_SERVER['PHP_SELF'] ?>", formData, function(response) {
            if (response.trim() === "UserAdded") {
                $("#liveAlert").html('<div class="alert alert-success alert-dismissible fade show">User added successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                $("#newUserForm")[0].reset();
                
                let newRow = `<tr>
                    <td>${formData.phone}</td>
                    <td>${formData.email}</td>
                    <td>${formData.usertype}</td>
                    <td><button class="btn btn-danger btn-sm deleteUserBtn">Delete</button></td>
                </tr>`;
                $("#userTable tbody").prepend(newRow);
                
                attachDeleteUserEvent();
                
            } else if (response.trim() === "EmailExists") {
                $("#liveAlert").html('<div class="alert alert-warning alert-dismissible fade show">Email already exists.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            } else {
                $("#liveAlert").html('<div class="alert alert-danger alert-dismissible fade show">Failed to add user.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
        });
    });

    // Function to attach delete event to user delete buttons (unchanged)
    function attachDeleteUserEvent() {
        $(".deleteUserBtn").off("click").on("click", function () {
            let row = $(this).closest("tr");
            let email = row.find("td").eq(1).text();

            if (confirm("Are you sure you want to delete this user?")) {
                $.post("<?= $_SERVER['PHP_SELF'] ?>", { delete_email: email }, function(response) {
                    if (response.trim() === "Deleted") {
                        row.remove();
                        $("#liveAlert").html('<div class="alert alert-warning alert-dismissible fade show">User deleted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                    } else {
                        $("#liveAlert").html('<div class="alert alert-danger alert-dismissible fade show">Failed to delete user.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                    }
                });
            }
        });
    }

    // Initial attachment of delete events (unchanged)
    attachDeleteUserEvent();

    // Delete shop (unchanged)
    $(".deleteShopBtn").click(function () {
        let row = $(this).closest("tr");
        let shopId = row.data("shop-id");

        if (confirm("Are you sure you want to delete this shop?")) {
            $.post("<?= $_SERVER['PHP_SELF'] ?>", { delete_shop_id: shopId }, function(response) {
                if (response.trim() === "ShopDeleted") {
                    row.remove();
                    $("#liveAlert").html('<div class="alert alert-warning alert-dismissible fade show">Shop deleted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                } else {
                    $("#liveAlert").html('<div class="alert alert-danger alert-dismissible fade show">Failed to delete shop.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
                }
            });
        }
    });
});
</script>

</body>
</html>