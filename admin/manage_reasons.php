<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../config/timezone.php';

if (!isAdminLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'pending'; // pending, approved, rejected, all
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-29 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$filterEmployee = $_GET['employee_id'] ?? '';

// Fetch reason records based on filters (normalized tables)
try {
    $whereConditions = [];
    $params = [];

    // Filter by date range (on time_record.record_date)
    if ($startDate !== 'all' && $endDate !== 'all') {
        $whereConditions[] = "DATE(tr.record_date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    } elseif ($startDate !== 'all' && $endDate === 'all') {
        $whereConditions[] = "DATE(tr.record_date) >= ?";
        $params[] = $startDate;
    } elseif ($startDate === 'all' && $endDate !== 'all') {
        $whereConditions[] = "DATE(tr.record_date) <= ?";
        $params[] = $endDate;
    }

    // Filter by employee
    if ($filterEmployee !== '' && $filterEmployee !== 'all') {
        $whereConditions[] = "e.employee_id = ?";
        $params[] = $filterEmployee;
    }

    // Build base query: select reason rows with latest approval status (null -> pending) and get old/new values from audit
    $sql = "SELECT r.reason_id, r.record_id, r.reason_type, r.reason_text, r.submitted_at, tr.record_date, e.employee_id, e.first_name, e.last_name,
                COALESCE((SELECT ta.approval_status FROM time_approval ta WHERE ta.reason_id = r.reason_id ORDER BY ta.approved_at DESC LIMIT 1), 'pending') as approval_status,
                (SELECT ra.old_value FROM reason_audit ra WHERE ra.reason_id = r.reason_id AND ra.new_value NOT IN ('approved','rejected','pending') ORDER BY ra.changed_at DESC LIMIT 1) as old_value,
                (SELECT ra.new_value FROM reason_audit ra WHERE ra.reason_id = r.reason_id AND ra.new_value NOT IN ('approved','rejected','pending') ORDER BY ra.changed_at DESC LIMIT 1) as new_value
            FROM time_reason r
            JOIN time_record tr ON r.record_id = tr.record_id
            JOIN employee e ON tr.employee_id = e.employee_id
            WHERE 1";

    if (count($whereConditions) > 0) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }

    // Filter by approval status
    if ($filterStatus !== 'all') {
        $sql .= " AND COALESCE((SELECT ta2.approval_status FROM time_approval ta2 WHERE ta2.reason_id = r.reason_id ORDER BY ta2.approved_at DESC LIMIT 1), 'pending') = ?";
        $params[] = $filterStatus;
    }

    $sql .= " ORDER BY r.submitted_at DESC LIMIT 200";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Get all employees for filter dropdown
    $empStmt = $conn->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) as name FROM employee ORDER BY first_name");
    $employees = $empStmt->fetchAll();

} catch (PDOException $e) {
    $error = $e->getMessage();
}

