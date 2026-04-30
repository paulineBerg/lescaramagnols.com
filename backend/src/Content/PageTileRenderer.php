<?php

declare(strict_types=1);

namespace Caramagnols\Content;

final class PageTileRenderer
{
    public function __construct(
        private readonly TileRepository $tileRepository,
        private readonly PageRepository $pageRepository,
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    public function renderAfterBody(string $pageSlug, string $language): string
    {
        $pageSlug = trim($pageSlug);
        $language = trim($language) !== '' ? trim($language) : $this->defaultLanguage;

        if ($pageSlug === '') {
            return '';
        }

        $placements = $this->tileRepository->renderablePlacements($pageSlug, TileRepository::DEFAULT_REGION);
        if ($placements === []) {
            return '';
        }

        $currentPage = $this->pageRepository->findBySlug($pageSlug);
        $currentPageRoute = is_array($currentPage)
            ? (normalize_public_route((string) ($currentPage['route'] ?? '')) ?? '')
            : '';
        $groupsHtml = '';
        /** @var array<string, bool> $renderedTargetKeys */
        $renderedTargetKeys = [];
        foreach ($placements as $placement) {
            if (!is_array($placement)) {
                continue;
            }

            $groupHtml = $this->renderPlacement($placement, $language, $pageSlug, $currentPageRoute, $renderedTargetKeys);
            if ($groupHtml === '') {
                continue;
            }

            $groupsHtml .= $groupHtml;
        }

        if ($groupsHtml === '') {
            return '';
        }

        return '<section class="page-tile-groups" aria-label="Liens associés">'
            . $groupsHtml
            . '</section>';
    }

    /**
     * @param array<string, mixed> $placement
     * @param array<string, bool> $renderedTargetKeys
     */
    private function renderPlacement(
        array $placement,
        string $language,
        string $currentPageSlug,
        string $currentPageRoute,
        array &$renderedTargetKeys
    ): string {
        $group = is_array($placement['group'] ?? null) ? $placement['group'] : [];
        $items = is_array($group['items'] ?? null) ? $group['items'] : [];
        $overrides = is_array($placement['overrides'] ?? null) ? $placement['overrides'] : [];
        $groupName = trim((string) ($group['name'] ?? ''));
        $theme = trim((string) ($group['theme'] ?? TileRepository::DEFAULT_THEME));

        $tiles = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemUid = trim((string) ($item['item_uid'] ?? ''));
            $override = is_array($overrides[$itemUid] ?? null) ? $overrides[$itemUid] : [];
            $tileHtml = $this->renderTile(
                $item,
                $override,
                $language,
                $currentPageSlug,
                $currentPageRoute,
                $renderedTargetKeys
            );
            if ($tileHtml === '') {
                continue;
            }

            $tiles[] = [
                'html' => $tileHtml,
                'size' => TileRepository::normalizeTileSizeValue(
                    (string) ($item['tile_size'] ?? TileRepository::DEFAULT_SIZE)
                ),
            ];
        }

        $tilesHtml = $this->renderTileGridHtml($tiles);
        if ($tilesHtml === '') {
            return '';
        }

        $groupAttributes = [
            'class="page-tile-group page-tile-group--' . $this->escapeAttribute($theme) . '"',
        ];

        if ($groupName !== '') {
            $groupAttributes[] = 'aria-label="' . $this->escapeAttribute($groupName) . '"';
        }

        return '<div ' . implode(' ', $groupAttributes) . '>'
            . '<div class="page-tile-group__grid">'
            . $tilesHtml
            . '</div></div>';
    }

    /**
     * @param array<int, array{html: string, size: string}> $tiles
     */
    private function renderTileGridHtml(array $tiles): string
    {
        $html = '';
        $smallTilesHtml = '';

        foreach ($tiles as $tile) {
            if ($tile['size'] === 'small') {
                $smallTilesHtml .= $tile['html'];
                continue;
            }

            $html .= $this->renderSmallTileCluster($smallTilesHtml);
            $smallTilesHtml = '';
            $html .= $tile['html'];
        }

        return $html . $this->renderSmallTileCluster($smallTilesHtml);
    }

