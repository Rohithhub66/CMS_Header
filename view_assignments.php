<?php
session_start();
require 'db.php';

function record_email_log($pdo, $user_id, $training_title, $email_type, $email_to) {
    $stmt = $pdo->prepare("INSERT INTO training_email_logs (user_id, training_title, email_type, email_to, sent_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $training_title, $email_type, $email_to]);
}
function get_all_email_logs($pdo) {
    $logs = [];
    $stmt = $pdo->query("SELECT user_id, training_title, email_type, MAX(sent_at) as last_sent FROM training_email_logs GROUP BY user_id, training_title, email_type");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $logs[$row['user_id']][$row['training_title']][$row['email_type']] = $row['last_sent'];
    }
    return $logs;
}

function send_compliance_email($pdo, $user_id, $to, $employee_name, $training_title, $due_date, $manager_email = null, $escalate = false) {
    $subject = $escalate ? "ESCALATION: Training Overdue for {$employee_name}" : "Training Assignment Notification";
    $message = $escalate
        ? "Dear Manager,\n\nThis is to escalate that your reportee, {$employee_name}, has not completed the assigned training listed below by the due date:\n\nTraining: {$training_title}\nDue Date: {$due_date}\n\nPlease ensure timely completion.\n\nRegards,\nLMS Admin Team"
        : "Dear {$employee_name},\n\nThis is a notification regarding the following training assigned to you:\n\nTraining: {$training_title}\nDue Date: {$due_date}\n\nPlease ensure timely completion.\n\nRegards,\nLMS Admin Team";
    $headers = "From: noreply@lms.com\r\n";
    // mail($to, $subject, $message, $headers);
    $email_type = $escalate ? 'escalation' : 'reminder';
    record_email_log($pdo, $user_id, $training_title, $email_type, $to);
}

