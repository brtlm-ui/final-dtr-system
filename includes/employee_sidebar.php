<div class="sidebar-wrapper">
    <div class="card sidebar">
        <div class="card-body p-0">
            <nav class="nav flex-column py-3">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="view_records.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'view_records.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check"></i> View My Records
                </a>
                <a href="edit_record.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'edit_record.php' ? 'active' : ''; ?>">
                    <i class="bi bi-pencil-square"></i> Edit Records
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
