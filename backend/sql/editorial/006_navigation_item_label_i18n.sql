ALTER TABLE {{table:navigation_items}}
    ADD COLUMN `label_default_language` VARCHAR(16) NULL AFTER `label_translation_key`,
    ADD COLUMN `label_translations_json` LONGTEXT NULL AFTER `label_default_language`;

