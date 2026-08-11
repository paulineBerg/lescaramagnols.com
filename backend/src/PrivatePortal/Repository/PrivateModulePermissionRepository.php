<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use PDO;

final class PrivateModulePermissionRepository
{
    private bool $privateSchemaReady = false;

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly PrivateModuleRegistry $moduleRegistry
    ) {
    }

    /**
     * @return array<int, array{code: string, name: string, description: string, active: bool, assigned: bool}>
     */
    public function listModuleStatesForUser(int $userId): array
    {
        $states = $this->registryStates();
        if ($userId <= 0) {
            return array_values($states);
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT m.`code`, m.`is_active` AS module_active, p.`is_active` AS permission_active, p.`id` AS permission_id
                     FROM `%s` m
                     LEFT JOIN `%s` p
                        ON p.`private_module_id` = m.`id`
                       AND p.`private_user_id` = :user_id',
                    $this->moduleTable(),
                    $this->permissionTable()
                )
            );
            $statement->execute(['user_id' => $userId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return array_values($states);
        }

        $hasExplicitPermissions = false;
        $canonicalAssigned = [];
        $legacyAssigned = [];
        $canonicalActive = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $hasPermission = is_numeric($row['permission_id'] ?? null);
            if ($hasPermission) {
                $hasExplicitPermissions = true;
            }

            $rawCode = is_string($row['code'] ?? null) ? (string) $row['code'] : '';
            $code = $this->normalizeModuleCode($rawCode);
            if ($code === '' || !isset($states[$code])) {
                continue;
            }

            $hasCanonicalCode = $code === $this->sanitizeModuleCode($rawCode);
            if ($hasCanonicalCode) {
                $states[$code]['active'] = $this->truthy($row['module_active'] ?? null);
                $canonicalActive[$code] = true;
            } elseif (!($canonicalActive[$code] ?? false)) {
                $states[$code]['active'] = $this->truthy($row['module_active'] ?? null);
            }

            if ($hasPermission) {
                $assigned = $this->truthy($row['permission_active'] ?? null);

                if ($hasCanonicalCode) {
                    $canonicalAssigned[$code] = $assigned;
                } else {
                    $legacyAssigned[$code] = ($legacyAssigned[$code] ?? false) || $assigned;
                }
            }
        }

        if (!$hasExplicitPermissions) {
            foreach ($states as $code => $module) {
                $states[$code]['assigned'] = (bool) ($module['active'] ?? false);
            }
        } else {
            foreach ($states as $code => $module) {
                if (array_key_exists($code, $canonicalAssigned)) {
                    $states[$code]['assigned'] = (bool) $canonicalAssigned[$code];
                    continue;
                }

                if (array_key_exists($code, $legacyAssigned)) {
                    $states[$code]['assigned'] = (bool) $legacyAssigned[$code];
                }
            }
        }

        return array_values($states);
    }

    /**
     * @return array<int, array{code: string, name: string, description: string, active: bool, assigned: bool}>
     */
    public function listRegistryModuleStates(): array
    {
        $states = $this->registryStates();

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->query(
                sprintf(
                    'SELECT `code`, `is_active` FROM `%s`',
                    $this->moduleTable()
                )
            );
            $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return array_values($states);
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = $this->normalizeModuleCode(is_string($row['code'] ?? null) ? (string) $row['code'] : '');
            if ($code === '' || !isset($states[$code])) {
                continue;
            }

            $states[$code]['active'] = $this->truthy($row['is_active'] ?? null);
        }

        return array_values($states);
    }

    /**
     * @return array<int, array{code: string, name: string, description: string, active: bool, assigned: bool}>
     */
    public function activeModulesForUser(int $userId): array
    {
        return array_values(array_filter(
            $this->listModuleStatesForUser($userId),
            static fn (array $module): bool => $module['active'] && $module['assigned']
        ));
    }

    /**
     * @return array<string, int>
     */
    public function moduleDataCountsForUser(int $userId): array
    {
        $counts = array_fill_keys($this->moduleRegistry->moduleCodes(), 0);
        if ($userId <= 0) {
            return $counts;
        }

        $counts['documents'] = $this->countRows('private_documents', '`private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('private_document_categories', '`private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId]);

        $counts['blocnote'] = $this->countRows('private_blocnote_notes', '`private_user_id` = :user_id', ['user_id' => $userId])
            + $this->countRows('private_blocnote_categories', '`private_user_id` = :user_id', ['user_id' => $userId]);

        $counts['discussions'] = $this->countRows('discussion_conversation_members', '`private_user_id` = :user_id AND `left_at` IS NULL', ['user_id' => $userId])
            + $this->countRows('discussion_messages', '`sender_private_user_id` = :user_id AND `purge_status` = \'active\' AND `deleted_at` IS NULL', ['user_id' => $userId])
            + $this->countRows('discussion_crypto_devices', '`private_user_id` = :user_id AND `revoked_at` IS NULL', ['user_id' => $userId]);

        $counts['real_estate_rental'] = $this->countRows('rental_properties', '`created_by_private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('rental_units', '`created_by_private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('rental_property_members', '`private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('rental_tenants', '`created_by_private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('rental_leases', '`created_by_private_user_id` = :user_id AND `status` <> \'cancelled\'', ['user_id' => $userId])
            + $this->countRows('rental_rents', '`created_by_private_user_id` = :user_id AND `status` <> \'cancelled\'', ['user_id' => $userId])
            + $this->countRows('rental_payments', '`created_by_private_user_id` = :user_id AND `status` <> \'cancelled\'', ['user_id' => $userId])
            + $this->countRows('rental_payment_requests', '`sent_by_private_user_id` = :user_id', ['user_id' => $userId])
            + $this->countRows('rental_expenses', '`created_by_private_user_id` = :user_id AND `status` <> \'cancelled\'', ['user_id' => $userId])
            + $this->countRows('rental_documents', '`uploaded_by_private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('rental_generated_documents', '`generated_by_private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('rental_charge_regularizations', '`generated_by_private_user_id` = :user_id AND `is_active` = 1', ['user_id' => $userId])
            + $this->countRows('rental_export_logs', '`private_user_id` = :user_id', ['user_id' => $userId]);

        $counts['tax_declaration_helper'] = $this->countRows('tax_years', '`private_user_id` = :user_id', ['user_id' => $userId])
            + $this->countRows('tax_source_activations', '`private_user_id` = :user_id', ['user_id' => $userId])
            + $this->countRows('tax_manual_income_entries', '`private_user_id` = :user_id AND `status` <> \'cancelled\'', ['user_id' => $userId])
            + $this->countRows('tax_annual_summaries', '`private_user_id` = :user_id', ['user_id' => $userId])
            + $this->countRows('tax_export_logs', '`private_user_id` = :user_id', ['user_id' => $userId]);

        return $counts;
    }

    /**
     * @param array<int, string> $selectedCodes
     * @return array<int, array{code: string, name: string, count: int}>
     */
    public function blockedModuleRevocations(int $userId, array $selectedCodes): array
    {
        if ($userId <= 0) {
            return [];
        }

        $selectedCodes = $this->normalizeModuleCodes($selectedCodes);
        $counts = $this->moduleDataCountsForUser($userId);
        $blocked = [];
        foreach ($this->listModuleStatesForUser($userId) as $module) {
            $code = is_string($module['code'] ?? null) ? strtolower(trim((string) $module['code'])) : '';
            if ($code === '' || empty($module['assigned']) || in_array($code, $selectedCodes, true)) {
                continue;
            }

            $count = max(0, (int) ($counts[$code] ?? 0));
            if ($count <= 0) {
                continue;
            }

            $blocked[] = [
                'code' => $code,
                'name' => is_string($module['name'] ?? null) ? (string) $module['name'] : $code,
                'count' => $count,
            ];
        }

        return $blocked;
    }

    public function userHasModuleAccess(int $userId, string $moduleCode): bool
    {
        $normalizedCode = $this->normalizeModuleCode($moduleCode);
        $module = $this->moduleRegistry->moduleCode($normalizedCode);
        if ($userId <= 0 || !is_array($module)) {
            return false;
        }

        $code = is_string($normalizedCode) ? $normalizedCode : '';
        if ($code === '') {
            return false;
        }

        foreach ($this->listModuleStatesForUser($userId) as $state) {
            $stateCode = is_string($state['code'] ?? null) ? strtolower(trim($state['code'])) : '';
            if ($stateCode === $code) {
                return (bool) (($state['active'] ?? false) && ($state['assigned'] ?? false));
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $moduleCodes
     */
    public function setUserModules(int $userId, array $moduleCodes, ?string $actorIdentifier = null): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $selectedCodes = $this->normalizeModuleCodes($moduleCodes);
        $actorIdentifier = $this->normalizeActorIdentifier($actorIdentifier);
        $now = $this->currentDateTime();

        try {
            $this->ensureSchema();
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $this->ensureRegistryModules($pdo);
            $moduleIds = $this->moduleIdsByCode($pdo);
            foreach ($this->moduleRegistry->moduleCodes() as $code) {
                $moduleId = $moduleIds[$code] ?? null;
                if (!is_int($moduleId) || $moduleId <= 0) {
                    continue;
                }

                $enabled = in_array($code, $selectedCodes, true);
                $permissionId = $this->permissionId($pdo, $userId, $moduleId);
                if ($permissionId === null && !$enabled) {
                    continue;
                }

                if ($permissionId === null) {
                    $this->insertPermission($pdo, $userId, $moduleId, $actorIdentifier, $now);
                    continue;
                }

                $this->updatePermission($pdo, $permissionId, $enabled, $actorIdentifier, $now);
            }

            $pdo->commit();

            return true;
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    public function validModuleCodesFromPayload(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return $this->normalizeModuleCodes(array_values($value));
    }

    private function moduleTable(): string
    {
        return $this->database->table('private_modules');
    }

    private function permissionTable(): string
    {
        return $this->database->table('private_user_module_permissions');
    }

    /**
     * @param array<string, int|string|null> $params
     */
    private function countRows(string $table, string $where, array $params): int
    {
        try {
            $this->database->ensureReady();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $this->database->table($table), $where)
            );
            $statement->execute($params);

            return max(0, (int) $statement->fetchColumn());
        } catch (\Throwable) {
            return 0;
        }
    }

    private function ensureSchema(): void
    {
        if ($this->privateSchemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `code` VARCHAR(64) NOT NULL UNIQUE,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `display_name` VARCHAR(128) NOT NULL,
                    `description` TEXT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_private_modules_active` (`is_active`),
                    KEY `idx_private_modules_code` (`code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->moduleTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `private_module_id` INT NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `granted_by_admin_email` VARCHAR(254) NULL,
                    `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `revoked_at` DATETIME NULL,
                    `revoked_by_admin_email` VARCHAR(254) NULL,
                    UNIQUE KEY `uq_private_user_module_permissions_user_module` (`private_user_id`, `private_module_id`),
                    KEY `idx_private_user_module_permissions_user` (`private_user_id`),
                    KEY `idx_private_user_module_permissions_module` (`private_module_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->permissionTable()
            )
        );

        $this->ensureRegistryModules($pdo);
        $this->privateSchemaReady = true;
    }

    /**
     * @return array<string, array{code: string, name: string, description: string, active: bool, assigned: bool}>
     */
    private function registryStates(): array
    {
        $states = [];
        foreach ($this->moduleRegistry->allModules() as $module) {
            $code = is_string($module['code'] ?? null) ? strtolower(trim($module['code'])) : '';
            if ($code === '') {
                continue;
            }

            $states[$code] = [
                'code' => $code,
                'name' => is_string($module['name'] ?? null) ? trim((string) $module['name']) : $code,
                'description' => is_string($module['description'] ?? null) ? trim((string) $module['description']) : '',
                'active' => true,
                'assigned' => false,
            ];
        }

        return $states;
    }

    /**
     * @param array<int, mixed> $moduleCodes
     * @return array<int, string>
     */
    private function normalizeModuleCodes(array $moduleCodes): array
    {
        $allowed = $this->moduleRegistry->moduleCodes();
        $normalized = [];

        foreach ($moduleCodes as $moduleCode) {
            $code = $this->normalizeModuleCode((string) $moduleCode);
            if ($code === '' || !in_array($code, $allowed, true)) {
                continue;
            }

            $normalized[$code] = $code;
        }

        return array_values($normalized);
    }

    private function ensureRegistryModules(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s` (`code`, `is_active`, `display_name`, `description`, `created_at`, `updated_at`)
                 VALUES (:code, 1, :display_name, :description, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    `display_name` = VALUES(`display_name`),
                    `description` = VALUES(`description`),
                    `updated_at` = VALUES(`updated_at`)',
                $this->moduleTable()
            )
        );

        $now = $this->currentDateTime();
        foreach ($this->moduleRegistry->allModules() as $module) {
            $code = is_string($module['code'] ?? null) ? strtolower(trim($module['code'])) : '';
            if ($code === '') {
                continue;
            }

            $statement->execute([
                'code' => $code,
                'display_name' => is_string($module['name'] ?? null) ? trim((string) $module['name']) : $code,
                'description' => is_string($module['description'] ?? null) ? trim((string) $module['description']) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array<string, int>
     */
    private function moduleIdsByCode(PDO $pdo): array
    {
        $statement = $pdo->query(
            sprintf(
                'SELECT `id`, `code` FROM `%s`',
                $this->moduleTable()
            )
        );
        $rows = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = is_string($row['code'] ?? null) ? strtolower(trim($row['code'])) : '';
            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0;
            if ($code === '' || $id <= 0) {
                continue;
            }

            $ids[$code] = $id;
        }

        return $ids;
    }

    private function normalizeModuleCode(string $moduleCode): string
    {
        $code = $this->sanitizeModuleCode($moduleCode);
        if ($code === '') {
            return '';
        }

        return match ($code) {
            'bloc_note', 'blocnotes', 'notes_privees' => 'blocnote',
            'document', 'document_hub', 'documents_hub', 'fichiers', 'files', 'private_documents' => 'documents',
            'discussion', 'family_discussion', 'family_discussions', 'discussions_familiales' => 'discussions',
            'location', 'locations', 'locations_immobilieres', 'location_immobiliere',
            'real_estate', 'rental', 'rentals', 'rental_dashboard' => 'real_estate_rental',
            'aide_impot', 'aide_impots', 'fiscal', 'impot', 'impots', 'tax', 'tax_declaration' => 'tax_declaration_helper',
            'web', 'web_dev', 'webdevelopment', 'projets_web' => 'web_development',
            default => $code,
        };
    }

    private function sanitizeModuleCode(string $moduleCode): string
    {
        $code = strtr(trim($moduleCode), [
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ä' => 'A',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Î' => 'I',
            'Ï' => 'I',
            'Ô' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
        $code = strtolower($code);
        $code = str_replace(['-', ' '], '_', $code);
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? '';
        $code = preg_replace('/_+/', '_', $code) ?? '';

        return trim($code, '_');
    }

    private function permissionId(PDO $pdo, int $userId, int $moduleId): ?int
    {
        $statement = $pdo->prepare(
            sprintf(
                'SELECT `id` FROM `%s`
                 WHERE `private_user_id` = :user_id AND `private_module_id` = :module_id
                 LIMIT 1',
                $this->permissionTable()
            )
        );
        $statement->execute([
            'user_id' => $userId,
            'module_id' => $moduleId,
        ]);
        $id = $statement->fetchColumn();

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private function insertPermission(PDO $pdo, int $userId, int $moduleId, ?string $actorIdentifier, string $now): void
    {
        $statement = $pdo->prepare(
            sprintf(
                'INSERT INTO `%s`
                    (`private_user_id`, `private_module_id`, `is_active`, `granted_by_admin_email`, `granted_at`)
                 VALUES
                    (:user_id, :module_id, 1, :actor, :now)',
                $this->permissionTable()
            )
        );
        $statement->execute([
            'user_id' => $userId,
            'module_id' => $moduleId,
            'actor' => $actorIdentifier,
            'now' => $now,
        ]);
    }

    private function updatePermission(
        PDO $pdo,
        int $permissionId,
        bool $enabled,
        ?string $actorIdentifier,
        string $now
    ): void {
        if ($enabled) {
            $statement = $pdo->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `is_active` = 1,
                         `granted_by_admin_email` = :actor,
                         `granted_at` = :now,
                         `revoked_at` = NULL,
                         `revoked_by_admin_email` = NULL
                     WHERE `id` = :id',
                    $this->permissionTable()
                )
            );
            $statement->execute([
                'actor' => $actorIdentifier,
                'now' => $now,
                'id' => $permissionId,
            ]);

            return;
        }

        $statement = $pdo->prepare(
            sprintf(
                'UPDATE `%s`
                 SET `is_active` = 0,
                     `revoked_at` = :now,
                     `revoked_by_admin_email` = :actor
                 WHERE `id` = :id',
                $this->permissionTable()
            )
        );
        $statement->execute([
            'actor' => $actorIdentifier,
            'now' => $now,
            'id' => $permissionId,
        ]);
    }

    private function normalizeActorIdentifier(?string $actorIdentifier): ?string
    {
        $normalized = trim((string) $actorIdentifier);
        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return substr($normalized, 0, 254);
    }

    private function currentDateTime(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'active'], true);
    }
}
