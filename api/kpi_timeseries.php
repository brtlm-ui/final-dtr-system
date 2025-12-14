<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Admin only
if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$metric = $_GET['metric'] ?? 'on_time_rate';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$employeeId = $_GET['employee_id'] ?? null;

if (!$endDate) $endDate = date('Y-m-d');
if (!$startDate) $startDate = date('Y-m-d', strtotime('-29 days', strtotime($endDate)));

try {
    if ($metric !== 'on_time_rate') {
        echo json_encode(['success' => false, 'message' => 'Unsupported metric']);
        exit();
    }

    // Fetch aggregated daily counts
    $params = [$startDate, $endDate];
    $empFilter = '';
    if ($employeeId) { $empFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }

    $sql = "SELECT DATE(tr.record_date) AS day,
        SUM(CASE WHEN tr.am_in IS NOT NULL 
            AND ot.am_time_in IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, ot.am_time_in, TIME(tr.am_in)) <= ot.grace_period_minutes
            THEN 1 ELSE 0 END) AS am_on_time,
        SUM(CASE WHEN tr.pm_in IS NOT NULL 
            AND ot.pm_time_in IS NOT NULL
            AND TIMESTAMPDIFF(MINUTE, ot.pm_time_in, TIME(tr.pm_in)) <= ot.grace_period_minutes
            THEN 1 ELSE 0 END) AS pm_on_time,
        SUM(CASE WHEN tr.am_in IS NOT NULL THEN 1 ELSE 0 END) AS am_total,
        SUM(CASE WHEN tr.pm_in IS NOT NULL THEN 1 ELSE 0 END) AS pm_total
        FROM time_record tr
        LEFT JOIN official_time ot ON ot.employee_id = tr.employee_id 
            AND ot.day_of_week = DAYNAME(tr.record_date) 
            AND ot.is_active = 1
        WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $empFilter . "
        GROUP BY DATE(tr.record_date)
        ORDER BY DATE(tr.record_date) ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map results by date for quick lookup
    $map = [];
    foreach ($rows as $r) {
        $day = $r['day'];
        $on = ($r['am_on_time'] ?? 0) + ($r['pm_on_time'] ?? 0);
        $tot = ($r['am_total'] ?? 0) + ($r['pm_total'] ?? 0);
        $pct = $tot > 0 ? round(($on / $tot) * 100, 2) : null;
        $map[$day] = ['pct' => $pct, 'on' => (int)$on, 'total' => (int)$tot];
    }

    // Build full label/value arrays for each date in range
    $labels = [];
    $values = [];
    $period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
    foreach ($period as $dt) {
        $d = $dt->format('Y-m-d');
        $labels[] = $d;
        if (isset($map[$d])) {
            $values[] = $map[$d]['pct'];
        } else {
            $values[] = null; // no data for that day
        }
    }

    echo json_encode(['success' => true, 'metric' => $metric, 'labels' => $labels, 'values' => $values, 'range' => ['start'=>$startDate,'end'=>$endDate]]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
