<?php

declare(strict_types=1);

namespace Caramagnols\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

final class LegacyTileMigrationService
{
    /**
     * @var array<string, array{label: string, color: string, image: string}>
     */
    private const AUTO_RETRO_BRANDS = [
        'austin' => [
            'label' => 'Austin',
            'color' => 'vertfonce',
            'image' => '/assets/images/structure/menu/auto-retro/uiaustin.jpg',
        ],
        'citroen' => [
            'label' => 'Citroën',
            'color' => 'bleu',
            'image' => '/assets/images/structure/menu/auto-retro/uicitroen.jpg',
        ],
        'mercedes' => [
            'label' => 'Mercedes-Benz',
            'color' => 'blanc',
            'image' => '/assets/images/structure/menu/auto-retro/uimercedes.jpg',
        ],
        'panhard' => [
            'label' => 'Panhard',
            'color' => 'orange',
            'image' => '/assets/images/structure/menu/auto-retro/uipanhard.jpg',
        ],
        'renault' => [
            'label' => 'Renault',
            'color' => 'bleufonce',
            'image' => '/assets/images/structure/menu/auto-retro/uirenault.jpg',
        ],
        'simca' => [
            'label' => 'Simca',
            'color' => 'rouge',
            'image' => '/assets/images/structure/menu/auto-retro/uisimca.jpg',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const AUTO_RETRO_HISTORY_TARGETS = [
        'austin' => 'auto-retro-austin-histoire-de-austin',
        'citroen' => 'auto-retro-citroen-histoire-de-citroen',
        'mercedes' => 'auto-retro-mercedes-histoire-de-mercedes',
        'panhard' => 'auto-retro-panhard-histoire-de-panhard',
        'renault' => 'auto-retro-renault-histoire-de-renault',
        'simca' => 'auto-retro-simca-histoire-de-simca',
    ];

    /**
     * @var array<string, string>
     */
    private const AUTO_RETRO_MODEL_TARGETS = [
        'austin' => 'auto-retro-austin-aventure-mini-austin',
        'citroen' => 'auto-retro-citroen-histoire-de-la-2cv',
        'mercedes' => 'auto-retro-mercedes-la-slk-une-voiture-compacte-sportive',
        'panhard' => 'auto-retro-panhard-une-dyna-icone-automobile',
        'renault' => 'auto-retro-renault-la-twingo-une-voiture-a-succes',
        'simca' => 'auto-retro-simca-histoire-simca-aronde-icone-francaise',
    ];

    /**
     * @var array<string, string>
     */
    private const AUTO_RETRO_EXEMPLAR_TARGETS = [
        'austin' => 'auto-retro-austin-une-mini-dans-le-golfe-de-sttropez',
        'citroen' => 'auto-retro-citroen-histoire-de-la-2cv',
        'mercedes' => 'auto-retro-mercedes-une-slk-dans-le-golfe-de-sttropez',
        'panhard' => 'auto-retro-panhard-une-dynaz12-dans-le-golfe-de-sttropez',
        'renault' => 'auto-retro-renault-twingo-helios-1999-notre-exemplaire',
        'simca' => 'auto-retro-simca-une-aronde-dans-le-golfe-de-sttropez',
    ];

    /**
     * @var array<string, string>
     */
    private const AUTO_RETRO_HISTORY_PAGES = [
        'austin' => 'auto-retro-austin-histoire-de-austin',
        'citroen' => 'auto-retro-citroen-histoire-de-citroen',
        'mercedes' => 'auto-retro-mercedes-histoire-de-mercedes',
        'panhard' => 'auto-retro-panhard-histoire-de-panhard',
        'renault' => 'auto-retro-renault-histoire-de-renault',
        'simca' => 'auto-retro-simca-histoire-de-simca',
    ];

    /**
     * @var array<string, string>
     */
    private const AUTO_RETRO_MODEL_OVERVIEW_PAGES = [
        'austin' => 'auto-retro-austin-aventure-mini-austin',
        'citroen' => 'auto-retro-citroen-histoire-de-la-2cv',
        'mercedes' => 'auto-retro-mercedes-la-slk-une-voiture-compacte-sportive',
        'panhard' => 'auto-retro-panhard-une-dyna-icone-automobile',
        'renault' => 'auto-retro-renault-la-twingo-une-voiture-a-succes',
        'simca' => 'auto-retro-simca-histoire-simca-aronde-icone-francaise',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const AUTO_RETRO_MODEL_DETAIL_PAGES = [
        'mercedes' => [
            'auto-retro-mercedes-la-slk-r170',
        ],
        'panhard' => [
            'auto-retro-panhard-la-dyna-z-voiture-de-collection',
            'auto-retro-panhard-la-dyna-modele-z12',
        ],
        'simca' => [
            'auto-retro-simca-la-simca-9-aronde-voiture-de-collection',
            'auto-retro-simca-la-simca-aronde-1300-voiture-de-collection',
            'auto-retro-simca-la-simca-p60-voiture-de-collection',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const AUTO_RETRO_EXEMPLAR_PAGES = [
        'austin' => 'auto-retro-austin-une-mini-dans-le-golfe-de-sttropez',
        'mercedes' => 'auto-retro-mercedes-une-slk-dans-le-golfe-de-sttropez',
        'panhard' => 'auto-retro-panhard-une-dynaz12-dans-le-golfe-de-sttropez',
        'renault' => 'auto-retro-renault-twingo-helios-1999-notre-exemplaire',
        'simca' => 'auto-retro-simca-une-aronde-dans-le-golfe-de-sttropez',
    ];

    /**
     * @var array<int, string>
     */
    private const AUTO_RETRO_EXEMPLAR_EXTRA_PAGES = [
        'auto-retro-simca-une-simca-aronde-en-restauration-chez-sava-rioz',
    ];

    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly TileRepository $tileRepository,
        private readonly array $availableLanguages = ['fr', 'en', 'de']
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        return $this->run(false);
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(): array
    {
        return $this->run(true);
    }

    public function stripLegacyTileMarkup(string $html): string
    {
        $fragment = trim($html);
        if ($fragment === '' || !$this->containsLegacyTileMarkup($fragment)) {
            return $fragment;
        }

        $wrapperId = 'legacy-tile-wrapper';
        $previousErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div id="' . $wrapperId . '">' . $fragment . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if ($loaded !== true) {
            return $fragment;
        }

        $wrapper = $dom->getElementById($wrapperId);
        if (!$wrapper instanceof DOMElement) {
            return $fragment;
        }

        $children = [];
        foreach ($wrapper->childNodes as $childNode) {
            $children[] = $childNode;
        }

        foreach ($children as $childNode) {
            if ($childNode instanceof DOMElement && $this->isLegacyTileContainer($childNode)) {
                $wrapper->removeChild($childNode);
            }
        }

        $cleanedHtml = '';
        foreach ($wrapper->childNodes as $childNode) {
            $cleanedHtml .= $dom->saveHTML($childNode);
        }

        return trim($cleanedHtml);
    }

    /**
     * @return array<string, mixed>
     */
    private function run(bool $write): array
    {
        $groupDefinitions = $this->groupDefinitions();
        $pagePlans = $this->pagePlans();

        $errors = [];
        $missingTargetSlugs = $this->missingTargetSlugs($groupDefinitions, $pagePlans);
        if ($missingTargetSlugs !== []) {
            $errors[] = 'Pages cibles manquantes : ' . implode(', ', $missingTargetSlugs);
        }

        $groupIds = [];
        if ($write && $errors === []) {
            try {
                $groupIds = $this->upsertGroups($groupDefinitions);
            } catch (\Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $pageResults = [];
        $migratedPageCount = 0;
        $cleanedRegionCount = 0;
        $placementCount = 0;

        foreach ($pagePlans as $pageSlug => $plan) {
            $page = $this->pageRepository->findBySlug($pageSlug);
            if (!is_array($page)) {
                $errors[] = sprintf('Page introuvable pour la migration: %s', $pageSlug);
                $pageResults[] = [
                    'slug' => $pageSlug,
                    'group' => (string) ($plan['group'] ?? ''),
                    'status' => 'missing',
                    'cleanedRegions' => [],
                    'overrideCount' => $this->overrideCount((array) ($plan['overrides'] ?? [])),
                ];
                continue;
            }

            $cleaned = $this->cleanLegacyTileRegions($page);
            $cleanedRegions = $cleaned['cleanedRegions'];
            $cleanedPage = $cleaned['page'];
            $groupKey = (string) ($plan['group'] ?? '');
            $placement = $this->buildPlacement($groupKey, $plan, $groupIds);

            $status = $write ? 'pending' : 'planned';
            if ($write && $errors === []) {
                if ($placement === null) {
                    $status = 'error';
                    $errors[] = sprintf('Placement introuvable pour la page %s.', $pageSlug);
                } else {
                    $savedPage = true;
                    if ($cleanedRegions !== []) {
                        $savedPage = $this->pageRepository->savePage($cleanedPage, $pageSlug);
                    }

                    $savedPlacements = $savedPage
                        ? $this->tileRepository->replacePlacementsForPage($pageSlug, [$placement], $pageSlug)
                        : false;

                    if (!$savedPage || !$savedPlacements) {
                        $status = 'error';
                        $errors[] = sprintf('Echec de migration pour la page %s.', $pageSlug);
                    } else {
                        $status = 'migrated';
                    }
                }
            } elseif ($write) {
                $status = 'skipped';
            }

            if ($status === 'migrated') {
                $migratedPageCount++;
            }

            $cleanedRegionCount += count($cleanedRegions);
            $placementCount++;
            $pageResults[] = [
                'slug' => $pageSlug,
                'group' => $groupKey,
                'status' => $status,
                'cleanedRegions' => $cleanedRegions,
                'overrideCount' => $this->overrideCount((array) ($plan['overrides'] ?? [])),
            ];
        }

        if ($write && $errors === []) {
            pages_cache_clear();
            app_runtime_cache_clear(['pages', 'tiles']);
        }

        return [
            'mode' => $write ? 'apply' : 'dry-run',
            'groups' => array_map(
                static fn (array $definition): array => [
                    'name' => (string) ($definition['name'] ?? ''),
                    'itemCount' => is_array($definition['items'] ?? null) ? count($definition['items']) : 0,
                ],
                array_values($groupDefinitions)
            ),
            'pages' => $pageResults,
            'counts' => [
                'groups' => count($groupDefinitions),
                'pages' => count($pagePlans),
                'migratedPages' => $migratedPageCount,
                'cleanedRegions' => $cleanedRegionCount,
                'placements' => $placementCount,
                'errors' => count($errors),
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function groupDefinitions(): array
    {
        return [
            'auto-retro-marques-histoires' => [
                'name' => 'Auto-Retro - Marques - Histoires',
                'theme' => TileRepository::DEFAULT_THEME,
                'items' => $this->buildAutoRetroItems(self::AUTO_RETRO_HISTORY_TARGETS),
            ],
            'auto-retro-marques-modeles' => [
                'name' => 'Auto-Retro - Marques - Modeles',
                'theme' => TileRepository::DEFAULT_THEME,
                'items' => $this->buildAutoRetroItems(self::AUTO_RETRO_MODEL_TARGETS),
            ],
            'auto-retro-marques-exemplaires' => [
                'name' => 'Auto-Retro - Marques - Exemplaires',
                'theme' => TileRepository::DEFAULT_THEME,
                'items' => $this->buildAutoRetroItems(self::AUTO_RETRO_EXEMPLAR_TARGETS),
            ],
            'bouger-golfe-entrees' => [
                'name' => 'Bouger - Golfe - Entrees',
                'theme' => TileRepository::DEFAULT_THEME,
                'items' => [
                    $this->pageItem(
                        'villages',
                        10,
                        'orange',
                        '/assets/images/structure/menu/bouger/uivillages.jpg',
                        'bouger-villages',
                        [
                            'fr' => [
                                'label' => 'Les villages',
                                'alt' => 'Accéder aux villages du golfe de Saint-Tropez',
                                'title' => 'Les villages',
                            ],
                            'en' => [
                                'label' => 'The villages',
                                'alt' => 'Open the villages of the Gulf of Saint-Tropez',
                                'title' => 'The villages',
                            ],
                            'de' => [
                                'label' => 'Die Dörfer',
                                'alt' => 'Zu den Dörfern des Golfs von Saint-Tropez',
                                'title' => 'Die Dörfer',
                            ],
                        ],
                        'rectangle'
                    ),
                    $this->pageItem(
                        'animations',
                        20,
                        'bleuvert',
                        '/assets/images/structure/menu/bouger/uianimations.jpg',
                        'bouger-animations-dans-le-golfe-de-sttropez',
                        [
                            'fr' => [
                                'label' => 'Les animations',
                                'alt' => 'Accéder aux animations du golfe de Saint-Tropez',
                                'title' => 'Les animations',
                            ],
                            'en' => [
                                'label' => 'Events',
                                'alt' => 'Open events in the Gulf of Saint-Tropez',
                                'title' => 'Events',
                            ],
                            'de' => [
                                'label' => 'Veranstaltungen',
                                'alt' => 'Zu den Veranstaltungen im Golf von Saint-Tropez',
                                'title' => 'Veranstaltungen',
                            ],
                        ],
                        'rectangle'
                    ),
                ],
            ],
            'bouger-villages' => [
                'name' => 'Bouger - Villages',
                'theme' => TileRepository::DEFAULT_THEME,
                'items' => [
                    $this->pageItem(
                        'cogolin',
                        10,
                        'gris',
                        '/assets/images/structure/menu/bouger/uicogolin.jpg',
                        'bouger-villages-cogolin',
                        [
                            'fr' => [
                                'label' => 'Cogolin',
                                'alt' => 'Visitez le village de Cogolin',
                                'title' => 'Cogolin',
                            ],
                            'en' => [
                                'label' => 'Cogolin',
                                'alt' => 'Visit the village of Cogolin',
                                'title' => 'Cogolin',
                            ],
                            'de' => [
                                'label' => 'Cogolin',
                                'alt' => 'Besuchen Sie das Dorf Cogolin',
                                'title' => 'Cogolin',
                            ],
                        ],
                        'rectangle'
                    ),
                    $this->pageItem(
                        'garde-freinet',
                        20,
                        'blanc',
                        '/assets/images/structure/menu/bouger/uigardefreinet.jpg',
                        'bouger-villages-la-garde-freinet',
                        [
                            'fr' => [
                                'label' => 'La Garde-Freinet',
                                'alt' => 'Visitez le village de La Garde-Freinet',
                                'title' => 'La Garde-Freinet',
                            ],
                            'en' => [
                                'label' => 'La Garde-Freinet',
                                'alt' => 'Visit the village of La Garde-Freinet',
                                'title' => 'La Garde-Freinet',
                            ],
                            'de' => [
                                'label' => 'La Garde-Freinet',
                                'alt' => 'Besuchen Sie das Dorf La Garde-Freinet',
                                'title' => 'La Garde-Freinet',
                            ],
                        ],
                        'rectangle'
                    ),
                    $this->pageItem(
                        'ramatuelle',
                        30,
                        'bleufonce',
                        '/assets/images/structure/menu/bouger/uiramatuelle.jpg',
                        'bouger-villages-ramatuelle',
                        [
                            'fr' => [
                                'label' => 'Ramatuelle',
                                'alt' => 'Visitez le village de Ramatuelle',
                                'title' => 'Ramatuelle',
                            ],
                            'en' => [
                                'label' => 'Ramatuelle',
                                'alt' => 'Visit the village of Ramatuelle',
                                'title' => 'Ramatuelle',
                            ],
                            'de' => [
                                'label' => 'Ramatuelle',
                                'alt' => 'Besuchen Sie das Dorf Ramatuelle',
                                'title' => 'Ramatuelle',
                            ],
                        ],
                        'rectangle'
                    ),
                    $this->pageItem(
                        'sttropez',
                        40,
                        'noir',
                        '/assets/images/structure/menu/bouger/uisttropez.jpg',
                        'bouger-villages-sttropez',
                        [
                            'fr' => [
                                'label' => 'St-Tropez',
                                'alt' => 'Visitez le village de Saint-Tropez',
                                'title' => 'St-Tropez',
                            ],
                            'en' => [
                                'label' => 'St-Tropez',
                                'alt' => 'Visit the village of Saint-Tropez',
                                'title' => 'St-Tropez',
                            ],
                            'de' => [
                                'label' => 'St-Tropez',
                                'alt' => 'Besuchen Sie das Dorf Saint-Tropez',
                                'title' => 'St-Tropez',
                            ],
                        ],
                        'rectangle'
                    ),
                ],
            ],
            'sava-reseaux' => [
                'name' => 'SAVA - Reseaux',
                'theme' => TileRepository::DEFAULT_THEME,
                'items' => [
                    $this->externalItem(
                        'instagram',
                        10,
                        'orange',
                        '/assets/images/structure/menu/sava/uihistoiresdevieilles.jpg',
                        'https://www.instagram.com/garage_sava/',
                        [
                            'fr' => [
                                'label' => 'Voir sur Instagram',
                                'alt' => 'Voir sur Instagram',
                                'title' => 'Voir sur Instagram',
                            ],
                            'en' => [
                                'label' => 'View on Instagram',
                                'alt' => 'View on Instagram',
                                'title' => 'View on Instagram',
                            ],
                            'de' => [
                                'label' => 'Auf Instagram ansehen',
                                'alt' => 'Auf Instagram ansehen',
                                'title' => 'Auf Instagram ansehen',
                            ],
                        ],
                        true,
                        'rectangle'
                    ),
                    $this->externalItem(
                        'facebook',
                        20,
                        'bleufonce',
                        '/assets/images/structure/menu/sava/uihistoiresdevieilles.jpg',
                        'https://www.facebook.com/histoiresdevieilles',
                        [
                            'fr' => [
                                'label' => 'Voir sur Facebook',
                                'alt' => 'Voir sur Facebook',
                                'title' => 'Voir sur Facebook',
                            ],
                            'en' => [
                                'label' => 'View on Facebook',
                                'alt' => 'View on Facebook',
                                'title' => 'View on Facebook',
                            ],
                            'de' => [
                                'label' => 'Auf Facebook ansehen',
                                'alt' => 'Auf Facebook ansehen',
                                'title' => 'Auf Facebook ansehen',
                            ],
                        ],
                        true,
                        'rectangle'
                    ),
                    $this->externalItem(
                        'site-sava',
                        30,
                        'gris',
                        '/assets/images/structure/menu/sava/uisava.jpg',
                        'https://www.sarl-sava.com',
                        [
                            'fr' => [
                                'label' => 'Site de SAVA',
                                'alt' => 'Site de SAVA',
                                'title' => 'Site de SAVA',
                            ],
                            'en' => [
                                'label' => 'SAVA Website',
                                'alt' => 'SAVA Website',
                                'title' => 'SAVA Website',
                            ],
                            'de' => [
                                'label' => 'SAVA-Website',
                                'alt' => 'SAVA-Website',
                                'title' => 'SAVA-Website',
                            ],
                        ],
                        true,
                        'rectangle'
                    ),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function pagePlans(): array
    {
        $plans = [];

        foreach (self::AUTO_RETRO_HISTORY_PAGES as $brand => $pageSlug) {
            $plans[$pageSlug] = [
                'group' => 'auto-retro-marques-histoires',
                'sort_order' => 10,
                'overrides' => [
                    $brand => $this->targetOverride(self::AUTO_RETRO_MODEL_TARGETS[$brand]),
                ],
            ];
        }

        foreach (self::AUTO_RETRO_MODEL_OVERVIEW_PAGES as $brand => $pageSlug) {
            $targetSlug = self::AUTO_RETRO_EXEMPLAR_TARGETS[$brand] ?? self::AUTO_RETRO_HISTORY_TARGETS[$brand];
            if ($targetSlug === $pageSlug) {
                $targetSlug = self::AUTO_RETRO_HISTORY_TARGETS[$brand];
            }

            $plans[$pageSlug] = [
                'group' => 'auto-retro-marques-modeles',
                'sort_order' => 10,
                'overrides' => [
                    $brand => $this->targetOverride($targetSlug),
                ],
            ];
        }

        foreach (self::AUTO_RETRO_MODEL_DETAIL_PAGES as $brand => $pageSlugs) {
            foreach ($pageSlugs as $pageSlug) {
                $plans[$pageSlug] = [
                    'group' => 'auto-retro-marques-modeles',
                    'sort_order' => 10,
                    'overrides' => [],
                ];
            }
        }

        foreach (self::AUTO_RETRO_EXEMPLAR_PAGES as $brand => $pageSlug) {
            $plans[$pageSlug] = [
                'group' => 'auto-retro-marques-exemplaires',
                'sort_order' => 10,
                'overrides' => [
                    $brand => $this->targetOverride(self::AUTO_RETRO_HISTORY_TARGETS[$brand]),
                ],
            ];
        }

        foreach (self::AUTO_RETRO_EXEMPLAR_EXTRA_PAGES as $pageSlug) {
            $plans[$pageSlug] = [
                'group' => 'auto-retro-marques-exemplaires',
                'sort_order' => 10,
                'overrides' => [],
            ];
        }

        $plans['bouger-se-promener-dans-le-golfe-de-sttropez'] = [
            'group' => 'bouger-golfe-entrees',
            'sort_order' => 10,
            'overrides' => [],
        ];
        $plans['bouger-villages'] = [
            'group' => 'bouger-villages',
            'sort_order' => 10,
            'overrides' => [],
        ];
        $plans['bouger-villages-cogolin'] = [
            'group' => 'bouger-villages',
            'sort_order' => 10,
            'overrides' => [
                'cogolin' => $this->hiddenOverride(),
            ],
        ];
        $plans['bouger-villages-la-garde-freinet'] = [
            'group' => 'bouger-villages',
            'sort_order' => 10,
            'overrides' => [
                'garde-freinet' => $this->hiddenOverride(),
            ],
        ];
        $plans['bouger-villages-ramatuelle'] = [
            'group' => 'bouger-villages',
            'sort_order' => 10,
            'overrides' => [
                'ramatuelle' => $this->hiddenOverride(),
            ],
        ];
        $plans['bouger-villages-sttropez'] = [
            'group' => 'bouger-villages',
            'sort_order' => 10,
            'overrides' => [
                'sttropez' => $this->hiddenOverride(),
            ],
        ];
        $plans['sava-sava-auto-retro-rioz'] = [
            'group' => 'sava-reseaux',
            'sort_order' => 10,
            'overrides' => [],
        ];

        ksort($plans, SORT_NATURAL | SORT_FLAG_CASE);

        return $plans;
    }

    /**
     * @param array<string, array<string, mixed>> $groupDefinitions
     * @param array<string, array<string, mixed>> $pagePlans
     * @return array<int, string>
     */
    private function missingTargetSlugs(array $groupDefinitions, array $pagePlans): array
    {
        $requiredSlugs = [];

        foreach ($groupDefinitions as $definition) {
            $items = is_array($definition['items'] ?? null) ? $definition['items'] : [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (($item['target_type'] ?? '') !== 'page') {
                    continue;
                }

                $targetPageSlug = trim((string) ($item['target_page_slug'] ?? ''));
                if ($targetPageSlug !== '') {
                    $requiredSlugs[$targetPageSlug] = true;
                }
            }
        }

        foreach ($pagePlans as $pageSlug => $plan) {
            $requiredSlugs[$pageSlug] = true;
            $overrides = is_array($plan['overrides'] ?? null) ? $plan['overrides'] : [];
            foreach ($overrides as $override) {
                if (!is_array($override)) {
                    continue;
                }

                $targetPageSlug = trim((string) ($override['target_page_slug'] ?? ''));
                if ($targetPageSlug !== '') {
                    $requiredSlugs[$targetPageSlug] = true;
                }
            }
        }

        $missing = [];
        foreach (array_keys($requiredSlugs) as $pageSlug) {
            if (!is_array($this->pageRepository->findBySlug($pageSlug))) {
                $missing[] = $pageSlug;
            }
        }

        sort($missing, SORT_NATURAL | SORT_FLAG_CASE);

        return $missing;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @return array<string, int>
     */
    private function upsertGroups(array $definitions): array
    {
        $existingByName = [];
        foreach ($this->tileRepository->listGroupSummaries() as $summary) {
            if (!is_array($summary)) {
                continue;
            }

            $name = trim((string) ($summary['name'] ?? ''));
            $groupId = (int) ($summary['id'] ?? 0);
            if ($name === '' || $groupId <= 0) {
                continue;
            }

            $existingByName[$this->normalizeLookupKey($name)] = $groupId;
        }

        $groupIds = [];
        foreach ($definitions as $key => $definition) {
            $name = trim((string) ($definition['name'] ?? ''));
            $payload = $definition;
            $lookupKey = $this->normalizeLookupKey($name);
            if (isset($existingByName[$lookupKey])) {
                $payload['id'] = $existingByName[$lookupKey];
            }

            $savedGroupId = $this->tileRepository->saveGroup($payload);
            if (!is_int($savedGroupId) || $savedGroupId <= 0) {
                throw new \RuntimeException(sprintf('Impossible de sauvegarder le groupe de tuiles "%s".', $name));
            }

            $groupIds[$key] = $savedGroupId;
        }

        return $groupIds;
    }

    /**
     * @param array<string, mixed> $plan
     * @param array<string, int> $groupIds
     * @return array<string, mixed>|null
     */
    private function buildPlacement(string $groupKey, array $plan, array $groupIds): ?array
    {
        $groupId = $groupIds[$groupKey] ?? 0;
        if ($groupId <= 0) {
            return null;
        }

        $overrides = [];
        foreach ((array) ($plan['overrides'] ?? []) as $itemUid => $override) {
            if (!is_string($itemUid) || !is_array($override)) {
                continue;
            }

            $overrides[$itemUid] = $override;
        }

        return [
            'group_id' => $groupId,
            'sort_order' => max(0, (int) ($plan['sort_order'] ?? 10)),
            'overrides' => $overrides,
        ];
    }

    /**
     * @param array<string, mixed> $page
     * @return array{page: array<string, mixed>, cleanedRegions: array<int, string>}
     */
    private function cleanLegacyTileRegions(array $page): array
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $cleanedRegions = [];

        foreach ($translations as $locale => $translation) {
            if (!is_string($locale) || !is_array($translation)) {
                continue;
            }

            $regions = is_array($translation['regions'] ?? null) ? $translation['regions'] : [];
            foreach (['after_body', 'postscript'] as $regionKey) {
                $payload = is_array($regions[$regionKey] ?? null) ? $regions[$regionKey] : null;
                if (!is_array($payload)) {
                    continue;
                }

                $html = trim((string) ($payload['html'] ?? ''));
                if ($html === '' || !$this->containsLegacyTileMarkup($html)) {
                    continue;
                }

                $cleanedHtml = $this->stripLegacyTileMarkup($html);
                if ($cleanedHtml === $html) {
                    continue;
                }

                if ($cleanedHtml === '') {
                    unset($regions[$regionKey]);
                } else {
                    $payload['html'] = $cleanedHtml;
                    $regions[$regionKey] = $payload;
                }

                $translation['regions'] = $regions;
                $translations[$locale] = $translation;
                $cleanedRegions[] = $locale . ':' . $regionKey;
            }
        }

        $page['translations'] = $translations;

        return [
            'page' => $page,
            'cleanedRegions' => $cleanedRegions,
        ];
    }

    private function containsLegacyTileMarkup(string $html): bool
    {
        return preg_match('/id="(?:boutonrectangle|boutongrand|menuwindows|menurectanglewindows|bloccenter)/i', $html) === 1;
    }

    private function isLegacyTileContainer(DOMElement $element): bool
    {
        if (!$this->nodeContainsLegacyTileMarkup($element)) {
            return false;
        }

        $id = strtolower(trim($element->getAttribute('id')));
        if ($id === 'menuwindows') {
            return true;
        }

        if ($id === 'bloccenter' || $id === 'menurectanglewindows') {
            return $this->subtreeContainsOnlyLegacyMenuMarkup($element);
        }

        return $id === '' && $this->subtreeContainsOnlyLegacyMenuMarkup($element);
    }

    private function nodeContainsLegacyTileMarkup(DOMNode $node): bool
    {
        if ($node instanceof DOMElement) {
            $id = strtolower(trim($node->getAttribute('id')));
            if (
                $id === 'menuwindows'
                || $id === 'menurectanglewindows'
                || $id === 'bloccenter'
                || str_starts_with($id, 'boutonrectangle')
                || str_starts_with($id, 'boutongrand')
            ) {
                return true;
            }
        }

        foreach ($node->childNodes as $childNode) {
            if ($this->nodeContainsLegacyTileMarkup($childNode)) {
                return true;
            }
        }

        return false;
    }

    private function subtreeContainsOnlyLegacyMenuMarkup(DOMNode $node): bool
    {
        foreach ($node->childNodes as $childNode) {
            if ($childNode instanceof DOMElement) {
                $tagName = strtolower($childNode->tagName);
                if (!in_array($tagName, ['div', 'a', 'img'], true)) {
                    return false;
                }

                if (!$this->subtreeContainsOnlyLegacyMenuMarkup($childNode)) {
                    return false;
                }

                continue;
            }

            if ($childNode->nodeType === XML_TEXT_NODE) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<string, string> $targets
     * @return array<int, array<string, mixed>>
     */
    private function buildAutoRetroItems(array $targets): array
    {
        $items = [];
        $sortOrder = 10;
        $tileSizes = array_fill(0, count(self::AUTO_RETRO_BRANDS), TileRepository::DEFAULT_SIZE);
        $brandIndex = 0;

        foreach (self::AUTO_RETRO_BRANDS as $brandKey => $brand) {
            $items[] = $this->pageItem(
                $brandKey,
                $sortOrder,
                (string) ($brand['color'] ?? 'bleu'),
                (string) ($brand['image'] ?? ''),
                (string) ($targets[$brandKey] ?? ''),
                $this->sameLabelTranslations((string) ($brand['label'] ?? $brandKey)),
                $tileSizes[$brandIndex] ?? TileRepository::DEFAULT_SIZE
            );

            $sortOrder += 10;
            $brandIndex++;
        }

        return $items;
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @return array<string, mixed>
     */
    private function pageItem(
        string $itemUid,
        int $sortOrder,
        string $colorToken,
        string $imageSrc,
        string $targetPageSlug,
        array $translations,
        string $tileSize = TileRepository::DEFAULT_SIZE
    ): array {
        $imageMeta = $this->imageMeta($imageSrc);

        return [
            'item_uid' => $itemUid,
            'sort_order' => $sortOrder,
            'tile_size' => TileRepository::normalizeTileSizeValue($tileSize),
            'color_token' => $colorToken,
            'image_src' => $imageMeta['src'],
            'image_width' => $imageMeta['width'],
            'image_height' => $imageMeta['height'],
            'target_type' => 'page',
            'target_page_slug' => $targetPageSlug,
            'target_route' => '',
            'target_url' => '',
            'open_in_new_tab' => false,
            'translations' => $translations,
        ];
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @return array<string, mixed>
     */
    private function externalItem(
        string $itemUid,
        int $sortOrder,
        string $colorToken,
        string $imageSrc,
        string $targetUrl,
        array $translations,
        bool $openInNewTab,
        string $tileSize = TileRepository::DEFAULT_SIZE
    ): array {
        $imageMeta = $this->imageMeta($imageSrc);

        return [
            'item_uid' => $itemUid,
            'sort_order' => $sortOrder,
            'tile_size' => TileRepository::normalizeTileSizeValue($tileSize),
            'color_token' => $colorToken,
            'image_src' => $imageMeta['src'],
            'image_width' => $imageMeta['width'],
            'image_height' => $imageMeta['height'],
            'target_type' => 'external',
            'target_page_slug' => '',
            'target_route' => '',
            'target_url' => $targetUrl,
            'open_in_new_tab' => $openInNewTab,
            'translations' => $translations,
        ];
    }

    /**
     * @return array{src: string, width: int|null, height: int|null}
     */
    private function imageMeta(string $publicPath): array
    {
        $path = trim($publicPath);
        $meta = [
            'src' => $path,
            'width' => null,
            'height' => null,
        ];

        if ($path === '') {
            return $meta;
        }

        $relativePath = null;
        if (str_starts_with($path, '/assets/')) {
            $relativePath = '/public' . $path;
        } elseif (str_starts_with($path, '/uploads/')) {
            $relativePath = '/public' . $path;
        }

        if ($relativePath === null) {
            return $meta;
        }

        $absolutePath = ROOT_PATH . $relativePath;
        if (!is_file($absolutePath)) {
            return $meta;
        }

        $size = @getimagesize($absolutePath);
        if (!is_array($size)) {
            return $meta;
        }

        $meta['width'] = isset($size[0]) ? max(1, (int) $size[0]) : null;
        $meta['height'] = isset($size[1]) ? max(1, (int) $size[1]) : null;

        return $meta;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function sameLabelTranslations(string $label): array
    {
        $translations = [];

        foreach ($this->availableLanguages as $language) {
            if (!is_string($language) || trim($language) === '') {
                continue;
            }

            $translations[$language] = [
                'label' => $label,
                'alt' => $label,
                'title' => $label,
            ];
        }

        return $translations;
    }

    /**
     * @return array<string, mixed>
     */
    private function targetOverride(string $targetPageSlug): array
    {
        return [
            'visibility_mode' => 'default',
            'target_mode' => 'page',
            'target_page_slug' => $targetPageSlug,
            'target_route' => '',
            'target_url' => '',
            'translations' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function hiddenOverride(): array
    {
        return [
            'visibility_mode' => 'hidden',
            'target_mode' => 'default',
            'target_page_slug' => '',
            'target_route' => '',
            'target_url' => '',
            'translations' => [],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function overrideCount(array $overrides): int
    {
        return count(array_filter($overrides, static fn (mixed $override): bool => is_array($override)));
    }

    private function normalizeLookupKey(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower(trim($value), 'UTF-8')
            : strtolower(trim($value));
    }
}
