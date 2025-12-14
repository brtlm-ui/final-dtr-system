<?php
require_once __DIR__ . '/../config/mail.php';
// Ensure PHPMailer classes are autoloaded when available (Composer install).
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

// send_mail: simple wrapper that prefers PHPMailer if installed, otherwise falls back to PHP mail()
function send_mail($to, $subject, $htmlBody, $options = []) {
    $config = require __DIR__ . '/../config/mail.php';
    $fromEmail = $config['from_email'] ?? 'no-reply@example.com';
    $fromName = $config['from_name'] ?? 'DTR System';

    // Plain text fallback
    $textBody = $options['text'] ?? strip_tags($htmlBody);

    // If PHPMailer is available, use it
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        // Allow SMTP debug override via environment variable SMTP_DEBUG (0-4)
        $mail->SMTPDebug = getenv('SMTP_DEBUG') !== false ? (int)getenv('SMTP_DEBUG') : 0; // 0=off
        $mail->Debugoutput = 'error_log';
        try {
            if (!empty($config['use_smtp'])) {
                $smtp = $config['smtp'];
                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['username'];
                $mail->Password = $smtp['password'];
                $mail->SMTPSecure = $smtp['encryption'] ?? 'tls';
                $mail->Port = $smtp['port'] ?? 587;
                if (!empty($smtp['timeout'])) {
                    $mail->Timeout = (int)$smtp['timeout'];
                }
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;

            $mail->send();
            return true;
        } catch (Exception $e) {
            $err = 'PHPMailer error: ' . $e->getMessage();
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, $err . "\n" . ($mail->ErrorInfo ? 'ErrorInfo: ' . $mail->ErrorInfo . "\n" : ''));
            } else {
                error_log($err);
            }
            // Do NOT fall through silently; continue to fallback mail()
        }
    } else {
        // Informative STDERR message in CLI if PHPMailer missing; helps diagnose why fallback used.
        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, "PHPMailer class not found; using native mail() fallback.\n");
        }
    }

    // Fallback to native mail()
    $boundary = md5(uniqid(time()));
    $headers = "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $textBody . "\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlBody . "\r\n";
    $message .= "--{$boundary}--";

    // Suppress warnings from mail() (e.g., missing local SMTP) to avoid breaking JSON API responses
    return @mail($to, $subject, $message, $headers);
}

// No enqueue function: emails are sent synchronously via send_mail() per project requirement.
