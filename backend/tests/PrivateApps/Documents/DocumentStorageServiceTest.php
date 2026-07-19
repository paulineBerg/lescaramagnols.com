<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\Documents;

use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;
use PHPUnit\Framework\TestCase;

final class DocumentStorageServiceTest extends TestCase
{
    private string $rootDirectory = '';

    protected function setUp(): void
    {
        $this->rootDirectory = sys_get_temp_dir() . '/doc-hub-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDirectory);
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
            if ($item instanceof \SplFileInfo) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }

    public function testDirectoriesAreCreated(): void
    {
        $storage = new DocumentStorageService($this->rootDirectory);
        self::assertDirectoryExists($storage->quarantineDirectory());
        self::assertDirectoryExists($this->rootDirectory . '/objects/sha256');
        self::assertDirectoryExists($storage->exportsTempDirectory());
        self::assertDirectoryExists($storage->restoreTempDirectory());
    }

    public function testQuarantineThenAtomicPromotion(): void
    {
        $storage = new DocumentStorageService($this->rootDirectory);
        $source = $this->rootDirectory . '/incoming.bin';
        file_put_contents($source, 'contenu original');
        $expectedHash = hash('sha256', 'contenu original');

        $quarantined = $storage->moveToQuarantine($source, false);
        self::assertNotNull($quarantined);
        self::assertFileDoesNotExist($source);
        self::assertStringStartsWith($storage->quarantineDirectory() . '/', (string) $quarantined);

        $hash = $storage->sha256File((string) $quarantined);
        self::assertSame($expectedHash, $hash);

        $storageKey = $storage->promoteFromQuarantine((string) $quarantined, (string) $hash);
        self::assertSame($storage->storageKeyForHash($expectedHash), $storageKey);
        self::assertFileDoesNotExist((string) $quarantined);

        $absolute = $storage->absolutePathForKey((string) $storageKey);
        self::assertNotNull($absolute);
        self::assertSame('contenu original', file_get_contents((string) $absolute));
    }

    public function testConcurrentSameContentNeverOverwritesOriginal(): void
    {
        $storage = new DocumentStorageService($this->rootDirectory);
        $hash = hash('sha256', 'même contenu');

        foreach ([1, 2] as $attempt) {
            $source = $this->rootDirectory . '/upload-' . $attempt . '.bin';
            file_put_contents($source, 'même contenu');
            $quarantined = $storage->moveToQuarantine($source, false);
            $storageKey = $storage->promoteFromQuarantine((string) $quarantined, $hash);
            self::assertSame($storage->storageKeyForHash($hash), $storageKey);
        }

        $absolute = $storage->absolutePathForKey($storage->storageKeyForHash($hash));
        self::assertSame('même contenu', file_get_contents((string) $absolute));

        $quarantineFiles = glob($storage->quarantineDirectory() . '/*') ?: [];
        self::assertSame([], $quarantineFiles, 'la quarantaine doit être vide après promotion/déduplication');
    }

    public function testPathTraversalIsImpossible(): void
    {
        $storage = new DocumentStorageService($this->rootDirectory);
        self::assertNull($storage->absolutePathForKey('../secrets'));
        self::assertNull($storage->absolutePathForKey('objects/sha256/../../../../etc/passwd'));
        self::assertNull($storage->absolutePathForKey('objects/sha256/ab/cd/nothash'));
        self::assertNull($storage->absolutePathForKey('objects/sha256/AB/CD/' . str_repeat('A', 64)));
    }

    public function testPromotionRejectsForeignPaths(): void
    {
        $storage = new DocumentStorageService($this->rootDirectory);
        $outside = $this->rootDirectory . '/outside.bin';
        file_put_contents($outside, 'x');

        self::assertNull($storage->promoteFromQuarantine($outside, hash('sha256', 'x')));
        self::assertFileExists($outside);
    }

    public function testQuarantinePurgeOnlyRemovesExpiredFiles(): void
    {
        $storage = new DocumentStorageService($this->rootDirectory);
        $oldFile = $storage->quarantineDirectory() . '/old.tmp';
        $recentFile = $storage->quarantineDirectory() . '/recent.tmp';
        file_put_contents($oldFile, 'vieux');
        file_put_contents($recentFile, 'récent');
        touch($oldFile, time() - 172800);

        $purged = $storage->purgeQuarantine(86400);

        self::assertSame(1, $purged);
        self::assertFileDoesNotExist($oldFile);
        self::assertFileExists($recentFile);
    }
}
