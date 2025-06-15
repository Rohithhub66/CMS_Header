<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$results = $pdo->query("
    SELECT pe.id, u.name, u.email, pe.sent_at, pe.clicked_at, pe.ip_address 
    FROM phishing_emails pe
    JOIN users u ON pe.user_id = u.id
    ORDER BY pe.sent_at DESC
")->fetchAll();
?>

<h2>Phishing Simulation Report</h2>
<table border="1" cellpadding="5">
    <tr>
        <th>User</th>
        <th>Email</th>
        <th>Email Sent At</th>
        <th>Clicked At</th>
        <th>IP Address</th>
    </tr>
    <?php foreach ($results as $r): ?>
        <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td><?= $r['sent_at'] ?></td>
            <td><?= $r['clicked_at'] ?? 'Not Clicked' ?></td>
            <td><?= htmlspecialchars($r['ip_address'] ?? '-') ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<p><a href="dashboard.php">Back to Dashboard</a></p>
