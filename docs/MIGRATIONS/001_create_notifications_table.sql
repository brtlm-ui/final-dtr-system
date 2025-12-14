-- Migration: create notifications table
-- Place this migration in docs/MIGRATIONS and run it against your DB (phpMyAdmin or mysql CLI)

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL, -- null for broadcast (admins/system), otherwise employee/admin id
  type VARCHAR(50) NOT NULL, -- e.g., 'reason_submitted', 'reason_approved', 'account_created'
  payload JSON NULL, -- optional extra data like {"record_id":123, "message":"..."}
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  link VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_isread (user_id, is_read),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
