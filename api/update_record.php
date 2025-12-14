
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
// Include mail for admin notification emails when employees submit edit requests
require_once '../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method';
    header('Location: ../employee/edit_record.php');
    exit();
}

// Check if user is logged in (employee or admin)
if (!isEmployeeLoggedIn() && !isAdminLoggedIn()) {
    header('Location: ../employee/login.php');
    exit();
}

$recordId = sanitizeInput($_POST['record_id'] ?? '');
// Accept both old and new field names for compatibility
$amIn = $_POST['am_in'] ?? null;
$amOut = $_POST['am_out'] ?? null;
$pmIn = $_POST['pm_in'] ?? null;
$pmOut = $_POST['pm_out'] ?? null;
$editReason = sanitizeInput($_POST['edit_reason'] ?? '');

error_log("EDIT RECORD SUBMIT: record_id={$recordId}, am_in={$amIn}, am_out={$amOut}, pm_in={$pmIn}, pm_out={$pmOut}, reason={$editReason}");

if (empty($recordId) || empty($editReason)) {
    $_SESSION['error_message'] = 'Record ID and edit reason are required';
    header('Location: ' . (isAdminLoggedIn() ? '../admin/edit_record.php' : '../employee/edit_record.php') . '?id=' . $recordId);
    exit();
}

