<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\Storage;

use Caramagnols\PrivateApps\Documents\PrivateDocumentStorage;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;
use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use PHPUnit\Framework\TestCase;

final class RuntimeStorageFailFastTest extends TestCase
{
    private array $previousConfig = [];
    private string $tempRoot = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
    }

    protected function setUp(): void
    {
        global $appConfig;
        $this->previousConfig = is_array($appConfig ?? null) ? $appConfig : [];
        $this->tempRoot = sys_get_temp_dir() . '/runtime-storage-fail-fast-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRoot);

        global $appConfig;
        $appConfig = $this->previousConfig;
    }

    public function testPrivateDocumentsRejectEmptyRootInProduction(): void
    {
        $this->useProductionEnvironment();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PRIVATE_STORAGE_ROOT');

        new PrivateDocumentStorage('', 'storage', 'uploads', 'exports');
    }

    public function testPrivateDocumentsRejectRootPathStorageInProduction(): void
    {
        $this->useProductionEnvironment();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ROOT_PATH');

        new PrivateDocumentStorage(ROOT_PATH . '/private', 'storage', 'uploads', 'exports');
    }

    public function testDocumentHubRejectsMissingProductionRoot(): void
    {
        $this->useProductionEnvironment();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('absent');

        new DocumentStorageService($this->tempRoot . '/missing-document-hub');
    }

    public function testFamilyDiscussionRejectsRootPathStorageInProduction(): void
    {
        $this->useProductionEnvironment();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ROOT_PATH');

        new DiscussionAttachmentStorage(ROOT_PATH . '/private', storageDirectory: 'storage');
    }

    public function testProductionRuntimeStorageOutsideRootPathIsAccepted(): void
    {
        $this->useProductionEnvironment();
        mkdir($this->tempRoot . '/private-storage/uploads', 0770, true);
        mkdir($this->tempRoot . '/private-storage/document-hub', 0770, true);
        mkdir($this->tempRoot . '/private-storage/family-discussion', 0770, true);

        $privateStorage = new PrivateDocumentStorage(
            $this->tempRoot,
            'private-storage',
            'uploads',
            'exports'
        );
        $hubStorage = new DocumentStorageService($this->tempRoot . '/private-storage/document-hub');
        $discussionStorage = new DiscussionAttachmentStorage(
            $this->tempRoot,
            storageDirectory: 'private-storage'
        );

        self::assertFalse($privateStorage->isLegacyMode());
        self::assertFalse($hubStorage->isLegacyMode());
        self::assertFalse($discussionStorage->isLegacyMode());
    }

    private function useProductionEnvironment(): void
    {
        global $appConfig;
        $appConfig['env'] = 'production';
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }

            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
