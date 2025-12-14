<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Admin only
requireAdminLogin();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$employeeId = $_GET['employee_id'] ?? null;

$params = [$startDate, $endDate];
$employeeFilter = '';
if ($employeeId) {
    $employeeFilter = ' AND tr.employee_id = ?';
    $params[] = $employeeId;
}

// Get all records in date range with employee info
$sql = "SELECT tr.*, e.employee_id as emp_id, e.first_name, e.last_name, 
        d.department_name, p.position_name
        FROM time_record tr
        JOIN employee e ON tr.employee_id = e.employee_id
        LEFT JOIN department d ON e.department_id = d.department_id
        LEFT JOIN position p ON e.position_id = p.position_id
        WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter . "
        ORDER BY tr.record_date DESC, e.last_name, e.first_name";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter for late incidents only
$lateIncidents = [];
foreach ($records as $record) {
    $metrics = getRecordMetrics($conn, $record);
    $incidents = [];
    
    if ($metrics['am_in']['status'] == 'LATE') {
        $incidents[] = [
            'entry' => 'AM In',
            'time' => $record['am_in'],
            'official' => $metrics['am_in']['official'] ?? '',
            'diff' => $metrics['am_in']['diff_text'] ?? '',
            'reason' => $metrics['am_in']['reason'] ?? 'No reason provided'
        ];
    }
    
    if ($metrics['pm_in']['status'] == 'LATE') {
        $incidents[] = [
            'entry' => 'PM In',
            'time' => $record['pm_in'],
            'official' => $metrics['pm_in']['official'] ?? '',
            'diff' => $metrics['pm_in']['diff_text'] ?? '',
            'reason' => $metrics['pm_in']['reason'] ?? 'No reason provided'
        ];
    }
    
    if (!empty($incidents)) {
        $lateIncidents[] = [
            'record' => $record,
            'incidents' => $incidents
        ];
    }
}

$totalIncidents = 0;
foreach ($lateIncidents as $li) {
    $totalIncidents += count($li['incidents']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Late Incidents Report</title>
    <link rel="stylesheet" href="../assets/css/print.css">
    <style>
        @media print {
            .no-print { display: none; }
        }
        .report-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .report-info {
            margin: 15px 0;
            font-size: 14px;
        }
        .incident-row {
            background: #f8f9fa;
        }
        .sub-row {
            font-size: 13px;
            border-left: 3px solid #dc3545;
        }
    </style>
</head>
<body>
<div class="no-print" style="margin: 20px; text-align: right;">
    <button onclick="window.print()" style="padding: 8px 16px; background: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer;">Print</button>
    <button onclick="window.close()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 8px;">Close</button>
</div>

<div class="container-print">
    <div class="report-header">
        <h2>LATE INCIDENTS REPORT</h2>
        <h4>Disciplinary Documentation</h4>
    </div>

    <div class="report-info">
        <div><strong>Report Period:</strong> <?php echo date('F d, Y', strtotime($startDate)) . ' to ' . date('F d, Y', strtotime($endDate)); ?></div>
        <div><strong>Total Late Incidents:</strong> <?php echo $totalIncidents; ?></div>
        <div><strong>Employees Affected:</strong> <?php echo count($lateIncidents); ?></div>
        <div><strong>Generated:</strong> <?php echo date('F d, Y h:i A'); ?></div>
    </div>

    <?php if (empty($lateIncidents)): ?>
        <div class="alert alert-success" style="padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; margin: 20px 0;">
            <strong>No late incidents found for this period.</strong>
        </div>
    <?php else: ?>
        <table class="table-print">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Entry</th>
                    <th>Clock Time</th>
                    <th>Official Time</th>
                    <th>Lateness</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lateIncidents as $li): 
                    $record = $li['record'];
                    $incidents = $li['incidents'];
                    $rowspan = count($incidents);
                    $firstRow = true;
                ?>
                    <?php foreach ($incidents as $incident): ?>
                        <tr class="<?php echo $firstRow ? 'incident-row' : 'sub-row'; ?>">
                            <?php if ($firstRow): ?>
                                <td rowspan="<?php echo $rowspan; ?>"><?php echo date('M d, Y', strtotime($record['record_date'])); ?></td>
                                <td rowspan="<?php echo $rowspan; ?>">
                                    <strong><?php echo htmlspecialchars($record['last_name'] . ', ' . $record['first_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($record['emp_id']); ?></small>
                                </td>
                                <td rowspan="<?php echo $rowspan; ?>"><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                            <td><?php echo $incident['entry']; ?></td>
                            <td><?php echo date('h:i A', strtotime($incident['time'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($incident['official'])); ?></td>
                            <td style="color: #dc3545; font-weight: bold;"><?php echo $incident['diff']; ?></td>
                            <td style="font-size: 12px;"><?php echo htmlspecialchars($incident['reason']); ?></td>
                        </tr>
                        <?php $firstRow = false; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107;">
            <strong>Summary by Employee:</strong>
            <ul style="margin-top: 10px;">
                <?php 
                $empSummary = [];
                foreach ($lateIncidents as $li) {
                    $emp = $li['record']['last_name'] . ', ' . $li['record']['first_name'];
                    if (!isset($empSummary[$emp])) {
                        $empSummary[$emp] = 0;
                    }
                    $empSummary[$emp] += count($li['incidents']);
                }
                arsort($empSummary);
                foreach ($empSummary as $emp => $count):
                ?>
                    <li><strong><?php echo htmlspecialchars($emp); ?>:</strong> <?php echo $count; ?> late incident(s)</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="signature" style="margin-top: 50px;">
        <div class="box">
            <div style="margin-top: 50px; border-top: 1px solid #000; display: inline-block; padding-top: 5px;">
                Prepared by / Date
            </div>
        </div>
        <div class="box">
            <div style="margin-top: 50px; border-top: 1px solid #000; display: inline-block; padding-top: 5px;">
                Reviewed by / Date
            </div>
        </div>
    </div>

    <div style="margin-top: 30px; font-size: 10px; text-align: center; color: #666;">
        <p><em>This report is intended for internal use only. Contains confidential employee information.</em></p>
        <p>Generated by DTR System on <?php echo date('F d, Y h:i A'); ?></p>
    </div>
</div>
</body>
</html>
