<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Service;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoCommuneNormalizer;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoPathPolicy;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoRenameTemplate;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoBatchRepository;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoOperationRepository;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoSequenceRepository;
use RuntimeException;

final class PhotoRenameBatchService
{
    private const MAX_PREVIEWS_PER_SYNC = 10;
    private const MAX_OPERATIONS_PER_BATCH = 2000;

    public function __construct(
        private readonly EditorialDatabase $database,
        private readonly PhotoBatchRepository $batches,
        private readonly PhotoOperationRepository $operations,
        private readonly PhotoSequenceRepository $sequences,
        private readonly PhotoPathPolicy $pathPolicy = new PhotoPathPolicy(),
        private readonly PhotoCommuneNormalizer $communes = new PhotoCommuneNormalizer(),
        private readonly PhotoRenameTemplate $template = new PhotoRenameTemplate(),
        private readonly ?AdministrativePlaceResolver $placeResolver = null
    ) {
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<int, mixed> $previews
     * @return array{stored: int, rejected: int}
     */
    public function ingestAgentPreviews(int $ownerId, array $agent, array $previews): array
    {
        $agentId = (int) ($agent['id'] ?? 0);
        $agentUid = is_string($agent['agent_uid'] ?? null) ? strtolower(trim((string) $agent['agent_uid'])) : '';
        if ($ownerId <= 0 || $agentId <= 0) {
            return ['stored' => 0, 'rejected' => count($previews)];
        }

        $stored = 0;
        $rejected = 0;
        foreach (array_slice($previews, 0, self::MAX_PREVIEWS_PER_SYNC) as $preview) {
            if (!is_array($preview)) {
                $rejected++;
                continue;
            }

            try {
                $this->storePreview($ownerId, $agentId, $agentUid, $preview);
                $stored++;
            } catch (\Throwable) {
                $rejected++;
            }
        }

        return ['stored' => $stored, 'rejected' => $rejected];
    }

    /**
     * @param array<string, mixed> $agent
     * @param array<int, mixed> $results
     * @return array{stored: int, rejected: int}
     */
    public function ingestAgentResults(int $ownerId, array $agent, array $results): array
    {
        $agentId = (int) ($agent['id'] ?? 0);
        if ($ownerId <= 0 || $agentId <= 0) {
            return ['stored' => 0, 'rejected' => count($results)];
        }

        $stored = 0;
        $rejected = 0;
        foreach (array_slice($results, 0, self::MAX_PREVIEWS_PER_SYNC) as $result) {
            if (!is_array($result)) {
                $rejected++;
                continue;
            }

            try {
                $this->storeResult($ownerId, $agentId, $result);
                $stored++;
            } catch (\Throwable) {
                $rejected++;
            }
        }

        return ['stored' => $stored, 'rejected' => $rejected];
    }

    /**
     * @param array<string, mixed> $agent
     * @return array<string, mixed>
     */
    public function executePayload(int $ownerId, array $agent, string $batchUid, string $previewUid): array
    {
        $agentId = (int) ($agent['id'] ?? 0);
        if ($ownerId <= 0 || $agentId <= 0 || !$this->isUid($batchUid) || !$this->isUid($previewUid)) {
            throw new RuntimeException('Lot PhotoGeoRenamer invalide.');
        }

        $batch = $this->batches->findByUid($batchUid);
        if (
            !is_array($batch)
            || (int) ($batch['private_user_id'] ?? 0) !== $ownerId
            || (int) ($batch['agent_id'] ?? 0) !== $agentId
            || strtolower((string) ($batch['preview_uid'] ?? '')) !== $previewUid
        ) {
            throw new RuntimeException('Apercu PhotoGeoRenamer introuvable.');
        }

        $rootUid = (string) ($batch['root_uid'] ?? '');
        $relativeDir = (string) ($batch['relative_dir'] ?? '');
        if (!$this->pathPolicy->isValidRootUid($rootUid) || $this->pathPolicy->normalizeRelativeDirectory($relativeDir) === null) {
            throw new RuntimeException('Source PhotoGeoRenamer invalide.');
        }

        $rows = $this->operations->forBatch((int) $batch['id']);
        if ($rows === []) {
            throw new RuntimeException('Aucune operation PhotoGeoRenamer stockee.');
        }

        if ($this->allReserved($rows)) {
            return $this->payloadFromReservedRows(
                $batch,
                $this->sortRows($rows, (string) ($batch['sort_order'] ?? 'chronological'))
            );
        }

        $rows = $this->readyRows($rows);
        if ($rows === []) {
            throw new RuntimeException('Aucune operation PhotoGeoRenamer executable.');
        }

        $rows = $this->sortRows($rows, (string) ($batch['sort_order'] ?? 'chronological'));
        $requests = [];
        foreach ($rows as $row) {
            $commune = $this->communes->normalize($row['commune_name'] ?? null);
            if ($commune === null) {
                throw new RuntimeException('Commune PhotoGeoRenamer manquante.');
            }
            $requests[$commune->name] = ($requests[$commune->name] ?? 0) + 1;
        }

        $pdo = $this->database->pdo();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $reservations = $this->sequences->reserve($requests);
            $nextByCommune = [];
            $reservedRows = [];
            $template = $this->batchTemplate($batch);
            $separator = (string) ($batch['separator'] ?? '-');
            $digits = is_numeric($batch['counter_digits'] ?? null) ? (int) $batch['counter_digits'] : 2;

            foreach ($rows as $row) {
                $commune = $this->communes->normalize($row['commune_name'] ?? null);
                if ($commune === null || !isset($reservations[$commune->key])) {
                    throw new RuntimeException('Reservation PhotoGeoRenamer incomplete.');
                }
                $nextByCommune[$commune->key] ??= 0;
                $numbers = $reservations[$commune->key]->numbers;
                $assignedNumber = $numbers[$nextByCommune[$commune->key]] ?? null;
                if (!is_int($assignedNumber)) {
                    throw new RuntimeException('Numero PhotoGeoRenamer indisponible.');
                }
                $nextByCommune[$commune->key]++;

                $newName = $this->template->filename([
                    'current_name' => (string) ($row['original_name'] ?? ''),
                    'name' => (string) ($row['original_name'] ?? ''),
                    'city' => $commune->name,
                    'commune_name' => $commune->name,
                    'taken_at' => (string) ($row['taken_at'] ?? ''),
                ], $template, $separator, $assignedNumber, $digits);
                if ($newName === '' || $this->pathPolicy->normalizeRelativePhoto($newName) === null) {
                    throw new RuntimeException('Nom PhotoGeoRenamer calcule invalide.');
                }

                $this->operations->markReserved((int) $row['id'], $newName, $assignedNumber);
                $row['new_name'] = $newName;
                $row['assigned_number'] = $assignedNumber;
                $row['status'] = 'reserved';
                $reservedRows[] = $row;
            }

            $this->batches->markReserved((int) $batch['id'], count($reservedRows));
            if ($started) {
                $pdo->commit();
            }

            return $this->payloadFromReservedRows($batch, $reservedRows);
        } catch (\Throwable $exception) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $preview
     */
    private function storePreview(int $ownerId, int $agentId, string $agentUid, array $preview): void
    {
        $batchUid = $this->uidValue($preview['batch_uid'] ?? null);
        $previewUid = $this->uidValue($preview['preview_uid'] ?? null);
        $rootUid = is_string($preview['root_uid'] ?? null) ? trim((string) $preview['root_uid']) : '';
        $relativeDir = $this->pathPolicy->normalizeRelativeDirectory($preview['relative_dir'] ?? '');
        $rawOperations = is_array($preview['operations'] ?? null) ? array_values($preview['operations']) : [];
        if (
            $batchUid === ''
            || $previewUid === ''
            || !$this->pathPolicy->isValidRootUid($rootUid)
            || $relativeDir === null
            || $rawOperations === []
            || count($rawOperations) > self::MAX_OPERATIONS_PER_BATCH
        ) {
            throw new RuntimeException('Apercu PhotoGeoRenamer invalide.');
        }

        $template = $this->template->normalizeBlocks(is_array($preview['template'] ?? null) ? $preview['template'] : []);
        $separator = is_string($preview['separator'] ?? null) ? (string) $preview['separator'] : '-';
        $digits = is_numeric($preview['counter_digits'] ?? null) ? (int) $preview['counter_digits'] : 2;
        $sortOrder = is_string($preview['sort_order'] ?? null) ? (string) $preview['sort_order'] : 'chronological';
        $operations = $this->normalizeOperations($rawOperations);
        if ($operations === []) {
            throw new RuntimeException('Apercu PhotoGeoRenamer vide.');
        }

        $pdo = $this->database->pdo();
        $started = !$pdo->inTransaction();
        if ($started) {
            $pdo->beginTransaction();
        }

        try {
            $batch = $this->batches->upsertPreview(
                $ownerId,
                $agentId,
                $agentUid,
                $batchUid,
                $previewUid,
                $rootUid,
                $relativeDir,
                $template,
                $separator,
                $digits,
                $sortOrder,
                count($operations)
            );
            $this->operations->replacePreviewOperations((int) ($batch['id'] ?? 0), $operations);
            if ($started) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($started && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function storeResult(int $ownerId, int $agentId, array $result): void
    {
        $batchUid = $this->uidValue($result['batch_uid'] ?? null);
        if ($batchUid === '') {
            throw new RuntimeException('Resultat PhotoGeoRenamer invalide.');
        }

        $batch = $this->batches->findByUid($batchUid);
        if (
            !is_array($batch)
            || (int) ($batch['private_user_id'] ?? 0) !== $ownerId
            || (int) ($batch['agent_id'] ?? 0) !== $agentId
        ) {
            throw new RuntimeException('Lot PhotoGeoRenamer introuvable.');
        }

        $rawOperations = is_array($result['operations'] ?? null) ? array_values($result['operations']) : [];
        if (count($rawOperations) > self::MAX_OPERATIONS_PER_BATCH) {
            throw new RuntimeException('Resultat PhotoGeoRenamer trop volumineux.');
        }

        $success = 0;
        $failed = 0;
        foreach ($rawOperations as $operation) {
            if (!is_array($operation)) {
                continue;
            }
            $this->operations->markResult((int) $batch['id'], $operation);
            $status = strtolower((string) ($operation['status'] ?? ''));
            if (in_array($status, ['completed', 'unchanged'], true)) {
                $success++;
            } elseif ($status === 'failed') {
                $failed++;
            }
        }

        $status = $failed > 0 ? ($success > 0 ? 'partial' : 'failed') : 'completed';
        $this->batches->updateStatus((int) $batch['id'], $status, [
            'success_files' => $success,
            'failed_files' => $failed,
            'completed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, mixed> $operations
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOperations(array $operations): array
    {
        $normalized = [];
        foreach ($operations as $operation) {
            if (!is_array($operation)) {
                continue;
            }

            $relativePath = $this->pathPolicy->normalizeRelativePhoto($operation['relative_path'] ?? $operation['old_name'] ?? null);
            $originalName = is_string($operation['old_name'] ?? null)
                ? trim((string) $operation['old_name'])
                : basename((string) $relativePath);
            $commune = $this->communes->normalize($operation['commune_name'] ?? $operation['city'] ?? null);
            $latitude = is_numeric($operation['latitude'] ?? null) ? (float) $operation['latitude'] : null;
            $longitude = is_numeric($operation['longitude'] ?? null) ? (float) $operation['longitude'] : null;
            if ($commune === null && $latitude !== null && $longitude !== null) {
                $resolved = $this->resolveCommuneFromCoordinates($latitude, $longitude);
                $commune = $resolved !== null ? $this->communes->normalize($resolved->communeName) : null;
            }
            if ($relativePath === null || $originalName === '') {
                continue;
            }

            $normalized[] = [
                'file_uid' => $this->shortString($operation['file_uid'] ?? null, 96),
                'original_name' => basename($originalName),
                'new_name' => $this->shortString($operation['new_name'] ?? null, 240),
                'relative_path' => $relativePath,
                'commune_key' => $commune?->key,
                'commune_name' => $commune?->name,
                'taken_at' => $this->dateValue($operation['taken_at'] ?? $operation['date_taken'] ?? null),
                'taken_at_source' => $this->shortString($operation['taken_at_source'] ?? null, 64),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'status' => $commune === null ? 'conflict' : 'previewed',
                'error_code' => $commune === null ? 'commune_missing' : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, string $sortOrder): array
    {
        if ($sortOrder === 'manual') {
            return $rows;
        }

        usort($rows, static function (array $left, array $right) use ($sortOrder): int {
            return match ($sortOrder) {
                'name' => strcmp((string) ($left['original_name'] ?? ''), (string) ($right['original_name'] ?? '')),
                'city' => strcmp((string) ($left['commune_key'] ?? ''), (string) ($right['commune_key'] ?? ''))
                    ?: strcmp((string) ($left['original_name'] ?? ''), (string) ($right['original_name'] ?? '')),
                default => strcmp((string) ($left['commune_key'] ?? ''), (string) ($right['commune_key'] ?? ''))
                    ?: strcmp((string) ($left['taken_at'] ?? '9999-12-31 23:59:59'), (string) ($right['taken_at'] ?? '9999-12-31 23:59:59'))
                    ?: strcmp((string) ($left['original_name'] ?? ''), (string) ($right['original_name'] ?? '')),
            };
        });

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function readyRows(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => in_array((string) ($row['status'] ?? ''), ['draft', 'previewed'], true)
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function allReserved(array $rows): bool
    {
        foreach ($rows as $row) {
            if (
                (string) ($row['status'] ?? '') !== 'reserved'
                || (string) ($row['new_name'] ?? '') === ''
                || !is_numeric($row['assigned_number'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $batch
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function payloadFromReservedRows(array $batch, array $rows): array
    {
        return [
            'batch_uid' => (string) ($batch['batch_uid'] ?? ''),
            'preview_uid' => (string) ($batch['preview_uid'] ?? ''),
            'root_uid' => (string) ($batch['root_uid'] ?? ''),
            'relative_dir' => (string) ($batch['relative_dir'] ?? ''),
            'no_overwrite' => true,
            'two_pass' => true,
            'operations' => array_values(array_map(fn (array $row): array => [
                'file_uid' => (string) ($row['file_uid'] ?? ''),
                'relative_path' => (string) ($row['relative_path'] ?? ''),
                'old_name' => (string) ($row['original_name'] ?? ''),
                'new_name' => (string) ($row['new_name'] ?? ''),
                'temporary_name' => $this->temporaryName(
                    (string) ($row['original_name'] ?? ''),
                    (string) ($batch['batch_uid'] ?? ''),
                    (int) ($row['id'] ?? 0)
                ),
                'commune_key' => (string) ($row['commune_key'] ?? ''),
                'commune_name' => (string) ($row['commune_name'] ?? ''),
                'assigned_number' => (int) ($row['assigned_number'] ?? 0),
            ], $rows)),
        ];
    }

    /**
     * @param array<string, mixed> $batch
     * @return array<int, array<string, mixed>>
     */
    private function batchTemplate(array $batch): array
    {
        $decoded = json_decode((string) ($batch['template_json'] ?? '[]'), true);

        return $this->template->normalizeBlocks(is_array($decoded) ? $decoded : []);
    }

    private function uidValue(mixed $value): string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return $this->isUid($value) ? $value : '';
    }

    private function isUid(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{32}\z/', $value) === 1;
    }

    private function dateValue(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private function shortString(mixed $value, int $max): ?string
    {
        $value = trim(is_string($value) || is_numeric($value) ? (string) $value : '');

        return $value !== '' ? mb_substr($value, 0, $max) : null;
    }

    private function resolveCommuneFromCoordinates(float $latitude, float $longitude): ?\Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\ResolvedPlace
    {
        if ($this->placeResolver === null || $latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            return null;
        }

        try {
            return $this->placeResolver->resolve(round($latitude, 6), round($longitude, 6));
        } catch (\Throwable) {
            return null;
        }
    }

    private function temporaryName(string $oldName, string $batchUid, int $index): string
    {
        $extension = (string) pathinfo($oldName, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? '.' . $extension : '';

        return '.pbgestion-' . substr($batchUid, 0, 12) . '-' . str_pad((string) max(1, $index), 6, '0', STR_PAD_LEFT) . '.tmp' . $suffix;
    }
}
