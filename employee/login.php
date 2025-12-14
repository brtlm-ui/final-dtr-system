<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'employee') {
        header('Location: dashboard.php');
        exit();
    }
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: ../admin/dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold">DTR System</h2>
                            <p class="text-muted">Daily Time Record</p>
                        </div>

                        <!-- Tab Navigation -->
                        <ul class="nav nav-pills nav-justified mb-4" id="loginTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="employee-tab" data-bs-toggle="pill" 
                                        data-bs-target="#employee-login" type="button" role="tab">
                                    Employee
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="admin-tab" data-bs-toggle="pill" 
                                        data-bs-target="#admin-login" type="button" role="tab">
                                    Admin
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="loginTabContent">
                            <!-- Employee Login -->
                            <div class="tab-pane fade show active" id="employee-login" role="tabpanel">
                                <form id="employeeLoginForm">
                                    <div class="mb-3">
                                        <label for="employee_id" class="form-label">Employee ID</label>
                                        <input type="text" class="form-control" id="employee_id" 
                                               name="employee_id" required autofocus>
                                    </div>
                                    <div class="mb-3">
                                        <label for="pin" class="form-label">PIN</label>
                                        <input type="password" class="form-control" id="pin" 
                                               name="pin" maxlength="6" required>
                                    </div>
                                    <div id="employee-error" class="alert alert-danger d-none"></div>
                                    <button type="submit" class="btn btn-primary w-100">Login</button>
                                </form>
                            </div>

                            <!-- Admin Login -->
                            <div class="tab-pane fade" id="admin-login" role="tabpanel">
                                <form id="adminLoginForm" method="POST" action="../api/admin_login.php" autocomplete="off">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" 
                                               name="username" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" 
                                                   name="password" required>
                                            <button type="button" class="btn btn-outline-secondary" id="toggleAdminPassword">
                                                <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8z"/>
                                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z" fill="#fff"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <?php if (isset($_GET['error']) && $_GET['error'] === 'admin'): ?>
                                        <div id="admin-error" class="alert alert-danger">Invalid username or password.</div>
                                    <?php else: ?>
                                        <div id="admin-error" class="alert alert-danger d-none"></div>
                                    <?php endif; ?>

                                    <button type="submit" class="btn btn-success w-100">Login</button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/login.js"></script>

    <script>
    // If admin login error, activate admin tab and content
    if (window.location.search.includes('error=admin')) {
        document.addEventListener('DOMContentLoaded', function() {
            // Activate Admin tab
            var adminTab = document.getElementById('admin-tab');
            var adminTabContent = document.getElementById('admin-login');
            var employeeTab = document.getElementById('employee-tab');
            var employeeTabContent = document.getElementById('employee-login');
            if (adminTab && adminTabContent && employeeTab && employeeTabContent) {
                adminTab.classList.add('active');
                adminTab.setAttribute('aria-selected', 'true');
                adminTabContent.classList.add('show', 'active');
                employeeTab.classList.remove('active');
                employeeTab.setAttribute('aria-selected', 'false');
                employeeTabContent.classList.remove('show', 'active');
            }
        });
    }
    </script>
</body>
</html>
