
<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: forgot_pass.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_code = $_POST['reset_code'];
    
    require './includes/db.php'; // Include the database connection file   


    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $email = $_SESSION['email'];

    $stmt = $conn->prepare("SELECT reset_code FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && $user['reset_code'] == $entered_code) {
        $_SESSION['code_verified'] = true;
        header('Location: resetpassword.php'); // Redirect to the reset password form

        exit();
    } else {
        $_SESSION['error'] = "Invalid verification code.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Verification Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-image: linear-gradient(to right, #141e30, #243b55);
        }

        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056b3;
        }

        .message {
            text-align: center;
            color: red;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    
<img src="/images/logoweb.png" alt="Logo" style="width: 70%; max-width: 250px; height: auto; object-fit: contain; margin-bottom: 15px; margin-left: 70px;">
    <h2>Enter Verification Code</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">

        <label for="reset_code">Verification Code:</label>
        <input type="text" name="reset_code" id="reset_code" required>

        <button type="submit">Verify Code</button>
        <br><br>

    </form>
</div>
</body>
</html>
