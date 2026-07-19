SET @log_entries_table = '{{table:log_entries}}';
SET @log_entries_table_name = REPLACE(@log_entries_table, '`', '');

SET @log_entries_occurred_at_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'occurred_at'
);
SET @log_entries_occurred_at_sql = IF(
    @log_entries_occurred_at_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `occurred_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`'
    ),
    'DO 0'
);
PREPARE log_entries_occurred_at_stmt FROM @log_entries_occurred_at_sql;
EXECUTE log_entries_occurred_at_stmt;
DEALLOCATE PREPARE log_entries_occurred_at_stmt;

UPDATE {{table:log_entries}}
SET `occurred_at` = `created_at`
WHERE `occurred_at` IS NULL;

SET @log_entries_stream_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'stream'
);
SET @log_entries_stream_sql = IF(
    @log_entries_stream_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `stream` VARCHAR(32) NULL AFTER `occurred_at`'
    ),
    'DO 0'
);
PREPARE log_entries_stream_stmt FROM @log_entries_stream_sql;
EXECUTE log_entries_stream_stmt;
DEALLOCATE PREPARE log_entries_stream_stmt;

UPDATE {{table:log_entries}}
SET `stream` = CASE
    WHEN `channel` = 'security' THEN 'security'
    WHEN `channel` = 'content' THEN 'audit'
    WHEN `channel` = 'access' THEN 'application'
    ELSE `channel`
END
WHERE `stream` IS NULL OR `stream` = '';

SET @log_entries_application_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'application'
);
SET @log_entries_application_sql = IF(
    @log_entries_application_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `application` VARCHAR(64) NULL AFTER `stream`'
    ),
    'DO 0'
);
PREPARE log_entries_application_stmt FROM @log_entries_application_sql;
EXECUTE log_entries_application_stmt;
DEALLOCATE PREPARE log_entries_application_stmt;

SET @log_entries_module_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'module'
);
SET @log_entries_module_sql = IF(
    @log_entries_module_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `module` VARCHAR(64) NULL AFTER `application`'
    ),
    'DO 0'
);
PREPARE log_entries_module_stmt FROM @log_entries_module_sql;
EXECUTE log_entries_module_stmt;
DEALLOCATE PREPARE log_entries_module_stmt;

SET @log_entries_request_id_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'request_id'
);
SET @log_entries_request_id_sql = IF(
    @log_entries_request_id_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `request_id` VARCHAR(128) NULL AFTER `module`'
    ),
    'DO 0'
);
PREPARE log_entries_request_id_stmt FROM @log_entries_request_id_sql;
EXECUTE log_entries_request_id_stmt;
DEALLOCATE PREPARE log_entries_request_id_stmt;

SET @log_entries_correlation_id_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'correlation_id'
);
SET @log_entries_correlation_id_sql = IF(
    @log_entries_correlation_id_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `correlation_id` VARCHAR(128) NULL AFTER `request_id`'
    ),
    'DO 0'
);
PREPARE log_entries_correlation_id_stmt FROM @log_entries_correlation_id_sql;
EXECUTE log_entries_correlation_id_stmt;
DEALLOCATE PREPARE log_entries_correlation_id_stmt;

SET @log_entries_error_class_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'error_class'
);
SET @log_entries_error_class_sql = IF(
    @log_entries_error_class_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `error_class` VARCHAR(191) NULL AFTER `correlation_id`'
    ),
    'DO 0'
);
PREPARE log_entries_error_class_stmt FROM @log_entries_error_class_sql;
EXECUTE log_entries_error_class_stmt;
DEALLOCATE PREPARE log_entries_error_class_stmt;

SET @log_entries_error_fingerprint_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'error_fingerprint'
);
SET @log_entries_error_fingerprint_sql = IF(
    @log_entries_error_fingerprint_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `error_fingerprint` VARCHAR(64) NULL AFTER `error_class`'
    ),
    'DO 0'
);
PREPARE log_entries_error_fingerprint_stmt FROM @log_entries_error_fingerprint_sql;
EXECUTE log_entries_error_fingerprint_stmt;
DEALLOCATE PREPARE log_entries_error_fingerprint_stmt;

SET @log_entries_http_status_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'http_status'
);
SET @log_entries_http_status_sql = IF(
    @log_entries_http_status_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `http_status` SMALLINT UNSIGNED NULL AFTER `error_fingerprint`'
    ),
    'DO 0'
);
PREPARE log_entries_http_status_stmt FROM @log_entries_http_status_sql;
EXECUTE log_entries_http_status_stmt;
DEALLOCATE PREPARE log_entries_http_status_stmt;

SET @log_entries_duration_ms_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'duration_ms'
);
SET @log_entries_duration_ms_sql = IF(
    @log_entries_duration_ms_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `duration_ms` INT UNSIGNED NULL AFTER `http_status`'
    ),
    'DO 0'
);
PREPARE log_entries_duration_ms_stmt FROM @log_entries_duration_ms_sql;
EXECUTE log_entries_duration_ms_stmt;
DEALLOCATE PREPARE log_entries_duration_ms_stmt;

SET @log_entries_actor_type_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'actor_type'
);
SET @log_entries_actor_type_sql = IF(
    @log_entries_actor_type_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `actor_type` VARCHAR(32) NULL AFTER `duration_ms`'
    ),
    'DO 0'
);
PREPARE log_entries_actor_type_stmt FROM @log_entries_actor_type_sql;
EXECUTE log_entries_actor_type_stmt;
DEALLOCATE PREPARE log_entries_actor_type_stmt;

