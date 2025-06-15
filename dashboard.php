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
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="header.css">
  <style>
    .sidebar {
      width: 220px;
      height: 100vh;
      background-color: #111827;
      color: white;
      padding: 20px 0;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 100;
    }
    .sidebar .nav-link {
      color: #E5E7EB;
      white-space: nowrap;
      padding: 10px 20px;
      display: block;
      text-decoration: none;
      transition: background 0.2s, color 0.2s;
    }
    .sidebar .nav-link.active,
    .sidebar .nav-link:hover {
      background-color: #1E40AF;
      color: #fff;
    }
    .sidebar i {
      width: 28px;
    }
    .profile {
      padding: 10px 0;
      background: #222;
      text-align: center;
      color: #ccc;
    }
    .sidebar .menu-parent {
      font-weight: bold;
      padding: 10px 20px 5px 20px;
      color: #fff;
      letter-spacing: 1px;
      margin-top: 10px;
      margin-bottom: 5px;
      font-size: 1.04em;
      border-bottom: 1px solid #2c3a55;
      cursor: pointer;
      user-select: none;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .sidebar .menu-parent .fa-chevron-down,
    .sidebar .menu-parent .fa-chevron-right {
      font-size: 0.8em;
      margin-left: 8px;
      transition: transform 0.2s;
    }
    .sidebar .submenu {
      padding-left: 0;
      margin-bottom: 15px;
      margin-top: 5px;
      display: none;
    }
    .sidebar .submenu.show {
      display: block;
    }
    .sidebar .submenu .nav-link {
      padding-left: 38px;
      font-weight: 400;
      font-size: 0.98em;
    }
    @media (max-width: 900px) {
      .sidebar { width: 100px; }
      .main { margin-left: 120px; }
    }
    @media (max-width: 600px) {
      .sidebar { width: 100%; height: auto; position: relative; }
      .main { margin-left: 0; padding: 10px; }
    }
  </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="sidebar" id="sidebar">
  <div class="profile">
    <i class="fas fa-user-circle fa-2x"></i><br>
    <small><?= htmlspecialchars($_SESSION['user']['name']) ?></small>
  </div>
  <!-- Parent menu for Training (collapsible) -->
  <div class="menu-parent" id="trainingMenuToggle">
    <span><i class="fas fa-graduation-cap"></i> Training</span>
    <i class="fas fa-chevron-down" id="trainingChevron"></i>
  </div>
  <div class="submenu" id="trainingSubMenu">
    <a class="nav-link" href="training_framework.php" data-bs-toggle="tooltip" title="Training Framework"><i class="fas fa-cubes"></i> Training Framework</a>
    <a class="nav-link" href="add_training.php" data-bs-toggle="tooltip" title="Add Training"><i class="fas fa-book"></i> Add Training</a>
    <a class="nav-link" href="assign_training.php" data-bs-toggle="tooltip" title="Assign Training"><i class="fas fa-paper-plane"></i> Assign Training</a>
    <a class="nav-link" href="view_assignments.php" data-bs-toggle="tooltip" title="Training Compliance"><i class="fas fa-check-circle"></i> Compliance</a>
  </div>
  <!-- Other menu items -->
  <a class="nav-link" href="phishing_simulation2.php" data-bs-toggle="tooltip" title="Create Phishing Campaign"><i class="fas fa-bullseye"></i> Create Campaign</a>
  <a class="nav-link" href="campaign_report.php" data-bs-toggle="tooltip" title="Phishing Reports"><i class="fas fa-chart-line"></i> Reports</a>
  <a class="nav-link" href="threat_intel.php" data-bs-toggle="tooltip" title="Threat Intel"><i class="fas fa-shield-alt"></i> Threat Intel</a>
  <a class="nav-link" href="infra_manage.php" data-bs-toggle="tooltip" title="Infra Management"><i class="fas fa-server"></i> Infra</a>
</div>

<div class="main">
  <?php if ($backfillMessage): ?>
    <p class="text-success fw-bold"><?= htmlspecialchars($backfillMessage) ?></p>
  <?php endif; ?>

  <div class="card-container">
    <div class="domain-card card">
      <h5>Training & Awareness</h5>
      <p><?= $completedTrainings ?> / <?= $totalAssignments ?> completed</p>
    </div>
    <div class="domain-card card">
      <h5>Phishing Simulation</h5>
      <p><?= $totalCampaigns ?> campaigns / <?= $totalClicks ?> clicks</p>
    </div>
    <div class="domain-card card">
      <h5>Threat Intelligence</h5>
      <p><a href="threat_intel.php" class="btn btn-sm btn-light">View Threats</a></p>
    </div>
    <div class="domain-card card">
      <h5>Change Management</h5>
      <p><a href="#" class="btn btn-sm btn-light">Coming Soon</a></p>
    </div>
  </div>
  
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Collapsible Trainings menu
  const trainingMenuToggle = document.getElementById('trainingMenuToggle');
  const trainingSubMenu = document.getElementById('trainingSubMenu');
  const trainingChevron = document.getElementById('trainingChevron');
  let trainingMenuOpen = true;

  function showTrainingMenu(open) {
    if (open) {
      trainingSubMenu.classList.add('show');
      trainingChevron.classList.remove('fa-chevron-right');
      trainingChevron.classList.add('fa-chevron-down');
    } else {
      trainingSubMenu.classList.remove('show');
      trainingChevron.classList.remove('fa-chevron-down');
      trainingChevron.classList.add('fa-chevron-right');
    }
  }

  // Initialize menu to open by default
  showTrainingMenu(true);

  trainingMenuToggle.addEventListener('click', function() {
    trainingMenuOpen = !trainingMenuOpen;
    showTrainingMenu(trainingMenuOpen);
  });

  // Bootstrap tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
</script>
</body>
</html>