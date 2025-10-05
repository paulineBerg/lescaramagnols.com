<?php
//templates/pages/site/auto-retro/simca/histoire-de-simca.php

$blocks = [];

// === HEAD (balises META OG + SEO) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (titre principal) ===
$blocks['EditRegion1'] = '<h1>' . t('TXT_TITRESIMCA') . '</h1>';

// === Introduction courte dans bloc gauche ===
$blocks['EditRegion2'] = '<div id="blocHaut" class="border">' . t('TXT_SIMCAINTRO') . '</div>';

// === Logo Simca dans bloc droit ===
$blocks['EditRegion8'] = '
<img src="/assets/images/autoretro/simca/simcal_logo.png"
     title="' . t('IMAGE_ALT_simcalogo') . '"
     alt="' . t('IMAGE_ALT_simcalogo') . '">
';

// === Texte principal ===
$blocks['EditRegion3'] = t('TXT_SIMCADESCRIPT');

// === Menu UI (en bas de page) à ne surtout pas modifier ===
$blocks['EditRegion4'] = '
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglerouge">
            <a href="/site/auto-retro/simca/histoire-simca-aronde-icone-francaise">
                ' . t('MENU_histoirearonde') . '
                <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                     alt="' . t('MENU_histoirearonde') . '"
                     title="' . t('MENU_histoirearonde') . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectangleorange">
            <a href="/site/auto-retro/panhard/histoire-de-panhard.php">
                ' . t('MENU_UI_PANHARD') . '
                <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg"
                     alt="' . t('MENU_UI_PANHARD') . '"
                     title="' . t('MENU_UI_PANHARD') . '">
            </a>
        </div>
    </div>
</div>
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglevertfonce">
            <a href="/site/auto-retro/austin/histoire-de-austin.php">
                ' . t('MENU_UI_AUSTIN') . '
                <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg"
                     alt="' . t('MENU_UI_AUSTIN') . '"
                     title="' . t('MENU_UI_AUSTIN') . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectanglebleuturquoise">
            <a href="/site/auto-retro/renault/histoire-de-renault.php">
                ' . t('MENU_UI_RENAULT') . '
                <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                     alt="' . t('MENU_UI_RENAULT') . '"
                     title="' . t('MENU_UI_RENAULT') . '">
            </a>
        </div>
    </div>
</div>
';

// === Bloc bas gauche ===
$blocks['EditRegion5'] = '';

// === Bloc bas droite ===
$blocks['EditRegion6'] = '';

// === Bloc bas centre (au cas où) ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';
