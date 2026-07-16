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
}
