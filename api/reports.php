<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

// Ensure timezone is set for date handling
require_once '../config/timezone.php';

// Only admin can access reports (use session helpers)
if (!isAdminLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? 'preview_daily';
$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$employeeId = $_GET['employee_id'] ?? null;
$format = $_GET['format'] ?? 'json';

try {
    // Base filters
    $params = [$startDate, $endDate];
    $employeeFilter = '';
    if ($employeeId) { $employeeFilter = ' AND tr.employee_id = ?'; $params[] = $employeeId; }


    $sql = "SELECT tr.*, e.first_name, e.last_name FROM time_record tr JOIN employee e ON tr.employee_id = e.employee_id
            WHERE DATE(tr.record_date) BETWEEN ? AND ?" . $employeeFilter . " ORDER BY tr.record_date DESC";

    // Preview: return first 200 rows JSON
    if ($action === 'preview_daily') {
        $stmt = $conn->prepare($sql . ' LIMIT 200');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Compute metrics for each row
        $result = [];
        foreach ($rows as $row) {
            $metrics = getRecordMetrics($conn, $row);
            $result[] = [
                'record_id' => $row['record_id'],
                'employee_id' => $row['employee_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'record_date' => $row['record_date'],
                'am_in' => $row['am_in'] ? date('h:i A', strtotime($row['am_in'])) : null,
                'am_out' => $row['am_out'] ? date('h:i A', strtotime($row['am_out'])) : null,
                'pm_in' => $row['pm_in'] ? date('h:i A', strtotime($row['pm_in'])) : null,
                'pm_out' => $row['pm_out'] ? date('h:i A', strtotime($row['pm_out'])) : null,
                // status columns removed; use only computed metrics if needed
            ];
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'count' => count($result), 'rows' => $result]);
        exit();
    }

    // Export CSV
    if ($action === 'export_daily') {
        // Stream CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="daily_attendance_' . $startDate . '_to_' . $endDate . '.csv"');
        $output = fopen('php://output', 'w');
        // header row
        $headers = ['record_id','employee_id','name','record_date','am_in','am_out','pm_in','pm_out'];
        fputcsv($output, $headers);

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = $row['first_name'] . ' ' . $row['last_name'];
            $line = [
                $row['record_id'], $row['employee_id'], $name, $row['record_date'],
                $row['am_in'], $row['am_out'], $row['pm_in'], $row['pm_out']
            ];
            fputcsv($output, $line);
        }
        fclose($output);
        exit();
    }

    // Other report actions can be added (monthly_timesheet, exceptions, overtime, reason_audit)
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unknown action']);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
