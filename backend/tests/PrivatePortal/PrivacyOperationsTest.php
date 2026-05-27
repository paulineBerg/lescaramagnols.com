<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Operations\PrivateBackupService;
use Caramagnols\PrivatePortal\Operations\PrivateDataProtectionService;
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

        $restore = $service->restoreBackup((string) $backup['path'], true);
        $this->assertTrue((bool) ($restore['success'] ?? false));
        $this->assertTrue((bool) ($restore['dryRun'] ?? false));
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
