<?php
// Site Les Caramagnols — Gestion des menus principaux
// /templates/menus_header.php

// ✅ Chargement dynamique de la langue
$langTranslations = $langTranslations ?? require ROOT_PATH . '/lang/fr.php';

// Menus principaux
$menu1        = $menuConfig['menu1']    ?? [];
$banniereData = $menuConfig['banniere'] ?? [];
$menu2        = $menuConfig['menu2']    ?? [];

/**
 * Menu 1 : Réseaux sociaux à gauche + Recherche + Langues à droite
 */
function renderMenu1(array $menuItems): void {
    if (empty($menuItems)) return;
    ?>
    <div id="menu1" class="menu1">
        <div class="menu1-inner">
            <!-- Réseaux sociaux -->
            <ul id="navReseaux">
                <?php foreach ($menuItems as $item): ?>
                    <?php if (isset($item['image'])): ?>
                        <li class="bouton_gauche">
                            <a href="<?= htmlspecialchars($item['url']) ?>" target="_blank" rel="noopener noreferrer"
                               title="<?= htmlspecialchars($item['title'] ?? $item['alt'] ?? '') ?>">
                                <img src="<?= htmlspecialchars($item['image']) ?>"
                                     alt="<?= htmlspecialchars($item['alt'] ?? '') ?>"
                                     title="<?= htmlspecialchars($item['title'] ?? $item['alt'] ?? '') ?>" />
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <!-- Recherche -->
            <div id="menuRecherche">
                <form action="/site/search" method="get" role="search">
                    <input type="hidden" name="lang" value="<?= CURRENT_LANG ?>">
                    <input type="text" name="q" placeholder="<?= t('MENU_RECHERCHER') ?>..." required aria-label="<?= t('MENU_RECHERCHE') ?>">
                    <button type="submit">🔍</button>
                </form>
            </div>    

            <!-- Drapeaux (langues) -->
            <ul id="navLangues">
                <?php
                $langs = ['fr' => 'Français', 'de' => 'Allemand', 'en' => 'Anglais'];
                foreach ($langs as $code => $label):
                    $active = (CURRENT_LANG === $code) ? 'active-lang' : '';
                    $img = [
                        'fr' => 'drapeaufranc.gif',
                        'de' => 'drapeauallem.gif',
                        'en' => 'drapeauangl.gif'
                    ][$code];
                ?>
                <li class="bouton_droite <?= $active ?>">
                    <a href="?lang=<?= $code ?>" onclick="setLangCookie('<?= $code ?>')" title="<?= $label ?>">
                        <img src="/assets/images/structure/menu/<?= $img ?>" alt="<?= $label ?>"
                             class="lang-flag <?= $active ?>">
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

            <script>
            function setLangCookie(code) {
                document.cookie = "lang=" + code + "; path=/; max-age=31536000";
            }
            </script>
        </div>
    </div>
    <?php
}

/**
 * Bannière : image + texte traduit
 */
function renderBanniere(array $banniereData, array $langTranslations): void {
    $bannerText = $langTranslations[$banniereData['texte_key']] ?? $banniereData['texte_key'] ?? '';
    $imageUrl = htmlspecialchars($banniereData['image'] ?? '');
    $alt = htmlspecialchars($banniereData['alt'] ?? '');
    $title = htmlspecialchars($banniereData['title'] ?? '');
    ?>
    <div id="banniere" class="banniere" style="background-image: url('<?= $imageUrl ?>');" title="<?= $title ?>">
        <div class="banniere-texte" title="<?= $title ?>"><?= htmlspecialchars($bannerText) ?></div>
    </div>
    <?php
}

/**
 * Menu 2 : Menu déroulant principal
 */
function renderMenu2(array $menuItems, array $langTranslations): void {
    if (empty($menuItems)) return;
    $menuIcon = '/assets/images/structure/favicon-48x48.png';
    ?>
    <div id="menu2position">
        <nav id="menuDeroulant" aria-label="Menu principal">
            <ul id="menu2">
                <li class="menu2-favicon menu2-favicon-left" aria-hidden="true">
                    <img src="<?= htmlspecialchars($menuIcon) ?>" alt="">
                </li>
                <?php foreach ($menuItems as $item): ?>
                    <?php renderMenu2Item($item, $langTranslations); ?>
                <?php endforeach; ?>
                <li class="menu2-favicon menu2-favicon-right" aria-hidden="true">
                    <img src="<?= htmlspecialchars($menuIcon) ?>" alt="">
                </li>
            </ul>
        </nav>
    </div>
    <?php
}

/**
 * Menu déroulant (élément)
 */
function renderMenu2Item(array $item, array $langTranslations): void {
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
            <span alt="<?= $alt ?>" title="<?= $title ?>"><?= htmlspecialchars($label) ?></span>
        <?php endif; ?>

        <?php if ($hasSubmenu): ?>
            <ul>
                <?php foreach ($item['sous_menu'] as $subitem): ?>
                    <?php renderMenu2Item($subitem, $langTranslations); ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </li>
    <?php
}

/**
 * Menu mobile type hamburger — version propre et accessible
 */
function renderMenuHamburger(array $menuItems, array $langTranslations): void {
    if (empty($menuItems)) return;

    global $banniereData;
    ?>
    <div id="breadcrumb-mobile">
        <div class="menu-header">
            <!-- Icône hamburger -->
            <div id="hamburger-icon" role="button" tabindex="0" aria-label="Ouvrir le menu mobile"
                 aria-controls="mobile-menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </div>

            <!-- Bannière mobile -->
            <?php if (function_exists('renderBanniere') && !empty($banniereData)): ?>
                <div class="banniere-mobile-header">
                    <?php renderBanniere($banniereData, $langTranslations); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Menu mobile -->
        <nav id="mobile-menu" class="breadcrumb-menu" aria-label="Menu mobile">

            <!-- Recherche mobile -->
            <div id="menuRecherche">
                <form action="/site/search" method="get" role="search">
                    <input type="hidden" name="lang" value="<?= CURRENT_LANG ?>">
                    <input type="text" name="q" placeholder="<?= t('MENU_RECHERCHER') ?>..." required aria-label="<?= t('MENU_RECHERCHE') ?>">
                    <button type="submit">🔍</button>
                </form>
            </div>
            <!-- Menu principal (mobile) -->
            <div>
                <ul>
                    <?php foreach ($menuItems as $item): ?>
                        <?php renderMenu2Item($item, $langTranslations); ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Langues (mobile) -->
            <div id="menuLanguesMobile">
                <ul class="langues-mobiles">
                    <?php
                    $langs = ['fr' => 'Français', 'de' => 'Allemand', 'en' => 'Anglais'];
                    foreach ($langs as $code => $label):
                        $active = (CURRENT_LANG === $code) ? 'active-lang' : '';
                        $img = [
                            'fr' => 'drapeaufranc.gif',
                            'de' => 'drapeauallem.gif',
                            'en' => 'drapeauangl.gif'
                        ][$code];
                    ?>
                    <li class="<?= $active ?>">
                        <a href="?lang=<?= $code ?>" onclick="setLangCookie('<?= $code ?>')" title="<?= $label ?>">
                            <img src="/assets/images/structure/menu/<?= $img ?>" alt="<?= $label ?>"
                                 class="lang-flag <?= $active ?>">
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>

        <script>
        function setLangCookie(code) {
            document.cookie = "lang=" + code + "; path=/; max-age=31536000";
        }
        </script>
    </div>
    <?php
}
