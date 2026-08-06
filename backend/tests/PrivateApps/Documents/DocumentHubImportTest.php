<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\Documents;

use Caramagnols\PrivateApps\Documents\Contract\DocumentEntityRef;
use Caramagnols\PrivateApps\Documents\PersonalDocumentIntegration;
use Caramagnols\PrivateApps\Documents\Registry\DocumentIntegrationRegistry;
use Caramagnols\PrivateApps\Documents\Repository\DocumentHubRepository;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;
use Caramagnols\PrivateApps\Documents\Service\DocumentClassificationService;
use Caramagnols\PrivateApps\Documents\Service\DocumentExportService;
use Caramagnols\PrivateApps\Documents\Service\DocumentImportService;
use Caramagnols\PrivateApps\Documents\Service\DocumentLinkService;
use Caramagnols\PrivateApps\Documents\Service\DocumentPolicy;
use Caramagnols\PrivateApps\Documents\Service\DocumentStorageService;
use Caramagnols\PrivateApps\Documents\Service\DocumentValidationService;
use Caramagnols\PrivatePortal\PrivateAppRegistry;
use Caramagnols\PrivateApps\TaxDeclarationHelper\TaxDocumentIntegration;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Test d'intégration du hub documentaire (MySQL requis, ignoré sinon) :
 * import complet, déduplication SHA-256, rattachements, autorisations, export.
 */