// Helper to get badge color for reason status
function getReasonStatusBadge($status) {
    $colors = [
        'pending' => '#FBB040',    // orange/yellow
        'approved' => '#10B981',   // green
        'rejected' => '#DC2626'    // red
    ];
    $color = $colors[$status] ?? '#6B7280';
    return "background-color: {$color}; color: white;";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reasons - DTR System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .reason-modal-content { max-height: 400px; overflow-y: auto; }
        .badge-reason { font-size: 0.85rem; padding: 0.4rem 0.6rem; }
        .reason-text { font-size: 0.9rem; max-width: 200px; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>

<?php require_once '../includes/sidebar.php'; ?>
<div class="container-fluid mt-4">
    <div class="main-content">
        <div class="mb-4">
            <h2>Manage Employee Reasons</h2>
        </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="status" class="form-label">Filter by Status:</label>
                        <select id="status" name="status" class="form-select">
                            <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filterStatus === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="start_date" class="form-label">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate !== 'all' ? $startDate : ''); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date" class="form-label">End Date:</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate !== 'all' ? $endDate : ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="employee_id" class="form-label">Filter by Employee:</label>
                        <select id="employee_id" name="employee_id" class="form-select">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" <?php echo $filterEmployee == $emp['employee_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <a href="manage_reasons.php" class="btn btn-outline-secondary w-100">All Dates</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Reasons Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Entry</th>
                        <th>Change</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No reasons found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $rec): ?>
                            <?php
                            $entries = [
                                ['type' => 'am_in', 'label' => 'AM In'],
                                ['type' => 'am_out', 'label' => 'AM Out'],
                                ['type' => 'pm_in', 'label' => 'PM In'],
                                ['type' => 'pm_out', 'label' => 'PM Out']
                            ];
                            ?>
                            <tr>
                                <td><?php echo formatDate($rec['record_date']); ?></td>
                                <td><?php echo htmlspecialchars($rec['first_name'] . ' ' . $rec['last_name']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php
                                        $labelMap = ['am_in'=>'AM In','am_out'=>'AM Out','pm_in'=>'PM In','pm_out'=>'PM Out'];
                                        echo $labelMap[$rec['reason_type']] ?? $rec['reason_type'];
                                    ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($rec['old_value']) || !empty($rec['new_value'])): ?>
                                        <small>
                                            <span class="text-muted text-decoration-line-through"><?php echo htmlspecialchars(formatTime($rec['old_value'] ?? '—')); ?></span>
                                            <span class="mx-1">→</span>
                                            <strong class="text-primary"><?php echo htmlspecialchars(formatTime($rec['new_value'] ?? '—')); ?></strong>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">—</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="reason-text"><?php echo htmlspecialchars(substr($rec['reason_text'], 0, 50)); ?></small>
                                    <?php if (strlen($rec['reason_text']) > 50): ?>
                                        <br><small class="text-muted">...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-reason" style="<?php echo getReasonStatusBadge($rec['approval_status']); ?>">
                                        <?php echo ucfirst($rec['approval_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo !empty($rec['submitted_at']) ? formatDate($rec['submitted_at']) : '—'; ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-success" id="approveBtn-<?php echo $rec['reason_id']; ?>" onclick="approveReason(<?php echo $rec['reason_id']; ?>)">
                                            <span class="btn-text">✓</span>
                                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                        <button class="btn btn-outline-danger" id="rejectBtn-<?php echo $rec['reason_id']; ?>" onclick="rejectReason(<?php echo $rec['reason_id']; ?>)">
                                            <span class="btn-text">✗</span>
                                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="viewReason(<?php echo $rec['reason_id']; ?>, '<?php echo htmlspecialchars(addslashes($rec['reason_text'])); ?>')">View</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Reason Modal -->
<div class="modal fade" id="viewReasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Full Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="reasonText" class="reason-modal-content"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/toast.js"></script>
<script>
const viewReasonModal = new bootstrap.Modal(document.getElementById('viewReasonModal'));

function viewReason(reasonId, reason) {
    document.getElementById('reasonText').textContent = reason;
    viewReasonModal.show();
}

function approveReason(reasonId) {
    if (!confirm('Approve this reason?')) return;
    
    const btn = document.getElementById('approveBtn-' + reasonId);
    const btnText = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner-border');
    
    // Show spinner, disable button
    btnText.classList.add('d-none');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    fetch('../api/approve_reason.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ reason_id: reasonId, approval_status: 'approved' })
    })
    .then(async r => {
        const txt = await r.text();
        try { return JSON.parse(txt); } catch(e) {
            console.error('Approve parse error. Raw response:', txt);
            showToast('Approve failed. Server error.', 'error');
            throw e;
        }
    })
    .then(data => {
        if (data.success) {
            showToast('Reason approved!', 'success', 2000);
            // Show email status
            if (data.email_sent === true) {
                showToast('Email sent to employee ✓', 'success', 3000);
            } else if (data.email_sent === false) {
                showToast('Email failed to send', 'warning', 4000);
            }
            setTimeout(() => location.reload(), 2500);
        } else {
            showToast('Error: ' + data.message, 'error');
            btnText.classList.remove('d-none');
            spinner.classList.add('d-none');
            btn.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        btnText.classList.remove('d-none');
        spinner.classList.add('d-none');
        btn.disabled = false;
    });
}

function rejectReason(reasonId) {
    const notes = prompt('Reason for rejection (optional):');
    if (notes === null) return;
    
    const btn = document.getElementById('rejectBtn-' + reasonId);
    const btnText = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner-border');
    
    btnText.classList.add('d-none');
    spinner.classList.remove('d-none');
    btn.disabled = true;
    
    fetch('../api/approve_reason.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ reason_id: reasonId, approval_status: 'rejected', admin_notes: notes })
    })
    .then(async r => {
        const txt = await r.text();
        try { return JSON.parse(txt); } catch(e) {
            console.error('Reject parse error. Raw response:', txt);
            btnText.classList.remove('d-none');
            spinner.classList.add('d-none');
            btn.disabled = false;
            showToast('Reject failed. Server error.', 'error');
            throw e;
        }
    })
    .then(data => {
        if (data.success) {
            showToast('Reason rejected', 'error', 2000);
            // Show email status for rejection
            if (data.email_sent === true) {
                showToast('Rejection email sent ✓', 'success', 3000);
            } else if (data.email_sent === false) {
                showToast('Email failed to send', 'warning', 4000);
            }
            setTimeout(() => location.reload(), 2500);
        } else {
            btnText.classList.remove('d-none');
            spinner.classList.add('d-none');
            btn.disabled = false;
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(err => {
        btnText.classList.remove('d-none');
        spinner.classList.add('d-none');
        btn.disabled = false;
        console.error(err);
    });
}
</script>
</body>
</html>
