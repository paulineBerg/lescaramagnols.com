<!DOCTYPE html>
<html lang="<?= CURRENT_LANG ?>">
<head>
    <?php
    // templates/partials/layout.php

    // ✅ Chargement des traductions dynamiques dès le début
    require_once ROOT_PATH . '/core/lang_bootstrap.php';

    // ✅ Chargement des menus
    $menuConfig   = require ROOT_PATH . '/config/menu_data.php';
    $menu1        = $menuConfig['menu1']    ?? [];
    $banniereData = $menuConfig['banniere'] ?? [];
    $menu2        = $menuConfig['menu2']    ?? [];
    $menu3        = $menuConfig['menu3']    ?? [];
    $menuDroit    = $menuConfig['menuDroit'] ?? [];
    $menuGauche   = $menuConfig['menuGauche'] ?? [];

    // Scripts <head>
    if (file_exists(__DIR__ . '/scripts_head.php')) {
        include __DIR__ . '/scripts_head.php';
    }

    if (file_exists(__DIR__ . '/header.php')) {
        include __DIR__ . '/header.php';
    }
    ?>
</head>

<body>

<?php
if (file_exists(__DIR__ . '/scripts_body.php')) {
    include __DIR__ . '/scripts_body.php';
}
?>

<!-- En-tête principal -->
<div id="entete">
    <?php
    if (file_exists(__DIR__ . '/menus_header.php')) {
        require_once __DIR__ . '/menus_header.php';
    }

    if (function_exists('renderMenu1'))       renderMenu1($menu1, $langTranslations);
    if (function_exists('renderBanniere'))    renderBanniere($banniereData, $langTranslations);
    if (function_exists('renderMenu2'))       renderMenu2($menu2, $langTranslations);
    ?>
</div>

<!-- Menu mobile -->
<?php
if (function_exists('renderMenuHamburger')) {
    renderMenuHamburger($menu2, $langTranslations);
}
?>

<?php
if (file_exists(__DIR__ . '/menus_fixes.php')) {
    require_once __DIR__ . '/menus_fixes.php';
}
?>

<div id="menuDroit">
    <?php if (function_exists('renderMenuFixe')) renderMenuFixe($menuDroit, 'menuDroit', t('TXT_MENUDROIT')); ?>
</div>

<div id="menuGauche">
    <?php if (function_exists('renderMenuFixe')) renderMenuFixe($menuGauche, 'menuGauche', t('TXT_MENUGAUCHE')); ?>
</div>

<div id="contenu">
    <?php
    if (file_exists(__DIR__ . '/contenu.php')) {
        require_once __DIR__ . '/contenu.php';
    }
    ?>
</div>

<!-- Flèche REMONTER -->
<div id="remonter" class="fleche">
    <a href="#" onclick="toTop(); return false;" aria-label="Remonter en haut de page" role="button">
        <span class="remonter-texte">TOP</span>
        <img src="/assets/images/structure/menu/remonter.png"
             alt="remonter en haut"
             title="Cliquez pour remonter en haut">
    </a>
</div>

<div id="piedpage">
    <div id="menu3">
        <?php
        if (file_exists(__DIR__ . '/menus_footer.php')) {
            require_once __DIR__ . '/menus_footer.php';
        }
        ?>
    </div>

    <div id="MenuPiedpage">
        <?php
        if (file_exists(__DIR__ . '/footer.php')) {
            include __DIR__ . '/footer.php';
        }
        ?>
    </div>

    <?= $blocks['EditRegion9'] ?? '' ?>
</div>

<?= $blocks['EditRegion12'] ?? '' ?>

<?php
if (file_exists(__DIR__ . '/scripts_body_bas.php')) {
    include __DIR__ . '/scripts_body_bas.php';
}
?>

</body>
</html>
