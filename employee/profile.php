<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/timezone.php';

requireEmployeeLogin();
$employeeId = $_SESSION['employee_id'];
$stmt = $conn->prepare("SELECT e.*, COALESCE(p.position_name,'') AS position, COALESCE(d.department_name,'') AS department FROM employee e LEFT JOIN position p ON e.position_id = p.position_id LEFT JOIN department d ON e.department_id = d.department_id WHERE e.employee_id = ? LIMIT 1");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch();
if (!$employee) { echo 'Employee not found'; exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Profile</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<?php require_once '../includes/header.php'; ?>
<?php require_once '../includes/employee_sidebar.php'; ?>
<div class="container-fluid mt-4">
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>My Profile</h3>
        </div>

        <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Profile</h5>
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($employee['first_name'].' '.$employee['last_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($employee['email']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($employee['position']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($employee['department']); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Account Details</h5>
                    <p><strong>PIN:</strong> ******</p>
                    <p class="text-muted">Contact your administrator to update personal details.</p>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>