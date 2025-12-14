<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if employee data is in session
if (!isset($_SESSION['confirm_employee_id'])) {
    // If already logged in (has employee_id), redirect to dashboard
    if (isset($_SESSION['employee_id']) && $_SESSION['user_type'] === 'employee') {
        header('Location: dashboard.php');
        exit();
    }
    // Not logged in at all
    header('Location: login.php');
    exit();
}

// If user already clocked in today, set full session so dashboard access works
if (!isset($_SESSION['employee_id']) || ($_SESSION['user_type'] ?? '') !== 'employee') {
    $alreadyClocked = hasClockInToday($conn, $_SESSION['confirm_employee_id']);
    if ($alreadyClocked) {
        $_SESSION['employee_id'] = $_SESSION['confirm_employee_id'];
        $_SESSION['user_type'] = 'employee';
    }
}


// Get employee info with position and department names
$stmt = $conn->prepare("
    SELECT e.*, p.position_name AS position_name, d.department_name AS department_name
    FROM employee e
    LEFT JOIN position p ON e.position_id = p.position_id
    LEFT JOIN department d ON e.department_id = d.department_id
    WHERE e.employee_id = ? AND e.is_active = 1
");
$stmt->execute([$_SESSION['confirm_employee_id']]);
$employee = $stmt->fetch();

if (!$employee) {
    session_unset();
    header('Location: login.php');
    exit();
}

// Mask the name
$maskedFirstName = maskName($employee['first_name']);
$maskedLastName = maskName($employee['last_name']);

// Check if already clocked in today and get record
$alreadyClockedIn = hasClockInToday($conn, $employee['employee_id']);
$todayRecord = $alreadyClockedIn ? getTodayRecord($conn, $employee['employee_id']) : null;

// Get IP address
$ipAddress = $_SERVER['REMOTE_ADDR'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Identity - DTR System</title>
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
                            <h2 class="fw-bold">Confirm Your Identity</h2>
                            <p class="text-muted">Please verify your information</p>
                        </div>

                        <div class="employee-info mb-4">
                            <div class="mb-3">
                                <label class="form-label text-muted">Employee ID:</label>
                                <div class="fs-5 fw-semibold"><?php echo htmlspecialchars($employee['employee_id']); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Name:</label>
                                <div class="fs-5 fw-semibold">
                                    <?php echo htmlspecialchars($maskedFirstName . ' ' . $maskedLastName); ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Position:</label>
                                <div class="fs-5 fw-semibold"><?php echo htmlspecialchars($employee['position_name'] ?? ''); ?></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Department:</label>
                                <div class="fs-5 fw-semibold"><?php echo htmlspecialchars($employee['department_name'] ?? ''); ?></div>
                            </div>
                        </div>

                        <div id="confirm-error" class="alert alert-danger d-none"></div>
                        <div id="confirm-success" class="alert alert-success d-none"></div>

                        <form id="confirmForm">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="confirmBtn" style="font-size:1.1rem;">
                                    <span id="btnText">Confirm & Clock In</span>
                                    <span id="btnSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                                </button>
                                <?php if (isset($_SESSION['employee_id']) && ($_SESSION['user_type'] ?? '') === 'employee'): ?>
                                    <a href="dashboard.php" class="btn btn-success btn-lg w-100" style="font-size:1.1rem;">Go to Dashboard</a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-success btn-lg w-100" style="font-size:1.1rem;" disabled title="Clock in first to access the dashboard">Go to Dashboard</button>
                                <?php endif; ?>
                                <a href="login.php" class="btn btn-outline-secondary btn-lg w-100" style="font-size:1.1rem;">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mandatory Reason Modal for LATE/OVERTIME -->
    <div class="modal fade" id="clockReasonModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="clockReasonModalTitle">Reason Required</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong id="clockReasonStatus"></strong> - Please provide a reason before proceeding.
                    </div>
                    <div class="mb-3">
                        <label for="clockReasonText" class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="clockReasonText" rows="4" required placeholder="Enter your reason..."></textarea>
                        <div class="invalid-feedback" id="clockReasonError">
                            Please provide a reason.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="submitClockReasonBtn">
                        <span id="submitReasonText">Submit</span>
                        <span class="spinner-border spinner-border-sm d-none" id="submitReasonSpinner"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/toast.js"></script>
    <script src="../assets/js/employee.js"></script>
    <script>
    let clockReasonModal;
    let pendingClockData = null;

    document.addEventListener('DOMContentLoaded', function() {
        clockReasonModal = new bootstrap.Modal(document.getElementById('clockReasonModal'));

        // Submit reason handler
        document.getElementById('submitClockReasonBtn').addEventListener('click', function() {
            const reasonText = document.getElementById('clockReasonText').value.trim();
            
            if (!reasonText) {
                document.getElementById('clockReasonText').classList.add('is-invalid');
                return;
            }

            document.getElementById('clockReasonText').classList.remove('is-invalid');
            const submitBtn = document.getElementById('submitClockReasonBtn');
            const submitText = document.getElementById('submitReasonText');
            const submitSpinner = document.getElementById('submitReasonSpinner');
            
            submitBtn.disabled = true;
            submitText.textContent = 'Submitting...';
            submitSpinner.classList.remove('d-none');

            const requestData = {
                record_id: pendingClockData.record_id,
                reason_type: pendingClockData.reason_type,
                reason_text: reasonText,
                clock_datetime: pendingClockData.clock_datetime
            };
            console.log('Submitting reason:', requestData);

            fetch('../api/submit_clock_reason.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(requestData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Reason submitted successfully! Notification sent to admin.', 'success', 3000);
                    // Small delay to show toast before redirect
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    showToast('Error: ' + data.message, 'error');
                    submitBtn.disabled = false;
                    submitText.textContent = 'Submit';
                    submitSpinner.classList.add('d-none');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitText.textContent = 'Submit';
                submitSpinner.classList.add('d-none');
            });
        });
    });

    // Override the confirm form submission
    document.getElementById('confirmForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const btn = document.getElementById('confirmBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');
        
        btn.disabled = true;
        btnText.textContent = 'Processing...';
        btnSpinner.classList.remove('d-none');

        fetch('../api/clock_in.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'}
        })
        .then(res => res.json())
        .then(data => {
            console.log('Clock in response:', data);
            if (data.success) {
                // Check if status is LATE or OVERTIME
                if (data.status === 'LATE' || data.status === 'OVERTIME') {
                    console.log('Status requires reason:', data.status);
                    // Store data and show modal
                    pendingClockData = {
                        record_id: data.record_id,
                        reason_type: data.reason_type,
                        clock_datetime: data.clock_datetime
                    };
                    
                    // Reset button state
                    btn.disabled = false;
                    btnText.textContent = 'Confirm & Clock In';
                    btnSpinner.classList.add('d-none');
                    
                    document.getElementById('clockReasonStatus').textContent = data.status;
                    document.getElementById('clockReasonText').value = '';
                    clockReasonModal.show();
                } else {
                    // ON TIME - proceed normally
                    console.log('Status is ON TIME, redirecting');
                    window.location.href = data.redirect;
                }
            } else {
                console.error('Clock in failed:', data.message);
                alert(data.message);
                btn.disabled = false;
                btnText.textContent = 'Confirm & Clock In';
                btnSpinner.classList.add('d-none');
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred. Please try again.');
            btn.disabled = false;
            btnText.textContent = 'Confirm & Clock In';
            btnSpinner.classList.add('d-none');
        });
    });
    </script>
</body>
</html>