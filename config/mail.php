<?php

return [
    // Toggle SMTP usage; if false native mail() is used.
    'use_smtp' => true,

    // Primary SMTP credentials (legacy shape retained)
    'smtp' => [
        // Gmail defaults (override via ENV for other providers)
        'host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port'       => (int) (getenv('SMTP_PORT') ?: 587),
        'username'   => getenv('SMTP_USER') ?: 'dtrsystem.wmschool@gmail.com',
        'password'   => getenv('SMTP_PASS') ?: 'dvxnvjcfniamseim', // 16-char Gmail App Password
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // tls (STARTTLS) | ssl
        'timeout'    => (int) (getenv('SMTP_TIMEOUT') ?: 15), // seconds
    ],

    // Sender identity
    'from_email' => getenv('MAIL_FROM_EMAIL') ?: 'dtrsystem.wmschool@gmail.com',
    'from_name'  => getenv('MAIL_FROM_NAME')  ?: 'DTR System',

    // Optional reply-to (null to skip)
    'reply_to_email' => getenv('MAIL_REPLY_TO') ?: null,
    'reply_to_name'  => getenv('MAIL_REPLY_TO_NAME') ?: null,

    // Branding options consumed by the HTML template builder
    'branding' => [
        // Logo removed per request; set to null/empty string to omit.
        'logo_url'      => getenv('MAIL_LOGO_URL') ?: '',
        'primary_color' => '#0D47A1', // deep blue
        'accent_color'  => '#1976D2', // lighter blue
        'bg_color'      => '#F5F7FA',
        'text_color'    => '#2D3748',
        'font_family'   => 'system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif'
    ],

    // Footer / compliance details (used by template)
    'footer' => [
        'company_name' => 'Your Company Inc.',
        'address_line' => '123 Example Street, City, Country',
        'contact_line' => 'Questions? Reply to this email.',
        'unsubscribe_url' => getenv('MAIL_UNSUB_URL') ?: null
    ]
];