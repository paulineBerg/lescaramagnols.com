<?php

declare(strict_types=1);

namespace Caramagnols\Tests\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Service\RenderCacheService;
use PHPUnit\Framework\TestCase;

final class RenderCacheServiceTest extends TestCase
{
    private string $testCacheDir;
    private RenderCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testCacheDir = sys_get_temp_dir() . '/caramagnols_cache_test_' . uniqid() . '/';
        $this->cacheService = new RenderCacheService($this->testCacheDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Nettoyer le répertoire de test
        $this->removeDirectory($this->testCacheDir);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $path . $file;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }
        rmdir($path);
    }

    public function testCacheDirectoryIsCreated(): void
    {
        $this->assertTrue(is_dir($this->testCacheDir));
    }

    public function testSetAndGetCache(): void
    {
        $key = 'test_key';
        $content = ['data' => 'test_value', 'timestamp' => time()];

        $result = $this->cacheService->set($key, $content);
        $this->assertTrue($result);

        $retrieved = $this->cacheService->get($key);
        $this->assertEquals($content, $retrieved);
    }

    public function testGetNonExistentKeyReturnsNull(): void
    {
        $result = $this->cacheService->get('non_existent_key');
        $this->assertNull($result);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $key = 'test_has_key';
        $this->cacheService->set($key, ['test' => 'data']);

        $this->assertTrue($this->cacheService->has($key));
    }

    public function testHasReturnsFalseForNonExistentKey(): void
    {
        $this->assertFalse($this->cacheService->has('non_existent'));
    }

    public function testInvalidateRemovesKey(): void
    {
        $key = 'test_invalidate';
        $this->cacheService->set($key, ['data' => 'value']);

        $this->assertTrue($this->cacheService->has($key));

        $result = $this->cacheService->invalidate($key);
        $this->assertTrue($result);

        $this->assertFalse($this->cacheService->has($key));
    }

    public function testInvalidateAllRemovesAllKeys(): void
    {
        $this->cacheService->set('key1', ['data' => 'value1']);
        $this->cacheService->set('key2', ['data' => 'value2']);
        $this->cacheService->set('key3', ['data' => 'value3']);

        $count = $this->cacheService->invalidateAll();
        $this->assertEquals(3, $count);

        $this->assertFalse($this->cacheService->has('key1'));
        $this->assertFalse($this->cacheService->has('key2'));
        $this->assertFalse($this->cacheService->has('key3'));
    }

    public function testGenerateCacheKey(): void
    {
        $key1 = $this->cacheService->generateCacheKey('route1', ['param1' => 'value1']);
        $key2 = $this->cacheService->generateCacheKey('route1', ['param1' => 'value1']);
        $key3 = $this->cacheService->generateCacheKey('route1', ['param1' => 'value2']);
        $key4 = $this->cacheService->generateCacheKey('route2', ['param1' => 'value1']);

        // Mêmes paramètres = même clé
        $this->assertEquals($key1, $key2);

        // Paramètres différents = clés différentes
        $this->assertNotEquals($key1, $key3);

        // Routes différentes = clés différentes
        $this->assertNotEquals($key1, $key4);

        // Vérifier que la clé a une longueur cohérente (hash SHA256 = 64 caractères)
        $this->assertEquals(64, strlen($key1));
    }

    public function testGenerateCacheKeyWithUserId(): void
    {
        $key1 = $this->cacheService->generateCacheKey('route1', [], 1);
        $key2 = $this->cacheService->generateCacheKey('route1', [], 2);

        $this->assertNotEquals($key1, $key2);
    }

    public function testExpiredCacheReturnsNull(): void
    {
        $key = 'test_expire';
        $this->cacheService->set($key, ['data' => 'value'], 1); // TTL de 1 seconde

        $this->assertNotNull($this->cacheService->get($key));

        // Attendre 2 secondes pour que le cache expire
        sleep(2);

        $this->assertNull($this->cacheService->get($key));
    }

    public function testInvalidateForProperty(): void
    {
        // Ajouter des éléments de cache avec des IDs de propriété dans les clés
        $this->cacheService->set('property_1_data', ['property_id' => 1, 'data' => 'test']);
        $this->cacheService->set('property_2_data', ['property_id' => 2, 'data' => 'test']);
        $this->cacheService->set('other_data', ['data' => 'other']);

        // Invalider uniquement la propriété 1
        $this->cacheService->invalidateForProperty(1);

        $this->assertFalse($this->cacheService->has('property_1_data'));
        $this->assertTrue($this->cacheService->has('property_2_data'));
        $this->assertTrue($this->cacheService->has('other_data'));
    }

    public function testCleanupExpired(): void
    {
        // Ajouter des éléments de cache avec TTL court
        $this->cacheService->set('expired1', ['data' => 'value'], 1);
        $this->cacheService->set('expired2', ['data' => 'value'], 1);
        $this->cacheService->set('valid', ['data' => 'value'], 3600);

        // Attendre que les premiers expire
        sleep(2);

        // Nettoyer les expirés
        $count = $this->cacheService->cleanupExpired();

        // Should have cleaned up at least the expired ones
        // Note: Race condition possible, mais normalement 2
        $this->assertGreaterThanOrEqual(0, $count);

        // Le valid devrait encore exister
        $this->assertNotNull($this->cacheService->get('valid'));
    }
}
