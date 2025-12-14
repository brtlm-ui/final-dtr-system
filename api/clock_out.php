<?php
session_start();
require_once '../config/timezone.php';
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isEmployeeLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$employeeId = $_SESSION['employee_id'];
$period = isset($input['period']) ? $input['period'] : null;
if (empty($period)) {
    echo json_encode(['success' => false, 'message' => 'Period not specified']);
    exit();
}

try {
    // Get today's record
    $todayRecord = getTodayRecord($conn, $employeeId);

    if (!$todayRecord) {
        echo json_encode(['success' => false, 'message' => 'No clock-in record found for today']);
        exit();
    }

    $currentDateTime = date('Y-m-d H:i:s');
    $dayOfWeek = getDayOfWeek($currentDateTime);
    $officialTime = getOfficialTime($conn, $employeeId, $dayOfWeek);

    if ($period === 'am') {
        // Clock out AM
        if (!empty($todayRecord['am_out'])) {
            echo json_encode(['success' => false, 'message' => 'Already clocked out for AM']);
            exit();
        }

        // Calculate AM OUT status (for response only)
        $officialAMOut = date('Y-m-d') . ' ' . $officialTime['am_time_out'];
        $amOutStatus = calculateClockOutStatus($currentDateTime, $officialAMOut, $officialTime['grace_period_minutes']);
        $amDiffText = differenceText($currentDateTime, $officialAMOut, $amOutStatus);

        // Save AM clock out (no diff/status columns)
        $stmt = $conn->prepare("UPDATE time_record SET am_out = ? WHERE record_id = ?");
        $stmt->execute([$currentDateTime, $todayRecord['record_id']]);

        // TODO: Write to record_audit table for audit trail

        echo json_encode([
            'success' => true,
            'message' => 'AM clock out successful',
            'time' => formatTime($currentDateTime),
            'status' => $amOutStatus,
            'difference_text' => $amDiffText,
            'record_id' => $todayRecord['record_id'],
            'reason_type' => 'am_out',
            'clock_datetime' => $currentDateTime
        ]);

    } elseif ($period === 'pm_in') {
        // Clock in PM
        if (empty($todayRecord['am_out'])) {
            echo json_encode(['success' => false, 'message' => 'Please clock out AM first']);
            exit();
        }

        if (!empty($todayRecord['pm_in'])) {
            echo json_encode(['success' => false, 'message' => 'Already clocked in for PM']);
            exit();
        }

        // Calculate PM IN status
        $officialPMIn = date('Y-m-d') . ' ' . $officialTime['pm_time_in'];
        $pmInStatus = calculateStatus($currentDateTime, $officialPMIn, $officialTime['grace_period_minutes']);

        $pmInDiffMins = getMinutesDifference($currentDateTime, $officialPMIn);
        $pmInDiffText = differenceText($currentDateTime, $officialPMIn, $pmInStatus);

        // Save PM in (no diff/status columns)
        $stmt = $conn->prepare("UPDATE time_record SET pm_in = ? WHERE record_id = ?");
        $stmt->execute([$currentDateTime, $todayRecord['record_id']]);

        // TODO: Write to record_audit table for audit trail

        echo json_encode([
            'success' => true,
            'message' => 'PM clock in successful',
            'time' => formatTime($currentDateTime),
            'status' => $pmInStatus,
            'difference_text' => $pmInDiffText,
            'record_id' => $todayRecord['record_id'],
            'reason_type' => 'pm_in',
            'clock_datetime' => $currentDateTime
        ]);

    } elseif ($period === 'pm') {
        // Clock out PM
        if (empty($todayRecord['pm_in'])) {
            echo json_encode(['success' => false, 'message' => 'Please clock in PM first']);
            exit();
        }
        if (!empty($todayRecord['pm_out'])) {
            echo json_encode(['success' => false, 'message' => 'Already clocked out for PM']);
            exit();
        }

        // Calculate PM OUT status (check for overtime or early leave)
        $officialPMOut = date('Y-m-d') . ' ' . $officialTime['pm_time_out'];
        $pmOutStatus = calculateClockOutStatus($currentDateTime, $officialPMOut, $officialTime['grace_period_minutes']);

        $pmOutDiffMins = getMinutesDifference($currentDateTime, $officialPMOut);
        $pmOutDiffText = differenceText($currentDateTime, $officialPMOut, $pmOutStatus);

        // Save PM out (no diff/status columns)
        $stmt = $conn->prepare("UPDATE time_record SET pm_out = ? WHERE record_id = ?");
        $stmt->execute([$currentDateTime, $todayRecord['record_id']]);

        // TODO: Write to record_audit table for audit trail

        echo json_encode([
            'success' => true,
            'message' => 'PM clock out successful',
            'time' => formatTime($currentDateTime),
            'status' => $pmOutStatus,
            'difference_text' => $pmOutDiffText,
            'record_id' => $todayRecord['record_id'],
            'reason_type' => 'pm_out',
            'clock_datetime' => $currentDateTime
        ]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid period']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>