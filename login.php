<?php
session_start();

// Clear any existing session data (prevent data leakage between users)
session_unset();
session_destroy();
session_start();

// Check if user is already logged in
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    // Redirect based on user type
    if ($_SESSION["usertype"] === "customer") {
        header("Location: costumer_dashboard.php");
    } elseif ($_SESSION["usertype"] === "mechanic") {
        header("Location: shopdashboard.php");
    } elseif ($_SESSION["usertype"] === "admin") {
        header("Location: admin_dashboard.php");
    }
    exit;
}

// Database connection
require "includes/db.php";


// Variables
$email = $password = "";
$email_err = $password_err = $login_err = $captcha_err = "";

// Process form data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email)) {
        $email_err = "Please enter your email.";
    }
    if (empty($password)) {
        $password_err = "Please enter your password.";
    }

    // Verify CAPTCHA
    $recaptcha_secret = "6LewHz8rAAAAAE6g04Op573iKdH9-EOpPQd57sA6"; // Replace with your actual secret key
    $recaptcha_response = $_POST['g-recaptcha-response'];
    
    // Make request to verify CAPTCHA
    $verify_response = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='.$recaptcha_secret.'&response='.$recaptcha_response);
    $response_data = json_decode($verify_response);
    
    if (!$response_data->success) {
        $captcha_err = "Please complete the CAPTCHA verification.";
    }

    if (empty($email_err) && empty($password_err) && empty($captcha_err)) {
        $sql = "SELECT Name_id, email, password, usertype, phone FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            // Bind result variables
            $stmt->bind_result($id, $db_email, $hashed_password, $usertype, $phone);
            
            if ($stmt->fetch()) {
                if (password_verify($password, $hashed_password)) {
                    // Password is correct, start a new secure session
                    session_regenerate_id(true); // This prevents session fixation attacks
                    
                    // Store session variables
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id"] = $id;
                    $_SESSION["email"] = $db_email;    
                    $_SESSION["usertype"] = $usertype;
                    $_SESSION["phone"] = $phone;
            
                    // Redirect based on user type
                    if ($usertype === "customer") {
                        header("Location: costumer_dashboard.php");
                    } elseif ($usertype === "mechanic") {
                        header("Location: shop_appointment.php");
                    } elseif ($usertype === "admin") {
                        header("Location: admin_dashboard.php");
                    } else {
                        $login_err = "Invalid user type!";
                    }
                    exit;
                } else {
                    $login_err = "Invalid password!";
                }
            } else {
                $login_err = "No account found with that email.";
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
    <title>RideSpeed - Login</title>
    <link rel="icon" type="image/png" href="../images/logo ridespeed.png">
    <link rel="stylesheet" href="/styles/login.css">
    <!-- Add reCAPTCHA API -->

     <script src="https://www.google.com/recaptcha/api.js"></script>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="/images/logoweb.png" alt="RideSpeed Logo">
        </div>
        <h2>Welcome Back</h2>
        
        <?php if (!empty($login_err)) { ?>
            <div class="error-message show"><?php echo $login_err; ?></div>
        <?php } ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo $email; ?>" placeholder="Enter your email">
                <?php if (!empty($email_err)) { ?>
                    <span class="error-message show"><?php echo $email_err; ?></span>
                <?php } ?>
            </div>    
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password">
                <?php if (!empty($password_err)) { ?>
                    <span class="error-message show"><?php echo $password_err; ?></span>
                <?php } ?>
            </div>
            
            <div class="form-group captcha-container">
                <!-- Google reCAPTCHA widget -->
                <div class="g-recaptcha" data-sitekey="6LewHz8rAAAAAPOmtjHOQ-9ks35K2R3Gf4hPcIjv"></div>
                <?php if (!empty($captcha_err)) { ?>
                    <span class="error-message show"><?php echo $captcha_err; ?></span>
                <?php } ?>
            </div>

            <div class="form-group">
                <button type="submit" class="btn-login">Sign In</button>
            </div>
              
            <div class="form-group">
                <p class="divider">or</p>
                <a href="google-login.php" class="btn-google">
                    <img src="/images/googleIcon.webp" alt="Google Icon" />
                    Continue with Google
                </a>
            </div>
            <p class="signup-link">Don't have an account? <a href="registration.php">Sign up now</a></p>
            <p class="login-link" style="text-align: center;"><a href="forgot_pass.php">Forgot your password?</a></p>
        </form>
    </div>
</body>
</html>