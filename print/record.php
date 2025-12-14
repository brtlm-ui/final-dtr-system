<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Allow admin or the employee themselves to print
$recordId = $_GET['id'] ?? null;
if (!$recordId) { echo 'Record ID required'; exit; }


$stmt = $conn->prepare("SELECT tr.*, e.first_name, e.last_name, e.employee_id as emp_id,
    COALESCE(e.position_id, p.position_name, '') AS position,
    COALESCE(e.department_id, d.department_name, '') AS department
    FROM time_record tr
    JOIN employee e ON tr.employee_id = e.employee_id
    LEFT JOIN position p ON e.position_id = p.position_id
    LEFT JOIN department d ON e.department_id = d.department_id
    WHERE tr.record_id = ?");
$stmt->execute([$recordId]);
$rec = $stmt->fetch();
if (!$rec) { echo 'Record not found'; exit; }

// Compute metrics for this record
$metrics = getRecordMetrics($conn, $rec);

// Basic permission: allow if admin or same employee
$allow = false;
if (isAdminLoggedIn()) $allow = true;
if (isset($_SESSION['employee_id']) && $_SESSION['employee_id'] == $rec['employee_id']) $allow = true;
if (!$allow) { echo 'Unauthorized'; exit; }

?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Printable Record - <?php echo htmlspecialchars($rec['first_name'].' '.$rec['last_name']); ?></title>
  <link rel="stylesheet" href="../assets/css/print.css">
  <style>
    @media print {
        .no-print { display: none; }
    }
  </style>
</head>
<body>
<div class="no-print" style="margin: 20px; text-align: right;">
    <button onclick="window.print()" style="padding: 8px 16px; background: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">Print</button>
    <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">Close</button>
</div>

<div class="container-print">
    <div class="header">
        <img src="../assets/images/logo.png" alt="Company" class="company-logo" onerror="this.style.display='none'">
        <h2>Daily Time Record</h2>
        <div><?php echo htmlspecialchars($rec['first_name'].' '.$rec['last_name']); ?> — <?php echo htmlspecialchars($rec['emp_id']); ?></div>
        <div class="small">Date: <?php echo htmlspecialchars(date('F d, Y', strtotime($rec['record_date'] ?? $rec['created_at'] ?? ''))); ?></div>
    </div>

    <table class="table-print">
        <thead>
            <tr>
                <th>Period</th>
                <th>Time</th>
                <th>Status</th>
                <th>Difference</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>AM In</td>
                <td><?php echo formatTime($rec['am_in']); ?></td>
                <td><?php echo $metrics['am_in']['status'] ?? '--'; ?></td>
                <td><?php echo !empty($metrics['am_in']) ? htmlspecialchars(differenceText($metrics['am_in']['value'] ?? null, $metrics['am_in']['official'] ?? null, $metrics['am_in']['status'] ?? null)) : '--'; ?></td>
                <td><?php echo htmlspecialchars($metrics['am_in']['reason'] ?? ''); ?></td>
            </tr>
            <tr>
                <td>AM Out</td>
                <td><?php echo formatTime($rec['am_out']); ?></td>
                <td><?php echo $metrics['am_out']['status'] ?? '--'; ?></td>
                <td><?php echo !empty($metrics['am_out']) ? htmlspecialchars(differenceText($metrics['am_out']['value'] ?? null, $metrics['am_out']['official'] ?? null, $metrics['am_out']['status'] ?? null)) : '--'; ?></td>
                <td><?php echo htmlspecialchars($metrics['am_out']['reason'] ?? ''); ?></td>
            </tr>
            <tr>
                <td>PM In</td>
                <td><?php echo formatTime($rec['pm_in']); ?></td>
                <td><?php echo $metrics['pm_in']['status'] ?? '--'; ?></td>
                <td><?php echo !empty($metrics['pm_in']) ? htmlspecialchars(differenceText($metrics['pm_in']['value'] ?? null, $metrics['pm_in']['official'] ?? null, $metrics['pm_in']['status'] ?? null)) : '--'; ?></td>
                <td><?php echo htmlspecialchars($metrics['pm_in']['reason'] ?? ''); ?></td>
            </tr>
            <tr>
                <td>PM Out</td>
                <td><?php echo formatTime($rec['pm_out']); ?></td>
                <td><?php echo $metrics['pm_out']['status'] ?? '--'; ?></td>
                <td><?php echo !empty($metrics['pm_out']) ? htmlspecialchars(differenceText($metrics['pm_out']['value'] ?? null, $metrics['pm_out']['official'] ?? null, $metrics['pm_out']['status'] ?? null)) : '--'; ?></td>
                <td><?php echo htmlspecialchars($metrics['pm_out']['reason'] ?? ''); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="signature">
        <div class="box">
            _______________________<br>
            Employee Signature
        </div>
        <div class="box">
            _______________________<br>
            Supervisor Signature
        </div>
    </div>

    <div class="no-print" style="margin-top:20px; text-align:center;">
        <button onclick="window.print()" class="btn btn-primary">Print</button>
    </div>
</div>
</body>
</html>