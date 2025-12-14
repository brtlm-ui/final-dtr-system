<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/timezone.php';

requireAdminLogin();

// --- KPI defaults & retrieval ---
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days', strtotime($endDate)));
$employeeId = $_GET['employee_id'] ?? null;

$onTime = kpi_on_time_rate($conn, $startDate, $endDate, $employeeId);
$avgHours = kpi_average_daily_hours($conn, $startDate, $endDate, $employeeId);
$overtime = kpi_total_overtime_hours($conn, $startDate, $endDate, $employeeId);
$avgLateness = kpi_average_lateness($conn, $startDate, $endDate, $employeeId);
$lateCount = kpi_late_incidents_count($conn, $startDate, $endDate, $employeeId);
$pendingReasonsKpi = kpi_pending_reasons_count($conn, $startDate, $endDate, $employeeId);

// Employees for filter
$empStmt = $conn->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM employee WHERE is_active = 1 ORDER BY first_name");
$employees = $empStmt->fetchAll();

// Get statistics (legacy)
$stmt = $conn->query("SELECT COUNT(*) as total FROM employee WHERE is_active = 1");
$totalEmployees = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM time_record WHERE DATE(created_at) = CURDATE()");
$todayRecords = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM time_record");
$totalRecords = $stmt->fetch()['total'];

