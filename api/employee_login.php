<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$employeeId = sanitizeInput($input['employee_id'] ?? '');
$pin = sanitizeInput($input['pin'] ?? '');

if (empty($employeeId) || empty($pin)) {
    echo json_encode(['success' => false, 'message' => 'Please enter both Employee ID and PIN']);
    exit();
}

try {
    // Verify employee credentials
    $stmt = $conn->prepare("
        SELECT * FROM employee 
        WHERE employee_id = ? AND pin = ? AND is_active = 1
    ");
    $stmt->execute([$employeeId, $pin]);
    $employee = $stmt->fetch();

    if ($employee) {
        // Set session for confirmation page
        $_SESSION['confirm_employee_id'] = $employee['employee_id'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'redirect' => 'confirm.php'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Employee ID or PIN'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>