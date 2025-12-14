<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

requireAdminLogin();

$fieldErrors = [];
$successMessage = '';
$errorMessage = '';

// Handle schedule submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_schedule') {
        $employeeId = trim($_POST['employee_id'] ?? '');
        $dayOfWeek = trim($_POST['day_of_week'] ?? '');
        $amTimeIn = trim($_POST['am_time_in'] ?? '');
        $amTimeOut = trim($_POST['am_time_out'] ?? '');
        $pmTimeIn = trim($_POST['pm_time_in'] ?? '');
        $pmTimeOut = trim($_POST['pm_time_out'] ?? '');
        $gracePeriod = trim($_POST['grace_period_minutes'] ?? '15');

        // Validate inputs
        $errors = [];
        if (empty($employeeId)) {
            $errors['employee_id'] = 'Employee ID is required';
        }
        if (empty($dayOfWeek)) {
            $errors['day_of_week'] = 'Day of week is required';
        }
        if (empty($amTimeIn)) {
            $errors['am_time_in'] = 'Morning time in is required';
        }
        if (empty($amTimeOut)) {
            $errors['am_time_out'] = 'Morning time out is required';
        }
        if (empty($pmTimeIn)) {
            $errors['pm_time_in'] = 'Afternoon time in is required';
        }
        if (empty($pmTimeOut)) {
            $errors['pm_time_out'] = 'Afternoon time out is required';
        }

        if (empty($errors)) {
            try {
                // Check if schedule already exists for this employee and day
                $stmt = $conn->prepare("SELECT COUNT(*) FROM official_time WHERE employee_id = ? AND day_of_week = ?");
                $stmt->execute([$employeeId, $dayOfWeek]);
                if ($stmt->fetchColumn() > 0) {
                    // Update existing schedule
                    $stmt = $conn->prepare("
                        UPDATE official_time 
                        SET am_time_in = ?, am_time_out = ?, pm_time_in = ?, pm_time_out = ?, 
                            grace_period_minutes = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE employee_id = ? AND day_of_week = ?
                    ");
                    $stmt->execute([$amTimeIn, $amTimeOut, $pmTimeIn, $pmTimeOut, $gracePeriod, $employeeId, $dayOfWeek]);
                } else {
                    // Insert new schedule
                    $stmt = $conn->prepare("
                        INSERT INTO official_time (employee_id, day_of_week, am_time_in, am_time_out, 
                            pm_time_in, pm_time_out, grace_period_minutes, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$employeeId, $dayOfWeek, $amTimeIn, $amTimeOut, $pmTimeIn, $pmTimeOut, $gracePeriod]);
                }
                $successMessage = 'Schedule updated successfully';
            } catch (PDOException $e) {
                $errorMessage = 'Failed to update schedule';
            }
        } else {
            $fieldErrors = $errors;
        }
    }
}

// Get all employees for the dropdown
$stmt = $conn->query("SELECT employee_id, first_name, last_name FROM employee WHERE is_active = 1 ORDER BY first_name");
$employees = $stmt->fetchAll();

// Get filter parameters
$filterEmployee = $_GET['employee_id'] ?? '';
$searchName = trim($_GET['search'] ?? '');

// Build query for schedules with filters
$scheduleQuery = "
    SELECT ot.*, e.first_name, e.last_name 
    FROM official_time ot 
    JOIN employee e ON ot.employee_id = e.employee_id 
    WHERE 1=1
";
$params = [];

if (!empty($filterEmployee)) {
    $scheduleQuery .= " AND e.employee_id = ?";
    $params[] = $filterEmployee;
}

if (!empty($searchName)) {
    $scheduleQuery .= " AND (e.first_name LIKE ? OR e.last_name LIKE ?)";
    $params[] = "%{$searchName}%";
    $params[] = "%{$searchName}%";
}

$scheduleQuery .= " ORDER BY e.first_name, ot.day_of_week";

$stmt = $conn->prepare($scheduleQuery);
$stmt->execute($params);
$schedules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <?php
// Include header
require_once '../includes/header.php';
?>

