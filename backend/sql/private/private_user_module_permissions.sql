-- Private portal module permissions per family user.
-- A revoked permission is represented by is_active=0 while preserving history.

CREATE TABLE IF NOT EXISTS car_private_user_module_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    private_module_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    granted_by_admin_email VARCHAR(254) NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    revoked_by_admin_email VARCHAR(254) NULL,
    UNIQUE KEY uq_private_user_module_permissions_user_module (private_user_id, private_module_id),
    KEY idx_private_user_module_permissions_user (private_user_id),
    KEY idx_private_user_module_permissions_module (private_module_id),
    CONSTRAINT fk_private_user_module_permissions_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_private_user_module_permissions_module
        FOREIGN KEY (private_module_id)
        REFERENCES car_private_modules (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
