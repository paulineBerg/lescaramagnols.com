-- Jobs cron pour la maintenance du Document Hub
-- Ces jobs doivent être ajoutés à la table cron_jobs

-- 1. Intégrité + GC + Purge de corbeille quotidienne
INSERT IGNORE INTO `cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_maintenance',
    'Maintenance Document Hub',
    'Exécute l\'intégrité, la garbage collection et la purge de corbeille pour le Document Hub',
    'core/tools/document_hub_maintenance.php',
    '{"args": ["--json", "--delete-unreferenced"]}',
    '30 2 * * *',
    'active',
    1800,
    NOW(),
    NOW()
);

-- 2. Vérification d'intégrité uniquement (plus fréquente)
INSERT IGNORE INTO `cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_integrity',
    'Vérification intégrité Document Hub',
    'Vérifie l\'intégrité des objets et documents sans modification',
    'core/tools/document_hub_integrity.php',
    '{"args": ["--json"]}',
    '*/4 * * * *',
    'active',
    600,
    NOW(),
    NOW()
);

-- 3. Garbage Collection quotidienne
INSERT IGNORE INTO `cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_garbage_collection',
    'GC Document Hub',
    'Nettoyage des fichiers temporaires (quarantaine, exports, jobs expirés)',
    'core/tools/document_hub_gc.php',
    '{"args": ["--json", "--delete-unreferenced", "--delete-quarantine", "--delete-exports"]}',
    '45 2 * * *',
    'active',
    900,
    NOW(),
    NOW()
);

-- 4. Backup documentaire quotidien
INSERT IGNORE INTO `cron_jobs` (
    `code`, `name`, `description`, `script_path`, `arguments_json`, `schedule_expression`,
    `status`, `timeout_seconds`, `created_at`, `updated_at`
) VALUES (
    'document_hub_backup',
    'Backup Document Hub',
    'Sauvegarde des tables et fichiers du Document Hub',
    'core/tools/document_hub_backup.php',
    '{"args": ["--json", "--target=/home/surfacepro8/www/caramagnols/backend/private/storage/backups/document-hub"]}',
    '0 1 * * *',
    'active',
    3600,
    NOW(),
    NOW()
);
