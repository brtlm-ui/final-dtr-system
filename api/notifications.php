<?php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userType = $_SESSION['user_type'];
$userId = null;
if ($userType === 'admin') {
    $userId = $_SESSION['admin_id'] ?? null;
} elseif ($userType === 'employee') {
    $userId = $_SESSION['employee_id'] ?? null;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$isAdmin = ($userType === 'admin') ? 1 : 0;

// Ensure notification_reads table exists (in case migration not yet applied)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS notification_reads (\n      notification_id INT NOT NULL,\n      user_id INT NOT NULL,\n      is_read TINYINT(1) NOT NULL DEFAULT 0,\n      read_at TIMESTAMP NULL DEFAULT NULL,\n      PRIMARY KEY (notification_id, user_id),\n      INDEX idx_nr_user (user_id),\n      CONSTRAINT fk_nr_notification FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // If creation fails, continue; subsequent queries may fail and return graceful error
}

try {
    if ($action === 'count') {
        // Unread count for this user: sum direct unread + broadcast unread tracked in notification_reads
        // Direct (explicit) notifications where user_id = this user and is_read = 0
        $directStmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $directStmt->execute([$userId]);
        $directCnt = (int)$directStmt->fetchColumn();

        // Broadcast notifications (user_id IS NULL) — count only those not marked read by this user
                // Restrict broadcasts to relevant audience by link prefix: admin/% for admins, employee/% for employees, or empty/null (global)
                $broadcastSql = 'SELECT COUNT(*)
                                                 FROM notifications n
                                                 LEFT JOIN notification_reads nr ON n.notification_id = nr.notification_id AND nr.user_id = ?
                                                 WHERE n.user_id IS NULL AND (nr.is_read IS NULL OR nr.is_read = 0)
                                                     AND (
                                                                (? = 1 AND (n.link LIKE "admin/%" OR n.link IS NULL OR n.link = ""))
                                                         OR (? = 0 AND (n.link LIKE "employee/%" OR n.link IS NULL OR n.link = ""))
                                                     )';
                $broadcastStmt = $conn->prepare($broadcastSql);
                $broadcastStmt->execute([$userId, $isAdmin, $isAdmin]);
        $broadcastCnt = (int)$broadcastStmt->fetchColumn();

        echo json_encode(['success' => true, 'count' => $directCnt + $broadcastCnt]);
        exit();
    }

    if ($action === 'list') {
        $limit = intval($_GET['limit'] ?? 10);
        // For direct notifications (user_id = ?), use notifications.is_read.
        // For broadcasts (user_id IS NULL), use notification_reads.is_read for this user (default 0).
        $sql = 'SELECT n.*, CASE WHEN n.user_id IS NULL THEN COALESCE(nr.is_read, 0) ELSE n.is_read END AS is_read
            FROM notifications n
            LEFT JOIN notification_reads nr ON n.notification_id = nr.notification_id AND nr.user_id = ?
            WHERE (
                n.user_id = ?
                 OR (
                  n.user_id IS NULL
                  AND (
                    (? = 1 AND (n.link LIKE "admin/%" OR n.link IS NULL OR n.link = ""))
                 OR (? = 0 AND (n.link LIKE "employee/%" OR n.link IS NULL OR n.link = ""))
                  )
                )
            )
            ORDER BY n.created_at DESC
            LIMIT ?';
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $isAdmin, PDO::PARAM_INT);
        $stmt->bindValue(4, $isAdmin, PDO::PARAM_INT);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Collapse duplicate broadcast submissions (same type + record_id + entry_type) keeping newest
        $seen = [];
        $filtered = [];
        foreach ($rows as $r) {
            if ($r['type'] === 'reason_submitted') {
                // Parse payload minimally for keys
                $payload = $r['payload'];
                $recId = null; $entryType = null;
                if (is_string($payload)) {
                    if (preg_match('/"record_id":(\d+)/', $payload, $m)) $recId = $m[1];
                    if (preg_match('/"entry_type":"([a-z_]+)"/', $payload, $m)) $entryType = $m[1];
                }
                $key = $r['type'] . '|' . ($recId ?? '') . '|' . ($entryType ?? '');
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $filtered[] = $r;
                }
            } else {
                $filtered[] = $r;
            }
        }
        $rows = $filtered;
        echo json_encode(['success' => true, 'rows' => $rows]);
        exit();
    }

    if ($action === 'mark_read') {
        // support single id or array of ids
        $nids = $_POST['notification_id'] ?? $_POST['notification_ids'] ?? null;
        if (!$nids) { echo json_encode(['success' => false, 'message' => 'notification_id(s) required']); exit(); }
        if (!is_array($nids)) $nids = [$nids];

        $ownerStmt = $conn->prepare('SELECT user_id FROM notifications WHERE notification_id = ?');
        $insertNr = $conn->prepare('INSERT INTO notification_reads (notification_id, user_id, is_read, read_at) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE is_read = 1, read_at = NOW()');
        $updateNotify = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ?');

        foreach ($nids as $nid) {
            $ownerStmt->execute([$nid]);
            $row = $ownerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue; // not found
            // Broadcast: user_id IS NULL => track per-user in notification_reads
            if ($row['user_id'] === null) {
                $insertNr->execute([$nid, $userId]);
            } else {
                // Only update if the notification was intended for this user
                if ((int)$row['user_id'] === (int)$userId) {
                    $updateNotify->execute([$nid]);
                }
            }
        }
        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}

?>