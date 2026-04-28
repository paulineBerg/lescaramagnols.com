<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Admin\Navigation\NavigationBuilderActionParser;
use Caramagnols\Admin\Navigation\NavigationItemPathCodec;
use Caramagnols\Admin\Navigation\NavigationItemLabelManager;
use Caramagnols\Content\PageRepository;
use Caramagnols\Navigation\NavigationRepository;
use Caramagnols\Navigation\NavigationViewModelBuilder;

final class AdminNavigationService
{
    public const DEFAULT_LOCATION = 'primary';
    private const FOOTER_NOTICE_DEFAULT_TRANSLATION_KEY = 'TXT_PiedPageModele';
    private const FOOTER_NOTICE_DEFAULT_LANGUAGE = 'fr';

    /**
     * @var array<string, array{label: string, summary: string, supportsChildren: bool, editorKind: string, addKinds: array<int, string>}>
     */
    private const LOCATION_DEFINITIONS = [
        'utility' => [
            'label' => 'Réseaux / utilitaire',
            'summary' => 'Réseaux sociaux et liens d’appoint du bandeau haut.',
            'supportsChildren' => false,
            'editorKind' => 'navigation',
            'addKinds' => ['external', 'route', 'page'],
        ],
        'primary' => [
            'label' => 'Menu principal',
            'summary' => 'Navigation haute desktop/mobile avec sous-menus.',
            'supportsChildren' => true,
            'editorKind' => 'navigation',
            'addKinds' => ['route', 'page', 'group', 'external'],
        ],
        'footer' => [
            'label' => 'Pied de page',
            'summary' => 'Liens de pied de page et navigation complémentaire.',
            'supportsChildren' => true,
            'editorKind' => 'navigation',
            'addKinds' => ['route', 'page', 'group', 'external'],
        ],
        'sideLeft' => [
            'label' => 'Bloc latéral gauche',
            'summary' => 'Cartes éditoriales fixes à gauche du contenu.',
            'supportsChildren' => false,
            'editorKind' => 'content_card',
            'addKinds' => ['content_card'],
        ],
        'sideRight' => [
            'label' => 'Bloc latéral droit',
            'summary' => 'Cartes éditoriales fixes à droite du contenu.',
            'supportsChildren' => false,
            'editorKind' => 'content_card',
            'addKinds' => ['content_card'],
        ],
    ];

    private readonly NavigationBuilderActionParser $actionParser;
    private readonly NavigationItemPathCodec $pathCodec;
    private readonly NavigationItemLabelManager $labelManager;

