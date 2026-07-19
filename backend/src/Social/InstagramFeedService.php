<?php

declare(strict_types=1);

namespace Caramagnols\Social;

final class InstagramFeedService
{
    /** @var callable(string, int): array{status: int, body: string}|null */
    private $httpGetter;

    /** @var callable(): int */
    private $clock;

    public function __construct(
        private readonly string $cachePath = ROOT_PATH . '/var/cache/instagram-feed.json',
        ?callable $httpGetter = null,
        ?callable $clock = null
    ) {
        $this->httpGetter = $httpGetter;
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<string, mixed> $rawConfig
     * @return array{
     *   enabled: bool,
     *   username: string,
     *   profileUrl: string,
     *   posts: array<int, array{
     *     id: string,
     *     caption: string,
     *     imageUrl: string,
     *     permalink: string,
     *     mediaType: string,
     *     timestamp: string
     *   }>,
     *   rotationIntervalMs: int,
     *   source: string,
     *   error: string|null
     * }
     */
    public function resolveFeed(array $rawConfig): array
    {
        $config = $this->normalizeConfig($rawConfig);
        $profileUrl = $config['username'] !== '' ? sprintf('https://www.instagram.com/%s/', $config['username']) : '';

        if (!$config['enabled']) {
            return [
                'enabled' => false,
                'username' => $config['username'],
                'profileUrl' => $profileUrl,
                'posts' => [],
                'rotationIntervalMs' => $config['rotationIntervalMs'],
                'source' => 'disabled',
                'error' => null,
            ];
        }

        if ($config['accessToken'] === '') {
            return [
                'enabled' => true,
                'username' => $config['username'],
                'profileUrl' => $profileUrl,
                'posts' => [],
                'rotationIntervalMs' => $config['rotationIntervalMs'],
                'source' => 'config-error',
                'error' => 'Token Instagram manquant.',
            ];
        }

        $fingerprint = hash('sha256', $config['accessToken'] . '|' . $config['userId'] . '|' . $config['username']);
        $cached = $this->readCache();
        $cacheIsFresh = $this->cacheIsFresh($cached, $fingerprint, $config['cacheTtlSeconds']);

        if ($cacheIsFresh) {
            return [
                'enabled' => true,
                'username' => $this->resolveUsername($config['username'], $cached),
                'profileUrl' => $profileUrl !== '' ? $profileUrl : $this->profileUrlFromCache($cached),
                'posts' => $this->slicePostsFromCache($cached, $config['limit']),
                'rotationIntervalMs' => $config['rotationIntervalMs'],
                'source' => 'cache',
                'error' => null,
            ];
        }

        $apiResult = $this->fetchFromApi($config);
        if ($apiResult['error'] === null) {
            $username = $this->normalizeUsername((string) ($apiResult['username'] ?? $config['username']));
            $cachePayload = [
                'fingerprint' => $fingerprint,
                'fetched_at' => ($this->clock)(),
                'username' => $username,
                'posts' => $apiResult['posts'],
            ];
            $this->writeCache($cachePayload);

            return [
                'enabled' => true,
                'username' => $username,
                'profileUrl' => $username !== '' ? sprintf('https://www.instagram.com/%s/', $username) : $profileUrl,
                'posts' => array_slice($apiResult['posts'], 0, $config['limit']),
                'rotationIntervalMs' => $config['rotationIntervalMs'],
                'source' => 'api',
                'error' => null,
            ];
        }

        if ($this->hasCachedPosts($cached)) {
            return [
                'enabled' => true,
                'username' => $this->resolveUsername($config['username'], $cached),
                'profileUrl' => $profileUrl !== '' ? $profileUrl : $this->profileUrlFromCache($cached),
                'posts' => $this->slicePostsFromCache($cached, $config['limit']),
                'rotationIntervalMs' => $config['rotationIntervalMs'],
                'source' => 'stale-cache',
                'error' => null,
            ];
        }

        return [
            'enabled' => true,
            'username' => $config['username'],
            'profileUrl' => $profileUrl,
            'posts' => [],
            'rotationIntervalMs' => $config['rotationIntervalMs'],
            'source' => 'api-error',
            'error' => $apiResult['error'],
        ];
    }

    /**
     * @param array<string, mixed> $rawConfig
     * @return array{success: bool, username: string, postCount: int, error: string|null}
     */
    public function probe(array $rawConfig): array
    {
        $config = $this->normalizeConfig($rawConfig);
        if ($config['accessToken'] === '') {
            return [
                'success' => false,
                'username' => $config['username'],
                'postCount' => 0,
                'error' => 'Token Instagram manquant.',
            ];
        }

        $apiResult = $this->fetchFromApi($config);
        if ($apiResult['error'] !== null) {
            return [
                'success' => false,
                'username' => $config['username'],
                'postCount' => 0,
                'error' => $apiResult['error'],
            ];
        }

        $username = $this->normalizeUsername((string) ($apiResult['username'] ?? $config['username']));

        return [
            'success' => true,
            'username' => $username,
            'postCount' => count($apiResult['posts']),
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{posts: array<int, array{id: string, caption: string, imageUrl: string, permalink: string, mediaType: string, timestamp: string}>, username: string, error: string|null}
     */
    private function fetchFromApi(array $config): array
    {
        $baseUrl = rtrim((string) ($config['apiBaseUrl'] ?? 'https://graph.instagram.com'), '/');
        $path = $config['userId'] !== ''
            ? '/' . rawurlencode((string) $config['userId']) . '/media'
            : '/me/media';
        $url = $baseUrl . $path . '?' . http_build_query(
            [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,username',
                'limit' => $config['limit'],
                'access_token' => $config['accessToken'],
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        $response = $this->httpGet($url, (int) $config['timeoutSeconds']);
        if ($response === null) {
            return ['posts' => [], 'username' => '', 'error' => 'Instagram API inaccessible.'];
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return ['posts' => [], 'username' => '', 'error' => 'Réponse Instagram invalide.'];
        }

        if ($response['status'] >= 400 || is_array($decoded['error'] ?? null)) {
            $apiMessage = is_array($decoded['error'] ?? null)
                ? trim((string) ($decoded['error']['message'] ?? ''))
                : '';
            $message = $apiMessage !== '' ? $apiMessage : 'Erreur de récupération Instagram.';

            return ['posts' => [], 'username' => '', 'error' => $message];
        }

        $items = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        $posts = [];
        $username = '';

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $permalink = trim((string) ($item['permalink'] ?? ''));
            $mediaType = strtoupper(trim((string) ($item['media_type'] ?? '')));
            $mediaUrl = trim((string) ($item['media_url'] ?? ''));
            $thumbnailUrl = trim((string) ($item['thumbnail_url'] ?? ''));
            $imageUrl = $mediaType === 'VIDEO' && $thumbnailUrl !== '' ? $thumbnailUrl : $mediaUrl;

            if ($permalink === '' || $imageUrl === '') {
                continue;
            }

            if ($username === '') {
                $username = $this->normalizeUsername((string) ($item['username'] ?? ''));
            }

            $caption = $this->normalizeCaption((string) ($item['caption'] ?? ''));
            $timestamp = trim((string) ($item['timestamp'] ?? ''));
            $identifier = trim((string) ($item['id'] ?? ''));
            if ($identifier === '') {
                $identifier = hash('sha1', $permalink . '|' . $imageUrl);
            }

            $posts[] = [
                'id' => $identifier,
                'caption' => $caption,
                'imageUrl' => $imageUrl,
                'permalink' => $permalink,
                'mediaType' => $mediaType !== '' ? $mediaType : 'IMAGE',
                'timestamp' => $timestamp,
            ];
        }

        return [
            'posts' => array_slice($posts, 0, (int) $config['limit']),
            'username' => $username,
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $cached
     */
    private function hasCachedPosts(?array $cached): bool
    {
        return is_array($cached)
            && is_array($cached['posts'] ?? null)
            && $cached['posts'] !== [];
    }

    /**
     * @param array<string, mixed>|null $cached
     */
    private function cacheIsFresh(?array $cached, string $fingerprint, int $ttl): bool
    {
        if (!is_array($cached)) {
            return false;
        }

        $cachedFingerprint = trim((string) ($cached['fingerprint'] ?? ''));
        if ($cachedFingerprint === '' || !hash_equals($cachedFingerprint, $fingerprint)) {
            return false;
        }

        $fetchedAt = (int) ($cached['fetched_at'] ?? 0);
        if ($fetchedAt <= 0) {
            return false;
        }

        return (($this->clock)() - $fetchedAt) <= $ttl;
    }

    /**
     * @param array<string, mixed>|null $cached
     * @return array<int, array{id: string, caption: string, imageUrl: string, permalink: string, mediaType: string, timestamp: string}>
     */
    private function slicePostsFromCache(?array $cached, int $limit): array
    {
        if (!is_array($cached) || !is_array($cached['posts'] ?? null)) {
            return [];
        }

        $normalized = [];
        foreach ($cached['posts'] as $post) {
            if (!is_array($post)) {
                continue;
            }

            $id = trim((string) ($post['id'] ?? ''));
            $caption = $this->normalizeCaption((string) ($post['caption'] ?? ''));
            $imageUrl = trim((string) ($post['imageUrl'] ?? ''));
            $permalink = trim((string) ($post['permalink'] ?? ''));
            $mediaType = strtoupper(trim((string) ($post['mediaType'] ?? 'IMAGE')));
            $timestamp = trim((string) ($post['timestamp'] ?? ''));

            if ($id === '' || $imageUrl === '' || $permalink === '') {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'caption' => $caption,
                'imageUrl' => $imageUrl,
                'permalink' => $permalink,
                'mediaType' => $mediaType !== '' ? $mediaType : 'IMAGE',
                'timestamp' => $timestamp,
            ];
        }

        return array_slice($normalized, 0, $limit);
    }

    /**
     * @param array<string, mixed>|null $cached
     */
    private function resolveUsername(string $configured, ?array $cached): string
    {
        if ($configured !== '') {
            return $configured;
        }

        if (!is_array($cached)) {
            return '';
        }

        return $this->normalizeUsername((string) ($cached['username'] ?? ''));
    }

    /**
     * @param array<string, mixed>|null $cached
     */
    private function profileUrlFromCache(?array $cached): string
    {
        if (!is_array($cached)) {
            return '';
        }

        $username = $this->normalizeUsername((string) ($cached['username'] ?? ''));
        if ($username === '') {
            return '';
        }

        return sprintf('https://www.instagram.com/%s/', $username);
    }

    private function normalizeCaption(string $caption): string
    {
        $caption = trim(preg_replace('/\s+/u', ' ', $caption) ?? $caption);
        if ($caption === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($caption, 0, 220)
            : substr($caption, 0, 220);
    }

    /**
     * @param array<string, mixed> $rawConfig
     * @return array{
     *   enabled: bool,
     *   username: string,
     *   userId: string,
     *   accessToken: string,
     *   limit: int,
     *   rotationIntervalMs: int,
     *   cacheTtlSeconds: int,
     *   timeoutSeconds: int,
     *   apiBaseUrl: string
     * }
     */
    private function normalizeConfig(array $rawConfig): array
    {
        $enabled = (bool) ($rawConfig['enabled'] ?? false);
        $username = $this->normalizeUsername((string) ($rawConfig['username'] ?? ''));
        $userId = trim((string) ($rawConfig['user_id'] ?? ''));
        $accessToken = trim((string) ($rawConfig['access_token'] ?? ''));
        $limit = $this->normalizeInteger($rawConfig['limit'] ?? 6, 6, 1, 20);
        $rotationIntervalMs = $this->normalizeInteger($rawConfig['rotation_interval_ms'] ?? 5500, 5500, 2500, 30000);
        $cacheTtlSeconds = $this->normalizeInteger($rawConfig['cache_ttl_seconds'] ?? 1800, 1800, 60, 86400);
        $timeoutSeconds = $this->normalizeInteger($rawConfig['timeout_seconds'] ?? 8, 8, 3, 20);
        $apiBaseUrl = trim((string) ($rawConfig['api_base_url'] ?? 'https://graph.instagram.com'));

        if (!preg_match('#^https?://#i', $apiBaseUrl)) {
            $apiBaseUrl = 'https://graph.instagram.com';
        }

        return [
            'enabled' => $enabled,
            'username' => $username,
            'userId' => $userId,
            'accessToken' => $accessToken,
            'limit' => $limit,
            'rotationIntervalMs' => $rotationIntervalMs,
            'cacheTtlSeconds' => $cacheTtlSeconds,
            'timeoutSeconds' => $timeoutSeconds,
            'apiBaseUrl' => $apiBaseUrl,
        ];
    }

    private function normalizeUsername(string $username): string
    {
        $username = ltrim(trim($username), '@');
        if ($username === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $username) !== 1) {
            return '';
        }

        return $username;
    }

    private function normalizeInteger(mixed $value, int $fallback, int $min, int $max): int
    {
        if (!is_scalar($value) && $value !== null) {
            return $fallback;
        }

        $intValue = filter_var((string) $value, FILTER_VALIDATE_INT);
        if ($intValue === false) {
            return $fallback;
        }

        return max($min, min($max, (int) $intValue));
    }

    /**
     * @return array{status: int, body: string}|null
     */
    private function httpGet(string $url, int $timeoutSeconds): ?array
    {
        if (is_callable($this->httpGetter)) {
            $result = ($this->httpGetter)($url, $timeoutSeconds);
            if (is_array($result)) {
                $status = isset($result['status']) ? (int) $result['status'] : 0;
                $body = isset($result['body']) ? (string) $result['body'] : '';

                return ['status' => $status, 'body' => $body];
            }

            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(3, min(20, $timeoutSeconds)),
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $headers = is_array($http_response_header) ? $http_response_header : [];
        $status = $this->httpStatusFromHeaders($headers);

        if (is_string($body) && $body !== '') {
            return ['status' => $status, 'body' => $body];
        }

        if (!function_exists('curl_init')) {
            return null;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return null;
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => max(3, min(20, $timeoutSeconds)),
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]
        );

        $curlBody = curl_exec($curl);
        $curlStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($curlBody) || $curlBody === '') {
            return null;
        }

        return ['status' => $curlStatus, 'body' => $curlBody];
    }

    /**
     * @param array<int, string> $headers
     */
    private function httpStatusFromHeaders(array $headers): int
    {
        $statusLine = $headers[0] ?? '';
        if (!is_string($statusLine)) {
            return 0;
        }

        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCache(): ?array
    {
        if (!is_file($this->cachePath)) {
            return null;
        }

        $json = @file_get_contents($this->cachePath);
        if (!is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeCache(array $payload): void
    {
        $directory = dirname($this->cachePath);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return;
        }

        @file_put_contents($this->cachePath, $json, LOCK_EX);
    }
}