// Get pending reasons count (new logic: count from time_reason/time_approval)
$stmt = $conn->query("
    SELECT COUNT(*) as total FROM time_reason r
    LEFT JOIN time_approval a ON r.reason_id = a.reason_id
    WHERE (a.approval_status IS NULL OR a.approval_status = 'pending')
");
$pendingReasons = $stmt->fetch()['total'];

// Get recent records
$stmt = $conn->query("
    SELECT tr.*, e.first_name, e.last_name, e.employee_id as emp_id
    FROM time_record tr
    JOIN employee e ON tr.employee_id = e.employee_id
    ORDER BY tr.created_at DESC
    LIMIT 10
");
$recentRecords = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <!-- Navbar -->
    <?php
// Include header
require_once '../includes/header.php';
?>

    <?php require_once '../includes/sidebar.php'; ?>
    <div class="container-fluid mt-4">
        <!-- Main Content -->
        <div class="main-content">
                <div class="row">
                    <div class="col-12">
                        <h2 class="mb-4">Admin Dashboard</h2>
                    </div>
                </div>
                <!-- KPI Cards & Filters -->
                <div class="row mb-3">
                    <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <h4 class="mb-0">Key Performance Indicators</h4>
                        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" class="form-control form-control-sm" style="width: auto;">
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" class="form-control form-control-sm" style="width: auto;">
                            <select name="employee_id" class="form-select form-select-sm" style="width: auto;">
                                <option value=""> All Employees </option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>" <?php echo $employeeId == $emp['employee_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-primary btn-sm">Refresh</button>
                        </form>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="card p-3">
                            <h6>On-time Rate</h6>
                            <h4><?php echo $onTime['pct'] !== null ? $onTime['pct'] . '%' : '—'; ?></h4>
                            <small><?php echo $onTime['numerator']; ?> of <?php echo $onTime['denominator']; ?> entries</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3">
                            <h6>Avg Daily Hours</h6>
                            <h4><?php echo $avgHours['avg_hours'] !== null ? $avgHours['avg_hours'] . ' hrs' : '—'; ?></h4>
                            <small>Average per record</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3">
                            <h6>Total Overtime</h6>
                            <h4><?php echo $overtime['overtime_hours']; ?> hrs</h4>
                            <small><?php echo $overtime['overtime_minutes']; ?> minutes</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card p-3">
                            <h6>Pending Reasons</h6>
                            <h4><?php echo $pendingReasonsKpi['pending_reasons']; ?></h4>
                            <small><a href="manage_reasons.php">Manage Reasons</a></small>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card p-3">
                            <h6>Average Lateness (mins)</h6>
                            <h4><?php echo $avgLateness['avg_late_minutes'] !== null ? $avgLateness['avg_late_minutes'] . ' mins' : '—'; ?></h4>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card p-3">
                            <h6>Late Incidents</h6>
                            <h4><?php echo $lateCount['late_incidents']; ?></h4>
                        </div>
                    </div>
                </div>

                <!-- Trend Chart -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">On-time Rate (Daily)</h6>
                                <small class="text-muted">Last 30 days</small>
                            </div>
                            <div style="height:260px;">
                                <canvas id="onTimeChart" height="220"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Records -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card recent-records-card shadow-sm">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Recent Time Records</h5>
                                <small class="opacity-75">Latest entries</small>
                            </div>
                            <div class="card-body pt-3 pb-3">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 align-middle recent-records-table">
                                       <thead class="table-light">
    <tr>
        <th>Employee ID</th>
        <th>Name</th>
        <th>Date</th>
        <th>AM In</th>
        <th>AM In Status</th>
        <th>AM Out</th>
        <th>AM Out Status</th>
        <th>PM In</th>
        <th>PM In Status</th>
        <th>PM Out</th>
        <th>PM Out Status</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($recentRecords as $record): ?>
        <?php $metrics = getRecordMetrics($conn, $record); ?>
        <tr>
            <td><?php echo htmlspecialchars($record['emp_id']); ?></td>
            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
            <td><?php echo formatDate($record['created_at'], 'M d, Y'); ?></td>
            <td><?php echo formatTime($record['am_in']); ?></td>
            <td>
                <?php if ($metrics && !empty($metrics['am_in']['status'])): ?>
                    <span class="badge" style="<?php echo getBadgeStyle($metrics['am_in']['status']); ?>">
                        <?php echo $metrics['am_in']['status']; ?>
                    </span>
                    <div class="small text-muted">
                        <?php
                            if (!empty($metrics['am_in']['value_datetime']) && !empty($metrics['am_in']['official'])) {
                                echo differenceText($metrics['am_in']['value_datetime'], $metrics['am_in']['official'], $metrics['am_in']['status']);
                            }
                        ?>
                    </div>
                <?php endif; ?>
            </td>
            <td><?php echo formatTime($record['am_out']); ?></td>
            <td>
                <?php if ($metrics && !empty($metrics['am_out']['status'])): ?>
                    <span class="badge" style="<?php echo getBadgeStyle($metrics['am_out']['status']); ?>">
                        <?php echo $metrics['am_out']['status']; ?>
                    </span>
                    <div class="small text-muted">
                        <?php
                            if (!empty($metrics['am_out']['value_datetime']) && !empty($metrics['am_out']['official'])) {
                                echo differenceText($metrics['am_out']['value_datetime'], $metrics['am_out']['official'], $metrics['am_out']['status']);
                            }
                        ?>
                    </div>
                <?php endif; ?>
            </td>
            <td><?php echo formatTime($record['pm_in']); ?></td>
            <td>
                <?php if ($metrics && !empty($metrics['pm_in']['status'])): ?>
                    <span class="badge" style="<?php echo getBadgeStyle($metrics['pm_in']['status']); ?>">
                        <?php echo $metrics['pm_in']['status']; ?>
                    </span>
                    <div class="small text-muted">
                        <?php
                            if (!empty($metrics['pm_in']['value_datetime']) && !empty($metrics['pm_in']['official'])) {
                                echo differenceText($metrics['pm_in']['value_datetime'], $metrics['pm_in']['official'], $metrics['pm_in']['status']);
                            }
                        ?>
                    </div>
                <?php endif; ?>
            </td>
            <td><?php echo formatTime($record['pm_out']); ?></td>
            <td>
                <?php if ($metrics && !empty($metrics['pm_out']['status'])): ?>
                    <span class="badge" style="<?php echo getBadgeStyle($metrics['pm_out']['status']); ?>">
                        <?php echo $metrics['pm_out']['status']; ?>
                    </span>
                    <div class="small text-muted">
                        <?php
                            if (!empty($metrics['pm_out']['value_datetime']) && !empty($metrics['pm_out']['official'])) {
                                echo differenceText($metrics['pm_out']['value_datetime'], $metrics['pm_out']['official'], $metrics['pm_out']['status']);
                            }
                        ?>
                    </div>
                <?php endif; ?>
            </td>
            <td>
                <a href="edit_record.php?id=<?php echo $record['record_id']; ?>" class="btn btn-sm btn-warning">
                    <i class="bi bi-pencil-fill"></i>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                                    </table>
                                </div>
                                <div class="text-end mt-3">
                                    <a href="view_all_records.php" class="btn btn-primary btn-sm px-4">View All Records →</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    const start = '<?php echo $startDate; ?>';
    const end = '<?php echo $endDate; ?>';
    const employee = '<?php echo $employeeId; ?>';
    const url = new URL('../api/kpi_timeseries.php', window.location.origin + window.location.pathname);
    url.searchParams.set('metric','on_time_rate');
    url.searchParams.set('start_date', start);
    url.searchParams.set('end_date', end);
    if (employee) url.searchParams.set('employee_id', employee);

    fetch(url.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                console.error('KPI timeseries error', data.message);
                return;
            }
            const labels = data.labels || [];
            const values = data.values || [];
            const ctx = document.getElementById('onTimeChart');
            if (!ctx) return;
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'On-time Rate (%)',
                        data: values,
                        spanGaps: true,
                        borderColor: 'rgba(59,130,246,1)',
                        backgroundColor: 'rgba(59,130,246,0.08)',
                        tension: 0.25,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, max: 100 }, x: {} }
                }
            });
        })
        .catch(err => console.error('KPI timeseries fetch error', err));
})();

// Initialize Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});
</script>
</body>
</html>