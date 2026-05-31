<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service\AgencyAdvancedReconciliationService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyAdvancedReconciliationServiceTest extends TestCase
{
    public function testSummarizesTransfersDocumentsAndReviewGaps(): void
    {
        $summary = (new AgencyAdvancedReconciliationService())->summarizeDocument([
            'privateDocumentId' => 'private-doc-001',
            'storagePath' => 'private/agency/source.pdf',
            'textExtractionStatus' => ExtractedTextResult::STATUS_EXTRACTED,
            'lines' => [
                [
                    'rawLabel' => 'Loyer',
                    'mappedCategory' => 'rent_income',
                    'mappingStatus' => 'validated',
                    'paidAmount' => 700.0,
                    'periodStart' => '2026-02-01',
                    'periodEnd' => '2026-02-28',
                ],
                [
                    'rawLabel' => 'Honoraires',
                    'mappedCategory' => 'agency_management_fee',
                    'mappingStatus' => 'review',
                    'debitAmount' => 70.0,
                    'periodStart' => '2026-02-01',
                    'periodEnd' => '2026-02-28',
                ],
                [
                    'rawLabel' => 'Règlement Virement',
                    'mappedCategory' => 'owner_transfer',
                    'mappingStatus' => 'suggested',
                    'ownerTransferAmount' => 620.0,
                ],
                [
                    'rawLabel' => 'Ligne inconnue',
                    'mappedCategory' => 'other',
                    'mappingStatus' => 'review',
                    'amount' => 12.0,
                ],
            ],
        ]);

        $this->assertSame(700.0, $summary['incomeAmount']);
        $this->assertSame(70.0, $summary['expenseAmount']);
        $this->assertSame(630.0, $summary['netBeforeTransfer']);
        $this->assertSame(620.0, $summary['ownerTransferAmount']);
        $this->assertSame(10.0, $summary['transferDelta']);
        $this->assertTrue((bool) $summary['sourceDocumentRetained']);
        $this->assertFalse((bool) $summary['manualEntryRequired']);
        $this->assertSame(1, $summary['unclassifiedLineCount']);
        $this->assertSame(1, $summary['sensitiveAwaitingReviewCount']);
        $this->assertSame('review_required', $summary['status']);
    }

    public function testFlagsManualEntryQueueWhenNoTextLinesAreAvailable(): void
    {
        $summary = (new AgencyAdvancedReconciliationService())->summarizeDocument([
            'privateDocumentId' => 'private-doc-ocr',
            'textExtractionStatus' => ExtractedTextResult::STATUS_NEEDS_OCR_OR_MANUAL_ENTRY,
            'lines' => [],
        ]);

        $this->assertTrue((bool) $summary['manualEntryRequired']);
        $this->assertSame('manual_entry_required', $summary['status']);
    }
}
