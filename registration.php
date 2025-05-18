<?php
// Database connection configuration
require "includes/db.php";

// Initialize variables
$phone = $email = $password = $confirm_password = $usertype = "";
$phone_err = $email_err = $password_err = $confirm_password_err = $usertype_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate phone
    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter your phone number.";
    } else {
        $phone = trim($_POST["phone"]);
        if (!preg_match("/^[0-9]{10,14}$/", $phone)) {
            $phone_err = "Please enter a valid phone number.";
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        $sql = "SELECT Name_id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "This email is already taken.";
                } else {
                    $email = trim($_POST["email"]);
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email_err = "Please enter a valid email address.";
                    }
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate usertype
    if (empty($_POST["usertype"])) {
        $usertype_err = "Please select user type.";
    } else {
        $usertype = $_POST["usertype"];
        if ($usertype != "customer" && $usertype != "mechanic" && $usertype != "admin") {
            $usertype_err = "Invalid user type selection.";
        }
    }
    
    // Check input errors before inserting in database
    $valid_submission = empty($phone_err) && empty($email_err) && empty($password_err) && 
                        empty($confirm_password_err) && empty($usertype_err);

    if ($valid_submission) {
        $sql = "INSERT INTO users (phone, email, password, usertype) VALUES (?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $param_phone, $param_email, $param_password, $param_usertype);
            $param_phone = $phone;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Secure password hashing
            $param_usertype = $usertype;
            
            if ($stmt->execute()) {
                header("location: login.php");
                exit();
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/styles/registration.css">
    <title>Speed Parts Registration</title>
</head>
<body>
    <div class="register-container">
        <div class="login-container">
            <div class="logo-container">
                <img src="/images/logoweb.png" alt="RideSpeed Logo">
            </div>
            <h2>Register</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?php echo $phone; ?>" class="form-control">
                    <span class="error-message"><?php echo $phone_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo $email; ?>" class="form-control">
                    <span class="error-message"><?php echo $email_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control">
                    <span class="error-message"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control">
                    <span class="error-message"><?php echo $confirm_password_err; ?></span>
                </div>
                <div class="form-group">
                    <label>User Type</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="customer" name="usertype" value="customer" <?php if($usertype == "customer") echo "checked"; ?>>
                            <label for="customer">Customer</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="mechanic" name="usertype" value="mechanic" <?php if($usertype == "mechanic") echo "checked"; ?>>
                            <label for="mechanic">Mechanic</label>
                        </div>
                    </div>
                    <span class="error-message"><?php echo $usertype_err; ?></span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-register">Create Account</button>
                </div>
                <p class="login-link">Already have an account? <a href="login.php">Login here</a>.</p>
            </form>
        </div>
    </div>
</body>
</html>