    public function __construct(
        private readonly NavigationRepository $repository,
        private readonly PageRepository $pageRepository,
        ?NavigationBuilderActionParser $actionParser = null,
        ?NavigationItemPathCodec $pathCodec = null,
        ?NavigationItemLabelManager $labelManager = null
    ) {
        $this->actionParser = $actionParser ?? new NavigationBuilderActionParser();
        $this->pathCodec = $pathCodec ?? new NavigationItemPathCodec(
            array_keys(self::LOCATION_DEFINITIONS),
            self::DEFAULT_LOCATION
        );
        $availableLanguages = function_exists('site_available_languages')
            ? site_available_languages()
            : [(string) app_config('default_lang', 'fr')];
        $this->labelManager = $labelManager ?? new NavigationItemLabelManager(
            (string) app_config('default_lang', 'fr'),
            is_array($availableLanguages) ? $availableLanguages : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function viewModel(?string $activeLocation = null, ?string $selectedPath = null): array
    {
        $canonical = $this->loadCanonical();

        return $this->buildViewModel(
            $canonical,
            $this->normalizeLocation($activeLocation),
            $selectedPath
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{message: string|null, error: string|null, view: array<string, mixed>}
     */
    public function handle(array $payload): array
    {
        $canonical = $this->canonicalFromPayload($payload);
        $activeLocation = $this->normalizeLocation($this->stringOrNull($payload['active_location'] ?? null));
        $selectedPath = $this->stringOrNull($payload['selected_item'] ?? null);
        $action = $this->parseAction($this->stringOrNull($payload['builder_action'] ?? null));
        $message = null;
        $error = null;

        switch ($action['name']) {
            case 'switch_location':
                $activeLocation = $this->normalizeLocation($action['target']);
                $selectedPath = $this->firstItemPath($canonical, $activeLocation);
                break;

            case 'select':
                $selectedPath = $action['target'];
                $parsedPath = $this->parseItemPath($selectedPath);
                if ($parsedPath !== null) {
                    $activeLocation = $parsedPath['location'];
                }
                break;

            case 'append':
                $appendLocation = $this->normalizeLocation($action['target']);
                $appendKind = $this->normalizeAppendKind($appendLocation, $action['extra']);
                $canonical['locations'][$appendLocation][] = $this->newItem($appendLocation, $appendKind);
                $activeLocation = $appendLocation;
                $selectedPath = $this->encodeItemPath(
                    $appendLocation,
                    [max(0, count($canonical['locations'][$appendLocation]) - 1)]
                );
                break;

            case 'append_child':
                $parsedPath = $this->parseItemPath($action['target']);
                if ($parsedPath !== null) {
                    $childKind = $this->normalizeAppendKind($parsedPath['location'], $action['extra']);
                    $canonical['locations'][$parsedPath['location']] = $this->appendChildAtPath(
                        $canonical['locations'][$parsedPath['location']] ?? [],
                        $parsedPath['indices'],
                        $this->newItem($parsedPath['location'], $childKind)
                    );
                    $children = $this->itemAtPath(
                        $canonical['locations'][$parsedPath['location']] ?? [],
                        $parsedPath['indices']
                    )['children'] ?? [];
                    $selectedPath = $this->encodeItemPath(
                        $parsedPath['location'],
                        array_merge($parsedPath['indices'], [max(0, count($children) - 1)])
                    );
                    $activeLocation = $parsedPath['location'];
                }
                break;

            case 'duplicate':
                $parsedPath = $this->parseItemPath($action['target']);
                if ($parsedPath !== null) {
                    $canonical['locations'][$parsedPath['location']] = $this->duplicateItemAtPath(
                        $canonical['locations'][$parsedPath['location']] ?? [],
                        $parsedPath['indices']
                    );
                    $duplicatedIndex = $parsedPath['indices'];
                    $duplicatedIndex[count($duplicatedIndex) - 1]++;
                    $selectedPath = $this->encodeItemPath($parsedPath['location'], $duplicatedIndex);
                    $activeLocation = $parsedPath['location'];
                }
                break;

            case 'remove':
                $parsedPath = $this->parseItemPath($action['target']);
                if ($parsedPath !== null) {
                    $canonical['locations'][$parsedPath['location']] = $this->removeItemAtPath(
                        $canonical['locations'][$parsedPath['location']] ?? [],
                        $parsedPath['indices']
                    );
                    $activeLocation = $parsedPath['location'];
                    $selectedPath = $this->fallbackSelectionAfterRemoval($canonical, $parsedPath);
                }
                break;

            case 'move_up':
            case 'move_down':
                $parsedPath = $this->parseItemPath($action['target']);
                if ($parsedPath !== null) {
                    $canonical['locations'][$parsedPath['location']] = $this->moveItemAtPath(
                        $canonical['locations'][$parsedPath['location']] ?? [],
                        $parsedPath['indices'],
                        $action['name'] === 'move_up' ? -1 : 1
                    );
                    $activeLocation = $parsedPath['location'];
                    $movedIndices = $parsedPath['indices'];
                    $lastPosition = count($movedIndices) - 1;
                    $movedIndices[$lastPosition] = max(0, $movedIndices[$lastPosition] + ($action['name'] === 'move_up' ? -1 : 1));
                    $selectedPath = $this->encodeItemPath($parsedPath['location'], $movedIndices);
                }
                break;

            case 'save':
            default:
                $validationError = $this->validateCanonical($canonical);
                if ($validationError !== null) {
                    $error = $validationError;
                    break;
                }

                if ($this->repository->saveCanonical($canonical)) {
                    $message = 'Menus sauvegardés via le builder visuel.';
                    app_runtime_cache_clear(['navigation']);
                    $canonical = $this->loadCanonical();
                    $selectedPath = $this->normalizeSelectedPath($canonical, $activeLocation, $selectedPath);
                } else {
                    $error = 'Impossible d’enregistrer la navigation éditoriale.';
                }
                break;
        }

        return [
            'message' => $message,
            'error' => $error,
            'view' => $this->buildViewModel($canonical, $activeLocation, $selectedPath),
        ];
    }

    /**
     * @return array<int, array{slug: string, title: string, route: string, status: string}>
     */
    public function pageReferenceOptions(): array
    {
        $references = [];

        foreach ($this->pageRepository->all() as $page) {
            if (!is_array($page)) {
                continue;
            }

            $references[] = [
                'slug' => (string) ($page['slug'] ?? ''),
                'title' => $this->pageTitle($page),
                'route' => normalize_public_route((string) ($page['route'] ?? '')) ?? '',
                'status' => (string) ($page['status'] ?? PageRepository::STATUS_DRAFT),
            ];
        }

        usort(
            $references,
            static fn (array $left, array $right): int => strcmp($left['slug'], $right['slug'])
        );

        return $references;
    }

    /**
     * @return array{
     *   totalItems: int,
     *   rootItems: int,
     *   nestedItems: int,
     *   configuredLocations: int,
     *   totalLocations: int,
     *   emptyLocations: int,
     *   pageTargets: int,
     *   routeTargets: int,
     *   externalTargets: int,
     *   groups: int,
     *   contentCards: int,
     *   bannerConfigured: bool,
     *   backToTopConfigured: bool,
     *   footerNoticeConfigured: bool
     * }
     */
    public function dashboardSummary(): array
    {
        $canonical = $this->loadCanonical();
        $locations = is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [];
        $trackedLocations = array_keys(self::LOCATION_DEFINITIONS);
        $summary = [
            'totalItems' => 0,
            'rootItems' => 0,
            'nestedItems' => 0,
            'configuredLocations' => 0,
            'totalLocations' => count($trackedLocations),
            'emptyLocations' => 0,
            'pageTargets' => 0,
            'routeTargets' => 0,
            'externalTargets' => 0,
            'groups' => 0,
            'contentCards' => 0,
            'bannerConfigured' => $this->isSystemLocationConfigured($locations['banner'] ?? null),
            'backToTopConfigured' => $this->isSystemLocationConfigured($locations['remonter'] ?? null),
            'footerNoticeConfigured' => $this->isSystemLocationConfigured($locations['footerNotice'] ?? null),
        ];

        foreach ($trackedLocations as $locationKey) {
            $items = is_array($locations[$locationKey] ?? null) ? $locations[$locationKey] : [];

            if ($items === []) {
                $summary['emptyLocations']++;
                continue;
            }

            $summary['configuredLocations']++;
            $summary['rootItems'] += count(array_filter($items, 'is_array'));

            $counts = $this->countNavigationDashboardItems($items);
            foreach ($counts as $key => $value) {
                if (array_key_exists($key, $summary)) {
                    $summary[$key] += $value;
                }
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewModel(array $canonical, string $activeLocation, ?string $selectedPath): array
    {
        $activeLocation = $this->normalizeLocation($activeLocation);
        $selectedPath = $this->normalizeSelectedPath($canonical, $activeLocation, $selectedPath);
        $selected = $this->selectedItemDescriptor($canonical, $selectedPath);

        return [
            'canonical' => $canonical,
            'locations' => is_array($canonical['locations'] ?? null) ? $canonical['locations'] : [],
            'locationDefinitions' => self::LOCATION_DEFINITIONS,
            'activeLocation' => $activeLocation,
            'selectedItemPath' => $selectedPath,
            'selectedItem' => $selected,
            'banner' => is_array($canonical['locations']['banner'] ?? null) ? $canonical['locations']['banner'] : [],
            'backToTop' => is_array($canonical['locations']['remonter'] ?? null) ? $canonical['locations']['remonter'] : [],
            'footerNotice' => $this->footerNoticeForView(
                is_array($canonical['locations']['footerNotice'] ?? null) ? $canonical['locations']['footerNotice'] : []
            ),
            'pageOptions' => $this->publishedPageOptions(),
            'pageReferences' => $this->pageReferenceOptions(),
            'preview' => $this->buildPreview($canonical),
            'expertJson' => $this->prettyJson($canonical),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function canonicalFromPayload(array $payload): array
    {
        $existingCanonical = $this->loadCanonical();
        $existingLocations = is_array($existingCanonical['locations'] ?? null) ? $existingCanonical['locations'] : [];

        if (
            !is_array($payload['locations'] ?? null)
            && !is_array($payload['banner'] ?? null)
            && !is_array($payload['remonter'] ?? null)
            && !is_array($payload['footer_notice'] ?? null)
        ) {
            return $existingCanonical;
        }

        $postedLocations = is_array($payload['locations'] ?? null) ? $payload['locations'] : [];
        $existingFooterNotice = is_array($existingCanonical['locations']['footerNotice'] ?? null)
            ? $existingCanonical['locations']['footerNotice']
            : [];
        $existingBanner = is_array($existingLocations['banner'] ?? null)
            ? $existingLocations['banner']
            : [];
        $existingBackToTop = is_array($existingLocations['remonter'] ?? null)
            ? $existingLocations['remonter']
            : [];
        $canonicalLocations = [
            'remonter' => is_array($payload['remonter'] ?? null)
                ? $this->normalizeBackToTop($payload['remonter'], $existingBackToTop)
                : ($existingBackToTop !== [] ? $existingBackToTop : $this->normalizeBackToTop([], [])),
            'banner' => is_array($payload['banner'] ?? null)
                ? $this->normalizeBanner($payload['banner'], $existingBanner)
                : ($existingBanner !== [] ? $existingBanner : $this->normalizeBanner([], [])),
            'footerNotice' => is_array($payload['footer_notice'] ?? null)
                ? $this->normalizeFooterNoticeFromPost($payload['footer_notice'], $existingFooterNotice)
                : $this->normalizeFooterNoticeFromPost([], $existingFooterNotice),
        ];

        foreach (array_keys(self::LOCATION_DEFINITIONS) as $location) {
            if (array_key_exists($location, $postedLocations) && is_array($postedLocations[$location])) {
                $canonicalLocations[$location] = $this->normalizePostedItems($postedLocations[$location], $location);
                continue;
            }

            $canonicalLocations[$location] = is_array($existingLocations[$location] ?? null)
                ? $existingLocations[$location]
                : [];
        }

        return [
            'meta' => ['version' => NavigationRepository::SCHEMA_VERSION],
            'locations' => $canonicalLocations,
        ];
    }

    /**
     * @return array{name: string, target: string, extra: string}
     */
    private function parseAction(?string $payload): array
    {
        return $this->actionParser->parse($payload);
    }

    /**
     * @param array<string, mixed> $banner
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function normalizeBanner(array $banner, array $existing = []): array
    {
        return [
            'image' => $this->stringOrNull($banner['image'] ?? null),
            'headline' => $this->labelManager->normalizeFromPost(
                $banner['headline'] ?? null,
                $banner['headline_translation_key'] ?? null,
                $banner['headline_translations'] ?? null,
                $banner['headline_default_language'] ?? null,
                is_array($existing['headline'] ?? null) ? $existing['headline'] : [],
                ['TXT_']
            ),
            'accessibility' => [
                'alt' => $this->stringOrNull($banner['alt'] ?? null),
                'title' => $this->stringOrNull($banner['title'] ?? null),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $backToTop
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function normalizeBackToTop(array $backToTop, array $existing = []): array
    {
        return [
            'label' => $this->labelManager->normalizeFromPost(
                $backToTop['label'] ?? null,
                $backToTop['label_translation_key'] ?? null,
                $backToTop['label_translations'] ?? null,
                $backToTop['label_default_language'] ?? null,
                is_array($existing['label'] ?? null) ? $existing['label'] : [],
                ['REMONTER_']
            ),
            'accessibility' => [
                'alt' => $this->stringOrNull($backToTop['alt'] ?? null),
                'title' => $this->stringOrNull($backToTop['title'] ?? null),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $footerNotice
     * @param array<string, mixed> $existing
     * @return array<string, mixed>
     */
    private function normalizeFooterNoticeFromPost(array $footerNotice, array $existing = []): array
    {
        $availableLanguages = array_values(
            array_filter(
                array_map(
                    static fn (mixed $language): string => is_string($language) ? strtolower(trim($language)) : '',
                    site_available_languages()
                ),
                static fn (string $language): bool => $language !== ''
            )
        );
        if (!in_array(self::FOOTER_NOTICE_DEFAULT_LANGUAGE, $availableLanguages, true)) {
            array_unshift($availableLanguages, self::FOOTER_NOTICE_DEFAULT_LANGUAGE);
            $availableLanguages = array_values(array_unique($availableLanguages));
        }

        $existingTranslations = is_array($existing['translations'] ?? null) ? $existing['translations'] : [];
        $postedTranslations = is_array($footerNotice['translations'] ?? null) ? $footerNotice['translations'] : [];

        $translations = [];
        foreach ($availableLanguages as $language) {
            $postedValue = $this->stringOrNull($postedTranslations[$language] ?? null);
            $existingValue = $this->stringOrNull($existingTranslations[$language] ?? null);
            $value = $postedValue ?? $existingValue;

            if ($value !== null) {
                $translations[$language] = $value;
            }
        }

        $defaultLanguage = $this->stringOrNull($footerNotice['default_language'] ?? null)
            ?? $this->stringOrNull($existing['defaultLanguage'] ?? null)
            ?? self::FOOTER_NOTICE_DEFAULT_LANGUAGE;
        if (!in_array($defaultLanguage, $availableLanguages, true)) {
            $defaultLanguage = self::FOOTER_NOTICE_DEFAULT_LANGUAGE;
        }

        $translationKey = $this->stringOrNull($footerNotice['translation_key'] ?? null)
            ?? $this->stringOrNull($existing['translationKey'] ?? null)
            ?? self::FOOTER_NOTICE_DEFAULT_TRANSLATION_KEY;

        if (!isset($translations[$defaultLanguage])) {
            $fallback = $this->translationForLanguage($defaultLanguage, $translationKey);
            if ($fallback !== null) {
                $translations[$defaultLanguage] = $fallback;
            }
        }

        return [
            'defaultLanguage' => $defaultLanguage,
            'translationKey' => $translationKey,
            'translations' => $translations,
        ];
    }

    /**
     * @param array<int|string, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizePostedItems(array $items, string $location): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $children = [];
            if (($this->locationDefinition($location)['supportsChildren'] ?? false) && is_array($item['children'] ?? null)) {
                $children = $this->normalizePostedItems($item['children'], $location);
            }

            $kind = $this->normalizePostedKind($location, $item['kind'] ?? null, $children !== []);
            $targetMode = $this->normalizeTargetMode($location, $kind, $item['target_mode'] ?? null, $item);
            if (
                $kind !== 'group'
                && $kind !== 'content_card'
                && in_array($targetMode, ['page', 'route', 'external'], true)
            ) {
                $kind = $targetMode;
            }
            $target = [
                'pageSlug' => $targetMode === 'page'
                    ? (
                        $this->resolvePublishedPageSlugAlias($this->stringOrNull($item['target_page_slug'] ?? null))
                        ?? $this->stringOrNull($item['target_page_slug'] ?? null)
                    )
                    : null,
                'route' => $targetMode === 'route' ? $this->normalizeRoute($item['target_route'] ?? null) : null,
                'url' => $targetMode === 'external' ? $this->stringOrNull($item['target_url'] ?? null) : null,
                'openInNewTab' => $targetMode === 'external'
                    ? (bool) ($item['open_in_new_tab'] ?? false)
                    : false,
            ];

            $normalized[] = [
                'id' => $this->stringOrNull($item['id'] ?? null) ?? $this->newItemId($location),
                'kind' => $kind,
                'label' => $this->labelManager->normalizeFromPost(
                    $item['label_text'] ?? null,
                    $item['label_translation_key'] ?? null,
                    $item['label_translations'] ?? null,
                    $item['label_default_language'] ?? null,
                    is_array($item['label'] ?? null) ? $item['label'] : [],
                    ['MENU_']
                ),
                'target' => $target,
                'media' => [
                    'image' => $this->stringOrNull($item['image'] ?? null),
                ],
                'content' => [
                    'text' => $this->stringOrNull($item['content_text'] ?? null),
                ],
                'accessibility' => [
                    'alt' => $this->stringOrNull($item['alt'] ?? null),
                    'title' => $this->stringOrNull($item['title'] ?? null),
                ],
                'presentation' => $this->normalizePostedPresentation($item, $kind, $location),
                'children' => $children,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param array<string, mixed> $canonical
     */
    private function validateCanonical(array $canonical): ?string
    {
        foreach (array_keys(self::LOCATION_DEFINITIONS) as $location) {
            $items = is_array($canonical['locations'][$location] ?? null) ? $canonical['locations'][$location] : [];
            $error = $this->validateItems($items, $location, $this->locationDefinition($location)['label']);

            if ($error !== null) {
                return $error;
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function validateItems(array $items, string $location, string $contextLabel): ?string
    {
        foreach ($items as $index => $item) {
            $itemNumber = $index + 1;
            $kind = (string) ($item['kind'] ?? 'route');
            $label = $this->labelToString($item['label'] ?? null) ?? sprintf('item %d', $itemNumber);
            $context = sprintf('%s > %s', $contextLabel, $label);

            if (in_array($location, ['sideLeft', 'sideRight'], true) && $kind !== 'content_card') {
                return sprintf('%s : les blocs latéraux n’acceptent que des cartes éditoriales.', $context);
            }

            if ($location === 'utility' && $kind === 'group') {
                return sprintf('%s : le menu utilitaire ne gère pas de groupes.', $context);
            }

            if (in_array($location, ['sideLeft', 'sideRight'], true) && ((array) ($item['children'] ?? [])) !== []) {
                return sprintf('%s : les cartes latérales ne gèrent pas de sous-menus.', $context);
            }

            if ($kind === 'group') {
                $children = is_array($item['children'] ?? null) ? $item['children'] : [];
                $presentation = is_array($item['presentation'] ?? null) ? $item['presentation'] : [];
                $displayMode = strtolower(trim((string) ($presentation['displayMode'] ?? 'dropdown')));

                if ($displayMode === 'mega' && $location !== 'primary') {
                    return sprintf('%s : le mode mega menu est réservé au menu principal.', $context);
                }

                $featuredCard = is_array($presentation['featuredCard'] ?? null) ? $presentation['featuredCard'] : [];
                $featuredTarget = is_array($featuredCard['target'] ?? null) ? $featuredCard['target'] : [];
                $featuredPageSlug = $this->stringOrNull($featuredTarget['pageSlug'] ?? null);
                $featuredRoute = $this->normalizeRoute($featuredTarget['route'] ?? null);
                $featuredUrl = $this->stringOrNull($featuredTarget['url'] ?? null);

                if ($featuredPageSlug !== null) {
                    $page = $this->findPublishedPageBySlugAlias($featuredPageSlug);
                    if ($page === null) {
                        return sprintf('%s : la page ciblée par la carte mise en avant doit être publiée.', $context);
                    }
                }

                if ($featuredPageSlug !== null && ($featuredRoute !== null || $featuredUrl !== null)) {
                    return sprintf('%s : la carte mise en avant ne peut cibler qu’une seule destination.', $context);
                }

                if ($featuredRoute !== null && $featuredUrl !== null) {
                    return sprintf('%s : la carte mise en avant ne peut pas avoir une route interne et une URL externe en même temps.', $context);
                }

                $childError = $this->validateItems($children, $location, $context);

                if ($childError !== null) {
                    return $childError;
                }

                continue;
            }

            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            $pageSlug = $this->stringOrNull($target['pageSlug'] ?? null);
            $route = $this->normalizeRoute($target['route'] ?? null);
            $url = $this->stringOrNull($target['url'] ?? null);

            if ($kind === 'page') {
                if ($pageSlug === null) {
                    return sprintf('%s : aucune page publiée n’est sélectionnée.', $context);
                }

                $page = $this->findPublishedPageBySlugAlias($pageSlug);
                if ($page === null) {
                    return sprintf('%s : la page cible doit être publiée.', $context);
                }
            }

            if ($kind === 'route' && $route === null) {
                return sprintf('%s : la route interne est obligatoire.', $context);
            }

            if ($kind === 'external' && $url === null) {
                return sprintf('%s : l’URL externe est obligatoire.', $context);
            }

            if ($kind === 'content_card') {
                $hasEditorialPayload = $this->stringOrNull($item['media']['image'] ?? null) !== null
                    || $this->labelToString($item['label'] ?? null) !== null
                    || $this->stringOrNull($item['content']['text'] ?? null) !== null;

                if (!$hasEditorialPayload) {
                    return sprintf('%s : une carte latérale doit avoir au moins une image, un titre ou un texte.', $context);
                }

                if ($pageSlug !== null) {
                    $page = $this->findPublishedPageBySlugAlias($pageSlug);
                    if ($page === null) {
                        return sprintf('%s : la page cible doit être publiée.', $context);
                    }
                } elseif ($route === null && $url === null) {
                    continue;
                } elseif ($route === null && $url !== null) {
                    continue;
                } elseif ($route !== null && $url === null) {
                    continue;
                } else {
                    return sprintf('%s : une carte ne peut pas cibler à la fois une route interne et une URL externe.', $context);
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function selectedItemDescriptor(array $canonical, ?string $selectedPath): array
    {
        if ($selectedPath === null) {
            return [
                'path' => null,
                'location' => null,
                'indices' => [],
                'inputName' => '',
                'item' => null,
                'locationDefinition' => null,
            ];
        }

        $parsedPath = $this->parseItemPath($selectedPath);
        if ($parsedPath === null) {
            return [
                'path' => null,
                'location' => null,
                'indices' => [],
                'inputName' => '',
                'item' => null,
                'locationDefinition' => null,
            ];
        }

        $items = is_array($canonical['locations'][$parsedPath['location']] ?? null)
            ? $canonical['locations'][$parsedPath['location']]
            : [];
        $item = $this->itemAtPath($items, $parsedPath['indices']);

        return [
            'path' => $selectedPath,
            'location' => $parsedPath['location'],
            'indices' => $parsedPath['indices'],
            'inputName' => $this->inputNameForPath($parsedPath['location'], $parsedPath['indices']),
            'item' => $item,
            'locationDefinition' => $this->locationDefinition($parsedPath['location']),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function publishedPageOptions(): array
    {
        $options = [];

        foreach ($this->pageRepository->published() as $page) {
            if (!is_array($page)) {
                continue;
            }

            $options[] = [
                'slug' => (string) ($page['slug'] ?? ''),
                'title' => $this->pageTitle($page),
                'route' => normalize_public_route((string) ($page['route'] ?? '')) ?? '',
            ];
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strcmp($left['slug'], $right['slug'])
        );

        return $options;
    }

    /**
     * @param array<string, mixed> $canonical
     * @return array<string, mixed>
     */
    private function buildPreview(array $canonical): array
    {
        $builder = new NavigationViewModelBuilder($this->pageRepository, site_available_languages());

        return $builder->build($canonical, (string) app_config('default_lang', 'fr'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCanonical(): array
    {
        return $this->repository->loadCanonical();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{
     *   totalItems: int,
     *   nestedItems: int,
     *   pageTargets: int,
     *   routeTargets: int,
     *   externalTargets: int,
     *   groups: int,
     *   contentCards: int
     * }
     */
    private function countNavigationDashboardItems(array $items, int $depth = 0): array
    {
        $summary = [
            'totalItems' => 0,
            'nestedItems' => 0,
            'pageTargets' => 0,
            'routeTargets' => 0,
            'externalTargets' => 0,
            'groups' => 0,
            'contentCards' => 0,
        ];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $summary['totalItems']++;
            if ($depth > 0) {
                $summary['nestedItems']++;
            }

            $kind = (string) ($item['kind'] ?? '');
            if ($kind === 'group') {
                $summary['groups']++;
            }
            if ($kind === 'content_card') {
                $summary['contentCards']++;
            }

            $target = is_array($item['target'] ?? null) ? $item['target'] : [];
            if (trim((string) ($target['pageSlug'] ?? '')) !== '') {
                $summary['pageTargets']++;
            } elseif (trim((string) ($target['route'] ?? '')) !== '') {
                $summary['routeTargets']++;
            } elseif (trim((string) ($target['url'] ?? '')) !== '') {
                $summary['externalTargets']++;
            }

            $children = is_array($item['children'] ?? null) ? $item['children'] : [];
            if ($children === []) {
                continue;
            }

            $childrenSummary = $this->countNavigationDashboardItems($children, $depth + 1);
            foreach ($childrenSummary as $key => $value) {
                $summary[$key] += $value;
            }
        }

        return $summary;
    }

    private function isSystemLocationConfigured(mixed $payload): bool
    {
        if (is_array($payload)) {
            foreach ($payload as $value) {
                if ($this->isSystemLocationConfigured($value)) {
                    return true;
                }
            }

            return false;
        }

        if (is_string($payload)) {
            return trim($payload) !== '';
        }

        if (is_bool($payload)) {
            return $payload;
        }

        if (is_int($payload) || is_float($payload)) {
            return $payload !== 0;
        }

        return $payload !== null;
    }

    private function normalizeLocation(?string $location): string
    {
        $location = is_string($location) ? trim($location) : '';

        return array_key_exists($location, self::LOCATION_DEFINITIONS) ? $location : self::DEFAULT_LOCATION;
    }

    /**
     * @return array{label: string, summary: string, supportsChildren: bool, editorKind: string, addKinds: array<int, string>}
     */
    private function locationDefinition(string $location): array
    {
        return self::LOCATION_DEFINITIONS[$this->normalizeLocation($location)];
    }

    private function normalizeSelectedPath(array $canonical, string $activeLocation, ?string $selectedPath): ?string
    {
        $selectedPath = $this->stringOrNull($selectedPath);
        $parsedPath = $this->parseItemPath($selectedPath);

        if (
            $parsedPath !== null
            && $parsedPath['location'] === $activeLocation
            && $this->itemAtPath($canonical['locations'][$activeLocation] ?? [], $parsedPath['indices']) !== null
        ) {
            return $selectedPath;
        }

        return $this->firstItemPath($canonical, $activeLocation);
    }

    private function firstItemPath(array $canonical, string $location): ?string
    {
        $items = is_array($canonical['locations'][$location] ?? null) ? $canonical['locations'][$location] : [];

        if ($items === []) {
            return null;
        }

        return $this->encodeItemPath($location, [0]);
    }

    /**
     * @return array{location: string, indices: array<int, int>}|null
     */
    private function parseItemPath(?string $path): ?array
    {
        return $this->pathCodec->parse($path);
    }

    /**
     * @param array<int, int> $indices
     */
    private function encodeItemPath(string $location, array $indices): string
    {
        return $this->pathCodec->encode($location, $indices);
    }

    /**
     * @param array<int, int> $indices
     */
    private function inputNameForPath(string $location, array $indices): string
    {
        return $this->pathCodec->inputName($location, $indices);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $indices
     * @return array<string, mixed>|null
     */
    private function itemAtPath(array $items, array $indices): ?array
    {
        $currentItems = $items;

        foreach ($indices as $offset => $index) {
            if (!isset($currentItems[$index]) || !is_array($currentItems[$index])) {
                return null;
            }

            $currentItem = $currentItems[$index];

            if ($offset === count($indices) - 1) {
                return $currentItem;
            }

            $currentItems = is_array($currentItem['children'] ?? null) ? $currentItem['children'] : [];
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $indices
     * @return array<int, array<string, mixed>>
     */
    private function appendChildAtPath(array $items, array $indices, array $child): array
    {
        return $this->mutateItemAtPath(
            $items,
            $indices,
            function (array $item) use ($child): array {
                $item['kind'] = 'group';
                $item['children'] = array_values(is_array($item['children'] ?? null) ? $item['children'] : []);
                $item['children'][] = $child;

                return $item;
            }
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $indices
     * @return array<int, array<string, mixed>>
     */
    private function duplicateItemAtPath(array $items, array $indices): array
    {
        return $this->mutateSiblingList(
            $items,
            $indices,
            function (array $siblings, int $index): array {
                if (!isset($siblings[$index]) || !is_array($siblings[$index])) {
                    return array_values($siblings);
                }

                $copy = $this->reseedItemIdentifiers($siblings[$index]);
                array_splice($siblings, $index + 1, 0, [$copy]);

                return array_values($siblings);
            }
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $indices
     * @return array<int, array<string, mixed>>
     */
    private function removeItemAtPath(array $items, array $indices): array
    {
        return $this->mutateSiblingList(
            $items,
            $indices,
            static function (array $siblings, int $index): array {
                if (!isset($siblings[$index])) {
                    return array_values($siblings);
                }

                array_splice($siblings, $index, 1);

                return array_values($siblings);
            }
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $indices
     * @return array<int, array<string, mixed>>
     */
    private function moveItemAtPath(array $items, array $indices, int $direction): array
    {
        return $this->mutateSiblingList(
            $items,
            $indices,
            static function (array $siblings, int $index) use ($direction): array {
                $targetIndex = $index + $direction;

                if (!isset($siblings[$index]) || !isset($siblings[$targetIndex])) {
                    return array_values($siblings);
                }

                [$siblings[$index], $siblings[$targetIndex]] = [$siblings[$targetIndex], $siblings[$index]];

                return array_values($siblings);
            }
        );
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $indices
     * @param callable(array<string, mixed>): array<string, mixed> $mutation
     * @return array<int, array<string, mixed>>
     */
    private function mutateItemAtPath(array $items, array $indices, callable $mutation): array
    {
        $index = array_shift($indices);
        if ($index === null || !isset($items[$index]) || !is_array($items[$index])) {
            return array_values($items);
        }

        if ($indices === []) {
            $items[$index] = $mutation($items[$index]);

            return array_values($items);
        }

        $children = is_array($items[$index]['children'] ?? null) ? $items[$index]['children'] : [];
        $items[$index]['children'] = $this->mutateItemAtPath($children, $indices, $mutation);

        return array_values($items);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<int, int> $indices
     * @param callable(array<int, array<string, mixed>>, int): array<int, array<string, mixed>> $mutation
     * @return array<int, array<string, mixed>>
     */
    private function mutateSiblingList(array $items, array $indices, callable $mutation): array
    {
        $index = array_shift($indices);
        if ($index === null) {
            return array_values($items);
        }

        if ($indices === []) {
            return $mutation(array_values($items), $index);
        }

        if (!isset($items[$index]) || !is_array($items[$index])) {
            return array_values($items);
        }

        $children = is_array($items[$index]['children'] ?? null) ? $items[$index]['children'] : [];
        $items[$index]['children'] = $this->mutateSiblingList($children, $indices, $mutation);

        return array_values($items);
    }

    /**
     * @param array<string, mixed> $canonical
     * @param array{location: string, indices: array<int, int>} $removedPath
     */
    private function fallbackSelectionAfterRemoval(array $canonical, array $removedPath): ?string
    {
        $location = $removedPath['location'];
        $items = is_array($canonical['locations'][$location] ?? null) ? $canonical['locations'][$location] : [];

        if ($items === []) {
            return null;
        }

        $indices = $removedPath['indices'];
        $lastPosition = count($indices) - 1;
        $indices[$lastPosition] = max(0, $indices[$lastPosition] - 1);

        while ($indices !== []) {
            $candidate = $this->itemAtPath($items, $indices);
            if ($candidate !== null) {
                return $this->encodeItemPath($location, $indices);
            }

            array_pop($indices);
        }

        return $this->firstItemPath($canonical, $location);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function reseedItemIdentifiers(array $item): array
    {
        $item['id'] = $this->newItemId('copy');
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];

        foreach ($children as $index => $child) {
            if (!is_array($child)) {
                continue;
            }

            $children[$index] = $this->reseedItemIdentifiers($child);
        }

        $item['children'] = array_values($children);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function newItem(string $location, string $kind): array
    {
        $isContentCard = $kind === 'content_card';
        $label = match ($kind) {
            'group' => 'Nouveau groupe',
            'page' => 'Nouveau lien page',
            'external' => 'Nouveau lien externe',
            'content_card' => 'Nouvelle carte latérale',
            default => 'Nouveau lien interne',
        };

        return [
            'id' => $this->newItemId($location),
            'kind' => $kind,
            'label' => [
                'text' => $label,
                'translationKey' => null,
            ],
            'target' => [
                'pageSlug' => null,
                'route' => $kind === 'route' ? '/' : null,
                'url' => null,
                'openInNewTab' => $kind === 'external',
            ],
            'media' => [
                'image' => $isContentCard ? '/assets/images/structure/favicon-48x48.png' : null,
            ],
            'content' => [
                'text' => $isContentCard ? 'Texte de présentation de la carte.' : null,
            ],
            'accessibility' => [
                'alt' => $label,
                'title' => $label,
            ],
            'presentation' => [
                'displayMode' => $kind === 'group'
                    ? ($location === 'primary' ? 'mega' : 'dropdown')
                    : 'link',
                'columnCount' => $location === 'primary' && $kind === 'group' ? 3 : null,
                'menuTemplate' => $location === 'primary' && $kind === 'group' ? 'standard' : null,
                'isHighlight' => false,
                'featuredCard' => [
                    'title' => null,
                    'text' => null,
                    'image' => null,
                    'ctaLabel' => null,
                    'target' => [
                        'pageSlug' => null,
                        'route' => null,
                        'url' => null,
                        'openInNewTab' => false,
                    ],
                ],
            ],
            'children' => [],
        ];
    }

    private function newItemId(string $location): string
    {
        return sprintf(
            '%s-%s',
            strtolower(trim($location) !== '' ? trim($location) : 'item'),
            substr(bin2hex(random_bytes(6)), 0, 10)
        );
    }

    private function normalizeAppendKind(string $location, string $requestedKind): string
    {
        $definition = $this->locationDefinition($location);
        $requestedKind = trim($requestedKind);

        if (in_array($requestedKind, $definition['addKinds'], true)) {
            return $requestedKind;
        }

        return $definition['addKinds'][0] ?? 'route';
    }

    private function normalizePostedKind(string $location, mixed $kind, bool $hasChildren): string
    {
        if (in_array($location, ['sideLeft', 'sideRight'], true)) {
            return 'content_card';
        }

        $normalized = strtolower(trim(is_string($kind) ? $kind : 'route'));
        $allowed = $this->locationDefinition($location)['addKinds'];

        if ($hasChildren) {
            return 'group';
        }

        return in_array($normalized, $allowed, true) ? $normalized : 'route';
    }

    private function normalizeTargetMode(string $location, string $kind, mixed $targetMode, array $item): string
    {
        $requested = strtolower(trim(is_string($targetMode) ? $targetMode : ''));
        $pageSlug = $this->stringOrNull($item['target_page_slug'] ?? null);
        $route = $this->normalizeRoute($item['target_route'] ?? null);
        $url = $this->stringOrNull($item['target_url'] ?? null);

        if ($kind === 'group') {
            return 'none';
        }

        if ($kind === 'content_card') {
            if (in_array($requested, ['none', 'page', 'route', 'external'], true)) {
                return $requested;
            }

            if ($pageSlug !== null) {
                return 'page';
            }

            if ($url !== null) {
                return 'external';
            }

            if ($route !== null) {
                return 'route';
            }

            return 'none';
        }

        if ($pageSlug !== null) {
            return 'page';
        }

        if ($requested === 'external' && $url !== null) {
            return 'external';
        }

        if ($requested === 'route' && $route !== null) {
            return 'route';
        }

        if ($url !== null && $route === null) {
            return 'external';
        }

        if ($route !== null) {
            return 'route';
        }

        if ($url !== null) {
            return 'external';
        }

        if (in_array($requested, ['page', 'route', 'external'], true)) {
            return $requested;
        }

        return $kind;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizePostedPresentation(array $item, string $kind, string $location): array
    {
        $displayMode = strtolower(trim((string) ($item['display_mode'] ?? '')));
        if ($kind !== 'group') {
            $displayMode = 'link';
        } elseif (!in_array($displayMode, ['dropdown', 'mega'], true)) {
            $displayMode = $location === 'primary' ? 'mega' : 'dropdown';
        }

        $featuredTargetMode = strtolower(trim((string) ($item['featured_target_mode'] ?? 'none')));
        if (!in_array($featuredTargetMode, ['none', 'page', 'route', 'external'], true)) {
            $featuredTargetMode = 'none';
        }

        return [
            'displayMode' => $displayMode,
            'columnCount' => $displayMode === 'mega'
                ? max(2, min(4, (int) ($item['column_count'] ?? 3)))
                : null,
            'menuTemplate' => $displayMode === 'mega'
                ? ($this->stringOrNull($item['menu_template'] ?? null) ?? 'standard')
                : null,
            'isHighlight' => !empty($item['is_highlight']),
            'featuredCard' => [
                'title' => $this->stringOrNull($item['featured_title'] ?? null),
                'text' => $this->stringOrNull($item['featured_text'] ?? null),
                'image' => $this->stringOrNull($item['featured_image'] ?? null),
                'ctaLabel' => $this->stringOrNull($item['featured_cta_label'] ?? null),
                'target' => [
                    'pageSlug' => $featuredTargetMode === 'page'
                        ? (
                            $this->resolvePublishedPageSlugAlias(
                                $this->stringOrNull($item['featured_target_page_slug'] ?? null)
                            ) ?? $this->stringOrNull($item['featured_target_page_slug'] ?? null)
                        )
                        : null,
                    'route' => $featuredTargetMode === 'route'
                        ? $this->normalizeRoute($item['featured_target_route'] ?? null)
                        : null,
                    'url' => $featuredTargetMode === 'external'
                        ? $this->stringOrNull($item['featured_target_url'] ?? null)
                        : null,
                    'openInNewTab' => $featuredTargetMode === 'external'
                        ? !empty($item['featured_open_in_new_tab'])
                        : false,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageTitle(array $page): string
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];

        foreach (['fr', 'en', 'de'] as $language) {
            $translation = is_array($translations[$language] ?? null) ? $translations[$language] : [];
            $title = $this->stringOrNull($translation['title'] ?? null);
            if ($title !== null) {
                return $title;
            }
        }

        return (string) ($page['slug'] ?? '');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPublishedPageBySlugAlias(?string $slug): ?array
    {
        $resolvedSlug = $this->resolvePublishedPageSlugAlias($slug);
        if ($resolvedSlug === null) {
            return null;
        }

        $page = $this->pageRepository->findBySlug($resolvedSlug);
        if ($page === null || ($page['status'] ?? PageRepository::STATUS_DRAFT) !== PageRepository::STATUS_PUBLISHED) {
            return null;
        }

        return $page;
    }

    private function resolvePublishedPageSlugAlias(?string $slug): ?string
    {
        $slug = $this->stringOrNull($slug);
        if ($slug === null) {
            return null;
        }

        $exactPage = $this->pageRepository->findBySlug($slug);
        if ($exactPage !== null && ($exactPage['status'] ?? PageRepository::STATUS_DRAFT) === PageRepository::STATUS_PUBLISHED) {
            return $slug;
        }

        $suffixMatches = [];
        foreach ($this->pageRepository->published() as $page) {
            if (!is_array($page)) {
                continue;
            }

            $candidateSlug = $this->stringOrNull($page['slug'] ?? null);
            if ($candidateSlug === null) {
                continue;
            }

            if ($candidateSlug === $slug || str_ends_with($candidateSlug, '-' . $slug)) {
                $suffixMatches[] = $candidateSlug;
            }
        }

        if (count($suffixMatches) !== 1) {
            return null;
        }

        return $suffixMatches[0];
    }

    private function labelToString(mixed $label): ?string
    {
        if (is_array($label)) {
            return $this->labelManager->labelToString(
                $label,
                $this->adminPreferredLanguage(),
                fn (string $key): ?string => $this->translateKey($key)
            );
        }

        return $this->stringOrNull($label);
    }

    private function adminPreferredLanguage(): string
    {
        if (function_exists('admin_interface_language')) {
            return admin_interface_language();
        }

        return strtolower(trim((string) app_config('default_lang', 'fr')));
    }

    private function translateKey(?string $key): ?string
    {
        $key = $this->stringOrNull($key);
        if ($key === null) {
            return null;
        }

        if (function_exists('admin_translate')) {
            $translated = admin_translate($key);
        } elseif (function_exists('t')) {
            $translated = t($key);
        } else {
            return null;
        }

        if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
            return null;
        }

        return $translated;
    }

    /**
     * @param array<string, mixed> $footerNotice
     * @return array{
     *   defaultLanguage: string,
     *   translationKey: string,
     *   translations: array<string, string>
     * }
     */
    private function footerNoticeForView(array $footerNotice): array
    {
        $normalized = $this->normalizeFooterNoticeFromPost($footerNotice, $footerNotice);
        $translations = is_array($normalized['translations'] ?? null) ? $normalized['translations'] : [];
        $translationKey = (string) ($normalized['translationKey'] ?? self::FOOTER_NOTICE_DEFAULT_TRANSLATION_KEY);

        foreach (site_available_languages() as $language) {
            if (!is_string($language)) {
                continue;
            }

            $language = strtolower(trim($language));
            if ($language === '' || isset($translations[$language])) {
                continue;
            }

            $fallback = $this->translationForLanguage($language, $translationKey);
            if ($fallback !== null) {
                $translations[$language] = $fallback;
            }
        }

        $defaultLanguage = (string) ($normalized['defaultLanguage'] ?? self::FOOTER_NOTICE_DEFAULT_LANGUAGE);
        if (!isset($translations[$defaultLanguage])) {
            $translations[$defaultLanguage] = $this->translationForLanguage($defaultLanguage, $translationKey)
                ?? '';
        }

        return [
            'defaultLanguage' => $defaultLanguage,
            'translationKey' => $translationKey,
            'translations' => $translations,
        ];
    }

    private function translationForLanguage(string $language, string $key): ?string
    {
        if (!function_exists('load_translations_cached')) {
            return null;
        }

        $translations = load_translations_cached($language);
        if (!is_array($translations)) {
            return null;
        }

        $value = $translations[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function prettyJson(array $value): string
    {
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($json) ? $json : '{}';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeRoute(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $route = trim($value);

        if ($route === '' || $route === '#') {
            return null;
        }

        if (preg_match('#^https?://#i', $route) === 1) {
            return normalize_public_route($route);
        }

        return normalize_public_route($route);
    }
}
