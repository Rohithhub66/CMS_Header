<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$assignments = $pdo->query("SELECT ta.id, u.name AS employee_name, tm.title, ta.mandatory, ta.completed_at
    FROM training_assignments ta
    JOIN users u ON ta.user_id = u.id
    JOIN training_materials tm ON ta.training_id = tm.id
    ORDER BY u.name")->fetchAll();
?>

<h2>Training Status</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>Employee</th>
        <th>Training</th>
        <th>Mandatory</th>
        <th>Completed At</th>
    </tr>
    <?php foreach ($assignments as $a): ?>
        <tr>
            <td><?= htmlspecialchars($a['employee_name']) ?></td>
            <td><?= htmlspecialchars($a['title']) ?></td>
            <td><?= $a['mandatory'] ? 'Yes' : 'No' ?></td>
            <td><?= $a['completed_at'] ? $a['completed_at'] : 'Pending' ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<p><a href="dashboard.php">Back to Dashboard</a></p>