<?php require_once '../includes/sidebar.php'; ?>
<div class="container-fluid mt-4">
    <div class="main-content">
        <h1>Manage Schedules</h1>

            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search by Name</label>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Enter first or last name" 
                                   value="<?php echo htmlspecialchars($searchName); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filter_employee_id" class="form-label">Filter by Employee</label>
                            <select id="filter_employee_id" name="employee_id" class="form-select">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>" 
                                            <?php echo $filterEmployee == $emp['employee_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="manage_schedules.php" class="btn btn-outline-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row">
                <!-- Add/Edit Schedule Form -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Add/Edit Schedule</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" novalidate>
                                <input type="hidden" name="action" value="add_schedule">

                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Employee</label>
                                    <select name="employee_id" id="employee_id" class="form-select <?php echo isset($fieldErrors['employee_id']) ? 'is-invalid' : ''; ?>" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($employees as $emp): ?>
                                        <option value="<?= htmlspecialchars($emp['employee_id']) ?>">
                                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($fieldErrors['employee_id'])): ?>
                                    <div class="invalid-feedback"><?= $fieldErrors['employee_id'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="day_of_week" class="form-label">Day of Week</label>
                                <select name="day_of_week" id="day_of_week" class="form-select <?php echo isset($fieldErrors['day_of_week']) ? 'is-invalid' : ''; ?>" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                    <option value="Sunday">Sunday</option>
                                </select>
                                <?php if (isset($fieldErrors['day_of_week'])): ?>
                                    <div class="invalid-feedback"><?= $fieldErrors['day_of_week'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="am_time_in" class="form-label">Morning Time In</label>
                                    <input type="time" name="am_time_in" id="am_time_in" class="form-control <?php echo isset($fieldErrors['am_time_in']) ? 'is-invalid' : ''; ?>" required>
                                    <?php if (isset($fieldErrors['am_time_in'])): ?>
                                        <div class="invalid-feedback"><?= $fieldErrors['am_time_in'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="am_time_out" class="form-label">Morning Time Out</label>
                                    <input type="time" name="am_time_out" id="am_time_out" class="form-control <?php echo isset($fieldErrors['am_time_out']) ? 'is-invalid' : ''; ?>" required>
                                    <?php if (isset($fieldErrors['am_time_out'])): ?>
                                        <div class="invalid-feedback"><?= $fieldErrors['am_time_out'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="pm_time_in" class="form-label">Afternoon Time In</label>
                                    <input type="time" name="pm_time_in" id="pm_time_in" class="form-control <?php echo isset($fieldErrors['pm_time_in']) ? 'is-invalid' : ''; ?>" required>
                                    <?php if (isset($fieldErrors['pm_time_in'])): ?>
                                        <div class="invalid-feedback"><?= $fieldErrors['pm_time_in'] ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="pm_time_out" class="form-label">Afternoon Time Out</label>
                                    <input type="time" name="pm_time_out" id="pm_time_out" class="form-control <?php echo isset($fieldErrors['pm_time_out']) ? 'is-invalid' : ''; ?>" required>
                                    <?php if (isset($fieldErrors['pm_time_out'])): ?>
                                        <div class="invalid-feedback"><?= $fieldErrors['pm_time_out'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="grace_period_minutes" class="form-label">Grace Period (minutes)</label>
                                <input type="number" name="grace_period_minutes" id="grace_period_minutes" class="form-control" value="15" min="0" max="60">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Save Schedule</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Schedule List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Employee Schedules</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Day</th>
                                        <th>Morning Schedule</th>
                                        <th>Afternoon Schedule</th>
                                        <th>Grace Period</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedules as $schedule): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></td>
                                            <td><?= htmlspecialchars($schedule['day_of_week']) ?></td>
                                            <td><?= htmlspecialchars(substr($schedule['am_time_in'], 0, 5) . ' - ' . substr($schedule['am_time_out'], 0, 5)) ?></td>
                                            <td><?= htmlspecialchars(substr($schedule['pm_time_in'], 0, 5) . ' - ' . substr($schedule['pm_time_out'], 0, 5)) ?></td>
                                            <td><?= htmlspecialchars($schedule['grace_period_minutes']) ?> mins</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary edit-schedule" 
                                                    data-schedule='<?= json_encode($schedule) ?>'>
                                                    Edit
                                                </button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle edit button clicks
        document.querySelectorAll('.edit-schedule').forEach(button => {
            button.addEventListener('click', function() {
                const schedule = JSON.parse(this.dataset.schedule);
                document.getElementById('employee_id').value = schedule.employee_id;
                document.getElementById('day_of_week').value = schedule.day_of_week;
                document.getElementById('am_time_in').value = schedule.am_time_in.substr(0, 5);
                document.getElementById('am_time_out').value = schedule.am_time_out.substr(0, 5);
                document.getElementById('pm_time_in').value = schedule.pm_time_in.substr(0, 5);
                document.getElementById('pm_time_out').value = schedule.pm_time_out.substr(0, 5);
                document.getElementById('grace_period_minutes').value = schedule.grace_period_minutes;
            });
        });
    });
    </script>
    </div>
</div>
</body>
</html>