<?php
/**
 * Professional Email Template for DTR System
 * 
 * Usage: 
 * $html = render_email_template([
 *     'title' => 'Welcome to DTR System',
 *     'preheader' => 'Your account has been created',
 *     'header' => 'Welcome Aboard!',
 *     'content' => '<p>Your content here...</p>',
 *     'button_text' => 'Login Now',
 *     'button_url' => 'https://yoursite.com/login',
 *     'footer_text' => 'Additional footer info'
 * ]);
 */

function render_email_template($data = []) {
    $title = $data['title'] ?? 'DTR System Notification';
    $preheader = $data['preheader'] ?? '';
    $header = $data['header'] ?? 'DTR System';
    $content = $data['content'] ?? '';
    $button_text = $data['button_text'] ?? '';
    $button_url = $data['button_url'] ?? '';
    $footer_text = $data['footer_text'] ?? '';
    $year = date('Y');
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <title>{$title}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        
        /* Base Styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
        }
        
        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            margin: 0;
            padding: 0;
            font-weight: 700;
            line-height: 1.3;
            color: #111827;
        }
        
        p {
            margin: 0 0 16px;
            line-height: 1.6;
            color: #4b5563;
        }
        
        a {
            color: #2563eb;
            text-decoration: none;
        }
        
        /* Container */
        .email-wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 32px 0;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 40px 32px;
            text-align: center;
        }
        
        .email-logo {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        
        .email-tagline {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
        }
        
        /* Content */
        .email-content {
            padding: 40px 32px;
        }
        
        .content-heading {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 16px;
        }
        
        .content-text {
            font-size: 16px;
            line-height: 1.6;
            color: #4b5563;
            margin-bottom: 16px;
        }
        
        /* Info Box */
        .info-box {
            background-color: #f9fafb;
            border-left: 4px solid #2563eb;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 8px;
        }
        
        .info-box p {
            margin: 8px 0;
            font-size: 14px;
        }
        
        .info-box strong {
            color: #111827;
            font-weight: 600;
        }
        
        /* Button */
        .button-container {
            text-align: center;
            margin: 32px 0;
        }
        
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            padding: 14px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: #1e40af;
        }
        
        /* Divider */
        .divider {
            border: 0;
            border-top: 1px solid #e5e7eb;
            margin: 32px 0;
        }
        
        /* Footer */
        .email-footer {
            background-color: #f9fafb;
            padding: 32px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .footer-text {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
            margin: 8px 0;
        }
        
        .footer-links {
            margin: 16px 0;
        }
        
        .footer-links a {
            color: #2563eb;
            text-decoration: none;
            margin: 0 8px;
            font-size: 14px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-error {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .status-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                border-radius: 0;
            }
            
            .email-header,
            .email-content,
            .email-footer {
                padding: 24px 20px;
            }
            
            .content-heading {
                font-size: 20px;
            }
            
            .button {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <!-- Preheader Text (Hidden but read by email clients) -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {$preheader}
    </div>
    
    <!-- Email Wrapper -->
    <table role="presentation" class="email-wrapper" cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
            <td align="center">
                <!-- Email Container -->
                <table role="presentation" class="email-container" cellpadding="0" cellspacing="0" border="0" width="600">
                    <!-- Header -->
                    <tr>
                        <td class="email-header">
                            <div class="email-logo">DTR System</div>
                            <p class="email-tagline">Daily Time Record Management</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td class="email-content">
                            <h1 class="content-heading">{$header}</h1>
                            
                            <div class="content-text">
                                {$content}
                            </div>
HTML;

    // Add button if provided
    if (!empty($button_text) && !empty($button_url)) {
        $html .= <<<HTML
                            
                            <div class="button-container">
                                <a href="{$button_url}" class="button">{$button_text}</a>
                            </div>
HTML;
    }

    // Add footer text if provided
    if (!empty($footer_text)) {
        $html .= <<<HTML
                            
                            <hr class="divider">
                            
                            <p class="content-text" style="font-size: 14px; color: #6b7280;">
                                {$footer_text}
                            </p>
HTML;
    }

    $html .= <<<HTML
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td class="email-footer">
                            <p class="footer-text">
                                This is an automated message from DTR System.<br>
                                Please do not reply to this email.
                            </p>
                            
                            <p class="footer-text" style="margin-top: 16px;">
                                &copy; {$year} DTR System. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    return $html;
}

/**
 * Template for Account Creation Email
 */
function email_account_created($employeeData) {
    $content = <<<HTML
<p>Hello <strong>{$employeeData['first_name']}</strong>,</p>

<p>Your account has been successfully created in the DTR System. You can now log in and start recording your daily time.</p>

<div class="info-box">
    <p><strong>Employee ID:</strong> {$employeeData['employee_id']}</p>
    <p><strong>PIN:</strong> {$employeeData['pin']}</p>
    <p><strong>Position:</strong> {$employeeData['position']}</p>
    <p><strong>Department:</strong> {$employeeData['department']}</p>
</div>

<p>Please keep your PIN confidential and change it after your first login if needed.</p>
HTML;

    return render_email_template([
        'title' => 'Welcome to DTR System',
        'preheader' => 'Your account has been created',
        'header' => 'Welcome to DTR System!',
        'content' => $content,
        'button_text' => 'Login Now',
        // Ensure full absolute URL with protocol + path
        'button_url' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/employee/login.php',
        'footer_text' => 'If you did not expect this email or have questions, please contact your administrator.'
    ]);
}

/**
 * Template for Reason Approval Email
 */
function email_reason_approved($employeeData, $reasonData) {
    $statusClass = $reasonData['status'] === 'approved' ? 'status-success' : 'status-error';
    $statusText = strtoupper($reasonData['status']);
    
    $content = <<<HTML
<p>Hello <strong>{$employeeData['first_name']}</strong>,</p>

<p>Your submitted reason for <strong>{$reasonData['reason_type']}</strong> on <strong>{$reasonData['date']}</strong> has been reviewed.</p>

<div style="text-align: center; margin: 24px 0;">
    <span class="status-badge {$statusClass}">{$statusText}</span>
</div>

<div class="info-box">
    <p><strong>Date:</strong> {$reasonData['date']}</p>
    <p><strong>Type:</strong> {$reasonData['reason_type']}</p>
    <p><strong>Your Reason:</strong> {$reasonData['reason_text']}</p>
</div>
HTML;

    if (!empty($reasonData['admin_notes'])) {
        $content .= <<<HTML

<p><strong>Administrator Notes:</strong></p>
<p style="padding: 12px; background-color: #f9fafb; border-radius: 6px; font-style: italic;">
    {$reasonData['admin_notes']}
</p>
HTML;
    }

    return render_email_template([
        'title' => 'Reason Review - DTR System',
        'preheader' => "Your reason has been {$reasonData['status']}",
        'header' => 'Reason Review Update',
        'content' => $content,
        'button_text' => 'View My Records',
        'button_url' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/employee/view_records.php'
    ]);
}

/**
 * Template for Time Record Modified Email
 */
function email_record_modified($employeeData, $recordData) {
    $content = <<<HTML
<p>Hello <strong>{$employeeData['first_name']}</strong>,</p>

<p>Your time record for <strong>{$recordData['date']}</strong> has been modified by an administrator.</p>

<div class="info-box">
    <p><strong>Date:</strong> {$recordData['date']}</p>
    <p><strong>Modified By:</strong> {$recordData['admin_name']}</p>
    <p><strong>Modification Time:</strong> {$recordData['modified_at']}</p>
</div>

<p>Please review the changes and contact your administrator if you have any questions.</p>
HTML;

    return render_email_template([
        'title' => 'Time Record Modified - DTR System',
        'preheader' => 'Your time record has been updated',
        'header' => 'Time Record Updated',
        'content' => $content,
        'button_text' => 'View My Records',
        'button_url' => (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/employee/view_records.php',
        'footer_text' => 'This modification was made for accuracy. If you believe this is an error, please contact your administrator immediately.'
    ]);
}
