<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

/**
 * Stockage physique privé du hub documentaire, adressé par contenu (CAS).
 *
 * Arborescence sous <racine privée>/document-hub/ :
 *   quarantine/    fichiers en attente de validation (noms aléatoires)
 *   objects/sha256/ab/cd/<sha256>   originaux immuables
 *   derivatives/   dérivés recréables
 *   exports-temp/  archives d'export à durée de vie courte
 *   restore-temp/  espace de restauration isolé
 *
 * Règles : écriture d'abord en quarantaine, promotion par rename() atomique,
 * jamais d'écrasement d'un objet existant, jamais le nom utilisateur comme chemin.
 */
final class DocumentStorageService
{
    private const DIRECTORY_PERMISSIONS = 0770;
    private const FILE_PERMISSIONS = 0660;
    private const STORAGE_KEY_PATTERN = '/\Aobjects\/sha256\/[a-f0-9]{2}\/[a-f0-9]{2}\/[a-f0-9]{64}\z/';
    private const DERIVATIVE_KEY_PATTERN = '/\Aderivatives\/[a-f0-9]{2}\/[a-f0-9]{64}-[a-z0-9_]{1,32}\.[a-z0-9]{1,8}\z/';

    private string $rootPath;

    /**
     * Mode legacy : lecture depuis l'ancien chemin, écriture bloquée.
     * Activé automatiquement si le nouveau chemin n'existe pas mais que l'ancien existe.
     */
    private bool $legacyMode = false;

    /**
     * Chemin legacy pour la compatibilité pendant la migration.
     */
    private const LEGACY_STORAGE_ROOT = ROOT_PATH . '/private';
    private const LEGACY_STORAGE_DIR = 'storage/document-hub';

    private ?string $lastError = null;

    public function __construct(string $rootPath)
    {
        $rootPath = rtrim(str_replace('\\', '/', trim($rootPath)), '/');
        if ($rootPath === '') {
            throw new \InvalidArgumentException('Racine de stockage documentaire vide.');
        }

        // Vérifier si le chemin principal existe, sinon essayer le fallback legacy
        $this->checkLegacyFallback($rootPath);

        $this->ensureDirectories();
    }

    /**
     * Vérifie si le chemin principal existe, sinon bascule vers le chemin legacy.
     * Le mode legacy permet la lecture mais bloque l'écriture.
     */
    private function checkLegacyFallback(string $configuredRootPath): void
    {
        $appEnv = function_exists('app_config') ? strtolower((string) app_config('env', '')) : '';
        if (!in_array($appEnv, ['production', 'prod', 'live'], true)) {
            $this->rootPath = $configuredRootPath;
            return;
        }

        // Chemin principal
        $mainRootPath = $configuredRootPath;

        // Chemin legacy
        $legacyRootPath = self::LEGACY_STORAGE_ROOT . '/' . self::LEGACY_STORAGE_DIR;

        // Si le chemin principal n'existe pas mais que le legacy existe, basculer
        if (!is_dir($mainRootPath) && is_dir($legacyRootPath)) {
            $this->rootPath = $legacyRootPath;
            $this->legacyMode = true;
        } else {
            $this->rootPath = $mainRootPath;
        }
    }

