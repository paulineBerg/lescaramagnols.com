-- Private portal per-user SMTP settings.
-- SMTP passwords are stored encrypted by the application key, never in clear text.

CREATE TABLE IF NOT EXISTS car_private_user_mail_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    smtp_host VARCHAR(190) NOT NULL,
    smtp_port INT UNSIGNED NOT NULL DEFAULT 587,
    smtp_user VARCHAR(190) NULL,
    smtp_password_ciphertext TEXT NULL,
    smtp_encryption VARCHAR(16) NOT NULL DEFAULT 'tls',
    from_address VARCHAR(254) NOT NULL,
    from_name VARCHAR(120) NOT NULL,
    reply_to VARCHAR(254) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_private_user_mail_settings_user (private_user_id),
    KEY idx_private_user_mail_settings_updated (updated_at),
    CONSTRAINT fk_private_user_mail_settings_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
