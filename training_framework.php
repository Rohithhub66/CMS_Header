<?php
require_once 'db.php';

// Handle Role Area and Role addition messages
$message = '';
// Add Function
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_function'])) {
    $function_name = trim($_POST['function_name']);
    if ($function_name) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM functions WHERE name = ?");
            $stmt->execute([$function_name]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO functions (name) VALUES (?)");
                $stmt->execute([$function_name]);
                $message = "<span style='color:green'>Function added successfully!</span>";
            } else {
                $message = "<span style='color:orange'>Function already exists.</span>";
            }
        } catch (Exception $e) {
            $message = "<span style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
        }
    } else {
        $message = "<span style='color:red'>Please fill in the function name.</span>";
    }
}
// Add Role Area
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role_area'])) {
    $function_id = intval($_POST['function_id']);
    $role_area_name = trim($_POST['role_area_name']);
    if ($function_id && $role_area_name) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_areas WHERE name = ? AND function_id = ?");
            $stmt->execute([$role_area_name, $function_id]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO role_areas (name, function_id) VALUES (?, ?)");
                $stmt->execute([$role_area_name, $function_id]);
                $message = "<span style='color:green'>Role Area added successfully!</span>";
            } else {
                $message = "<span style='color:orange'>Role Area already exists for this Function.</span>";
            }
        } catch (Exception $e) {
            $message = "<span style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
        }
    } else {
        $message = "<span style='color:red'>Please fill in all fields for Role Area.</span>";
    }
}
// Add Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $role_area_id = intval($_POST['role_area_id']);
    $role_name = trim($_POST['role_name']);
    if ($role_area_id && $role_name) {
        try {
            // Insert or get Role
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->execute([$role_name]);
            $role_id = $stmt->fetchColumn();
            if (!$role_id) {
                $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
                $stmt->execute([$role_name]);
                $role_id = $pdo->lastInsertId();
            }
            // Insert into role_area_roles if not existing
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM role_area_roles WHERE role_area_id = ? AND role_id = ?");
            $stmt->execute([$role_area_id, $role_id]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO role_area_roles (role_area_id, role_id) VALUES (?, ?)");
                $stmt->execute([$role_area_id, $role_id]);
                $message = "<span style='color:green'>Role added successfully!</span>";
            } else {
                $message = "<span style='color:orange'>Role already exists for this Role Area.</span>";
            }
        } catch (Exception $e) {
            $message = "<span style='color:red'>Error: " . htmlspecialchars($e->getMessage()) . "</span>";
        }
    } else {
        $message = "<span style='color:red'>Please fill in all fields for Role.</span>";
    }
}

