<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Service;

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use ZipArchive;

final class RentalExportService
{
    public const KIND_SUMMARY = 'summary';
    public const KIND_RENTS = 'rents';
    public const KIND_EXPENSES = 'expenses';
    public const KIND_PROPERTY_ANNUAL = 'property_annual';
    public const KIND_TENANT_RECAP = 'tenant_recap';
    public const KIND_PROPERTY_DOCUMENTS = 'property_documents';

    private const MAX_EXPORT_ROWS = 5000;
    private const EXPORT_TTL_SECONDS = 86400;

    public function __construct(
        private readonly RentalLifecycleRepository $repository,
        private readonly RentalAnnualSummaryService $annualSummaryService,
        private readonly PrivateDocumentStorage $storage
    ) {
    }

    /**
     * @param array<int, int> $authorizedPropertyIds
     * @return array<string, mixed>
     */
    public function create(
        int $privateUserId,
        int $year,
        string $kind,
        array $authorizedPropertyIds,
        ?int $propertyId = null,
        ?int $tenantId = null
    ): array {
        $year = $this->normalizeYear($year);
        $requestedKind = strtolower(trim($kind));
        $summaryFormat = str_contains($requestedKind, 'csv') ? 'csv' : 'pdf';
        $kind = $this->normalizeKind($requestedKind);
        $authorizedPropertyIds = $this->normalizeIds($authorizedPropertyIds);

        if ($privateUserId <= 0 || $authorizedPropertyIds === []) {
            return $this->failure('forbidden');
        }

        if ($this->isFinancialKind($kind)) {
            $summary = $this->annualSummaryService->build($year, $authorizedPropertyIds);
            if (!empty($summary['blocked'])) {
                return $this->failure('draft_data', ['summary' => $summary]);
            }
        } else {
            $summary = [
                'year' => $year,
                'kind' => $kind,
            ];
        }

        $export = match ($kind) {
            self::KIND_RENTS => $this->rentsCsv($year, $authorizedPropertyIds),
            self::KIND_EXPENSES => $this->expensesCsv($year, $authorizedPropertyIds),
            self::KIND_PROPERTY_ANNUAL => $this->propertyAnnualPdf($year, $authorizedPropertyIds, (int) $propertyId),
            self::KIND_TENANT_RECAP => $this->tenantRecapPdf($year, $authorizedPropertyIds, (int) $tenantId),
            self::KIND_PROPERTY_DOCUMENTS => $this->propertyDocumentsZip($year, $authorizedPropertyIds, (int) $propertyId),
            default => $this->summaryExport($summary, $year, $summaryFormat),
        };

        if (!($export['success'] ?? false)) {
            return $export;
        }

        $payload = [
            'kind' => $kind,
            'format' => $export['format'],
            'filename' => $export['filename'],
            'propertyId' => $propertyId,
            'tenantId' => $tenantId,
            'sha256' => $export['sha256'],
            'sizeBytes' => $export['sizeBytes'],
            'summary' => $summary,
        ];
        $this->repository->createExportLog(
            $privateUserId,
            $year,
            (string) $export['format'],
            $payload,
            $kind
        );

        return $export + [
            'kind' => $kind,
            'year' => $year,
        ];
    }

