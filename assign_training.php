<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Fetch all trainings (and joined info for categories)
$stmt = $pdo->query(
    "SELECT t.*, f.name as function_name, ra.name as role_area_name, r.name as role_name
     FROM trainings t
     LEFT JOIN functions f ON t.function_id = f.id
     LEFT JOIN role_areas ra ON t.role_area_id = ra.id
     LEFT JOIN roles r ON t.role_id = r.id
     ORDER BY t.id DESC"
);
$allTrainings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $allTrainings[] = $row;

// Fetch employees
$employees = $pdo->query("SELECT * FROM users WHERE role = 'employee'")->fetchAll();

// For filters:
function getOptions($pdo, $table, $orderBy = 'name') {
    $stmt = $pdo->query("SELECT id, name FROM $table ORDER BY $orderBy");
    $options = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $options[] = $row;
    return $options;
}
$functionOptions = getOptions($pdo, 'functions');
$roleAreaOptions = getOptions($pdo, 'role_areas');
$roleOptions = getOptions($pdo, 'roles');

// Handle POSTs
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bulk assign - General
    if (isset($_POST['bulk_assign_general'])) {
        $employee_id = $_POST['employee_id_general'];
        $general_sub_category = $_POST['general_sub_category'];
        $mandatory = isset($_POST['mandatory_general']) ? 1 : 0;
        $due_date = $_POST['due_date_general'];

        $assigned = 0;
        foreach ($allTrainings as $training) {
            if ($training['category_type']=='general' && $training['general_sub_category']==$general_sub_category) {
                // Check if already assigned
                $stmt = $pdo->prepare("SELECT * FROM training_assignments WHERE training_id = ? AND user_id = ?");
                $stmt->execute([$training['id'], $employee_id]);
                if ($stmt->rowCount() == 0) {
                    $stmt_insert = $pdo->prepare("INSERT INTO training_assignments (training_id, user_id, mandatory, assigned_at, due_date) VALUES (?, ?, ?, NOW(), ?)");
                    $stmt_insert->execute([$training['id'], $employee_id, $mandatory, $due_date]);
                    $assigned++;
                }
            }
        }
        if ($assigned > 0) {
            $message = "All trainings in <strong>$general_sub_category</strong> have been assigned to the selected employee.";
        } else {
            $message = "All trainings in <strong>$general_sub_category</strong> are already assigned to this employee.";
        }
    }
    // Bulk assign - Role based
    elseif (isset($_POST['bulk_assign_role'])) {
        $employee_id = $_POST['employee_id_role'];
        $function_id = $_POST['function_id'];
        $role_area_id = $_POST['role_area_id'];
        $role_id = $_POST['role_id'];
        $mandatory = isset($_POST['mandatory_role']) ? 1 : 0;
        $due_date = $_POST['due_date_role'];

        $assigned = 0;
        foreach ($allTrainings as $training) {
            if (
                $training['category_type']=='role'
                && $training['function_id']==$function_id
                && $training['role_area_id']==$role_area_id
                && $training['role_id']==$role_id
            ) {
                $stmt = $pdo->prepare("SELECT * FROM training_assignments WHERE training_id = ? AND user_id = ?");
                $stmt->execute([$training['id'], $employee_id]);
                if ($stmt->rowCount() == 0) {
                    $stmt_insert = $pdo->prepare("INSERT INTO training_assignments (training_id, user_id, mandatory, assigned_at, due_date) VALUES (?, ?, ?, NOW(), ?)");
                    $stmt_insert->execute([$training['id'], $employee_id, $mandatory, $due_date]);
                    $assigned++;
                }
            }
        }
        // Compose readable role category
        $role_desc = '';
        foreach ($functionOptions as $f) if ($f['id'] == $function_id) $role_desc .= htmlspecialchars($f['name']).' / ';
        foreach ($roleAreaOptions as $ra) if ($ra['id'] == $role_area_id) $role_desc .= htmlspecialchars($ra['name']).' / ';
        foreach ($roleOptions as $r) if ($r['id'] == $role_id) $role_desc .= htmlspecialchars($r['name']);
        $role_desc = trim($role_desc, ' /');
        if ($assigned > 0) {
            $message = "All trainings in <strong>$role_desc</strong> have been assigned to the selected employee.";
        } else {
            $message = "All trainings in <strong>$role_desc</strong> are already assigned to this employee.";
        }
    }
    // Single assign
    elseif (isset($_POST['training_id']) && isset($_POST['employee_id'])) {
        $training_id = $_POST['training_id'];
        $employee_id = $_POST['employee_id'];
        $mandatory = isset($_POST['mandatory']) ? 1 : 0;
        $due_date = $_POST['due_date'];

        $stmt = $pdo->prepare("SELECT * FROM training_assignments WHERE training_id = ? AND user_id = ?");
        $stmt->execute([$training_id, $employee_id]);

        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO training_assignments (training_id, user_id, mandatory, assigned_at, due_date) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->execute([$training_id, $employee_id, $mandatory, $due_date]);
            $message = "Training assigned successfully.";
        } else {
            $message = "Training already assigned to this employee.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>LMS - Assign Training</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="header.css">
    <style>
    body { background: #f6f8fa; font-family: 'Roboto', sans-serif;}
    .assign-container {
        max-width: 540px;
        margin: 36px auto 0 auto;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 16px rgba(40, 60, 120, .09);
        padding: 35px 32px 32px 32px;
    }
    h2 {
        color: #24305e;
        margin: 0 0 18px 0;
        font-weight: 700;
        font-size: 1.28em;
        letter-spacing: .5px;
    }
    label {
        font-weight: 500;
        color: #3c4a69;
        margin-top: 16px;
        display: inline-block;
    }
    select, input[type="date"], input[type="text"] {
        width: 100%;
        box-sizing: border-box;
        padding: 10px 13px;
        margin: 10px 0;
        border: 1.5px solid #dce1ec;
        border-radius: 7px;
        font-size: 1em;
        background: #f8fbfd;
        transition: border .2s;
        min-width: 0;
    }
    select:focus, input[type="date"]:focus, input[type="text"]:focus {
        border: 1.5px solid #7ea7fb; background: #f2f7fd;
    }
    .form-section { margin-bottom: 19px; }
    .btn-primary {
        background: linear-gradient(90deg, #1976d2 0%, #5c91e6 100%);
        color: white;
        padding: 10px 22px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 1em;
        font-weight: 500;
        margin: 4px 0;
        transition: background 0.19s, box-shadow 0.19s;
        text-decoration: none;
        display: inline-block;
        white-space: nowrap;
    }
    .message {
        margin: 12px 0 0 0;
        font-size: 1.01em;
        color: #1976d2;
        font-weight: 500;
    }
    .back-link {
        display: inline-block;
        margin-top: 20px;
        color: #1976d2;
        text-decoration: underline;
        font-weight: 500;
        font-size: 1em;
    }
    .section-divider {
        margin: 32px 0 18px 0;
        border: none;
        border-top: 2px dashed #e0e4ea;
    }
    .bulk-form-label {
        font-size: 1.11em;
        color: #1a2567;
        font-weight: bold;
        margin-bottom: 6px;
        display: block;
    }
    .bulk-form-sub {
        color: #5c6b95;
        font-size: .97em;
        margin-bottom: 3px;
    }
    @media (max-width: 600px) {
        .assign-container { padding: 15px 7px; }
    }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="assign-container">
    <h2>Assign Training</h2>
    <?php if (!empty($message)): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <!-- Bulk assign - General category -->
    <form method="post" autocomplete="off" style="margin-bottom:30px;">
        <span class="bulk-form-label">Bulk Assign All "General" Sub-Category Trainings</span>
        <div class="form-section">
            <span class="bulk-form-sub">Select Employee</span>
            <select name="employee_id_general" required>
                <option value="" disabled selected>Select employee</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <span class="bulk-form-sub">Select Sub-Category</span>
            <select name="general_sub_category" required>
                <option value="" disabled selected>Select sub-category</option>
                <?php
                $subs = [];
                foreach ($allTrainings as $t)
                    if ($t['category_type'] == 'general' && $t['general_sub_category'] && !in_array($t['general_sub_category'], $subs))
                        $subs[] = $t['general_sub_category'];
                foreach ($subs as $sub): ?>
                    <option value="<?= htmlspecialchars($sub) ?>"><?= htmlspecialchars($sub) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <label>
                <input type="checkbox" name="mandatory_general" style="margin-right:5px;"> Mandatory?
            </label>
        </div>
        <div class="form-section">
            <label for="due_date_general">Due Date</label>
            <input type="date" name="due_date_general" id="due_date_general" required>
        </div>
        <button type="submit" name="bulk_assign_general" value="1" class="btn-primary">Bulk Assign General Trainings</button>
    </form>

    <!-- Bulk assign - Role based -->
    <form method="post" autocomplete="off" style="margin-bottom:30px;">
        <span class="bulk-form-label">Bulk Assign All "Role Based" Trainings</span>
        <div class="form-section">
            <span class="bulk-form-sub">Select Employee</span>
            <select name="employee_id_role" required>
                <option value="" disabled selected>Select employee</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <span class="bulk-form-sub">Function</span>
            <select name="function_id" required>
                <option value="" disabled selected>Select function</option>
                <?php foreach ($functionOptions as $f): ?>
                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <span class="bulk-form-sub">Role Area</span>
            <select name="role_area_id" required>
                <option value="" disabled selected>Select role area</option>
                <?php foreach ($roleAreaOptions as $ra): ?>
                    <option value="<?= $ra['id'] ?>"><?= htmlspecialchars($ra['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <span class="bulk-form-sub">Role</span>
            <select name="role_id" required>
                <option value="" disabled selected>Select role</option>
                <?php foreach ($roleOptions as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <label>
                <input type="checkbox" name="mandatory_role" style="margin-right:5px;"> Mandatory?
            </label>
        </div>
        <div class="form-section">
            <label for="due_date_role">Due Date</label>
            <input type="date" name="due_date_role" id="due_date_role" required>
        </div>
        <button type="submit" name="bulk_assign_role" value="1" class="btn-primary">Bulk Assign Role Based Trainings</button>
    </form>

    <hr class="section-divider"/>

    <!-- Single assign -->
    <form method="post" autocomplete="off">
        <span class="bulk-form-label">Assign Single Training</span>
        <div class="form-section">
            <label for="training_id">Select Training</label>
            <select name="training_id" id="training_id" required>
                <option value="" disabled selected>Select training</option>
                <?php foreach ($allTrainings as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <label for="employee_id">Select Employee</label>
            <select name="employee_id" id="employee_id" required>
                <option value="" disabled selected>Select employee</option>
                <?php foreach ($employees as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-section">
            <label>
                <input type="checkbox" name="mandatory" style="margin-right:5px;"> Mandatory?
            </label>
        </div>
        <div class="form-section">
            <label for="due_date">Due Date</label>
            <input type="date" name="due_date" id="due_date" required>
        </div>
        <button type="submit" class="btn-primary">Assign Training</button>
    </form>
    <a href="dashboard.php" class="back-link">Back to Dashboard</a>
</div>
</body>
</html>