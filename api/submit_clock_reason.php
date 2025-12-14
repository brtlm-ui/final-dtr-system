<?php
session_start();
require_once '../config/timezone.php';
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';
require_once '../includes/email_template.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Check if employee is logged in (for clock out) or in confirmation session (for initial clock in)
$employeeId = null;
if (isset($_SESSION['employee_id'])) {
    $employeeId = $_SESSION['employee_id'];
} elseif (isset($_SESSION['confirm_employee_id'])) {
    $employeeId = $_SESSION['confirm_employee_id'];
}

if (!$employeeId) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$recordId = $input['record_id'] ?? null;
$reasonType = $input['reason_type'] ?? null;
$reasonText = trim($input['reason_text'] ?? '');
$clockDatetime = $input['clock_datetime'] ?? null;

if (empty($recordId) || empty($reasonType) || empty($reasonText)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Insert reason into time_reason table
    $stmt = $conn->prepare("
        INSERT INTO time_reason (record_id, reason_type, reason_text, submitted_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$recordId, $reasonType, $reasonText]);
    
    $reasonId = $conn->lastInsertId();

    // Auto-create time_approval entry with 'pending' status
        // Auto-create time_approval entry with 'pending' status
        // Table uses approved_at as the timestamp column (see approve_reason.php)
        $stmt = $conn->prepare("
            INSERT INTO time_approval (reason_id, approval_status, approved_at)
            VALUES (?, 'pending', NOW())
        ");
    $stmt->execute([$reasonId]);

    // Broadcast notification to all admins
    $type = 'reason_submitted';
    $payload = json_encode([
        'record_id' => (int)$recordId,
        'employee_id' => (int)$employeeId,
        'reason_type' => $reasonType,
        'reason_text' => $reasonText,
        'reason_id' => (int)$reasonId
    ]);
    $link = 'admin/manage_reasons.php?reason_id=' . (int)$reasonId;
    $nstmt = $conn->prepare("INSERT INTO notifications (user_id, type, payload, link) VALUES (NULL, ?, ?, ?)");
    $nstmt->execute([$type, $payload, $link]);

    // Send email notifications to all active admins
    try {
        $astmt = $conn->prepare("SELECT email, username FROM admin WHERE is_active = 1 AND email IS NOT NULL AND email != ''");
        $astmt->execute();
        $admins = $astmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get employee name and record details
        $empStmt = $conn->prepare("SELECT first_name, last_name FROM employee WHERE employee_id = ?");
        $empStmt->execute([$employeeId]);
        $empData = $empStmt->fetch();
        $employeeName = $empData ? $empData['first_name'] . ' ' . $empData['last_name'] : 'Employee #' . $employeeId;
        
        // Get record date
        $recStmt = $conn->prepare("SELECT record_date FROM time_record WHERE record_id = ?");
        $recStmt->execute([$recordId]);
        $recData = $recStmt->fetch();
        $recordDate = $recData ? date('F d, Y', strtotime($recData['record_date'])) : 'Unknown Date';
        
        // Format reason type for display
        $reasonTypeLabel = str_replace('_', ' ', ucwords($reasonType));
        
        foreach ($admins as $a) {
            if (!empty($a['email'])) {
                $to = $a['email'];
                $subject = 'Clock-In/Out Reason Submitted - DTR System';
                
                $content = '<p>Hello <strong>' . htmlspecialchars($a['username']) . '</strong>,</p>' .
                           '<p>An employee has submitted a reason for late/early/overtime clock entry that requires your review.</p>' .
                           '<div class="info-box">' .
                           '<p><strong>Employee:</strong> ' . htmlspecialchars($employeeName) . '</p>' .
                           '<p><strong>Record Date:</strong> ' . htmlspecialchars($recordDate) . '</p>' .
                           '<p><strong>Entry Type:</strong> ' . htmlspecialchars($reasonTypeLabel) . '</p>' .
                           '<p><strong>Reason:</strong> ' . htmlspecialchars($reasonText) . '</p>' .
                           '</div>' .
                           '<p>Please review and approve or reject this reason.</p>';
                
                $html = render_email_template([
                    'title' => 'Clock Entry Reason Submitted - DTR System',
                    'preheader' => 'A reason submission requires your review',
                    'header' => 'Clock Entry Reason Submitted',
                    'content' => $content,
                    'button_text' => 'Review Reason',
                    'button_url' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/admin/manage_reasons.php?reason_id=' . (int)$reasonId
                ]);
                send_mail($to, $subject, $html);
            }
        }
    } catch (Exception $e) {
        error_log('Failed to send email notification: ' . $e->getMessage());
        // Continue anyway - notification is in database
    }

    // If this was initial clock in, promote session properly
    if (isset($_SESSION['confirm_employee_id'])) {
        $_SESSION['user_type'] = 'employee';
        $_SESSION['employee_id'] = $_SESSION['confirm_employee_id'];
        unset($_SESSION['confirm_employee_id']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reason submitted successfully'
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
