<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

requireAdminLogin();

// Get record if editing
$record = null;
$employee = null;
if (isset($_GET['id'])) {
    $stmt = $conn->prepare("
        SELECT tr.*, e.first_name, e.last_name, e.employee_id as emp_id
        FROM time_record tr
        JOIN employee e ON tr.employee_id = e.employee_id
        WHERE tr.record_id = ?
    ");
    $stmt->execute([$_GET['id']]);
    $record = $stmt->fetch();
}

// Get all employees
$employeesStmt = $conn->query("SELECT employee_id, first_name, last_name FROM employee WHERE is_active = 1 ORDER BY first_name");
$employees = $employeesStmt->fetchAll();

// Get recent records
$recentStmt = $conn->query("
    SELECT tr.*, e.first_name, e.last_name, e.employee_id as emp_id
    FROM time_record tr
    JOIN employee e ON tr.employee_id = e.employee_id
    ORDER BY tr.created_at DESC
    LIMIT 50
");
$recentRecords = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Time Record - Admin</title>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Time Record</h2>
            </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
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
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <div class="list-group">
                            <?php foreach ($recentRecords as $rec): ?>
                                <?php
                                // Check if record is edited (has approved TIME_REASON and REASON_AUDIT)
                                $isEdited = false;
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM time_reason r JOIN time_approval ap ON r.reason_id = ap.reason_id AND ap.approval_status = 'approved' JOIN reason_audit a ON r.reason_id = a.reason_id WHERE r.record_id = ?");
                                $stmt->execute([$rec['record_id']]);
                                if ($stmt->fetchColumn() > 0) $isEdited = true;
                                ?>
                                <a href="?id=<?php echo $rec['record_id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo ($record && $record['record_id'] == $rec['record_id']) ? 'active' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($rec['emp_id'] . ' - ' . $rec['first_name'] . ' ' . $rec['last_name']); ?></h6>
                                        <?php if ($isEdited): ?>
                                            <span class="badge bg-warning text-dark ms-2">Edited</span>
                                        <?php endif; ?>
                                    </div>
                                    <small>
                                        <?php echo formatDate($rec['created_at'], 'M d, Y'); ?><br>
                                        AM: <?php echo formatTime($rec['am_in']); ?> - <?php echo formatTime($rec['am_out']); ?><br>
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
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                Edit Record - <?php echo htmlspecialchars($record['emp_id'] . ' - ' . $record['first_name'] . ' ' . $record['last_name']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Employee Info -->
                            <div class="alert alert-info">
                                <strong>Employee:</strong> <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?><br>
                                <!-- Position and Department fields removed as they do not exist in the employee table -->
                                <strong>Date:</strong> <?php echo formatDate($record['created_at'], 'F d, Y (l)'); ?>
                            </div>

                            <form id="editRecordForm" method="POST" action="../api/update_record.php">

                                <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="am_in" class="form-label">AM Clock In</label>
                                        <input type="datetime-local" class="form-control" id="am_in" 
                                               name="am_in" 
                                               value="<?php echo $record['am_in'] ? date('Y-m-d\TH:i', strtotime($record['am_in'])) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="am_out" class="form-label">AM Clock Out</label>
                                        <input type="datetime-local" class="form-control" id="am_out" 
                                               name="am_out" 
                                               value="<?php echo $record['am_out'] ? date('Y-m-d\TH:i', strtotime($record['am_out'])) : ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="pm_in" class="form-label">PM Clock In</label>
                                        <input type="datetime-local" class="form-control" id="pm_in" 
                                               name="pm_in" 
                                               value="<?php echo $record['pm_in'] ? date('Y-m-d\TH:i', strtotime($record['pm_in'])) : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="pm_out" class="form-label">PM Clock Out</label>
                                        <input type="datetime-local" class="form-control" id="pm_out" 
                                               name="pm_out" 
                                               value="<?php echo $record['pm_out'] ? date('Y-m-d\TH:i', strtotime($record['pm_out'])) : ''; ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="edit_reason" class="form-label">Reason for Edit <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="edit_reason" name="edit_reason" 
                                              rows="3" required 
                                              placeholder="Please provide a detailed reason for editing this record"></textarea>
                                    <small class="text-muted">This will be logged and visible in the record history.</small>
                                </div>

                                <?php
                                // Check for approved TIME_REASON and REASON_AUDIT for this record
                                $editHistory = [];
                                if (!empty($record['record_id'])) {
                                    $stmt = $conn->prepare("SELECT r.reason_type, r.reason_text, r.submitted_at, a.old_value, a.new_value, a.changed_at, a.changed_by FROM time_reason r JOIN time_approval ap ON r.reason_id = ap.reason_id AND ap.approval_status = 'approved' JOIN reason_audit a ON r.reason_id = a.reason_id WHERE r.record_id = ? ORDER BY a.changed_at DESC");
                                    $stmt->execute([$record['record_id']]);
                                    $editHistory = $stmt->fetchAll();
                                }
                                if (!empty($editHistory)):
                                ?>
                                    <div class="alert alert-warning">
                                        <strong><i class="bi bi-exclamation-triangle"></i> Edit History:</strong><br>
                                        <ul class="mb-0">
                                        <?php foreach ($editHistory as $edit): ?>
                                            <li>
                                                <b><?php echo strtoupper($edit['reason_type']); ?></b> changed from <code><?php echo htmlspecialchars($edit['old_value']); ?></code> to <code><?php echo htmlspecialchars($edit['new_value']); ?></code>
                                                by <?php echo htmlspecialchars($edit['changed_by'] ? $edit['changed_by'] : 'Admin'); ?>
                                                on <?php echo formatDate($edit['changed_at'], 'M d, Y h:i A'); ?><br>
                                                <small class="text-muted">Reason: <?php echo htmlspecialchars($edit['reason_text']); ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
                                    <a href="view_all_records.php" class="btn btn-outline-secondary">Cancel</a>
                                </div>
                            </form>

                            <!-- Reasons Section -->
                            <hr class="my-4">
                            <h5 id="reasons">Employee Reasons</h5>
                            <?php
                            $hasReasons = !empty($record['am_in_reason']) || !empty($record['am_out_reason']) || 
                                         !empty($record['pm_in_reason']) || !empty($record['pm_out_reason']);
                            
                            if ($hasReasons):
                                $entries = [
                                    ['type' => 'am_in', 'label' => 'AM Clock In'],
                                    ['type' => 'am_out', 'label' => 'AM Clock Out'],
                                    ['type' => 'pm_in', 'label' => 'PM Clock In'],
                                    ['type' => 'pm_out', 'label' => 'PM Clock Out']
                                ];
                                
                                foreach ($entries as $entry):
                                    $reasonCol = $entry['type'] . '_reason';
                                    $statusCol = $entry['type'] . '_reason_status';
                                    $timestampCol = $entry['type'] . '_reason_timestamp';
                                    
                                    if (!empty($record[$reasonCol])):
                                        $status = $record[$statusCol] ?? 'pending';
                                        $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                                        $statusBg = $statusColors[$status] ?? 'secondary';
                                    ?>
                                        <div class="card mb-3">
                                            <div class="card-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong><?php echo $entry['label']; ?></strong>
                                                    <span class="badge bg-<?php echo $statusBg; ?>"><?php echo ucfirst($status); ?></span>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <p><?php echo nl2br(htmlspecialchars($record[$reasonCol])); ?></p>
                                                <small class="text-muted">
                                                    Submitted: <?php echo !empty($record[$timestampCol]) ? formatDate($record[$timestampCol], 'M d, Y h:i A') : '—'; ?>
                                                </small>
                                            </div>
                                            <div class="card-footer">
                                                <?php if ($status === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveReason(<?php echo $record['record_id']; ?>, '<?php echo $entry['type']; ?>')">
                                                        ✓ Approve
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectReason(<?php echo $record['record_id']; ?>, '<?php echo $entry['type']; ?>')">
                                                        ✗ Reject
                                                    </button>
                                                <?php else: ?>
                                                    <small class="text-muted">Status: <?php echo ucfirst($status); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php 
                                    endif;
                                endforeach;
                            else:
                            ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> No reasons submitted for this record.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Please select a record from the list on the left to edit.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approveReason(recordId, entryType) {
            if (!confirm('Approve this reason?')) return;
            
            fetch('../api/approve_reason.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    record_id: recordId,
                    entry_type: entryType,
                    approval_status: 'approved'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Reason approved');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        }

        function rejectReason(recordId, entryType) {
            const notes = prompt('Reason for rejection (optional):');
            if (notes === null) return;
            
            fetch('../api/approve_reason.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    record_id: recordId,
                    entry_type: entryType,
                    approval_status: 'rejected',
                    admin_notes: notes
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Reason rejected');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(err => alert('Error: ' + err));
        }
    </script>
</body>
</html>