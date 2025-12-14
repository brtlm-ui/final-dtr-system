# InfinityFree Deployment Checklist

## ⚠️ CRITICAL - Must Do Before Deploying

### 1. **Database Configuration** 
**File:** `config/database.php`

Change these values to match InfinityFree:
```php
define('DB_HOST', 'sql###.infinityfreeapp.com'); // InfinityFree MySQL host
define('DB_USER', 'epiz_#######');              // Your InfinityFree DB username
define('DB_PASS', 'your_db_password');          // Your InfinityFree DB password
define('DB_NAME', 'epiz_#######_employee_dtr'); // Your InfinityFree database name
```

📝 **Note:** InfinityFree MySQL hostnames are usually `sql###.infinityfreeapp.com` (not localhost)

---

### 2. **SMTP Email Configuration**
**File:** `config/mail.php`

InfinityFree blocks outgoing mail() and port 25. You MUST use external SMTP:

**Option A: Keep Gmail (Recommended)**
- Already configured in your code
- Keep `smtp.gmail.com` and port `587`
- Ensure your App Password is still valid

**Option B: Use free SMTP providers**
- SendGrid (100 emails/day free)
- Mailgun (first month free)
- SMTP2GO (1000 emails/month free)

Update lines 32-39 if changing provider:
```php
'host'       => 'smtp.yourprovider.com',
'port'       => 587,
'username'   => 'your_smtp_username',
'password'   => 'your_smtp_password',
'encryption' => 'tls',
```

---

### 3. **Remove Debug Code**
**File:** `api/admin_login.php` - Lines 9 and 23

❌ **DELETE these lines:**
```php
file_put_contents(__DIR__ . '/../debug_admin.txt', "POST: " . print_r($_POST, true));
file_put_contents(__DIR__ . '/../debug_admin.txt', "\nADMIN: " . print_r($admin, true), FILE_APPEND);
```

---

### 4. **Delete Unused Files**

Remove these before uploading:
- `test_kpi.php`
- `test_record.php`
- `verify.php`
- `debug_admin.txt`
- `composer.phar` (if composer isn't needed on server)
- `print/monthly_timesheet.php` (unused)
- Entire `tests/` folder:
  - `tests/notification_reads_demo.php`
  - `tests/notification_update_record_demo.php`
  - `tests/send_mail_demo.php`
  - `tests/validate_kpis.php`

---

### 5. **Create .htaccess File**
InfinityFree requires proper .htaccess for routing and security.

**Create:** `.htaccess` in root directory
```apache
# Error handling - hide errors in production
php_flag display_errors off
php_flag display_startup_errors off
php_value error_reporting 0

# Security - prevent directory listing
Options -Indexes

# Default document
DirectoryIndex index.php

# Protect sensitive files
<FilesMatch "\.(sql|md|txt|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Protect config directory
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^config/ - [F,L]
    RewriteRule ^vendor/ - [F,L]
    RewriteRule ^docs/ - [F,L]
</IfModule>

# Session security
php_value session.cookie_httponly 1
php_value session.use_only_cookies 1

# Upload size limits (adjust if needed)
php_value upload_max_filesize 10M
php_value post_max_size 10M
```

---

### 6. **Import Database**

1. **Export:** Already done - `database_dump.sql`
2. **Login to InfinityFree's phpMyAdmin**
3. **Create database** (if not auto-created)
4. **Import:** Upload `database_dump.sql`
5. **Verify:** Check all tables exist (admin, employee, time_record, etc.)

---

### 7. **Update Session Configuration**
**File:** `includes/session.php`

Check if session paths need adjustment for InfinityFree's file system restrictions.

---

### 8. **URL References** (Already Dynamic ✅)

Your code already handles URLs dynamically:
```php
'http://' . $_SERVER['HTTP_HOST']
```
This will automatically use your InfinityFree domain. No changes needed!

---

## 🔍 InfinityFree Specific Limitations

### Known Restrictions:
1. **No outgoing port 25** (mail blocked) - Use SMTP ✅ Already configured
2. **No cron jobs** - Your `tools/email_worker.php` won't run automatically
   - **Solution:** Use external cron service (cron-job.org, EasyCron)
   - Call: `https://yourdomain.infinityfreeapp.com/tools/email_worker.php`
3. **File permissions:** InfinityFree auto-manages, but watch for write issues
4. **500 MB disk space** limit
5. **Idle timeout:** Sites sleep after inactivity
6. **No shell access:** All management via File Manager/FTP

---

## 📋 Pre-Upload Checklist

- [ ] Updated `config/database.php` with InfinityFree credentials
- [ ] Verified SMTP settings in `config/mail.php`
- [ ] Removed debug code from `api/admin_login.php`
- [ ] Deleted test files (test_kpi.php, test_record.php, verify.php, debug_admin.txt)
- [ ] Deleted `tests/` folder
- [ ] Deleted unused `print/monthly_timesheet.php`
- [ ] Created `.htaccess` file
- [ ] Tested database_dump.sql imports successfully
- [ ] Set up external cron for email_worker.php (optional but recommended)
- [ ] Changed default admin password after deployment

---

## 🚀 Upload Methods

**Option 1: FTP (FileZilla)**
- Host: `ftpupload.net`
- Username: `epiz_#######`
- Port: 21
- Upload to: `/htdocs/` folder

**Option 2: File Manager**
- Use InfinityFree's built-in File Manager
- Upload as ZIP and extract online

---

## ✅ Post-Deployment Testing

1. Visit: `https://yourdomain.infinityfreeapp.com/`
2. Test admin login
3. Test employee login
4. Test clock in/out
5. Test notifications
6. Test email sending
7. Test printing functions
8. Check error logs in InfinityFree control panel

---

## 🆘 Troubleshooting

**"Database connection failed"**
- Double-check DB_HOST, DB_USER, DB_PASS, DB_NAME
- InfinityFree DB host is NOT 'localhost'

**"500 Internal Server Error"**
- Check .htaccess syntax
- Verify PHP version compatibility (InfinityFree uses PHP 8.x)
- Check error logs in control panel

**"Emails not sending"**
- Verify SMTP credentials
- Test with SMTP tester
- Check spam folder

**"Session issues"**
- Clear browser cookies
- Check session.php paths

---

## 📧 Need Help?

- InfinityFree Forum: https://forum.infinityfree.com/
- InfinityFree Knowledge Base: https://infinityfree.com/support/

---

**Last Updated:** December 14, 2025