if (isset($_POST['bulk_send_email']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = json_decode($_POST['rows'], true);
    foreach ($rows as $row) {
        send_compliance_email($pdo, $row['user_id'], $row['to'], $row['employee_name'], $row['training_title'], $row['due_date'], $row['manager_email'], false);
    }
    echo json_encode(['success' => true, 'msg' => 'Bulk email sent']);
    exit;
}
if (isset($_POST['bulk_escalate_email']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = json_decode($_POST['rows'], true);
    foreach ($rows as $row) {
        if ($row['manager_email']) {
            send_compliance_email($pdo, $row['user_id'], $row['manager_email'], $row['employee_name'], $row['training_title'], $row['due_date'], $row['manager_email'], true);
        }
    }
    echo json_encode(['success' => true, 'msg' => 'Bulk escalation email sent']);
    exit;
}
if (isset($_POST['send_email']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $to = $_POST['to'];
    $employee_name = $_POST['employee_name'];
    $training_title = $_POST['training_title'];
    $due_date = $_POST['due_date'];
    $manager_email = $_POST['manager_email'];
    $escalate = isset($_POST['escalate']) && $_POST['escalate'] === "1";
    send_compliance_email($pdo, $user_id, $to, $employee_name, $training_title, $due_date, $manager_email, $escalate);
    echo json_encode(['success' => true, 'msg' => 'Email sent']);
    exit;
}

$query = "
    SELECT u.id AS user_id, u.name AS employee_name, u.email, u.manager_email,
           t.title AS training_title, ta.due_date, ta.completed_at, ta.mandatory,
           t.category_type, t.general_sub_category
    FROM users u
    LEFT JOIN training_assignments ta ON u.id = ta.user_id
    LEFT JOIN trainings t ON ta.training_id = t.id
    WHERE u.role = 'employee'
    ORDER BY u.name, ta.due_date, t.title
";
$stmt = $pdo->query($query);
$allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function get_category_label($row) {
    if ($row['category_type'] === "general") {
        if ($row['general_sub_category']) return ucfirst($row['general_sub_category']);
        return "General";
    } elseif ($row['category_type'] === "role") {
        return "Role based";
    }
    return "-";
}
function get_compliance_status($due_date, $completed_at) {
    $now = strtotime(date('Y-m-d'));
    $due_ts = $due_date ? strtotime($due_date) : false;
    $completed_ts = $completed_at ? strtotime($completed_at) : false;
    if (!$due_ts) return ['-','na',''];
    if (!$completed_at && $due_ts < $now) return ['No','no','overdue'];
    if ($completed_at && $completed_ts > $due_ts) return ['No','no','overdue'];
    if ($completed_at && $completed_ts <= $due_ts) return ['Yes','yes',''];
    if (!$completed_at && $due_ts >= $now) {
        $pendingDays = working_days_between($now, $due_ts);
        if ($pendingDays <= 5 && $pendingDays >= 0) return ['Pending','pending','soon'];
        else return ['Pending','pending',''];
    }
    return ['-','na',''];
}
function working_days_between($from, $to) {
    $days = 0;
    for ($i = $from; $i <= $to; $i += 86400) {
        $weekday = date('N', $i);
        if ($weekday < 6) $days++;
    }
    return $days - 1;
}
function format_date($date) {
    if (!$date) return "-";
    return date("d M, Y", strtotime($date));
}

$employeeNames = [];
$emails = [];
$trainingTitles = [];
$mandatoryVals = [];
$categoryVals = [];
foreach ($allRows as $row) {
    if ($row['employee_name'] && !in_array($row['employee_name'], $employeeNames)) $employeeNames[] = $row['employee_name'];
    if ($row['email'] && !in_array($row['email'], $emails)) $emails[] = $row['email'];
    if ($row['training_title'] && !in_array($row['training_title'], $trainingTitles)) $trainingTitles[] = $row['training_title'];
    $mandatoryStr = $row['mandatory'] ? "Yes" : "No";
    if (!in_array($mandatoryStr, $mandatoryVals)) $mandatoryVals[] = $mandatoryStr;
    $cat = get_category_label($row);
    if (!in_array($cat, $categoryVals)) $categoryVals[] = $cat;
}
sort($employeeNames); sort($emails); sort($trainingTitles); sort($mandatoryVals); sort($categoryVals);

$emailLogs = get_all_email_logs($pdo);
$page_breadcrumb = [
    ['href' => 'dashboard.php', 'text' => 'Dashboard'],
    ['text' => 'Assignments']
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>LMS - Training Assignment Compliance</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="header.css">
    <style>
    body { background: #f6f8fa; font-family: 'Roboto', sans-serif; }
    .main-grid {
        display: grid;
        grid-template-columns: 1fr;
        max-width: 1180px;
        margin: 36px auto 0 auto;
        padding: 0 10px 40px 10px;
        align-items: flex-start;
    }
    .compliance-card {
        max-width: 1100px;
        margin: auto;
        padding: 0;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(60,100,150,.07);
    }
    .compliance-ribbon {
        width: 100%;
        background: #e6eaf9;
        border-radius: 16px 16px 0 0;
        padding: 28px 28px 18px 28px;
        position: relative;
        min-height: 64px;
        box-sizing: border-box;
        border-bottom: 1.5px solid #e6eaf3;
        box-shadow: 0 2px 16px rgba(40, 60, 120, .09);
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .compliance-ribbon canvas {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: 0;
        pointer-events: none;
        display: block;
        border-radius: 16px 16px 0 0;
    }
    .compliance-ribbon h2 {
        position: relative;
        z-index: 1;
        margin: 0;
        font-size: 1.32em;
        font-weight: 700;
        color: #24305e;
        letter-spacing: .5px;
        font-family: 'Roboto', sans-serif;
    }
    .table-wrapper {
        width: 100%;
        padding: 0 28px 0 28px;
        box-sizing: border-box;
    }
    .bulk-email-controls { margin: 14px 0 14px 0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;}
    .bulk-btn { background: linear-gradient(90deg, #1976d2 0%, #5c91e6 100%); color: white; padding: 7px 16px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.97em; font-weight: 500; margin: 0 4px; transition: background 0.19s, box-shadow 0.19s; display: inline-block; white-space: nowrap; box-shadow: 0 2px 8px rgba(25,118,210,0.08); font-family: inherit;}
    .bulk-btn:disabled { background: #d3dbe9; color: #888; cursor: not-allowed;}
    .select-all-checkbox, .row-checkbox { accent-color: #1976d2; margin-right: 6px; height: 1em; width: 1em; vertical-align: middle; border-radius: 4px; border: 1px solid #c0d5eb;}
    .filter-select { min-width: 70px; font-size: 0.95em; margin-top: 2px; border-radius: 5px; border: 1px solid #c0d5eb; background: #f8fbfd; padding: 2px 7px;}
    .filter-search-input { width: 90%; min-width: 60px; font-size: 0.92em; margin: 2px 0 4px 0; border-radius: 4px; border: 1px solid #c0d5eb; background: #f8fbfd; padding: 2px 6px; }
    table { width: 100%; border-collapse: collapse; background: #f8fbfd; border-radius: 0 0 16px 16px; font-size: 0.96em; box-sizing: border-box; overflow: hidden;}
    thead { background: #f5f6fb; }
    th { position: sticky; top: 0; background: #e6eaf9; color: #222b4a; font-weight: 600; font-size: 0.93em; letter-spacing: 0.01em; padding: 8px 0 4px 0; border-bottom: 2px solid #c7d3ee; text-align: left; vertical-align: bottom; z-index: 2; box-shadow: 0 1px 0 #c7d3ee; text-transform: none;}
    th select.filter-select { font-weight: 400; font-size: 0.93em; margin-top: 0; margin-bottom: 0;}
    td { padding: 7px 0; border-bottom: 1.5px solid #e6eaf3; text-align: left; vertical-align: middle; font-size: 0.95em; background: #f8fbfd;}
    tr:last-child td { border-bottom: none; }
    #compliance-table tbody tr:hover { background: #e0f2fd; transition: background 0.18s;}
    .compliant-yes { color: #32b56c; font-weight: 600; }
    .compliant-no { color: #d32f2f; font-weight: 600; }
    .compliant-pending { color: #e9a100; font-weight: 600; }
    .compliant-soon { color: orange; font-weight: 600; }
    .icon-btn { background: none; border: none; cursor: pointer; padding: 0 4px; margin: 0; transition: background .13s; border-radius: 4px; vertical-align: middle; display: inline-flex; align-items: center; justify-content: center; position: relative;}
    .icon-btn:hover, .icon-btn:focus { background: #e6eaf9; }
    .icon-mail, .icon-escalate { width: 18px; height: 18px; color: #1976d2; display: inline-block; vertical-align: middle; transition: color .15s;}
    .icon-escalate { color: #b1322f; }
    .icon-btn:active .icon-mail, .icon-btn:active .icon-escalate { color: #24305e;}
    .icon-btn[title]:hover::after { content: attr(title); position: absolute; left: 50%; top: -2.1em; background: #222b4a; color: #fff; font-size: 0.92em; padding: 3px 9px; border-radius: 4px; white-space: nowrap; transform: translateX(-50%); opacity: 0.97; pointer-events: none; z-index: 10;}
    .email-success { color: #32b56c; margin-left: 6px; font-size: 0.93em; font-weight: 500; display: inline; animation: fadeOutMove 2s forwards;}
    @keyframes fadeOutMove { 0% { opacity: 1; transform: translateY(0);} 80% { opacity: 1; transform: translateY(-3px);} 100% { opacity: 0; transform: translateY(-8px);}}
    .pagination { margin: 13px 0 7px 0; display: flex; justify-content: flex-end; gap: 6px; flex-wrap: wrap;}
    .pagination a, .pagination span { display: inline-block; padding: 5px 12px; border-radius: 4px; background: #eff1f8; color: #24305e; text-decoration: none; margin: 0 2px; font-weight: 500; font-size: 0.97em; border: 1px solid #dce1ec; transition: background 0.12s, color 0.12s; cursor: pointer;}
    .pagination .current { background: #1976d2; color: #fff; border: 1.5px solid #1976d2;}
    .back-link { display: inline-block; margin-top: 18px; color: #1976d2; text-decoration: underline; font-weight: 500; font-size: 0.97em;}
    @media (max-width: 900px) {
      .main-grid { padding: 0 2vw 30px 2vw; }
      .compliance-card { padding: 0; }
      .compliance-ribbon { padding: 12px 4px 10px 4px; min-height: 60px; }
      .table-wrapper { padding: 0 2px 0 2px; }
      th, td { font-size: .95em; }
      .bulk-email-controls { flex-direction: column; align-items: flex-start; gap: 7px;}
      .filter-select { font-size: 0.90em; min-width: 48px; }
      .pagination { justify-content: center; }
    }
    

.ribbon {
    width: 100%;
    padding: 20px 30px; /* Increased horizontal padding */
    background-color: #f1f1f1;
    font-weight: bold;
    font-size: 18px;
}

.table th {
    padding: 12px 18px; /* Increased padding for better spacing */
    min-width: 120px;   /* Ensures clear column separation */
    background-color: #e9e9e9;
}
</style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="main-grid">
  <div class="compliance-card card">
    <div class="compliance-ribbon">
      <canvas id="ribbon-canvas"></canvas>
      <h2>Employee Training Assignment Compliance</h2>
    </div>
    <div class="table-wrapper">
      <div class="pagination pagination-top"></div>
      <div class="bulk-email-controls">
        <label>
          <input type="checkbox" class="select-all-checkbox" id="select-all">
          Select All Trainings For Email
        </label>
        <button class="bulk-btn" id="bulk-send" disabled>
          Send Reminder Email
        </button>
        <button class="bulk-btn" id="bulk-escalate" disabled>
          Escalate to Manager
        </button>
        <span id="bulk-email-status" style="margin-left:12px;color:#227644;font-size:0.97em;font-weight:500;display:none;"></span>
      </div>
      <table id="compliance-table">
        <thead>
          <tr>
            <th></th>
            <th>
              <div>employee</div>
              <input class="filter-search-input" type="text" id="search-employee" placeholder="Search...">
              <select class="filter-select" id="filter-employee">
                <option value="">All</option>
                <?php foreach ($employeeNames as $v): ?>
                  <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
              </select>
            </th>
            <th>
              <div>email</div>
              <input class="filter-search-input" type="text" id="search-email" placeholder="Search...">
              <select class="filter-select" id="filter-email">
                <option value="">All</option>
                <?php foreach ($emails as $v): ?>
                  <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
              </select>
            </th>
            <th>
              <div>training</div>
              <input class="filter-search-input" type="text" id="search-title" placeholder="Search...">
              <select class="filter-select" id="filter-title">
                <option value="">All</option>
                <?php foreach ($trainingTitles as $v): ?>
                  <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
              </select>
            </th>
            <th>
              <div>category</div>
              <input class="filter-search-input" type="text" id="search-category" placeholder="Search...">
              <select class="filter-select" id="filter-category">
                <option value="">All</option>
                <?php foreach ($categoryVals as $v): ?>
                  <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
              </select>
            </th>
            <th>
              <div>mandatory</div>
              <input class="filter-search-input" type="text" id="search-mandatory" placeholder="Search...">
              <select class="filter-select" id="filter-mandatory">
                <option value="">All</option>
                <?php foreach ($mandatoryVals as $v): ?>
                  <option value="<?= $v ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </th>
            <th>due</th>
            <th>status</th>
            <th>completed</th>
            <th>last reminder</th>
            <th>last escalation</th>
            <th>email</th>
            <th>escalate</th>
          </tr>
        </thead>
        <tbody>
        <!-- Rendered by JS -->
        </tbody>
      </table>
      <div class="pagination pagination-bottom"></div>
      <a href="dashboard.php" class="back-link">Back to Dashboard</a>
    </div>
  </div>
</div>
<script>
function resizeRibbonCanvas() {
    const ribbon = document.querySelector('.compliance-ribbon');
    const canvas = document.getElementById('ribbon-canvas');
    if (!canvas || !ribbon) return;
    canvas.width = ribbon.offsetWidth;
    canvas.height = ribbon.offsetHeight;
    const ctx = canvas.getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, canvas.width, 0);
    grad.addColorStop(0, "#aee3fa");
    grad.addColorStop(1, "#e6eaf9");
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.beginPath();
    ctx.moveTo(0, canvas.height * 0.65);
    ctx.bezierCurveTo(
        canvas.width * 0.25, canvas.height * 0.80,
        canvas.width * 0.75, canvas.height * 0.50,
        canvas.width, canvas.height * 0.75
    );
    ctx.lineTo(canvas.width, canvas.height);
    ctx.lineTo(0, canvas.height);
    ctx.closePath();
    ctx.globalAlpha = 0.14;
    ctx.fillStyle = "#1976d2";
    ctx.fill();
    ctx.globalAlpha = 1;
}
window.addEventListener('resize', resizeRibbonCanvas);
window.addEventListener('DOMContentLoaded', resizeRibbonCanvas);

const allRows = <?= json_encode($allRows, JSON_UNESCAPED_UNICODE) ?>;
const emailLogs = <?= json_encode($emailLogs, JSON_UNESCAPED_UNICODE) ?>;
const perPage = 15;

let filterState = {
  employee: "",
  email: "",
  title: "",
  category: "",
  mandatory: "",
  search_employee: "",
  search_email: "",
  search_title: "",
  search_category: "",
  search_mandatory: ""
};
function normalize(s) { return (s||"").toLowerCase(); }
function applyFilters(rows) {
  return rows.filter(function(row){
    let mandatoryStr = row.mandatory ? "Yes" : "No";
    let categoryLabel = getCategoryLabel(row);

    if (filterState.employee && row.employee_name !== filterState.employee) return false;
    if (filterState.email && row.email !== filterState.email) return false;
    if (filterState.title && row.training_title !== filterState.title) return false;
    if (filterState.mandatory && mandatoryStr !== filterState.mandatory) return false;
    if (filterState.category && categoryLabel !== filterState.category) return false;

    if (filterState.search_employee && normalize(row.employee_name).indexOf(normalize(filterState.search_employee)) === -1) return false;
    if (filterState.search_email && normalize(row.email).indexOf(normalize(filterState.search_email)) === -1) return false;
    if (filterState.search_title && normalize(row.training_title).indexOf(normalize(filterState.search_title)) === -1) return false;
    if (filterState.search_category && normalize(categoryLabel).indexOf(normalize(filterState.search_category)) === -1) return false;
    if (filterState.search_mandatory && normalize(mandatoryStr).indexOf(normalize(filterState.search_mandatory)) === -1) return false;

    return true;
  });
}
function formatDate(dateStr) {
    if (!dateStr) return "-";
    let d = new Date(dateStr.replace(/-/g, '/'));
    if (isNaN(d)) return "-";
    const month = d.toLocaleString('en-US', { month: 'short' });
    return d.getDate() + " " + month + ", " + d.getFullYear();
}
function timePart(dateStr) {
    if (!dateStr) return "";
    let d = new Date(dateStr.replace(/-/g, '/'));
    if (isNaN(d)) return "";
    return ('0'+d.getHours()).slice(-2) + ":" + ('0'+d.getMinutes()).slice(-2);
}
function capitalize(str) { return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''; }
function getCategoryLabel(row) {
    if (row.category_type === "general") {
        return row.general_sub_category ? capitalize(row.general_sub_category) : "General";
    } else if (row.category_type === "role") {
        return "Role based";
    }
    return "-";
}
function getComplianceStatus(row) {
    let due_date = row.due_date;
    let completed_at = row.completed_at;
    let now = new Date("<?= date('Y-m-d') ?>");
    let due_ts = due_date ? new Date(due_date.replace(/-/g, '/')) : null;
    let completed_ts = completed_at ? new Date(completed_at.replace(/-/g, '/')) : null;
    if (!due_ts) return ['-','na',''];
    if ((!completed_at || !completed_ts) && due_ts < now) return ['No','no','overdue'];
    if (completed_ts && completed_ts > due_ts) return ['No','no','overdue'];
    if (completed_ts && completed_ts <= due_ts) return ['Yes','yes',''];
    if (!completed_ts && due_ts >= now) {
        let pendingDays = workingDaysBetween(now, due_ts);
        if (pendingDays <= 5 && pendingDays >= 0) return ['Pending','pending','soon'];
        else return ['Pending','pending',''];
    }
    return ['-','na',''];
}
function workingDaysBetween(from, to) {
    let days = 0;
    let d = new Date(from);
    d.setHours(0,0,0,0);
    to = new Date(to);
    to.setHours(0,0,0,0);
    while (d <= to) {
        let weekday = d.getDay();
        if (weekday > 0 && weekday < 6) days++;
        d.setDate(d.getDate() + 1);
    }
    return days - 1;
}
function escapeHtml(s) {
    return s == null ? "" : String(s).replace(/[&<>"']/g, function (m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m];
    });
}
let currentPage = 1;
let filteredRows = allRows.slice();

function renderTable() {
    filteredRows = applyFilters(allRows);
    let totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));
    if (currentPage > totalPages) currentPage = totalPages;
    let pageRows = filteredRows.slice((currentPage-1)*perPage, currentPage*perPage);

    let tbody = document.querySelector('#compliance-table tbody');
    tbody.innerHTML = pageRows.map(function(row, i) {
        let [compliance, compliance_class, pending_type] = getComplianceStatus(row);
        let mandatoryStr = row.mandatory ? "Yes" : "No";
        let categoryLabel = getCategoryLabel(row);
        let completedAt = row.completed_at ? formatDate(row.completed_at) : "-";
        let rlog = (emailLogs[row.user_id] && emailLogs[row.user_id][row.training_title] && emailLogs[row.user_id][row.training_title]['reminder']) ? emailLogs[row.user_id][row.training_title]['reminder'] : null;
        let elog = (emailLogs[row.user_id] && emailLogs[row.user_id][row.training_title] && emailLogs[row.user_id][row.training_title]['escalation']) ? emailLogs[row.user_id][row.training_title]['escalation'] : null;
        let managerCell = row.manager_email ? `
            <button class="icon-btn escalate-btn" title="Escalate to Manager"
                data-user-id="${escapeHtml(row.user_id)}"
                data-to="${escapeHtml(row.manager_email)}"
                data-employee="${escapeHtml(row.employee_name)}"
                data-title="${escapeHtml(row.training_title)}"
                data-due="${escapeHtml(row.due_date)}"
                data-manager="${escapeHtml(row.manager_email)}">
                &#9888;
            </button>
            <span class="email-success" style="display:none;">Escalated!</span>
        ` : "-";
        return `<tr class="row-data"
            data-user-id="${escapeHtml(row.user_id)}"
            data-employee="${escapeHtml(row.employee_name)}"
            data-email="${escapeHtml(row.email)}"
            data-title="${escapeHtml(row.training_title)}"
            data-mandatory="${mandatoryStr}"
            data-category="${escapeHtml(categoryLabel)}">
            <td>
                <input type="checkbox" class="row-checkbox"
                    data-user-id="${escapeHtml(row.user_id)}"
                    data-to="${escapeHtml(row.email)}"
                    data-employee="${escapeHtml(row.employee_name)}"
                    data-title="${escapeHtml(row.training_title)}"
                    data-due="${escapeHtml(row.due_date)}"
                    data-manager="${escapeHtml(row.manager_email)}">
            </td>
            <td>${escapeHtml(row.employee_name)}</td>
            <td>${escapeHtml(row.email)}</td>
            <td>${escapeHtml(row.training_title)}</td>
            <td>${escapeHtml(categoryLabel)}</td>
            <td>${mandatoryStr}</td>
            <td>${formatDate(row.due_date)}</td>
            <td>${
                compliance_class === 'yes'      ? '<span class="compliant-yes">Compliant</span>' :
                compliance_class === 'no'       ? '<span class="compliant-no">Not Compliant</span>' :
                (compliance_class === 'pending' && pending_type === 'soon') ? '<span class="compliant-soon">Pending</span>' :
                (compliance_class === 'pending') ? '<span class="compliant-pending">Pending</span>' :
                '-'
            }</td>
            <td>${completedAt}</td>
            <td>${rlog ? formatDate(rlog) + "<br><span style='font-size:90%;color:#888'>" + timePart(rlog) + "</span>" : "-"}</td>
            <td>${elog ? formatDate(elog) + "<br><span style='font-size:90%;color:#888'>" + timePart(elog) + "</span>" : "-"}</td>
            <td>
                <button class="icon-btn send-mail-btn" title="Send Email"
                    data-user-id="${escapeHtml(row.user_id)}"
                    data-to="${escapeHtml(row.email)}"
                    data-employee="${escapeHtml(row.employee_name)}"
                    data-title="${escapeHtml(row.training_title)}"
                    data-due="${escapeHtml(row.due_date)}"
                    data-manager="${escapeHtml(row.manager_email)}">
                    &#9993;
                </button>
                <span class="email-success" style="display:none;">Sent!</span>
            </td>
            <td>${managerCell}</td>
        </tr>`;
    }).join('');
    updateBulkBtns();
    bindRowEvents();
    renderPagination(totalPages);
}

function renderPagination(totalPages) {
    let pagDivs = document.querySelectorAll('.pagination');
    pagDivs.forEach(function(pagDiv) {
        let html = '';
        if (currentPage > 1) {
            html += `<a href="#" data-page="1">&laquo; First</a>`;
            html += `<a href="#" data-page="${currentPage-1}">&lt; Prev</a>`;
        }
        for (let p=1; p<=totalPages; p++) {
            if (p === currentPage) html += `<span class="current">${p}</span>`;
            else html += `<a href="#" data-page="${p}">${p}</a>`;
        }
        if (currentPage < totalPages) {
            html += `<a href="#" data-page="${currentPage+1}">Next &gt;</a>`;
            html += `<a href="#" data-page="${totalPages}">Last &raquo;</a>`;
        }
        pagDiv.innerHTML = html;
        pagDiv.querySelectorAll('a[data-page]').forEach(a => {
            a.onclick = function(e) {
                e.preventDefault();
                currentPage = parseInt(this.getAttribute('data-page'));
                renderTable();
            };
        });
    });
}
function addFilterHandlers() {
  [
    {input: "search-employee", filter: "search_employee"},
    {input: "search-email", filter: "search_email"},
    {input: "search-title", filter: "search_title"},
    {input: "search-category", filter: "search_category"},
    {input: "search-mandatory", filter: "search_mandatory"}
  ].forEach(function(f) {
    let inp = document.getElementById(f.input);
    if (inp) inp.addEventListener("input", function() {
      filterState[f.filter] = inp.value.trim();
      currentPage = 1;
      renderTable();
    });
  });
  [
    {select: "filter-employee", filter: "employee"},
    {select: "filter-email", filter: "email"},
    {select: "filter-title", filter: "title"},
    {select: "filter-category", filter: "category"},
    {select: "filter-mandatory", filter: "mandatory"}
  ].forEach(function(f) {
    let sel = document.getElementById(f.select);
    if (sel) sel.addEventListener("change", function() {
      filterState[f.filter] = sel.value;
      currentPage = 1;
      renderTable();
    });
  });
}
addFilterHandlers();

document.getElementById('select-all').addEventListener('change', function() {
    let checked = this.checked;
    document.querySelectorAll('#compliance-table tbody tr').forEach(function(row) {
        let cb = row.querySelector('.row-checkbox');
        if (cb) cb.checked = checked;
    });
    updateBulkBtns();
});
function updateBulkBtns() {
    const checked = Array.from(document.querySelectorAll('#compliance-table tbody .row-checkbox')).filter(cb => cb.checked);
    document.getElementById('bulk-send').disabled = checked.length === 0;
    document.getElementById('bulk-escalate').disabled = checked.length === 0;
}
function bindRowEvents() {
    document.querySelectorAll(".send-mail-btn").forEach(function(btn){
        btn.onclick = function(){
            var emailBtn = this;
            var emailSuccess = emailBtn.parentElement.querySelector(".email-success");
            emailBtn.disabled = true;
            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    send_email: 1,
                    user_id: emailBtn.getAttribute("data-user-id"),
                    to: emailBtn.getAttribute("data-to"),
                    employee_name: emailBtn.getAttribute("data-employee"),
                    training_title: emailBtn.getAttribute("data-title"),
                    due_date: emailBtn.getAttribute("data-due"),
                    manager_email: emailBtn.getAttribute("data-manager")
                })
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    emailSuccess.textContent = "Sent!";
                    emailSuccess.style.display = "inline";
                    emailSuccess.classList.remove("email-success");
                    void emailSuccess.offsetWidth;
                    emailSuccess.classList.add("email-success");
                    setTimeout(() => { emailSuccess.style.display = "none"; emailBtn.disabled = false; }, 2000);
                } else {
                    alert("Failed to send email.");
                    emailBtn.disabled = false;
                }
            })
            .catch(() => {
                alert("Failed to send email.");
                emailBtn.disabled = false;
            });
        };
    });
    document.querySelectorAll(".escalate-btn").forEach(function(btn){
        btn.onclick = function(){
            var emailBtn = this;
            var emailSuccess = emailBtn.parentElement.querySelector(".email-success");
            emailBtn.disabled = true;
            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    send_email: 1,
                    user_id: emailBtn.getAttribute("data-user-id"),
                    to: emailBtn.getAttribute("data-to"),
                    employee_name: emailBtn.getAttribute("data-employee"),
                    training_title: emailBtn.getAttribute("data-title"),
                    due_date: emailBtn.getAttribute("data-due"),
                    manager_email: emailBtn.getAttribute("data-manager"),
                    escalate: 1
                })
            })
            .then(resp => resp.json())
            .then(data => {
                if (data.success) {
                    emailSuccess.textContent = "Escalated!";
                    emailSuccess.style.display = "inline";
                    emailSuccess.classList.remove("email-success");
                    void emailSuccess.offsetWidth;
                    emailSuccess.classList.add("email-success");
                    setTimeout(() => { emailSuccess.style.display = "none"; emailBtn.disabled = false; }, 2000);
                } else {
                    alert("Failed to send escalation email.");
                    emailBtn.disabled = false;
                }
            })
            .catch(() => {
                alert("Failed to send escalation email.");
                emailBtn.disabled = false;
            });
        };
    });
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.onchange = updateBulkBtns;
    });
}
document.getElementById('bulk-send').addEventListener('click', function() {
    const checked = Array.from(document.querySelectorAll('#compliance-table tbody .row-checkbox')).filter(cb => cb.checked);
    if (checked.length === 0) return;
    this.disabled = true;
    let bulkStatus = document.getElementById('bulk-email-status');
    bulkStatus.style.display = "inline";
    bulkStatus.textContent = "Sending...";
    let rows = checked.map(cb => ({
        user_id: cb.getAttribute("data-user-id"),
        to: cb.getAttribute("data-to"),
        employee_name: cb.getAttribute("data-employee"),
        training_title: cb.getAttribute("data-title"),
        due_date: cb.getAttribute("data-due"),
        manager_email: cb.getAttribute("data-manager")
    }));
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            bulk_send_email: 1,
            rows: JSON.stringify(rows)
        })
    })
    .then(resp => resp.json())
    .then(data => {
        bulkStatus.textContent = data.success ? "Bulk email sent!" : "Failed to send.";
        setTimeout(() => { bulkStatus.style.display = "none"; document.getElementById('bulk-send').disabled = false; }, 2300);
    })
    .catch(() => {
        bulkStatus.textContent = "Failed to send.";
        setTimeout(() => { bulkStatus.style.display = "none"; document.getElementById('bulk-send').disabled = false; }, 2300);
    });
});
document.getElementById('bulk-escalate').addEventListener('click', function() {
    const checked = Array.from(document.querySelectorAll('#compliance-table tbody .row-checkbox')).filter(cb => cb.checked);
    if (checked.length === 0) return;
    this.disabled = true;
    let bulkStatus = document.getElementById('bulk-email-status');
    bulkStatus.style.display = "inline";
    bulkStatus.textContent = "Sending escalation...";
    let rows = checked.map(cb => ({
        user_id: cb.getAttribute("data-user-id"),
        to: cb.getAttribute("data-manager"),
        employee_name: cb.getAttribute("data-employee"),
        training_title: cb.getAttribute("data-title"),
        due_date: cb.getAttribute("data-due"),
        manager_email: cb.getAttribute("data-manager")
    }));
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            bulk_escalate_email: 1,
            rows: JSON.stringify(rows)
        })
    })
    .then(resp => resp.json())
    .then(data => {
        bulkStatus.textContent = data.success ? "Bulk escalation sent!" : "Failed to escalate.";
        setTimeout(() => { bulkStatus.style.display = "none"; document.getElementById('bulk-escalate').disabled = false; }, 2300);
    })
    .catch(() => {
        bulkStatus.textContent = "Failed to escalate.";
        setTimeout(() => { bulkStatus.style.display = "none"; document.getElementById('bulk-escalate').disabled = false; }, 2300);
    });
});
renderTable();
</script>
</body>
</html>