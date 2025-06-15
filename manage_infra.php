<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$backfillMessage = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backfill_campaign'])) {
    try {
        $colCheck = $pdo->query("SHOW COLUMNS FROM phishing_emails LIKE 'campaign_id'")->fetch();
        if (!$colCheck) throw new Exception("The column 'campaign_id' does not exist in phishing_emails.");

        $stmt = $pdo->query("SELECT COUNT(*) FROM phishing_emails WHERE campaign_id IS NULL");
        $missingCount = $stmt->fetchColumn();

        if ($missingCount == 0) {
            $backfillMessage = "✅ All phishing email records already have campaign IDs.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM phishing_campaigns WHERE name = ?");
            $stmt->execute(['Default Campaign']);
            $campaign = $stmt->fetch();
            $defaultCampaignId = $campaign['id'] ?? null;

            if (!$defaultCampaignId) {
                $pdo->prepare("INSERT INTO phishing_campaigns (name) VALUES (?)")->execute(['Default Campaign']);
                $defaultCampaignId = $pdo->lastInsertId();
            }

            $pdo->prepare("UPDATE phishing_emails SET campaign_id = ? WHERE campaign_id IS NULL")
                ->execute([$defaultCampaignId]);

            $backfillMessage = "✅ Backfilled $missingCount records to campaign ID $defaultCampaignId.";
        }
    } catch (Exception $e) {
        $backfillMessage = "❌ Error: " . $e->getMessage();
    }
}

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee'")->fetchColumn();
$completedTrainings = $pdo->query("SELECT COUNT(*) FROM training_assignments WHERE completed_at = 1")->fetchColumn();
$totalAssignments = $pdo->query("SELECT COUNT(*) FROM training_assignments")->fetchColumn();
$pendingTrainings = $totalAssignments - $completedTrainings;
$totalCampaigns = $pdo->query("SELECT COUNT(*) FROM phishing_campaigns")->fetchColumn();
$totalClicks = $pdo->query("SELECT COUNT(*) FROM phishing_emails WHERE clicked_at IS NOT NULL")->fetchColumn();
$isAdmin = $_SESSION['user']['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - LMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background-color: #121212;
      color: #fff;
    }
    .sidebar {
      height: 100vh;
      background-color: #1e1e1e;
      width: 250px;
      transition: width 0.3s ease;
      position: fixed;
      top: 0;
      left: 0;
      overflow-x: hidden;
    }
    .sidebar.collapsed {
      width: 60px;
    }
    .sidebar .nav-link {
      color: #ddd;
      white-space: nowrap;
    }
    .sidebar .nav-link:hover {
      background-color: #333;
      color: #fff;
    }
    .sidebar .active {
      background-color: #333;
      font-weight: bold;
    }
    .sidebar i {
      width: 30px;
    }
    .content {
      margin-left: 250px;
      padding: 20px;
      transition: margin-left 0.3s ease;
    }
    .collapsed + .content {
      margin-left: 60px;
    }
    .toggle-btn {
      position: absolute;
      top: 10px;
      right: -25px;
      background: #1e1e1e;
      border-radius: 50%;
      border: none;
      color: #fff;
      width: 30px;
      height: 30px;
    }
    .profile {
      padding: 10px;
      background: #222;
      text-align: center;
      color: #ccc;
    }
    .domain-card {
      background: #2a2a2a;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      box-shadow: 0 0 10px rgba(0,0,0,0.5);
    }
  </style>
</head>
<body>
<div class="d-flex">
  <div class="sidebar" id="sidebar">
    <div class="profile">
      <i class="fas fa-user-circle fa-2x"></i><br>
      <small><?= htmlspecialchars($_SESSION['user']['name']) ?></small>
    </div>
    <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <nav class="nav flex-column mt-4">
      <a class="nav-link active" href="#" data-bs-toggle="tooltip" title="Dashboard"><i class="fas fa-home"></i><span class="link-text"> Dashboard</span></a>
      <a class="nav-link" href="add_training.php" data-bs-toggle="tooltip" title="Add Training"><i class="fas fa-book"></i><span class="link-text"> Add Training</span></a>
      <a class="nav-link" href="assign_training.php" data-bs-toggle="tooltip" title="Assign Training"><i class="fas fa-paper-plane"></i><span class="link-text"> Assign Training</span></a>
      <a class="nav-link" href="phishing_simulation2.php" data-bs-toggle="tooltip" title="Create Phishing Campaign"><i class="fas fa-bullseye"></i><span class="link-text"> Create Campaign</span></a>
      <a class="nav-link" href="campaign_report.php" data-bs-toggle="tooltip" title="Phishing Reports"><i class="fas fa-chart-line"></i><span class="link-text"> Reports</span></a>
      <a class="nav-link" href="view_assignments.php" data-bs-toggle="tooltip" title="Training Compliance"><i class="fas fa-check-circle"></i><span class="link-text"> Compliance</span></a>
      <a class="nav-link" href="threat_intel.php" data-bs-toggle="tooltip" title="Threat Intel"><i class="fas fa-shield-alt"></i><span class="link-text"> Threat Intel</span></a>
      <a class="nav-link" href="infra_manage.php" data-bs-toggle="tooltip" title="Infra Management"><i class="fas fa-server"></i><span class="link-text"> Infra</span></a>
    </nav>
  </div>

  <div class="content">
    <h2>Dashboard</h2>
    <?php if ($backfillMessage): ?>
      <p class="text-success fw-bold"><?= htmlspecialchars($backfillMessage) ?></p>
    <?php endif; ?>

    <div class="row">
      <div class="col-md-3">
        <div class="domain-card">
          <h5>Training & Awareness</h5>
          <p><?= $completedTrainings ?> / <?= $totalAssignments ?> completed</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="domain-card">
          <h5>Phishing Simulation</h5>
          <p><?= $totalCampaigns ?> campaigns / <?= $totalClicks ?> clicks</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="domain-card">
          <h5>Threat Intelligence</h5>
          <p><a href="threat_intel.php" class="btn btn-sm btn-light">View Threats</a></p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="domain-card">
          <h5>Change Management</h5>
          <p><a href="#" class="btn btn-sm btn-light">Coming Soon</a></p>
        </div>
      </div>
    </div>

    <form method="post" class="mt-4">
      <input type="submit" name="backfill_campaign" value="🛠 Backfill Missing Campaign IDs" class="btn btn-warning">
    </form>
    <a href="logout.php" class="btn btn-danger mt-3">Logout</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    document.querySelector('.content').classList.toggle('collapsed');
    const texts = document.querySelectorAll('.link-text');
    texts.forEach(el => el.style.display = sidebar.classList.contains('collapsed') ? 'none' : 'inline');
  }
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
</script>
</body>
</html>
