<?php
//templates/pages/site/auto-retro/simca/la-simca-P60-voiture-de-collection.php

$blocks = [];

// === HEAD : balises META OG + SEO (VIDÉ comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (titre principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREHISTOIREARONDEP60") . '</h1>
';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_HISTOIREARONDEP60INTRO") . '</div>
';

// === Image d'intro (colonneJustifie25) ===
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/simca/aronde/simca_aronde_P60_bacalan.jpg"
         title="' . t("IMAGE_ALT_histoirearondep60") . '"
         alt="' . t("IMAGE_ALT_histoirearondep60") . '">
';

// === Bloc centre principal : contenu historique ===
$blocks['EditRegion3'] = '
    ' . t("TXT_HISTOIREARONDEP60DESCRIPT") . '
';

// === Bloc bas centre : menus UI ===
$blocks['EditRegion4'] = '
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglerouge">
            <a href="../simca/une-aronde-dans-le-golfe-de-sttropez.php">
                ' . t("MENU_UI_ARONDE") . '
                <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                     alt="' . t("MENU_UI_ARONDE") . '"
                     title="' . t("MENU_UI_ARONDE") . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectangleorange">
            <a href="../panhard/une-dyna-dans-le-golfe-de-sttropez.php">
                ' . t("MENU_UI_DYNA") . '
                <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg"
                     alt="' . t("MENU_UI_DYNA") . '"
                     title="' . t("MENU_UI_DYNA") . '">
            </a>
        </div>
    </div>
</div>

<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglevertfonce">
            <a href="une-mini-dans-le-golfe-de-sttropez.php">
                ' . t("MENU_UI_MINI") . '
                <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg"
                     alt="' . t("MENU_UI_MINI") . '"
                     title="' . t("MENU_UI_MINI") . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectanglebleuturquoise">
            <a href="../renault/une-twingo-dans-le-golfe-de-sttropez.php">
                ' . t("MENU_UI_TWINGO") . '
                <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                     alt="' . t("MENU_UI_TWINGO") . '"
                     title="' . t("MENU_UI_TWINGO") . '">
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