SET @log_entries_actor_id_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'actor_id'
);
SET @log_entries_actor_id_sql = IF(
    @log_entries_actor_id_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `actor_id` VARCHAR(191) NULL AFTER `actor_type`'
    ),
    'DO 0'
);
PREPARE log_entries_actor_id_stmt FROM @log_entries_actor_id_sql;
EXECUTE log_entries_actor_id_stmt;
DEALLOCATE PREPARE log_entries_actor_id_stmt;

SET @log_entries_entity_type_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'entity_type'
);
SET @log_entries_entity_type_sql = IF(
    @log_entries_entity_type_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `entity_type` VARCHAR(64) NULL AFTER `actor_id`'
    ),
    'DO 0'
);
PREPARE log_entries_entity_type_stmt FROM @log_entries_entity_type_sql;
EXECUTE log_entries_entity_type_stmt;
DEALLOCATE PREPARE log_entries_entity_type_stmt;

SET @log_entries_entity_id_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'entity_id'
);
SET @log_entries_entity_id_sql = IF(
    @log_entries_entity_id_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `entity_id` VARCHAR(191) NULL AFTER `entity_type`'
    ),
    'DO 0'
);
PREPARE log_entries_entity_id_stmt FROM @log_entries_entity_id_sql;
EXECUTE log_entries_entity_id_stmt;
DEALLOCATE PREPARE log_entries_entity_id_stmt;

SET @log_entries_schema_version_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND COLUMN_NAME = 'schema_version'
);
SET @log_entries_schema_version_sql = IF(
    @log_entries_schema_version_missing,
    CONCAT(
        'ALTER TABLE ',
        @log_entries_table,
        ' ADD COLUMN `schema_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `entity_id`'
    ),
    'DO 0'
);
PREPARE log_entries_schema_version_stmt FROM @log_entries_schema_version_sql;
EXECUTE log_entries_schema_version_stmt;
DEALLOCATE PREPARE log_entries_schema_version_stmt;

SET @log_entries_idx_occurred_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND INDEX_NAME = 'idx_log_entries_occurred_id'
);
SET @log_entries_idx_occurred_sql = IF(
    @log_entries_idx_occurred_missing,
    CONCAT('ALTER TABLE ', @log_entries_table, ' ADD INDEX `idx_log_entries_occurred_id` (`occurred_at`, `id`)'),
    'DO 0'
);
PREPARE log_entries_idx_occurred_stmt FROM @log_entries_idx_occurred_sql;
EXECUTE log_entries_idx_occurred_stmt;
DEALLOCATE PREPARE log_entries_idx_occurred_stmt;

SET @log_entries_idx_stream_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND INDEX_NAME = 'idx_log_entries_stream_occurred'
);
SET @log_entries_idx_stream_sql = IF(
    @log_entries_idx_stream_missing,
    CONCAT('ALTER TABLE ', @log_entries_table, ' ADD INDEX `idx_log_entries_stream_occurred` (`stream`, `occurred_at`)'),
    'DO 0'
);
PREPARE log_entries_idx_stream_stmt FROM @log_entries_idx_stream_sql;
EXECUTE log_entries_idx_stream_stmt;
DEALLOCATE PREPARE log_entries_idx_stream_stmt;

SET @log_entries_idx_request_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND INDEX_NAME = 'idx_log_entries_request_id'
);
SET @log_entries_idx_request_sql = IF(
    @log_entries_idx_request_missing,
    CONCAT('ALTER TABLE ', @log_entries_table, ' ADD INDEX `idx_log_entries_request_id` (`request_id`)'),
    'DO 0'
);
PREPARE log_entries_idx_request_stmt FROM @log_entries_idx_request_sql;
EXECUTE log_entries_idx_request_stmt;
DEALLOCATE PREPARE log_entries_idx_request_stmt;

SET @log_entries_idx_correlation_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND INDEX_NAME = 'idx_log_entries_correlation_id'
);
SET @log_entries_idx_correlation_sql = IF(
    @log_entries_idx_correlation_missing,
    CONCAT('ALTER TABLE ', @log_entries_table, ' ADD INDEX `idx_log_entries_correlation_id` (`correlation_id`)'),
    'DO 0'
);
PREPARE log_entries_idx_correlation_stmt FROM @log_entries_idx_correlation_sql;
EXECUTE log_entries_idx_correlation_stmt;
DEALLOCATE PREPARE log_entries_idx_correlation_stmt;

SET @log_entries_idx_fingerprint_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @log_entries_table_name
      AND INDEX_NAME = 'idx_log_entries_fingerprint_occurred'
);
SET @log_entries_idx_fingerprint_sql = IF(
    @log_entries_idx_fingerprint_missing,
    CONCAT('ALTER TABLE ', @log_entries_table, ' ADD INDEX `idx_log_entries_fingerprint_occurred` (`error_fingerprint`, `occurred_at`)'),
    'DO 0'
);
PREPARE log_entries_idx_fingerprint_stmt FROM @log_entries_idx_fingerprint_sql;
EXECUTE log_entries_idx_fingerprint_stmt;
DEALLOCATE PREPARE log_entries_idx_fingerprint_stmt;
