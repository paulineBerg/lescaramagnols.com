<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Logging\SqlLogStore;

final class AdminLogService
{
    public function __construct(
        private readonly SqlLogStore $store,
        private readonly int $limit = 200
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{q: string, channel: string, level: string, date_from: string, date_to: string}
     */
    public function normalizeFilters(array $input): array
    {
        $source = is_array($input['filters'] ?? null) ? $input['filters'] : $input;

        return [
            'q' => $this->trimText((string) ($source['q'] ?? ''), 120),
            'channel' => $this->normalizeToken((string) ($source['channel'] ?? ''), 32, '/[^a-z0-9_-]+/'),
            'level' => $this->normalizeToken((string) ($source['level'] ?? ''), 16, '/[^a-z]+/'),
            'date_from' => $this->normalizeDate((string) ($source['date_from'] ?? '')),
            'date_to' => $this->normalizeDate((string) ($source['date_to'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function viewModel(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $storageAvailable = $this->store->isAvailable();
        $entries = $storageAvailable ? $this->store->listEntries($filters, $this->limit) : [];

        return [
            'filters' => $filters,
            'entries' => $entries,
            'availableChannels' => $storageAvailable ? $this->store->listChannels() : [],
            'availableLevels' => $storageAvailable ? $this->store->listLevels() : [],
            'filteredCount' => $storageAvailable ? $this->store->countEntries($filters) : 0,
            'totalCount' => $storageAvailable ? $this->store->countEntries([]) : 0,
            'storageAvailable' => $storageAvailable,
            'storageMessage' => $storageAvailable ? null : 'Le stockage SQL des logs est indisponible.',
            'hasActiveFilters' => $this->hasActiveFilters($filters),
            'limit' => $this->limit,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string, deletedCount: int, filters: array{q: string, channel: string, level: string, date_from: string, date_to: string}}
     */
    public function deleteSelected(array $payload): array
    {
        $filters = $this->normalizeFilters($payload);
        $ids = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (mixed $value): int => (int) $value,
                        is_array($payload['log_ids'] ?? null) ? $payload['log_ids'] : []
                    ),
                    static fn (int $value): bool => $value > 0
                )
            )
        );

        if ($ids === []) {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Sélection vide : choisissez au moins une entrée à supprimer.',
                'deletedCount' => 0,
                'filters' => $filters,
            ];
        }

        if (!$this->store->isAvailable()) {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Le journal SQL est indisponible.',
                'deletedCount' => 0,
                'filters' => $filters,
            ];
        }

        $deletedCount = $this->store->deleteByIds($ids);

        return [
            'success' => $deletedCount > 0,
            'message' => $deletedCount > 0 ? sprintf('%d entrée(s) supprimée(s).', $deletedCount) : null,
            'error' => $deletedCount > 0 ? null : 'Aucune entrée n’a été supprimée.',
            'deletedCount' => $deletedCount,
            'filters' => $filters,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: ?string, error: ?string, deletedCount: int, filters: array{q: string, channel: string, level: string, date_from: string, date_to: string}}
     */
    public function purgeFiltered(array $payload): array
    {
        $filters = $this->normalizeFilters($payload);

        if (!$this->hasActiveFilters($filters)) {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Applique au moins un filtre avant de lancer une purge.',
                'deletedCount' => 0,
                'filters' => $filters,
            ];
        }

        if (!$this->store->isAvailable()) {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Le journal SQL est indisponible.',
                'deletedCount' => 0,
                'filters' => $filters,
            ];
        }

        $deletedCount = $this->store->deleteByFilters($filters);

        return [
            'success' => $deletedCount > 0,
            'message' => $deletedCount > 0 ? sprintf('%d entrée(s) filtrée(s) supprimée(s).', $deletedCount) : null,
            'error' => $deletedCount > 0 ? null : 'Aucune entrée ne correspondait aux filtres.',
            'deletedCount' => $deletedCount,
            'filters' => $filters,
        ];
    }

    /**
     * @param array{q: string, channel: string, level: string, date_from: string, date_to: string} $filters
     */
    public function hasActiveFilters(array $filters): bool
    {
        return $filters['q'] !== ''
            || $filters['channel'] !== ''
            || $filters['level'] !== ''
            || $filters['date_from'] !== ''
            || $filters['date_to'] !== '';
    }

    private function normalizeToken(string $value, int $maxLength, string $pattern): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace($pattern, '', $value) ?? '';

        return $this->trimText($value, $maxLength);
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }

    private function trimText(string $value, int $maxLength): string
    {
        $value = trim($value);

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength)
            : substr($value, 0, $maxLength);
    }
}
