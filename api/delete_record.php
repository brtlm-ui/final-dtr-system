<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Only admin can delete records
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$recordId = $input['record_id'] ?? '';

if (empty($recordId)) {
    echo json_encode(['success' => false, 'message' => 'Record ID is required']);
    exit();
}

try {
    $stmt = $conn->prepare("DELETE FROM time_record WHERE record_id = ?");
    $stmt->execute([$recordId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>