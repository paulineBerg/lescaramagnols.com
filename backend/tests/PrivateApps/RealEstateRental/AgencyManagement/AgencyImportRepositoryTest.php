<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyImportPreviewService;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\DocumentTextExtractorInterface;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyImportRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyImportRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testCreatesAndListsAgenciesWithoutImportedDocument(): void
    {
        $repository = new AgencyImportRepository($this->editorialSqlDatabase());

        $this->assertTrue($repository->createAgency(1, 'ASG IMMOBILIER'));
        $this->assertTrue($repository->createAgency(1, 'ASG IMMOBILIER'));
        $this->assertTrue($repository->createAgency(2, 'Autre agence'));

        $agencies = $repository->listAgencies(1);
        $this->assertCount(1, $agencies);
        $this->assertSame('ASG IMMOBILIER', $agencies[0]['name'] ?? null);
        $this->assertSame(1, $agencies[0]['batchCount'] ?? null);
        $this->assertSame(0, $agencies[0]['fileCount'] ?? null);

        $batch = $repository->createBatch(1, 'ASG IMMOBILIER', '/tmp/agence', 1, 0, 0, 'review');
        $this->assertNotNull($batch);

        $agencies = $repository->listAgencies(1);
        $this->assertCount(1, $agencies);
        $this->assertSame(2, $agencies[0]['batchCount'] ?? null);
        $this->assertSame(1, $agencies[0]['fileCount'] ?? null);
        $this->assertSame(0, $agencies[0]['ignoredFileCount'] ?? null);
        $this->assertSame(0, $agencies[0]['duplicateFileCount'] ?? null);
    }

    public function testAgencyUnitMappingAutoAssignsImportedLinesPerAgency(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $repository = new AgencyImportRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'agency-unit-map@example.com');
        $property = $propertyRepository->create(
            $ownerId,
            'Les Caramagnols P',
            '2738 route de la Mole',
            'immeuble',
            'pleine propriete',
            'active'
        );
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Arbousier', 34.0, true, 'available', null, $ownerId, 'apartment');
        $this->assertNotNull($unit);

        $this->assertTrue($repository->createAgency($ownerId, 'ASG IMMOBILIER'));
        $this->assertTrue($repository->createUnitMapping($ownerId, 'ASG IMMOBILIER', 'EVE Hervé', $property->id, $unit->id));
        $mappings = $repository->listUnitMappings($ownerId);
        $this->assertCount(1, $mappings);
        $this->assertSame('ASG IMMOBILIER', $mappings[0]['agencyName'] ?? null);
        $this->assertSame('EVE Hervé', $mappings[0]['matchText'] ?? null);
        $this->assertSame('Arbousier', $mappings[0]['unitLabel'] ?? null);

        $batch = $repository->createBatch($ownerId, 'ASG IMMOBILIER', '/tmp/agence', 1);
        $this->assertNotNull($batch);
        $sourcePath = tempnam(sys_get_temp_dir(), 'agency-unit-map-');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, 'unit mapping pdf bytes');

        try {
            $preview = (new AgencyImportPreviewService($this->textExtractorReturning($this->asgMultiLotText())))->preview(
                $sourcePath,
                'releve-gerance-multi-lot.pdf',
                'text/plain'
            );
            $document = $repository->persistPreview($batch->id, $preview, 'private-doc-unit-map', 'ASG IMMOBILIER');
            $this->assertNotNull($document);
            $statement = $repository->findStatementByImportedDocumentId($document->id);
            $this->assertNotNull($statement);

            $lines = $repository->listStatementLines($statement->id);
            $unitsByAmount = [];
            foreach ($lines as $line) {
                $amount = $line->amount !== null ? sprintf('%.2f', $line->amount) : '';
                $unitsByAmount[$amount] = $line->rentalUnitId;
            }

            $this->assertSame($unit->id, $unitsByAmount['7.41'] ?? null);
            $this->assertSame($unit->id, $unitsByAmount['842.59'] ?? null);
            $this->assertNull($unitsByAmount['900.00'] ?? null);
        } finally {
            @unlink($sourcePath);
        }

        $mappingId = is_numeric($mappings[0]['id'] ?? null) ? (int) $mappings[0]['id'] : 0;
        $this->assertTrue($repository->deleteUnitMappingForUser($ownerId, $mappingId));
        $this->assertSame([], $repository->listUnitMappings($ownerId));
    }

    public function testPersistsAgencyImportPreviewWithStatementLinesAndBlocksDuplicateSha(): void
    {
        $repository = new AgencyImportRepository($this->editorialSqlDatabase());
        $batch = $repository->createBatch(1, 'ASG IMMOBILIER', '/tmp/agence', 1);
        $this->assertNotNull($batch);

        $sourcePath = tempnam(sys_get_temp_dir(), 'agency-import-repo-');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, 'same original pdf bytes');

        try {
            $preview = (new AgencyImportPreviewService($this->textExtractorReturning($this->asgText())))->preview(
                $sourcePath,
                'releve-gerance.pdf',
                'text/plain'
            );

            $document = $repository->persistPreview(
                $batch->id,
                $preview,
                'private-doc-001',
                'ASG IMMOBILIER',
                'uploads/aa/bb/private-doc-001.txt'
            );
            $this->assertNotNull($document);
            $this->assertSame('uploads/aa/bb/private-doc-001.txt', $document->storagePath);
            $this->assertSame(AgencyDocumentType::ASG_MANAGEMENT_STATEMENT, $document->detectedDocumentType);
            $this->assertSame('asg-releve-gerance-v1', $document->parserProfile);
            $this->assertTrue($document->containsSensitiveData);

            $statement = $repository->findStatementByImportedDocumentId($document->id);
            $this->assertNotNull($statement);
            $this->assertSame('2025-02-01', $statement->statementPeriodStart);
            $this->assertSame('411QUINETJ', $statement->ownerAccountReference);

            $lines = $repository->listStatementLines($statement->id);
            $this->assertCount(2, $lines);
            $this->assertSame(['rent_income', 'owner_transfer'], array_map(
                static fn ($line): string => $line->mappedCategory,
                $lines
            ));

            $this->assertNotNull($repository->findImportedDocumentBySha256((string) $preview->sha256));
            $this->assertNull($repository->persistPreview($batch->id, $preview, 'private-doc-duplicate', 'ASG IMMOBILIER'));

            $this->assertTrue($repository->updateStatementPropertyForDocument(1, $document->id, 42));
            $reviewDocument = $repository->reviewDocumentForUser(1, $document->id);
            $this->assertIsArray($reviewDocument);
            $this->assertSame(42, $reviewDocument['rentalPropertyId'] ?? null);
            $this->assertCount(2, $reviewDocument['lines'] ?? []);
            $reviewLines = is_array($reviewDocument['lines'] ?? null) ? $reviewDocument['lines'] : [];
            $this->assertSame([42, 42], array_map(
                static fn (mixed $line): ?int => is_array($line) && is_int($line['rentalPropertyId'] ?? null)
                    ? $line['rentalPropertyId']
                    : null,
                $reviewLines
            ));

            $this->assertTrue($repository->updateStatementPropertyForDocument(1, $document->id, 43));
            $reviewDocument = $repository->reviewDocumentForUser(1, $document->id);
            $this->assertIsArray($reviewDocument);
            $this->assertSame(43, $reviewDocument['rentalPropertyId'] ?? null);
            $reviewLines = is_array($reviewDocument['lines'] ?? null) ? $reviewDocument['lines'] : [];
            $this->assertSame([43, 43], array_map(
                static fn (mixed $line): ?int => is_array($line) && is_int($line['rentalPropertyId'] ?? null)
                    ? $line['rentalPropertyId']
                    : null,
                $reviewLines
            ));

            $corrected = $repository->reviewStatementLine(1, $lines[1]->id, 'correct', [
                'mapped_category' => 'agency_management_fee',
                'period_start' => '2025-02-01',
                'period_end' => '2025-02-28',
                'amount' => '24,50',
                'debit_amount' => '24,50',
                'credit_amount' => '',
            ]);
            $this->assertNotNull($corrected);
            $this->assertSame('agency_management_fee', $corrected->mappedCategory);
            $this->assertSame('review', $corrected->mappingStatus);
            $this->assertSame(24.5, $corrected->debitAmount);

            $validated = $repository->reviewStatementLine(1, $lines[0]->id, 'validate', [
                'rental_property_id' => '84',
                'rental_unit_id' => '12',
                'mapped_category' => 'rent_income',
                'period_start' => '2025-02-01',
                'period_end' => '2025-02-28',
                'amount' => '662,87',
                'debit_amount' => '',
                'credit_amount' => '662,87',
            ]);
            $ignored = $repository->reviewStatementLine(1, $lines[1]->id, 'ignore');
            $this->assertNotNull($validated);
            $this->assertNotNull($ignored);
            $this->assertSame('validated', $validated->mappingStatus);
            $this->assertSame(84, $validated->rentalPropertyId);
            $this->assertSame(12, $validated->rentalUnitId);
            $this->assertSame('ignored', $ignored->mappingStatus);

            $this->assertTrue($repository->updateStatementPropertyForDocument(1, $document->id, 42));
            $reviewDocument = $repository->reviewDocumentForUser(1, $document->id);
            $this->assertIsArray($reviewDocument);
            $reviewLines = is_array($reviewDocument['lines'] ?? null) ? $reviewDocument['lines'] : [];
            $reviewLinesById = [];
            foreach ($reviewLines as $reviewLine) {
                if (!is_array($reviewLine) || !is_int($reviewLine['id'] ?? null)) {
                    continue;
                }

                $reviewLinesById[$reviewLine['id']] = $reviewLine;
            }
            $this->assertSame(84, $reviewLinesById[$lines[0]->id]['rentalPropertyId'] ?? null);
            $this->assertSame(12, $reviewLinesById[$lines[0]->id]['rentalUnitId'] ?? null);
            $this->assertSame(42, $reviewLinesById[$lines[1]->id]['rentalPropertyId'] ?? null);

            $fiscalLines = $repository->listValidatedFiscalLines(2025, [42]);
            $this->assertSame([], $fiscalLines);
            $fiscalLines = $repository->listValidatedFiscalLines(2025, [84]);
            $this->assertCount(1, $fiscalLines);
            $this->assertSame('rent_income', $fiscalLines[0]['mapped_category'] ?? null);
            $this->assertSame(84, (int) ($fiscalLines[0]['rental_property_id'] ?? 0));
            $this->assertSame(12, (int) ($fiscalLines[0]['rental_unit_id'] ?? 0));

            $reviewedDocument = $repository->reviewDocumentForUser(1, $document->id);
            $this->assertIsArray($reviewedDocument);
            $this->assertSame('validated', $reviewedDocument['reviewStatus'] ?? null);

            $this->assertNull($repository->deleteImportedDocumentForUser(2, $document->id));
            $this->assertNotNull($repository->findImportedDocumentById($document->id));

            $deleted = $repository->deleteImportedDocumentForUser(1, $document->id);
            $this->assertNotNull($deleted);
            $this->assertSame('private-doc-001', $deleted->privateDocumentId);
            $this->assertNull($repository->findImportedDocumentById($document->id));
            $this->assertNull($repository->findStatementByImportedDocumentId($document->id));
            $this->assertSame([], $repository->listStatementLines($statement->id));
            $this->assertSame([], $repository->listIssues($document->id));
            $this->assertSame([], $repository->listRecentDocumentsForUser(1));
            $deletedBatch = $repository->findBatchById($batch->id);
            $this->assertNotNull($deletedBatch);
            $this->assertSame(0, $deletedBatch->fileCount);
            $this->assertSame('cancelled', $deletedBatch->status);
            $this->assertSame([], $repository->listRecentBatches(1));
        } finally {
            @unlink($sourcePath);
        }
    }

    public function testAutoAssignsImportedLinesToRentalUnitsFromTenantNamesAndChargeTotals(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $repository = new AgencyImportRepository($database);

        $ownerId = $this->createPrivateUser($userRepository, 'agency-auto-match@example.com');
        $property = $propertyRepository->create(
            $ownerId,
            'Les Caramagnols P',
            '2738 route de la Mole',
            'immeuble',
            'pleine propriete',
            'active'
        );
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unitOne = $unitRepository->create($property->id, 'Appartement Eve', 34.0, true, 'available', null, $ownerId, 'apartment');
        $unitThree = $unitRepository->create($property->id, 'Appartement Fournajoux', 38.0, true, 'available', null, $ownerId, 'apartment');
        $this->assertNotNull($unitOne);
        $this->assertNotNull($unitThree);
        $tenantOne = $lifecycleRepository->createTenant($property->id, $unitOne->id, 'Herve EVE', null, null, 'validated', $ownerId);
        $tenantThree = $lifecycleRepository->createTenant($property->id, $unitThree->id, 'Delphine FOURNAJOUX', null, null, 'validated', $ownerId);
        $this->assertIsArray($tenantOne);
        $this->assertIsArray($tenantThree);
        $this->assertIsArray($lifecycleRepository->createLease($property->id, $unitOne->id, (int) $tenantOne['id'], '2026-01-01', null, 857.41, 0.0, 'validated', $ownerId));
        $this->assertIsArray($lifecycleRepository->createLease($property->id, $unitThree->id, (int) $tenantThree['id'], '2026-01-01', null, 900.0, 0.0, 'validated', $ownerId));

        $batch = $repository->createBatch($ownerId, 'ASG IMMOBILIER', '/tmp/agence', 1);
        $this->assertNotNull($batch);
        $sourcePath = tempnam(sys_get_temp_dir(), 'agency-import-auto-match-');
        $this->assertIsString($sourcePath);
        file_put_contents($sourcePath, 'auto match pdf bytes');

        try {
            $preview = (new AgencyImportPreviewService($this->textExtractorReturning($this->asgMultiLotText())))->preview(
                $sourcePath,
                'releve-gerance-multi-lot.pdf',
                'text/plain'
            );
            $document = $repository->persistPreview($batch->id, $preview, 'private-doc-auto-match', 'ASG IMMOBILIER');
            $this->assertNotNull($document);
            $statement = $repository->findStatementByImportedDocumentId($document->id);
            $this->assertNotNull($statement);

            $lines = $repository->listStatementLines($statement->id);
            $this->assertCount(8, $lines);
            $unitsByAmount = [];
            foreach ($lines as $line) {
                $amount = $line->amount !== null ? sprintf('%.2f', $line->amount) : '';
                $unitsByAmount[$amount] = $line->rentalUnitId;
            }

            $this->assertSame($unitOne->id, $unitsByAmount['7.41'] ?? null);
            $this->assertSame($unitOne->id, $unitsByAmount['842.59'] ?? null);
            $this->assertSame($unitThree->id, $unitsByAmount['900.00'] ?? null);
            $this->assertSame($unitOne->id, $unitsByAmount['59.50'] ?? null);
            $this->assertSame($unitOne->id, $unitsByAmount['11.90'] ?? null);
            $this->assertSame($unitThree->id, $unitsByAmount['63.00'] ?? null);
            $this->assertSame($unitThree->id, $unitsByAmount['12.60'] ?? null);
            $this->assertSame($unitOne->id, $unitsByAmount['30.01'] ?? null);
        } finally {
            @unlink($sourcePath);
        }
    }

    private function textExtractorReturning(string $text): DocumentTextExtractorInterface
    {
        return new class ($text) implements DocumentTextExtractorInterface {
            public function __construct(private readonly string $text)
            {
            }

            public function supports(string $path, string $mimeType): bool
            {
                return is_file($path) && $mimeType === 'text/plain';
            }

            public function extract(string $path): ExtractedTextResult
            {
                return new ExtractedTextResult(ExtractedTextResult::STATUS_EXTRACTED, $this->text, 0, '');
            }
        };
    }

    private function asgText(): string
    {
        return <<<'TEXT'
Relevé de gérance
Numéro de compte       411QUINETJ
Code d'accès : QUINETJULIETTE
ASG IMMOBILIER
Période du 01/02/2025 au 28/02/2025 - Fév 2025
IMMEUBLE - Villa CARENA COGOLIN                                                        Quittancé     Recettes      Dépenses
Lot 1 Appartement
AMIROUCHEN Luc
Loyer                                                                               662,87        662,87
Règlement Virement                                                                               548,48
TEXT;
    }

    private function asgMultiLotText(): string
    {
        return <<<'TEXT'
Relevé de gérance
Numéro de compte       411BERGONP
Libellé                BERGON Gérard
Le 05/02/2026
ASG IMMOBILIER
Période du 01/01/2026 au 31/01/2026 - Jan 2026
IMMEUBLE - Les Caramagnols COGOLIN                                                  Quittancé      Recettes      Dépenses
Lot 1 Appartement
EVE Hervé (Solde débiteur : 14,82)
Période Déc 2025
Loyer                                                                                          7,41
Période Jan 2026
Loyer                                                                         857,41        842,59
Total lot        857,41        850,00
Lot 3 Appartement
FOURNAJOUX Delphine
Période Jan 2026
Loyer                                                                            900,00        900,00
Total lot        900,00        900,00
Dépenses de l'immeuble
Honoraires de gestion Jan 2026 (850 x 7%)                                                                   59,50
TVA sur Honoraires de gestion Jan 2026                                                                      11,90
Honoraires de gestion Jan 2026 (900 x 7%)                                                                   63,00
TVA sur Honoraires de gestion Jan 2026                                                                      12,60
ASSURANCE MILA Jan 2026 (857,41 x 3,5%)                                                                     30,01
TEXT;
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }
}
