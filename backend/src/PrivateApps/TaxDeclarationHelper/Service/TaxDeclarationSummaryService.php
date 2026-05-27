<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\TaxDeclarationHelper\Service;

use Caramagnols\PrivateApps\TaxDeclarationHelper\Repository\TaxDeclarationRepository;
use Caramagnols\PrivatePortal\TaxDeclarationHelper\Source\TaxDataSourceInterface;

final class TaxDeclarationSummaryService
{
    public const DISCLAIMER = 'Aide non officielle : cette synthese ne remplace pas une declaration fiscale ni un conseil professionnel.';

    /**
     * @param array<int, TaxDataSourceInterface> $sources
     */
    public function __construct(
        private readonly TaxDeclarationRepository $repository,
        private readonly array $sources
    ) {
    }

    /**
     * @param array<int, int> $scopeIds
     * @return array<string, mixed>
     */
    public function build(int $privateUserId, int $year, array $scopeIds): array
    {
        $this->repository->findOrCreateYear($privateUserId, $year);
        $manualEntries = $this->repository->listManualEntries($privateUserId, $year);
        $yearRow = $this->repository->findYear($privateUserId, $year) ?? [];
        $lines = [];
        $controls = [];
        $missingDocuments = [];
        $totals = [
            'manualIncome' => 0.0,
            'rentalIncome' => 0.0,
            'deductibleExpenses' => 0.0,
            'grossIncome' => 0.0,
            'controlsCount' => 0,
            'missingDocumentsCount' => 0,
        ];

        foreach ($manualEntries as $entry) {
            if (($entry['status'] ?? '') !== 'validated') {
                continue;
            }

            $amount = $this->amount($entry['amount'] ?? 0);
            $totals['manualIncome'] += $amount;
            $lines[] = [
                'sourceCode' => 'manual',
                'sourceLabel' => 'Revenus manuels',
                'lineType' => 'income',
                'label' => (string) ($entry['label'] ?? 'Revenu manuel'),
                'amount' => $amount,
                'sourceReference' => 'tax_manual_income_entries#' . (int) ($entry['id'] ?? 0),
                'metadata' => ['category' => (string) ($entry['category'] ?? '')],
            ];
        }

        foreach ($this->sources as $source) {
            $sourceControls = $source->controls($year, $scopeIds);
            foreach ($sourceControls as $control) {
                $control = trim((string) $control);
                if ($control === '') {
                    continue;
                }
                $controls[] = $control;
                $lines[] = [
                    'sourceCode' => $source->code(),
                    'sourceLabel' => $source->label(),
                    'lineType' => 'control',
                    'label' => $control,
                    'amount' => 0.0,
                    'sourceReference' => $source->code() . ':control',
                    'metadata' => [],
                ];
            }

            if ($sourceControls !== []) {
                continue;
            }

            $income = $source->annualRentalIncome($year, $scopeIds);
            if ($income->rentPaid > 0.0 || $income->validatedPaymentCount > 0) {
                $totals['rentalIncome'] += $income->rentPaid;
                $lines[] = [
                    'sourceCode' => $source->code(),
                    'sourceLabel' => $source->label(),
                    'lineType' => 'income',
                    'label' => 'Loyers encaisses',
                    'amount' => $income->rentPaid,
                    'sourceReference' => 'rental_payments',
                    'metadata' => ['sourceRows' => $income->sourceRows()],
                ];
            }

            $expenses = $source->annualDeductibleExpenses($year, $scopeIds);
            if ($expenses->deductibleCandidateExpenses > 0.0 || $expenses->validatedExpenseCount > 0) {
                $totals['deductibleExpenses'] += $expenses->deductibleCandidateExpenses;
                $lines[] = [
                    'sourceCode' => $source->code(),
                    'sourceLabel' => $source->label(),
                    'lineType' => 'expense',
                    'label' => 'Charges potentiellement deductibles',
                    'amount' => $expenses->deductibleCandidateExpenses,
                    'sourceReference' => 'rental_expenses',
                    'metadata' => ['sourceRows' => $expenses->sourceRows()],
                ];
            }

            foreach ($source->missingDocuments($year, $scopeIds) as $missingDocument) {
                $missingDocuments[] = $missingDocument->toArray();
                $lines[] = [
                    'sourceCode' => $source->code(),
                    'sourceLabel' => $source->label(),
                    'lineType' => 'document',
                    'label' => $missingDocument->label,
                    'amount' => 0.0,
                    'sourceReference' => $missingDocument->sourceReference,
                    'metadata' => $missingDocument->toArray(),
                ];
            }
        }

        $totals['manualIncome'] = round($totals['manualIncome'], 2);
        $totals['rentalIncome'] = round($totals['rentalIncome'], 2);
        $totals['deductibleExpenses'] = round($totals['deductibleExpenses'], 2);
        $totals['grossIncome'] = round($totals['manualIncome'] + $totals['rentalIncome'], 2);
        $totals['controlsCount'] = count($controls);
        $totals['missingDocumentsCount'] = count($missingDocuments);

        return [
            'privateUserId' => $privateUserId,
            'year' => $year,
            'locked' => ($yearRow['status'] ?? '') === 'locked',
            'officialDisclaimer' => self::DISCLAIMER,
            'totals' => $totals,
            'lines' => $lines,
            'manualEntries' => $manualEntries,
            'controls' => $controls,
            'missingDocuments' => $missingDocuments,
        ];
    }

    /**
     * @param array<int, int> $scopeIds
     * @return array<string, mixed>|null
     */
    public function generate(int $privateUserId, int $year, array $scopeIds, int $actorPrivateUserId): ?array
    {
        if ($this->repository->isYearLocked($privateUserId, $year)) {
            return null;
        }

        $summary = $this->build($privateUserId, $year, $scopeIds);
        $saved = $this->repository->saveSummary(
            $privateUserId,
            $year,
            is_array($summary['totals'] ?? null) ? $summary['totals'] : [],
            is_array($summary['lines'] ?? null) ? $summary['lines'] : [],
            $actorPrivateUserId
        );

        return is_array($saved) ? $summary : null;
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function csv(array $summary): string
    {
        $lines = is_array($summary['lines'] ?? null) ? $summary['lines'] : [];
        $rows = [['source', 'type', 'libelle', 'montant', 'origine']];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $rows[] = [
                (string) ($line['sourceLabel'] ?? ''),
                (string) ($line['lineType'] ?? ''),
                (string) ($line['label'] ?? ''),
                (string) $this->amount($line['amount'] ?? 0),
                (string) ($line['sourceReference'] ?? ''),
            ];
        }

        $output = [];
        foreach ($rows as $row) {
            $output[] = implode(';', array_map(
                static fn (string $cell): string => '"' . str_replace('"', '""', $cell) . '"',
                $row
            ));
        }

        return "\xEF\xBB\xBF" . implode("\n", $output) . "\n";
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function pdf(array $summary): string
    {
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
        $year = is_numeric($summary['year'] ?? null) ? (int) $summary['year'] : (int) date('Y');

        return sprintf(
            "%%PDF-1.4\n%% Caramagnols tax helper export\nAide impots %d\nRevenus bruts: %.2f EUR\nCharges candidates: %.2f EUR\n%s\n%%%%EOF\n",
            $year,
            $this->amount($totals['grossIncome'] ?? 0),
            $this->amount($totals['deductibleExpenses'] ?? 0),
            self::DISCLAIMER
        );
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}
