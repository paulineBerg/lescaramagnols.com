-- Jobs cron pour la maintenance du Document Hub.
-- Ce seed est fourni comme référence SQL statique. En production, préférer
-- backend/core/tools/configure_document_hub_cron_jobs.php qui applique le
-- préfixe dynamique de table via CronJobRepository.

-- 1. Intégrité + GC + Purge de corbeille quotidienne
INSERT IGNORE INTO `car_cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_maintenance',
    'Maintenance Document Hub',
    'Exécute l\'intégrité, la garbage collection et la purge de corbeille pour le Document Hub',
    'core/tools/document_hub_maintenance.php',
    '{"args": ["--json", "--dry-run"]}',
    '30 2 * * *',
    'active',
    1800,
    NOW(),
    NOW()
);

-- 2. Vérification d'intégrité quotidienne, sans recalcul SHA-256 complet
INSERT IGNORE INTO `car_cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_integrity',
    'Vérification intégrité Document Hub',
    'Vérifie l\'intégrité des objets et documents sans modification',
    'core/tools/document_hub_integrity.php',
    '{"args": ["--json"]}',
    '15 3 * * *',
    'active',
    600,
    NOW(),
    NOW()
);

-- 3. Garbage Collection quotidienne
INSERT IGNORE INTO `car_cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_garbage_collection',
    'GC Document Hub',
    'Nettoyage report-only des fichiers temporaires et inventaire des objets non référencés',
    'core/tools/document_hub_gc.php',
    '{"args": ["--json"]}',
    '45 2 * * *',
    'active',
    900,
    NOW(),
    NOW()
);

-- 4. Backup documentaire quotidien
INSERT IGNORE INTO `car_cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_backup',
    'Backup Document Hub',
    'Sauvegarde des tables et fichiers du Document Hub',
    'core/tools/document_hub_backup.php',
    '{"args": ["--json"]}',
    '0 1 * * *',
    'active',
    3600,
    NOW(),
    NOW()
);
