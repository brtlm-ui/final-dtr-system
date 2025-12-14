<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/timezone.php';

requireAdminLogin();

// Show admin profile (info of the logged-in admin)
$adminId = $_SESSION['admin_id'] ?? null;
$admin = null;
if ($adminId) {
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ? LIMIT 1");
    $stmt->execute([$adminId]);
    $admin = $stmt->fetch();
    if (!$admin) $error = 'Admin account not found';
} else {
    $error = 'Admin not logged in';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Employee Profile - Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<?php require_once '../includes/header.php'; ?>

<?php require_once '../includes/sidebar.php'; ?>
<div class="container-fluid mt-4">
    <div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Admin Profile</h3>
    </div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Admin Profile</h5>
                    <?php if ($admin): ?>
                        <p><strong>ID:</strong> <?php echo htmlspecialchars($admin['admin_id']); ?></p>
                        <p><strong>Username:</strong> <?php echo htmlspecialchars($admin['username']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($admin['email'] ?? ''); ?></p>
                        <p><strong>Active:</strong> <?php echo $admin['is_active'] ? 'Yes' : 'No'; ?></p>
                        <p><strong>Created:</strong> <?php echo htmlspecialchars($admin['created_at']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Edit Admin</h5>
                    <form id="editForm">
                        <input type="hidden" name="admin_id" value="<?php echo htmlspecialchars($admin['admin_id'] ?? ''); ?>">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input name="username" class="form-control" value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" placeholder="admin@example.com">
                            <div class="form-text">Used for notification emails.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password (leave blank to keep)</label>
                            <input name="password" type="password" class="form-control">
                        </div>
                        <button type="button" id="saveBtn" class="btn btn-primary">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('saveBtn').addEventListener('click', function(){
            var form = document.getElementById('editForm');
            var fd = new FormData(form);
            fetch('../api/update_admin.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(json => {
                    if (json.success) {
                        alert(json.message);
                        location.reload();
                    } else alert(json.message || 'Error');
                }).catch(e => { alert('Error'); console.error(e); });
        });
    </script>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>