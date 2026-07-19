-- Private portal blocnote notes and categories.
-- Source of truth for the private Bloc-note module schema.

CREATE TABLE IF NOT EXISTS car_private_blocnote_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    name VARCHAR(80) NOT NULL,
    slug VARCHAR(96) NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#ffffff',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_private_blocnote_categories_user_slug (private_user_id, slug),
    KEY idx_private_blocnote_categories_user (private_user_id, is_default, name),
    CONSTRAINT fk_private_blocnote_categories_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS car_private_blocnote_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    private_user_id INT NOT NULL,
    category_id INT NULL,
    title VARCHAR(191) NOT NULL DEFAULT '',
    content LONGTEXT NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#ffffff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_private_blocnote_notes_user_updated (private_user_id, updated_at),
    KEY idx_private_blocnote_notes_category (category_id),
    CONSTRAINT fk_private_blocnote_notes_user
        FOREIGN KEY (private_user_id)
        REFERENCES car_private_users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_private_blocnote_notes_category
        FOREIGN KEY (category_id)
        REFERENCES car_private_blocnote_categories(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
