<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';

requireAdminLogin();

// Handle bulk mark-read via POST
// Bulk mark selected read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $ids = $_POST['notification_ids'] ?? [];
    if (!is_array($ids)) $ids = [$ids];
    try {
        $adminId = $_SESSION['admin_id'];
        $ownerStmt = $conn->prepare('SELECT user_id FROM notifications WHERE notification_id = ?');
        $insertNr = $conn->prepare('INSERT INTO notification_reads (notification_id, user_id, is_read, read_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()');
        $updateNotify = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ?');
        foreach ($ids as $id) {
            $ownerStmt->execute([$id]);
            $row = $ownerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            if ($row['user_id'] === null) {
                // broadcast => track per-admin in notification_reads
                $insertNr->execute([$id, $adminId]);
            } else {
                if ((int)$row['user_id'] === (int)$adminId) {
                    $updateNotify->execute([$id]);
                }
            }
        }
        $_SESSION['success_message'] = 'Notifications marked as read';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'DB error: ' . $e->getMessage();
    }
    header('Location: notifications.php');
    exit();
}

// Mark ALL notifications for this admin as read (selected + unselected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    try {
        $adminId = $_SESSION['admin_id'];
        // Fetch all notification ids visible to this admin (direct + broadcasts)
        $allStmt = $conn->prepare('SELECT notification_id, user_id FROM notifications WHERE user_id = ? OR user_id IS NULL');
        $allStmt->execute([$adminId]);
        $rowsAll = $allStmt->fetchAll(PDO::FETCH_ASSOC);
        $insertNr = $conn->prepare('INSERT INTO notification_reads (notification_id, user_id, is_read, read_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()');
        $updateNotify = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ?');
        foreach ($rowsAll as $row) {
            if ($row['user_id'] === null) {
                $insertNr->execute([$row['notification_id'], $adminId]);
            } else if ((int)$row['user_id'] === (int)$adminId) {
                $updateNotify->execute([$row['notification_id']]);
            }
        }
        $_SESSION['success_message'] = 'All notifications marked as read';
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'DB error: ' . $e->getMessage();
    }
    header('Location: notifications.php');
    exit();
}

