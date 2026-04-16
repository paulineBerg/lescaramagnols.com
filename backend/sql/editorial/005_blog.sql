CREATE TABLE IF NOT EXISTS {{table:blog_articles}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(191) NOT NULL,
    `lang` VARCHAR(16) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
    `author` VARCHAR(191) NULL,
    `category` VARCHAR(191) NULL,
    `date_value` VARCHAR(64) NULL,
    `excerpt` TEXT NULL,
    `content` LONGTEXT NOT NULL,
    `tags_json` LONGTEXT NULL,
    `translations_json` LONGTEXT NULL,
    `comments_json` LONGTEXT NULL,
    `page_slug` VARCHAR(191) NULL,
    `parent_slug` VARCHAR(191) NULL,
    `parent_lang` VARCHAR(16) NULL,
    `child_sort_order` INT UNSIGNED NULL,
    `created_at` VARCHAR(64) NOT NULL,
    `updated_at` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_blog_articles_slug_lang` (`slug`, `lang`),
    KEY `idx_blog_articles_status` (`status`),
    KEY `idx_blog_articles_page_slug` (`page_slug`),
    KEY `idx_blog_articles_parent` (`parent_slug`, `parent_lang`),
    KEY `idx_blog_articles_date_value` (`date_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:blog_discussions}} (
    `id` VARCHAR(64) NOT NULL,
    `article_slug` VARCHAR(191) NOT NULL,
    `article_lang` VARCHAR(16) NOT NULL,
    `author` VARCHAR(191) NOT NULL DEFAULT '',
    `email` VARCHAR(191) NOT NULL DEFAULT '',
    `content` LONGTEXT NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `created_at` VARCHAR(64) NOT NULL,
    `updated_at` VARCHAR(64) NOT NULL,
    `moderated_at` VARCHAR(64) NULL,
    `moderated_by` VARCHAR(191) NULL,
    `ip_hash` VARCHAR(128) NULL,
    `user_agent_hash` VARCHAR(128) NULL,
    PRIMARY KEY (`id`),
    KEY `idx_blog_discussions_article` (`article_slug`, `article_lang`),
    KEY `idx_blog_discussions_status` (`status`),
    KEY `idx_blog_discussions_created_at` (`created_at`),
    CONSTRAINT `fk_blog_discussions_article`
        FOREIGN KEY (`article_slug`, `article_lang`)
        REFERENCES {{table:blog_articles}} (`slug`, `lang`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
