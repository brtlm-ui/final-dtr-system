<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Employee only - can only print their own timesheet
requireEmployeeLogin();

$employeeId = $_SESSION['employee_id'];
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

$startDate = date('Y-m-d', strtotime("{$year}-{$month}-01"));
$endDate = date('Y-m-t', strtotime($startDate));

// Get employee info
$stmtEmp = $conn->prepare("SELECT employee_id, CONCAT(first_name,' ',last_name) as name, department_id, position_id FROM employee WHERE employee_id = ?");
$stmtEmp->execute([$employeeId]);
$emp = $stmtEmp->fetch();

// Get department and position
$dept = '';
$pos = '';
if ($emp['department_id']) {
    $d = $conn->prepare("SELECT department_name FROM department WHERE department_id = ?");
    $d->execute([$emp['department_id']]);
    $dept = $d->fetchColumn();
}
if ($emp['position_id']) {
    $p = $conn->prepare("SELECT position_name FROM position WHERE position_id = ?");
    $p->execute([$emp['position_id']]);
    $pos = $p->fetchColumn();
}

// Get time records
$stmt = $conn->prepare("SELECT tr.* FROM time_record tr WHERE tr.employee_id = ? AND DATE(tr.record_date) BETWEEN ? AND ? ORDER BY tr.record_date ASC");
$stmt->execute([$employeeId, $startDate, $endDate]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build day map
$daysInMonth = date('t', strtotime($startDate));
$map = [];
foreach ($rows as $r) {
    $day = (int)date('j', strtotime($r['record_date']));
    $map[$day] = $r;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Personal Monthly Timesheet - <?php echo htmlspecialchars($emp['name']); ?></title>
    <link rel="stylesheet" href="../assets/css/print.css">
    <style>
        @media print {
            .no-print { display: none; }
        }
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .emp-details {
            margin: 15px 0;
            font-size: 14px;
        }
        .emp-details div {
            margin: 5px 0;
        }
    </style>
</head>
<body>
<div class="no-print" style="margin: 20px; text-align: right;">
    <button onclick="window.print()" style="padding: 8px 16px; background: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">Print</button>
    <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">Close</button>
</div>

<div class="container-print">
    <div class="print-header">
        <h2>DAILY TIME RECORD</h2>
        <h3><?php echo date('F Y', strtotime($startDate)); ?></h3>
    </div>

    <div class="emp-details">
        <div><strong>Employee Name:</strong> <?php echo htmlspecialchars($emp['name']); ?></div>
        <div><strong>Employee ID:</strong> <?php echo htmlspecialchars($emp['employee_id']); ?></div>
        <?php if ($dept): ?><div><strong>Department:</strong> <?php echo htmlspecialchars($dept); ?></div><?php endif; ?>
        <?php if ($pos): ?><div><strong>Position:</strong> <?php echo htmlspecialchars($pos); ?></div><?php endif; ?>
    </div>

    <table class="table-print">
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>AM In</th>
                <th>AM Out</th>
                <th>PM In</th>
                <th>PM Out</th>
                <th>Hours</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalHours = 0;
            $daysWorked = 0;
            $lateCount = 0;
            
            for ($d = 1; $d <= $daysInMonth; $d++):
                $fullDate = date('Y-m-d', strtotime("{$year}-{$month}-" . sprintf('%02d', $d)));
                $dayName = date('D', strtotime($fullDate));
                $r = $map[$d] ?? null;
                
                $amHours = $r ? getTotalHours($r['am_in'] ?? null, $r['am_out'] ?? null) : 0;
                $pmHours = $r ? getTotalHours($r['pm_in'] ?? null, $r['pm_out'] ?? null) : 0;
                $dayTotal = $amHours + $pmHours;
                
                if ($r) $daysWorked++;
                if ($dayTotal > 0) $totalHours += $dayTotal;
                
                $metrics = $r ? getRecordMetrics($conn, $r) : null;
                $remarks = '';
                if ($metrics) {
                    $statuses = [];
                    if ($metrics['am_in']['status'] != 'ON TIME') $statuses[] = 'AM In: ' . $metrics['am_in']['status'];
                    if ($metrics['pm_in']['status'] != 'ON TIME') $statuses[] = 'PM In: ' . $metrics['pm_in']['status'];
                    $remarks = implode('; ', $statuses);
                    
                    if ($metrics['am_in']['status'] == 'LATE' || $metrics['pm_in']['status'] == 'LATE') {
                        $lateCount++;
                    }
                }
            ?>
                <tr>
                    <td><?php echo date('M d', strtotime($fullDate)); ?></td>
                    <td><?php echo $dayName; ?></td>
                    <td><?php echo $r && $r['am_in'] ? date('h:i A', strtotime($r['am_in'])) : '--'; ?></td>
                    <td><?php echo $r && $r['am_out'] ? date('h:i A', strtotime($r['am_out'])) : '--'; ?></td>
                    <td><?php echo $r && $r['pm_in'] ? date('h:i A', strtotime($r['pm_in'])) : '--'; ?></td>
                    <td><?php echo $r && $r['pm_out'] ? date('h:i A', strtotime($r['pm_out'])) : '--'; ?></td>
                    <td><?php echo $dayTotal > 0 ? number_format($dayTotal, 2) : '--'; ?></td>
                    <td style="font-size: 11px;"><?php echo htmlspecialchars($remarks); ?></td>
                </tr>
            <?php endfor; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="6">Monthly Total</th>
                <th><?php echo number_format($totalHours, 2); ?></th>
                <th></th>
            </tr>
            <tr>
                <th colspan="6">Days Worked</th>
                <th colspan="2"><?php echo $daysWorked; ?> days</th>
            </tr>
            <tr>
                <th colspan="6">Late Incidents</th>
                <th colspan="2"><?php echo $lateCount; ?> times</th>
            </tr>
        </tfoot>
    </table>

    <div class="signature" style="margin-top: 40px;">
        <div class="box">
            <div style="margin-top: 50px; border-top: 1px solid #000; display: inline-block; padding-top: 5px;">
                Employee Signature / Date
            </div>
        </div>
        <div class="box">
            <div style="margin-top: 50px; border-top: 1px solid #000; display: inline-block; padding-top: 5px;">
                Supervisor Signature / Date
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; font-size: 11px; text-align: center; color: #666;">
        <p>This is an auto-generated document. Printed on <?php echo date('F d, Y h:i A'); ?></p>
    </div>
</div>

<script>
    // Auto-open print dialog
    window.onload = function() {
        // Uncomment to auto-print on load
        // window.print();
    };
</script>
</body>
</html>
