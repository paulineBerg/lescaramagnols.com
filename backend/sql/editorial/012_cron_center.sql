CREATE TABLE IF NOT EXISTS {{table:cron_jobs}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `description` TEXT NULL,
    `script_path` VARCHAR(255) NOT NULL,
    `arguments_json` LONGTEXT NULL,
    `schedule_expression` VARCHAR(64) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'active',
    `timeout_seconds` INT UNSIGNED NOT NULL DEFAULT 300,
    `last_run_at` DATETIME NULL,
    `last_status` VARCHAR(32) NULL,
    `last_exit_code` INT NULL,
    `last_duration_ms` INT UNSIGNED NULL,
    `next_run_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_cron_jobs_code` (`code`),
    KEY `idx_cron_jobs_status` (`status`),
    KEY `idx_cron_jobs_next_run_at` (`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:cron_runs}} (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `job_code` VARCHAR(64) NOT NULL,
    `job_name` VARCHAR(191) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `scheduled_at` DATETIME NULL,
    `started_at` DATETIME NOT NULL,
    `finished_at` DATETIME NULL,
    `duration_ms` INT UNSIGNED NULL,
    `exit_code` INT NULL,
    `stdout_text` LONGTEXT NULL,
    `stderr_text` LONGTEXT NULL,
    `message` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cron_runs_job_code` (`job_code`),
    KEY `idx_cron_runs_status` (`status`),
    KEY `idx_cron_runs_started_at` (`started_at`),
    CONSTRAINT `fk_cron_runs_job`
        FOREIGN KEY (`job_code`)
        REFERENCES {{table:cron_jobs}} (`code`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS {{table:cron_scheduler_state}} (
    `state_key` VARCHAR(64) NOT NULL,
    `value_json` LONGTEXT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`state_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
