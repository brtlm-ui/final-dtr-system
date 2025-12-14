<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get form data
$employeeId = sanitizeInput($_POST['employee_id']);
$firstName = sanitizeInput($_POST['first_name']);
$lastName = sanitizeInput($_POST['last_name']);
$email = sanitizeInput($_POST['email']);
$positionId = sanitizeInput($_POST['position_id']);
$departmentId = sanitizeInput($_POST['department_id']);
$pin = sanitizeInput($_POST['pin']);

try {
    // Start building the update query
    $updateFields = [];
    $params = [];

    // Add fields that are always updated
    $updateFields[] = "first_name = ?";
    $params[] = $firstName;
    
    $updateFields[] = "last_name = ?";
    $params[] = $lastName;
    
    $updateFields[] = "email = ?";
    $params[] = $email;
    
    $updateFields[] = "position_id = ?";
    $params[] = $positionId;
    
    $updateFields[] = "department_id = ?";
    $params[] = $departmentId;

    // Add PIN only if provided
    if (!empty($pin)) {
        $updateFields[] = "pin = ?";
        $params[] = $pin;
    }

    // Add employee_id at the end of params array for WHERE clause
    $params[] = $employeeId;

    // Construct and execute the query
    $query = "UPDATE employee SET " . implode(", ", $updateFields) . " WHERE employee_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Employee updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No changes made or employee not found'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
?>