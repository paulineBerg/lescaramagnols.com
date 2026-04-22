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

        $groupsHtml = '';
        foreach ($placements as $placement) {
            if (!is_array($placement)) {
                continue;
            }

            $groupHtml = $this->renderPlacement($placement, $language);
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
     */
    private function renderPlacement(array $placement, string $language): string
    {
        $group = is_array($placement['group'] ?? null) ? $placement['group'] : [];
        $items = is_array($group['items'] ?? null) ? $group['items'] : [];
        $overrides = is_array($placement['overrides'] ?? null) ? $placement['overrides'] : [];
        $groupName = trim((string) ($group['name'] ?? ''));
        $theme = trim((string) ($group['theme'] ?? TileRepository::DEFAULT_THEME));

        $tilesHtml = '';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemUid = trim((string) ($item['item_uid'] ?? ''));
            $override = is_array($overrides[$itemUid] ?? null) ? $overrides[$itemUid] : [];
            $tileHtml = $this->renderTile($item, $override, $language);
            if ($tileHtml === '') {
                continue;
            }

            $tilesHtml .= $tileHtml;
        }

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
     * @param array<string, mixed> $item
     * @param array<string, mixed> $override
     */
    private function renderTile(array $item, array $override, string $language): string
    {
        if (($override['is_visible'] ?? null) === false) {
            return '';
        }

        $target = $this->resolveTarget($item, $override);
        if ($target === null) {
            return '';
        }

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
     * @param array<string, mixed> $item
     * @param array<string, mixed> $override
     * @return array{href: string, new_tab: bool}|null
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
            ];
        }

        $normalizedUrl = $this->normalizeExternalUrl($url);
        if ($normalizedUrl === '') {
            return null;
        }

        return [
            'href' => $normalizedUrl,
            'new_tab' => $newTab,
        ];
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
