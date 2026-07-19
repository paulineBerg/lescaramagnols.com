CREATE TABLE IF NOT EXISTS {{table:log_entries}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel` VARCHAR(32) NOT NULL,
    `level` VARCHAR(16) NOT NULL,
    `event` VARCHAR(191) NOT NULL,
    `context_json` LONGTEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_log_entries_channel` (`channel`),
    KEY `idx_log_entries_level` (`level`),
    KEY `idx_log_entries_created_at` (`created_at`),
    KEY `idx_log_entries_event` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
