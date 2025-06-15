<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['employee_file'])) {
    $file = $_FILES['employee_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'])) {
            $handle = fopen($file['tmp_name'], 'r');
            $rowNum = 0;
            $added = 0;
            $skipped = 0;
            $errors = [];
            while (($data = fgetcsv($handle)) !== FALSE) {
                $rowNum++;
                // Skip header
                if (
                    $rowNum == 1 &&
                    (stripos(implode(',', $data), 'name') !== false &&
                    stripos(implode(',', $data), 'email') !== false)
                ) {
                    continue;
                }
                // Skip empty lines or lines with all empty fields
                if (count(array_filter($data, fn($v) => trim($v) !== '')) == 0) {
                    continue;
                }
                // Ensure array has at least 6 elements
                $data = array_pad($data, 6, '');

                $name = trim($data[0]);
                $email = trim($data[1]);
                $password = trim($data[2]);
                $department = trim($data[3]);
                $manager_name = trim($data[4]);
                $manager_email = trim($data[5]);

                if (!$name || !$email || !$password) {
                    $errors[] = "Row $rowNum: Missing required field(s).";
                    $skipped++;
                    continue;
                }
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row $rowNum: Invalid email format ($email).";
                    $skipped++;
                    continue;
                }
                // Check duplicate by email
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "Row $rowNum: Duplicate email ($email).";
                    $skipped++;
                    continue;
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password, role, department, manager_name, manager_email) 
                     VALUES (?, ?, ?, 'employee', ?, ?, ?)"
                );
                $stmt->execute([$name, $email, $hash, $department, $manager_name, $manager_email]);
                $added++;
            }
            fclose($handle);
            $message = "<span style='color:green;'>$added employees added, $skipped skipped.</span>";
            if ($errors) {
                $message .= "<br><span style='color:orange;font-size:0.96em;'>" . implode("<br>", $errors) . "</span>";
            }
        } else {
            $message = "<span style='color:red;'>Please upload a CSV or TXT file.</span>";
        }
    } else {
        $message = "<span style='color:red;'>File upload error.</span>";
    }
}

// AD integration stub
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_ad'])) {
    // Example: Simulate importing 3 dummy employees from AD with new fields
    $dummyUsers = [
        ['John Doe', 'john.doe@company.com', 'ad_password', 'IT', 'Alice Manager', 'alice.manager@company.com'],
        ['Jane Smith', 'jane.smith@company.com', 'ad_password', 'HR', 'Bob Lead', 'bob.lead@company.com'],
        ['Alice Brown', 'alice.brown@company.com', 'ad_password', 'Finance', 'Tom Supervisor', 'tom.sup@company.com'],
    ];
    $added = 0;
    $skipped = 0;
    foreach ($dummyUsers as $rowNum => $user) {
        list($name, $email, $password, $department, $manager_name, $manager_email) = $user;
        // Check duplicate by email
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, role, department, manager_name, manager_email) 
             VALUES (?, ?, ?, 'employee', ?, ?, ?)"
        );
        $stmt->execute([$name, $email, $hash, $department, $manager_name, $manager_email]);
        $added++;
    }
    $message = "<span style='color:green;'>$added employees imported from AD, $skipped skipped (already exist).</span>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Employees</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="header.css">
    <style>
        body { font-family: 'Roboto', Arial, sans-serif; background: #f8f8fa; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 60px auto; background: #fff; padding: 30px 40px; border-radius: 9px; box-shadow: 0 2px 24px 0 rgba(60,72,90,0.14);}
        h2 { text-align: center; margin-bottom: 30px; color: #24305e; font-weight: 700; }
        .message { text-align: center; margin-bottom: 18px; }
        input[type="file"] { width: 100%; margin: 10px 0 20px 0;}
        input[type="submit"], button[type="submit"] {
            background: linear-gradient(90deg, #1976d2 0%, #5c91e6 100%);
            color: white; padding: 10px 22px; border: none; border-radius: 6px;
            cursor: pointer; font-size: 1em; font-weight: 500; margin: 3px 0;
            transition: background 0.19s, box-shadow 0.19s;
            display: inline-block;
        }
        input[type="submit"]:hover, button[type="submit"]:hover {
            background: #003e7c;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 22px;
            color: #0051a3;
            text-decoration: none;
            font-size: 1em;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .ad-block { margin-top: 40px; text-align: center; }
        .ad-block form { display: inline-block; }
        .ad-block small { color: #666; }
        .sample-link { display:block; text-align:center; margin:10px 0 20px 0; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<nav class="nav-secondary">
    <div class="nav-secondary-inner">
        <a href="dashboard.php">Dashboard</a>
        <span class="breadcrumb-sep">&gt;</span>
        <span>Upload Employees</span>
    </div>
</nav>
<div class="container">
    <h2>Upload Employees</h2>
    <?php if ($message): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label for="employee_file"><b>Upload CSV/TXT file</b> (columns: <em>name, email, password, department, manager_name, manager_email</em>):</label>
        <input type="file" name="employee_file" id="employee_file" accept=".csv,.txt" required>
        <input type="submit" value="Upload Employees">
    </form>
    <a class="sample-link" href="data:text/csv;charset=utf-8,name,email,password,department,manager_name,manager_email%0D%0AJohn Doe,john@company.com,Test@123,IT,Jane Manager,jane.mgr@company.com%0D%0AJane Smith,jane@company.com,Test@456,HR,Bob Lead,bob.lead@company.com" download="employees_sample.csv">Download Sample CSV</a>
    <div class="ad-block">
        <form method="post">
            <button type="submit" name="import_ad" value="1">Import Employees from Active Directory</button><br>
            <small>(Demo only: Imports a fixed list. For real AD, integrate with LDAP/AD APIs.)</small>
        </form>
    </div>
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
</div>
</body>
</html>