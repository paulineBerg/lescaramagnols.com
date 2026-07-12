<?php
// Site Les Caramagnols — Gestion des menus footer
// /templates/menus_footer.php

$footerItems = is_array($navigationViewModel['footer'] ?? null) ? $navigationViewModel['footer'] : [];
$legacyMenu3 = is_array($menuConfig['menu3'] ?? null) ? $menuConfig['menu3'] : [];
$langTranslations = is_array($GLOBALS['langTranslations'] ?? null) ? $GLOBALS['langTranslations'] : [];

/**
 * Menu footer canonique : rendu depuis le view model de navigation.
 */
function renderFooterMenu(array $menuItems): void
{
    if ($menuItems === []) {
        return;
    }
    ?>
<div id="menu3">
    <ul id="nav-menu-3">
        <?php foreach ($menuItems as $item): ?>
            <?php renderFooterMenuItem($item); ?>
        <?php endforeach; ?>
    </ul>
</div>
    <?php
}

/**
 * Élément footer canonique.
 */
function renderFooterMenuItem(array $item): void
{
    $children = is_array($item['children'] ?? null) ? $item['children'] : [];
    $hasSubmenu = $children !== [];
    $label = trim((string) ($item['label'] ?? ''));
    $href = trim((string) ($item['href'] ?? ''));
    $alt = htmlspecialchars(trim((string) ($item['alt'] ?? $label)), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars(trim((string) ($item['title'] ?? $label)), ENT_QUOTES, 'UTF-8');
    $targetAttributes = '';
    if (!empty($item['openInNewTab']) || !empty($item['external'])) {
        $targetAttributes = ' target="_blank" rel="noopener noreferrer"';
    }
    ?>
<li class="<?= $hasSubmenu ? 'has-submenu' : '' ?>">
    <?php if ($href !== ''): ?>
        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $alt ?>" title="<?= $title ?>"<?= $targetAttributes ?>>
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php else: ?>
        <span alt="<?= $alt ?>" title="<?= $title ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </span>
    <?php endif; ?>

    <?php if ($hasSubmenu): ?>
        <ul>
            <?php foreach ($children as $subitem): ?>
                <?php renderFooterMenuItem($subitem); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
    <?php
}

/**
 * Menu 3 legacy : fallback de compatibilité.
 */
function renderLegacyFooterMenu(array $menuItems, array $langTranslations): void
{
    if ($menuItems === []) {
        return;
    }
    ?>
<div id="menu3">
    <ul id="nav-menu-3">
        <?php foreach ($menuItems as $item): ?>
            <?php renderLegacyFooterMenuItem($item, $langTranslations); ?>
        <?php endforeach; ?>
    </ul>
</div>
    <?php
}

/**
 * Élément footer legacy.
 */
function renderLegacyFooterMenuItem(array $item, array $langTranslations): void
{
    $hasSubmenu = !empty($item['sous_menu']) && is_array($item['sous_menu']);
    $labelKey = trim((string) ($item['titre'] ?? ''));
    $label = trim((string) ($langTranslations[$labelKey] ?? $labelKey));
    $href = trim((string) ($item['chemin'] ?? ''));
    $alt = htmlspecialchars(trim((string) ($item['alt'] ?? $label)), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars(trim((string) ($item['title'] ?? $label)), ENT_QUOTES, 'UTF-8');
    ?>
<li class="<?= $hasSubmenu ? 'has-submenu' : '' ?>">
    <?php if ($href !== ''): ?>
        <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $alt ?>" title="<?= $title ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php else: ?>
        <span alt="<?= $alt ?>" title="<?= $title ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </span>
    <?php endif; ?>

    <?php if ($hasSubmenu): ?>
        <ul>
            <?php foreach ($item['sous_menu'] as $subitem): ?>
                <?php renderLegacyFooterMenuItem($subitem, $langTranslations); ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</li>
    <?php
}

if ($footerItems !== []) {
    renderFooterMenu($footerItems);
    return;
}

renderLegacyFooterMenu($legacyMenu3, $langTranslations);
?>