try {
    // Get the record
    $stmt = $conn->prepare("SELECT * FROM time_record WHERE record_id = ?");
    $stmt->execute([$recordId]);
    $record = $stmt->fetch();

    if (!$record) {
        $_SESSION['error_message'] = 'Record not found';
        header('Location: ' . (isAdminLoggedIn() ? '../admin/edit_record.php' : '../employee/edit_record.php'));
        exit();
    }

    // If employee, verify ownership
    if (isEmployeeLoggedIn() && $record['employee_id'] != $_SESSION['employee_id']) {
        $_SESSION['error_message'] = 'Unauthorized access';
        header('Location: ../employee/edit_record.php');
        exit();
    }

    // Normalize datetime inputs (from datetime-local HTML inputs)
    // Strictly normalize to HH:MM:SS (drop any date parts accidentally passed)
    $normalizeTimeInput = function ($val) {
        if (empty($val)) return null;
        $s = str_replace('T', ' ', trim((string)$val));
        // Prefer exact HH:MM
        if (preg_match('/^([0-2]\d):([0-5]\d)$/', $s, $m)) {
            return $m[1] . ':' . $m[2] . ':00';
        }
        // Extract HH:MM from any larger string
        if (preg_match('/\b([0-2]\d):([0-5]\d)\b/', $s, $m)) {
            return $m[1] . ':' . $m[2] . ':00';
        }
        return null;
    };

$amIn = $normalizeTimeInput($amIn);
$amOut = $normalizeTimeInput($amOut);
$pmIn = $normalizeTimeInput($pmIn);
$pmOut = $normalizeTimeInput($pmOut);

error_log("EDIT RECORD NORMALIZED: am_in={$amIn}, am_out={$amOut}, pm_in={$pmIn}, pm_out={$pmOut}");    // Helper: reduce any time/datetime string to HH:MM for minute-level comparison
    $minuteKey = function ($val) {
        if (!$val) return null;
        // Accept formats: 'HH:MM[:SS]', 'YYYY-MM-DD HH:MM[:SS]', 'YYYY-MM-DDTHH:MM[:SS]'
        $s = str_replace('T', ' ', trim((string)$val));
        if (preg_match('/\b(\d{2}):(\d{2})(?::\d{2})?\b/', $s, $m)) {
            return $m[1] . ':' . $m[2];
        }
        return $s; // fallback to raw, likely won't match and will be treated as change only if actually different
    };

    // EMPLOYEE: Create edit request (TIME_REASON + TIME_APPROVAL), do NOT update TIME_RECORD
    if (isEmployeeLoggedIn()) {
        error_log("EDIT RECORD: Employee logged in, processing as employee");
        $fields = [];
        // Only consider a field if provided AND different from DB at minute precision
        if ($amIn) {
            $old = $minuteKey($record['am_in'] ?? null);
            $new = $minuteKey($amIn);
            error_log("EDIT RECORD: Checking am_in - old={$old}, new={$new}");
            if ($new !== $old) $fields['am_in'] = $amIn;
        }
        if ($amOut) {
            $old = $minuteKey($record['am_out'] ?? null);
            $new = $minuteKey($amOut);
            error_log("EDIT RECORD: Checking am_out - old={$old}, new={$new}");
            if ($new !== $old) $fields['am_out'] = $amOut;
        }
        if ($pmIn) {
            $old = $minuteKey($record['pm_in'] ?? null);
            $new = $minuteKey($pmIn);
            error_log("EDIT RECORD: Checking pm_in - old={$old}, new={$new}");
            if ($new !== $old) $fields['pm_in'] = $pmIn;
        }
        if ($pmOut) {
            $old = $minuteKey($record['pm_out'] ?? null);
            $new = $minuteKey($pmOut);
            error_log("EDIT RECORD: Checking pm_out - old={$old}, new={$new}");
            if ($new !== $old) $fields['pm_out'] = $pmOut;
        }

        error_log("EDIT RECORD: Changed fields detected: " . json_encode(array_keys($fields)));

        if (empty($fields)) {
            $_SESSION['error_message'] = 'No changes detected.';
            header('Location: ../employee/edit_record.php?id=' . $recordId);
            exit();
        }

        foreach ($fields as $field => $newValue) {
            // Insert into TIME_REASON (no employee_id)
            $reasonType = $field;
            $ins = $conn->prepare("INSERT INTO time_reason (record_id, reason_type, reason_text, submitted_at) VALUES (?, ?, ?, NOW())");
            $ins->execute([$recordId, $reasonType, $editReason]);
            // Capture reason_id for audit and notifications
            $reasonId = $conn->lastInsertId();

            // Insert audit entry (old_value -> new_value of the field being requested for change)
            try {
                $changedBy = $_SESSION['employee_id'] ?? null;
                $oldValue = $record[$field];
                error_log("EDIT REQUEST AUDIT: reason_id={$reasonId}, field={$field}, old={$oldValue}, new={$newValue}, by={$changedBy}");
                $audit = $conn->prepare("INSERT INTO reason_audit (reason_id, old_value, new_value, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
                $audit->execute([$reasonId, $oldValue, $newValue, $changedBy]);
                error_log("EDIT REQUEST AUDIT: inserted successfully for reason_id={$reasonId}");
            } catch (PDOException $e) {
                error_log("EDIT REQUEST AUDIT FAILED: " . $e->getMessage());
            }

            // Broadcast notification to admins about submitted edit request
            try {
                $payload = json_encode([
                    'reason_id' => $reasonId,
                    'record_id' => $recordId,
                    'entry_type' => $field,
                    'employee_id' => $record['employee_id'],
                    'old_value' => $record[$field],
                    'new_value' => $newValue
                ]);
                $nstmt = $conn->prepare("INSERT INTO notifications (user_id, type, payload, link) VALUES (NULL, ?, ?, ?)");
                $nstmt->execute(['reason_submitted', $payload, 'admin/manage_reasons.php?status=pending&reason_id=' . (int)$reasonId]);

                // Optional: email active admins
                try {
                    require_once '../includes/email_template.php';
                    $astmt = $conn->prepare("SELECT email, username FROM admin WHERE is_active = 1 AND email IS NOT NULL AND email != ''");
                    $astmt->execute();
                    $admins = $astmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get employee name
                    $empStmt = $conn->prepare("SELECT first_name, last_name FROM employee WHERE employee_id = ?");
                    $empStmt->execute([$record['employee_id']]);
                    $empData = $empStmt->fetch();
                    $employeeName = $empData ? $empData['first_name'] . ' ' . $empData['last_name'] : 'Employee #' . $record['employee_id'];
                    
                    foreach ($admins as $a) {
                        if (!empty($a['email'])) {
                            $to = $a['email'];
                            $subject = 'Record Edit Request - DTR System';
                            
                            $fieldLabel = str_replace('_', ' ', ucwords($field));
                            $content = '<p>Hello <strong>' . htmlspecialchars($a['username']) . '</strong>,</p>' .
                                       '<p>An employee has submitted an edit request that requires your review.</p>' .
                                       '<div class="info-box">' .
                                       '<p><strong>Employee:</strong> ' . htmlspecialchars($employeeName) . '</p>' .
                                       '<p><strong>Record ID:</strong> ' . (int)$recordId . '</p>' .
                                       '<p><strong>Field:</strong> ' . htmlspecialchars($fieldLabel) . '</p>' .
                                       '<p><strong>Current Value:</strong> <code>' . htmlspecialchars((string)$oldValue) . '</code></p>' .
                                       '<p><strong>Requested Value:</strong> <code>' . htmlspecialchars((string)$newValue) . '</code></p>' .
                                       '<p><strong>Reason:</strong> ' . htmlspecialchars($editReason) . '</p>' .
                                       '</div>' .
                                       '<p>Please review and approve or reject this edit request.</p>';
                            
                            $html = render_email_template([
                                'title' => 'Record Edit Request - DTR System',
                                'preheader' => 'An edit request requires your review',
                                'header' => 'Record Edit Request',
                                'content' => $content,
                                'button_text' => 'Review Request',
                                'button_url' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/admin/manage_reasons.php'
                            ]);
                            send_mail($to, $subject, $html);
                        }
                    }
                } catch (Exception $e) { error_log('Edit request email failed: ' . $e->getMessage()); }
            } catch (PDOException $e) {
                // Non-fatal: ignore notification failure
            }
            // No TIME_APPROVAL insert yet; approval handled by admin
        }
        $_SESSION['success_message'] = 'Edit request submitted for approval.';
        header('Location: ../employee/edit_record.php?id=' . $recordId);
        exit();
    }

    // ADMIN: Direct edit
    if (isAdminLoggedIn()) {
        $fields = [];
        if ($amIn && $amIn !== $record['am_in']) $fields['am_in'] = $amIn;
        if ($amOut && $amOut !== $record['am_out']) $fields['am_out'] = $amOut;
        if ($pmIn && $pmIn !== $record['pm_in']) $fields['pm_in'] = $pmIn;
        if ($pmOut && $pmOut !== $record['pm_out']) $fields['pm_out'] = $pmOut;

        if (empty($fields)) {
            $_SESSION['error_message'] = 'No changes detected.';
            header('Location: ../admin/edit_record.php?id=' . $recordId);
            exit();
        }

        // Update TIME_RECORD
        $updateSql = "UPDATE time_record SET ";
        $params = [];
        foreach ($fields as $field => $newValue) {
            $updateSql .= "$field = ?, ";
            $params[] = $newValue;
        }
        $updateSql = rtrim($updateSql, ', ') . " WHERE record_id = ?";
        $params[] = $recordId;
        $stmt = $conn->prepare($updateSql);
        $stmt->execute($params);

        // For each changed field, log to REASON_AUDIT only (no TIME_REASON, no TIME_APPROVAL)
        foreach ($fields as $field => $newValue) {
            $audit = $conn->prepare("INSERT INTO reason_audit (reason_id, old_value, new_value, changed_by, changed_at) VALUES (NULL, ?, ?, ?, NOW())");
            $adminId = $_SESSION['admin_id'] ?? null;
            $oldValue = $record[$field];
            $audit->execute([$oldValue, $newValue, $adminId]);
        }

        $_SESSION['success_message'] = 'Record updated and audit logged.';
        header('Location: ../admin/edit_record.php?id=' . $recordId);
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Database error: ' . $e->getMessage();
    header('Location: ' . (isAdminLoggedIn() ? '../admin/edit_record.php' : '../employee/edit_record.php') . '?id=' . $recordId);
}
?>