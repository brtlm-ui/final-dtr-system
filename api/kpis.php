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

$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$employeeId = $_GET['employee_id'] ?? null;

// Default to last 30 days
if (!$endDate) $endDate = date('Y-m-d');
if (!$startDate) $startDate = date('Y-m-d', strtotime('-29 days', strtotime($endDate)));

try {
    $onTime = kpi_on_time_rate($conn, $startDate, $endDate, $employeeId);
    $avgHours = kpi_average_daily_hours($conn, $startDate, $endDate, $employeeId);
    $overtime = kpi_total_overtime_hours($conn, $startDate, $endDate, $employeeId);
    $avgLateness = kpi_average_lateness($conn, $startDate, $endDate, $employeeId);
    $lateCount = kpi_late_incidents_count($conn, $startDate, $endDate, $employeeId);
    $pendingReasons = kpi_pending_reasons_count($conn, $startDate, $endDate, $employeeId);

    echo json_encode([
        'success' => true,
        'range' => ['start' => $startDate, 'end' => $endDate],
        'kpis' => [
            'on_time_rate' => $onTime,
            'average_daily_hours' => $avgHours,
            'total_overtime' => $overtime,
            'average_lateness' => $avgLateness,
            'late_incidents' => $lateCount,
            'pending_reasons' => $pendingReasons
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