final class DocumentHubImportTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $storageRoot = '';
    private string $fixturesDirectory = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
    }

    protected function setUp(): void
    {
        PrivateAppRegistry::reset();
        DocumentIntegrationRegistry::reset();
        $this->storageRoot = sys_get_temp_dir() . '/doc-hub-int-' . bin2hex(random_bytes(6));
        $this->fixturesDirectory = $this->storageRoot . '-fixtures';
        mkdir($this->fixturesDirectory, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
        foreach ([$this->fixturesDirectory, $this->storageRoot] as $directory) {
            if (!is_dir($directory)) {
                continue;
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
        PrivateAppRegistry::reset();
        DocumentIntegrationRegistry::reset();
    }

    private function createPrivateUsersTable(): void
    {
        $database = $this->editorialSqlDatabase();
        $database->pdo()->exec(sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(190) NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT \'active\'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $database->table('private_users')
        ));
    }

    private function insertUser(string $email, string $status = 'active'): int
    {
        $database = $this->editorialSqlDatabase();
        $statement = $database->pdo()->prepare(sprintf(
            'INSERT INTO `%s` (`email`, `status`) VALUES (:email, :status)',
            $database->table('private_users')
        ));
        $statement->execute(['email' => $email, 'status' => $status]);

        return (int) $database->pdo()->lastInsertId();
    }

    /**
     * @return array{0: DocumentImportService, 1: DocumentHubRepository, 2: DocumentLinkService, 3: DocumentStorageService, 4: DocumentTaxonomyRepository}
     */
    private function buildServices(): array
    {
        $database = $this->editorialSqlDatabase();
        $hubRepository = new DocumentHubRepository($database);
        $hubRepository->ensureSchema();
        $taxonomy = new DocumentTaxonomyRepository($database);
        $taxonomy->seedSystemCategories();
        $storage = new DocumentStorageService($this->storageRoot);
        $policy = new DocumentPolicy();
        $linkService = new DocumentLinkService($hubRepository, $database);
        $importService = new DocumentImportService(
            $policy,
            new DocumentValidationService($policy),
            $storage,
            $hubRepository,
            $taxonomy,
            new DocumentClassificationService($taxonomy),
            $linkService
        );

        return [$importService, $hubRepository, $linkService, $storage, $taxonomy];
    }

    /**
     * @return array{name: string, tmp_name: string, size: int, error: int}
     */
    private function fixtureUpload(string $name, string $content): array
    {
        $path = $this->fixturesDirectory . '/' . bin2hex(random_bytes(4)) . '-' . $name;
        file_put_contents($path, $content);

        return ['name' => $name, 'tmp_name' => $path, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK];
    }

    public function testImportDeduplicationLinksAuthorizationsAndExport(): void
    {
        $this->createPrivateUsersTable();
        [$importService, $hubRepository, $linkService, $storage, $taxonomy] = $this->buildServices();

        $ownerId = $this->insertUser('proprietaire@example.test');
        $strangerId = $this->insertUser('autre@example.test');

        $ownerRef = [DocumentEntityRef::of(PersonalDocumentIntegration::ENTITY_PERSONAL, $ownerId)];
        $content = "Quittance de loyer janvier 2026\n";

        // 1. Import initial.
        $results = $importService->importBatch(
            $ownerId,
            PersonalDocumentIntegration::PROFILE_PERSONAL,
            [$this->fixtureUpload('quittance-janvier.txt', $content)],
            $ownerRef,
            ['document_date' => '2026-01-05']
        );
        self::assertCount(1, $results);
        self::assertTrue($results[0]['ok'], $results[0]['error_code']);
        self::assertFalse($results[0]['deduplicated']);

        $document = $hubRepository->findDocumentByUid($results[0]['document_uid']);
        self::assertNotNull($document);
        self::assertSame(2026, (int) $document['fiscal_year']);
        self::assertSame(hash('sha256', $content), (string) $document['sha256']);

        $absolutePath = $storage->absolutePathForKey((string) $document['storage_key']);
        self::assertNotNull($absolutePath);
        self::assertSame($content, file_get_contents((string) $absolutePath));

        // Classement automatique par nom de fichier : « quittance » -> rents.receipt.
        self::assertSame('rents.receipt', (string) $document['category_code']);

        // 2. Second import du même contenu : un seul objet physique.
        $results2 = $importService->importBatch(
            $ownerId,
            PersonalDocumentIntegration::PROFILE_PERSONAL,
            [$this->fixtureUpload('copie-quittance.txt', $content)],
            $ownerRef,
            []
        );
        self::assertTrue($results2[0]['ok'], $results2[0]['error_code']);
        self::assertTrue($results2[0]['deduplicated']);

        $stats = $hubRepository->stats();
        self::assertSame(2, $stats['documents']);
        self::assertSame(1, $stats['objects']);
        self::assertGreaterThan(0, $stats['dedup_saved_bytes']);

        // 3. Rattachements : le document est lié à l'entité du contexte.
        $links = $hubRepository->linksForDocument((int) $document['id']);
        self::assertCount(1, $links);
        self::assertSame(PersonalDocumentIntegration::ENTITY_PERSONAL, (string) $links[0]['entity_type']);

        // 4. Autorisations : jamais par le seul objet physique partagé.
        $document['links'] = $links;
        self::assertTrue($linkService->userCanAccessDocument($document, $ownerId));
        self::assertFalse($linkService->userCanAccessDocument($document, $strangerId));

        // 5. Un utilisateur ne peut pas importer dans l'espace d'un autre.
        $forbidden = $importService->importBatch(
            $strangerId,
            PersonalDocumentIntegration::PROFILE_PERSONAL,
            [$this->fixtureUpload('intrusion.txt', 'x')],
            $ownerRef,
            []
        );
        self::assertFalse($forbidden[0]['ok']);
        self::assertSame('entity_forbidden', $forbidden[0]['error_code']);

        // 6. Les formats interdits sont refusés côté serveur.
        $rejected = $importService->importBatch(
            $ownerId,
            PersonalDocumentIntegration::PROFILE_PERSONAL,
            [$this->fixtureUpload('malware.exe', 'MZ...')],
            $ownerRef,
            []
        );
        self::assertFalse($rejected[0]['ok']);
        self::assertSame('forbidden_extension', $rejected[0]['error_code']);

        // 7. Export lisible : une seule copie physique, checksums vérifiables.
        if (class_exists(\ZipArchive::class)) {
            $exportService = new DocumentExportService($hubRepository, $taxonomy, $storage, $linkService);
            $export = $exportService->exportToZip($ownerId, [], 'test');
            self::assertTrue($export['ok'], $export['error_code']);
            self::assertSame(1, $export['file_count'], 'les deux documents partagent un seul fichier');
            self::assertFileExists($export['zip_path']);

            $zip = new \ZipArchive();
            self::assertTrue($zip->open($export['zip_path']));
            $checksums = (string) $zip->getFromName('SHA256SUMS');
            self::assertStringContainsString(hash('sha256', $content), $checksums);
            self::assertNotFalse($zip->getFromName('manifest.json'));
            self::assertNotFalse($zip->getFromName('documents.csv'));
            $zip->close();
        }

        // 8. Cycle de vie : archivage puis corbeille ; purge refusée si référencé.
        self::assertTrue($hubRepository->transitionStatus((int) $document['id'], DocumentHubRepository::DOC_STATUS_ARCHIVED));
        self::assertTrue($hubRepository->transitionStatus((int) $document['id'], DocumentHubRepository::DOC_STATUS_TRASHED));
        self::assertGreaterThan(0, $hubRepository->objectReferenceCount((int) $document['object_id']));
    }

    public function testTaxYearDocumentsAreScopedToTheOwnerUser(): void
    {
        $this->createPrivateUsersTable();
        [$importService, $hubRepository, $linkService] = $this->buildServices();

        $ownerId = $this->insertUser('tax-owner@example.test');
        $strangerId = $this->insertUser('tax-stranger@example.test');
        $taxRef = [DocumentEntityRef::of(TaxDocumentIntegration::ENTITY_YEAR, $ownerId . '-2026', 'tax_support')];

        $results = $importService->importBatch(
            $ownerId,
            TaxDocumentIntegration::PROFILE_TAX_YEAR,
            [$this->fixtureUpload('taxe-fonciere-2026.txt', "Taxe fonciere 2026\n")],
            $taxRef,
            ['document_date' => '2026-10-15']
        );

        self::assertCount(1, $results);
        self::assertTrue($results[0]['ok'], $results[0]['error_code']);

        $document = $hubRepository->findDocumentByUid($results[0]['document_uid']);
        self::assertNotNull($document);
        $document['links'] = $hubRepository->linksForDocument((int) $document['id']);

        self::assertTrue($linkService->userCanAccessDocument($document, $ownerId));
        self::assertFalse($linkService->userCanAccessDocument($document, $strangerId));
        self::assertSame('tax', (string) $document['category_code']);
    }

    public function testTaxonomyIsGlobalAndSeeded(): void
    {
        $this->createPrivateUsersTable();
        [, , , , $taxonomy] = $this->buildServices();

        $categories = $taxonomy->listActive();
        $codes = array_map(static fn (array $row): string => (string) $row['code'], $categories);

        foreach (['property', 'tenants', 'leases', 'inventory', 'rents', 'charges', 'works', 'tax', 'insurance', 'coownership', 'diagnostics', 'bank', 'mail', 'other', 'inbox'] as $expected) {
            self::assertContains($expected, $codes, 'catégorie système manquante : ' . $expected);
        }

        self::assertTrue($taxonomy->isActiveCategoryCode('charges.water'));
        self::assertFalse($taxonomy->isActiveCategoryCode('inconnue'));

        // Le seed est idempotent.
        $taxonomy->seedSystemCategories();
        self::assertCount(count($categories), $taxonomy->listActive());
    }
}
