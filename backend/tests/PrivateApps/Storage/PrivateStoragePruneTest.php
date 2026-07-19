<?php

declare(strict_types=1);

/**
 * Tests pour l'outil de nettoyage du stockage privé.
 *
 * Ces tests vérifient que :
 *   - Le dry-run ne supprime rien
 *   - Seuls les dossiers vides sont supprimés
 *   - Les fichiers ne sont JAMAIS supprimés
 *   - La racine n'est JAMAIS supprimée
 *   - Les chemins dangereux sont refusés
 */

namespace Caramagnols\Tests\PrivateApps\Storage;

use PHPUnit\Framework\TestCase;

/**
 * @covers \PrivateStoragePrune
 */
final class PrivateStoragePruneTest extends TestCase
{
    private string $testStorageRoot;
    private string $backupStorageRoot;
    private string $toolPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStorageRoot = sys_get_temp_dir() . '/test-prune-storage-' . uniqid();
        $this->backupStorageRoot = sys_get_temp_dir() . '/backup-prune-storage-' . uniqid();
        $this->toolPath = dirname(__DIR__, 3) . '/core/tools/private_storage_prune.php';

        // Créer la structure de test
        $this->createTestStorage();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Nettoyer
        $this->removeDirectory($this->testStorageRoot);
        $this->removeDirectory($this->backupStorageRoot);
    }

    /**
     * Teste que le dry-run ne supprime rien.
     */
    public function testDryRunDoesNotDeleteAnything(): void
    {
        // Sauvegarder l'état initial
        $this->backupDirectory($this->testStorageRoot, $this->backupStorageRoot);

        // Exécuter le prune en mode dry-run
        $command = sprintf(
            'php "%s" --root="%s" --dry-run --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode);

        // Vérifier que rien n'a été supprimé
        $this->assertDirectoriesEqual($this->testStorageRoot, $this->backupStorageRoot);
    }

    /**
     * Teste que le dry-run détecte les dossiers vides.
     */
    public function testDryRunDetectsEmptyDirectories(): void
    {
        $command = sprintf(
            'php "%s" --root="%s" --dry-run --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);
        $this->assertIsArray($json);

        // On a créé des dossiers vides : 0b/c6, 0b/36
        $this->assertGreaterThan(0, $json['would_remove_count']);
    }

    /**
     * Teste que les fichiers ne sont JAMAIS supprimés (même en mode apply).
     */
    public function testFilesAreNeverDeleted(): void
    {
        // Sauvegarder l'état initial
        $this->backupDirectory($this->testStorageRoot, $this->backupStorageRoot);

        // Compter les fichiers avant
        $filesBefore = $this->countFiles($this->testStorageRoot);

        // Exécuter le prune avec --apply (mais sans --confirm-production, donc ça échouera)
        // ou avec --confirm-production sur un chemin qui n'est pas détecté comme production
        $command = sprintf(
            'php "%s" --root="%s" --apply --confirm-production --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        // Compter les fichiers après
        $filesAfter = $this->countFiles($this->testStorageRoot);

        // Les fichiers ne doivent pas avoir changé
        $this->assertSame($filesBefore, $filesAfter);
    }

    /**
     * Teste que la racine n'est JAMAIS supprimée.
     */
    public function testRootIsNeverDeleted(): void
    {
        // Exécuter le prune
        $command = sprintf(
            'php "%s" --root="%s" --apply --confirm-production --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        // Vérifier que la racine existe toujours
        $this->assertTrue(is_dir($this->testStorageRoot));
    }

    /**
     * Teste que les chemins dangereux sont refusés.
     */
    public function testDangerousPathsAreRejected(): void
    {
        $dangerousPaths = [
            '/',
            '/home',
            '/var',
            '/tmp',
            '/etc',
        ];

        foreach ($dangerousPaths as $path) {
            $output = [];
            $command = sprintf(
                'php "%s" --root="%s" --dry-run --json 2>&1',
                $this->toolPath,
                $path
            );

            exec($command, $output, $returnCode);

            $this->assertNotSame(0, $returnCode, "Le chemin $path devrait être rejeté");

            $json = json_decode(implode("\n", $output), true);
            $this->assertArrayHasKey('error', $json, "Le chemin $path devrait produire une erreur");
        }
    }

    /**
     * Teste que le prune fonctionne correctement sur un chemin valide.
     */
    public function testPruneWithApplyOnValidPath(): void
    {
        // Créer une copie pour le test (car on va supprimer des dossiers)
        $testRoot = sys_get_temp_dir() . '/test-prune-apply-' . uniqid();
        $this->copyDirectory($this->testStorageRoot, $testRoot);

        // Compter les dossiers avant
        $dirsBefore = $this->countDirectories($testRoot);

        // Exécuter le prune avec --apply (en dehors de la production, donc ça devrait fonctionner)
        $command = sprintf(
            'php "%s" --root="%s" --apply --confirm-production --json 2>&1',
            $this->toolPath,
            $testRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);

        // Des dossiers devraient avoir été supprimés
        $this->assertGreaterThanOrEqual(0, $json['removed_count']);

        // Compter les dossiers après
        $dirsAfter = $this->countDirectories($testRoot);

        // Le nombre de dossiers devrait avoir diminué
        $this->assertGreaterThan(0, $json['removed_count']);
        $this->assertLessThan($dirsBefore, $dirsAfter);
        $this->assertSame($dirsBefore - $dirsAfter, $json['removed_count']);

        // Nettoyer
        $this->removeDirectory($testRoot);
    }

    /**
     * Teste que --apply sans --confirm-production échoue en production.
     */
    public function testApplyWithoutConfirmProductionFailsInProduction(): void
    {
        // Simuler un environnement de production
        putenv('APP_ENV=production');

        $command = sprintf(
            'APP_ENV=production php "%s" --root="%s" --apply --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        // Doit échouer car --confirm-production est manquant
        $this->assertNotSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);
        $this->assertArrayHasKey('error', $json);
    }

    /**
     * Crée une structure de stockage de test.
     */
    private function createTestStorage(): void
    {
        // Créer les répertoires principaux
        mkdir($this->testStorageRoot, 0777, true);
        mkdir($this->testStorageRoot . '/uploads', 0777, true);
        mkdir($this->testStorageRoot . '/document-hub', 0777, true);

        // Créer des fichiers de test dans uploads avec structure de sharding
        mkdir($this->testStorageRoot . '/uploads/71', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/71/73', 0777, true);
        file_put_contents($this->testStorageRoot . '/uploads/71/73/test-document-1.pdf', 'PDF content 1');

        // Créer un deuxième fichier
        mkdir($this->testStorageRoot . '/uploads/11', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/11/c9', 0777, true);
        file_put_contents($this->testStorageRoot . '/uploads/11/c9/test-document-2.pdf', 'PDF content 2');

        // Créer des dossiers vides (pour tester le prune)
        mkdir($this->testStorageRoot . '/uploads/0b', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/0b/c6', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/0b/36', 0777, true);

        // Créer un dossier vide au niveau racine
        mkdir($this->testStorageRoot . '/exports', 0777, true);
    }

    /**
     * Copie un répertoire récursivement.
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                mkdir($destination . '/' . $iterator->getSubPathname(), 0777, true);
            } else {
                copy($item->getPathname(), $destination . '/' . $iterator->getSubPathname());
            }
        }
    }

    /**
     * Supprime un répertoire récursivement.
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * Sauvegarde un répertoire.
     */
    private function backupDirectory(string $source, string $destination): void
    {
        $this->copyDirectory($source, $destination);
    }

    /**
     * Compte le nombre de fichiers dans un répertoire.
     */
    private function countFiles(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Compte le nombre de répertoires dans un répertoire.
     */
    private function countDirectories(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vérifie que deux répertoires sont identiques.
     */
    private function assertDirectoriesEqual(string $dir1, string $dir2): void
    {
        $iterator1 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir1, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $iterator2 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir2, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $files1 = [];
        foreach ($iterator1 as $item) {
            if ($item->isFile()) {
                $files1[$iterator1->getSubPathname()] = file_get_contents($item->getPathname());
            }
        }

        $files2 = [];
        foreach ($iterator2 as $item) {
            if ($item->isFile()) {
                $files2[$iterator2->getSubPathname()] = file_get_contents($item->getPathname());
            }
        }

        $this->assertSame(
            array_keys($files1),
            array_keys($files2),
            'Les fichiers diffèrent entre les répertoires'
        );

        foreach ($files1 as $path => $content) {
            $this->assertSame(
                $content,
                $files2[$path],
                "Le contenu diffère pour $path"
            );
        }
    }
}