// Fetch notifications relevant for admin
$adminId = $_SESSION['admin_id'];
// Fetch notifications for this admin; compute per-user is_read for broadcasts
$stmt = $conn->prepare('SELECT n.*, CASE WHEN n.user_id IS NULL THEN COALESCE(nr.is_read, 0) ELSE n.is_read END AS is_read FROM notifications n LEFT JOIN notification_reads nr ON n.notification_id = nr.notification_id AND nr.user_id = ? WHERE (n.user_id = ? OR n.user_id IS NULL) ORDER BY n.created_at DESC');
$stmt->execute([$adminId, $adminId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
<?php require_once '../includes/header.php'; ?>

<?php require_once '../includes/sidebar.php'; ?>
<div class="container-fluid mt-4">
    <div class="main-content" style="padding: 0 24px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Notifications</h3>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <?php
    // Helper to produce friendly text & optional metadata
    function formatNotificationAdmin($row, $conn) {
        $type = $row['type'];
        $raw = $row['payload'];
        $payload = $raw;
        if (is_string($raw)) {
            try { $payload = json_decode($raw, true); } catch (Exception $e) { $payload = $raw; }
        }
        // If payload did not decode to array keep a simple fallback
        if (!is_array($payload)) {
            return [$type, $raw];
        }
        $friendly = '';
        switch ($type) {
            case 'account_created':
                $name = $payload['name'] ?? ('Employee #' . ($payload['employee_id'] ?? ''));
                $friendly = "New account created: $name";
                break;
            case 'reason_submitted':
                $rec = $payload['record_id'] ?? '';
                $entry = $payload['entry_type'] ?? '';
                $old = $payload['old_value'] ?? null;
                $new = $payload['new_value'] ?? null;
                $entryLabel = strtoupper(str_replace('_',' ',$entry));
                if ($old !== null && $new !== null) {
                    $friendly = "Edit request for record #$rec ($entryLabel) from $old to $new – awaiting review";
                } else {
                    $friendly = "Reason submitted for record #$rec ($entryLabel) – awaiting review";
                }
                break;
            case 'reason_approved':
                $rec = $payload['record_id'] ?? '';
                $entry = $payload['entry_type'] ?? '';
                $entryLabel = strtoupper(str_replace('_',' ',$entry));
                $friendly = "Approved: employee request for record #$rec ($entryLabel)";
                break;
            case 'reason_rejected':
                $rec = $payload['record_id'] ?? '';
                $entry = $payload['entry_type'] ?? '';
                $entryLabel = strtoupper(str_replace('_',' ',$entry));
                $friendly = "Rejected: employee request for record #$rec ($entryLabel)";
                break;
            case 'day_complete':
                $date = $payload['date'] ?? '';
                $friendly = "Daily completion summary for $date";
                break;
            default:
                $friendly = ucfirst(str_replace('_',' ', $type));
        }
        return [$friendly, json_encode($payload)];
    }
    ?>
    <form method="POST" id="notifForm">
        <input type="hidden" name="action" value="mark_read" id="notifFormAction">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" id="selectAllBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-check-square"></i> Select all
                    </button>
                    <button type="button" id="clearSelectionBtn" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-square"></i> Clear
                    </button>
                    <div class="vr d-none d-md-block"></div>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-check2"></i> Mark selected as read
                    </button>
                    <button type="button" id="markAllBtn" class="btn btn-sm btn-danger ms-auto">
                        <i class="bi bi-check2-all"></i> Mark all as read
                    </button>
                </div>
            </div>
        </div>
        <div class="mb-3">
            <?php if (empty($rows)): ?>
                <div class="alert alert-info">No notifications to display.</div>
            <?php else: ?>
            <?php foreach ($rows as $r): list($friendly,$details)=formatNotificationAdmin($r,$conn); ?>
                <div class="card shadow-sm mb-3<?php echo $r['is_read'] ? '' : ' border-warning border-2'; ?>">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-start gap-3">
                            <input type="checkbox" name="notification_ids[]" value="<?php echo $r['notification_id']; ?>" class="form-check-input mt-1 ms-0" style="flex-shrink:0;">
                            <div class="flex-grow-1">
                            <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-start">
                                <span class="fw-semibold text-dark">
                                    <?php echo htmlspecialchars($friendly); ?>
                                    <?php if ($r['user_id']===null): ?><small class="text-muted">(broadcast)</small><?php endif; ?>
                                </span>
                                <?php if (!$r['is_read']): ?><span class="badge bg-primary">New</span><?php endif; ?>
                            </div>
                            <div class="small text-muted mt-1">Created: <?php echo htmlspecialchars($r['created_at']); ?></div>
                            <details class="mt-2 small">
                                <summary class="text-secondary" style="cursor:pointer;">Payload details</summary>
                                <pre class="bg-light p-2 mb-0 border rounded mt-2" style="white-space:pre-wrap; word-break:break-word; max-height:160px; overflow:auto;"><?php echo htmlspecialchars($details); ?></pre>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </form>
    <script>
    const notifForm = document.getElementById('notifForm');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const markAllBtn = document.getElementById('markAllBtn');
    const formAction = document.getElementById('notifFormAction');

    selectAllBtn?.addEventListener('click', () => {
        notifForm?.querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = true; });
    });

    clearSelectionBtn?.addEventListener('click', () => {
        notifForm?.querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = false; });
    });

    markAllBtn?.addEventListener('click', () => {
        if (!notifForm) return;
        if (confirm('Mark ALL notifications as read?')) {
            if (formAction) {
                formAction.value = 'mark_all_read';
                notifForm.submit();
                formAction.value = 'mark_read';
            } else {
                notifForm.submit();
            }
        }
    });
    </script>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>