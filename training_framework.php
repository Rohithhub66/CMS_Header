<?php
require_once 'db.php';
session_start();

// Handle add function/role area/role logic (unchanged)
$message = '';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $role_area_id = intval($_POST['role_area_id']);
    $role_name = trim($_POST['role_name']);
    if ($role_area_id && $role_name) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = ?");
            $stmt->execute([$role_name]);
            $role_id = $stmt->fetchColumn();
            if (!$role_id) {
                $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
                $stmt->execute([$role_name]);
                $role_id = $pdo->lastInsertId();
            }
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

// AJAX: trainings for a role
if (isset($_GET['ajax']) && $_GET['ajax'] === 'trainings_for_role' && isset($_GET['role_id'])) {
    header('Content-Type: application/json');
    $role_id = intval($_GET['role_id']);
    $stmt = $pdo->prepare("SELECT t.*, f.name as function_name, ra.name as role_area_name, r.name as role_name
        FROM trainings t
        LEFT JOIN functions f ON t.function_id = f.id
        LEFT JOIN role_areas ra ON t.role_area_id = ra.id
        LEFT JOIN roles r ON t.role_id = r.id
        WHERE t.role_id = ?
        ORDER BY t.id DESC");
    $stmt->execute([$role_id]);
    $trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($trainings);
    exit;
}

// For dependent dropdowns
if (isset($_GET['ajax']) && $_GET['ajax'] === 'role_areas' && isset($_GET['function_id'])) {
    header('Content-Type: application/json');
    $function_id = intval($_GET['function_id']);
    $stmt = $pdo->prepare("SELECT id, name FROM role_areas WHERE function_id = ? ORDER BY name");
    $stmt->execute([$function_id]);
    $role_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($role_areas);
    exit;
}

// Retrieve data for canvas view
$stmt = $pdo->query("SELECT id, name FROM functions ORDER BY name");
$functions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$function_blocks = [];
foreach ($functions as $func) {
    $stmt = $pdo->prepare("SELECT id, name FROM role_areas WHERE function_id = ? ORDER BY name");
    $stmt->execute([$func['id']]);
    $role_areas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $area_blocks = [];
    foreach ($role_areas as $area) {
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

// Get all training counts per role
$stmt = $pdo->query("SELECT role_id, COUNT(*) as cnt FROM trainings WHERE role_id IS NOT NULL GROUP BY role_id");
$role_training_counts = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $role_training_counts[$row['role_id']] = $row['cnt'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Training Framework Canvas</title>
    <link rel="stylesheet" href="header.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f8fa;
            margin: 0;
            font-size: 13px;
        }
        .main-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 28px;
            max-width: 1400px;
            margin: 36px auto 0 auto;
            padding: 0 10px 40px 10px;
            align-items: flex-start;
        }
        .sidebar-card {
            background: #232946;
            color: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(40, 60, 120, .09);
            padding: 18px 14px 14px 14px;
            min-width: 0;
            margin-bottom: 0;
            max-width: 340px;
        }
        .sidebar-card h2 {
            font-size: 1.02em;
            font-weight: bold;
            color: #eebbc3;
            margin-bottom: 7px;
        }
        .sidebar-card label {
            color: #fffffe;
            font-size: 0.96em;
        }
        .sidebar-card input[type="text"], .sidebar-card select {
            width: 94%;
            padding: 5px 6px;
            margin-top: 2px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: none;
            font-size: 0.98em;
        }
        .sidebar-card button {
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
        .sidebar-card button:hover {
            background: #ffd6db;
        }
        .message-area {
            margin-bottom: 8px;
            font-weight: bold;
            font-size: 0.98em;
        }
        .framework-area {
            width: 100%;
        }
        .canvas-pages {
            max-width: 100%;
        }
        .block-function {
            background: #fff;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px #0001;
            padding: 12px 15px 15px 15px;
            border: 1px solid #e6eaf3;
        }
        .block-title {
            font-size: 1.08em;
            font-weight: bold;
            margin-bottom: 8px;
            color: #253050;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .block-title .arrow {
            font-size: 1.1em;
            margin-right: 8px;
            transition: transform 0.2s;
        }
        .block-function.minimized .block-title .arrow {
            transform: rotate(-90deg);
        }
        .block-role-area {
            background: #f1f3f7;
            border-radius: 5px;
            margin-bottom: 7px;
            padding: 7px 10px 8px 10px;
            margin-left: 8px;
            border-left: 4px solid #b9cbe6;
        }
        .block-role-area-title {
            font-weight: bold;
            margin-bottom: 4px;
            color: #394260;
            font-size: 0.98em;
            cursor: pointer;
            display: flex;
            align-items: center;
        }
        .block-role-area-title .arrow {
            font-size: 1em;
            margin-right: 8px;
            transition: transform 0.2s;
        }
        .block-role-area.minimized .block-role-area-title .arrow {
            transform: rotate(-90deg);
        }
        .block-role {
            display: inline-block;
            background: #e0e8f4;
            color: #29334f;
            border-radius: 3px;
            padding: 3px 9px;
            margin: 2px 6px 2px 0;
            font-size: 0.97em;
            cursor: pointer;
            transition: background 0.17s, color 0.17s;
            border: 1.2px solid #b9cbe6;
        }
        .block-role:hover {
            background: #1976d2;
            color: #fff;
            border-color: #1976d2;
        }
        .block-content,
        .block-role-area-content {
            display: none;
        }
        .block-function.expanded .block-content {
            display: block;
        }
        .block-role-area.expanded .block-role-area-content {
            display: block;
        }
        @media (max-width: 900px) {
            .main-grid { grid-template-columns: 1fr; gap: 0; }
            .sidebar-card { max-width: 100%; }
        }
        @media (max-width: 600px) {
            .main-grid { margin: 16px 0 0 0; padding: 0 0 30px 0; }
            .sidebar-card { border-radius: 9px; padding: 14px 6px; }
        }
        /* Modal styles (unchanged) */
        .modal-mask {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0; top: 0;
            width: 100vw; height: 100vh;
            background: rgba(44,51,71,0.32);
        }
        .modal-mask.show { display: block; }
        .modal-box {
            background: #fff;
            padding: 28px 30px 20px 30px;
            max-width: 460px;
            width: 98vw;
            max-height: 86vh;
            overflow-y: auto;
            border-radius: 13px;
            margin: 80px auto 0 auto;
            box-shadow: 0 4px 40px rgba(40,60,120,0.17);
            position: relative;
        }
        .modal-close {
            position: absolute;
            right: 14px; top: 12px;
            font-size: 1.25em;
            color: #888;
            cursor: pointer;
            transition: color 0.15s;
        }
        .modal-close:hover { color: #d32f2f; }
        .modal-header {
            font-size: 1.13em;
            font-weight: bold;
            margin-bottom: 8px;
            color: #253050;
        }
        .training-list-item {
            background: #f7faff;
            border-left: 4px solid #1976d2;
            border-radius: 7px;
            margin-bottom: 11px;
            padding: 12px 13px 10px 15px;
            box-shadow: 0 1px 4px rgba(90,140,200,0.04);
        }
        .training-list-item strong {
            font-size: 1.05em;
            color: #24305e;
        }
        .training-meta {
            font-size: .96em;
            color: #6c89d3;
            margin: 4px 0 2px 0;
            word-break: break-word;
        }
        .training-link { margin-top: 5px; }
        .no-trainings-msg {
            color: #888;
            padding: 12px 10px;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<nav class="nav-secondary">
    <div class="nav-secondary-inner" style="padding: 10px 24px;">
        <a href="dashboard.php">Dashboard</a>
        <span class="breadcrumb-sep">&gt;</span>
        <span>Training Framework</span>
    </div>
</nav>
<div class="main-grid">
    <div class="sidebar-card">
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
            <select name="function_id" id="function_id" required>
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
    <div class="framework-area">
        <h1 style="color:#253050;margin-bottom:10px;font-size:1.11em;letter-spacing:1px;">Function & Role Canvas</h1>
        <div class="canvas-pages" id="canvasPages">
        <?php foreach ($function_blocks as $fblock): ?>
            <div class="block-function minimized" data-function-id="<?= $fblock['id'] ?>">
                <div class="block-title" onclick="toggleFunction(this)">
                    <span class="arrow">&#9654;</span>
                    <?= htmlspecialchars($fblock['name']) ?>
                    <span style="font-size:0.97em;color:#888;font-weight:normal;margin-left:auto;">
                        <?= count($fblock['areas']) ?> Role Area<?= count($fblock['areas'])==1 ? '' : 's' ?>
                    </span>
                </div>
                <div class="block-content">
                    <?php if (count($fblock['areas']) === 0): ?>
                        <div style="margin-left:7px;color:#aaa;">No Role Areas</div>
                    <?php endif; ?>
                    <?php foreach ($fblock['areas'] as $ablock): ?>
                        <div class="block-role-area minimized" data-role-area-id="<?= $ablock['id'] ?>">
                            <div class="block-role-area-title" onclick="toggleRoleArea(this)">
                                <span class="arrow">&#9654;</span>
                                <?= htmlspecialchars($ablock['name']) ?>
                            </div>
                            <div class="block-role-area-content">
                                <?php if (count($ablock['roles']) === 0): ?>
                                    <span style="color:#888;margin-left:7px;">No Roles</span>
                                <?php else: ?>
                                    <?php foreach ($ablock['roles'] as $role): ?>
                                        <?php
                                            $tr_count = $role_training_counts[$role['id']] ?? 0;
                                        ?>
                                        <span class="block-role" data-role-id="<?= $role['id'] ?>" data-role-name="<?= htmlspecialchars($role['name']) ?>" title="<?= $tr_count ?> training<?= $tr_count==1 ? '' : 's' ?>">
                                            <?= htmlspecialchars($role['name']) ?>
                                            <span style="color:#1976d2;font-size:0.97em;font-weight:normal;">(<?= $tr_count ?>)</span>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<!-- Modal for trainings -->
<div class="modal-mask" id="modalMask">
    <div class="modal-box" id="modalBox">
        <span class="modal-close" onclick="closeModal()" title="Close">&times;</span>
        <div class="modal-header" id="modalHeader">Trainings for Role</div>
        <div id="modalContent">
            <div style="text-align:center;color:#888;">Loading trainings...</div>
        </div>
    </div>
</div>
<script>
    // Dependent Role Area dropdown for Add Role form
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
    function onFunctionChangeForRole() {
        let funcId = document.getElementById('function_for_role').value;
        if (funcId) {
            fetchOptions('training_framework.php?ajax=role_areas&function_id=' + funcId, 'role_area_for_add', 'Select Role Area');
        } else {
            document.getElementById('role_area_for_add').innerHTML = '<option value="">Select Role Area</option>';
        }
    }

    // Minimize everything at start, expand only what is clicked
    function toggleFunction(titleElem) {
        // Minimize all functions
        document.querySelectorAll('.block-function').forEach(f => {
            f.classList.add('minimized');
            f.classList.remove('expanded');
        });
        // Minimize all role areas
        document.querySelectorAll('.block-role-area').forEach(r => {
            r.classList.add('minimized');
            r.classList.remove('expanded');
        });
        // Expand clicked function
        const block = titleElem.closest('.block-function');
        block.classList.remove('minimized');
        block.classList.add('expanded');
    }
    function toggleRoleArea(titleElem) {
        // Minimize all role areas in this function
        const functionBlock = titleElem.closest('.block-function');
        functionBlock.querySelectorAll('.block-role-area').forEach(r => {
            r.classList.add('minimized');
            r.classList.remove('expanded');
        });
        // Expand clicked role area
        const areaBlock = titleElem.closest('.block-role-area');
        areaBlock.classList.remove('minimized');
        areaBlock.classList.add('expanded');
    }
    // Role click: Show trainings in modal
    document.querySelectorAll('.block-role').forEach(function(roleElem) {
        roleElem.onclick = function(e) {
            e.stopPropagation();
            let roleId = this.getAttribute('data-role-id');
            let roleName = this.getAttribute('data-role-name');
            showModal(roleId, roleName);
        }
    });

    function showModal(roleId, roleName) {
        document.getElementById('modalHeader').textContent = "Trainings for Role: " + roleName;
        document.getElementById('modalContent').innerHTML = '<div style="text-align:center;color:#888;">Loading trainings...</div>';
        document.getElementById('modalMask').classList.add('show');
        fetch('training_framework.php?ajax=trainings_for_role&role_id=' + roleId)
            .then(resp => resp.json())
            .then(function(trainings) {
                let html = '';
                if (trainings.length === 0) {
                    html = '<div class="no-trainings-msg">No trainings found for this role.</div>';
                } else {
                    trainings.forEach(function(training) {
                        html += `<div class="training-list-item">
                            <strong>${escapeHtml(training.title)}</strong>
                            <div class="training-meta">
                              ${training.function_name ? `<span><b>Function:</b> ${escapeHtml(training.function_name)}</span> ` : ''}
                              ${training.role_area_name ? `<span><b>Role Area:</b> ${escapeHtml(training.role_area_name)}</span> ` : ''}
                            </div>
                            <div style="color:#425;">${escapeHtml(training.description)}</div>
                            ${training.link ? `<div class="training-link"><a href="${escapeHtml(training.link)}" target="_blank" style="color:#1976d2;text-decoration:underline;">View Link</a></div>` : ''}
                            ${training.file_path ? `<div class="training-link"><a href="${escapeHtml(training.file_path)}" download style="color:#2d662f;text-decoration:underline;">Download File</a></div>` : ''}
                        </div>`;
                    });
                }
                document.getElementById('modalContent').innerHTML = html;
            });
    }
    function closeModal() {
        document.getElementById('modalMask').classList.remove('show');
    }
    document.getElementById('modalMask').onclick = function(e) {
        if (e.target === this) closeModal();
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function (m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }
    // On load, minimize all (which is default), and expand first function if you want to
    // let firstFunction = document.querySelector('.block-function');
    // if (firstFunction) firstFunction.classList.remove('minimized'), firstFunction.classList.add('expanded');
</script>
</body>
</html>