    private function renderSmallTileCluster(string $tilesHtml): string
    {
        if ($tilesHtml === '') {
            return '';
        }

        $fallbackStyle = 'display:grid;'
            . 'grid-column:span 2;'
            . 'grid-row:span 2;'
            . 'grid-template-columns:repeat(2,minmax(0,1fr));'
            . 'grid-template-rows:repeat(2,minmax(0,1fr));'
            . 'gap:var(--page-tile-gap,.55rem);'
            . 'height:100%;'
            . 'min-width:0;'
            . 'min-height:0;';

        return '<div class="page-tile-small-cluster" style="' . $fallbackStyle . '">'
            . $tilesHtml
            . '</div>';
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $override
     * @param array<string, bool> $renderedTargetKeys
     */
    private function renderTile(
        array $item,
        array $override,
        string $language,
        string $currentPageSlug,
        string $currentPageRoute,
        array &$renderedTargetKeys
    ): string {
        if (array_key_exists('is_visible', $item) && empty($item['is_visible'])) {
            return '';
        }

        if (($override['is_visible'] ?? null) === false) {
            return '';
        }

        $target = $this->resolveTarget($item, $override);
        if ($target === null || $this->isCurrentPageTarget($target, $currentPageSlug, $currentPageRoute)) {
            return '';
        }

        $targetKey = $this->targetDeduplicationKey($target);
        if (isset($renderedTargetKeys[$targetKey])) {
            return '';
        }

        $renderedTargetKeys[$targetKey] = true;

        $label = $this->resolveTranslatedText(
            is_array($item['translations'] ?? null) ? $item['translations'] : [],
            is_array($override['labels'] ?? null) ? $override['labels'] : [],
            $language,
            'label'
        );
        if ($label === '') {
            $label = 'Tuile';
        }

        $alt = $this->resolveTranslatedText(
            is_array($item['translations'] ?? null) ? $item['translations'] : [],
            is_array($override['alts'] ?? null) ? $override['alts'] : [],
            $language,
            'alt'
        );
        if ($alt === '') {
            $alt = $label;
        }

        $title = $this->resolveTranslatedText(
            is_array($item['translations'] ?? null) ? $item['translations'] : [],
            is_array($override['titles'] ?? null) ? $override['titles'] : [],
            $language,
            'title'
        );

        $imageSrc = trim((string) ($item['image_src'] ?? ''));
        $tileSize = TileRepository::normalizeTileSizeValue((string) ($item['tile_size'] ?? TileRepository::DEFAULT_SIZE));
        $colorToken = TileRepository::buttonColorToken($tileSize, (string) ($item['color_token'] ?? 'bleu'));
        $imageWidth = is_numeric($item['image_width'] ?? null) ? max(1, (int) $item['image_width']) : null;
        $imageHeight = is_numeric($item['image_height'] ?? null) ? max(1, (int) $item['image_height']) : null;
        $newTab = $target['new_tab'];
        $summary = $title !== '' && strcasecmp($title, $label) !== 0 ? $title : '';
        $buttonStyle = sprintf(
            '--page-tile-bg-default:url(\'%s\');--page-tile-bg-hover:url(\'%s\');--page-tile-bg-active:url(\'%s\');',
            $this->escapeCssUrl(\getTileButtonImage($tileSize, $colorToken, 'default')),
            $this->escapeCssUrl(\getTileButtonImage($tileSize, $colorToken, 'hover')),
            $this->escapeCssUrl(\getTileButtonImage($tileSize, $colorToken, 'active'))
        );

        $attributes = [
            'class="' . $this->escapeAttribute(
                'page-tile page-tile--size-' . $tileSize . ' page-tile--color-' . $colorToken . ($imageSrc !== '' ? ' page-tile--with-media' : '')
            ) . '"',
            'href="' . $this->escapeAttribute($target['href']) . '"',
            'style="' . $buttonStyle . '"',
        ];

        if ($title !== '') {
            $attributes[] = 'title="' . $this->escapeAttribute($title) . '"';
        }

        if ($newTab) {
            $attributes[] = 'target="_blank"';
            $attributes[] = 'rel="noopener noreferrer"';
        }

        $imageHtml = '';
        if ($imageSrc !== '') {
            $widthHtml = $imageWidth !== null ? ' width="' . $imageWidth . '"' : '';
            $heightHtml = $imageHeight !== null ? ' height="' . $imageHeight . '"' : '';
            $imageHtml = '<figure class="page-tile__figure"><img class="page-tile__image" src="'
                . $this->escapeAttribute($imageSrc)
                . '" alt="'
                . $this->escapeAttribute($alt)
                . '"'
                . $widthHtml
                . $heightHtml
                . ' loading="lazy" decoding="async" fetchpriority="low" /></figure>';
        }

        return '<a ' . implode(' ', $attributes) . '>'
            . '<div class="page-tile__inner">'
            . $imageHtml
            . '<div class="page-tile__overlay"></div>'
            . '<div class="page-tile__content">'
            . '<span class="page-tile__label">' . $this->escapeTileLabel($label) . '</span>'
            . ($summary !== '' ? '<span class="page-tile__summary">' . $this->escape($summary) . '</span>' : '')
            . '</div>'
            . '</div></a>';
    }

    /**
     * @param array{href: string, new_tab: bool, page_slug: string, normalized_path: ?string} $target
     */
    private function targetDeduplicationKey(array $target): string
    {
        $normalizedPath = trim((string) ($target['normalized_path'] ?? ''));
        if ($normalizedPath !== '') {
            return 'path:' . $normalizedPath;
        }

        return 'href:' . trim((string) $target['href']);
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $override
     * @return array{href: string, new_tab: bool, page_slug: string, normalized_path: ?string}|null
     */
    private function resolveTarget(array $item, array $override): ?array
    {
        $target = is_array($item['target'] ?? null) ? $item['target'] : [];
        $targetType = trim((string) ($override['target_type'] ?? $target['type'] ?? 'page'));
        $pageSlug = trim((string) ($override['target_page_slug'] ?? $target['pageSlug'] ?? ''));
        $route = trim((string) ($override['target_route'] ?? $target['route'] ?? ''));
        $url = trim((string) ($override['target_url'] ?? $target['url'] ?? ''));
        $newTab = array_key_exists('open_in_new_tab', $override)
            ? (bool) $override['open_in_new_tab']
            : !empty($item['open_in_new_tab']);

        if ($targetType === 'page') {
            $page = $pageSlug !== '' ? $this->pageRepository->findBySlug($pageSlug) : null;
            if (!is_array($page) || (string) ($page['status'] ?? PageRepository::STATUS_DRAFT) !== PageRepository::STATUS_PUBLISHED) {
                return null;
            }

            $pageRoute = normalize_public_route((string) ($page['route'] ?? ''));
            if (!is_string($pageRoute) || $pageRoute === '') {
                return null;
            }

            return [
                'href' => $pageRoute,
                'new_tab' => $newTab,
                'page_slug' => trim((string) ($page['slug'] ?? $pageSlug)),
                'normalized_path' => $pageRoute,
            ];
        }

        if ($targetType === 'route') {
            $normalizedRoute = normalize_public_route($route);
            if (!is_string($normalizedRoute) || $normalizedRoute === '') {
                return null;
            }

            return [
                'href' => $normalizedRoute,
                'new_tab' => $newTab,
                'page_slug' => '',
                'normalized_path' => $normalizedRoute,
            ];
        }

        $normalizedUrl = $this->normalizeExternalUrl($url);
        if ($normalizedUrl === '') {
            return null;
        }

        return [
            'href' => $normalizedUrl,
            'new_tab' => $newTab,
            'page_slug' => '',
            'normalized_path' => str_starts_with($normalizedUrl, '/')
                ? (normalize_public_route($normalizedUrl) ?? null)
                : null,
        ];
    }

    /**
     * @param array{href: string, new_tab: bool, page_slug: string, normalized_path: ?string} $target
     */
    private function isCurrentPageTarget(array $target, string $currentPageSlug, string $currentPageRoute): bool
    {
        if ($currentPageSlug !== '' && trim((string) ($target['page_slug'] ?? '')) === $currentPageSlug) {
            return true;
        }

        $targetPath = trim((string) ($target['normalized_path'] ?? ''));

        return $currentPageRoute !== '' && $targetPath !== '' && $targetPath === $currentPageRoute;
    }

    /**
     * @param array<string, array<string, string>> $translations
     * @param array<string, string> $overrideTranslations
     */
    private function resolveTranslatedText(
        array $translations,
        array $overrideTranslations,
        string $language,
        string $field
    ): string {
        $candidates = array_values(array_unique(array_filter([
            $language,
            $this->defaultLanguage,
            'fr',
            'en',
            'de',
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        foreach ($candidates as $candidateLanguage) {
            $overrideValue = trim((string) ($overrideTranslations[$candidateLanguage] ?? ''));
            if ($overrideValue !== '') {
                return $overrideValue;
            }
        }

        foreach ($candidates as $candidateLanguage) {
            $translation = is_array($translations[$candidateLanguage] ?? null) ? $translations[$candidateLanguage] : [];
            $value = trim((string) ($translation[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($overrideTranslations as $overrideValue) {
            $value = trim((string) $overrideValue);
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

        return '';
    }

    private function normalizeExternalUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^(?:https?:)?//#i', $url) === 1 || preg_match('#^(mailto|tel):#i', $url) === 1) {
            return $url;
        }

        $normalizedRoute = normalize_public_route($url);

        return is_string($normalizedRoute) ? $normalizedRoute : '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function escapeTileLabel(string $value): string
    {
        return str_replace('-', '&#8209;', $this->escape($value));
    }

    private function escapeAttribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function escapeCssUrl(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }
}
