<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once 'db.php';

$user = $_SESSION['user'] ?? [];
if (!$user) {
    header("Location: index.php");
    exit;
}
// Accept either ['first_name','last_name'] or ['name'] or fallback to username or Admin
if (isset($user['first_name']) && isset($user['last_name'])) {
    $userName = trim($user['first_name'].' '.$user['last_name']);
} elseif (isset($user['name'])) {
    $userName = $user['name'];
} elseif (isset($user['username'])) {
    $userName = $user['username'];
} else {
    $userName = 'Admin';
}
$userId = $user['id'] ?? null;
$role = $user['role'] ?? 'User';
$joinDate = '';
$rolesAvailable = [];
$completedTrainings = 0;
$totalTrainings = 0;

if ($userId) {
    // Get join date
    $stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $joinDate = $stmt->fetchColumn();
    $joinDateStr = $joinDate ? date("F j, Y", strtotime($joinDate)) : '--';

    // Get roles available (for global settings, show all possible roles)
    $rolesAvailable = ['admin', 'employee'];

    // If admin and employee, show both roles
    if ($role === 'admin' || $role === 'employee') {
        // Get completed training stats if employee
        if ($role === 'employee' || ($role === 'admin' && isset($user['employee_id']))) {
            $empId = $role === 'employee' ? $userId : $user['employee_id'];
            $completedTrainings = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE user_id = $empId AND completed_at IS NOT NULL")->fetchColumn();
            $totalTrainings = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE user_id = $empId")->fetchColumn();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="header.css">
    <style>
        body {
            background: #f6f6f8;
            color: #232946;
            min-height: 100vh;
            padding: 0;
            margin: 0;
            font-family: 'Roboto', Arial, sans-serif;
        }
        .profile-container {
            max-width: 500px;
            margin: 90px auto 0 auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 36px rgba(36,48,94,0.09), 0 2px 8px rgba(0,0,0,0.07);
            padding: 36px 38px 22px 38px;
        }
        .profile-heading {
            font-size: 2em;
            font-weight: 700;
            color: #24305e;
            margin-bottom: 16px;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .profile-details {
            font-size: 1.1em;
            margin-bottom: 10px;
        }
        .profile-details strong {
            color: #232946;
            font-weight: 700;
            min-width: 110px;
            display: inline-block;
        }
        .profile-details .role-badge {
            background: #1E3A8A;
            color: #232946;
            border-radius: 12px;
            font-size: 0.98em;
            font-weight: 600;
            padding: 3px 16px;
            margin-left: 6px;
            letter-spacing: 0.5px;
        }
        .profile-details .trainings {
            background: #b8c1ec;
            border-radius: 8px;
            padding: 2px 10px;
            color: #232946;
            font-weight: 500;
            margin-left: 6px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #232946;
            background: #eebbc3;
            border-radius: 8px;
            padding: 4px 14px;
            text-decoration: underline;
            font-weight: 500;
            transition: background 0.17s, color 0.17s;
        }
        .back-link:hover {
            background: #d1e3ff;
            color: #14213d;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            .profile-container { padding: 16px 5px 12px 5px; }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="profile-container">
        <a class="back-link" href="dashboard.php"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>
        <div class="profile-heading">
            <i class="fas fa-user-shield"></i> Profile Information
        </div>
        <div class="profile-details">
            <strong>Name:</strong> <?php echo htmlspecialchars($userName); ?>
        </div>
        <div class="profile-details">
            <strong>Role:</strong>
            <span class="role-badge"><?= htmlspecialchars(ucfirst($role)); ?></span>
        </div>
        <div class="profile-details">
            <strong>Joined:</strong> <?php echo $joinDateStr ?? '--'; ?>
        </div>
        <div class="profile-details">
            <strong>Roles available:</strong>
            <?php echo implode(', ', array_map('ucfirst', $rolesAvailable)); ?>
        </div>
        <?php if ($role === 'employee' || ($role === 'admin' && $completedTrainings > 0)): ?>
        <div class="profile-details">
            <strong>Trainings Completed:</strong>
            <span class="trainings"><?= $completedTrainings . ' / ' . $totalTrainings ?></span>
        </div>
        <?php endif; ?>
        <div style="margin-top: 18px;">
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
</body>
</html>