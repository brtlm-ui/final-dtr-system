<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isEmployeeLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$recordId = $_POST['record_id'] ?? null;
$entryType = $_POST['entry_type'] ?? null;  // am_in, am_out, pm_in, pm_out
$reason = trim($_POST['reason'] ?? '');

if (!$recordId || !$entryType || $reason === '') {
    echo json_encode(['success' => false, 'message' => 'Record ID, entry type, and reason are required']);
    exit();
}

// Validate entry_type
$validTypes = ['am_in', 'am_out', 'pm_in', 'pm_out'];
if (!in_array($entryType, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid entry type']);
    exit();
}

try {
    // Fetch record and verify ownership
    $stmt = $conn->prepare("SELECT * FROM time_record WHERE record_id = ?");
    $stmt->execute([$recordId]);
    $rec = $stmt->fetch();

    if (!$rec) {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit();
    }

    if ($rec['employee_id'] != $_SESSION['employee_id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }

        // Insert into time_reason (uses normalized reason table)
        $insert = $conn->prepare("INSERT INTO time_reason (record_id, reason_type, reason_text, submitted_at) VALUES (?, ?, ?, NOW())");
        $insert->execute([$recordId, $entryType, $reason]);

        $reasonId = $conn->lastInsertId();

        // Insert an initial audit entry for this reason (old_value NULL -> new_value)
        try {
            $audit = $conn->prepare("INSERT INTO reason_audit (reason_id, old_value, new_value, changed_by, changed_at) VALUES (?, NULL, ?, ?, NOW())");
            $changedBy = getCurrentUserId();
            $audit->execute([$reasonId, $reason, $changedBy]);
        } catch (PDOException $e) {
            // Non-fatal: continue even if audit insert fails
        }

        // Notify admins (broadcast) and optionally email them (dedupe safeguard for same reason_id)
        try {
            $payload = json_encode(['reason_id' => $reasonId, 'record_id' => $recordId, 'entry_type' => $entryType, 'employee_id' => $_SESSION['employee_id']]);
            // Avoid accidental duplicate broadcasts if submission retried quickly
            $dupChk = $conn->prepare("SELECT notification_id FROM notifications WHERE user_id IS NULL AND type = 'reason_submitted' AND payload LIKE ? LIMIT 1");
            $dupPattern = '%"reason_id":' . (int)$reasonId . '%';
            $dupChk->execute([$dupPattern]);
            if (!$dupChk->fetch()) {
                $nstmt = $conn->prepare("INSERT INTO notifications (user_id, type, payload, link) VALUES (NULL, ?, ?, ?)");
                $nstmt->execute(['reason_submitted', $payload, 'admin/manage_reasons.php?status=pending&reason_id=' . (int)$reasonId]);
            }

            // send email to active admins with email addresses
            $astmt = $conn->prepare("SELECT email, username FROM admin WHERE is_active = 1 AND email IS NOT NULL AND email != ''");
            $astmt->execute();
            $admins = $astmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $a) {
                try {
                    require_once '../includes/email_template.php';
                    $to = $a['email'];
                    $subject = 'New Reason Submitted - DTR System';
                    
                    // Get employee name
                    $empStmt = $conn->prepare("SELECT first_name, last_name FROM employee WHERE employee_id = ?");
                    $empStmt->execute([$_SESSION['employee_id']]);
                    $empData = $empStmt->fetch();
                    $employeeName = $empData ? $empData['first_name'] . ' ' . $empData['last_name'] : 'Employee #' . $_SESSION['employee_id'];
                    
                    $content = '<p>Hello <strong>' . htmlspecialchars($a['username']) . '</strong>,</p>' .
                               '<p>A new reason has been submitted and requires your review.</p>' .
                               '<div class="info-box">' .
                               '<p><strong>Employee:</strong> ' . htmlspecialchars($employeeName) . '</p>' .
                               '<p><strong>Reason ID:</strong> ' . (int)$reasonId . '</p>' .
                               '<p><strong>Record ID:</strong> ' . (int)$recordId . '</p>' .
                               '<p><strong>Type:</strong> ' . htmlspecialchars($entryType) . '</p>' .
                               '<p><strong>Reason:</strong> ' . htmlspecialchars($reason) . '</p>' .
                               '</div>' .
                               '<p>Please review and approve or reject this reason at your earliest convenience.</p>';
                    
                    $html = render_email_template([
                        'title' => 'New Reason Submitted - DTR System',
                        'preheader' => 'A reason requires your review',
                        'header' => 'New Reason Submitted',
                        'content' => $content,
                        'button_text' => 'Review Reasons',
                        'button_url' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/admin/manage_reasons.php'
                    ]);
                    // Synchronous send
                    send_mail($to, $subject, $html);
                } catch (Exception $e) { error_log('Reason submission email failed: ' . $e->getMessage()); }
            }
        } catch (PDOException $e) {
            // ignore notification errors
        }

        echo json_encode(['success' => true, 'message' => 'Reason submitted', 'reason_id' => $reasonId]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

