<?php
session_start();
require_once 'vendor/autoload.php';

// Save selected usertype to session
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["usertype"])) {
    $_SESSION["usertype"] = $_POST["usertype"];
} else {
    die("Please select a user type first.");
}

$client = new Google_Client();
$client->setClientId('1038500151165-sjc75o2mke9plcl2gp0r6b0lt8re18g3.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/Copy-of-The-Project/google-callback.php');

$client->addScope("email");
$client->addScope("profile");

$login_url = $client->createAuthUrl();
header("Location: " . $login_url);
exit();
