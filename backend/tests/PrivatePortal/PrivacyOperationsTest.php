<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Operations\PrivateBackupService;
use Caramagnols\PrivatePortal\Operations\PrivateDataProtectionService;
use Caramagnols\PrivatePortal\Operations\PrivateMigrationService;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\Database\EditorialDatabase;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class PrivacyOperationsTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $tempDir = '';
    /** @var array<int, string> */
    private array $deletionBackupPaths = [];
    /** @var array<int, string> */
    private array $privateFilePaths = [];

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        $this->removeDeletionBackupArtifacts();
        $this->removePrivateFileArtifacts();
        $this->removeTempDir();
    }

    public function testGdprExportDoesNotExposePasswordHashAndAnonymizeDisablesAccount(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $service = new PrivateDataProtectionService($database);
        $userId = $this->createPrivateUser($userRepository, 'privacy-export@example.com');
        $this->assertTrue($userRepository->updateMemberProfile($userId, 'Pauline Bergon', 'Cogolin', '+33 6 12 34 56 78'));

        $export = $service->exportAccount($userId);
        $privateUser = is_array($export['privateUser'] ?? null) ? $export['privateUser'] : [];

        $this->assertSame('privacy-export@example.com', $privateUser['email'] ?? null);
        $this->assertSame('Pauline Bergon', $privateUser['fullName'] ?? null);
        $this->assertSame('Cogolin', $privateUser['postalAddress'] ?? null);
        $this->assertSame('+33 6 12 34 56 78', $privateUser['phone'] ?? null);
        $this->assertArrayNotHasKey('passwordHash', $privateUser);
        $this->assertArrayNotHasKey('password_hash', $privateUser);

        $this->assertTrue($service->anonymizeAccount($userId, $userId, 'rgpd test'));
        $user = $userRepository->findById($userId);
        $this->assertIsArray($user);
        $this->assertSame('deleted', $user['status'] ?? null);
        $this->assertSame('deleted+' . $userId . '@anonymous.invalid', $user['email'] ?? null);
        $this->assertNull($user['full_name'] ?? null);
        $this->assertNull($user['postal_address'] ?? null);
        $this->assertNull($user['phone'] ?? null);
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

    public function testSuspendedAccountDeletionCreatesBackupAndPurgesPrivateData(): void
    {
        $database = $this->editorialSqlDatabase();
        $fixture = $this->createSuspendedDeletionFixture($database, 'privacy-c2-delete@example.com');
        $service = new PrivateDataProtectionService($database);

        $deletion = $service->deleteSuspendedAccountWithBackup($fixture['userId'], 'phpunit-c2', 30);
        $this->assertTrue((bool) ($deletion['success'] ?? false));
        $backupPath = (string) ($deletion['backupPath'] ?? '');
        $this->assertFileExists($backupPath);
        $this->trackDeletionBackup($backupPath);

        $payload = $this->deletionBackupPayload($backupPath);
        $this->assertSame($fixture['userId'], (int) ($payload['privateUserId'] ?? 0));
        $this->assertSame(30, (int) ($payload['retentionDays'] ?? 0));
        $this->assertNotEmpty($payload['deleteAfter'] ?? null);
        $this->assertSame('privacy-c2-delete@example.com', $payload['tables']['private_users'][0]['email'] ?? null);
        $this->assertSame('Compte C2', $payload['tables']['private_users'][0]['fullName'] ?? null);
        $this->assertCount(1, $payload['tables']['private_documents'] ?? []);
        $this->assertCount(1, $payload['files'] ?? []);
        $this->assertTrue((bool) ($payload['files'][0]['exists'] ?? false));

        $this->assertSame(0, $this->countRows($database, 'private_documents', '`private_user_id` = :user_id', ['user_id' => $fixture['userId']]));
        $this->assertSame(0, $this->countRows($database, 'private_document_categories', '`private_user_id` = :user_id', ['user_id' => $fixture['userId']]));
        $this->assertSame(0, $this->countRows($database, 'private_user_module_permissions', '`private_user_id` = :user_id', ['user_id' => $fixture['userId']]));
        $this->assertFileDoesNotExist($fixture['documentPath']);

        $userRepository = new PrivateUserRepository($database);
        $user = $userRepository->findById($fixture['userId']);
        $this->assertIsArray($user);
        $this->assertSame('suspended', $user['status'] ?? null);
        $this->assertNull($user['full_name'] ?? null);
        $this->assertNull($user['postal_address'] ?? null);
        $this->assertNull($user['phone'] ?? null);

        $secondDeletion = $service->deleteSuspendedAccountWithBackup($fixture['userId'], 'phpunit-c2', 30);
        $this->assertTrue((bool) ($secondDeletion['success'] ?? false));
        $this->assertSame($backupPath, (string) ($secondDeletion['backupPath'] ?? ''));
    }

    public function testDeletionCronDryRunsAndFinalPurgeAreScopedAndIdempotent(): void
    {
        $database = $this->editorialSqlDatabase();
        $fixture = $this->createSuspendedDeletionFixture($database, 'privacy-c2-cron@example.com');
        $service = new PrivateDataProtectionService($database);

        $deletion = $service->deleteSuspendedAccountWithBackup($fixture['userId'], 'phpunit-c2', 30);
        $this->assertTrue((bool) ($deletion['success'] ?? false));
        $backupPath = (string) ($deletion['backupPath'] ?? '');
        $this->trackDeletionBackup($backupPath);
        $payload = $this->deletionBackupPayload($backupPath);
        $generatedAt = strtotime((string) ($payload['generatedAt'] ?? ''));
        $deleteAfter = strtotime((string) ($payload['deleteAfter'] ?? ''));
        $this->assertNotFalse($generatedAt);
        $this->assertNotFalse($deleteAfter);

        $warningDryRun = $service->sendPendingDeletionWarnings(true, ((int) $generatedAt) + (20 * 86400), 20, $fixture['userId']);
        $this->assertSame(1, (int) ($warningDryRun['matched'] ?? 0));
        $this->assertSame(0, (int) ($warningDryRun['sent'] ?? 0));
        $this->assertTrue((bool) ($warningDryRun['dry_run'] ?? false));

        $warningSecondDryRun = $service->sendPendingDeletionWarnings(true, ((int) $generatedAt) + (20 * 86400), 20, $fixture['userId']);
        $this->assertSame(1, (int) ($warningSecondDryRun['matched'] ?? 0));
        $this->assertSame(0, (int) ($warningSecondDryRun['sent'] ?? 0));

        $purgeDryRun = $service->cleanupExpiredDeletionBackups(true, (int) $deleteAfter, $fixture['userId']);
        $this->assertSame(1, (int) ($purgeDryRun['matched'] ?? 0));
        $this->assertSame(0, (int) ($purgeDryRun['purged'] ?? 0));
        $this->assertFileExists($backupPath);

        $purge = $service->cleanupExpiredDeletionBackups(false, (int) $deleteAfter, $fixture['userId']);
        $this->assertSame(1, (int) ($purge['matched'] ?? 0));
        $this->assertSame(1, (int) ($purge['purged'] ?? 0));
        $this->assertGreaterThanOrEqual(1, (int) ($purge['backup_deleted'] ?? 0));
        $this->assertFileDoesNotExist($backupPath);

        $userRepository = new PrivateUserRepository($database);
        $this->assertNull($userRepository->findById($fixture['userId']));

        $secondPurge = $service->cleanupExpiredDeletionBackups(false, (int) $deleteAfter, $fixture['userId']);
        $this->assertSame(0, (int) ($secondPurge['matched'] ?? 0));
        $this->assertSame(0, (int) ($secondPurge['purged'] ?? 0));
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    /**
     * @return array{userId: int, documentPath: string}
     */
    private function createSuspendedDeletionFixture(EditorialDatabase $database, string $email): array
    {
        $userRepository = new PrivateUserRepository($database);
        $userId = $this->createPrivateUser($userRepository, $email);
        $this->assertTrue($userRepository->updateMemberProfile($userId, 'Compte C2', 'Adresse C2', '+33 6 00 00 00 00'));

        $permissionRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $this->assertTrue($permissionRepository->setUserModules($userId, ['dashboard', 'documents'], 'phpunit'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $documentRepository = new PrivateDocumentRepository($database);
        $category = $documentRepository->createCategory($userId, 'Documents C2');
        $this->assertIsArray($category);

        if ($this->tempDir === '') {
            $this->tempDir = sys_get_temp_dir() . '/caramagnols-c2-deletion-' . bin2hex(random_bytes(6));
            mkdir($this->tempDir, 0700, true);
        }
        $tmpPath = $this->tempDir . '/preuve-c2.txt';
        file_put_contents($tmpPath, 'preuve suppression compte suspendu C2');

        $documentId = 'phpunit-c2-' . bin2hex(random_bytes(6));
        $stored = $storage->storeUploadedFile(
            [
                'tmpPath' => $tmpPath,
                'originalName' => 'preuve-c2.txt',
                'extension' => 'txt',
                'mimeType' => 'text/plain',
                'sizeBytes' => (int) filesize($tmpPath),
            ],
            $documentId
        );
        $this->assertIsArray($stored, 'Stockage document impossible: ' . (string) $storage->uploadError());

        $documentPath = $storage->absolutePath((string) $stored['storagePath']);
        $this->assertIsString($documentPath);
        $this->assertFileExists($documentPath);
        $this->privateFilePaths[] = $documentPath;

        $document = $documentRepository->create(
            $userId,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $userId,
            (int) ($category['id'] ?? 0)
        );
        $this->assertIsArray($document);

        $this->assertTrue($userRepository->updateStatus($userId, 'suspended'));

        return [
            'userId' => $userId,
            'documentPath' => $documentPath,
        ];
    }

    /**
     * @param array<string, int|string|null> $params
     */
    private function countRows(EditorialDatabase $database, string $table, string $where, array $params): int
    {
        $statement = $database->pdo()->prepare(
            sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $database->table($table), $where)
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array<string, mixed>
     */
    private function deletionBackupPayload(string $path): array
    {
        $payload = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($payload);

        return $payload;
    }

    private function trackDeletionBackup(string $path): void
    {
        if ($path === '') {
            return;
        }

        $this->deletionBackupPaths[] = $path;
        $zipPath = preg_replace('/\.json\z/i', '.zip', $path);
        if (is_string($zipPath)) {
            $this->deletionBackupPaths[] = $zipPath;
        }
    }

    private function removeDeletionBackupArtifacts(): void
    {
        foreach (array_unique($this->deletionBackupPaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->deletionBackupPaths = [];
    }

    private function removePrivateFileArtifacts(): void
    {
        foreach (array_unique($this->privateFilePaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->privateFilePaths = [];
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