    /**
     * @param array<string, mixed> $export
     */
    public function cleanup(array $export): void
    {
        $path = is_string($export['path'] ?? null) ? (string) $export['path'] : '';
        $exportsRoot = $this->exportsRoot();
        if ($path === '' || $exportsRoot === '' || !str_starts_with(str_replace('\\', '/', $path), $exportsRoot . '/')) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    private function rentsCsv(int $year, array $propertyIds): array
    {
        $rows = [
            [
                'propriete',
                'bien',
                'locataire',
                'annee',
                'mois',
                'echeance',
                'loyer_attendu',
                'loyer_encaisse',
                'reste_du',
                'statut',
            ],
        ];

        foreach ($this->repository->listRents($propertyIds, $year, self::MAX_EXPORT_ROWS) as $rent) {
            $amountDue = $this->amount($rent['amountDue'] ?? 0);
            $amountPaid = $this->amount($rent['amountPaid'] ?? 0);
            $rows[] = [
                (string) ($rent['propertyName'] ?? ''),
                (string) ($rent['unitLabel'] ?? ''),
                (string) ($rent['tenantName'] ?? ''),
                (string) (int) ($rent['periodYear'] ?? $year),
                sprintf('%02d', (int) ($rent['periodMonth'] ?? 0)),
                (string) ($rent['dueDate'] ?? ''),
                $this->money($amountDue),
                $this->money($amountPaid),
                $this->money(max(0.0, $amountDue - $amountPaid)),
                (string) ($rent['status'] ?? ''),
            ];
        }

        return $this->writeExport(
            $this->csv($rows),
            sprintf('loyers-%d.csv', $year),
            'csv',
            'text/csv; charset=UTF-8'
        );
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    private function expensesCsv(int $year, array $propertyIds): array
    {
        $rows = [
            [
                'propriete',
                'bien',
                'date',
                'annee_fiscale',
                'categorie',
                'libelle',
                'montant',
                'recuperable',
                'candidate_deductible',
                'statut',
                'justificatifs',
            ],
        ];

        foreach ($this->repository->listExpenses($propertyIds, $year, self::MAX_EXPORT_ROWS) as $expense) {
            $rows[] = [
                (string) ($expense['propertyName'] ?? ''),
                (string) ($expense['unitLabel'] ?? ''),
                (string) ($expense['expenseDate'] ?? ''),
                (string) (int) ($expense['taxYear'] ?? $year),
                (string) ($expense['expenseCategory'] ?? ''),
                (string) ($expense['label'] ?? ''),
                $this->money($expense['amount'] ?? 0),
                ((int) ($expense['isRecoverable'] ?? 0)) === 1 ? 'oui' : 'non',
                ((int) ($expense['isDeductibleCandidate'] ?? 0)) === 1 ? 'oui' : 'non',
                (string) ($expense['status'] ?? ''),
                (string) (int) ($expense['supportingDocumentCount'] ?? 0),
            ];
        }

        return $this->writeExport(
            $this->csv($rows),
            sprintf('charges-%d.csv', $year),
            'csv',
            'text/csv; charset=UTF-8'
        );
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    private function propertyAnnualPdf(int $year, array $propertyIds, int $propertyId): array
    {
        if ($propertyId <= 0 || !in_array($propertyId, $propertyIds, true)) {
            return $this->failure('forbidden');
        }

        $rents = array_values(array_filter(
            $this->repository->listRents([$propertyId], $year, self::MAX_EXPORT_ROWS),
            static fn (array $rent): bool => in_array((string) ($rent['status'] ?? ''), ['pending', 'partial', 'paid', 'late', 'validated'], true)
        ));
        $payments = array_values(array_filter(
            $this->repository->listPayments([$propertyId], $year, self::MAX_EXPORT_ROWS),
            static fn (array $payment): bool => ($payment['status'] ?? '') === 'validated'
        ));
        $expenses = array_values(array_filter(
            $this->repository->listExpenses([$propertyId], $year, self::MAX_EXPORT_ROWS),
            static fn (array $expense): bool => ($expense['status'] ?? '') === 'validated'
        ));

        $propertyName = $this->firstValue($rents, 'propertyName')
            ?: $this->firstValue($expenses, 'propertyName')
            ?: 'Propriete #' . $propertyId;
        $rentDue = 0.0;
        foreach ($rents as $rent) {
            $rentDue += $this->amount($rent['amountDue'] ?? 0);
        }
        $rentPaid = 0.0;
        foreach ($payments as $payment) {
            $amount = $this->amount($payment['amountPaid'] ?? 0);
            $rentPaid += ($payment['paymentKind'] ?? '') === 'refund' ? -$amount : $amount;
        }
        $recoverableExpenses = 0.0;
        $deductibleCandidateExpenses = 0.0;
        $nonDeductibleExpenses = 0.0;
        foreach ($expenses as $expense) {
            $amount = $this->amount($expense['amount'] ?? 0);
            if ((int) ($expense['isRecoverable'] ?? 0) === 1) {
                $recoverableExpenses += $amount;
            }
            if ((int) ($expense['isDeductibleCandidate'] ?? 0) === 1) {
                $deductibleCandidateExpenses += $amount;
            } else {
                $nonDeductibleExpenses += $amount;
            }
        }

        $content = sprintf(
            "%%PDF-1.4\n%% Caramagnols private rental property annual export\nSynthese annuelle par bien %d\nPropriete: %s\nLoyers attendus: %.2f EUR\nLoyers encaisses: %.2f EUR\nReste du: %.2f EUR\nCharges recuperables: %.2f EUR\nCharges deductibles candidates: %.2f EUR\nCharges non deductibles: %.2f EUR\nLignes de loyers: %d\nLignes de charges: %d\n%%EOF\n",
            $year,
            $propertyName,
            round($rentDue, 2),
            round($rentPaid, 2),
            round(max(0.0, $rentDue - $rentPaid), 2),
            round($recoverableExpenses, 2),
            round($deductibleCandidateExpenses, 2),
            round($nonDeductibleExpenses, 2),
            count($rents),
            count($expenses)
        );

        return $this->writeExport(
            $content,
            sprintf('synthese-bien-%d-%d.pdf', $propertyId, $year),
            'pdf',
            'application/pdf'
        );
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    private function tenantRecapPdf(int $year, array $propertyIds, int $tenantId): array
    {
        if ($tenantId <= 0) {
            return $this->failure('forbidden');
        }

        $leases = array_values(array_filter(
            $this->repository->listLeases($propertyIds, self::MAX_EXPORT_ROWS),
            static fn (array $lease): bool => (int) ($lease['rentalTenantId'] ?? 0) === $tenantId
        ));
        if ($leases === []) {
            return $this->failure('forbidden');
        }

        $leaseIds = array_values(array_unique(array_map(
            static fn (array $lease): int => (int) ($lease['id'] ?? 0),
            $leases
        )));
        $rents = array_values(array_filter(
            $this->repository->listRents($propertyIds, $year, self::MAX_EXPORT_ROWS),
            static fn (array $rent): bool => in_array((int) ($rent['rentalLeaseId'] ?? 0), $leaseIds, true)
        ));
        $payments = array_values(array_filter(
            $this->repository->listPayments($propertyIds, $year, self::MAX_EXPORT_ROWS),
            static fn (array $payment): bool => in_array((int) ($payment['rentalLeaseId'] ?? 0), $leaseIds, true)
                && ($payment['status'] ?? '') === 'validated'
        ));

        $tenantName = $this->firstValue($leases, 'tenantName') ?: 'Locataire #' . $tenantId;
        $rentDue = 0.0;
        foreach ($rents as $rent) {
            $rentDue += $this->amount($rent['amountDue'] ?? 0);
        }
        $rentPaid = 0.0;
        foreach ($payments as $payment) {
            $amount = $this->amount($payment['amountPaid'] ?? 0);
            $rentPaid += ($payment['paymentKind'] ?? '') === 'refund' ? -$amount : $amount;
        }

        $content = sprintf(
            "%%PDF-1.4\n%% Caramagnols private rental tenant recap export\nRecapitulatif locataire %d\nLocataire: %s\nLoyers attendus: %.2f EUR\nLoyers encaisses: %.2f EUR\nReste du: %.2f EUR\nBaux rattaches: %d\nLignes de loyers: %d\nPaiements valides: %d\n%%EOF\n",
            $year,
            $tenantName,
            round($rentDue, 2),
            round($rentPaid, 2),
            round(max(0.0, $rentDue - $rentPaid), 2),
            count($leases),
            count($rents),
            count($payments)
        );

        return $this->writeExport(
            $content,
            sprintf('recap-locataire-%d-%d.pdf', $tenantId, $year),
            'pdf',
            'application/pdf'
        );
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>
     */
    private function propertyDocumentsZip(int $year, array $propertyIds, int $propertyId): array
    {
        unset($year);
        if ($propertyId <= 0 || !in_array($propertyId, $propertyIds, true)) {
            return $this->failure('forbidden');
        }
        if (!class_exists(ZipArchive::class)) {
            return $this->failure('zip_unavailable');
        }

        $this->cleanupExpiredExports();
        $path = $this->temporaryPath('zip');
        if ($path === '') {
            return $this->failure('export_storage_unavailable');
        }

        $documents = $this->repository->listDocuments([$propertyId], self::MAX_EXPORT_ROWS);
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->failure('export_write_failed');
        }

        $manifest = [];
        $added = 0;
        foreach ($documents as $document) {
            $absolutePath = $this->storage->absolutePath((string) ($document['storagePath'] ?? ''));
            if ($absolutePath === null || !is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            ++$added;
            $entryName = sprintf(
                'documents/%03d-%s',
                $added,
                $this->safeArchiveFilename((string) ($document['originalName'] ?? ('document-' . $added)))
            );
            $zip->addFile($absolutePath, $entryName);
            $manifest[] = [
                'entry' => $entryName,
                'documentId' => (string) ($document['documentId'] ?? ''),
                'originalName' => (string) ($document['originalName'] ?? ''),
                'propertyName' => (string) ($document['propertyName'] ?? ''),
                'unitLabel' => (string) ($document['unitLabel'] ?? ''),
                'mimeType' => (string) ($document['mimeType'] ?? ''),
                'sizeBytes' => (int) ($document['sizeBytes'] ?? 0),
            ];
        }

        $manifestJson = json_encode(
            [
                'generatedAt' => date('c'),
                'propertyId' => $propertyId,
                'documentCount' => count($manifest),
                'documents' => $manifest,
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        $zip->addFromString('manifest.json', is_string($manifestJson) ? $manifestJson : '{"documents":[]}');
        $zip->close();
        @chmod($path, 0600);

        if (!is_file($path)) {
            return $this->failure('export_write_failed');
        }

        $content = file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return $this->failure('export_write_failed');
        }

        return [
            'success' => true,
            'format' => 'zip',
            'mimeType' => 'application/zip',
            'filename' => sprintf('documents-bien-%d.zip', $propertyId),
            'path' => $path,
            'content' => $content,
            'sizeBytes' => strlen($content),
            'sha256' => hash('sha256', $content),
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function summaryExport(array $summary, int $year, string $format): array
    {
        $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
        if ($format === 'csv') {
            $rows = [
                ['annee', 'loyers_attendus', 'loyers_encaisses', 'impayes', 'charges_recuperables', 'charges_deductibles', 'charges_non_deductibles'],
                [
                    (string) $year,
                    $this->money($totals['rentDue'] ?? 0),
                    $this->money($totals['rentPaid'] ?? 0),
                    $this->money($totals['unpaidRent'] ?? 0),
                    $this->money($totals['recoverableExpenses'] ?? 0),
                    $this->money($totals['deductibleCandidateExpenses'] ?? 0),
                    $this->money($totals['nonDeductibleExpenses'] ?? 0),
                ],
            ];

            return $this->writeExport(
                $this->csv($rows),
                sprintf('synthese-locative-%d.csv', $year),
                'csv',
                'text/csv; charset=UTF-8'
            );
        }

        $content = sprintf(
            "%%PDF-1.4\n%% Caramagnols private rental summary export\nSynthese locative %d\nLoyers attendus: %.2f EUR\nLoyers encaisses: %.2f EUR\nImpayes: %.2f EUR\nCharges recuperables: %.2f EUR\nCharges deductibles candidates: %.2f EUR\nCharges non deductibles: %.2f EUR\n%%EOF\n",
            $year,
            (float) ($totals['rentDue'] ?? 0),
            (float) ($totals['rentPaid'] ?? 0),
            (float) ($totals['unpaidRent'] ?? 0),
            (float) ($totals['recoverableExpenses'] ?? 0),
            (float) ($totals['deductibleCandidateExpenses'] ?? 0),
            (float) ($totals['nonDeductibleExpenses'] ?? 0)
        );

        return $this->writeExport(
            $content,
            sprintf('synthese-locative-%d.pdf', $year),
            'pdf',
            'application/pdf'
        );
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    private function csv(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode(';', array_map(
                static fn (string $cell): string => '"' . str_replace('"', '""', $cell) . '"',
                $row
            ));
        }

        return "\xEF\xBB\xBF" . implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function writeExport(string $content, string $filename, string $format, string $mimeType): array
    {
        $this->cleanupExpiredExports();
        $path = $this->temporaryPath($format);
        if ($path === '') {
            return $this->failure('export_storage_unavailable');
        }
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            return $this->failure('export_write_failed');
        }
        @chmod($path, 0600);

        return [
            'success' => true,
            'format' => $format,
            'mimeType' => $mimeType,
            'filename' => $filename,
            'path' => $path,
            'content' => $content,
            'sizeBytes' => strlen($content),
            'sha256' => hash('sha256', $content),
        ];
    }

    private function temporaryPath(string $extension): string
    {
        $root = $this->exportsRoot();
        if ($root === '') {
            return '';
        }
        if (!is_dir($root) && !@mkdir($root, 0700, true) && !is_dir($root)) {
            return '';
        }
        @chmod($root, 0700);

        try {
            $token = bin2hex(random_bytes(12));
        } catch (\Throwable) {
            $token = hash('sha256', uniqid('rental-export', true));
        }

        return $root . '/rental-export-' . date('Ymd-His') . '-' . $token . '.' . $extension;
    }

    private function cleanupExpiredExports(): void
    {
        $root = $this->exportsRoot();
        if ($root === '' || !is_dir($root)) {
            return;
        }

        $threshold = time() - self::EXPORT_TTL_SECONDS;
        $files = glob($root . '/rental-export-*');
        if (!is_array($files)) {
            return;
        }
        foreach ($files as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }
            $mtime = filemtime($file);
            if (is_int($mtime) && $mtime < $threshold) {
                @unlink($file);
            }
        }
    }

    private function exportsRoot(): string
    {
        $root = rtrim(str_replace('\\', '/', $this->storage->exportsDirectory()), '/') . '/real-estate-rental';
        if (str_contains($root, '..') || str_starts_with($root, rtrim(str_replace('\\', '/', ROOT_PATH . '/public'), '/') . '/')) {
            return '';
        }

        return $root;
    }

    /**
     * @return array<string, mixed>
     */
    private function failure(string $error, array $extra = []): array
    {
        return ['success' => false, 'error' => $error] + $extra;
    }

    private function isFinancialKind(string $kind): bool
    {
        return in_array($kind, [
            self::KIND_SUMMARY,
            self::KIND_RENTS,
            self::KIND_EXPENSES,
            self::KIND_PROPERTY_ANNUAL,
            self::KIND_TENANT_RECAP,
        ], true);
    }

    private function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return match ($kind) {
            'rents', 'rents_csv', 'loyers' => self::KIND_RENTS,
            'expenses', 'expenses_csv', 'charges' => self::KIND_EXPENSES,
            'property', 'property_annual', 'annual_property', 'bien' => self::KIND_PROPERTY_ANNUAL,
            'tenant', 'tenant_recap', 'locataire' => self::KIND_TENANT_RECAP,
            'documents', 'property_documents', 'documents_zip' => self::KIND_PROPERTY_DOCUMENTS,
            default => self::KIND_SUMMARY,
        };
    }

    private function normalizeYear(int $year): int
    {
        return $year >= 2000 && $year <= 2100 ? $year : (int) date('Y');
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $normalized[] = (int) $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }

    private function money(mixed $value): string
    {
        return number_format($this->amount($value), 2, '.', '');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function firstValue(array $rows, string $key): string
    {
        foreach ($rows as $row) {
            $value = is_string($row[$key] ?? null) ? trim((string) $row[$key]) : '';
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function safeArchiveFilename(string $name): string
    {
        $name = trim(str_replace(["\r", "\n", "\t", '/', '\\'], ' ', $name));
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name);
        $name = trim((string) $name, " .\t\n\r\0\x0B");
        if ($name === '' || $name === '..' || $name === '.') {
            return 'document';
        }

        return substr($name, 0, 120);
    }
}
