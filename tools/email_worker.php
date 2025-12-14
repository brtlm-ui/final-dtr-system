<?php
// CLI worker to process email_queue and send emails.
// Run via: php tools/email_worker.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mail.php';

$limit = intval($argv[1] ?? 20);
$maxAttempts = 5;

echo "Email worker starting - processing up to {$limit} pending emails\n";

try {
    $sel = $conn->prepare("SELECT * FROM email_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT ?");
    $sel->bindValue(1, $limit, PDO::PARAM_INT);
    $sel->execute();
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $id = $r['email_id'];
        $to = $r['recipient'];
        $subject = $r['subject'];
        $body = $r['body'];

        try {
            $sent = send_mail($to, $subject, $body);
            if ($sent) {
                $up = $conn->prepare("UPDATE email_queue SET status='sent', sent_at = NOW(), attempts = attempts + 1 WHERE email_id = ?");
                $up->execute([$id]);
                echo "Sent email {$id} -> {$to}\n";
            } else {
                $up = $conn->prepare("UPDATE email_queue SET attempts = attempts + 1, last_error = ?, status = CASE WHEN attempts+1 >= ? THEN 'failed' ELSE 'pending' END WHERE email_id = ?");
                $up->execute(['send_failed', $maxAttempts, $id]);
                echo "Failed to send email {$id} -> {$to}\n";
            }
        } catch (Exception $e) {
            $up = $conn->prepare("UPDATE email_queue SET attempts = attempts + 1, last_error = ?, status = CASE WHEN attempts+1 >= ? THEN 'failed' ELSE 'pending' END WHERE email_id = ?");
            $up->execute([$e->getMessage(), $maxAttempts, $id]);
            echo "Error sending email {$id}: {$e->getMessage()}\n";
        }
    }

    echo "Worker run complete.\n";
} catch (PDOException $e) {
    echo "DB error: " . $e->getMessage() . "\n";
}

?>