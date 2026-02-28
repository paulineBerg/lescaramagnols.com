<?php
// Site Les Caramagnols — Gestion des menus footer
// /templates/menus_footer.php

$menu3 = $menuConfig['menu3'] ?? [];

/**
 * Menu 3 : Menu déroulant principal du pied de page
 */
function renderMenu3(array $menuItems, array $langTranslations): void {
    if (empty($menuItems)) return;
    ?>
    <div id="menu3">
        <ul id="nav-menu-3">
            <?php foreach ($menuItems as $item): ?>
                <?php renderMenu3Item($item, $langTranslations); ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

/**
 * Menu déroulant (élément)
 */
function renderMenu3Item(array $item, array $langTranslations): void {
    $hasSubmenu = !empty($item['sous_menu']) && is_array($item['sous_menu']);
    $labelKey = $item['titre'] ?? '';
    $label = $langTranslations[$labelKey] ?? $labelKey;
    $alt = htmlspecialchars($item['alt'] ?? $label);
    $title = htmlspecialchars($item['title'] ?? $label);
    ?>
    <li class="<?= $hasSubmenu ? 'has-submenu' : '' ?>">
        <?php if (!empty($item['chemin'])): ?>
            <a href="<?= htmlspecialchars($item['chemin']) ?>" alt="<?= $alt ?>" title="<?= $title ?>">
                <?= htmlspecialchars($label) ?>
            </a>
        <?php else: ?>
            <span alt="<?= $alt ?>" title="<?= $title ?>">
                <?= htmlspecialchars($label) ?>
            </span>
        <?php endif; ?>

        <?php if ($hasSubmenu): ?>
            <ul>
                <?php foreach ($item['sous_menu'] as $subitem): ?>
                    <?php renderMenu3Item($subitem, $langTranslations); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
}

// Affichage du menu
renderMenu3($menu3, $langTranslations);
?>

