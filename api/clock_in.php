<?php
session_start();
require_once '../config/timezone.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if employee is in confirmation session
if (!isset($_SESSION['confirm_employee_id'])) {
    // Debug: log what's in the session
    error_log('Session expired. Session contents: ' . json_encode($_SESSION));
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.', 'debug' => $_SESSION]);
    exit();
}

$employeeId = $_SESSION['confirm_employee_id'];

try {
    // Check if already clocked in today
    if (hasClockInToday($conn, $employeeId)) {
        echo json_encode(['success' => false, 'message' => 'You have already clocked in today']);
        exit();
    }

    // Get current day and official time
    $currentDateTime = date('Y-m-d H:i:s');
        $recordDate = date('Y-m-d');
    $dayOfWeek = getDayOfWeek($currentDateTime);
    $officialTime = getOfficialTime($conn, $employeeId, $dayOfWeek);

    if (!$officialTime) {
        echo json_encode(['success' => false, 'message' => 'No schedule found for today']);
        exit();
    }


    // Calculate AM IN status (for response only)
    $officialAMIn = date('Y-m-d') . ' ' . $officialTime['am_time_in'];
    $amInStatus = calculateStatus($currentDateTime, $officialAMIn, $officialTime['grace_period_minutes']);

    // Create time record with AM clock in (no status/diff columns)
    $stmt = $conn->prepare("
        INSERT INTO time_record (employee_id, record_date, am_in, created_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$employeeId, $recordDate, $currentDateTime, $currentDateTime]);

    // TODO: Write to record_audit table for audit trail

    // Only set full employee session if ON TIME (no reason required)
    // For LATE/OVERTIME, session will be set after reason submission
    if ($amInStatus === 'ON TIME') {
        $_SESSION['user_type'] = 'employee';
        $_SESSION['employee_id'] = $employeeId;
        // Keep confirm_employee_id for idempotency; second submit will be blocked by hasClockInToday()
        $_SESSION['success_message'] = "Successfully clocked in at " . formatTime($currentDateTime) . " - Status: " . $amInStatus;
    }

    $recordId = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Clock in successful',
        'status' => $amInStatus,
        'time' => formatTime($currentDateTime),
        'redirect' => 'dashboard.php',
        'record_id' => $recordId,
        'reason_type' => 'am_in',
        'clock_datetime' => $currentDateTime
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>