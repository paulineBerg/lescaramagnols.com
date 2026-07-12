-- Private portal modules registry
-- Each row corresponds to a module that can be enabled per family user.

CREATE TABLE IF NOT EXISTS car_private_modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(64) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    display_name VARCHAR(128) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_private_modules_active (is_active),
    KEY idx_private_modules_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO car_private_modules (`code`, `is_active`, `display_name`, `description`)
    VALUES
        ('dashboard', 1, 'Dashboard', 'Tableau de bord principal de l’espace privé.'),
        ('documents', 1, 'Documents', 'Accès aux documents privés de l’usager.'),
        ('discussions', 0, 'Discussions', 'Gestion des échanges en accès privé.');
