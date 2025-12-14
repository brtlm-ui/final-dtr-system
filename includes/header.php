<?php
// Get the current user type and name from session
$currentUserType = $_SESSION['user_type'] ?? '';
$currentUserName = '';
$dashboardLink = '';
$navbarColor = '';

if ($currentUserType === 'admin') {
    $currentUserName = $_SESSION['admin_name'] ?? '';
    $dashboardLink = '../admin/dashboard.php';
    $navbarColor = 'bg-success'; // Green for admin
} elseif ($currentUserType === 'employee') {
    $currentUserName = $_SESSION['employee_name'] ?? '';
    $dashboardLink = '../employee/dashboard.php';
    $navbarColor = 'bg-primary'; // Blue for employee
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'DTR System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php // cache-busting version param to ensure latest notifications formatter loads ?>
    <script src="../assets/js/notifications.js?v=20251123a" defer></script>
    <script src="../assets/js/toast.js" defer></script>
</head>
<body class="bg-light" style="padding-top: 60px;">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark <?php echo $navbarColor; ?>" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1030; overflow: visible;">
        <div class="container-fluid" style="margin-left: 0; padding-left: 24px; padding-right: 24px; overflow: visible;">
            <a class="navbar-brand fw-bold" href="<?php echo $dashboardLink; ?>" style="font-size: 1.25rem;">
                DTR System
            </a>
            <div class="d-flex align-items-center ms-auto">
                <div class="dropdown me-3">
                    <button class="btn btn-link text-white position-relative p-2" type="button" id="notifMenuButton" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none;">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <span id="notifBadge" class="badge bg-danger position-absolute top-0 start-100 translate-middle" style="display:none;">0</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifMenuButton">
                        <div id="notifDropdown" style="min-width: 300px;">
                            <li><span class="dropdown-item">Loading...</span></li>
                        </div>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($currentUserType === 'admin'): ?>
                            <li><a class="dropdown-item text-center" href="../admin/notifications.php">View all notifications</a></li>
                        <?php elseif ($currentUserType === 'employee'): ?>
                            <li><a class="dropdown-item text-center" href="../employee/notifications.php">View all notifications</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <a href="<?php echo ($currentUserType === 'admin') ? '../admin/profile.php' : '../employee/profile.php'; ?>" class="d-flex align-items-center text-decoration-none">
                    <img src="<?php echo '../assets/images/default-avatar.png'; ?>" alt="Profile" class="rounded-circle" width="40" height="40" style="object-fit: cover; border: 2px solid white;">
                </a>
            </div>
        </div>
    </nav>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../<?php echo $currentUserType; ?>/logout.php" class="btn btn-primary">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="container-fluid mt-4">
