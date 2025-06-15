<?php
require 'db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid phishing link.");
}

// Check if token exists
$stmt = $pdo->prepare("SELECT * FROM phishing_emails WHERE token = ?");
$stmt->execute([$token]);
$record = $stmt->fetch();

if (!$record) {
    die("Invalid phishing link.");
}

// If not clicked before, update clicked_at and IP
if (is_null($record['clicked_at'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $update = $pdo->prepare("UPDATE phishing_emails SET clicked_at = NOW(), ip_address = ? WHERE id = ?");
    $update->execute([$ip, $record['id']]);
}

// Show fake landing page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Important Notification</title>
</head>
<body>
    <h2>Important Security Notification</h2>
    <p>Thank you for reviewing this message. No action is required.</p>
</body>
</html>
