<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];

    $file = $_FILES['training_file'];
    $allowedTypes = ['video/mp4', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

    if (in_array($file['type'], $allowedTypes)) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filePath = $uploadDir . time() . '_' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $stmt = $pdo->prepare("INSERT INTO training_materials (title, description, file_path) VALUES (?, ?, ?)");
            $stmt->execute([$title, $description, $filePath]);
            echo "Training uploaded successfully.";
        } else {
            echo "File upload failed.";
        }
    } else {
        echo "Unsupported file type.";
    }
}
?>

<h2>Upload Training Material</h2>
<form method="post" enctype="multipart/form-data">
    Title: <input type="text" name="title" required><br>
    Description:<br>
    <textarea name="description" rows="4" cols="50"></textarea><br>
    File (MP4, PDF, DOC): <input type="file" name="training_file" required><br>
    <input type="submit" value="Upload">
</form>
<p><a href="dashboard.php">Back to Dashboard</a></p>
