<?php

declare(strict_types=1);

/**
 * Outil de nettoyage des dossiers vides dans le stockage runtime privé.
 *
 * Cet outil supprime uniquement les dossiers VIDES selon des règles strictes :
 *   - Ne supprime JAMAIS de fichiers
 *   - Ne supprime JAMAIS la racine du stockage
 *   - Ne suit PAS les liens symboliques
 *   - Vérifie que le dossier est encore vide au moment de la suppression
 *   - Nécessite une confirmation explicite avec --apply
 *   - Exige --confirm-production pour la production
 *
 * Usage:
 *   php backend/core/tools/private_storage_prune.php [options]
 *
 * Options:
 *   --root=<path>           Chemin racine du stockage (défaut: ROOT_PATH/private/storage)
 *   --dry-run              Analyse uniquement, aucune suppression (défaut)
 *   --apply                Active la suppression (nécessite aussi --confirm-production en prod)
 *   --confirm-production   Confirmation explicite pour l'environnement de production
 *   --json                Sortie au format JSON
 *   --min-age=<seconds>    Âge minimum des dossiers vides avant suppression (défaut: 0)
 *   --max-depth=<n>        Profondeur maximale à analyser (défaut: 10)
 *
 * Règles de sécurité:
 *   - Sans --apply, seule une simulation est effectuée
 *   - En production, --apply ET --confirm-production sont obligatoires
 *   - La racine du stockage n'est jamais supprimée
 *   - Les chemins dangereux (/ , /home, /var, etc.) sont refusés
 */

$privateStoragePruneProcessAppEnv = getenv('APP_ENV');
if (!defined('PRIVATE_STORAGE_PRUNE_PROCESS_APP_ENV')) {
    define(
        'PRIVATE_STORAGE_PRUNE_PROCESS_APP_ENV',
        is_string($privateStoragePruneProcessAppEnv) ? $privateStoragePruneProcessAppEnv : ''
    );
}

require_once __DIR__ . '/../../core/bootstrap.php';

use Caramagnols\Logging\AppEventLogger;

class PrivateStoragePrune
{
    private const FORBIDDEN_ROOTS = [
        '/',
        '/home',
        '/var',
        '/tmp',
        '/usr',
        '/etc',
        '/bin',
        '/sbin',
        '/lib',
        '/opt',
        '/srv',
    ];

    private const ALLOWED_STORAGE_ROOTS = [
        'private/storage',
        'private-storage',
        'private',
    ];

    private string $rootPath;
    private bool $dryRun = true;
    private bool $apply = false;
    private bool $confirmProduction = false;
    private bool $jsonOutput = false;
    private int $minAgeSeconds = 0;
    private int $maxDepth = 10;
    private ?AppEventLogger $logger;
    private array $removedDirectories = [];
    private array $wouldRemoveDirectories = [];
    private array $errors = [];

    public function __construct(
        string $rootPath,
        bool $dryRun = true,
        bool $apply = false,
        bool $confirmProduction = false,
        bool $jsonOutput = false,
        int $minAgeSeconds = 0,
        int $maxDepth = 10,
        ?AppEventLogger $logger = null
    ) {
        $this->rootPath = $this->normalizePath($rootPath);
        $this->dryRun = $dryRun;
        $this->apply = $apply;
        $this->confirmProduction = $confirmProduction;
        $this->jsonOutput = $jsonOutput;
        $this->minAgeSeconds = max(0, $minAgeSeconds);
        $this->maxDepth = max(1, min($maxDepth, 20));
        $this->logger = $logger;
    }

    public function run(): int
    {
        // Validation de la racine
        if (!$this->validateRootPath()) {
            $this->outputError("Chemin racine invalide ou interdit: {$this->rootPath}");
            return 1;
        }

        if (!is_dir($this->rootPath)) {
            $this->outputError("Répertoire racine introuvable: {$this->rootPath}");
            return 1;
        }

        // Vérification des prérequis pour l'application
        if ($this->apply && !$this->dryRun) {
            // En production, confirmation explicite requise
            if ($this->isProductionEnvironment() && !$this->confirmProduction) {
                $this->outputError(
                    "En environnement de production, --apply nécessite aussi --confirm-production. " .
                    "Aucune suppression ne sera effectuée sans confirmation explicite."
                );
                return 1;
            }

            // Avertissement final
            if (!$this->jsonOutput) {
                echo "ATTENTION: Vous avez demandé la suppression des dossiers vides.\n";
                echo "Cette opération est IRRÉVERSIBLE.\n";
                echo "Vérifiez que vous avez une sauvegarde avant de continuer.\n";
                echo "\n";
            }
        }

        $this->log('info', 'private.storage.prune.started', [
            'root_path' => $this->rootPath,
            'dry_run' => $this->dryRun,
            'apply' => $this->apply,
            'min_age_seconds' => $this->minAgeSeconds,
            'max_depth' => $this->maxDepth,
        ]);

        // Exécuter le nettoyage
        $this->pruneEmptyDirectories();

        // Sortir le rapport
        $this->outputReport();

        $this->log('info', 'private.storage.prune.completed', [
            'dry_run' => $this->dryRun,
            'would_remove_count' => count($this->wouldRemoveDirectories),
            'removed_count' => count($this->removedDirectories),
            'errors' => $this->errors,
        ]);

        // Retourner un code d'erreur si des erreurs ou si rien n'a été fait en mode apply
        if (!empty($this->errors)) {
            return 1;
        }
        if ($this->apply && count($this->removedDirectories) === 0) {
            if (!$this->jsonOutput) {
                echo "Aucun dossier vide à supprimer.\n";
            }
            return 0;
        }

        return 0;
    }

