<?php
//templates/pages/site/auto-retro/austin/aventure-mini-austin.php

// Initialisation des blocs (évite les erreurs si appelés dans le layout)
$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = ''; 

// === Bloc principal (h1 titre) ===
$blocks['EditRegion1'] = '
<h1>' . t('TXT_TITREHISTOIRESLK') . '</h1>
';

// === Bloc introduction colonne 40% ===
$blocks['EditRegion2'] = '
<div id="blocHaut" class="border">' . t('TXT_HISTOIRESLKINTRO') . '</div>
';

// === Bloc image colonne 25% ===
$blocks['EditRegion8'] = '
<img src="/assets/images/autoretro/austin/emblemes_mini.jpg"
     title="' . t('IMAGE_ALT_histoireslk') . '"
     alt="' . t('IMAGE_ALT_histoireslk') . '">
';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
<h2>' . t('TXT_HISTOIRESLKDESCRIPT') . '</h2>
';

// === Bloc bas centre : menus UI voiture ===
$blocks['EditRegion4'] = '
<!-- MENU UI DE WINDOWS -->
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglerouge">
            <a href="/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php">
                ' . t('MENU_UI_ARONDE') . '
                <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                     alt="' . t('MENU_UI_ARONDE') . '"
                     title="' . t('MENU_UI_ARONDE') . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectangleorange">
            <a href="/site/auto-retro/panhard/une-dyna-dans-le-golfe-de-sttropez.php">
                ' . t('MENU_UI_DYNA') . '
                <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg"
                     alt="' . t('MENU_UI_DYNA') . '"
                     title="' . t('MENU_UI_DYNA') . '">
            </a>
        </div>
    </div>
</div>
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglevertfonce">
            <a href="/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php">
                ' . t('MENU_UI_MINI') . '
                <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg"
                     alt="' . t('MENU_UI_MINI') . '"
                     title="' . t('MENU_UI_MINI') . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectanglebleuturquoise">
            <a href="/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php">
                ' . t('MENU_UI_TWINGO') . '
                <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                     alt="' . t('MENU_UI_TWINGO') . '"
                     title="' . t('MENU_UI_TWINGO') . '">
            </a>
        </div>
    </div>
</div>
';

// === Bloc bas gauche (vide pour l’instant) ===
$blocks['EditRegion5'] = '';

// === Bloc bas droite (vide pour l’instant) ===
$blocks['EditRegion6'] = '';

// === Bloc bas centre (vide pour l’instant) ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';

