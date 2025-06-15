<?php
include 'db.php';
session_start();

function getOptions($pdo, $table, $orderBy = 'name') {
    $stmt = $pdo->query("SELECT id, name FROM $table ORDER BY $orderBy");
    $options = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $options[] = $row;
    return $options;
}
$functionOptions = getOptions($pdo, 'functions');
$roleAreaOptions = getOptions($pdo, 'role_areas');
$roleOptions = getOptions($pdo, 'roles');

// Fetch all trainings
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

$page_breadcrumb = [
    ['href' => 'dashboard.php', 'text' => 'Dashboard'],
    ['text' => 'Trainings']
];

// --- ADD: Handle training upload and show success message ---
$upload_success = false;
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
    $category_type = $_POST['category_type'];
    $general_sub_category = ($category_type === 'general' && isset($_POST['general_sub_category'])) ? $_POST['general_sub_category'] : null;
    $function_id = ($category_type === 'role' && isset($_POST['function_id'])) ? $_POST['function_id'] : null;
    $role_area_id = ($category_type === 'role' && isset($_POST['role_area_id'])) ? $_POST['role_area_id'] : null;
    $role_id = ($category_type === 'role' && isset($_POST['role_id'])) ? $_POST['role_id'] : null;
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $link = trim($_POST['link']);
    $file_path = null;

    // Handle file upload if present
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf','ppt','doc','docx','mp4','avi','mkv','jpg','jpeg','png','zip','rar'];
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $target_dir = 'uploads/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $filename = uniqid('tr_') . '.' . $ext;
            $target_file = $target_dir . $filename;
            if (move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
                $file_path = $target_file;
            }
        }
    }

    // Insert into trainings table
    $stmt = $pdo->prepare(
        "INSERT INTO trainings (category_type, general_sub_category, function_id, role_area_id, role_id, title, description, link, file_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $category_type,
        $general_sub_category,
        $function_id,
        $role_area_id,
        $role_id,
        $title,
        $description,
        $link,
        $file_path
    ]);
    $upload_success = true;

    // Set message according to category
    if ($category_type === 'general') {
        $cat = $general_sub_category ? ucfirst($general_sub_category) : "General";
        $upload_message = "Training has been successfully added to the <strong>$cat</strong> category.";
    } elseif ($category_type === 'role') {
        $cat = [];
        if ($function_id) {
            foreach ($functionOptions as $f) if ($f['id'] == $function_id) $cat[] = $f['name'];
        }
        if ($roleAreaOptions && $role_area_id) {
            foreach ($roleAreaOptions as $ra) if ($ra['id'] == $role_area_id) $cat[] = $ra['name'];
        }
        if ($roleOptions && $role_id) {
            foreach ($roleOptions as $r) if ($r['id'] == $role_id) $cat[] = $r['name'];
        }
        $cat_str = $cat ? implode(' / ', $cat) : 'Role-based';
        $upload_message = "Training has been successfully added to the <strong>$cat_str</strong> role-based category.";
    } else {
        $upload_message = "Training has been successfully added.";
    }
    // Refresh $allTrainings for showing the new entry without reload
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
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>LMS - Add Training</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="header.css">
    <style>
    body { background: #f6f8fa; font-family: 'Roboto', sans-serif; }
    .main-grid {
        display: grid;
        grid-template-columns: 1fr minmax(340px, 480px);
        gap: 28px;
        max-width: 1180px;
        margin: 36px auto 0 auto;
        padding: 0 10px 40px 10px;
        align-items: flex-start;
    }
    .form-card, .trainings-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 2px 16px rgba(40, 60, 120, .09);
        padding: 28px 28px 23px 28px;
        min-width: 0;
        margin-bottom: 0;
    }
    .form-card { box-shadow: 0 4px 24px rgba(60,100,150,.07);}
    .trainings-card {
        box-shadow: 0 2px 10px rgba(25, 80, 150, .06);
        width: 100%;
        max-width: 480px;
        min-width: 0;
        overflow-x: auto;
        overflow-y: visible;
    }
    .upload-success {
        padding: 14px 18px;
        background: #eafaed;
        border: 1.5px solid #6edc94;
        color: #227644;
        border-radius: 8px;
        margin-bottom: 18px;
        font-size: 1.09em;
        font-weight: 500;
        box-shadow: 0 2px 8px rgba(80,180,120,0.06);
    }
    h2 { color: #24305e; margin: 0 0 16px 0; font-weight: 700; font-size: 1.32em; letter-spacing: .5px;}
    label { font-weight: 500; color: #3c4a69; margin-top: 15px; display: inline-block;}
    input[type="text"], textarea, select {
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
    input[type="text"]:focus, textarea:focus, select:focus {
        border: 1.5px solid #7ea7fb; background: #f2f7fd;
    }
    textarea { resize: vertical; height: 72px; }
    input[type="file"] { margin: 10px 0;}
    button[type="submit"] {
        background: linear-gradient(90deg, #1976d2 0%, #5c91e6 100%);
        color: white; padding: 10px 22px; border: none; border-radius: 6px;
        cursor: pointer; font-size: 1em; font-weight: 500; margin: 3px 0;
        transition: background 0.19s, box-shadow 0.19s;
        text-decoration: none; display: inline-block;
        white-space: nowrap;
    }
    /* FILTER BUTTONS - SMALLER */
    .filters button {
        background: #eebbc3;
        color: #232946;
        border: none;
        font-weight: 600;
        font-size: 0.95em;
        padding: 4px 14px;
        border-radius: 4px;
        margin-right: 2px;
        min-width: 48px;
        min-height: 28px;
        line-height: 1.2;
        transition: background .12s, color .12s, box-shadow 0.15s;
    }
    .filters button.active {
        background: #1976d2;
        color: #fff;
        box-shadow: 0 1px 3px rgba(25, 80, 150, .11);
    }
    /* TRAINING ACTION BUTTONS - ICON ONLY, SMALLEST & CONSISTENT */
    .training-actions a {
        background: none;
        color: #1976d2;
        border: none;
        padding: 2px 5px;
        border-radius: 3px;
        font-size: 1.11em;
        margin: 0 0 0 4px;
        line-height: 1;
        min-width: 0;
        min-height: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: none;
        transition: color 0.15s;
        text-decoration: none;
        white-space: nowrap;
    }
    .training-actions a.edit      { color: #1976d2; }
    .training-actions a.delete    { color: #d32f2f; }
    .training-actions a.download  { color: #2d662f; }
    .training-actions a:hover,
    .training-actions a:focus     { background: #eaeaea; outline: none; }
    .training-actions .icon {
        display: inline-block;
        width: 18px;
        height: 18px;
        vertical-align: middle;
        pointer-events: none;
        margin-right: 0px;
        margin-left: 0px;
    }
    /* TRAININGS PANEL */
    .tabs { display: flex; gap: 7px; margin-bottom: 14px;}
    .tabs button {
        background: #eebbc3; border: none; padding: 9px 20px; border-radius: 14px 14px 0 0;
        font-weight: 600; color: #232946; font-size: 1.07em; cursor: pointer;
        transition: background .12s, color .12s;
    }
    .tabs button.active { background: #1976d2; color: #fff;}
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .filters { display: flex; gap: 7px; margin-bottom: 8px; flex-wrap: wrap; align-items: center;}
    .filters label { margin: 0 5px 0 0;}
    .filters select {
        padding: 6px 11px; border-radius: 5px; border: 1px solid #dce1ec;
        font-size: .98em; background: #f8fbfd;
    }
    .training-item {
        border-left: 4px solid #1976d2; background: #f7faff; padding: 11px 15px;
        margin-bottom: 10px; border-radius: 7px; display: flex; justify-content: space-between; align-items: flex-start;
        box-shadow: 0 1px 4px rgba(90,140,200,0.04);
        gap: 10px;
        min-width: 0;
        word-break: break-word;
        flex-wrap: wrap;
    }
    .training-info { flex: 1; display: flex; flex-direction: column; min-width: 0;}
    .training-info strong { font-size: 1.05em; color: #24305e; margin-bottom: 2px;}
    .training-meta { font-size: .94em; color: #5c91e6; margin: 4px 0 2px 0; word-break: break-word;}
    .training-actions { margin-left: 10px; display: flex; align-items: center; gap: 7px; flex-shrink: 0;}
    .training-link { margin-top: 5px;}
    .desc-counter {font-size: .97em; color: #7a8aad; float: right;}
    .hidden { display: none !important;}
    @media (max-width: 900px) {
        .main-grid { grid-template-columns: 1fr; gap: 0;}
        .form-card, .trainings-card { max-width: 100%;}
        .tabs { gap: 2px;}
    }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="main-grid">
    <div class="form-card card">
        <h2>Add New Training</h2>
        <?php if ($upload_success && $upload_message): ?>
            <div class="upload-success"><?= $upload_message ?></div>
        <?php endif; ?>
        <form method="POST" action="" enctype="multipart/form-data" id="trainingForm">
            <label>Category/Group</label>
            <select name="category_type" id="category_type" required onchange="onCategoryTypeChange()">
                <option value="" disabled selected>Select category/group</option>
                <option value="general">General</option>
                <option value="role">Role based</option>
            </select>
            <div id="general_fields" class="hidden">
                <label>General Category</label>
                <select name="general_sub_category" id="general_sub_category">
                    <option value="" disabled selected>Select sub-category</option>
                    <option value="Onboarding">Onboarding</option>
                    <option value="Refresher">Refresher</option>
                </select>
            </div>
            <div id="role_fields" class="hidden">
                <label>Function</label>
                <select name="function_id" id="function_id">
                    <option value="" disabled selected>Select function</option>
                    <?php foreach ($functionOptions as $option): ?>
                        <option value="<?= $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Role Area</label>
                <select name="role_area_id" id="role_area_id">
                    <option value="" disabled selected>Select role area</option>
                    <?php foreach ($roleAreaOptions as $option): ?>
                        <option value="<?= $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Role</label>
                <select name="role_id" id="role_id">
                    <option value="" disabled selected>Select role</option>
                    <?php foreach ($roleOptions as $option): ?>
                        <option value="<?= $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <label>Title</label>
            <input type="text" name="title" required autocomplete="off">
            <label>Description <span class="desc-counter" id="descCounter"></span></label>
            <textarea name="description" id="description" maxlength="1200" required placeholder="Max 100 words"></textarea>
            <label>Link (YouTube/Drive/etc)</label>
            <input type="text" name="link" placeholder="Paste a link if available" autocomplete="off">
            <label>Or Upload File</label>
            <input type="file" name="file" accept=".pdf,.ppt,.doc,.docx,.mp4,.avi,.mkv,.jpg,.jpeg,.png,.zip,.rar">
            <button type="submit">Add Training</button>
        </form>
    </div>
    <div class="trainings-card card" id="trainingsSection">
        <h2 style="margin-top:0;margin-bottom:18px;">Training Repository</h2>
        <div class="tabs">
            <button class="active" id="tab-general" onclick="switchTab(event,'general')">General</button>
            <button id="tab-role" onclick="switchTab(event,'role')">Role based</button>
        </div>
        <div class="tab-panel active" id="panel-general">
            <div class="filters">
                <button class="active" onclick="filterGeneral(event,'All')">All</button>
                <button onclick="filterGeneral(event,'Onboarding')">Onboarding</button>
                <button onclick="filterGeneral(event,'Refresher')">Refresher</button>
            </div>
            <div id="general-list"></div>
        </div>
        <div class="tab-panel" id="panel-role">
            <div class="filters">
                <label>Function:</label>
                <select id="filter-function" onchange="populateRoleAreaFilter()"></select>
                <label>Role Area:</label>
                <select id="filter-role-area" onchange="populateRoleFilter()"></select>
                <label>Role:</label>
                <select id="filter-role" onchange="filterRoleBased()"></select>
            </div>
            <div id="role-list"></div>
        </div>
    </div>
</div>
<script>
    const allTrainings = <?= json_encode($allTrainings, JSON_UNESCAPED_UNICODE) ?>;
    const functions = <?= json_encode($functionOptions, JSON_UNESCAPED_UNICODE) ?>;
    const roleAreas = <?= json_encode($roleAreaOptions, JSON_UNESCAPED_UNICODE) ?>;
    const roles = <?= json_encode($roleOptions, JSON_UNESCAPED_UNICODE) ?>;
    let currentTab = 'general', currentGeneral = 'All';

    function switchTab(e, tab) {
        e.preventDefault();
        currentTab = tab;
        document.getElementById('tab-general').classList.toggle('active', tab==='general');
        document.getElementById('tab-role').classList.toggle('active', tab==='role');
        document.getElementById('panel-general').classList.toggle('active', tab==='general');
        document.getElementById('panel-role').classList.toggle('active', tab==='role');
        if(tab==='general') renderGeneralList();
        else { initRoleFilters(); filterRoleBased(); }
    }
    function filterGeneral(event,type) {
        event.preventDefault();
        currentGeneral = type;
        let btns = event.target.parentNode.querySelectorAll('button');
        btns.forEach(b => b.classList.remove('active'));
        event.target.classList.add('active');
        renderGeneralList();
    }
    function renderGeneralList() {
        let list = document.getElementById('general-list'), html = '';
        allTrainings.filter(t =>
            t.category_type === 'general' && (currentGeneral === 'All' || t.general_sub_category === currentGeneral)
        ).forEach(training => { html += renderTraining(training); });
        list.innerHTML = html || '<div style="color:#888;">No trainings found.</div>';
    }
    function populateRoleAreaFilter() {
        let funcId = document.getElementById('filter-function').value,
            raSel = document.getElementById('filter-role-area');
        raSel.innerHTML = '<option value="">All</option>';
        let filteredRAs = roleAreas.filter(ra => !funcId || allTrainings.some(t =>
            t.category_type === 'role' && t.function_id == funcId && t.role_area_id == ra.id));
        filteredRAs.forEach(ra => { raSel.innerHTML += `<option value="${ra.id}">${ra.name}</option>`; });
        populateRoleFilter();
    }
    function populateRoleFilter() {
        let funcId = document.getElementById('filter-function').value,
            raId = document.getElementById('filter-role-area').value,
            rSel = document.getElementById('filter-role');
        rSel.innerHTML = '<option value="">All</option>';
        let filteredRoles = roles.filter(r => !raId || allTrainings.some(t =>
            t.category_type === 'role' && t.function_id == funcId && t.role_area_id == raId && t.role_id == r.id));
        filteredRoles.forEach(r => {
            rSel.innerHTML += `<option value="${r.id}">${r.name}</option>`;
        });
        filterRoleBased();
    }
    function filterRoleBased() {
        let funcId = document.getElementById('filter-function').value,
            raId = document.getElementById('filter-role-area').value,
            roleId = document.getElementById('filter-role').value,
            list = document.getElementById('role-list'), html = '';
        allTrainings.filter(t =>
            t.category_type === 'role' &&
            (!funcId || t.function_id == funcId) &&
            (!raId || t.role_area_id == raId) &&
            (!roleId || t.role_id == roleId)
        ).forEach(training => { html += renderTraining(training); });
        list.innerHTML = html || '<div style="color:#888;">No trainings found.</div>';
    }
    function renderTraining(training) {
        return `<div class="training-item">
            <div class="training-info">
                <strong>${escapeHTML(training.title)}</strong>
                <div class="training-meta">
                    ${training.function_name ? `<span><b>Function:</b> ${escapeHTML(training.function_name)}</span>` : ''}
                    ${training.role_area_name ? `<span><b>Role Area:</b> ${escapeHTML(training.role_area_name)}</span>` : ''}
                    ${training.role_name ? `<span><b>Role:</b> ${escapeHTML(training.role_name)}</span>` : ''}
                </div>
                ${training.link ? `<div class="training-link"><a href="${escapeHTML(training.link)}" target="_blank" style="color:#1976d2;text-decoration:underline;">View Link</a></div>` : ''}
            </div>
            <div class="training-actions">
                <a class="edit" title="Edit" href="edit_training.php?id=${training.id}">
                    <span class="icon">
                        <!-- Pencil SVG -->
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3l-12 12-4 1 1-4 12-12z"/>
                        </svg>
                    </span>
                </a>
                <a class="delete" title="Delete" href="?delete=${training.id}" onclick="return confirm('Are you sure you want to delete this training?')">
                    <span class="icon">
                        <!-- Trash SVG -->
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </span>
                </a>
                ${training.file_path ? `<a class="download" title="Download file" href="${escapeHTML(training.file_path)}" download>
                    <span class="icon">
                        <!-- Download SVG -->
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                    </span>
                </a>` : ''}
            </div>
        </div>`;
    }
    function escapeHTML(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function (m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
        });
    }
    function initRoleFilters() {
        let funcSel = document.getElementById('filter-function');
        funcSel.innerHTML = '<option value="">All</option>';
        functions.forEach(f => { funcSel.innerHTML += `<option value="${f.id}">${f.name}</option>`; });
        populateRoleAreaFilter();
    }
    window.onload = function() {
        var textarea = document.getElementById('description');
        if (textarea) {
            textarea.addEventListener('input', function updateDescCounter() {
                var counter = document.getElementById('descCounter');
                var words = textarea.value.trim().split(/\s+/).filter(Boolean).length;
                if (words > 100) {
                    let arr = textarea.value.trim().split(/\s+/).slice(0, 100);
                    textarea.value = arr.join(' ');
                    words = 100;
                }
                counter.textContent = words + " / 100 words";
            });
            textarea.dispatchEvent(new Event('input'));
        }
        var catType = document.getElementById('category_type');
        if (catType && catType.value) onCategoryTypeChange();
        renderGeneralList();
        initRoleFilters();
        filterRoleBased();
    };
    function onCategoryTypeChange() {
        var type = document.getElementById('category_type').value;
        document.getElementById('general_fields').classList.add('hidden');
        document.getElementById('role_fields').classList.add('hidden');
        if (type === 'general') document.getElementById('general_fields').classList.remove('hidden');
        if (type === 'role') document.getElementById('role_fields').classList.remove('hidden');
    }
</script>
</body>
</html>