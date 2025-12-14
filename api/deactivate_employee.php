<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Only admin can deactivate employees
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$employeeId = $input['employee_id'] ?? '';

if (empty($employeeId)) {
    echo json_encode(['success' => false, 'message' => 'Employee ID is required']);
    exit();
}

try {
    $stmt = $conn->prepare("UPDATE employee SET is_active = 0 WHERE employee_id = ?");
    $stmt->execute([$employeeId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Employee deactivated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Employee not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>