<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';

requireAdminLogin();

$fieldErrors = [];
$successMessage = '';
$errorMessage = '';

// Handle activate/deactivate actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['deactivate','activate'])) {
    $employeeId = $_POST['employee_id'] ?? '';
    $isActive = $_POST['action'] === 'activate' ? 1 : 0;
    try {
        $stmt = $conn->prepare("UPDATE employee SET is_active = ? WHERE employee_id = ?");
        if ($stmt->execute([$isActive, $employeeId])) {
            $_SESSION['success_message'] = 'Employee status updated successfully';
        } else {
            $_SESSION['error_message'] = 'Failed to update employee status';
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Database error occurred';
    }
    header('Location: manage_employees.php');
    exit();
}

// Handle add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $employeeId = trim($_POST['employee_id'] ?? '');
    $pin = trim($_POST['pin'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $positionId = trim($_POST['position_id'] ?? '');
    $departmentId = trim($_POST['department_id'] ?? '');

    // Validation
    if (empty($employeeId)) {
        $fieldErrors['employee_id'] = 'Employee ID is required';
    } elseif (!preg_match('/^\d{4}$/', $employeeId)) {
        $fieldErrors['employee_id'] = 'Employee ID must be exactly 4 digits';
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM employee WHERE employee_id = ?");
        $stmt->execute([$employeeId]);
        if ($stmt->fetchColumn() > 0) $fieldErrors['employee_id'] = 'This Employee ID already exists';
    }

    if (empty($pin)) {
        $fieldErrors['pin'] = 'PIN is required';
    } elseif (!preg_match('/^\d{6}$/', $pin)) {
        $fieldErrors['pin'] = 'PIN must be exactly 6 digits';
    }

    if (empty($firstName)) $fieldErrors['first_name'] = 'First name is required';
    if (empty($lastName)) $fieldErrors['last_name'] = 'Last name is required';
    if (empty($positionId)) {
        $fieldErrors['position_id'] = 'Position is required';
    } elseif (!preg_match('/^\d+$/', $positionId)) {
        $fieldErrors['position_id'] = 'Invalid position selection';
    } else {
        $chk = $conn->prepare("SELECT COUNT(*) FROM position WHERE position_id = ?");
        $chk->execute([$positionId]);
        if ($chk->fetchColumn() == 0) $fieldErrors['position_id'] = 'Selected position not found';
    }

    if (empty($departmentId)) {
        $fieldErrors['department_id'] = 'Department is required';
    } elseif (!preg_match('/^\d+$/', $departmentId)) {
        $fieldErrors['department_id'] = 'Invalid department selection';
    } else {
        $chk = $conn->prepare("SELECT COUNT(*) FROM department WHERE department_id = ?");
        $chk->execute([$departmentId]);
        if ($chk->fetchColumn() == 0) $fieldErrors['department_id'] = 'Selected department not found';
    }

    if (empty($fieldErrors)) {
        try {
            $ins = $conn->prepare("INSERT INTO employee (employee_id, pin, first_name, last_name, email, position_id, department_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                if ($ins->execute([$employeeId, $pin, $firstName, $lastName, $email, $positionId, $departmentId])) {
                    // Insert a broadcast notification for admins
                    try {
                        $payload = json_encode(['employee_id' => $employeeId, 'name' => $firstName . ' ' . $lastName]);
                        $nstmt = $conn->prepare("INSERT INTO notifications (user_id, type, payload, link) VALUES (NULL, ?, ?, ?)");
                        $nstmt->execute(['account_created', $payload, 'admin/manage_employees.php']);
                    } catch (PDOException $e) {
                        // ignore notification failure
                    }

                    // Enqueue welcome email if email provided (non-blocking)
                    if (!empty($email)) {
                        try {
                            require_once '../includes/email_template.php';
                            // Fetch human-readable names for email template
                            $posNameStmt = $conn->prepare("SELECT position_name FROM position WHERE position_id = ? LIMIT 1");
                            $posNameStmt->execute([$positionId]);
                            $positionName = $posNameStmt->fetchColumn() ?: 'Not assigned';

                            $deptNameStmt = $conn->prepare("SELECT department_name FROM department WHERE department_id = ? LIMIT 1");
                            $deptNameStmt->execute([$departmentId]);
                            $departmentName = $deptNameStmt->fetchColumn() ?: 'Not assigned';

                            $employeeData = [
                                'first_name' => $firstName,
                                'employee_id' => $employeeId,
                                'pin' => $pin,
                                'position' => $positionName,
                                'department' => $departmentName
                            ];
                            $subject = 'Welcome to DTR System';
                            $html = email_account_created($employeeData);
                            // Synchronous send (PHPMailer or mail())
                            send_mail($email, $subject, $html);
                        } catch (Exception $e) {
                            error_log('Welcome email failed: ' . $e->getMessage());
                        }
                    }

                    header('Location: manage_employees.php?success=1');
                    exit();
                } else {
                    $errorMessage = 'Failed to add employee';
                }
        } catch (PDOException $e) {
            $errorMessage = 'Database error: ' . $e->getMessage();
        }
    }
}

// Success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) $successMessage = 'Employee added successfully!';

// Preload positions & departments for selects
try {
    $positions = $conn->query("SELECT position_id, position_name FROM position ORDER BY position_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $positions = []; }
try {
    $departments = $conn->query("SELECT department_id, department_name FROM department ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $departments = []; }

// Fetch employees to display (include position and department names)
$employeesStmt = $conn->query("SELECT e.employee_id, e.first_name, e.last_name, e.email, e.position_id, p.position_name, e.department_id, d.department_name, e.is_active FROM employee e LEFT JOIN position p ON e.position_id = p.position_id LEFT JOIN department d ON e.department_id = d.department_id ORDER BY e.first_name, e.last_name");
$employees = $employeesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Employees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<?php require_once '../includes/header.php'; ?>

<?php require_once '../includes/sidebar.php'; ?>
<div class="container-fluid mt-4">
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Employees</h2>
        </div>

            <?php if ($successMessage || isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($successMessage ?: $_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if ($errorMessage || isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage ?: $_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Add Employee Form (left) -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white"><h5 class="mb-0">Add New Employee</h5></div>
                        <div class="card-body">
                            <form method="POST" novalidate>
                                <input type="hidden" name="action" value="add">

                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Employee ID</label>
                                    <input type="text" name="employee_id" id="employee_id" class="form-control <?php echo isset($fieldErrors['employee_id']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>">
                                    <?php if (isset($fieldErrors['employee_id'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['employee_id']; ?></div><?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="pin" class="form-label">PIN (6 digits)</label>
                                    <input type="text" name="pin" id="pin" maxlength="6" class="form-control <?php echo isset($fieldErrors['pin']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($_POST['pin'] ?? ''); ?>">
                                    <?php if (isset($fieldErrors['pin'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['pin']; ?></div><?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control <?php echo isset($fieldErrors['first_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                                    <?php if (isset($fieldErrors['first_name'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['first_name']; ?></div><?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" class="form-control <?php echo isset($fieldErrors['last_name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                                    <?php if (isset($fieldErrors['last_name'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['last_name']; ?></div><?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email (optional)</label>
                                    <input type="email" name="email" id="email" class="form-control <?php echo isset($fieldErrors['email']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    <?php if (isset($fieldErrors['email'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['email']; ?></div><?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="position_id" class="form-label">Position</label>
                                    <select name="position_id" id="position_id" class="form-select <?php echo isset($fieldErrors['position_id']) ? 'is-invalid' : ''; ?>">
                                        <option value="">Select Position</option>
                                        <?php foreach ($positions as $p): ?>
                                            <option value="<?php echo (int)$p['position_id']; ?>" <?php echo (($_POST['position_id'] ?? '') == $p['position_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['position_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($fieldErrors['position_id'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['position_id']; ?></div><?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="department_id" class="form-label">Department</label>
                                    <select name="department_id" id="department_id" class="form-select <?php echo isset($fieldErrors['department_id']) ? 'is-invalid' : ''; ?>">
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $d): ?>
                                            <option value="<?php echo (int)$d['department_id']; ?>" <?php echo (($_POST['department_id'] ?? '') == $d['department_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['department_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($fieldErrors['department_id'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['department_id']; ?></div><?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn-primary w-100">Add Employee</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- All Employees Table (right) -->
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header bg-secondary text-white"><h5 class="mb-0">All Employees</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $emp): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($emp['position_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($emp['department_name'] ?? ''); ?></td>
                                                <td><?php echo $emp['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-primary btn-sm me-1" onclick='openEditModal(<?php echo json_encode($emp, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'>Edit</button>
                                                    <?php if ($emp['is_active']): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this employee?');">
                                                            <input type="hidden" name="action" value="deactivate">
                                                            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                            <button type="submit" class="btn btn-warning btn-sm">Deactivate</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="activate">
                                                            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                                                            <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Employee Modal -->
<div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editEmployeeForm">
                    <input type="hidden" name="employee_id" id="edit_employee_id">
                    <div class="mb-3">
                        <label class="form-label">First Name</label>
                        <input type="text" id="edit_first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Last Name</label>
                        <input type="text" id="edit_last_name" name="last_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" id="edit_email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <select id="edit_position_id" name="position_id" class="form-select" required>
                            <option value="">Select Position</option>
                            <?php foreach ($positions as $p): ?>
                                <option value="<?php echo (int)$p['position_id']; ?>"><?php echo htmlspecialchars($p['position_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select id="edit_department_id" name="department_id" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int)$d['department_id']; ?>"><?php echo htmlspecialchars($d['department_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PIN (leave blank to keep current)</label>
                        <input type="text" id="edit_pin" name="pin" maxlength="6" class="form-control">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEditBtn">Save changes</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const editModal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
    function openEditModal(emp) {
        document.getElementById('edit_employee_id').value = emp.employee_id;
        document.getElementById('edit_first_name').value = emp.first_name;
        document.getElementById('edit_last_name').value = emp.last_name;
        document.getElementById('edit_email').value = emp.email || '';
        const posSelect = document.getElementById('edit_position_id');
        if (posSelect) posSelect.value = emp.position_id || '';
        const deptSelect = document.getElementById('edit_department_id');
        if (deptSelect) deptSelect.value = emp.department_id || '';
        document.getElementById('edit_pin').value = '';
        editModal.show();
    }

    document.getElementById('saveEditBtn').addEventListener('click', function() {
        const form = document.getElementById('editEmployeeForm');
        const fd = new FormData(form);

        fetch('../api/update_employee.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Unable to update employee');
                }
            })
            .catch(err => { console.error(err); alert('Error updating employee'); });
    });
</script>
</body>
</html>