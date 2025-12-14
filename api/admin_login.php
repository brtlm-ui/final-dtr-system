<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Process only POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['username'], $_POST['password'])) {
        header("Location: ../employee/login.php?error=admin");
        exit();
    }

    $username = trim(sanitizeInput($_POST['username']));
    $password = trim($_POST['password']);

    // Query admin (require is_active=1)
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && $password === trim($admin['password'])) {
        $_SESSION['user_type'] = 'admin';
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['username'];
        header("Location: ../admin/dashboard.php");
        exit();
    }

    // Invalid credentials → return to login with error flag
    header("Location: ../employee/login.php?error=admin");
    exit();
}