    public static function fromAppConfig(): self
    {
        $config = function_exists('app_config') ? app_config('private.document_hub', []) : [];
        $config = is_array($config) ? $config : [];

        $root = is_string($config['storage_root_path'] ?? null) && trim((string) $config['storage_root_path']) !== ''
            ? trim((string) $config['storage_root_path'])
            : ROOT_PATH . '/private/storage/document-hub';

        return new self($root);
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function isLegacyMode(): bool
    {
        return $this->legacyMode;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function quarantineDirectory(): string
    {
        return $this->rootPath . '/quarantine';
    }

    public function exportsTempDirectory(): string
    {
        return $this->rootPath . '/exports-temp';
    }

    public function restoreTempDirectory(): string
    {
        return $this->rootPath . '/restore-temp';
    }

    /**
     * Place un fichier reçu en quarantaine sous un nom aléatoire.
     * Retourne le chemin absolu en quarantaine, ou null en cas d'échec.
     */
    public function moveToQuarantine(string $sourcePath, bool $isUploadedFile): ?string
    {
        $this->lastError = null;

        if ($this->legacyMode) {
            $this->lastError = 'legacy_mode_readonly';
            return null;
        }

        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            $this->lastError = 'source_unreadable';
            return null;
        }

        // Le dossier de quarantaine peut avoir disparu entre le démarrage du
        // process (ensureDirectories() au constructeur) et cet appel, par
        // exemple après un nettoyage externe des dossiers vides du stockage
        // runtime. On le recrée ici pour ne jamais échouer un import valide
        // à cause d'un dossier manquant.
        $quarantineDir = $this->quarantineDirectory();
        if (!is_dir($quarantineDir) && !@mkdir($quarantineDir, self::DIRECTORY_PERMISSIONS, true) && !is_dir($quarantineDir)) {
            $this->lastError = 'quarantine_directory_unavailable';
            return null;
        }

        $target = $quarantineDir . '/' . bin2hex(random_bytes(16)) . '.tmp';

        $moved = false;
        if ($isUploadedFile && is_uploaded_file($sourcePath)) {
            $moved = @move_uploaded_file($sourcePath, $target);
        }

        if (!$moved) {
            $moved = @rename($sourcePath, $target);
        }

        if (!$moved) {
            $moved = @copy($sourcePath, $target);
            if ($moved) {
                @unlink($sourcePath);
            }
        }

        if (!$moved || !is_file($target)) {
            $this->lastError = 'quarantine_write_denied';
            return null;
        }

        @chmod($target, self::FILE_PERMISSIONS);

        return $target;
    }

    /**
     * Empreinte SHA-256 calculée en flux (jamais de chargement complet en mémoire).
     */
    public function sha256File(string $absolutePath): ?string
    {
        $hash = @hash_file('sha256', $absolutePath);

        return is_string($hash) && preg_match('/\A[a-f0-9]{64}\z/', $hash) === 1 ? $hash : null;
    }

    public function storageKeyForHash(string $sha256): string
    {
        return sprintf('objects/sha256/%s/%s/%s', substr($sha256, 0, 2), substr($sha256, 2, 2), $sha256);
    }

    public function objectExists(string $sha256): bool
    {
        $absolute = $this->absolutePathForKey($this->storageKeyForHash($sha256));

        return $absolute !== null && is_file($absolute);
    }

    /**
     * Promeut un fichier de quarantaine vers le stockage immuable.
     * Si l'objet existe déjà (import concurrent ou déduplication), le fichier
     * en quarantaine est supprimé et l'objet existant est conservé tel quel.
     *
     * @return string|null storage_key en cas de succès
     */
    public function promoteFromQuarantine(string $quarantinePath, string $sha256): ?string
    {
        if ($this->legacyMode) {
            $this->lastError = 'legacy_mode_readonly';
            return null;
        }

        $quarantinePath = str_replace('\\', '/', $quarantinePath);
        if (!str_starts_with($quarantinePath, $this->quarantineDirectory() . '/') || !is_file($quarantinePath)) {
            return null;
        }

        if (preg_match('/\A[a-f0-9]{64}\z/', $sha256) !== 1) {
            return null;
        }

        $storageKey = $this->storageKeyForHash($sha256);
        $absolute = $this->absolutePathForKey($storageKey);
        if ($absolute === null) {
            return null;
        }

        if (is_file($absolute)) {
            // Objet déjà présent : ne jamais écraser un original.
            @unlink($quarantinePath);

            return $storageKey;
        }

        $targetDir = dirname($absolute);
        if (!is_dir($targetDir) && !@mkdir($targetDir, self::DIRECTORY_PERMISSIONS, true) && !is_dir($targetDir)) {
            return null;
        }

        if (!@rename($quarantinePath, $absolute)) {
            // Course possible : un import concurrent vient de promouvoir le même contenu.
            if (is_file($absolute)) {
                @unlink($quarantinePath);

                return $storageKey;
            }

            return null;
        }

        @chmod($absolute, self::FILE_PERMISSIONS);

        return $storageKey;
    }

    /**
     * Chemin absolu d'un objet à partir de sa clé de stockage contrôlée.
     * Refuse toute clé hors motif strict (aucune traversée possible).
     */
    public function absolutePathForKey(string $storageKey): ?string
    {
        $storageKey = trim(str_replace('\\', '/', $storageKey));
        if (
            preg_match(self::STORAGE_KEY_PATTERN, $storageKey) !== 1
            && preg_match(self::DERIVATIVE_KEY_PATTERN, $storageKey) !== 1
        ) {
            return null;
        }

        return $this->rootPath . '/' . $storageKey;
    }

    /**
     * Diffuse un objet vers la sortie HTTP par blocs (jamais tout en mémoire).
     * À appeler après l'envoi des en-têtes. Retourne les octets écrits ou null.
     */
    public function streamObject(string $storageKey): ?int
    {
        $absolute = $this->absolutePathForKey($storageKey);
        if ($absolute === null || !is_file($absolute) || !is_readable($absolute)) {
            return null;
        }

        $handle = @fopen($absolute, 'rb');
        if ($handle === false) {
            return null;
        }

        $written = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, 262144);
            if ($chunk === false) {
                break;
            }

            echo $chunk;
            $written += strlen($chunk);
        }

        fclose($handle);

        return $written;
    }

    /**
     * Supprime un fichier objet. Réservé au garbage collector après vérification
     * qu'aucune référence SQL (document, version) n'existe plus.
     */
    public function deleteUnreferencedObjectFile(string $storageKey): bool
    {
        if ($this->legacyMode) {
            return false;
        }

        $absolute = $this->absolutePathForKey($storageKey);
        if ($absolute === null || !is_file($absolute)) {
            return false;
        }

        return @unlink($absolute);
    }

    /**
     * Purge les fichiers de quarantaine plus vieux que le délai donné.
     *
     * @return int nombre de fichiers supprimés
     */
    public function purgeQuarantine(int $maxAgeSeconds = 86400): int
    {
        if ($this->legacyMode) {
            return 0;
        }

        return $this->purgeDirectory($this->quarantineDirectory(), $maxAgeSeconds);
    }

    /**
     * Purge les exports temporaires expirés.
     */
    public function purgeExpiredExports(int $maxAgeSeconds = 3600): int
    {
        if ($this->legacyMode) {
            return 0;
        }

        return $this->purgeDirectory($this->exportsTempDirectory(), $maxAgeSeconds);
    }

    private function purgeDirectory(string $directory, int $maxAgeSeconds): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        $threshold = time() - max(60, $maxAgeSeconds);
        $entries = @scandir($directory);
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_file($path) && (int) @filemtime($path) < $threshold && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function ensureDirectories(): void
    {
        foreach (['', '/quarantine', '/objects/sha256', '/derivatives', '/exports-temp', '/restore-temp'] as $suffix) {
            $directory = $this->rootPath . $suffix;
            if (!is_dir($directory) && !@mkdir($directory, self::DIRECTORY_PERMISSIONS, true) && !is_dir($directory)) {
                continue;
            }

            @chmod($directory, self::DIRECTORY_PERMISSIONS);
        }
    }
}
