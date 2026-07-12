SET @tile_group_items_table = '{{table:tile_group_items}}';
SET @tile_group_items_table_name = REPLACE(@tile_group_items_table, '`', '');
SET @tile_group_items_is_visible_missing = (
    SELECT COUNT(*) = 0
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @tile_group_items_table_name
      AND COLUMN_NAME = 'is_visible'
);
SET @tile_group_items_is_visible_sql = IF(
    @tile_group_items_is_visible_missing,
    CONCAT(
        'ALTER TABLE ',
        @tile_group_items_table,
        ' ADD COLUMN `is_visible` TINYINT(1) NOT NULL DEFAULT 1 AFTER `target_url`'
    ),
    'DO 0'
);
PREPARE tile_group_items_is_visible_stmt FROM @tile_group_items_is_visible_sql;
EXECUTE tile_group_items_is_visible_stmt;
DEALLOCATE PREPARE tile_group_items_is_visible_stmt;
