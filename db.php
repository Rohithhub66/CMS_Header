<?php
$host = "localhost";
$dbname = "lms_db";
$user = "root";
$pass = ""; // Change for production

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}
$GLOBALS['e'] = $e; // Store it in the global scope
?>