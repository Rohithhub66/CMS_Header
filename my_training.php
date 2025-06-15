<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'employee') {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

if (isset($_GET['complete'])) {
    $assign_id = intval($_GET['complete']);
    // Mark training complete if belongs to user
    $stmt = $pdo->prepare("UPDATE training_assignments SET completed_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->execute([$assign_id, $user_id]);
}

$assignments = $pdo->prepare("SELECT ta.id, tm.title, tm.description, tm.file_path, ta.completed_at FROM training_assignments ta JOIN training_materials tm ON ta.training_id = tm.id WHERE ta.user_id = ?");
$assignments->execute([$user_id]);
$results = $assignments->fetchAll();
?>

<h2>My Trainings</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>Title</th>
        <th>Description</th>
        <th>File</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
    <?php foreach ($results as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['title']) ?></td>
            <td><?= nl2br(htmlspecialchars($r['description'])) ?></td>
            <td><a href="<?= htmlspecialchars($r['file_path']) ?>" target="_blank">View</a></td>
            <td><?= $r['completed_at'] ? 'Completed' : 'Pending' ?></td>
            <td>
                <?php if (!$r['completed_at']): ?>
                    <a href="?complete=<?= $r['id'] ?>">Mark as Completed</a>
                <?php else: ?>
                    &mdash;
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<p><a href="dashboard.php">Back to Dashboard</a></p>
