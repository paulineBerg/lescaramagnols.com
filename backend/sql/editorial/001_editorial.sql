CREATE TABLE IF NOT EXISTS {{table:pages}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(191) NOT NULL,
    `type` VARCHAR(64) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `title` VARCHAR(255) NULL,
    `layout` VARCHAR(128) NOT NULL DEFAULT 'standard_page',
    `route` VARCHAR(255) NULL,
    `template` VARCHAR(255) NULL,
    `meta_json` LONGTEXT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_slug` (`slug`),
    UNIQUE KEY `uniq_route` (`route`),
    KEY `idx_status` (`status`),
    KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:page_sections}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` BIGINT UNSIGNED NOT NULL,
    `section_group` VARCHAR(32) NOT NULL,
    `section_key` VARCHAR(64) NOT NULL,
    `payload_json` LONGTEXT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_page_section` (`page_id`, `section_group`, `section_key`),
    KEY `idx_section_group` (`section_group`),
    FOREIGN KEY (`page_id`) REFERENCES {{table:pages}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:page_translations}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` BIGINT UNSIGNED NOT NULL,
    `locale` VARCHAR(16) NOT NULL,
    `title` VARCHAR(255) NULL,
    `meta_json` LONGTEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_page_locale` (`page_id`, `locale`),
    KEY `idx_locale` (`locale`),
    FOREIGN KEY (`page_id`) REFERENCES {{table:pages}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:page_translation_sections}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `translation_id` BIGINT UNSIGNED NOT NULL,
    `section_group` VARCHAR(32) NOT NULL,
    `section_key` VARCHAR(64) NOT NULL,
    `payload_json` LONGTEXT NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_translation_section` (`translation_id`, `section_group`, `section_key`),
    KEY `idx_translation_section_group` (`section_group`),
    FOREIGN KEY (`translation_id`) REFERENCES {{table:page_translations}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:navigation_sets}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_key` VARCHAR(64) NOT NULL,
    `settings_json` LONGTEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_location_key` (`location_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:navigation_items}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `set_id` BIGINT UNSIGNED NOT NULL,
    `parent_id` BIGINT UNSIGNED NULL,
    `item_uid` VARCHAR(128) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `kind` VARCHAR(32) NOT NULL,
    `label_text` VARCHAR(255) NULL,
    `label_translation_key` VARCHAR(255) NULL,
    `target_page_slug` VARCHAR(191) NULL,
    `target_route` VARCHAR(255) NULL,
    `target_url` VARCHAR(255) NULL,
    `open_in_new_tab` TINYINT(1) NOT NULL DEFAULT 0,
    `media_image` VARCHAR(255) NULL,
    `content_text` LONGTEXT NULL,
    `accessibility_alt` VARCHAR(255) NULL,
    `accessibility_title` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_navigation_items_set` (`set_id`),
    KEY `idx_navigation_items_parent` (`parent_id`),
    KEY `idx_navigation_items_sort` (`sort_order`),
    FOREIGN KEY (`set_id`) REFERENCES {{table:navigation_sets}} (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES {{table:navigation_items}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
