CREATE TABLE IF NOT EXISTS {{table:tile_groups}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `theme` VARCHAR(64) NOT NULL DEFAULT 'windows10-classic',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tile_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:tile_group_items}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id` BIGINT UNSIGNED NOT NULL,
    `item_uid` VARCHAR(128) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `tile_size` VARCHAR(16) NOT NULL DEFAULT 'rectangle',
    `color_token` VARCHAR(64) NOT NULL DEFAULT 'bleu',
    `image_src` VARCHAR(255) NULL,
    `image_width` INT UNSIGNED NULL,
    `image_height` INT UNSIGNED NULL,
    `target_type` VARCHAR(16) NOT NULL DEFAULT 'page',
    `target_page_slug` VARCHAR(191) NULL,
    `target_route` VARCHAR(255) NULL,
    `target_url` VARCHAR(255) NULL,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `open_in_new_tab` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_tile_group_item_uid` (`group_id`, `item_uid`),
    KEY `idx_tile_group_items_group_sort` (`group_id`, `sort_order`),
    FOREIGN KEY (`group_id`) REFERENCES {{table:tile_groups}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:tile_group_item_translations}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `locale` VARCHAR(16) NOT NULL,
    `label_text` VARCHAR(255) NULL,
    `accessibility_alt` VARCHAR(255) NULL,
    `accessibility_title` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_tile_group_item_locale` (`item_id`, `locale`),
    KEY `idx_tile_group_item_translations_locale` (`locale`),
    FOREIGN KEY (`item_id`) REFERENCES {{table:tile_group_items}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:page_tile_placements}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_slug` VARCHAR(191) NOT NULL,
    `region_key` VARCHAR(32) NOT NULL DEFAULT 'after_body',
    `group_id` BIGINT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_tile_placements_page_region_sort` (`page_slug`, `region_key`, `sort_order`),
    KEY `idx_page_tile_placements_group` (`group_id`),
    FOREIGN KEY (`group_id`) REFERENCES {{table:tile_groups}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:page_tile_item_overrides}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `placement_id` BIGINT UNSIGNED NOT NULL,
    `group_item_uid` VARCHAR(128) NOT NULL,
    `is_visible` TINYINT(1) NULL,
    `target_type` VARCHAR(16) NULL,
    `target_page_slug` VARCHAR(191) NULL,
    `target_route` VARCHAR(255) NULL,
    `target_url` VARCHAR(255) NULL,
    `open_in_new_tab` TINYINT(1) NULL,
    `label_translations_json` LONGTEXT NULL,
    `alt_translations_json` LONGTEXT NULL,
    `title_translations_json` LONGTEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_page_tile_override_uid` (`placement_id`, `group_item_uid`),
    KEY `idx_page_tile_item_overrides_placement` (`placement_id`),
    FOREIGN KEY (`placement_id`) REFERENCES {{table:page_tile_placements}} (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
