<?php
session_start();

require './includes/db.php'; // Include the database connection file   


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userEmail = $_POST['email'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    $user = $result->fetch_assoc();

    if ($user) {
        $reset_code = rand(100000, 888888);

        $update = $conn->prepare("UPDATE users SET reset_code = ? WHERE email = ?");
        $update->bind_param("is", $reset_code, $userEmail);
        $update->execute();

        $_SESSION['email'] = $userEmail;

        $mail = new PHPMailer(true);

        try {
            // SMTP Settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'bayronyurineil@gmail.com';
            $mail->Password   = 'yutz znms puzz cvum'; // Use env var for security!
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('bayronyurineil@gmail.com', 'Yuri Neil Bayron');
            $mail->addAddress($userEmail, 'User');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Verification Code';
            $mail->Body = "
                <div style='background-color: white; padding: 20px; font-family: Arial, sans-serif;'>
                    <h3>Password Reset Code</h3>
                    <p>Hello, use the code below to reset your password:</p>
                    <h2 style='color:  #0056b3;'>$reset_code</h2>
                    <p style='font-size: 14px; color: #555;'>If you did not request a password reset, please disregard this message.</p>
                </div>";
            $mail->AltBody = "Hello, use the code to reset your password: $reset_code";

            $mail->send();

            $_SESSION['email_sent'] = true;
            $_SESSION['success'] = "A verification code has been sent to your email.";
            header('Location: ../sendcode.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Email send error: {$mail->ErrorInfo}";
            header('Location: ../forgotpassword.php');
            exit();
        }
    } else {
        $_SESSION['error'] = "No user found with that email.";
        header('Location: ../forgotpassword.php');
        exit();
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f8fc;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-image: linear-gradient(to right, #141e30, #243b55);
        }

        .container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-size: 14px;
            color: #555;
        }

        input[type="email"] {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            padding: 12px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        .message {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }

        .message.error {
            color: red;
        }

        .message.success {
            color: green;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_pass.php">
            <label for="email">Enter your email address:</label>
            <input type="email" name="email" id="email" required>

            <button type="submit" name="send_code">Send Verification Code</button>
        </form>
    </div>
</body>
</html>
