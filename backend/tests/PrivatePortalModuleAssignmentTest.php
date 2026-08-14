<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivatePortalModuleAssignmentTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testOnlyRegisteredModulesCanBeAssigned(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $validCodes = $moduleRepository->validModuleCodesFromPayload(['documents', 'unknown']);

        $this->assertSame(['documents'], $validCodes);
        $this->assertTrue($moduleRepository->setUserModules($userId, $validCodes, 'admin@example.com'));
        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'documents'));
        $this->assertFalse($moduleRepository->userHasModuleAccess($userId, 'unknown'));
    }

    public function testLegacyFamilyDiscussionPermissionCodeIsMappedToDiscussions(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('legacy-discussions@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $moduleRepository->listRegistryModuleStates();

        $moduleTable = $database->table('private_modules');
        $permissionTable = $database->table('private_user_module_permissions');

        $statement = $database->pdo()->prepare(
            sprintf(
                'INSERT IGNORE INTO `%s` (`code`, `is_active`, `display_name`, `description`)
                 VALUES (\'family_discussion\', 1, \'Discussions\', \'Historique legacy.\')',
                $moduleTable
            )
        );
        $statement->execute();

        $moduleIdStatement = $database->pdo()->prepare(
            sprintf('SELECT `id` FROM `%s` WHERE `code` = :code LIMIT 1', $moduleTable)
        );
        $moduleIdStatement->execute(['code' => 'family_discussion']);
        $moduleId = $moduleIdStatement->fetchColumn();
        $this->assertTrue($moduleId !== false && is_numeric($moduleId));

        $insertPermission = $database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (`private_user_id`, `private_module_id`, `is_active`, `granted_at`)
                 VALUES (:user_id, :module_id, 1, CURRENT_TIMESTAMP)',
                $permissionTable
            )
        );
        $insertPermission->execute([
            'user_id' => $userId,
            'module_id' => (int) $moduleId,
        ]);

        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'discussions'));
        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'family_discussion'));

        $states = $moduleRepository->listModuleStatesForUser($userId);
        $statesByCode = [];
        foreach ($states as $state) {
            if (!is_array($state)) {
                continue;
            }
            $stateCode = is_string($state['code'] ?? null) ? (string) $state['code'] : '';
            if ($stateCode !== '') {
                $statesByCode[$stateCode] = $state;
            }
        }

        $this->assertArrayHasKey('discussions', $statesByCode);
        $this->assertTrue((bool) ($statesByCode['discussions']['assigned'] ?? false));
        $this->assertSame(
            ['discussions'],
            $moduleRepository->validModuleCodesFromPayload(['family_discussion', 'discussions', 'unknown'])
        );
    }

    public function testLegacyPrivateAppCodesOpenCanonicalWebapps(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('legacy-webapps@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $moduleRepository->listRegistryModuleStates();

        $moduleTable = $database->table('private_modules');
        $permissionTable = $database->table('private_user_module_permissions');
        $insertModule = $database->pdo()->prepare(
            sprintf(
                'INSERT IGNORE INTO `%s` (`code`, `is_active`, `display_name`, `description`)
                 VALUES (:code, 1, :display_name, :description)',
                $moduleTable
            )
        );
        $insertPermission = $database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (`private_user_id`, `private_module_id`, `is_active`, `granted_at`)
                 VALUES (:user_id, :module_id, 1, CURRENT_TIMESTAMP)',
                $permissionTable
            )
        );
        $selectModuleId = $database->pdo()->prepare(
            sprintf('SELECT `id` FROM `%s` WHERE `code` = :code LIMIT 1', $moduleTable)
        );

        foreach ([
            'bloc_note' => 'Bloc-note',
            'files' => 'Documents',
            'locations_immobilieres' => 'Locations immobilières',
        ] as $legacyCode => $displayName) {
            $insertModule->execute([
                'code' => $legacyCode,
                'display_name' => $displayName,
                'description' => 'Code historique.',
            ]);

            $selectModuleId->execute(['code' => $legacyCode]);
            $moduleId = $selectModuleId->fetchColumn();
            $this->assertTrue($moduleId !== false && is_numeric($moduleId));

            $insertPermission->execute([
                'user_id' => $userId,
                'module_id' => (int) $moduleId,
            ]);
        }

        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'blocnote'));
        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'documents'));
        $this->assertTrue($moduleRepository->userHasModuleAccess($userId, 'real_estate_rental'));

        $activeCodes = array_map(
            static fn (array $module): string => (string) ($module['code'] ?? ''),
            $moduleRepository->activeModulesForUser($userId)
        );

        $this->assertContains('blocnote', $activeCodes);
        $this->assertContains('documents', $activeCodes);
        $this->assertContains('real_estate_rental', $activeCodes);
        $this->assertSame(
            ['blocnote', 'documents', 'real_estate_rental'],
            $moduleRepository->validModuleCodesFromPayload(['bloc_note', 'files', 'locations_immobilieres'])
        );
    }

    public function testCanonicalRevocationOverridesLegacyPbGestionPermission(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('legacy-pbgestion@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $moduleRepository->listRegistryModuleStates();

        $moduleTable = $database->table('private_modules');
        $permissionTable = $database->table('private_user_module_permissions');
        $database->pdo()->exec(
            sprintf(
                "INSERT IGNORE INTO `%s` (`code`, `is_active`, `display_name`, `description`)
                 VALUES ('pbgestion', 1, 'Sécurité réseau', 'Alias historique.')",
                $moduleTable
            )
        );
        $legacyModuleId = $database->pdo()->query(
            sprintf("SELECT `id` FROM `%s` WHERE `code` = 'pbgestion' LIMIT 1", $moduleTable)
        )?->fetchColumn();
        $this->assertTrue($legacyModuleId !== false && is_numeric($legacyModuleId));

        $insertPermission = $database->pdo()->prepare(
            sprintf(
                'INSERT INTO `%s` (`private_user_id`, `private_module_id`, `is_active`, `granted_at`)
                 VALUES (:user_id, :module_id, 1, CURRENT_TIMESTAMP)',
                $permissionTable
            )
        );
        $insertPermission->execute([
            'user_id' => $userId,
            'module_id' => (int) $legacyModuleId,
        ]);

        $this->assertTrue($moduleRepository->userHasExplicitModuleAccess($userId, 'network_security'));
        $this->assertTrue($moduleRepository->userHasExplicitModuleAccess($userId, 'photo_geo_renamer'));
        $this->assertTrue($moduleRepository->setUserModules($userId, ['network_security', 'photo_geo_renamer'], 'admin@example.com'));
        $this->assertTrue($moduleRepository->setUserModules($userId, ['network_security'], 'admin@example.com'));

        $this->assertTrue($moduleRepository->userHasExplicitModuleAccess($userId, 'network_security'));
        $this->assertFalse($moduleRepository->userHasExplicitModuleAccess($userId, 'photo_geo_renamer'));
    }
}
