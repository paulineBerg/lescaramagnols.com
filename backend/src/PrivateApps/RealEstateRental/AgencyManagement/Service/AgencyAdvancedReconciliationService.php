<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Service;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyFiscalReviewPolicy;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;

final class AgencyAdvancedReconciliationService
{
    /**
     * @var array<int, string>
     */
    private const INCOME_CATEGORIES = [
        'rent_income',
        'charge_provision_income',
        'recoverable_tax_income',
    ];

    /**
     * @var array<int, string>
     */
    private const EXPENSE_CATEGORIES = [
        'recoverable_charge_adjustment',
        'recoverable_utility_charge',
        'agency_management_fee',
        'agency_fee_vat',
        'agency_letting_fee',
        'insurance_unpaid_rent',
        'property_tax_service_fee',
        'works_expense',
        'copro_work_fund',
        'condominium_current_charge',
    ];

    public function __construct(private readonly ?AgencyFiscalReviewPolicy $policy = null)
    {
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public function summarizeDocument(array $document): array
    {
        $policy = $this->policy ?? new AgencyFiscalReviewPolicy();
        $lines = $this->lines($document);
        $incomeAmount = 0.0;
        $expenseAmount = 0.0;
        $ownerTransferAmount = 0.0;
        $missingPeriodLineCount = 0;
        $unclassifiedLineCount = 0;
        $sensitiveLineCount = 0;
        $sensitiveAwaitingReviewCount = 0;
        $lineSignatures = [];

        foreach ($lines as $line) {
            $category = strtolower(trim((string) ($line['mappedCategory'] ?? '')));
            if ($policy->isUnclassified($category)) {
                ++$unclassifiedLineCount;
            }

            if ($policy->needsFiscalPeriod($category) && !$this->hasPeriod($line)) {
                ++$missingPeriodLineCount;
            }

            if ($policy->requiresManualFiscalReview($category)) {
                ++$sensitiveLineCount;
                if ((string) ($line['mappingStatus'] ?? '') !== 'validated') {
                    ++$sensitiveAwaitingReviewCount;
                }
            }

            if (in_array($category, self::INCOME_CATEGORIES, true)) {
                $incomeAmount += $this->lineIncomeAmount($line);
            } elseif (in_array($category, self::EXPENSE_CATEGORIES, true)) {
                $expenseAmount += $this->lineExpenseAmount($line);
            } elseif ($category === 'owner_transfer') {
                $ownerTransferAmount += $this->lineOwnerTransferAmount($line);
            }

            $signature = $this->lineSignature($line, $category);
            if ($signature !== '') {
                $lineSignatures[$signature] ??= 0;
                ++$lineSignatures[$signature];
            }
        }

        $duplicateCandidateCount = 0;
        foreach ($lineSignatures as $count) {
            if ($count > 1) {
                $duplicateCandidateCount += $count - 1;
            }
        }

        $netBeforeTransfer = round($incomeAmount - $expenseAmount, 2);
        $transferDelta = round($netBeforeTransfer - $ownerTransferAmount, 2);
        $manualEntryRequired = $this->manualEntryRequired($document, $lines);
        $sourceDocumentRetained = trim((string) ($document['storagePath'] ?? '')) !== ''
            || trim((string) ($document['privateDocumentId'] ?? '')) !== '';

        return [
            'lineCount' => count($lines),
            'incomeAmount' => round($incomeAmount, 2),
            'expenseAmount' => round($expenseAmount, 2),
            'netBeforeTransfer' => $netBeforeTransfer,
            'ownerTransferAmount' => round($ownerTransferAmount, 2),
            'transferDelta' => $transferDelta,
            'sourceDocumentRetained' => $sourceDocumentRetained,
            'manualEntryRequired' => $manualEntryRequired,
            'duplicateCandidateCount' => $duplicateCandidateCount,
            'missingPeriodLineCount' => $missingPeriodLineCount,
            'unclassifiedLineCount' => $unclassifiedLineCount,
            'sensitiveLineCount' => $sensitiveLineCount,
            'sensitiveAwaitingReviewCount' => $sensitiveAwaitingReviewCount,
            'status' => $this->status(
                $manualEntryRequired,
                $sourceDocumentRetained,
                $duplicateCandidateCount,
                $missingPeriodLineCount,
                $unclassifiedLineCount,
                $sensitiveAwaitingReviewCount,
                $transferDelta
            ),
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $document): array
    {
        if (!is_array($document['lines'] ?? null)) {
            return [];
        }

        $lines = [];
        foreach ($document['lines'] as $line) {
            if (is_array($line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $line
     */
    private function lineIncomeAmount(array $line): float
    {
        $calledAmount = $this->amount($line['calledAmount'] ?? null);
        $paidAmount = $this->amount($line['paidAmount'] ?? null);
        $creditAmount = $this->amount($line['creditAmount'] ?? null);
        $amount = $this->amount($line['amount'] ?? null);

        if ($paidAmount > 0.0) {
            return $paidAmount;
        }

        return $creditAmount > 0.0 ? $creditAmount : ($calledAmount > 0.0 ? $calledAmount : $amount);
    }

    /**
     * @param array<string, mixed> $line
     */
    private function lineExpenseAmount(array $line): float
    {
        $debitAmount = abs($this->amount($line['debitAmount'] ?? null));
        $amount = abs($this->amount($line['amount'] ?? null));

        return $debitAmount > 0.0 ? $debitAmount : $amount;
    }

    /**
     * @param array<string, mixed> $line
     */
    private function lineOwnerTransferAmount(array $line): float
    {
        $ownerTransferAmount = abs($this->amount($line['ownerTransferAmount'] ?? null));
        $creditAmount = abs($this->amount($line['creditAmount'] ?? null));
        $amount = abs($this->amount($line['amount'] ?? null));

        return $ownerTransferAmount > 0.0 ? $ownerTransferAmount : ($creditAmount > 0.0 ? $creditAmount : $amount);
    }

    /**
     * @param array<string, mixed> $line
     */
    private function lineSignature(array $line, string $category): string
    {
        $label = mb_strtolower(trim((string) ($line['rawLabel'] ?? '')), 'UTF-8');
        $periodStart = trim((string) ($line['periodStart'] ?? ''));
        $periodEnd = trim((string) ($line['periodEnd'] ?? ''));
        $amount = number_format($this->amount($line['amount'] ?? null), 2, '.', '');

        return trim($category . '|' . $periodStart . '|' . $periodEnd . '|' . $amount . '|' . $label, '|');
    }

    /**
     * @param array<string, mixed> $line
     */
    private function hasPeriod(array $line): bool
    {
        return $this->isSqlDate($line['periodStart'] ?? null)
            || $this->isSqlDate($line['periodEnd'] ?? null)
            || $this->isSqlDate($line['lineDate'] ?? null);
    }

    /**
     * @param array<string, mixed> $document
     * @param array<int, array<string, mixed>> $lines
     */
    private function manualEntryRequired(array $document, array $lines): bool
    {
        $status = trim((string) ($document['textExtractionStatus'] ?? ''));

        return $lines === []
            && in_array($status, [
                ExtractedTextResult::STATUS_NEEDS_OCR_OR_MANUAL_ENTRY,
                ExtractedTextResult::STATUS_UNSUPPORTED,
                ExtractedTextResult::STATUS_FAILED,
            ], true);
    }

    private function status(
        bool $manualEntryRequired,
        bool $sourceDocumentRetained,
        int $duplicateCandidateCount,
        int $missingPeriodLineCount,
        int $unclassifiedLineCount,
        int $sensitiveAwaitingReviewCount,
        float $transferDelta
    ): string {
        if ($manualEntryRequired) {
            return 'manual_entry_required';
        }

        if (!$sourceDocumentRetained) {
            return 'source_document_missing';
        }

        if (
            $duplicateCandidateCount > 0
            || $missingPeriodLineCount > 0
            || $unclassifiedLineCount > 0
            || $sensitiveAwaitingReviewCount > 0
            || abs($transferDelta) > 0.05
        ) {
            return 'review_required';
        }

        return 'ready';
    }

    private function isSqlDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1;
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}
