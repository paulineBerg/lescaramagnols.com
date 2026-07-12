ALTER TABLE {{table:navigation_items}}
    ADD COLUMN `display_mode` VARCHAR(16) NULL AFTER `kind`,
    ADD COLUMN `column_count` TINYINT UNSIGNED NULL AFTER `display_mode`,
    ADD COLUMN `menu_template` VARCHAR(32) NULL AFTER `column_count`,
    ADD COLUMN `is_highlight` TINYINT(1) NOT NULL DEFAULT 0 AFTER `menu_template`,
    ADD COLUMN `featured_title` VARCHAR(255) NULL AFTER `is_highlight`,
    ADD COLUMN `featured_text` LONGTEXT NULL AFTER `featured_title`,
    ADD COLUMN `featured_image` VARCHAR(255) NULL AFTER `featured_text`,
    ADD COLUMN `featured_cta_label` VARCHAR(255) NULL AFTER `featured_image`,
    ADD COLUMN `featured_target_page_slug` VARCHAR(191) NULL AFTER `featured_cta_label`,
    ADD COLUMN `featured_target_route` VARCHAR(255) NULL AFTER `featured_target_page_slug`,
    ADD COLUMN `featured_target_url` VARCHAR(255) NULL AFTER `featured_target_route`,
    ADD COLUMN `featured_open_in_new_tab` TINYINT(1) NOT NULL DEFAULT 0 AFTER `featured_target_url`;
