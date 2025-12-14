<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$adminId = $_SESSION['admin_id'] ?? null;
if (!$adminId) {
    echo json_encode(['success' => false, 'message' => 'Admin session missing']);
    exit();
}

// Support form-encoded POST and JSON body (used by fetch)
$rawUsername = $_POST['username'] ?? null;
$rawPassword = $_POST['password'] ?? null;
$rawEmail = $_POST['email'] ?? null;

if ($rawUsername === null && $rawPassword === null) {
    // try JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input)) {
        $rawUsername = $input['username'] ?? null;
        $rawPassword = $input['password'] ?? null;
        $rawEmail = $input['email'] ?? null;
    }
}

$username = $rawUsername !== null ? sanitizeInput($rawUsername) : null;
$password = $rawPassword !== null ? trim($rawPassword) : null;
$email = $rawEmail !== null ? trim($rawEmail) : null;

// Validate email if provided
if ($email !== null && $email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
    // Ensure uniqueness
    $ustmt = $conn->prepare('SELECT admin_id FROM admin WHERE email = ? AND admin_id <> ? LIMIT 1');
    $ustmt->execute([$email, $adminId]);
    if ($ustmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        exit();
    }
}

if (!$username && !$password && ($email === null || $email === '')) {
    echo json_encode(['success' => false, 'message' => 'No changes provided']);
    exit();
}

$fields = [];
$params = [];
if ($username) { $fields[] = 'username = ?'; $params[] = $username; }
if ($password) { $fields[] = 'password = ?'; $params[] = $password; }
if ($email !== null) { $fields[] = 'email = ?'; $params[] = $email === '' ? null : $email; }
$params[] = $adminId;

try {
    $sql = 'UPDATE admin SET ' . implode(', ', $fields) . ' WHERE admin_id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'message' => 'Admin updated']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}

?>