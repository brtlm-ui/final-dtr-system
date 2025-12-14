<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if employee is logged in
function isEmployeeLoggedIn() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'employee' && isset($_SESSION['employee_id']);
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin' && isset($_SESSION['admin_id']);
}

// Require employee login
function requireEmployeeLogin() {
    if (!isEmployeeLoggedIn()) {
        header('Location: ../employee/login.php');
        exit();
    }
}

// Require admin login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        header('Location: ../employee/login.php');
        exit();
    }
}

// Get current user ID
function getCurrentUserId() {
    if (isEmployeeLoggedIn()) {
        return $_SESSION['employee_id'];
    } elseif (isAdminLoggedIn()) {
        return $_SESSION['admin_id'];
    }
    return null;
}

// Get current user type
function getCurrentUserType() {
    return $_SESSION['user_type'] ?? null;
}

// Destroy session
function destroySession() {
    session_unset();
    session_destroy();
}
?>