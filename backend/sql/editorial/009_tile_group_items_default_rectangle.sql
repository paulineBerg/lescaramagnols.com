SET @tile_group_items_table = '{{table:tile_group_items}}';
SET @tile_group_items_default_sql = CONCAT(
    'ALTER TABLE ',
    @tile_group_items_table,
    ' MODIFY COLUMN `tile_size` VARCHAR(16) NOT NULL DEFAULT ''rectangle'''
);
PREPARE tile_group_items_default_stmt FROM @tile_group_items_default_sql;
EXECUTE tile_group_items_default_stmt;
DEALLOCATE PREPARE tile_group_items_default_stmt;

SET @tile_group_items_rectangle_sql = CONCAT(
    'UPDATE ',
    @tile_group_items_table,
    ' SET `tile_size` = ''rectangle''',
    ' WHERE `tile_size` IN (''small'', ''medium'', ''large'')',
    ' AND `image_src` IS NOT NULL',
    ' AND `image_src` <> '''''
);
PREPARE tile_group_items_rectangle_stmt FROM @tile_group_items_rectangle_sql;
EXECUTE tile_group_items_rectangle_stmt;
DEALLOCATE PREPARE tile_group_items_rectangle_stmt;
