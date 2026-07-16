<?php

declare(strict_types=1);

use Caramagnols\Social\InstagramFeedService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class InstagramFeedServiceTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = ROOT_PATH . '/var/instagram-feed-cache-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function testResolveFeedReturnsConfigErrorWhenTokenIsMissing(): void
    {
        $service = new InstagramFeedService(
            $this->cacheFile,
            static fn (string $url, int $timeout): ?array => null,
            static fn (): int => 1700000000
        );

        $result = $service->resolveFeed([
            'enabled' => true,
            'username' => 'paulineetnoel',
            'access_token' => '',
        ]);

        $this->assertTrue((bool) ($result['enabled'] ?? false));
        $this->assertSame('config-error', $result['source'] ?? null);
        $this->assertSame('Token Instagram manquant.', $result['error'] ?? null);
        $this->assertSame([], $result['posts'] ?? null);
    }

    public function testResolveFeedUsesFreshCacheWithoutApiCall(): void
    {
        $accessToken = 'cached-token';
        $fingerprint = hash('sha256', $accessToken . '||');
        file_put_contents(
            $this->cacheFile,
            json_encode(
                [
                    'fingerprint' => $fingerprint,
                    'fetched_at' => 1700000100,
                    'username' => 'paulineetnoel',
                    'posts' => [
                        [
                            'id' => '1789',
                            'caption' => 'Post en cache',
                            'imageUrl' => 'https://cdn.example.test/image.webp',
                            'permalink' => 'https://www.instagram.com/p/cache',
                            'mediaType' => 'IMAGE',
                            'timestamp' => '2026-03-18T10:00:00+00:00',
                        ],
                    ],
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $apiCallCount = 0;
        $service = new InstagramFeedService(
            $this->cacheFile,
            static function (string $url, int $timeout) use (&$apiCallCount): ?array {
                $apiCallCount++;
                return null;
            },
            static fn (): int => 1700000200
        );

        $result = $service->resolveFeed([
            'enabled' => true,
            'access_token' => $accessToken,
            'limit' => 6,
            'cache_ttl_seconds' => 1800,
        ]);

        $this->assertSame(0, $apiCallCount);
        $this->assertSame('cache', $result['source'] ?? null);
        $this->assertSame('paulineetnoel', $result['username'] ?? null);
        $this->assertCount(1, $result['posts'] ?? []);
    }

    public function testResolveFeedFetchesApiAndPersistsCache(): void
    {
        $apiCallCount = 0;
        $service = new InstagramFeedService(
            $this->cacheFile,
            static function (string $url, int $timeout) use (&$apiCallCount): ?array {
                $apiCallCount++;

                return [
                    'status' => 200,
                    'body' => json_encode(
                        [
                            'data' => [
                                [
                                    'id' => 'abcd',
                                    'caption' => 'Post API',
                                    'media_type' => 'IMAGE',
                                    'media_url' => 'https://cdn.example.test/post.webp',
                                    'thumbnail_url' => '',
                                    'permalink' => 'https://www.instagram.com/p/abcd',
                                    'timestamp' => '2026-03-18T11:00:00+00:00',
                                    'username' => 'Compte.API',
                                ],
                            ],
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ];
            },
            static fn (): int => 1700000300
        );

        $result = $service->resolveFeed([
            'enabled' => true,
            'access_token' => 'api-token',
            'limit' => 5,
            'cache_ttl_seconds' => 1800,
        ]);

        $this->assertSame(1, $apiCallCount);
        $this->assertSame('api', $result['source'] ?? null);
        $this->assertNull($result['error'] ?? null);
        $this->assertCount(1, $result['posts'] ?? []);
        $this->assertSame('Compte.API', $result['username'] ?? null);
        $this->assertFileExists($this->cacheFile);

        $cache = json_decode((string) file_get_contents($this->cacheFile), true);
        $this->assertIsArray($cache);
        $this->assertSame('Compte.API', $cache['username'] ?? null);
        $this->assertCount(1, $cache['posts'] ?? []);
    }

    public function testProbeReturnsSuccessForValidApiCredentials(): void
    {
        $service = new InstagramFeedService(
            $this->cacheFile,
            static fn (string $url, int $timeout): ?array => [
                'status' => 200,
                'body' => json_encode(
                    [
                        'data' => [
                            [
                                'id' => 'p1',
                                'caption' => 'Probe',
                                'media_type' => 'IMAGE',
                                'media_url' => 'https://cdn.example.test/probe.webp',
                                'thumbnail_url' => '',
                                'permalink' => 'https://www.instagram.com/p/probe/',
                                'timestamp' => '2026-03-18T12:00:00+00:00',
                                'username' => 'probe_account',
                            ],
                        ],
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ],
            static fn (): int => 1700000500
        );

        $probe = $service->probe([
            'access_token' => 'probe-token',
            'limit' => 3,
        ]);

        $this->assertTrue((bool) ($probe['success'] ?? false));
        $this->assertSame('probe_account', $probe['username'] ?? null);
        $this->assertSame(1, $probe['postCount'] ?? null);
        $this->assertNull($probe['error'] ?? null);
    }
}
