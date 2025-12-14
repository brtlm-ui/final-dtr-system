<?php
session_start();
require_once '../config/timezone.php';
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

requireEmployeeLogin();

// Get employee info
$stmt = $conn->prepare("SELECT * FROM employee WHERE employee_id = ?");
$stmt->execute([$_SESSION['employee_id']]);
$employee = $stmt->fetch();

// Get today's record
$todayRecord = getTodayRecord($conn, $_SESSION['employee_id']);

// Load any reasons submitted for today's record (normalized table)
$reasons = [];
if ($todayRecord) {
    try {
        $rstmt = $conn->prepare("SELECT reason_type, reason_text FROM time_reason WHERE record_id = ? ORDER BY submitted_at DESC");
        $rstmt->execute([$todayRecord['record_id']]);
        while ($r = $rstmt->fetch()) {
            if (!isset($reasons[$r['reason_type']])) {
                $reasons[$r['reason_type']] = $r['reason_text'];
            }
        }
    } catch (PDOException $e) {
        // ignore and treat as no reasons
    }
}

// Compute metrics for today's record (statuses/diffs/reasons)
$metrics = null;
if ($todayRecord) {
    $metrics = getRecordMetrics($conn, $todayRecord);
}

// Get official time for today
$dayOfWeek = date('l');
$officialTime = getOfficialTime($conn, $_SESSION['employee_id'], $dayOfWeek);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <?php require_once '../includes/header.php'; ?>

    <?php require_once '../includes/employee_sidebar.php'; ?>
    <div class="container-fluid mt-4">
        <div class="main-content">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($employee['first_name']); ?>!</h2>
                </div>
            </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Today's Status Card -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Today's Status</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3"><?php echo date('l, F d, Y'); ?></p>
                        
                        <?php if ($todayRecord): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <th>AM Clock In:</th>
                                        <td><?php echo formatTime($todayRecord['am_in']); ?></td>
                                        <td>
                                            <?php if ($metrics && !empty($metrics['am_in']['status'])): ?>
                                                <span class="badge" style="<?php echo getBadgeStyle($metrics['am_in']['status']); ?>">
                                                    <?php echo $metrics['am_in']['status']; ?>
                                                </span>
                                                <?php
                                                    $diffText = '';
                                                    if (!empty($metrics['am_in']['value']) && !empty($metrics['am_in']['official'])) {
                                                        $diffText = differenceText($metrics['am_in']['value'], $metrics['am_in']['official'], $metrics['am_in']['status']);
                                                    }
                                                ?>
                                                <?php if ($diffText): ?>
                                                    <div class="small text-muted"><?php echo $diffText; ?></div>
                                                <?php endif; ?>
                                                <?php if ($metrics['am_in']['status'] !== 'ON TIME' && empty($metrics['am_in']['reason'])): ?>
                                                    <button class="btn btn-sm btn-outline-secondary mt-1" onclick="openReasonModal('am_in', <?php echo $todayRecord['record_id']; ?>)">Add Reason</button>
                                                <?php elseif (!empty($metrics['am_in']['reason'])): ?>
                                                    <div class="small mt-1"><strong>Reason:</strong> <?php echo htmlspecialchars($metrics['am_in']['reason']); ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>AM Clock Out:</th>
                                        <td><?php echo formatTime($todayRecord['am_out']); ?></td>
                                        <td>
                                            <?php if ($metrics && !empty($metrics['am_out']['status'])): ?>
                                                <span class="badge" style="<?php echo getBadgeStyle($metrics['am_out']['status']); ?>">
                                                    <?php echo $metrics['am_out']['status']; ?>
                                                </span>
                                                <?php
                                                    $diffText = '';
                                                    if (!empty($metrics['am_out']['value']) && !empty($metrics['am_out']['official'])) {
                                                        $diffText = differenceText($metrics['am_out']['value'], $metrics['am_out']['official'], $metrics['am_out']['status']);
                                                    }
                                                ?>
                                                <?php if ($diffText): ?>
                                                    <div class="small text-muted"><?php echo $diffText; ?></div>
                                                <?php endif; ?>
                                                <?php if ($metrics['am_out']['status'] !== 'ON TIME' && empty($metrics['am_out']['reason'])): ?>
                                                    <button class="btn btn-sm btn-outline-secondary mt-1" onclick="openReasonModal('am_out', <?php echo $todayRecord['record_id']; ?>)">Add Reason</button>
                                                <?php elseif (!empty($metrics['am_out']['reason'])): ?>
                                                    <div class="small mt-1"><strong>Reason:</strong> <?php echo htmlspecialchars($metrics['am_out']['reason']); ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>PM Clock In:</th>
                                        <td><?php echo formatTime($todayRecord['pm_in']); ?></td>
                                        <td>
                                            <?php if ($metrics && !empty($metrics['pm_in']['status'])): ?>
                                                <span class="badge" style="<?php echo getBadgeStyle($metrics['pm_in']['status']); ?>">
                                                    <?php echo $metrics['pm_in']['status']; ?>
                                                </span>
                                                <?php
                                                    $diffText = '';
                                                    if (!empty($metrics['pm_in']['value']) && !empty($metrics['pm_in']['official'])) {
                                                        $diffText = differenceText($metrics['pm_in']['value'], $metrics['pm_in']['official'], $metrics['pm_in']['status']);
                                                    }
                                                ?>
                                                <?php if ($diffText): ?>
                                                    <div class="small text-muted"><?php echo $diffText; ?></div>
                                                <?php endif; ?>
                                                <?php if ($metrics['pm_in']['status'] !== 'ON TIME' && empty($metrics['pm_in']['reason'])): ?>
                                                    <button class="btn btn-sm btn-outline-secondary mt-1" onclick="openReasonModal('pm_in', <?php echo $todayRecord['record_id']; ?>)">Add Reason</button>
                                                <?php elseif (!empty($metrics['pm_in']['reason'])): ?>
                                                    <div class="small mt-1"><strong>Reason:</strong> <?php echo htmlspecialchars($metrics['pm_in']['reason']); ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>PM Clock Out:</th>
                                        <td><?php echo formatTime($todayRecord['pm_out']); ?></td>
                                        <td>
                                            <?php if ($metrics && !empty($metrics['pm_out']['status'])): ?>
                                                <span class="badge" style="<?php echo getBadgeStyle($metrics['pm_out']['status']); ?>">
                                                    <?php echo $metrics['pm_out']['status']; ?>
                                                </span>
                                                <?php
                                                    $diffText = '';
                                                    if (!empty($metrics['pm_out']['value']) && !empty($metrics['pm_out']['official'])) {
                                                        $diffText = differenceText($metrics['pm_out']['value'], $metrics['pm_out']['official'], $metrics['pm_out']['status']);
                                                    }
                                                ?>
                                                <?php if ($diffText): ?>
                                                    <div class="small text-muted"><?php echo $diffText; ?></div>
                                                <?php endif; ?>
                                                <?php if ($metrics['pm_out']['status'] !== 'ON TIME' && empty($metrics['pm_out']['reason'])): ?>
                                                    <button class="btn btn-sm btn-outline-secondary mt-1" onclick="openReasonModal('pm_out', <?php echo $todayRecord['record_id']; ?>)">Add Reason</button>
                                                <?php elseif (!empty($metrics['pm_out']['reason'])): ?>
                                                    <div class="small mt-1"><strong>Reason:</strong> <?php echo htmlspecialchars($metrics['pm_out']['reason']); ?></div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Clock Out Buttons -->
                            <div class="d-grid gap-2 mt-3">
                                <?php if (!$todayRecord['am_out']): ?>
                                    <button class="btn btn-warning" id="clockOutAMBtn">Clock Out (AM)</button>
                                <?php elseif (!$todayRecord['pm_in']): ?>
                                    <button class="btn btn-primary" id="clockInPMBtn">Clock In (PM)</button>
                                <?php elseif (!$todayRecord['pm_out']): ?>
                                    <button class="btn btn-warning" id="clockOutPMBtn">Clock Out (PM)</button>
                                <?php else: ?>
                                    <div class="alert alert-success mb-0">
                                        ✓ All clock entries completed for today
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No clock-in record for today yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Official Schedule Card -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Official Schedule (<?php echo $dayOfWeek; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($officialTime): ?>
                            <table class="table table-sm">
                                <tr>
                                    <th>AM Time In:</th>
                                    <td><?php echo date('h:i A', strtotime($officialTime['am_time_in'])); ?></td>
                                </tr>
                                <tr>
                                    <th>AM Time Out:</th>
                                    <td><?php echo date('h:i A', strtotime($officialTime['am_time_out'])); ?></td>
                                </tr>
                                <tr>
                                    <th>PM Time In:</th>
                                    <td><?php echo date('h:i A', strtotime($officialTime['pm_time_in'])); ?></td>
                                </tr>
                                <tr>
                                    <th>PM Time Out:</th>
                                    <td><?php echo date('h:i A', strtotime($officialTime['pm_time_out'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Grace Period:</th>
                                    <td><?php echo $officialTime['grace_period_minutes']; ?> minutes</td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                No schedule set for <?php echo $dayOfWeek; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quick Print Button -->
                        <div class="d-grid gap-2 mt-3">
                            <a href="../print/employee_timesheet.php?year=<?php echo date('Y'); ?>&month=<?php echo date('m'); ?>" 
                               target="_blank" class="btn btn-outline-success">
                                <i class="bi bi-printer"></i> Print My Monthly Timesheet
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/toast.js"></script>
    <!-- Reason Modal -->
    <div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="reasonModalLabel">Submit Reason</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="reasonError" class="alert alert-danger d-none"></div>
            <div class="mb-3">
              <label for="reasonText" class="form-label">Reason</label>
              <textarea id="reasonText" class="form-control" rows="4" placeholder="e.g., Personal appointment, Family emergency, Traffic delay"></textarea>
            </div>
            <input type="hidden" id="reasonRecordId">
            <input type="hidden" id="reasonEntryType">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" id="submitReasonBtn">Submit Reason</button>
          </div>
        </div>
      </div>
    </div>
        </div>
    <script>
        // Reason modal handling (dynamic - per entry)
        function openReasonModal(entryType, recordId) {
            document.getElementById('reasonEntryType').value = entryType;
            document.getElementById('reasonRecordId').value = recordId;
            document.getElementById('reasonText').value = '';
            document.getElementById('reasonError').classList.add('d-none');
            var reasonModal = new bootstrap.Modal(document.getElementById('reasonModal'));
            reasonModal.show();
        }

        document.getElementById('submitReasonBtn')?.addEventListener('click', function() {
            var recordId = document.getElementById('reasonRecordId').value;
            var entryType = document.getElementById('reasonEntryType').value;
            var reason = document.getElementById('reasonText').value.trim();
            var errorDiv = document.getElementById('reasonError');
            var submitBtn = this;

            if (!reason) {
                errorDiv.textContent = 'Please enter a reason';
                errorDiv.classList.remove('d-none');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            errorDiv.classList.add('d-none');

            var formData = new FormData();
            formData.append('record_id', recordId);
            formData.append('entry_type', entryType);
            formData.append('reason', reason);

            fetch('../api/reason.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Reason submitted successfully!', 'success', 2000);
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast('Error: ' + (data.message || 'An error occurred'), 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Reason';
                }
            })
            .catch(() => {
                showToast('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Reason';
            });
        });
    </script>

    <!-- Mandatory Reason Modal for LATE/OVERTIME -->
    <div class="modal fade" id="clockReasonModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Reason Required</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <strong id="dashboardClockReasonStatus"></strong> - Please provide a reason before proceeding.
                    </div>
                    <div class="mb-3">
                        <label for="dashboardClockReasonText" class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="dashboardClockReasonText" rows="4" required placeholder="Enter your reason..."></textarea>
                        <div class="invalid-feedback">
                            Please provide a reason.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="submitDashboardClockReasonBtn">
                        <span id="submitDashboardReasonText">Submit</span>
                        <span class="spinner-border spinner-border-sm d-none" id="submitDashboardReasonSpinner"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let dashboardClockReasonModal;
    let dashboardPendingClockData = null;

    document.addEventListener('DOMContentLoaded', function() {
        dashboardClockReasonModal = new bootstrap.Modal(document.getElementById('clockReasonModal'));

        // Submit reason handler
        document.getElementById('submitDashboardClockReasonBtn').addEventListener('click', function() {
            const reasonText = document.getElementById('dashboardClockReasonText').value.trim();
            
            if (!reasonText) {
                document.getElementById('dashboardClockReasonText').classList.add('is-invalid');
                return;
            }

            document.getElementById('dashboardClockReasonText').classList.remove('is-invalid');
            const submitBtn = document.getElementById('submitDashboardClockReasonBtn');
            const submitText = document.getElementById('submitDashboardReasonText');
            const submitSpinner = document.getElementById('submitDashboardReasonSpinner');
            
            submitBtn.disabled = true;
            submitText.textContent = 'Submitting...';
            submitSpinner.classList.remove('d-none');

            fetch('../api/submit_clock_reason.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    record_id: dashboardPendingClockData.record_id,
                    reason_type: dashboardPendingClockData.reason_type,
                    reason_text: reasonText,
                    clock_datetime: dashboardPendingClockData.clock_datetime
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Reason submitted successfully! Notification sent to admin.', 'success', 3000);
                    setTimeout(() => location.reload(), 1000);
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

        // Unified clock handlers (ensures mandatory reason modal triggers correctly)
        function setupClockButton(buttonId, confirmMessage, period, modalStatuses) {
            const btn = document.getElementById(buttonId);
            if (!btn) return;

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm(confirmMessage)) {
                    return;
                }

                fetch('../api/clock_out.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ period })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message);
                        return;
                    }

                    if (modalStatuses.includes(data.status)) {
                        dashboardPendingClockData = {
                            record_id: data.record_id,
                            reason_type: data.reason_type,
                            clock_datetime: data.clock_datetime
                        };
                        document.getElementById('dashboardClockReasonStatus').textContent = data.status;
                        document.getElementById('dashboardClockReasonText').value = '';
                        dashboardClockReasonModal.show();
                    } else {
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred. Please try again.');
                });
            });
        }

        setupClockButton('clockOutAMBtn', 'Are you sure you want to clock out for AM?', 'am', ['EARLY LEAVE', 'OVERTIME']);
        setupClockButton('clockInPMBtn', 'Are you sure you want to clock in for PM?', 'pm_in', ['LATE', 'OVERTIME']);
        setupClockButton('clockOutPMBtn', 'Are you sure you want to clock out for PM?', 'pm', ['EARLY LEAVE', 'OVERTIME']);
    });
    </script>
</body>
</html>