<?php

require_once __DIR__ . '/vendor/autoload.php';
session_start();

// Database connection
require "includes/db.php";

$client = new Google_Client();
$client->setClientId('1038500151165-sjc75o2mke9plcl2gp0r6b0lt8re18g3.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/Copy-of-The-Project/google-callback.php');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);

       $oauth2   = new Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();

        $google_id = $conn->real_escape_string($userInfo->id);
        $email = $conn->real_escape_string($userInfo->email);
        $name = $conn->real_escape_string($userInfo->name);
        $picture = $conn->real_escape_string($userInfo->picture);

        // Check if user exists in DB
        $query = "SELECT * FROM users WHERE email = '$email'";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $role = $user['usertype']; // assuming there's a `role` column

            $_SESSION['user'] = [
                'id' => $google_id,
                'email' => $email,
                'name' => $name,
                'picture' => $picture,
                'usertype' => $role
            ];

            // Redirect based on role
            if ($role === 'admin') {
                header('Location: admin_dashboard.php');
            } elseif ($role === 'mechanic') {
                header('Location: shopsetting.php');
            } elseif ($role === 'customer') {
                header('Location: costumer_dashboard.php');
            } else {
                echo "Unknown role.";
            }
            exit();
        } else {
            echo "User not found.";
        }
    } else {
        echo "Login failed. Error: " . $token['error_description'];
    }
} else {
    echo "Invalid request.";
}
?>
