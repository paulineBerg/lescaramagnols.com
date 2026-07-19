SET @tile_group_item_translations_table = '{{table:tile_group_item_translations}}';
SET @tile_group_item_translations_table_name = REPLACE(@tile_group_item_translations_table, '`', '');
SET @tile_translations_created_at_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @tile_group_item_translations_table_name
      AND COLUMN_NAME = 'created_at'
);
SET @tile_translations_created_at_sql = IF(
    @tile_translations_created_at_missing,
    CONCAT(
        'ALTER TABLE ',
        @tile_group_item_translations_table,
        ' ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
    ),
    'DO 0'
);
PREPARE tile_translations_created_at_stmt FROM @tile_translations_created_at_sql;
EXECUTE tile_translations_created_at_stmt;
DEALLOCATE PREPARE tile_translations_created_at_stmt;

SET @tile_translations_updated_at_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @tile_group_item_translations_table_name
      AND COLUMN_NAME = 'updated_at'
);
SET @tile_translations_updated_at_sql = IF(
    @tile_translations_updated_at_missing,
    CONCAT(
        'ALTER TABLE ',
        @tile_group_item_translations_table,
        ' ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ),
    'DO 0'
);
PREPARE tile_translations_updated_at_stmt FROM @tile_translations_updated_at_sql;
EXECUTE tile_translations_updated_at_stmt;
DEALLOCATE PREPARE tile_translations_updated_at_stmt;

SET @page_tile_item_overrides_table = '{{table:page_tile_item_overrides}}';
SET @page_tile_item_overrides_table_name = REPLACE(@page_tile_item_overrides_table, '`', '');
SET @tile_overrides_created_at_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @page_tile_item_overrides_table_name
      AND COLUMN_NAME = 'created_at'
);
SET @tile_overrides_created_at_sql = IF(
    @tile_overrides_created_at_missing,
    CONCAT(
        'ALTER TABLE ',
        @page_tile_item_overrides_table,
        ' ADD COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
    ),
    'DO 0'
);
PREPARE tile_overrides_created_at_stmt FROM @tile_overrides_created_at_sql;
EXECUTE tile_overrides_created_at_stmt;
DEALLOCATE PREPARE tile_overrides_created_at_stmt;

SET @tile_overrides_updated_at_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @page_tile_item_overrides_table_name
      AND COLUMN_NAME = 'updated_at'
);
SET @tile_overrides_updated_at_sql = IF(
    @tile_overrides_updated_at_missing,
    CONCAT(
        'ALTER TABLE ',
        @page_tile_item_overrides_table,
        ' ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ),
    'DO 0'
);
PREPARE tile_overrides_updated_at_stmt FROM @tile_overrides_updated_at_sql;
EXECUTE tile_overrides_updated_at_stmt;
DEALLOCATE PREPARE tile_overrides_updated_at_stmt;
