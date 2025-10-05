<?php
// Site Les Caramagnols — Menus fixes gauche/droite
// /templates/menus_fixes.php

$menuDroit = $menuConfig['menu_droit'] ?? [];
$menuGauche = $menuConfig['menu_gauche'] ?? [];

/**
 * Rend le contenu d’un menu fixe (sans <div id=...>)
 */
function renderMenuFixe(array $items, string $id, string $label): void {
    if (empty($items)) return;

    echo htmlspecialchars($label);
    foreach ($items as $item): ?>
        <div>
            <a href="<?= htmlspecialchars($item['chemin']) ?>" target="_self">
                <img src="<?= htmlspecialchars($item['image']) ?>"
                     alt="<?= htmlspecialchars($item['alt']) ?>"
                     title="<?= htmlspecialchars($item['title']) ?>">
            </a>
        </div>
    <?php endforeach;
}

?>