    private function validateRootPath(): bool
    {
        // Vérifier les chemins interdits
        $normalized = $this->normalizePath($this->rootPath);
        foreach (self::FORBIDDEN_ROOTS as $forbidden) {
            if ($normalized === $forbidden) {
                return false;
            }
        }

        // Vérifier que le chemin contient un des suffixes autorisés
        foreach (self::ALLOWED_STORAGE_ROOTS as $allowed) {
            if (str_contains($normalized, '/' . $allowed) || str_ends_with($normalized, $allowed)) {
                return true;
            }
        }

        // Accepter si c'est un chemin relatif ou absolu sûr
        // Mais refuser si c'est trop court ou suspect
        if (strlen($normalized) < 5) {
            return false;
        }

        return true;
    }

    private function isProductionEnvironment(): bool
    {
        $processEnv = strtolower((string) (defined('PRIVATE_STORAGE_PRUNE_PROCESS_APP_ENV') ? PRIVATE_STORAGE_PRUNE_PROCESS_APP_ENV : ''));
        if (in_array($processEnv, ['production', 'prod', 'live'], true)) {
            return true;
        }

        if (function_exists('app_config')) {
            $env = strtolower((string) app_config('env', 'development'));
            return in_array($env, ['production', 'prod', 'live'], true);
        }

        // Détection basique
        return getenv('APP_ENV') === 'production' ||
               getenv('ENVIRONMENT') === 'production' ||
               (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'lescaramagnols.com'));
    }

    private function pruneEmptyDirectories(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->rootPath,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        // Parcourir du plus profond vers le haut
        $paths = [];
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $paths[] = $item->getPathname();
            }
        }

        // Inverser pour traiter du plus profond au plus haut
        $paths = array_reverse($paths);

        foreach ($paths as $path) {
            // Ne jamais traiter la racine
            if ($this->normalizePath($path) === $this->normalizePath($this->rootPath)) {
                continue;
            }

            // Vérifier la profondeur
            $relative = str_replace($this->rootPath, '', $path);
            $depth = substr_count(trim($relative, '/'), '/') + 1;
            if ($depth > $this->maxDepth) {
                continue;
            }

            // Vérifier si vide
            if (!$this->isDirectoryEmpty($path)) {
                continue;
            }

            // Vérifier l'âge minimum
            if ($this->minAgeSeconds > 0) {
                $mtime = @filemtime($path);
                if ($mtime === false || (time() - $mtime) < $this->minAgeSeconds) {
                    continue;
                }
            }

            // Vérifier que c'est toujours vide (race condition)
            if (!$this->isDirectoryEmpty($path)) {
                continue;
            }

            // Ne pas suivre les liens symboliques
            if (is_link($path)) {
                continue;
            }

            if ($this->dryRun || !$this->apply) {
                $this->wouldRemoveDirectories[] = $path;
            } else {
                // Suppression effective
                if (@rmdir($path)) {
                    $this->removedDirectories[] = $path;
                    $this->log('info', 'private.storage.prune.removed', [
                        'path' => $path,
                    ]);
                } else {
                    $this->errors[] = "Échec de la suppression de: $path";
                    $this->log('error', 'private.storage.prune.failed', [
                        'path' => $path,
                        'error' => error_get_last()['message'] ?? 'unknown',
                    ]);
                }
            }
        }
    }

    private function isDirectoryEmpty(string $path): bool
    {
        $handle = @opendir($path);
        if ($handle === false) {
            return true;
        }

        while (($entry = readdir($handle)) !== false) {
            if ($entry !== '.' && $entry !== '..') {
                closedir($handle);
                return false;
            }
        }

        closedir($handle);
        return true;
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = str_replace('//', '/', $normalized);
        return rtrim($normalized, '/');
    }

    private function outputReport(): void
    {
        if ($this->jsonOutput) {
            echo json_encode([
                'root_path' => $this->rootPath,
                'dry_run' => $this->dryRun,
                'apply' => $this->apply,
                'would_remove_count' => count($this->wouldRemoveDirectories),
                'removed_count' => count($this->removedDirectories),
                'would_remove' => $this->wouldRemoveDirectories,
                'removed' => $this->removedDirectories,
                'errors' => $this->errors,
                'completed_at' => date('c'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            return;
        }

        echo "================================================================================\n";
        echo "NETTOYAGE DES DOSSIERS VIDES - STOCKAGE PRIVÉ\n";
        echo "================================================================================\n";
        echo "Racine: {$this->rootPath}\n";
        echo "Mode: " . ($this->dryRun ? "DRY-RUN (simulation)" : ($this->apply ? "APPLY" : "inactif")) . "\n";
        echo "Profondeur max: {$this->maxDepth}\n";
        echo "Âge minimum: " . ($this->minAgeSeconds > 0 ? "{$this->minAgeSeconds} secondes" : "aucune limite") . "\n";
        echo "\n";

        if ($this->dryRun || !$this->apply) {
            echo "--- SIMLATION : Dossiers qui seraient supprimés ---\n";
            if (empty($this->wouldRemoveDirectories)) {
                echo "Aucun dossier vide trouvé.\n";
            } else {
                echo sprintf("Trouvés: %d dossier(s) vide(s)\n\n", count($this->wouldRemoveDirectories));
                foreach ($this->wouldRemoveDirectories as $dir) {
                    $relative = str_replace($this->rootPath, '', $dir);
                    echo "  - " . ltrim($relative, '/') . "\n";
                }
            }
        } else {
            echo "--- SUPPRESSIONS EFFECTUÉES ---\n";
            if (empty($this->removedDirectories)) {
                echo "Aucun dossier supprimé.\n";
            } else {
                echo sprintf("Supprimés: %d dossier(s) vide(s)\n\n", count($this->removedDirectories));
                foreach ($this->removedDirectories as $dir) {
                    $relative = str_replace($this->rootPath, '', $dir);
                    echo "  - " . ltrim($relative, '/') . "\n";
                }
            }
        }

        if (!empty($this->errors)) {
            echo "\n--- ERREURS ---\n";
            foreach ($this->errors as $error) {
                echo "  ERREUR: $error\n";
            }
        }

        echo "\n";
        echo "================================================================================\n";
        if ($this->dryRun || !$this->apply) {
            echo "Aucune modification n'a été apportée (mode dry-run).\n";
            echo "Pour supprimer réellement, exécutez avec --apply --confirm-production\n";
        } else {
            echo "Nettoyage terminé.\n";
        }
        echo "================================================================================\n";
    }

    private function outputError(string $message): void
    {
        if ($this->jsonOutput) {
            echo json_encode(['error' => $message]) . "\n";
            return;
        }
        echo "ERREUR: $message\n";
    }

    private function log(string $level, string $event, array $context = []): void
    {
        if (!$this->logger instanceof AppEventLogger) {
            return;
        }

        $this->logger->security($event, $context, $level);
    }
}

// =============================================================================
// POINT D'ENTRÉE
// =============================================================================

try {
    $options = getopt('', [
        'root:',
        'dry-run',
        'apply',
        'confirm-production',
        'json',
        'min-age:',
        'max-depth:',
    ]);

    $rootPath = $options['root'] ?? (defined('ROOT_PATH') ? ROOT_PATH . '/private/storage' : __DIR__ . '/../../private/storage');
    $dryRun = !isset($options['apply']);
    $apply = isset($options['apply']);
    $confirmProduction = isset($options['confirm-production']);
    $jsonOutput = isset($options['json']);
    $minAge = isset($options['min-age']) ? (int) $options['min-age'] : 0;
    $maxDepth = isset($options['max-depth']) ? (int) $options['max-depth'] : 10;

    $logger = null;
    if (function_exists('app_event_logger')) {
        $logger = app_event_logger();
    }

    $prune = new PrivateStoragePrune(
        $rootPath,
        $dryRun,
        $apply,
        $confirmProduction,
        $jsonOutput,
        $minAge,
        $maxDepth,
        $logger
    );

    exit($prune->run());

} catch (\Throwable $e) {
    $isJson = isset($options['json']);
    if ($isJson) {
        echo json_encode(['error' => $e->getMessage()]) . "\n";
    } else {
        echo "ERREUR: " . $e->getMessage() . "\n";
    }
    exit(1);
}
