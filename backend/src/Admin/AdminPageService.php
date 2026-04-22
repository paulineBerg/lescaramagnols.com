<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Content\PageRepository;
use Caramagnols\Content\StandardPageRegionMapper;
use Caramagnols\Content\TileRepository;
use Caramagnols\Navigation\NavigationRepository;

final class AdminPageService
{
    public const EDITOR_MODE_BLOCKS = 'blocks';
    public const EDITOR_MODE_REGIONS = 'regions';
    private const SHARED_MEDIA_MAX_ITEMS = 24;

    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly PageRepository $repository,
        private readonly array $availableLanguages,
        private readonly string $defaultLanguage = 'fr',
        private readonly ?NavigationRepository $navigationRepository = null,
        ?StandardPageRegionMapper $regionMapper = null,
        private readonly ?TileRepository $tileRepository = null
    ) {
        $this->regionMapper = $regionMapper ?? new StandardPageRegionMapper();
    }

    private readonly StandardPageRegionMapper $regionMapper;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPages(
        ?string $statusFilter = null,
        ?string $languageFilter = null,
        ?string $query = null
    ): array {
        $pages = [];
        $statusFilter = $this->normalizeOptionalString($statusFilter);
        $languageFilter = $this->normalizeOptionalString($languageFilter);
        $query = mb_strtolower($this->normalizeOptionalString($query) ?? '');

        foreach ($this->repository->all() as $page) {
            $summary = $this->pageSummary($page);

            if ($statusFilter !== null && $summary['status'] !== $statusFilter) {
                continue;
            }

            if ($languageFilter !== null && !in_array($languageFilter, $summary['languages'], true)) {
                continue;
            }

            if ($query !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) $summary['slug'],
                    (string) $summary['title'],
                    (string) $summary['route'],
                ]));

                if (!str_contains($haystack, $query)) {
                    continue;
                }
            }

            $pages[] = $summary;
        }

        usort(
            $pages,
            static fn (array $left, array $right): int => strcmp((string) $left['slug'], (string) $right['slug'])
        );

        return $pages;
    }

    /**
     * @return array<int, array{slug: string, title: string, route: string, status: string}>
     */
    public function pageReferenceOptions(): array
    {
        $references = [];

        foreach ($this->listPages() as $page) {
            $references[] = [
                'slug' => (string) $page['slug'],
                'title' => (string) $page['title'],
                'route' => (string) $page['route'],
                'status' => (string) $page['status'],
            ];
        }

        return $references;
    }

    /**
     * @return array{
     *   total: int,
     *   published: int,
     *   drafts: int,
     *   structured: int,
     *   fullyTranslated: int,
     *   withMissingTranslations: int,
     *   byLanguage: array<string, int>
     * }
     */
    public function dashboardSummary(): array
    {
        $summary = [
            'total' => 0,
            'published' => 0,
            'drafts' => 0,
            'structured' => 0,
            'fullyTranslated' => 0,
            'withMissingTranslations' => 0,
            'byLanguage' => array_fill_keys($this->availableLanguages, 0),
        ];

        foreach ($this->listPages() as $page) {
            $summary['total']++;

            if (($page['status'] ?? PageRepository::STATUS_DRAFT) === PageRepository::STATUS_PUBLISHED) {
                $summary['published']++;
            } else {
                $summary['drafts']++;
            }

            $summary['structured']++;

            $languages = is_array($page['languages'] ?? null) ? $page['languages'] : [];
            foreach ($languages as $language) {
                if (is_string($language) && array_key_exists($language, $summary['byLanguage'])) {
                    $summary['byLanguage'][$language]++;
                }
            }

            $missingLanguages = is_array($page['missingLanguages'] ?? null) ? $page['missingLanguages'] : [];
            if ($languages !== [] && $missingLanguages === []) {
                $summary['fullyTranslated']++;
            }
            if ($missingLanguages !== []) {
                $summary['withMissingTranslations']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyFormData(): array
    {
        $translations = [];

        foreach ($this->availableLanguages as $language) {
            $translations[$language] = $this->emptyTranslationFormData();
        }

        return [
            'slug' => '',
            'status' => PageRepository::STATUS_DRAFT,
            'route' => '',
            'layout' => 'standard_page',
            'shared_media' => [],
            'tile_placements' => [],
            'translations' => $translations,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formDataForSlug(string $slug): ?array
    {
        $page = $this->repository->findBySlug($slug);
        if ($page === null) {
            return null;
        }

        return $this->buildFormData($page);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, error: string|null, form: array<string, mixed>, slug: string|null}
     */
    public function save(array $payload, ?string $originalSlug = null): array
    {
        $existingPage = $originalSlug !== null ? $this->repository->findBySlug($originalSlug) : null;
        $formData = $this->buildPostedFormData($payload, $existingPage);
        $slug = (string) ($formData['slug'] ?? '');
        $route = trim((string) ($formData['route'] ?? ''));
        if ($route === '') {
            $route = '/' . $slug;
            $formData['route'] = $route;
        }

        if ($slug === '') {
            return [
                'success' => false,
                'error' => 'Le slug est obligatoire.',
                'form' => $formData,
                'slug' => null,
            ];
        }

        $conflictingPage = $this->repository->findBySlug($slug);
        if ($conflictingPage !== null && $slug !== trim((string) ($originalSlug ?? ''))) {
            return [
                'success' => false,
                'error' => 'Une page utilise déjà ce slug.',
                'form' => $formData,
                'slug' => $slug,
            ];
        }

        $conflictingRoutePage = $this->repository->findByRoute($route);
        if (
            $conflictingRoutePage !== null
            && (string) ($conflictingRoutePage['slug'] ?? '') !== trim((string) ($originalSlug ?? ''))
        ) {
            return [
                'success' => false,
                'error' => 'Une autre page utilise déjà cette route.',
                'form' => $formData,
                'slug' => $slug,
            ];
        }

        $page = $this->buildPagePayload($formData, $existingPage);
        if (($page['translations'] ?? []) === []) {
            return [
                'success' => false,
                'error' => 'Au moins une langue doit contenir un titre ou un contenu.',
                'form' => $formData,
                'slug' => $slug,
            ];
        }

        if (!$this->repository->savePage($page, $originalSlug)) {
            return [
                'success' => false,
                'error' => 'Impossible de sauvegarder la page.',
                'form' => $formData,
                'slug' => $slug,
            ];
        }

        if ($this->tileSupportEnabled()) {
            $savedSlug = (string) ($page['slug'] ?? '');
            $placements = is_array($formData['tile_placements'] ?? null) ? $formData['tile_placements'] : [];
            if (!$this->tileRepository?->replacePlacementsForPage($savedSlug, $placements, $originalSlug)) {
                return [
                    'success' => false,
                    'error' => 'La page est sauvegardee, mais les placements de tuiles n ont pas pu etre mis a jour.',
                    'form' => $this->buildFormData($page),
                    'slug' => $slug,
                ];
            }
        }

        app_runtime_cache_clear(['pages', 'navigation', 'tiles']);

        return [
            'success' => true,
            'error' => null,
            'form' => $this->buildFormData($page),
            'slug' => $slug,
        ];
    }

    /**
     * @return array{canDelete: bool, references: array<int, array<string, string>>}
     */
    public function deletionInfoForSlug(string $slug): array
    {
        $references = array_merge(
            $this->navigationReferencesForPageSlug($slug),
            $this->tileReferencesForPageSlug($slug)
        );

        return [
            'canDelete' => $references === [],
            'references' => $references,
        ];
    }

    /**
     * @return array{success: bool, error: string|null, references: array<int, array<string, string>>}
     */
    public function delete(string $slug): array
    {
        $page = $this->repository->findBySlug($slug);
        if ($page === null) {
            return [
                'success' => false,
                'error' => 'La page demandée est introuvable.',
                'references' => [],
            ];
        }

        $references = array_merge(
            $this->navigationReferencesForPageSlug($slug),
            $this->tileReferencesForPageSlug($slug)
        );
        if ($references !== []) {
            return [
                'success' => false,
                'error' => 'Suppression impossible : cette page est encore utilisee dans la navigation ou dans des groupes de tuiles.',
                'references' => $references,
            ];
        }

        if (!$this->repository->deletePage($slug)) {
            return [
                'success' => false,
                'error' => 'Impossible de supprimer la page.',
                'references' => [],
            ];
        }

        if ($this->tileSupportEnabled()) {
            $this->tileRepository?->deletePlacementsForPage($slug);
        }

        app_runtime_cache_clear(['pages', 'navigation', 'tiles']);

        return [
            'success' => true,
            'error' => null,
            'references' => [],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function availableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * @return array<int, string>
     */
    public function supportedStatuses(): array
    {
        return [
            PageRepository::STATUS_DRAFT,
            PageRepository::STATUS_PUBLISHED,
        ];
    }

    public function tileSupportEnabled(): bool
    {
        return $this->tileRepository instanceof TileRepository && editorial_storage_mode() !== 'json';
    }

    /**
     * @return array<int, array{id: int, name: string, theme: string, tileCount: int}>
     */
    public function tileGroupReferenceOptions(): array
    {
        if (!$this->tileSupportEnabled()) {
            return [];
        }

        return $this->tileRepository->groupReferenceOptions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tileGroupCatalogForEditor(): array
    {
        if (!$this->tileSupportEnabled()) {
            return [];
        }

        $catalog = [];
        foreach ($this->tileRepository->groupReferenceOptions() as $groupOption) {
            $groupId = (int) ($groupOption['id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            $group = $this->tileRepository->findGroupForAdmin($groupId);
            if (!is_array($group)) {
                continue;
            }

            $items = [];
            foreach (is_array($group['items'] ?? null) ? $group['items'] : [] as $groupItem) {
                if (!is_array($groupItem)) {
                    continue;
                }

                $items[] = [
                    'item_uid' => (string) ($groupItem['item_uid'] ?? ''),
                    'label' => $this->preferredTileLabel($groupItem),
                    'tile_size' => (string) ($groupItem['tile_size'] ?? TileRepository::DEFAULT_SIZE),
                    'color_token' => (string) ($groupItem['color_token'] ?? 'bleu'),
                    'image_src' => (string) ($groupItem['image_src'] ?? ''),
                    'target_summary' => $this->tileTargetSummary(
                        is_array($groupItem['target'] ?? null) ? $groupItem['target'] : []
                    ),
                ];
            }

            $catalog[] = [
                'id' => $groupId,
                'name' => (string) ($group['name'] ?? ''),
                'theme' => (string) ($group['theme'] ?? TileRepository::DEFAULT_THEME),
                'items' => $items,
            ];
        }

        return $catalog;
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function pageSummary(array $page): array
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $languages = [];

        foreach ($this->availableLanguages as $language) {
            $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];

            if (
                $translation !== []
                || ($language === $this->defaultLanguage && $this->pageHasRootContent($page))
            ) {
                $languages[] = $language;
            }
        }

        $title = $this->preferredTitle($page);
        $route = normalize_public_route((string) ($page['route'] ?? '')) ?? ('/' . (string) ($page['slug'] ?? ''));

        return [
            'slug' => (string) ($page['slug'] ?? ''),
            'title' => $title !== '' ? $title : (string) ($page['slug'] ?? ''),
            'status' => (string) ($page['status'] ?? PageRepository::STATUS_PUBLISHED),
            'route' => $route,
            'layout' => (string) ($page['layout'] ?? 'standard_page'),
            'languages' => $languages,
            'missingLanguages' => array_values(array_diff($this->availableLanguages, $languages)),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function navigationReferencesForPageSlug(string $slug): array
    {
        if (!$this->navigationRepository instanceof NavigationRepository) {
            return [];
        }

        $canonical = $this->navigationRepository->loadCanonical();
        $locations = is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [];
        $references = [];

        foreach ($locations as $locationKey => $items) {
            if (!is_string($locationKey) || !is_array($items)) {
                continue;
            }

            if (in_array($locationKey, ['banner', 'remonter'], true)) {
                continue;
            }

            $this->collectNavigationReferences($items, $slug, $locationKey, [], $references);
        }

        return $references;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function tileReferencesForPageSlug(string $slug): array
    {
        if (!$this->tileSupportEnabled()) {
            return [];
        }

        return $this->tileRepository->referencesToPageSlug($slug);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, string> $trail
     * @param array<int, array<string, string>> $references
     */
    private function collectNavigationReferences(
        array $items,
        string $slug,
        string $locationKey,
        array $trail,
        array &$references
    ): void {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = $this->navigationItemLabel($item);
            $currentTrail = $trail;
            if ($label !== '') {
                $currentTrail[] = $label;
            }

            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            if ((string) ($target['pageSlug'] ?? '') === $slug) {
                $references[] = [
                    'location' => $this->locationLabel($locationKey),
                    'context' => 'Lien de navigation',
                    'path' => $currentTrail !== [] ? implode(' > ', $currentTrail) : $this->locationLabel($locationKey),
                ];
            }

            $presentation = is_array($item['presentation'] ?? null) ? $item['presentation'] : [];
            $featuredCard = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
            $featuredTarget = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];
            if ((string) ($featuredTarget['pageSlug'] ?? '') === $slug) {
                $references[] = [
                    'location' => $this->locationLabel($locationKey),
                    'context' => 'Carte mise en avant',
                    'path' => $currentTrail !== [] ? implode(' > ', $currentTrail) : $this->locationLabel($locationKey),
                ];
            }

            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children !== []) {
                $this->collectNavigationReferences($children, $slug, $locationKey, $currentTrail, $references);
            }
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function navigationItemLabel(array $item): string
    {
        $label = $item['label'] ?? null;
        if (is_array($label)) {
            $text = trim((string) ($label['text'] ?? ''));
            if ($text !== '') {
                return $text;
            }

            $translationKey = trim((string) ($label['translationKey'] ?? ''));
            if ($translationKey !== '') {
                return $translationKey;
            }
        }

        return trim((string) $label);
    }

    private function locationLabel(string $locationKey): string
    {
        return match ($locationKey) {
            'utility' => 'Réseaux / utilitaire',
            'primary' => 'Menu principal',
            'footer' => 'Pied de page',
            'sideLeft' => 'Bloc latéral gauche',
            'sideRight' => 'Bloc latéral droit',
            default => $locationKey,
        };
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function buildFormData(array $page): array
    {
        $formData = $this->emptyFormData();
        $formData['slug'] = (string) ($page['slug'] ?? '');
        $formData['status'] = (string) ($page['status'] ?? PageRepository::STATUS_PUBLISHED);
        $formData['route'] = normalize_public_route((string) ($page['route'] ?? '')) ?? '';
        $formData['layout'] = (string) ($page['layout'] ?? 'standard_page');
        $formData['shared_media'] = $this->sharedMediaFormFromPage($page);
        if ($this->tileSupportEnabled()) {
            $formData['tile_placements'] = $this->tileRepository->placementsForPageEditor(
                (string) ($page['slug'] ?? ''),
                $this->availableLanguages
            );
        }

        foreach ($this->availableLanguages as $language) {
            $translation = $this->translationForForm($page, $language);
            $meta = is_array($translation['meta'] ?? null) ? $translation['meta'] : [];
            $metaImage = AdminEditorialImageService::sanitizeImageMetadata(
                is_array($meta['image'] ?? null) ? $meta['image'] : []
            );
            $formData['translations'][$language] = [
                'title' => (string) ($translation['title'] ?? ''),
                'meta_description' => (string) ($meta['description'] ?? ''),
                'meta_image_src' => (string) ($metaImage['src'] ?? ''),
                'meta_image_alt' => (string) ($metaImage['alt'] ?? ''),
                'meta_image_title' => (string) ($metaImage['title'] ?? ''),
                'meta_image_width' => isset($metaImage['width']) ? (string) $metaImage['width'] : '',
                'meta_image_height' => isset($metaImage['height']) ? (string) $metaImage['height'] : '',
                'regions' => $this->regionsForForm($translation),
            ];
        }

        return $formData;
    }

    /**
     * @param array<string, mixed>|null $existingPage
     * @return array<string, mixed>
     */
    private function buildPostedFormData(array $payload, ?array $existingPage): array
    {
        $formData = $this->emptyFormData();
        $formData['slug'] = $this->sanitizeSlug((string) ($payload['slug'] ?? ''));
        $formData['status'] = $this->normalizeStatus((string) ($payload['status'] ?? PageRepository::STATUS_DRAFT));
        $formData['route'] = $this->normalizeRouteInput((string) ($payload['route'] ?? ''));
        $formData['layout'] = trim((string) ($payload['layout'] ?? 'standard_page')) ?: 'standard_page';
        $existingSharedMedia = $existingPage !== null ? $this->sharedMediaFormFromPage($existingPage) : [];
        $sharedMediaInput = array_key_exists('shared_media', $payload) ? $payload['shared_media'] : $existingSharedMedia;
        $formData['shared_media'] = $this->normalizeSharedMediaFormInput($sharedMediaInput);
        $existingTilePlacements = (
            $existingPage !== null
            && $this->tileSupportEnabled()
            && trim((string) ($existingPage['slug'] ?? '')) !== ''
        )
            ? $this->tileRepository->placementsForPageEditor((string) ($existingPage['slug'] ?? ''), $this->availableLanguages)
            : [];
        $tilePlacementsInput = array_key_exists('tile_placements', $payload)
            ? $payload['tile_placements']
            : $existingTilePlacements;
        $formData['tile_placements'] = $this->normalizeTilePlacementsFormInput($tilePlacementsInput);

        $translationsInput = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];

        foreach ($this->availableLanguages as $language) {
            $existingTranslation = $existingPage !== null ? $this->translationForForm($existingPage, $language) : [];
            $postedTranslation = is_array($translationsInput[$language] ?? null) ? $translationsInput[$language] : [];

            $formData['translations'][$language] = [
                'title' => trim((string) ($postedTranslation['title'] ?? ($existingTranslation['title'] ?? ''))),
                'meta_description' => trim(
                    (string) ($postedTranslation['meta_description'] ?? ($existingTranslation['meta']['description'] ?? ''))
                ),
                'meta_image_src' => trim(
                    (string) ($postedTranslation['meta_image_src'] ?? ($existingTranslation['meta']['image']['src'] ?? ''))
                ),
                'meta_image_alt' => trim(
                    (string) ($postedTranslation['meta_image_alt'] ?? ($existingTranslation['meta']['image']['alt'] ?? ''))
                ),
                'meta_image_title' => trim(
                    (string) ($postedTranslation['meta_image_title'] ?? ($existingTranslation['meta']['image']['title'] ?? ''))
                ),
                'meta_image_width' => trim(
                    (string) ($postedTranslation['meta_image_width'] ?? ($existingTranslation['meta']['image']['width'] ?? ''))
                ),
                'meta_image_height' => trim(
                    (string) ($postedTranslation['meta_image_height'] ?? ($existingTranslation['meta']['image']['height'] ?? ''))
                ),
                'regions' => $this->postedRegionsForForm($postedTranslation),
            ];
        }

        return $formData;
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, mixed>|null $existingPage
     * @return array<string, mixed>
     */
    private function buildPagePayload(array $formData, ?array $existingPage): array
    {
        $slug = (string) ($formData['slug'] ?? '');
        $translations = [];

        foreach ($this->availableLanguages as $language) {
            $translationForm = is_array($formData['translations'][$language] ?? null)
                ? $formData['translations'][$language]
                : [];
            $existingTranslation = $existingPage !== null ? $this->translationForForm($existingPage, $language) : [];
            $translation = $this->buildTranslationPayload($translationForm, $existingTranslation);

            if ($translation !== null) {
                $translations[$language] = $translation;
            }
        }

        $defaultTranslation = $translations[$this->defaultLanguage] ?? null;
        if (!is_array($defaultTranslation)) {
            $defaultTranslation = reset($translations);
        }
        if (!is_array($defaultTranslation)) {
            $defaultTranslation = [];
        }
        $defaultTitle = is_array($defaultTranslation) ? (string) ($defaultTranslation['title'] ?? '') : '';
        $defaultMeta = is_array($defaultTranslation) && is_array($defaultTranslation['meta'] ?? null)
            ? $defaultTranslation['meta']
            : [];
        $existingMeta = is_array($existingPage['meta'] ?? null) ? $existingPage['meta'] : [];
        $sharedMedia = $this->sharedMediaPayloadFromForm(
            is_array($formData['shared_media'] ?? null) ? $formData['shared_media'] : []
        );

        $route = trim((string) ($formData['route'] ?? ''));
        if ($route === '') {
            $route = '/' . $slug;
        }

        $page = [
            'slug' => $slug,
            'type' => PageRepository::TYPE_STRUCTURED_PAGE,
            'status' => (string) ($formData['status'] ?? PageRepository::STATUS_DRAFT),
            'title' => $defaultTitle !== '' ? $defaultTitle : null,
            'layout' => (string) ($formData['layout'] ?? 'standard_page'),
            'route' => $route,
            'blocks' => [],
            'regions' => [],
            'translations' => $translations,
            'meta' => $this->buildRootMeta($defaultMeta, $existingMeta, $sharedMedia),
        ];

        if ($existingPage !== null) {
            foreach ($existingPage as $key => $value) {
                if (!array_key_exists($key, $page) && $key !== 'meta') {
                    $page[$key] = $value;
                }
            }
        }

        return $page;
    }

    /**
     * @param array<string, mixed> $form
     * @param array<string, mixed> $existingTranslation
     * @return array<string, mixed>|null
     */
    private function buildTranslationPayload(array $form, array $existingTranslation): ?array
    {
        $title = trim((string) ($form['title'] ?? ''));
        $meta = $this->buildMeta(is_array($existingTranslation['meta'] ?? null) ? $existingTranslation['meta'] : [], $form);

        $translation = [
            'title' => $title !== '' ? $title : null,
            'blocks' => [],
            'regions' => [],
            'meta' => $meta,
        ];

        $translation['regions'] = $this->buildRegionsPayload(
            is_array($form['regions'] ?? null) ? $form['regions'] : [],
            is_array($existingTranslation['regions'] ?? null) ? $existingTranslation['regions'] : []
        );

        if ($this->translationIsEmpty($translation)) {
            return null;
        }

        return $translation;
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function translationForForm(array $page, string $language): array
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];

        if ($translation !== [] || $language !== $this->defaultLanguage) {
            return $translation;
        }

        $rootTranslation = [];

        if (isset($page['title']) && is_string($page['title']) && trim($page['title']) !== '') {
            $rootTranslation['title'] = trim($page['title']);
        }

        if (is_array($page['blocks'] ?? null) && $page['blocks'] !== []) {
            $rootTranslation['blocks'] = $page['blocks'];
        }

        if (is_array($page['regions'] ?? null) && $page['regions'] !== []) {
            $rootTranslation['regions'] = $page['regions'];
        }

        if (is_array($page['meta'] ?? null) && $page['meta'] !== []) {
            $rootTranslation['meta'] = $page['meta'];
        }

        return $rootTranslation;
    }

    /**
     * @param array<string, mixed> $translation
     * @return array<string, string>
     */
    private function regionsForForm(array $translation): array
    {
        return $this->regionMapper->formValuesFromTranslation($translation);
    }

    /**
     * @param array<string, mixed> $postedTranslation
     * @return array<string, string>
     */
    private function postedRegionsForForm(array $postedTranslation): array
    {
        $inputRegions = is_array($postedTranslation['regions'] ?? null) ? $postedTranslation['regions'] : [];
        $defaults = $this->emptyTranslationFormData()['regions'];

        foreach ($defaults as $key => $defaultValue) {
            $defaults[$key] = trim((string) ($inputRegions[$key] ?? $defaultValue));
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existingRegions
     * @return array<string, mixed>
     */
    private function buildRegionsPayload(array $input, array $existingRegions): array
    {
        return $this->regionMapper->buildRegionsFromPlanValues($input, $existingRegions);
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    private function buildMeta(array $meta, array $form): array
    {
        unset($meta['shared_media']);

        $description = trim((string) ($form['meta_description'] ?? ''));
        if ($description === '') {
            unset($meta['description']);
        } else {
            $meta['description'] = $description;
        }

        $image = AdminEditorialImageService::sanitizeImageMetadata([
            'src' => (string) ($form['meta_image_src'] ?? ''),
            'alt' => (string) ($form['meta_image_alt'] ?? ''),
            'title' => (string) ($form['meta_image_title'] ?? ''),
            'width' => $form['meta_image_width'] ?? null,
            'height' => $form['meta_image_height'] ?? null,
        ]);
        if (is_array($image) && trim((string) ($image['alt'] ?? '')) === '') {
            $fallbackAlt = trim((string) ($form['title'] ?? ''));
            if ($fallbackAlt !== '') {
                $image['alt'] = $fallbackAlt;
            }
        }

        if ($image === null) {
            unset($meta['image']);
        } else {
            $meta['image'] = $image;
        }

        return $meta;
    }

    private function normalizeStatus(string $status): string
    {
        return $status === PageRepository::STATUS_PUBLISHED
            ? PageRepository::STATUS_PUBLISHED
            : PageRepository::STATUS_DRAFT;
    }

    private function sanitizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';

        return trim($slug, '-_');
    }

    private function normalizeRouteInput(string $route): string
    {
        return normalize_public_route($route) ?? '';
    }

    private function pageHasRootContent(array $page): bool
    {
        return (
            trim((string) ($page['title'] ?? '')) !== ''
            || (is_array($page['blocks'] ?? null) && $page['blocks'] !== [])
            || (is_array($page['regions'] ?? null) && $page['regions'] !== [])
            || (is_array($page['meta'] ?? null) && $page['meta'] !== [])
        );
    }

    private function preferredTitle(array $page): string
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
     * @param array<string, mixed> $translation
     */
    private function translationIsEmpty(array $translation): bool
    {
        return (
            trim((string) ($translation['title'] ?? '')) === ''
            && (!is_array($translation['blocks'] ?? null) || $translation['blocks'] === [])
            && (!is_array($translation['regions'] ?? null) || $translation['regions'] === [])
            && (!is_array($translation['meta'] ?? null) || $translation['meta'] === [])
        );
    }

    private function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $groupItem
     */
    private function preferredTileLabel(array $groupItem): string
    {
        $translations = is_array($groupItem['translations'] ?? null) ? $groupItem['translations'] : [];

        foreach ([$this->defaultLanguage, ...$this->availableLanguages] as $language) {
            $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
            $label = trim((string) ($translation['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        foreach ($translations as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $label = trim((string) ($translation['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        return 'Tuile';
    }

    /**
     * @param array<string, mixed> $target
     */
    private function tileTargetSummary(array $target): string
    {
        $targetType = trim((string) ($target['type'] ?? 'page'));

        return match ($targetType) {
            'route' => 'Route : ' . trim((string) ($target['route'] ?? '')),
            'external' => 'URL : ' . trim((string) ($target['url'] ?? '')),
            default => 'Page : ' . trim((string) ($target['pageSlug'] ?? '')),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTranslationFormData(): array
    {
        return [
            'title' => '',
            'meta_description' => '',
            'meta_image_src' => '',
            'meta_image_alt' => '',
            'meta_image_title' => '',
            'meta_image_width' => '',
            'meta_image_height' => '',
            'regions' => StandardPageRegionMapper::emptyPlanFields(),
        ];
    }

    /**
     * @param mixed $input
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTilePlacementsFormInput(mixed $input): array
    {
        if (!$this->tileSupportEnabled() || !is_array($input)) {
            return [];
        }

        $normalized = [];
        foreach (array_values($input) as $index => $placement) {
            if (!is_array($placement)) {
                continue;
            }

            $overridesInput = is_array($placement['overrides'] ?? null) ? $placement['overrides'] : [];
            $overrides = [];

            foreach ($overridesInput as $itemUid => $override) {
                if (!is_string($itemUid) || !is_array($override)) {
                    continue;
                }

                $translationsInput = is_array($override['translations'] ?? null) ? $override['translations'] : [];
                $translations = [];
                foreach ($this->availableLanguages as $language) {
                    if (!is_string($language) || trim($language) === '') {
                        continue;
                    }

                    $translation = is_array($translationsInput[$language] ?? null) ? $translationsInput[$language] : [];
                    $translations[$language] = [
                        'label' => trim((string) ($translation['label'] ?? '')),
                        'alt' => trim((string) ($translation['alt'] ?? '')),
                        'title' => trim((string) ($translation['title'] ?? '')),
                    ];
                }

                $overrides[$itemUid] = [
                    'visibility_mode' => trim((string) ($override['visibility_mode'] ?? 'default')),
                    'target_mode' => trim((string) ($override['target_mode'] ?? 'default')),
                    'target_page_slug' => trim((string) ($override['target_page_slug'] ?? '')),
                    'target_route' => trim((string) ($override['target_route'] ?? '')),
                    'target_url' => trim((string) ($override['target_url'] ?? '')),
                    'translations' => $translations,
                ];
            }

            $normalized[] = [
                'placement_id' => trim((string) ($placement['placement_id'] ?? '')),
                'group_id' => trim((string) ($placement['group_id'] ?? '')),
                'sort_order' => trim((string) ($placement['sort_order'] ?? (($index + 1) * 10))),
                'overrides' => $overrides,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $input
     * @return array<int, array{src: string, alt: string, title: string, caption: string, width: string, height: string}>
     */
    private function normalizeSharedMediaFormInput(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $normalized = [];
        foreach (array_values($input) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sanitized = AdminEditorialImageService::sanitizeImageMetadata([
                'src' => (string) ($item['src'] ?? ''),
                'alt' => (string) ($item['alt'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'caption' => (string) ($item['caption'] ?? ''),
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
            ]);

            if ($sanitized === null) {
                continue;
            }

            $normalized[] = [
                'src' => (string) ($sanitized['src'] ?? ''),
                'alt' => (string) ($sanitized['alt'] ?? ''),
                'title' => (string) ($sanitized['title'] ?? ''),
                'caption' => (string) ($sanitized['caption'] ?? ''),
                'width' => isset($sanitized['width']) ? (string) $sanitized['width'] : '',
                'height' => isset($sanitized['height']) ? (string) $sanitized['height'] : '',
            ];

            if (count($normalized) >= self::SHARED_MEDIA_MAX_ITEMS) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $page
     * @return array<int, array{src: string, alt: string, title: string, caption: string, width: string, height: string}>
     */
    private function sharedMediaFormFromPage(array $page): array
    {
        $meta = is_array($page['meta'] ?? null) ? $page['meta'] : [];
        $sharedMedia = $meta['shared_media'] ?? [];

        return $this->normalizeSharedMediaFormInput($sharedMedia);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sharedMediaPayloadFromForm(array $items): array
    {
        $payload = [];

        foreach ($this->normalizeSharedMediaFormInput($items) as $item) {
            $sanitized = AdminEditorialImageService::sanitizeImageMetadata($item);
            if ($sanitized === null) {
                continue;
            }

            $payload[] = $sanitized;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $defaultMeta
     * @param array<string, mixed> $existingMeta
     * @param array<int, array<string, mixed>> $sharedMedia
     * @return array<string, mixed>
     */
    private function buildRootMeta(array $defaultMeta, array $existingMeta, array $sharedMedia): array
    {
        $meta = $defaultMeta;

        foreach ($existingMeta as $key => $value) {
            if (!is_string($key) || in_array($key, ['description', 'image', 'shared_media'], true)) {
                continue;
            }

            if (!array_key_exists($key, $meta)) {
                $meta[$key] = $value;
            }
        }

        if ($sharedMedia === []) {
            unset($meta['shared_media']);
        } else {
            $meta['shared_media'] = $sharedMedia;
        }

        return $meta;
    }
}
