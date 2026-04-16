<?php

/**
 * @param array<string, mixed> $item
 */
function renderNavigationAnchorOrLabel(array $item, string $linkClass, string $labelClass): void
{
    $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $href = is_string($item['href'] ?? null) ? (string) $item['href'] : null;
    $title = htmlspecialchars((string) (($item['title'] ?? $item['label'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8');
    $target = !empty($item['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';

    if ($href !== null && $href !== '') {
        ?>
        <a class="<?php echo htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $title; ?>"<?php echo $target; ?>>
          <?php echo $label; ?>
        </a>
        <?php
        return;
    }

    ?>
    <span class="<?php echo htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $label; ?></span>
    <?php
}

/**
 * @param array<string, mixed> $featured
 */
function renderNavigationFeaturedCard(array $featured, string $classPrefix = 'site-nav-featured'): void
{
    $title = htmlspecialchars((string) (($featured['title'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8');
    $text = htmlspecialchars((string) (($featured['text'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8');
    $image = is_string($featured['image'] ?? null) ? (string) $featured['image'] : null;
    $href = is_string($featured['href'] ?? null) ? (string) $featured['href'] : null;
    $ctaLabel = htmlspecialchars((string) (($featured['ctaLabel'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8');
    $target = !empty($featured['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';

    if ($title === '' && $text === '' && ($image === null || $image === '') && ($href === null || $href === '')) {
        return;
    }

    $containerClass = htmlspecialchars($classPrefix, ENT_QUOTES, 'UTF-8');
    ?>
    <aside class="<?php echo $containerClass; ?>">
      <?php if ($image !== null && $image !== ''): ?>
      <div class="<?php echo $containerClass; ?>-media">
        <img
          src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
          alt=""
          width="640"
          height="360"
          loading="lazy"
          decoding="async"
          fetchpriority="low"
        />
      </div>
      <?php endif; ?>

      <div class="<?php echo $containerClass; ?>-body">
        <?php if ($title !== ''): ?>
        <strong><?php echo $title; ?></strong>
        <?php endif; ?>
        <?php if ($text !== ''): ?>
        <p><?php echo $text; ?></p>
        <?php endif; ?>
        <?php if ($href !== null && $href !== '' && $ctaLabel !== ''): ?>
        <a class="<?php echo $containerClass; ?>-cta" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $target; ?>>
          <?php echo $ctaLabel; ?>
        </a>
        <?php endif; ?>
      </div>
    </aside>
    <?php
}

/**
 * @param array<int, array<string, mixed>> $items
 */
function renderMegaMenuLinks(array $items): void
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $href = is_string($item['href'] ?? null) ? (string) $item['href'] : null;
        $title = htmlspecialchars((string) (($item['title'] ?? $item['label'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8');
        $target = !empty($item['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
        $highlight = !empty($item['presentation']['isHighlight']) ? ' site-nav-mega-link-highlight' : '';
        ?>
        <li class="site-nav-mega-link<?php echo $highlight; ?>">
          <?php if ($href !== null && $href !== ''): ?>
          <a class="site-nav-mega-link-anchor" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $title; ?>"<?php echo $target; ?>>
            <?php echo $label; ?>
          </a>
          <?php else: ?>
          <span class="site-nav-mega-link-label"><?php echo $label; ?></span>
          <?php endif; ?>

          <?php if ($children !== []): ?>
          <ul class="site-nav-mega-link-children">
            <?php renderMegaMenuLinks($children); ?>
          </ul>
          <?php endif; ?>
        </li>
        <?php
    }
}

/**
 * @param array<int, array<string, mixed>> $columns
 * @return array<int, array<string, mixed>>
 */
function normalizeMegaSectionsForRender(array $columns): array
{
    $sections = [];

    foreach ($columns as $column) {
        if (!is_array($column)) {
            continue;
        }

        foreach ((array) ($column['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            $normalizedItems = array_values(array_filter(
                is_array($section['items'] ?? null) ? $section['items'] : [],
                static fn (mixed $item): bool => is_array($item)
            ));
            $label = trim((string) ($section['label'] ?? ''));
            $href = is_string($section['href'] ?? null) ? (string) $section['href'] : null;
            $firstItem = $normalizedItems[0] ?? null;

            if ($label !== '' && is_array($firstItem)) {
                $firstLabel = trim((string) ($firstItem['label'] ?? ''));
                $firstHref = is_string($firstItem['href'] ?? null) ? (string) $firstItem['href'] : null;

                if ($firstLabel !== '' && strcasecmp($firstLabel, $label) === 0 && $firstHref !== null && $firstHref !== '') {
                    if ($href === null || $href === '') {
                        $href = $firstHref;
                    }

                    array_shift($normalizedItems);
                }
            }

            $sections[] = [
                'label' => $label,
                'href' => $href,
                'items' => $normalizedItems,
            ];
        }
    }

    return $sections;
}

/**
 * @param array<string, mixed> $item
 * @return array{item: array<string, mixed>, children: array<int, array<string, mixed>>}
 */
function normalizeMobileNavigationItemForRender(array $item): array
{
    $normalizedItem = $item;
    $children = array_values(array_filter(
        is_array($item['children'] ?? null) ? $item['children'] : [],
        static fn (mixed $child): bool => is_array($child)
    ));

    $parentLabel = trim((string) ($item['label'] ?? ''));
    if ($parentLabel === '' || $children === []) {
        return [
            'item' => $normalizedItem,
            'children' => $children,
        ];
    }

    $parentHref = is_string($item['href'] ?? null) ? trim((string) $item['href']) : '';
    $matchIndex = null;
    $matchChild = null;

    foreach ($children as $index => $child) {
        $childLabel = trim((string) ($child['label'] ?? ''));
        $childHref = is_string($child['href'] ?? null) ? trim((string) $child['href']) : '';
        $childChildren = is_array($child['children'] ?? null) ? $child['children'] : [];

        if ($childLabel === '' || strcasecmp($childLabel, $parentLabel) !== 0) {
            continue;
        }

        if ($childHref === '' || $childChildren !== []) {
            continue;
        }

        $matchIndex = $index;
        $matchChild = $child;
        break;
    }

    if ($matchIndex === null || !is_array($matchChild)) {
        return [
            'item' => $normalizedItem,
            'children' => $children,
        ];
    }

    $childHref = trim((string) ($matchChild['href'] ?? ''));
    if ($parentHref === '') {
        $normalizedItem['href'] = $childHref;
        if (trim((string) ($normalizedItem['title'] ?? '')) === '') {
            $normalizedItem['title'] = (string) ($matchChild['title'] ?? $matchChild['label'] ?? $parentLabel);
        }
        if (empty($normalizedItem['openInNewTab']) && !empty($matchChild['openInNewTab'])) {
            $normalizedItem['openInNewTab'] = true;
        }
    } elseif ($parentHref !== $childHref) {
        return [
            'item' => $normalizedItem,
            'children' => $children,
        ];
    }

    unset($children[$matchIndex]);

    return [
        'item' => $normalizedItem,
        'children' => array_values($children),
    ];
}

/**
 * @param array<string, mixed> $item
 */
function renderDesktopNavigationItem(array $item, int $depth = 0): void
{
    $children = is_array($item['children'] ?? null) ? $item['children'] : [];
    $hasChildren = $children !== [];
    $panelKind = is_string($item['panelKind'] ?? null) ? (string) $item['panelKind'] : 'dropdown';
    $itemId = htmlspecialchars((string) ($item['id'] ?? 'nav-item'), ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $href = is_string($item['href'] ?? null) ? (string) $item['href'] : null;
    $title = htmlspecialchars((string) (($item['title'] ?? $item['label'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8');
    $target = !empty($item['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
    $activeClass = !empty($item['active']) ? ' site-nav-item-active' : '';
    $megaClass = $panelKind === 'mega' ? ' site-nav-item-mega' : '';
    $showSeparateToggle = $hasChildren && !($depth === 0 && $href === null);
    $togglelessClass = $hasChildren && !$showSeparateToggle ? ' site-nav-item-toggleless' : '';
    $panelId = 'site-nav-panel-desktop-' . $itemId;
    $presentation = is_array($item['presentation'] ?? null) ? $item['presentation'] : [];
    $mega = is_array($item['mega'] ?? null) ? $item['mega'] : [];
    $megaSections = normalizeMegaSectionsForRender((array) ($mega['columns'] ?? []));
    $configuredMegaColumns = max(1, (int) (($mega['columnCount'] ?? 3) ?: 3));
    $megaSectionCount = count($megaSections);
    $renderedMegaColumns = $megaSections !== []
        ? min(6, max($configuredMegaColumns, count($megaSections)))
        : $configuredMegaColumns;
    ?>
    <li class="site-nav-item<?php echo $hasChildren ? ' site-nav-item-has-children' : ''; ?><?php echo $activeClass . $megaClass . $togglelessClass; ?>" data-nav-item>
      <div class="site-nav-row">
        <?php if ($href !== null && $href !== ''): ?>
        <a class="site-nav-link" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $title; ?>"<?php echo $target; ?>>
          <?php echo $label; ?>
        </a>
        <?php elseif ($hasChildren): ?>
        <button
          type="button"
          class="site-nav-label-button"
          data-nav-submenu-toggle
          data-nav-scope="desktop"
          aria-haspopup="true"
          aria-expanded="false"
          aria-controls="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"
        >
          <?php echo $label; ?>
        </button>
        <?php else: ?>
        <span class="site-nav-label-static"><?php echo $label; ?></span>
        <?php endif; ?>

        <?php if ($showSeparateToggle): ?>
        <button
          type="button"
          class="site-nav-toggle"
          data-nav-submenu-toggle
          data-nav-scope="desktop"
          aria-haspopup="true"
          aria-expanded="false"
          aria-controls="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"
        >
          <span class="sr-only"><?php echo htmlspecialchars(sprintf((string) t('TXT_NAV_OPEN_SUBMENU'), (string) $label), ENT_QUOTES, 'UTF-8'); ?></span>
          <span aria-hidden="true">▾</span>
        </button>
        <?php endif; ?>
      </div>

      <?php if ($hasChildren): ?>
      <div
        class="site-nav-panel site-nav-panel-<?php echo htmlspecialchars($panelKind, ENT_QUOTES, 'UTF-8'); ?>"
        id="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"
        data-nav-panel
        data-nav-panel-kind="<?php echo htmlspecialchars($panelKind, ENT_QUOTES, 'UTF-8'); ?>"
        hidden
      >
        <?php if ($panelKind === 'mega'): ?>
        <div class="site-nav-mega site-nav-mega-template-<?php echo htmlspecialchars((string) (($presentation['menuTemplate'] ?? 'standard')), ENT_QUOTES, 'UTF-8'); ?>">
          <div
            class="site-nav-mega-columns<?php echo $megaSectionCount <= 2 ? ' site-nav-mega-columns-compact' : ''; ?>"
            style="--site-nav-mega-columns: <?php echo $renderedMegaColumns; ?>;"
            data-nav-mega-sections="<?php echo (int) $megaSectionCount; ?>"
          >
            <?php foreach ($megaSections as $section): ?>
            <section class="site-nav-mega-section">
              <?php if (!empty($section['label'])): ?>
              <?php if (!empty($section['href'])): ?>
              <a class="site-nav-mega-section-title" href="<?php echo htmlspecialchars((string) $section['href'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) $section['label'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <?php else: ?>
              <h3 class="site-nav-mega-section-title"><?php echo htmlspecialchars((string) $section['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
              <?php endif; ?>
              <?php endif; ?>

              <ul class="site-nav-mega-links">
                <?php renderMegaMenuLinks(is_array($section['items'] ?? null) ? $section['items'] : []); ?>
              </ul>
            </section>
            <?php endforeach; ?>
          </div>

          <?php if (is_array($mega['featuredCard'] ?? null)): ?>
          <?php renderNavigationFeaturedCard($mega['featuredCard'], 'site-nav-featured'); ?>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <ul class="site-nav-sublist">
          <?php foreach ($children as $childItem): ?>
          <?php if (is_array($childItem)) { renderDesktopNavigationItem($childItem, $depth + 1); } ?>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </li>
    <?php
}

/**
 * @param array<string, mixed> $item
 */
function renderMobileNavigationItem(array $item): void
{
    $normalized = normalizeMobileNavigationItemForRender($item);
    $item = $normalized['item'];
    $children = $normalized['children'];
    $hasChildren = $children !== [];
    $itemId = htmlspecialchars((string) ($item['id'] ?? 'nav-item'), ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
    $href = is_string($item['href'] ?? null) ? (string) $item['href'] : null;
    $title = htmlspecialchars((string) (($item['title'] ?? $item['label'] ?? '') ?: ''), ENT_QUOTES, 'UTF-8');
    $target = !empty($item['openInNewTab']) ? ' target="_blank" rel="noopener noreferrer"' : '';
    $panelId = 'site-nav-panel-mobile-' . $itemId;
    $mega = is_array($item['mega'] ?? null) ? $item['mega'] : [];
    ?>
    <li class="site-mobile-nav-item<?php echo $hasChildren ? ' site-mobile-nav-item-has-children' : ''; ?>" data-nav-item>
      <div class="site-mobile-nav-row">
        <?php if ($href !== null && $href !== ''): ?>
        <a class="site-mobile-nav-link" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $title; ?>"<?php echo $target; ?>>
          <?php echo $label; ?>
        </a>
        <?php else: ?>
        <span class="site-mobile-nav-label"><?php echo $label; ?></span>
        <?php endif; ?>

        <?php if ($hasChildren): ?>
        <span class="site-mobile-nav-toggle" aria-hidden="true">
          <span aria-hidden="true">▾</span>
        </span>
        <?php endif; ?>
      </div>

      <?php if ($hasChildren): ?>
      <div
        class="site-mobile-nav-panel"
        id="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"
        data-nav-panel
        hidden
      >
        <ul class="site-mobile-nav-sublist">
          <?php foreach ($children as $childItem): ?>
          <?php if (is_array($childItem)) { renderMobileNavigationItem($childItem); } ?>
          <?php endforeach; ?>
        </ul>

        <?php if (is_array($mega['featuredCard'] ?? null)): ?>
        <?php renderNavigationFeaturedCard($mega['featuredCard'], 'site-mobile-featured'); ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </li>
    <?php
}

/**
 * @param array<string, mixed> $navigation
 */
function renderSiteHeader(array $navigation): void
{
    $brand = is_array($navigation['brand'] ?? null) ? $navigation['brand'] : [];
    $utility = is_array($navigation['utility'] ?? null) ? $navigation['utility'] : [];
    $banner = is_array($navigation['banner'] ?? null) ? $navigation['banner'] : [];
    $primary = is_array($navigation['primary'] ?? null) ? $navigation['primary'] : [];
    $languages = is_array($navigation['languages'] ?? null) ? $navigation['languages'] : [];
    $search = is_array($navigation['search'] ?? null) ? $navigation['search'] : [];

    $brandLabel = htmlspecialchars((string) ($brand['label'] ?? t('TXT_SITE_BRAND')), ENT_QUOTES, 'UTF-8');
    $brandHref = htmlspecialchars((string) ($brand['href'] ?? '/'), ENT_QUOTES, 'UTF-8');
    $brandLogo = htmlspecialchars((string) ($brand['logo'] ?? '/assets/images/structure/favicon-48x48.png'), ENT_QUOTES, 'UTF-8');
    $bannerText = htmlspecialchars((string) ($banner['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bannerImage = is_string($banner['image'] ?? null) ? (string) $banner['image'] : null;
    $bannerTitle = htmlspecialchars((string) ($banner['title'] ?? $banner['headline'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bannerStyle = $bannerImage !== null && $bannerImage !== ''
        ? " style=\"--site-header-banner-image: url('" . htmlspecialchars($bannerImage, ENT_QUOTES, 'UTF-8') . "');\""
        : '';
    $searchAction = htmlspecialchars((string) ($search['action'] ?? '/search'), ENT_QUOTES, 'UTF-8');
    $searchLabel = htmlspecialchars((string) ($search['label'] ?? t('TXT_SEARCH_LABEL')), ENT_QUOTES, 'UTF-8');
    $searchPlaceholder = htmlspecialchars((string) ($search['placeholder'] ?? t('TXT_SEARCH_PLACEHOLDER')), ENT_QUOTES, 'UTF-8');
    $searchLanguage = htmlspecialchars((string) ($search['currentLanguage'] ?? CURRENT_LANG), ENT_QUOTES, 'UTF-8');
    $desktopPrimaryCount = max(1, count(array_filter($primary, static fn (mixed $item): bool => is_array($item))));
    $desktopPrimaryWidth = ($desktopPrimaryCount * 10.8) + (($desktopPrimaryCount - 1) * 0.3);
    $desktopPrimaryStyle = sprintf(
        ' style="--site-nav-root-count: %d; --site-nav-root-width: %.2frem;"',
        $desktopPrimaryCount,
        $desktopPrimaryWidth
    );
    ?>
    <div id="entete" class="site-header-shell">
      <header class="site-header" data-site-navigation>
        <div class="site-header-utility">
          <ul class="site-utility-list" aria-label="<?php echo htmlspecialchars(t('TXT_NAV_UTILITY_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($utility as $utilityItem): ?>
            <?php if (!is_array($utilityItem)) { continue; } ?>
            <?php $utilityHref = htmlspecialchars((string) ($utilityItem['href'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            <li>
              <a
                class="site-utility-link"
                href="<?php echo $utilityHref; ?>"
                title="<?php echo htmlspecialchars((string) ($utilityItem['title'] ?? $utilityItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo !empty($utilityItem['openInNewTab']) || !empty($utilityItem['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
              >
                <?php if (!empty($utilityItem['image'])): ?>
                <img
                  class="site-utility-icon"
                  src="<?php echo htmlspecialchars((string) $utilityItem['image'], ENT_QUOTES, 'UTF-8'); ?>"
                  alt="<?php echo htmlspecialchars((string) ($utilityItem['alt'] ?? $utilityItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                  width="18"
                  height="18"
                />
                <?php endif; ?>
                <span class="sr-only"><?php echo htmlspecialchars((string) ($utilityItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>

          <form class="site-search" action="<?php echo $searchAction; ?>" method="get" role="search">
            <input type="hidden" name="lang" value="<?php echo $searchLanguage; ?>" />
            <label class="sr-only" for="site-search-input"><?php echo $searchLabel; ?></label>
            <input id="site-search-input" type="text" name="q" placeholder="<?php echo $searchPlaceholder; ?>" required />
            <button type="submit" class="site-search-button"><?php echo htmlspecialchars(t('TXT_UI_OK'), ENT_QUOTES, 'UTF-8'); ?></button>
          </form>

          <ul class="site-language-list" aria-label="<?php echo htmlspecialchars(t('TXT_NAV_LANGUAGE_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
            <?php foreach ($languages as $languageItem): ?>
            <?php if (!is_array($languageItem)) { continue; } ?>
            <li>
              <a
                class="site-language-link<?php echo !empty($languageItem['active']) ? ' site-language-link-active' : ''; ?>"
                href="<?php echo htmlspecialchars((string) ($languageItem['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                lang="<?php echo htmlspecialchars((string) ($languageItem['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                title="<?php echo htmlspecialchars((string) ($languageItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
              >
                <?php if (!empty($languageItem['flag'])): ?>
                <img
                  class="site-language-flag"
                  src="<?php echo htmlspecialchars((string) $languageItem['flag'], ENT_QUOTES, 'UTF-8'); ?>"
                  alt="<?php echo htmlspecialchars((string) ($languageItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                  width="18"
                  height="12"
                />
                <?php endif; ?>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="site-header-banner"<?php echo $bannerStyle; ?> title="<?php echo $bannerTitle; ?>">
          <a class="site-brand" href="<?php echo $brandHref; ?>">
            <img src="<?php echo $brandLogo; ?>" alt="<?php echo $brandLabel; ?>" width="52" height="52" />
            <span><?php echo $brandLabel; ?></span>
          </a>
          <?php if ($bannerText !== ''): ?>
          <div class="site-header-marquee" aria-label="<?php echo $bannerTitle !== '' ? $bannerTitle : $bannerText; ?>">
            <p class="site-header-headline">
              <span><?php echo $bannerText; ?></span>
              <span aria-hidden="true"><?php echo $bannerText; ?></span>
            </p>
          </div>
          <?php endif; ?>
          <a class="site-banner-icon" href="<?php echo $brandHref; ?>" aria-label="<?php echo $brandLabel; ?>">
            <img src="<?php echo $brandLogo; ?>" alt="" width="52" height="52" />
          </a>
        </div>

        <div class="site-header-nav-shell" data-nav-scope-root="desktop">
          <nav class="site-nav" aria-label="<?php echo htmlspecialchars(t('TXT_NAV_PRIMARY_ARIA'), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $desktopPrimaryStyle; ?>>
            <ul class="site-nav-list">
              <?php foreach ($primary as $primaryItem): ?>
              <?php if (is_array($primaryItem)) { renderDesktopNavigationItem($primaryItem, 0); } ?>
              <?php endforeach; ?>
            </ul>
          </nav>
        </div>
      </header>
    </div>

    <div id="breadcrumb-mobile" class="site-mobile-header" data-nav-scope-root="mobile">
      <div class="site-mobile-header-bar">
        <button
          id="hamburger-icon"
          class="site-mobile-toggle"
          type="button"
          aria-expanded="false"
          aria-controls="site-mobile-panel"
          data-mobile-nav-toggle
        >
          <span></span><span></span><span></span>
          <span class="sr-only"><?php echo htmlspecialchars(t('TXT_NAV_OPEN_MENU'), ENT_QUOTES, 'UTF-8'); ?></span>
        </button>

        <a class="site-mobile-brand" href="<?php echo $brandHref; ?>">
          <img src="<?php echo $brandLogo; ?>" alt="<?php echo $brandLabel; ?>" width="52" height="52" />
          <span><?php echo $brandLabel; ?></span>
        </a>
      </div>

      <div id="site-mobile-panel" class="site-mobile-panel" data-mobile-nav-panel hidden>
        <div class="site-mobile-banner"<?php echo $bannerStyle; ?>>
          <?php if ($bannerText !== ''): ?>
          <p><?php echo $bannerText; ?></p>
          <?php endif; ?>
        </div>

        <form class="site-search site-search-mobile" action="<?php echo $searchAction; ?>" method="get" role="search">
          <input type="hidden" name="lang" value="<?php echo $searchLanguage; ?>" />
          <label class="sr-only" for="site-search-input-mobile"><?php echo $searchLabel; ?></label>
          <input id="site-search-input-mobile" type="text" name="q" placeholder="<?php echo $searchPlaceholder; ?>" required />
          <button type="submit" class="site-search-button"><?php echo htmlspecialchars(t('TXT_UI_OK'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>

        <ul class="site-mobile-utility-list" aria-label="<?php echo htmlspecialchars(t('TXT_NAV_UTILITY_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
          <?php foreach ($utility as $utilityItem): ?>
          <?php if (!is_array($utilityItem)) { continue; } ?>
          <li>
            <a
              class="site-mobile-utility-link"
              href="<?php echo htmlspecialchars((string) ($utilityItem['href'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
              title="<?php echo htmlspecialchars((string) ($utilityItem['title'] ?? $utilityItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
              <?php echo !empty($utilityItem['openInNewTab']) || !empty($utilityItem['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
            >
              <?php if (!empty($utilityItem['image'])): ?>
              <img
                class="site-utility-icon"
                src="<?php echo htmlspecialchars((string) $utilityItem['image'], ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars((string) ($utilityItem['alt'] ?? $utilityItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                width="18"
                height="18"
              />
              <?php endif; ?>
              <span><?php echo htmlspecialchars((string) ($utilityItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>

        <nav class="site-mobile-nav" aria-label="<?php echo htmlspecialchars(t('TXT_NAV_PRIMARY_MOBILE_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
          <ul class="site-mobile-nav-list">
            <?php foreach ($primary as $primaryItem): ?>
            <?php if (is_array($primaryItem)) { renderMobileNavigationItem($primaryItem); } ?>
            <?php endforeach; ?>
          </ul>
        </nav>

        <ul class="site-language-list site-language-list-mobile" aria-label="<?php echo htmlspecialchars(t('TXT_NAV_LANGUAGE_ARIA'), ENT_QUOTES, 'UTF-8'); ?>">
          <?php foreach ($languages as $languageItem): ?>
          <?php if (!is_array($languageItem)) { continue; } ?>
          <li>
            <a
              class="site-language-link<?php echo !empty($languageItem['active']) ? ' site-language-link-active' : ''; ?>"
              href="<?php echo htmlspecialchars((string) ($languageItem['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
              lang="<?php echo htmlspecialchars((string) ($languageItem['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
              title="<?php echo htmlspecialchars((string) ($languageItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            >
              <?php if (!empty($languageItem['flag'])): ?>
              <img
                class="site-language-flag"
                src="<?php echo htmlspecialchars((string) $languageItem['flag'], ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars((string) ($languageItem['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                width="18"
                height="12"
              />
              <?php endif; ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php
}
