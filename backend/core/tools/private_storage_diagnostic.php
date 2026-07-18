<?php

declare(strict_types=1);

/**
 * Outil de diagnostic du stockage runtime privé.
 *
 * Cet outil analyse l'état de backend/private/storage/ sans modifier les données.
 * Il permet de vérifier :
 *   - La structure des dossiers (sharding SHA-256)
 *   - Le nombre de fichiers et dossiers
 *   - Les dossiers vides
 *   - La taille totale
 *   - La conformité avec les algorithmes de stockage attendus
 *
 * Usage:
 *   php backend/core/tools/private_storage_diagnostic.php [--root=<path>] [--json]
 *
 * Options:
 *   --root=<path>    Chemin racine du stockage (défaut: ROOT_PATH/private/storage)
 *   --json           Sortie au format JSON
 *   --dry-run        Mode diagnostic uniquement (toujours vrai pour cet outil)
 */

require_once __DIR__ . '/../../core/bootstrap.php';

use Caramagnols\Logging\AppEventLogger;

class PrivateStorageDiagnostic
{
    private const SUPPORTED_STORAGE_DIRS = [
        'uploads',
        'document-hub',
        'family-discussion',
        'backups',
        'exports',
    ];

    private string $rootPath;
    private bool $jsonOutput = false;
    private ?AppEventLogger $logger;

    public function __construct(string $rootPath, bool $jsonOutput = false, ?AppEventLogger $logger = null)
    {
        $this->rootPath = rtrim(str_replace('\\', '/', trim($rootPath)), '/');
        $this->jsonOutput = $jsonOutput;
        $this->logger = $logger;
    }

    public function run(): int
    {
        if (!is_dir($this->rootPath)) {
            $this->outputError("Répertoire racine introuvable: {$this->rootPath}");
            return 1;
        }

        $this->log('info', 'private.storage.diagnostic.started', [
            'root_path' => $this->rootPath,
        ]);

        $report = $this->analyzeStorage();
        $this->outputReport($report);

        $this->log('info', 'private.storage.diagnostic.completed', [
            'summary' => $report['summary'],
        ]);

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeStorage(): array
    {
        $report = [
            'root_path' => $this->rootPath,
            'scanned_at' => date('c'),
            'directories' => [],
            'summary' => [
                'total_directories' => 0,
                'total_files' => 0,
                'total_size_bytes' => 0,
                'empty_directories' => 0,
                'non_empty_directories' => 0,
            ],
            'warnings' => [],
            'sharding_analysis' => [
                'uploads' => $this->analyzeShardingPattern('uploads', '/^[0-9a-f]{2}\/[0-9a-f]{2}\/[A-Za-z0-9._-]+\.[a-z0-9]{1,16}$/'),
                'document_hub_objects' => $this->analyzeShardingPattern('document-hub/objects/sha256', '/^[0-9a-f]{2}\/[0-9a-f]{64}$/'),
                'family_discussion' => $this->analyzeShardingPattern('family-discussion/uploads', '/^[0-9a-f]{2}\/[0-9a-f]{2}\/[A-Za-z0-9._-]+\.[a-z0-9]{1,16}$/'),
            ],
        ];

        foreach (self::SUPPORTED_STORAGE_DIRS as $dir) {
            $dirPath = $this->rootPath . '/' . $dir;
            if (!is_dir($dirPath)) {
                $report['directories'][$dir] = [
                    'exists' => false,
                    'path' => $dirPath,
                ];
                continue;
            }

            $dirReport = $this->analyzeDirectory($dirPath);
            $report['directories'][$dir] = $dirReport;

            $report['summary']['total_directories'] += $dirReport['total_directories'] + 1; // +1 pour le dir lui-même
            $report['summary']['total_files'] += $dirReport['total_files'];
            $report['summary']['total_size_bytes'] += $dirReport['total_size_bytes'];
            $report['summary']['empty_directories'] += $dirReport['empty_directories'];
            $report['summary']['non_empty_directories'] += $dirReport['non_empty_directories'];
        }

        // Ajouter le répertoire racine lui-même
        $report['summary']['total_directories']++;

        // Détecter les dossiers inattendus
        $actualDirs = array_filter(
            scandir($this->rootPath),
            fn($d) => $d !== '.' && $d !== '..' && is_dir($this->rootPath . '/' . $d)
        );

        $unexpectedDirs = array_diff($actualDirs, self::SUPPORTED_STORAGE_DIRS);
        if (!empty($unexpectedDirs)) {
            $report['warnings'][] = [
                'type' => 'unexpected_directories',
                'message' => 'Répertoires inattendus trouvés dans le stockage privé',
                'directories' => array_values($unexpectedDirs),
            ];
        }

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeDirectory(string $path, string $prefix = ''): array
    {
        $result = [
            'path' => $path,
            'relative_path' => ltrim($prefix, '/'),
            'total_directories' => 0,
            'total_files' => 0,
            'total_size_bytes' => 0,
            'empty_directories' => 0,
            'non_empty_directories' => 0,
            'max_depth' => 0,
            'files_by_extension' => [],
            'largest_files' => [],
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $depth = 0;
        foreach ($iterator as $item) {
            $currentDepth = $iterator->getDepth();
            $result['max_depth'] = max($result['max_depth'], $currentDepth);

            if ($item->isDir()) {
                $result['total_directories']++;
            } elseif ($item->isFile()) {
                $result['total_files']++;
                $size = $item->getSize();
                $result['total_size_bytes'] += $size;

                // Extension
                $extension = strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION));
                if ($extension !== '') {
                    $result['files_by_extension'][$extension] = ($result['files_by_extension'][$extension] ?? 0) + 1;
                }

                // Fichiers les plus gros
                $result['largest_files'][] = [
                    'path' => $iterator->getSubPathname(),
                    'size_bytes' => $size,
                ];
            }
        }

        // Trier les fichiers les plus gros
        usort($result['largest_files'], fn($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);
        $result['largest_files'] = array_slice($result['largest_files'], 0, 10);

        // Compter les dossiers vides (nécessite un scan séparé car les itérateurs ne voient pas les dossiers vides)
        $allDirs = $this->getAllDirectories($path);
        $emptyCount = 0;
        foreach ($allDirs as $dir) {
            if ($this->isDirectoryEmpty($dir)) {
                $emptyCount++;
            }
        }

        $result['empty_directories'] = $emptyCount;
        $result['non_empty_directories'] = $result['total_directories'] - $emptyCount;

        return $result;
    }

    /**
     * @return list<string>
     */
    private function getAllDirectories(string $root): array
    {
        $dirs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $dirs[] = $item->getPathname();
            }
        }

        return $dirs;
    }

