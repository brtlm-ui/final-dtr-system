<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/mail.php';
require_once '../includes/email_template.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Only admins can approve reasons
if (!isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Admin access required']);
    exit();
}

$reasonId = $_POST['reason_id'] ?? null;
// recordId & entryType will be resolved from the reason row (avoid stale/missing POST values)
$recordId = null;
$entryType = null;  // am_in, am_out, pm_in, pm_out
$approvalStatus = $_POST['approval_status'] ?? null;  // approved, rejected, pending
$adminNotes = trim($_POST['admin_notes'] ?? '');

if (!$approvalStatus) {
    echo json_encode(['success' => false, 'message' => 'Approval status is required']);
    exit();
}

// Validate approval_status
$validStatuses = ['pending', 'approved', 'rejected'];
if (!in_array($approvalStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid approval status']);
    exit();
}

try {
    // Determine reason_id: prefer explicit reason_id, else find latest reason for record+entry type
    if (!$reasonId) {
        echo json_encode(['success' => false, 'message' => 'reason_id is required']);
        exit();
    }
    // Resolve record & reason type for richer notification payload
    $rinfo = $conn->prepare("SELECT record_id, reason_type FROM time_reason WHERE reason_id = ? LIMIT 1");
    $rinfo->execute([$reasonId]);
    $rrow = $rinfo->fetch(PDO::FETCH_ASSOC);
    if (!$rrow) {
        echo json_encode(['success' => false, 'message' => 'Reason not found']);
        exit();
    }
    $recordId = $rrow['record_id'];
    $entryType = $rrow['reason_type'];

    // Insert approval record
    $ins = $conn->prepare("INSERT INTO time_approval (reason_id, approved_by, approval_status, approval_notes, approved_at) VALUES (?, ?, ?, ?, NOW())");
    $approver = getCurrentUserId();
    $ins->execute([$reasonId, $approver, $approvalStatus, $adminNotes]);

    // If approved, apply the change to time_record (only for valid time columns)
    if ($approvalStatus === 'approved') {
        $validFields = ['am_in','am_out','pm_in','pm_out'];
        if (in_array($entryType, $validFields, true)) {
            try {
                // Fetch the latest audit entry that represents the requested time change
                $auditStmt = $conn->prepare("SELECT new_value FROM reason_audit WHERE reason_id = ? AND new_value NOT IN ('approved','rejected','pending') ORDER BY changed_at DESC LIMIT 1");
                $auditStmt->execute([$reasonId]);
                $auditRow = $auditStmt->fetch(PDO::FETCH_ASSOC);
                if ($auditRow && !empty($auditRow['new_value'])) {
                    $raw = $auditRow['new_value'];
                    // Normalize separators
                    $raw = str_replace('T', ' ', $raw);
                    // Extract HH:MM[:SS] portion only (record columns are TIME type)
                    $timeVal = null;
                    if (preg_match('/\b(\d{2}:\d{2}:\d{2})\b/', $raw, $m)) {
                        $timeVal = $m[1];
                    } elseif (preg_match('/\b(\d{2}:\d{2})\b/', $raw, $m)) {
                        $timeVal = $m[1] . ':00';
                    }
                    if ($timeVal) {
                        // Final sanity: ensure valid range
                        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $timeVal)) {
                            // Debug: capture old value before update
                            $oldValStmt = $conn->prepare("SELECT {$entryType} FROM time_record WHERE record_id = ? LIMIT 1");
                            $oldValStmt->execute([$recordId]);
                            $oldTimeVal = $oldValStmt->fetchColumn();
                            $updateSql = "UPDATE time_record SET {$entryType} = ? WHERE record_id = ?";
                            $updateStmt = $conn->prepare($updateSql);
                            $updateStmt->execute([$timeVal, $recordId]);
                            $rows = $updateStmt->rowCount();
                            $newValStmt = $conn->prepare("SELECT {$entryType} FROM time_record WHERE record_id = ? LIMIT 1");
                            $newValStmt->execute([$recordId]);
                            $appliedVal = $newValStmt->fetchColumn();
                            if ($rows === 0) {
                                error_log("Time update affected 0 rows (reason_id={$reasonId}, record_id={$recordId}, field={$entryType}, requested={$timeVal})");
                            } else {
                                error_log("Time update SUCCESS (reason_id={$reasonId}, record_id={$recordId}, field={$entryType}, old={$oldTimeVal}, new={$appliedVal})");
                            }
                            // Expose applied value for API response
                            $GLOBALS['__applied_time_value'] = $appliedVal;
                        } else {
                            error_log("Extracted time '{$timeVal}' failed validation for reason_id={$reasonId}");
                        }
                    } else {
                        error_log("Could not extract time portion from raw audit value '{$raw}' for reason_id={$reasonId}");
                    }
                } else {
                    error_log("Approval could not find time change audit entry for reason_id={$reasonId}");
                }
            } catch (PDOException $e) {
                error_log('Failed to apply approved time change: ' . $e->getMessage());
            }
        } else {
            error_log("Skipped updating time_record: invalid field '{$entryType}' for reason_id={$reasonId}");
        }
    }

    // Insert audit entry for the approval change (capture previous approval status if any)
    try {
        $prevStmt = $conn->prepare("SELECT approval_status FROM time_approval WHERE reason_id = ? ORDER BY approved_at DESC LIMIT 1 OFFSET 1");
        $prevStmt->execute([$reasonId]);
        $prev = $prevStmt->fetchColumn();
        $auditIns = $conn->prepare("INSERT INTO reason_audit (reason_id, old_value, new_value, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
        $auditIns->execute([$reasonId, $prev ?: NULL, $approvalStatus, $approver]);
    } catch (PDOException $e) {
        // Non-fatal
    }

    // Notify the employee who submitted the reason (include record & entry type for disambiguation)
    try {
        $stmt = $conn->prepare("SELECT tr.employee_id, e.email, e.first_name, e.last_name FROM time_reason trn JOIN time_record tr ON trn.record_id = tr.record_id JOIN employee e ON tr.employee_id = e.employee_id WHERE trn.reason_id = ? LIMIT 1");
        $stmt->execute([$reasonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $employeeId = $row['employee_id'];
            $email = $row['email'];
            $name = $row['first_name'] . ' ' . $row['last_name'];

            $payloadArr = [
                'reason_id' => $reasonId,
                'status' => $approvalStatus,
                'record_id' => $recordId,
                'entry_type' => $entryType
            ];
            $payload = json_encode($payloadArr);

            // Prevent duplicate notification for same reason_id & status (e.g., double-click)
            $check = $conn->prepare("SELECT notification_id FROM notifications WHERE user_id = ? AND type = ? AND payload LIKE ? LIMIT 1");
            $typeVal = ($approvalStatus === 'approved' ? 'reason_approved' : 'reason_rejected');
            $likePattern = '%"reason_id":' . (int)$reasonId . '%"status":"' . $approvalStatus . '"%';
            $check->execute([$employeeId, $typeVal, $likePattern]);
            if (!$check->fetch()) {
                $nstmt = $conn->prepare("INSERT INTO notifications (user_id, type, payload, link) VALUES (?, ?, ?, ?)");
                // Link to employee notifications page for clearer context
                $nstmt->execute([$employeeId, $typeVal, $payload, 'employee/notifications.php']);
            }

            if (!empty($email)) {
                $emailSentFlag = false; $emailError = null; // instrumentation
                try {
                    error_log("APPROVAL EMAIL START: reason_id={$reasonId}, email={$email}");
                    // Get reason details for email
                    $rstmt = $conn->prepare("SELECT tr.record_id, tr.reason_type, tr.reason_text, tr.submitted_at,
                        DATE(t.record_date) as record_date
                        FROM time_reason tr JOIN time_record t ON tr.record_id = t.record_id WHERE tr.reason_id = ?");
                    $rstmt->execute([$reasonId]);
                    $reasonDetails = $rstmt->fetch();
                    error_log("APPROVAL EMAIL: reason details fetched: " . json_encode($reasonDetails));
                    
                    $employeeData = ['first_name' => $row['first_name']];
                    $reasonData = [
                        'status' => $approvalStatus,
                        'reason_type' => ucwords(str_replace('_', ' ', $reasonDetails['reason_type'] ?? 'Time Edit')),
                        'date' => $reasonDetails['record_date'] ?? date('Y-m-d'),
                        'reason_text' => $reasonDetails['reason_text'] ?? '',
                        'admin_notes' => $adminNotes ?? ''
                    ];
                    $subject = 'Reason ' . ucfirst($approvalStatus) . ' - DTR System';
                    error_log("APPROVAL EMAIL: calling email_reason_approved with data: " . json_encode(['employee' => $employeeData, 'reason' => $reasonData]));
                    $html = email_reason_approved($employeeData, $reasonData);
                    error_log("APPROVAL EMAIL: HTML template generated, length=" . strlen($html));
                    // Synchronous send to employee
                    error_log("APPROVAL EMAIL: calling send_mail to {$email}");
                    $emailSent = send_mail($email, $subject, $html);
                    error_log("APPROVAL EMAIL: send_mail returned " . ($emailSent ? 'TRUE' : 'FALSE'));
                    if ($emailSent) {
                        $emailSentFlag = true;
                        error_log("Approval email SENT to {$email} reason_id={$reasonId}");
                    } else {
                        $emailError = 'send_mail returned false';
                        error_log("Approval email FAILED to {$email} reason_id={$reasonId}");
                    }

                    // Send brief confirmation email to approving admin (optional)
                    try {
                        if (isset($_SESSION['admin_id'])) {
                            $adminStmt = $conn->prepare("SELECT email, username FROM admin WHERE admin_id = ? AND email IS NOT NULL AND email != '' LIMIT 1");
                            $adminStmt->execute([$_SESSION['admin_id']]);
                            $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
                            if ($adminRow) {
                                $adminContent = '<p>Hello <strong>' . htmlspecialchars($adminRow['username']) . '</strong>,</p>' .
                                                '<p>You have ' . htmlspecialchars($approvalStatus) . ' a reason request.</p>' .
                                                '<div class="info-box">' .
                                                '<p><strong>Employee:</strong> ' . htmlspecialchars($name) . '</p>' .
                                                '<p><strong>Reason Type:</strong> ' . htmlspecialchars($reasonData['reason_type']) . '</p>' .
                                                '<p><strong>Status:</strong> ' . htmlspecialchars($reasonData['status']) . '</p>' .
                                                '</div>';
                                $adminHtml = render_email_template([
                                    'title' => 'Reason Processed - DTR System',
                                    'preheader' => 'You processed a reason request',
                                    'header' => 'Reason Processed',
                                    'content' => $adminContent,
                                    'button_text' => 'View Reasons',
                                    'button_url' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/admin/notifications.php'
                                ]);
                                send_mail($adminRow['email'], 'Reason ' . $approvalStatus . ' confirmation', $adminHtml);
                            }
                        }
                    } catch (Exception $e) { /* ignore admin confirmation email errors */ }
                } catch (Exception $e) {
                    $emailError = 'Exception: ' . $e->getMessage();
                    error_log('Reason approval email exception: ' . $e->getMessage());
                }
            } else {
                error_log("Approval email SKIPPED (empty employee email) reason_id={$reasonId}");
            }
        }
    } catch (PDOException $e) {
        // ignore
    }

    echo json_encode([
        'success' => true,
        'message' => "Reason marked as {$approvalStatus}",
        'status' => $approvalStatus,
        'applied_time' => isset($GLOBALS['__applied_time_value']) ? $GLOBALS['__applied_time_value'] : null,
        'email_sent' => isset($emailSentFlag) ? $emailSentFlag : false,
        'email_error' => isset($emailError) ? $emailError : null
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
