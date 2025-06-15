<?php
// edit_training.php
include 'db.php';

if (!isset($_GET['id'])) {
    echo "Training ID is required.";
    exit;
}

$id = (int)$_GET['id'];

// Fetch existing training
$stmt = $pdo->prepare("SELECT * FROM trainings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$training = $result->fetch_assoc();
$stmt->close();

if (!$training) {
    echo "Training not found.";
    exit;
}

// Update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $link = $_POST['link'];
    $category = $_POST['category'];

    $stmt = $conn->prepare("UPDATE trainings SET title = ?, description = ?, link = ?, category = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $title, $description, $link, $category, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: add_training.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Training</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 40px;
        }
        .card {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin: auto;
        }
        h2 {
            color: #333;
        }
        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
<div class="card">
    <h2>Edit Training</h2>
    <form method="POST" action="">
        <label>Title</label>
        <input type="text" name="title" value="<?= htmlspecialchars($training['title']) ?>" required>

        <label>Description</label>
        <textarea name="description" required><?= htmlspecialchars($training['description']) ?></textarea>

        <label>Link</label>
        <input type="text" name="link" value="<?= htmlspecialchars($training['link']) ?>" required>

        <label>Category</label>
        <input type="text" name="category" value="<?= htmlspecialchars($training['category']) ?>" required>

        <button type="submit">Update Training</button>
    </form>
</div>
</body>
</html>
