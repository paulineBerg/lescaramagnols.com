<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\TileRepository;

final class AdminTileService
{
    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly TileRepository $tileRepository,
        private readonly PageRepository $pageRepository,
        private readonly array $availableLanguages,
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    public function isEnabled(): bool
    {
        return editorial_storage_mode() !== 'json';
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     theme: string,
     *     tileCount: int,
     *     placementCount: int,
     *     previewItems: array<int, array{
     *         tile_size: string,
     *         color_token: string,
     *         image_src: string,
     *         label: string,
     *         title: string
     *     }>
     * }>
     */
    public function listGroups(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $groups = [];

        foreach ($this->tileRepository->listGroupSummaries() as $summary) {
            if (!is_array($summary)) {
                continue;
            }

            $groupId = (int) ($summary['id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            $previewItems = [];
            $group = $this->tileRepository->findGroupForAdmin($groupId);
            $items = is_array($group['items'] ?? null) ? array_values($group['items']) : [];

            foreach (array_slice($items, 0, 6) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $previewItems[] = [
                    'tile_size' => TileRepository::normalizeTileSizeValue((string) ($item['tile_size'] ?? TileRepository::DEFAULT_SIZE)),
                    'color_token' => TileRepository::buttonColorToken(
                        (string) ($item['tile_size'] ?? TileRepository::DEFAULT_SIZE),
                        (string) ($item['color_token'] ?? 'bleu')
                    ),
                    'image_src' => trim((string) ($item['image_src'] ?? '')),
                    'label' => $this->preferredTileTranslation($item, 'label', 'Tuile'),
                    'title' => $this->preferredTileTranslation($item, 'title', ''),
                ];
            }

            $groups[] = [
                'id' => $groupId,
                'name' => trim((string) ($summary['name'] ?? '')),
                'theme' => trim((string) ($summary['theme'] ?? TileRepository::DEFAULT_THEME)),
                'tileCount' => max(0, (int) ($summary['tileCount'] ?? 0)),
                'placementCount' => max(0, (int) ($summary['placementCount'] ?? 0)),
                'previewItems' => $previewItems,
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    public function availableThemes(): array
    {
        return TileRepository::themeLabels();
    }

    /**
     * @return array<string, string>
     */
    public function availableColors(): array
    {
        return TileRepository::colorLabels();
    }

    /**
     * @return array<string, string>
     */
    public function availableSizes(): array
    {
        return TileRepository::sizeLabels();
    }

    /**
     * @return array<int, array{slug: string, title: string, route: string, status: string}>
     */
    public function pageReferenceOptions(): array
    {
        $options = [];

        foreach ($this->pageRepository->all() as $page) {
            if (!is_array($page)) {
                continue;
            }

            $slug = trim((string) ($page['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $title = $this->preferredPageTitle($page);
            $options[] = [
                'slug' => $slug,
                'title' => $title !== '' ? $title : $slug,
                'route' => normalize_public_route((string) ($page['route'] ?? '')) ?? ('/' . $slug),
                'status' => (string) ($page['status'] ?? PageRepository::STATUS_DRAFT),
            ];
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strcasecmp(
                (string) ($left['title'] ?? $left['slug'] ?? ''),
                (string) ($right['title'] ?? $right['slug'] ?? '')
            )
        );

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function availableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyFormData(): array
    {
        return [
            'id' => '',
            'name' => '',
            'theme' => TileRepository::DEFAULT_THEME,
            'items' => [$this->emptyItemFormData(0)],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formDataForGroup(int $groupId): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $group = $this->tileRepository->findGroupForAdmin($groupId);
        if (!is_array($group)) {
            return null;
        }

        return $this->buildFormData($group);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, error: string|null, form: array<string, mixed>, id: int|null}
     */
    public function save(array $payload, ?int $groupId = null): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Le module Tuiles est disponible uniquement quand l éditorial SQL est actif.',
                'form' => $this->emptyFormData(),
                'id' => null,
            ];
        }

        $existingGroup = ($groupId ?? 0) > 0 ? $this->tileRepository->findGroupForAdmin((int) $groupId) : null;
        $formData = $this->buildPostedFormData($payload, $existingGroup);

        $savedGroupId = $this->tileRepository->saveGroup($formData);
        if (!is_int($savedGroupId) || $savedGroupId <= 0) {
            return [
                'success' => false,
                'error' => 'Impossible de sauvegarder le groupe de tuiles.',
                'form' => $formData,
                'id' => is_numeric($formData['id'] ?? null) ? (int) $formData['id'] : null,
            ];
        }

        app_runtime_cache_clear(['tiles']);
        $savedForm = $this->formDataForGroup($savedGroupId) ?? $formData;

        return [
            'success' => true,
            'error' => null,
            'form' => $savedForm,
            'id' => $savedGroupId,
        ];
    }

    /**
     * @return array{success: bool, error: string|null}
     */
    public function delete(int $groupId): array
    {
        if ($groupId <= 0) {
            return [
                'success' => false,
                'error' => 'Groupe de tuiles introuvable.',
            ];
        }

        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Le module Tuiles est disponible uniquement quand l éditorial SQL est actif.',
            ];
        }

        foreach ($this->tileRepository->listGroupSummaries() as $summary) {
            if ((int) ($summary['id'] ?? 0) !== $groupId) {
                continue;
            }

            $placementCount = max(0, (int) ($summary['placementCount'] ?? 0));
            if ($placementCount > 0) {
                return [
                    'success' => false,
                    'error' => sprintf(
                        'Suppression impossible : ce groupe est encore rattache a %d page(s).',
                        $placementCount
                    ),
                ];
            }
        }

        if (!$this->tileRepository->deleteGroup($groupId)) {
            return [
                'success' => false,
                'error' => 'Impossible de supprimer le groupe de tuiles.',
            ];
        }

        app_runtime_cache_clear(['tiles']);

        return [
            'success' => true,
            'error' => null,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, id: int|null, name: string}
     */
    public function duplicate(int $groupId): array
    {
        if ($groupId <= 0) {
            return [
                'success' => false,
                'error' => 'Groupe de tuiles introuvable.',
                'id' => null,
                'name' => '',
            ];
        }

        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Le module Tuiles est disponible uniquement quand l éditorial SQL est actif.',
                'id' => null,
                'name' => '',
            ];
        }

        $existingGroup = $this->tileRepository->findGroupForAdmin($groupId);
        if (!is_array($existingGroup)) {
            return [
                'success' => false,
                'error' => 'Groupe de tuiles introuvable.',
                'id' => null,
                'name' => '',
            ];
        }

        $duplicateForm = $this->buildDuplicatedFormData($existingGroup);
        $savedGroupId = $this->tileRepository->saveGroup($duplicateForm);

        if (!is_int($savedGroupId) || $savedGroupId <= 0) {
            return [
                'success' => false,
                'error' => 'Impossible de dupliquer le groupe de tuiles.',
                'id' => null,
                'name' => (string) ($duplicateForm['name'] ?? ''),
            ];
        }

        app_runtime_cache_clear(['tiles']);

        return [
            'success' => true,
            'error' => null,
            'id' => $savedGroupId,
            'name' => (string) ($duplicateForm['name'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>
     */
    private function buildFormData(array $group): array
    {
        $formData = [
            'id' => (string) ($group['id'] ?? ''),
            'name' => trim((string) ($group['name'] ?? '')),
            'theme' => trim((string) ($group['theme'] ?? TileRepository::DEFAULT_THEME)),
            'items' => [],
        ];

        $items = is_array($group['items'] ?? null) ? array_values($group['items']) : [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $translations = [];
            foreach ($this->availableLanguages as $language) {
                if (!is_string($language) || trim($language) === '') {
                    continue;
                }

                $itemTranslation = is_array($item['translations'][$language] ?? null)
                    ? $item['translations'][$language]
                    : [];

                $translations[$language] = [
                    'label' => trim((string) ($itemTranslation['label'] ?? '')),
                    'alt' => trim((string) ($itemTranslation['alt'] ?? '')),
                    'title' => trim((string) ($itemTranslation['title'] ?? '')),
                ];
            }

            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            $formData['items'][] = [
                'item_uid' => trim((string) ($item['item_uid'] ?? '')),
                'sort_order' => (string) ($item['sort_order'] ?? (($index + 1) * 10)),
                'tile_size' => trim((string) ($item['tile_size'] ?? TileRepository::DEFAULT_SIZE)),
                'color_token' => trim((string) ($item['color_token'] ?? 'bleu')),
                'image_src' => trim((string) ($item['image_src'] ?? '')),
                'image_width' => isset($item['image_width']) ? (string) $item['image_width'] : '',
                'image_height' => isset($item['image_height']) ? (string) $item['image_height'] : '',
                'target_type' => trim((string) ($target['type'] ?? 'page')),
                'target_page_slug' => trim((string) ($target['pageSlug'] ?? '')),
                'target_route' => trim((string) ($target['route'] ?? '')),
                'target_url' => trim((string) ($target['url'] ?? '')),
                'open_in_new_tab' => !empty($item['open_in_new_tab']) ? '1' : '',
                'translations' => $translations,
            ];
        }

        if ($formData['items'] === []) {
            $formData['items'][] = $this->emptyItemFormData(0);
        }

        return $formData;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $existingGroup
     * @return array<string, mixed>
     */
    private function buildPostedFormData(array $payload, ?array $existingGroup): array
    {
        $formData = $this->emptyFormData();
        $formData['id'] = (string) max(
            0,
            (int) (($payload['id'] ?? ($existingGroup['id'] ?? 0)))
        );
        $formData['name'] = trim((string) ($payload['name'] ?? ($existingGroup['name'] ?? '')));
        $formData['theme'] = trim((string) ($payload['theme'] ?? ($existingGroup['theme'] ?? TileRepository::DEFAULT_THEME)));

        $itemsInput = is_array($payload['items'] ?? null) ? array_values($payload['items']) : [];
        $formData['items'] = [];

        foreach ($itemsInput as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemForm = $this->emptyItemFormData($index);
            $itemForm['item_uid'] = trim((string) ($item['item_uid'] ?? $itemForm['item_uid']));
            $itemForm['sort_order'] = (string) max(0, (int) ($item['sort_order'] ?? (($index + 1) * 10)));
            $itemForm['tile_size'] = TileRepository::normalizeTileSizeValue((string) ($item['tile_size'] ?? TileRepository::DEFAULT_SIZE));
            $itemForm['color_token'] = trim((string) ($item['color_token'] ?? 'bleu'));
            $itemForm['image_src'] = trim((string) ($item['image_src'] ?? ''));
            $itemForm['image_width'] = trim((string) ($item['image_width'] ?? ''));
            $itemForm['image_height'] = trim((string) ($item['image_height'] ?? ''));
            $itemForm['target_type'] = trim((string) ($item['target_type'] ?? 'page'));
            $itemForm['target_page_slug'] = trim((string) ($item['target_page_slug'] ?? ''));
            $itemForm['target_route'] = trim((string) ($item['target_route'] ?? ''));
            $itemForm['target_url'] = trim((string) ($item['target_url'] ?? ''));
            $itemForm['open_in_new_tab'] = !empty($item['open_in_new_tab']) ? '1' : '';

            $translationsInput = is_array($item['translations'] ?? null) ? $item['translations'] : [];
            foreach ($this->availableLanguages as $language) {
                if (!is_string($language) || trim($language) === '') {
                    continue;
                }

                $translation = is_array($translationsInput[$language] ?? null) ? $translationsInput[$language] : [];
                $itemForm['translations'][$language] = [
                    'label' => trim((string) ($translation['label'] ?? '')),
                    'alt' => trim((string) ($translation['alt'] ?? '')),
                    'title' => trim((string) ($translation['title'] ?? '')),
                ];
            }

            $formData['items'][] = $itemForm;
        }

        if ($formData['items'] === []) {
            $formData['items'][] = $this->emptyItemFormData(0);
        }

        return $formData;
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>
     */
    private function buildDuplicatedFormData(array $group): array
    {
        $formData = $this->buildFormData($group);
        $formData['id'] = '';
        $formData['name'] = $this->duplicateGroupName((string) ($formData['name'] ?? ''));

        $items = is_array($formData['items'] ?? null) ? array_values($formData['items']) : [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $item['item_uid'] = '';
            $item['sort_order'] = (string) (($index + 1) * 10);
            $items[$index] = $item;
        }

        $formData['items'] = $items !== [] ? $items : [$this->emptyItemFormData(0)];

        return $formData;
    }

    private function duplicateGroupName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '') {
            return 'Nouveau groupe - copie';
        }

        if (preg_match('/^(.*?)(?:\s*-\s*copie|\s+\(copie\))(?:\s+(\d+))?$/u', $normalized, $matches) === 1) {
            $baseName = trim((string) ($matches[1] ?? ''));
            $copyIndex = max(2, (int) ($matches[2] ?? 1) + 1);

            return sprintf('%s - copie %d', $baseName !== '' ? $baseName : 'Nouveau groupe', $copyIndex);
        }

        return $normalized . ' - copie';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyItemFormData(int $index): array
    {
        $translations = [];
        foreach ($this->availableLanguages as $language) {
            if (!is_string($language) || trim($language) === '') {
                continue;
            }

            $translations[$language] = [
                'label' => '',
                'alt' => '',
                'title' => '',
            ];
        }

        $defaultLanguage = in_array($this->defaultLanguage, $this->availableLanguages, true)
            ? $this->defaultLanguage
            : ($this->availableLanguages[0] ?? 'fr');
        $translations[$defaultLanguage] = $translations[$defaultLanguage] ?? [
            'label' => '',
            'alt' => '',
            'title' => '',
        ];

        return [
            'item_uid' => '',
            'sort_order' => (string) (($index + 1) * 10),
            'tile_size' => TileRepository::DEFAULT_SIZE,
            'color_token' => 'bleu',
            'image_src' => '',
            'image_width' => '',
            'image_height' => '',
            'target_type' => 'page',
            'target_page_slug' => '',
            'target_route' => '',
            'target_url' => '',
            'open_in_new_tab' => '',
            'translations' => $translations,
        ];
    }

    /**
     * @param array<string, mixed> $page
     */
    private function preferredPageTitle(array $page): string
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];

        foreach ([$this->defaultLanguage, ...$this->availableLanguages] as $language) {
            $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
            $title = trim((string) ($translation['title'] ?? ''));
            if ($title !== '') {
                return $title;
            }
        }

        return trim((string) ($page['title'] ?? ''));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function preferredTileTranslation(array $item, string $field, string $fallback): string
    {
        $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];

        foreach ([$this->defaultLanguage, ...$this->availableLanguages] as $language) {
            if (!is_string($language) || trim($language) === '') {
                continue;
            }

            $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
            $value = trim((string) ($translation[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $value = trim((string) ($translation[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}
