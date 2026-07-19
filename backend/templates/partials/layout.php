<!DOCTYPE html>
<html lang="<?= CURRENT_LANG ?>">
<head>
    <?php
    // file: backend/templates/partials/layout.php

    // ✅ Chargement des menus éditoriaux canonisés depuis backend/data/menus.json
    require_once ROOT_PATH . '/core/menu_loader.php';
    $menuConfig   = load_menus();
    $navigationViewModel = navigation_view_model($_SERVER['REQUEST_URI'] ?? '/', CURRENT_LANG);
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

<?php $bodyClass = trim((string) ($pageBodyClass ?? '')); ?>
<body<?php echo $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>

<?php
if (file_exists(__DIR__ . '/scripts_body.php')) {
    include __DIR__ . '/scripts_body.php';
}
?>

<!-- En-tête principal -->
<?php
if (file_exists(__DIR__ . '/menus_header.php')) {
    require_once __DIR__ . '/menus_header.php';
}

if (function_exists('renderSiteHeader')) {
    renderSiteHeader($navigationViewModel);
}
?>

<?php
if (file_exists(__DIR__ . '/menus_fixes.php')) {
    require_once __DIR__ . '/menus_fixes.php';
}
?>

<div id="menu-droit">
    <?php if (function_exists('renderMenuFixe')) renderMenuFixe($menuDroit, 'menu-droit', t('TXT_MENUDROIT')); ?>
</div>

<div id="menu-gauche">
    <?php if (function_exists('renderMenuFixe')) renderMenuFixe($menuGauche, 'menu-gauche', t('TXT_MENUGAUCHE')); ?>
</div>

<main id="main-content" role="main">
    <div id="contenu">
        <?php
        if (file_exists(__DIR__ . '/contenu.php')) {
            include __DIR__ . '/contenu.php';
        }
        ?>
    </div>
</main>

<!-- Flèche REMONTER -->
<div id="remonter" class="fleche">
    <?php $backToTopLabel = htmlspecialchars(t('REMONTER_TITRE'), ENT_QUOTES, 'UTF-8'); ?>
    <a href="#" onclick="toTop(); return false;" aria-label="<?php echo $backToTopLabel; ?>" role="button">
        <span class="remonter-texte"><?php echo $backToTopLabel; ?></span>
        <img src="/assets/images/structure/menu/remonter.png"
             alt=""
             aria-hidden="true"
             title="<?php echo htmlspecialchars(t('TXT_REMONTER_TITLE'), ENT_QUOTES, 'UTF-8'); ?>"
             width="32"
             height="32"
             loading="lazy"
             decoding="async"
             fetchpriority="low">
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

    <div id="menu-piedpage">
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
