<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

if (!isAdminLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$employeeId = $_GET['employee_id'] ?? null;

// Build query (reuse logic from api/reports.php)
$params = [$startDate, $endDate];
$employeeFilter = '';
if ($employeeId) { $employeeFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }


$sql = "SELECT tr.*, e.first_name, e.last_name FROM time_record tr JOIN employee e ON tr.employee_id = e.employee_id
  WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter . " ORDER BY tr.record_date DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Render print-friendly HTML
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Printable Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/print.css">
  <style>
    body { padding: 20px; }
    table { font-size: 12px; }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>
<div class="no-print" style="margin: 20px; text-align: right;">
    <button onclick="window.print()" class="btn btn-primary">Print</button>
    <button onclick="window.close()" class="btn btn-secondary">Close</button>
</div>

  <div class="container">
    <div class="text-center mb-4">
      <h3>Attendance Report</h3>
      <p class="text-muted"><?php echo htmlspecialchars(date('F d, Y', strtotime($startDate))) . ' - ' . htmlspecialchars(date('F d, Y', strtotime($endDate))); ?></p>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-sm">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Employee</th>
            <th>AM In</th>
            <th>AM In Status</th>
            <th>AM Out</th>
            <th>AM Out Status</th>
            <th>PM In</th>
            <th>PM In Status</th>
            <th>PM Out</th>
            <th>PM Out Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php $metrics = getRecordMetrics($conn, $r); ?>
            <tr>
              <td><?php echo htmlspecialchars(formatDate($r['record_date'])); ?></td>
              <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
              <td><?php echo htmlspecialchars(formatTime($r['am_in'])); ?></td>
              <td><?php echo htmlspecialchars($metrics['am_in']['status'] ?? '--'); ?></td>
              <td><?php echo htmlspecialchars(formatTime($r['am_out'])); ?></td>
              <td><?php echo htmlspecialchars($metrics['am_out']['status'] ?? '--'); ?></td>
              <td><?php echo htmlspecialchars(formatTime($r['pm_in'])); ?></td>
              <td><?php echo htmlspecialchars($metrics['pm_in']['status'] ?? '--'); ?></td>
              <td><?php echo htmlspecialchars(formatTime($r['pm_out'])); ?></td>
              <td><?php echo htmlspecialchars($metrics['pm_out']['status'] ?? '--'); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="text-center mt-4 no-print">
      <button class="btn btn-primary" onclick="window.print();">Print / Save as PDF</button>
      <a href="javascript:window.close();" class="btn btn-secondary">Close</a>
    </div>
  </div>

  <script>
    // Auto open print dialog after a short delay so page fully renders
    window.addEventListener('load', function(){
      setTimeout(function(){ window.print(); }, 500);
    });
  </script>
</body>
</html>
<?php
