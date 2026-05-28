<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Operations\PrivateBackupService;
use Caramagnols\PrivatePortal\Operations\PrivateDataProtectionService;
use Caramagnols\PrivatePortal\Operations\PrivateMigrationService;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class PrivacyOperationsTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tempDir = '';

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeTempDir();
    }

    public function testGdprExportDoesNotExposePasswordHashAndAnonymizeDisablesAccount(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $service = new PrivateDataProtectionService($database);
        $userId = $this->createPrivateUser($userRepository, 'privacy-export@example.com');

        $export = $service->exportAccount($userId);
        $privateUser = is_array($export['privateUser'] ?? null) ? $export['privateUser'] : [];

        $this->assertSame('privacy-export@example.com', $privateUser['email'] ?? null);
        $this->assertArrayNotHasKey('passwordHash', $privateUser);
        $this->assertArrayNotHasKey('password_hash', $privateUser);

        $this->assertTrue($service->anonymizeAccount($userId, $userId, 'rgpd test'));
        $user = $userRepository->findById($userId);
        $this->assertIsArray($user);
        $this->assertSame('deleted', $user['status'] ?? null);
        $this->assertSame('deleted+' . $userId . '@anonymous.invalid', $user['email'] ?? null);
    }

    public function testPrivateBackupCanBeCreatedVerifiedAndDryRunRestored(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $this->createPrivateUser($userRepository, 'backup-phase9@example.com');
        $this->tempDir = sys_get_temp_dir() . '/caramagnols-phase9-backup-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);
        file_put_contents($this->tempDir . '/private-note.txt', 'backup test');

        $service = new PrivateBackupService($database);
        $backup = $service->createBackup($this->tempDir . '/exports', $this->tempDir);
        $this->assertTrue((bool) ($backup['success'] ?? false));
        $this->assertIsString($backup['path'] ?? null);

        $verification = $service->verifyBackup((string) $backup['path']);
        $this->assertTrue((bool) ($verification['valid'] ?? false));
        $this->assertGreaterThanOrEqual(1, (int) ($verification['tableCount'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($verification['fileCount'] ?? 0));

        $payload = json_decode((string) file_get_contents((string) $backup['path']), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('private_blocnote_notes', $payload['tables'] ?? []);
        $this->assertArrayHasKey('discussion_messages', $payload['tables'] ?? []);
        $this->assertArrayHasKey('summary', $payload);
        $files = is_array($payload['files'] ?? null) ? $payload['files'] : [];
        $this->assertIsArray($files[0] ?? null);
        $this->assertArrayHasKey('mtimeIso', $files[0]);
        $this->assertArrayHasKey('owner', $files[0]);
        $this->assertArrayHasKey('sha256', $files[0]);

        $snapshot = $service->reconciliationSnapshot($this->tempDir);
        $comparison = $service->compareSnapshots($snapshot, $snapshot);
        $this->assertTrue((bool) ($comparison['equal'] ?? false));

        $restore = $service->restoreBackup((string) $backup['path'], true);
        $this->assertTrue((bool) ($restore['success'] ?? false));
        $this->assertTrue((bool) ($restore['dryRun'] ?? false));
    }

    public function testPrivateModuleMigrationStatusAndIdempotentBackupImport(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $this->createPrivateUser($userRepository, 'migration-m4@example.com');
        $this->tempDir = sys_get_temp_dir() . '/caramagnols-m4-migration-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);

        $backupService = new PrivateBackupService($database);
        $backup = $backupService->createBackup($this->tempDir . '/exports');
        $this->assertTrue((bool) ($backup['success'] ?? false));
        $payload = json_decode((string) file_get_contents((string) $backup['path']), true);
        $this->assertIsArray($payload);

        $migrationService = new PrivateMigrationService($database, new PrivateModuleRegistry());
        $initialStatus = $migrationService->moduleStatus('dashboard');
        $this->assertTrue((bool) ($initialStatus['success'] ?? false));
        $this->assertSame('php_source', $initialStatus['status'] ?? null);
        $this->assertFalse($migrationService->canDoubleWrite('dashboard'));

        $migratingStatus = $migrationService->setModuleStatus('dashboard', 'migrating', 'phpunit', 'M4 test');
        $this->assertTrue((bool) ($migratingStatus['success'] ?? false));
        $this->assertTrue($migrationService->canDoubleWrite('dashboard'));

        $dryRunImport = $migrationService->importBackupModule('dashboard', $payload, true);
        $this->assertTrue((bool) ($dryRunImport['success'] ?? false));
        $this->assertTrue((bool) ($dryRunImport['dryRun'] ?? false));
        $this->assertGreaterThanOrEqual(1, (int) ($dryRunImport['rows'] ?? 0));

        $appliedImport = $migrationService->importBackupModule('dashboard', $payload, false);
        $this->assertTrue((bool) ($appliedImport['success'] ?? false));
        $this->assertFalse((bool) ($appliedImport['dryRun'] ?? true));

        $legacyRead = $migrationService->readLegacyModel('dashboard');
        $this->assertTrue((bool) ($legacyRead['success'] ?? false));
        $tables = is_array($legacyRead['tables'] ?? null) ? $legacyRead['tables'] : [];
        $this->assertGreaterThanOrEqual(1, (int) ($tables['private_users']['rows'] ?? 0));

        $newSourceStatus = $migrationService->setModuleStatus('dashboard', 'new_source', 'phpunit', 'M4 done');
        $this->assertTrue((bool) ($newSourceStatus['success'] ?? false));
        $this->assertFalse($migrationService->canDoubleWrite('dashboard'));

        $invalidStatus = $migrationService->setModuleStatus('dashboard', 'always_double_write', 'phpunit');
        $this->assertFalse((bool) ($invalidStatus['success'] ?? true));
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    private function removeTempDir(): void
    {
        if ($this->tempDir === '' || !is_dir($this->tempDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($this->tempDir);
        $this->tempDir = '';
    }
}
