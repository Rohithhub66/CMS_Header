<?php
include 'db.php';

// Get all Functions, Role Areas, and Roles with counts
$functions = $pdo->query("SELECT id, name FROM functions ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// For Pie Chart: Trainings per Function
$funcCounts = $pdo->query("
    SELECT f.name as function_name, COUNT(t.id) as total
    FROM functions f
    LEFT JOIN trainings t ON t.function_id = f.id
    GROUP BY f.id
    ORDER BY f.name
")->fetchAll(PDO::FETCH_ASSOC);

// For Table: Function > Role Area > Role (with training counts)
$stmt = $pdo->query("
    SELECT
        f.name AS function_name,
        ra.name AS role_area_name,
        r.name AS role_name,
        COUNT(t.id) AS training_count
    FROM functions f
    LEFT JOIN trainings t ON t.function_id = f.id
    LEFT JOIN role_areas ra ON t.role_area_id = ra.id
    LEFT JOIN roles r ON t.role_id = r.id
    GROUP BY f.id, ra.id, r.id
    ORDER BY f.name, ra.name, r.name
");
$tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Training Summary - Pie Chart & Table</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .summary-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 1px 10px rgba(40, 60, 120, 0.10);
            padding: 32px 32px;
            margin-top: 16px;
            min-height: 100px;
            color: #222;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }
        .flex-row {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            justify-content: space-between;
            align-items: flex-start;
        }
        .chart-block {
            flex: 1 1 320px;
            max-width: 400px;
        }
        .table-block {
            flex: 2 1 520px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 16px;
        }
        th, td {
            border: 1px solid #dde7f7;
            padding: 7px 14px;
            text-align: left;
        }
        th {
            background: #e9f3fe;
            color: #1976d2;
        }
        tr:nth-child(even) {
            background: #f8fbfd;
        }
        .func-header {
            font-weight: bold;
            color: #1976d2;
        }
        .role-area-header {
            font-weight: 500;
            color: #386bcb;
        }
    </style>
</head>
<body>
<div class="summary-container">
    <h3>Trainings Distribution by Function (Pie Chart)</h3>
    <div class="flex-row">
        <div class="chart-block">
            <canvas id="trainingPieChart" width="380" height="380"></canvas>
        </div>
        <div class="table-block">
            <h4>Functions, Role Areas, and Roles</h4>
            <table>
                <thead>
                    <tr>
                        <th>Function</th>
                        <th>Role Area</th>
                        <th>Role</th>
                        <th># Trainings</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $lastFunc = $lastArea = '';
                    foreach ($tableData as $row):
                        $funcCell = $row['function_name'] !== $lastFunc ? '<td class="func-header">'.htmlspecialchars($row['function_name']).'</td>' : '<td></td>';
                        $areaCell = $row['role_area_name'] !== $lastArea || $row['function_name'] !== $lastFunc
                            ? '<td class="role-area-header">'.htmlspecialchars($row['role_area_name']).'</td>' : '<td></td>';
                        echo "<tr>
                                $funcCell
                                $areaCell
                                <td>".htmlspecialchars($row['role_name'])."</td>
                                <td align='center'>".$row['training_count']."</td>
                              </tr>";
                        $lastFunc = $row['function_name'];
                        $lastArea = $row['role_area_name'];
                    endforeach;
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    // Pie chart data from PHP
    const pieLabels = <?= json_encode(array_column($funcCounts, 'function_name')) ?>;
    const pieData = <?= json_encode(array_map('intval', array_column($funcCounts, 'total'))) ?>;
    // Generate nice colors
    function getColorPalette(len) {
        const palette = [
            "#5c91e6", "#f27474", "#48d490", "#f3c679", "#b97fd6", "#ff9f43", "#43c6ac", "#1e90ff", "#e35d6a", "#7e57c2"
        ];
        let arr = [];
        for(let i=0; i<len; i++) arr.push(palette[i%palette.length]);
        return arr;
    }
    const ctx = document.getElementById('trainingPieChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieData,
                backgroundColor: getColorPalette(pieLabels.length),
                borderWidth: 1
            }]
        },
        options: {
            responsive: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let value = context.parsed;
                            let percent = total === 0 ? 0 : (value / total * 100).toFixed(1);
                            return `${context.label}: ${value} trainings (${percent}%)`;
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>