    private function isDirectoryEmpty(string $path): bool
    {
        $handle = opendir($path);
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

    /**
     * Analyse le pattern de sharding pour un sous-répertoire.
     *
     * @return array<string, mixed>
     */
    private function analyzeShardingPattern(string $relativePath, string $expectedFilePattern): array
    {
        $fullPath = $this->rootPath . '/' . $relativePath;

        if (!is_dir($fullPath)) {
            return [
                'path' => $relativePath,
                'exists' => false,
                'expected_pattern' => $expectedFilePattern,
            ];
        }

        $analysis = [
            'path' => $relativePath,
            'exists' => true,
            'expected_pattern' => $expectedFilePattern,
            'total_level1_dirs' => 0,
            'total_level2_dirs' => 0,
            'total_files' => 0,
            'matching_files' => 0,
            'non_matching_files' => 0,
            'empty_level2_dirs' => 0,
            'sample_non_matching' => [],
        ];

        $level1Dirs = array_filter(
            scandir($fullPath),
            fn($d) => $d !== '.' && $d !== '..' && is_dir($fullPath . '/' . $d) && preg_match('/^[0-9a-f]{2}$/', $d)
        );

        $analysis['total_level1_dirs'] = count($level1Dirs);

        foreach ($level1Dirs as $l1Dir) {
            $l1Path = $fullPath . '/' . $l1Dir;
            $level2Dirs = array_filter(
                scandir($l1Path),
                fn($d) => $d !== '.' && $d !== '..' && is_dir($l1Path . '/' . $d) && preg_match('/^[0-9a-f]{2}$/', $d)
            );

            $analysis['total_level2_dirs'] += count($level2Dirs);

            foreach ($level2Dirs as $l2Dir) {
                $l2Path = $l1Path . '/' . $l2Dir;
                $files = array_filter(
                    scandir($l2Path),
                    fn($f) => $f !== '.' && $f !== '..' && is_file($l2Path . '/' . $f)
                );

                $analysis['total_files'] += count($files);

                foreach ($files as $file) {
                    if (preg_match($expectedFilePattern, $l2Dir . '/' . $file)) {
                        $analysis['matching_files']++;
                    } else {
                        $analysis['non_matching_files']++;
                        if (count($analysis['sample_non_matching']) < 5) {
                            $analysis['sample_non_matching'][] = $l2Dir . '/' . $file;
                        }
                    }
                }

                if ($this->isDirectoryEmpty($l2Path)) {
                    $analysis['empty_level2_dirs']++;
                }
            }

            // Vérifier aussi les fichiers directement dans level1 (cas mal formé)
            $l1Files = array_filter(
                scandir($l1Path),
                fn($f) => $f !== '.' && $f !== '..' && is_file($l1Path . '/' . $f)
            );
            foreach ($l1Files as $file) {
                $analysis['total_files']++;
                if (preg_match('/^' . $l1Dir . '\/' . $expectedFilePattern . '$/', $file)) {
                    $analysis['matching_files']++;
                } else {
                    $analysis['non_matching_files']++;
                }
            }
        }

        return $analysis;
    }

    private function outputReport(array $report): void
    {
        if ($this->jsonOutput) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            return;
        }

        echo "================================================================================\n";
        echo "DIAGNOSTIC DU STOCKAGE PRIVÉ\n";
        echo "================================================================================\n";
        echo "Racine: {$report['root_path']}\n";
        echo "Date: {$report['scanned_at']}\n";
        echo "\n";

        echo "--- RÉSUMÉ -----------------------------------------------------------------\n";
        $summary = $report['summary'];
        echo sprintf(
            "Dossiers totaux: %d (dont %d vides, %d non-vides)\n",
            $summary['total_directories'],
            $summary['empty_directories'],
            $summary['non_empty_directories']
        );
        echo sprintf("Fichiers totaux: %d\n", $summary['total_files']);
        echo sprintf("Taille totale: %s\n", $this->formatBytes($summary['total_size_bytes']));
        echo "\n";

        echo "--- ANALYSE PAR RÉPERTOIRE --------------------------------------------------\n";
        foreach ($report['directories'] as $name => $dirReport) {
            if (!$dirReport['exists']) {
                echo "  [$name] NON PRÉSENT\n";
                continue;
            }

            $directoryLabels = [
                'uploads' => " (documents privés)",
                'document-hub' => " (hub documentaire CAS)",
                'family-discussion' => " (pièces jointes discussions)",
                'backups' => " (sauvegardes)",
            ];
            $prefix = $directoryLabels[$name] ?? '';
            echo sprintf(
                "  [%s]%s: %d dossiers, %d fichiers, %s\n",
                $name,
                $prefix,
                $dirReport['total_directories'],
                $dirReport['total_files'],
                $this->formatBytes($dirReport['total_size_bytes'])
            );

            if (!empty($dirReport['files_by_extension'])) {
                echo "    Extensions: ";
                $extensions = $dirReport['files_by_extension'];
                arsort($extensions);
                echo implode(', ', array_map(
                    fn($ext, $count) => "$ext ($count)",
                    array_keys($extensions),
                    $extensions
                )) . "\n";
            }
        }
        echo "\n";

        echo "--- ANALYSE DU SHARDING -----------------------------------------------------\n";
        foreach ($report['sharding_analysis'] as $name => $analysis) {
            if (!$analysis['exists']) {
                continue;
            }
            echo "  $name:\n";
            echo sprintf(
                "    Niveau 1: %d dossiers, Niveau 2: %d dossiers\n",
                $analysis['total_level1_dirs'],
                $analysis['total_level2_dirs']
            );
            echo sprintf(
                "    Fichiers: %d (conformes: %d, non-conformes: %d)\n",
                $analysis['total_files'],
                $analysis['matching_files'],
                $analysis['non_matching_files']
            );
            echo sprintf("    Dossiers niveau 2 vides: %d\n", $analysis['empty_level2_dirs']);

            if (!empty($analysis['sample_non_matching'])) {
                echo "    Échantillon non-conforme: " . implode(', ', $analysis['sample_non_matching']) . "\n";
            }
        }
        echo "\n";

        if (!empty($report['warnings'])) {
            echo "--- AVERTISSEMENTS -----------------------------------------------------------\n";
            foreach ($report['warnings'] as $warning) {
                echo "  [{$warning['type']}] {$warning['message']}\n";
                if (isset($warning['directories'])) {
                    echo "    Répertoires: " . implode(', ', $warning['directories']) . "\n";
                }
            }
            echo "\n";
        }

        echo "================================================================================\n";
        echo "FIN DU DIAGNOSTIC\n";
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

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 octets';
        }

        $units = ['octets', 'Ko', 'Mo', 'Go', 'To'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return sprintf('%.2f %s', $value, $units[$index]);
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
    $options = getopt('', ['root:', 'json', 'dry-run']);

    $rootPath = $options['root'] ?? (defined('ROOT_PATH') ? ROOT_PATH . '/private/storage' : __DIR__ . '/../../private/storage');
    $jsonOutput = isset($options['json']);

    // --dry-run est toujours vrai pour cet outil (lecture seule)

    $logger = null;
    if (function_exists('app_event_logger')) {
        $logger = app_event_logger();
    }

    $diagnostic = new PrivateStorageDiagnostic($rootPath, $jsonOutput, $logger);
    exit($diagnostic->run());

} catch (\Throwable $e) {
    if (isset($options['json'])) {
        echo json_encode(['error' => $e->getMessage()]) . "\n";
    } else {
        echo "ERREUR: " . $e->getMessage() . "\n";
    }
    exit(1);
}
