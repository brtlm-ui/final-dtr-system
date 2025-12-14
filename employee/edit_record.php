<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

requireEmployeeLogin();

// Get employee info
$stmt = $conn->prepare("SELECT * FROM employee WHERE employee_id = ?");
$stmt->execute([$_SESSION['employee_id']]);
$employee = $stmt->fetch();

// Get record if editing
$record = null;
if (isset($_GET['id'])) {
    $stmt = $conn->prepare("SELECT * FROM time_record WHERE record_id = ? AND employee_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['employee_id']]);
    $record = $stmt->fetch();
}

// Get recent records for selection
$stmt = $conn->prepare("
    SELECT * FROM time_record 
    WHERE employee_id = ? 
    ORDER BY created_at DESC 
    LIMIT 30
");
$stmt->execute([$_SESSION['employee_id']]);
$recentRecords = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Time Record - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <?php
// Include header
require_once '../includes/header.php';
?>

    <?php require_once '../includes/employee_sidebar.php'; ?>
    <div class="container-fluid mt-4">
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Time Record</h2>
            </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

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
            <!-- Select Record -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Select Record to Edit</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($recentRecords as $rec): ?>
                                <a href="?id=<?php echo $rec['record_id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo ($record && $record['record_id'] == $rec['record_id']) ? 'active' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo formatDate($rec['created_at'], 'M d, Y'); ?></h6>
                                    </div>
                                    <small>
                                        AM: <?php echo formatTime($rec['am_in']); ?> - <?php echo formatTime($rec['am_out']); ?>
                                        <br>
                                        PM: <?php echo formatTime($rec['pm_in']); ?> - <?php echo formatTime($rec['pm_out']); ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="col-md-8">
                <?php if ($record): ?>
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">Edit Record - <?php echo formatDate($record['created_at'], 'F d, Y'); ?></h5>
                        </div>
                        <div class="card-body">
                            <form id="editRecordForm" method="POST" action="../api/update_record.php">
                                <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="am_in" class="form-label">AM Clock In</label>
                                        <input type="time" class="form-control" id="am_in" 
                                               name="am_in" step="60"
                                               value="<?php echo $record['am_in'] ? date('H:i', strtotime($record['am_in'])) : ''; ?>">
                                        <small class="text-muted">Date is fixed; edit time only.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="am_out" class="form-label">AM Clock Out</label>
                                        <input type="time" class="form-control" id="am_out" 
                                               name="am_out" step="60"
                                               value="<?php echo $record['am_out'] ? date('H:i', strtotime($record['am_out'])) : ''; ?>">
                                        <small class="text-muted">Date is fixed; edit time only.</small>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="pm_in" class="form-label">PM Clock In</label>
                                        <input type="time" class="form-control" id="pm_in" 
                                               name="pm_in" step="60"
                                               value="<?php echo $record['pm_in'] ? date('H:i', strtotime($record['pm_in'])) : ''; ?>">
                                        <small class="text-muted">Date is fixed; edit time only.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pm_out" class="form-label">PM Clock Out</label>
                                        <input type="time" class="form-control" id="pm_out" 
                                               name="pm_out" step="60"
                                               value="<?php echo $record['pm_out'] ? date('H:i', strtotime($record['pm_out'])) : ''; ?>">
                                        <small class="text-muted">Date is fixed; edit time only.</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="edit_reason" class="form-label">Reason for Edit <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="edit_reason" name="edit_reason" 
                                              rows="3" required 
                                              placeholder="Please provide a reason for editing this record"></textarea>
                                </div>

                                <?php
                                // Show edit history for this record (approved TIME_REASON and REASON_AUDIT)
                                $editHistory = [];
                                if (!empty($record['record_id'])) {
                                    $stmt = $conn->prepare("SELECT r.reason_type, r.reason_text, r.submitted_at, a.old_value, a.new_value, a.changed_at FROM time_reason r JOIN time_approval ap ON r.reason_id = ap.reason_id AND ap.approval_status = 'approved' JOIN reason_audit a ON r.reason_id = a.reason_id WHERE r.record_id = ? AND r.reason_text != 'Admin Correction' ORDER BY a.changed_at DESC");
                                    $stmt->execute([$record['record_id']]);
                                    $editHistory = $stmt->fetchAll();
                                }
                                if (!empty($editHistory)):
                                ?>
                                    <div class="alert alert-info">
                                        <strong>Edit History:</strong><br>
                                        <ul class="mb-0">
                                        <?php foreach ($editHistory as $edit): ?>
                                            <li>
                                                <b><?php echo strtoupper($edit['reason_type']); ?></b> changed from <code><?php echo htmlspecialchars($edit['old_value']); ?></code> to <code><?php echo htmlspecialchars($edit['new_value']); ?></code>
                                                on <?php echo formatDate($edit['changed_at'], 'M d, Y h:i A'); ?><br>
                                                <small class="text-muted">Reason: <?php echo htmlspecialchars($edit['reason_text']); ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                    <a href="view_records.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-info">
                                Please select a record from the list to edit.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/toast.js"></script>
    <script>
    // Submit only fields that actually changed (minute precision)
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('editRecordForm');
        if (!form) return;
        const fieldIds = ['am_in','am_out','pm_in','pm_out'];
        const original = {};
        const toMinute = (v) => (v || '').slice(0,5); // HH:MM
        fieldIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) original[id] = toMinute(el.value || '');
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            const editReason = document.getElementById('edit_reason').value.trim();
            
            if (!editReason) {
                showToast('Please provide a reason for editing', 'warning');
                document.getElementById('edit_reason').focus();
                return;
            }
            
            // Check if any field changed
            let hasChanges = false;
            fieldIds.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                const nowVal = toMinute(el.value || '');
                const oldVal = original[id] || '';
                if (nowVal !== oldVal) {
                    hasChanges = true;
                }
            });
            
            if (!hasChanges) {
                showToast('No changes detected in time fields', 'warning');
                return;
            }
            
            // Disable unchanged fields before submission
            fieldIds.forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                const nowVal = toMinute(el.value || '');
                const oldVal = original[id] || '';
                if (nowVal === oldVal) {
                    el.disabled = true;
                }
            });
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting Edit Request...';
            
            // Submit via FormData
            const formData = new FormData(form);
            
            fetch('../api/update_record.php', {
                method: 'POST',
                body: formData
            })
            .then(res => {
                if (!res.ok) {
                    return res.text().then(text => {
                        throw new Error('Server error: ' + text);
                    });
                }
                return res.text();
            })
            .then(text => {
                // Check if response is HTML (redirect happened)
                if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<')) {
                    showToast('Edit request submitted successfully! Notification sent to admin.', 'success', 3000);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    // Try to parse as JSON
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showToast('Edit request submitted successfully! Notification sent to admin.', 'success', 3000);
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                            // Re-enable fields
                            fieldIds.forEach(id => {
                                const el = document.getElementById(id);
                                if (el) el.disabled = false;
                            });
                        }
                    } catch (e) {
                        // Assume success if we got HTML (redirect happened)
                        showToast('Edit request submitted successfully! Notification sent to admin.', 'success', 3000);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    }
                }
            })
            .catch(err => {
                console.error(err);
                showToast('An error occurred: ' + err.message, 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                // Re-enable fields
                fieldIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.disabled = false;
                });
            });
        });
    });
    </script>
</body>
</html>