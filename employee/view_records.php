<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

requireEmployeeLogin();

// Get employee info
$stmt = $conn->prepare("SELECT * FROM employee WHERE employee_id = ?");
$stmt->execute([$_SESSION['employee_id']]);
$employee = $stmt->fetch();

// Get filter parameters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t'); // Last day of current month

// Get time records with official time
$stmt = $conn->prepare("
    SELECT tr.*
    FROM time_record tr
    WHERE tr.employee_id = ? 
    AND DATE(tr.record_date) BETWEEN ? AND ?
    ORDER BY tr.record_date DESC
");
$stmt->execute([$_SESSION['employee_id'], $startDate, $endDate]);
$records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Time Records - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
   <?php require_once '../includes/header.php'; ?>

    <?php require_once '../includes/employee_sidebar.php'; ?>
    <div class="container-fluid mt-4">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>My Time Records</h2>
            </div>

        <!-- Filter Card -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <a href="../print/employee_timesheet.php?year=<?php echo date('Y', strtotime($startDate)); ?>&month=<?php echo date('m', strtotime($startDate)); ?>" 
                               target="_blank" class="btn btn-success">
                                <i class="bi bi-printer"></i> Print Monthly Timesheet
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Records Table -->
        <div class="card">
            <div class="card-body">
                <?php if (count($records) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>AM In</th>
                                    <th>AM In Status</th>
                                    <th>AM Out</th>
                                    <th>AM Out Status</th>
                                    <th>PM In</th>
                                    <th>PM In Status</th>
                                    <th>PM Out</th>
                                    <th>PM Out Status</th>
                                    <th>Total Hours</th>
                                    <th>Edited</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <?php
                                    $metrics = getRecordMetrics($conn, $record);
                                    $amHours = getTotalHours($record['am_in'], $record['am_out']);
                                    $pmHours = getTotalHours($record['pm_in'], $record['pm_out']);
                                    $totalHours = $amHours + $pmHours;
                                    ?>
                                    <tr>
                                        <td><?php echo formatDate($record['record_date'], 'M d, Y'); ?></td>
                                        <td><?php echo formatTime($record['am_in']); ?></td>
                                        <td>
                                            <?php if ($metrics && !empty($metrics['am_in']['status'])): ?>
                                                <span class="badge" style="<?php echo getBadgeStyle($metrics['am_in']['status']); ?>">
                                                    <?php echo $metrics['am_in']['status']; ?>
                                                </span>
                                                <div class="small text-muted">
                                                    <?php
                                                        if (!empty($metrics['am_in']['value']) && !empty($metrics['am_in']['official'])) {
                                                            echo differenceText($metrics['am_in']['value'], $metrics['am_in']['official'], $metrics['am_in']['status']);
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
                                                        if (!empty($metrics['am_out']['value']) && !empty($metrics['am_out']['official'])) {
                                                            echo differenceText($metrics['am_out']['value'], $metrics['am_out']['official'], $metrics['am_out']['status']);
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
                                                        if (!empty($metrics['pm_in']['value']) && !empty($metrics['pm_in']['official'])) {
                                                            echo differenceText($metrics['pm_in']['value'], $metrics['pm_in']['official'], $metrics['pm_in']['status']);
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
                                                        if (!empty($metrics['pm_out']['value']) && !empty($metrics['pm_out']['official'])) {
                                                            echo differenceText($metrics['pm_out']['value'], $metrics['pm_out']['official'], $metrics['pm_out']['status']);
                                                        }
                                                    ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($totalHours, 2); ?> hrs</td>
                                        <td>
                                            <?php
                                            // Show 'Edited' badge if record has approved TIME_REASON and REASON_AUDIT (not admin correction)
                                            $isEdited = false;
                                            $stmt = $conn->prepare("SELECT COUNT(*) FROM time_reason r JOIN time_approval ap ON r.reason_id = ap.reason_id AND ap.approval_status = 'approved' JOIN reason_audit a ON r.reason_id = a.reason_id WHERE r.record_id = ? AND r.reason_text != 'Admin Correction'");
                                            $stmt->execute([$record['record_id']]);
                                            if ($stmt->fetchColumn() > 0) $isEdited = true;
                                            ?>
                                            <?php if ($isEdited): ?>
                                                <span class="badge bg-warning text-dark">Edited</span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h5>Summary</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Total Records:</strong> <?php echo count($records); ?> days
                            </div>
                            <div class="col-md-4">
                                <strong>Total Hours:</strong> 
                                <?php 
                                $totalAllHours = 0;
                                foreach ($records as $record) {
                                    $totalAllHours += getTotalHours($record['am_in'], $record['am_out']);
                                    $totalAllHours += getTotalHours($record['pm_in'], $record['pm_out']);
                                }
                                echo number_format($totalAllHours, 2);
                                ?> hours
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No time records found for the selected date range.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>