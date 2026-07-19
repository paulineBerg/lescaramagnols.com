<?php

declare(strict_types=1);

/**
 * Tests pour l'outil de diagnostic du stockage privé.
 *
 * Ces tests vérifient que :
 *   - Le diagnostic ne modifie pas les fichiers
 *   - Le dry-run est strictement respecté
 *   - Les chemins de stockage sont correctement analysés
 */

namespace Caramagnols\Tests\PrivateApps\Storage;

use PHPUnit\Framework\TestCase;

/**
 * @covers \PrivateStorageDiagnostic
 */
final class PrivateStorageDiagnosticTest extends TestCase
{
    private string $testStorageRoot;
    private string $backupStorageRoot;
    private string $toolPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStorageRoot = sys_get_temp_dir() . '/test-private-storage-' . uniqid();
        $this->backupStorageRoot = sys_get_temp_dir() . '/backup-private-storage-' . uniqid();
        $this->toolPath = dirname(__DIR__, 3) . '/core/tools/private_storage_diagnostic.php';

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
     * Teste que le diagnostic ne modifie pas les fichiers.
     */
    public function testDiagnosticDoesNotModifyFiles(): void
    {
        // Sauvegarder l'état initial
        $this->backupDirectory($this->testStorageRoot, $this->backupStorageRoot);

        // Exécuter le diagnostic
        $output = [];
        $returnCode = 0;

        $command = sprintf(
            'php "%s" --root="%s" --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode, 'Diagnostic should succeed');

        // Vérifier que rien n'a changé
        $this->assertDirectoriesEqual($this->testStorageRoot, $this->backupStorageRoot);
    }

    /**
     * Teste que le diagnostic détecte les fichiers.
     */
    public function testDiagnosticDetectsFiles(): void
    {
        $command = sprintf(
            'php "%s" --root="%s" --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);
        $this->assertIsArray($json);

        // On a créé 3 fichiers dans uploads
        $this->assertSame(3, $json['summary']['total_files']);
    }

    /**
     * Teste que le diagnostic détecte les dossiers.
     */
    public function testDiagnosticDetectsDirectories(): void
    {
        $command = sprintf(
            'php "%s" --root="%s" --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);
        $this->assertIsArray($json);

        // On a créé plusieurs dossiers
        $this->assertGreaterThan(0, $json['summary']['total_directories']);
    }

    /**
     * Teste que le diagnostic détecte les répertoires supportés.
     */
    public function testDiagnosticDetectsSupportedDirectories(): void
    {
        $command = sprintf(
            'php "%s" --root="%s" --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);

        // Vérifier que les répertoires supportés sont détectés
        $this->assertArrayHasKey('uploads', $json['directories']);
        $this->assertArrayHasKey('document-hub', $json['directories']);
        $this->assertArrayHasKey('family-discussion', $json['directories']);
        $this->assertArrayHasKey('backups', $json['directories']);
        $this->assertArrayHasKey('exports', $json['directories']);
    }

    /**
     * Teste que le diagnostic échoue avec un chemin invalide.
     */
    public function testDiagnosticFailsWithInvalidPath(): void
    {
        $command = sprintf(
            'php "%s" --root="/nonexistent/path" --json 2>&1',
            $this->toolPath
        );

        exec($command, $output, $returnCode);

        $this->assertNotSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);
        $this->assertArrayHasKey('error', $json);
    }

    /**
     * Teste l'analyse du sharding.
     */
    public function testShardingAnalysis(): void
    {
        $command = sprintf(
            'php "%s" --root="%s" --json 2>&1',
            $this->toolPath,
            $this->testStorageRoot
        );

        exec($command, $output, $returnCode);

        $this->assertSame(0, $returnCode);

        $json = json_decode(implode("\n", $output), true);

        // Vérifier l'analyse du sharding pour uploads
        $this->assertArrayHasKey('sharding_analysis', $json);
        $this->assertArrayHasKey('uploads', $json['sharding_analysis']);

        // On a créé des fichiers dans uploads/71/73/
        $uploadsAnalysis = $json['sharding_analysis']['uploads'];
        $this->assertTrue($uploadsAnalysis['exists']);
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
        mkdir($this->testStorageRoot . '/document-hub/objects/sha256', 0777, true);
        mkdir($this->testStorageRoot . '/family-discussion', 0777, true);
        mkdir($this->testStorageRoot . '/backups', 0777, true);
        mkdir($this->testStorageRoot . '/exports', 0777, true);

        // Créer des fichiers de test dans uploads avec structure de sharding
        // Simuler la structure : uploads/71/73/documentId.pdf
        mkdir($this->testStorageRoot . '/uploads/71', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/71/73', 0777, true);
        file_put_contents($this->testStorageRoot . '/uploads/71/73/test-document-1.pdf', 'PDF content 1');

        // Créer un deuxième fichier
        mkdir($this->testStorageRoot . '/uploads/11', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/11/c9', 0777, true);
        file_put_contents($this->testStorageRoot . '/uploads/11/c9/test-document-2.pdf', 'PDF content 2');

        // Créer un troisième fichier
        mkdir($this->testStorageRoot . '/uploads/82', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/82/e3', 0777, true);
        file_put_contents($this->testStorageRoot . '/uploads/82/e3/test-image.jpg', 'JPEG content');

        // Créer des dossiers vides (pour tester la détection)
        mkdir($this->testStorageRoot . '/uploads/0b', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/0b/c6', 0777, true);
        mkdir($this->testStorageRoot . '/uploads/0b/36', 0777, true);
    }

    /**
     * Sauvegarde un répertoire.
     */
    private function backupDirectory(string $source, string $destination): void
    {
        $this->copyDirectory($source, $destination);
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
