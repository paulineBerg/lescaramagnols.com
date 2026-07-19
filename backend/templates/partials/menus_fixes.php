<?php
// Site Les Caramagnols — Menus fixes gauche/droite
// /templates/menus_fixes.php

/**
 * Rend le contenu d’un menu fixe (sans <div id=...>)
 */
if (!function_exists('renderMenuFixe')) {
    function renderMenuFixe(array $items, string $id, string $label): void
    {
        if (empty($items)) {
            return;
        }

        echo '<div class="menu-fixe-title">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';

        foreach ($items as $item):
            $href = htmlspecialchars((string) ($item['chemin'] ?? ''), ENT_QUOTES, 'UTF-8');
            $image = htmlspecialchars((string) ($item['image'] ?? ''), ENT_QUOTES, 'UTF-8');
            $alt = htmlspecialchars((string) ($item['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
            $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
            $headlineSource = (string) ($item['titre'] ?? '');
            if (trim($headlineSource) === '') {
                $headlineSource = (string) (($item['title'] ?? '') ?: ($item['alt'] ?? ''));
            }
            $headline = htmlspecialchars($headlineSource, ENT_QUOTES, 'UTF-8');
            $text = htmlspecialchars((string) ($item['texte'] ?? ''), ENT_QUOTES, 'UTF-8');
            $imageWidth = max(40, min(1920, (int) ($item['image_width'] ?? 320)));
            $imageHeight = max(40, min(1920, (int) ($item['image_height'] ?? 180)));
            ?>
            <div class="menu-fixe-item">
                <?php if ($href !== ''): ?>
                <a class="menu-fixe-item-link" href="<?= $href ?>" target="_self">
                <?php else: ?>
                <div class="menu-fixe-item-link menu-fixe-item-link-static">
                <?php endif; ?>
                    <?php if ($image !== ''): ?>
                    <img src="<?= $image ?>"
                         alt="<?= $alt ?>"
                         title="<?= $title ?>"
                         loading="lazy"
                         decoding="async"
                         fetchpriority="low"
                         width="<?= $imageWidth ?>"
                         height="<?= $imageHeight ?>">
                    <?php endif; ?>

                    <?php if ($headline !== '' || $text !== ''): ?>
                    <div class="menu-fixe-item-content">
                        <?php if ($headline !== ''): ?>
                        <strong><?= $headline ?></strong>
                        <?php endif; ?>
                        <?php if ($text !== ''): ?>
                        <p><?= $text ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php if ($href !== ''): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach;
    }
}