// Retrieve data for canvas view
$stmt = $pdo->query("SELECT id, name FROM functions ORDER BY name");
$functions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$function_blocks = [];
foreach ($functions as $func) {
    // Get Role Areas for this function
    $stmt = $pdo->prepare("SELECT id, name FROM role_areas WHERE function_id = ? ORDER BY name");
    $stmt->execute([$func['id']]);
    $role_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $area_blocks = [];
    foreach ($role_areas as $area) {
        // Get Roles for this Role Area
        $stmt_r = $pdo->prepare(
            "SELECT r.id, r.name FROM roles r
             JOIN role_area_roles rar ON r.id = rar.role_id
             WHERE rar.role_area_id = ?
             ORDER BY r.name"
        );
        $stmt_r->execute([$area['id']]);
        $roles = $stmt_r->fetchAll(PDO::FETCH_ASSOC);
        $area_blocks[] = [
            'id' => $area['id'],
            'name' => $area['name'],
            'roles' => $roles
        ];
    }
    $function_blocks[] = [
        'id' => $func['id'],
        'name' => $func['name'],
        'areas' => $area_blocks
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Training Framework Canvas</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f6f6;
            font-size: 13px;
            line-height: 1.35;
            margin: 0;
        }
        .flex-container {
            display: flex;
            min-height: 100vh;
            margin: 0;
        }
        .sidebar {
            width: 295px;
            background: #232946;
            color: #fff;
            padding: 22px 10px 14px 14px;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 2;
            border-right: 3px solid #b8c1ec;
            min-height: 100vh;
        }
        .canvas-area {
            margin-left: 295px;
            flex: 1;
            padding: 20px 18px 20px 30px;
            overflow-x: auto;
            min-height: 100vh;
            background: #f6f6f6;
        }
        .block-function {
            background: #fff;
            border-radius: 9px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px #0001;
            padding: 12px 15px 15px 15px;
            page-break-inside: avoid;
        }
        .block-title {
            font-size: 1.08em;
            font-weight: bold;
            margin-bottom: 8px;
            color: #232946;
        }
        .block-role-area {
            background: #eebbc3;
            border-radius: 6px;
            margin-bottom: 7px;
            padding: 6px 9px 8px 9px;
            margin-left: 8px;
        }
        .block-role-area-title {
            font-weight: bold;
            margin-bottom: 3px;
            color: #232946;
            font-size: 0.98em;
        }
        .block-role {
            display: inline-block;
            background: #b8c1ec;
            color: #232946;
            border-radius: 4px;
            padding: 3px 8px;
            margin: 2px 5px 2px 0;
            font-size: 0.96em;
        }
        .sidebar h2 {
            font-size: 1.02em;
            font-weight: bold;
            color: #eebbc3;
            margin-bottom: 6px;
        }
        .sidebar label {
            color: #fffffe;
            font-size: 0.96em;
        }
        .sidebar input[type="text"], .sidebar select {
            width: 94%;
            padding: 5px 6px;
            margin-top: 2px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: none;
            font-size: 0.98em;
        }
        .sidebar button {
            background: #eebbc3;
            color: #232946;
            border: none;
            font-weight: bold;
            border-radius: 4px;
            padding: 6px 12px;
            cursor: pointer;
            margin-bottom: 13px;
            margin-top: 4px;
            transition: background 0.2s;
            font-size: 0.92em;
        }
        .sidebar button:hover {
            background: #ffd6db;
        }
        .message-area {
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 0.98em;
        }
        @media (max-width: 900px) {
            .flex-container { flex-direction: column; }
            .sidebar { width: 100%; position: relative; min-height: unset; }
            .canvas-area { margin-left: 0; padding:10px; }
        }
        /* Pagination */
        .canvas-area {
            max-width: 1500px;
        }
        .canvas-pages {
            columns: 2;
            column-gap: 28px;
            /* max-height: 95vh; */
        }
        @media (max-width:1200px) {
            .canvas-pages { columns: 1; }
        }
        @media print {
            body, .canvas-area, .canvas-pages {
                font-size: 10pt;
            }
            .sidebar, .sidebar * {
                display: none !important;
            }
            .canvas-area {
                margin: 0 !important;
                padding: 0 !important;
                max-width: none;
            }
            .canvas-pages {
                columns: 2;
                column-gap: 20px;
            }
            .block-function, .block-role-area, .block-role {
                page-break-inside: avoid;
            }
        }
    </style>
    <script>
    function fetchOptions(url, selectId, placeholder) {
        fetch(url)
            .then(resp => resp.json())
            .then(data => {
                let select = document.getElementById(selectId);
                select.innerHTML = `<option value="">${placeholder}</option>`;
                data.forEach(row => {
                    let opt = document.createElement('option');
                    opt.value = row.id;
                    opt.textContent = row.name;
                    select.appendChild(opt);
                });
            });
    }
    function onFunctionChangeForRoleArea() {
        // In this UI, just static dropdown
    }
    function onFunctionChangeForRole() {
        let funcId = document.getElementById('function_for_role').value;
        if (funcId) {
            fetchOptions('training_framework.php?ajax=role_areas&function_id=' + funcId, 'role_area_for_add', 'Select Role Area');
        } else {
            document.getElementById('role_area_for_add').innerHTML = '<option value="">Select Role Area</option>';
        }
    }
    </script>
</head>
<body>
<div class="flex-container">
    <div class="sidebar">
        <h2>Add Function</h2>
        <form method="POST" autocomplete="off">
            <label for="function_name">Function Name:</label><br>
            <input type="text" id="function_name" name="function_name" required>
            <br>
            <button type="submit" name="add_function">Add Function</button>
        </form>
        <hr style="border:0;height:1px;background:#b8c1ec;margin:15px 0;">
        <h2>Add Role Area</h2>
        <form method="POST" autocomplete="off">
            <label for="function_id">Function:</label><br>
            <select name="function_id" id="function_id" required onchange="onFunctionChangeForRoleArea()">
                <option value="">Select Function</option>
                <?php foreach ($functions as $func): ?>
                    <option value="<?= $func['id'] ?>"><?= htmlspecialchars($func['name']) ?></option>
                <?php endforeach; ?>
            </select><br>
            <label for="role_area_name">Role Area Name:</label><br>
            <input type="text" id="role_area_name" name="role_area_name" required><br>
            <button type="submit" name="add_role_area">Add Role Area</button>
        </form>
        <hr style="border:0;height:1px;background:#b8c1ec;margin:15px 0;">
        <h2>Add Role</h2>
        <form method="POST" autocomplete="off">
            <label for="function_for_role">Function:</label><br>
            <select name="function_for_role" id="function_for_role" required onchange="onFunctionChangeForRole()">
                <option value="">Select Function</option>
                <?php foreach ($functions as $func): ?>
                    <option value="<?= $func['id'] ?>"><?= htmlspecialchars($func['name']) ?></option>
                <?php endforeach; ?>
            </select><br>
            <label for="role_area_id">Role Area:</label><br>
            <select name="role_area_id" id="role_area_for_add" required>
                <option value="">Select Role Area</option>
            </select><br>
            <label for="role_name">Role Name:</label><br>
            <input type="text" id="role_name" name="role_name" required><br>
            <button type="submit" name="add_role">Add Role</button>
        </form>
        <div class="message-area"><?= $message ?></div>
    </div>
    <div class="canvas-area">
        <h1 style="color:#232946;margin-bottom:10px;font-size:1.11em;letter-spacing:1px;">Function & Role Canvas</h1>
        <div class="canvas-pages">
        <?php foreach ($function_blocks as $fblock): ?>
            <div class="block-function">
                <div class="block-title"><?= htmlspecialchars($fblock['name']) ?></div>
                <?php if (count($fblock['areas']) === 0): ?>
                    <div style="margin-left:7px;color:#aaa;">No Role Areas</div>
                <?php endif; ?>
                <?php foreach ($fblock['areas'] as $ablock): ?>
                    <div class="block-role-area">
                        <div class="block-role-area-title"><?= htmlspecialchars($ablock['name']) ?></div>
                        <?php if (count($ablock['roles']) === 0): ?>
                            <span style="color:#777;margin-left:7px;">No Roles</span>
                        <?php else: ?>
                            <?php foreach ($ablock['roles'] as $role): ?>
                                <span class="block-role"><?= htmlspecialchars($role['name']) ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>