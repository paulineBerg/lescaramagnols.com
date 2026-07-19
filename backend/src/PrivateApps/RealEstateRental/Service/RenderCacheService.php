<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivatePortal\Http\PrivatePortalController;

/**
 * Service de cache de rendu pour les pages publiques dynamiques.
 * Invalidation automatique à la publication admin.
 */
final class RenderCacheService
{
    private const CACHE_DIR = __DIR__ . '/../../../var/cache/render/';
    private const CACHE_TTL = 3600; // 1 heure par défaut

    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? self::CACHE_DIR;
        $this->ensureCacheDirectoryExists();
    }

    /**
     * Assure que le répertoire de cache existe
     */
    private function ensureCacheDirectoryExists(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Génère une clé de cache unique pour une route et ses paramètres
     */
    public function generateCacheKey(string $route, array $params = [], ?int $userId = null): string
    {
        $keyParts = [
            'route' => $route,
            'params' => $params,
        ];

        if ($userId !== null) {
            $keyParts['user_id'] = $userId;
        }

        $key = json_encode($keyParts, JSON_THROW_ON_ERROR);
        return hash('sha256', $key);
    }

    /**
     * Récupère un élément du cache
     */
    public function get(string $key): ?array
    {
        $file = $this->getCacheFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        // Vérifier l'expiration
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->invalidate($key);
            return null;
        }

        return $data['content'] ?? null;
    }

    /**
     * Stocke un élément dans le cache
     */
    public function set(string $key, array $content, int $ttl = self::CACHE_TTL): bool
    {
        $file = $this->getCacheFilePath($key);
        $data = [
            'expires_at' => time() + $ttl,
            'content' => $content,
        ];

        $json = json_encode($data, JSON_THROW_ON_ERROR);
        return file_put_contents($file, $json, LOCK_EX) !== false;
    }

    /**
     * Invalide un élément spécifique du cache
     */
    public function invalidate(string $key): bool
    {
        $file = $this->getCacheFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    /**
     * Invalide tous les éléments du cache
     */
    public function invalidateAll(): int
    {
        $count = 0;
        $files = glob($this->cacheDir . '*.json');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Invalide le cache pour une propriété spécifique
     */
    public function invalidateForProperty(int $propertyId): void
    {
        $pattern = $this->cacheDir . '*' . $propertyId . '*.json';
        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Invalide le cache pour toutes les routes liées à la location
     */
    public function invalidateRentalCache(): void
    {
        $prefix = $this->cacheDir . 'rental_';
        $files = glob($prefix . '*.json');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Vérifie si un élément est dans le cache
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Nettoie les éléments de cache expirés
     */
    public function cleanupExpired(): int
    {
        $count = 0;
        $files = glob($this->cacheDir . '*.json');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }

            if (isset($data['expires_at']) && $data['expires_at'] < time()) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Retourne le chemin du fichier de cache
     */
    private function getCacheFilePath(string $key): string
    {
        return $this->cacheDir . $key . '.json';
    }

    /**
     * Retourne le répertoire de cache
     */
    public function getCacheDirectory(): string
    {
        return $this->cacheDir;
    }
}
