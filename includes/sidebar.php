<div class="sidebar-wrapper">
    <div class="card sidebar">
        <div class="card-body p-0">
            <nav class="nav flex-column py-3">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Overview
                </a>
                <a href="manage_employees.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_employees.php' ? 'active' : ''; ?>">
                    <i class="bi bi-people"></i> Manage Employees
                </a>
                <a href="manage_schedules.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_schedules.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-event"></i> Manage Schedules
                </a>
                <a href="view_all_records.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'view_all_records.php' ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i> View All Records
                </a>
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                    <i class="bi bi-bar-chart"></i> Reports
                </a>
                <a href="manage_reasons.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_reasons.php' ? 'active' : ''; ?>">
                    <i class="bi bi-card-list"></i> Manage Reasons
                </a>
                <a href="edit_record.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'edit_record.php' ? 'active' : ''; ?>">
                    <i class="bi bi-pencil-square"></i> Edit Records
                </a>
                <a href="delete_record.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'delete_record.php' ? 'active' : ''; ?>">
                    <i class="bi bi-trash"></i> Delete Records
                </a>
            </nav>
            <div class="mt-auto p-3 border-top">
                <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </div>
        </div>
    </div>
</div>
