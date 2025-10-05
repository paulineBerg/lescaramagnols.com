<?php
//templates/pages/site/auto-retro/simca/la-simca-aronde-1300-voiture-de-collection.php

$blocks = [];

// === HEAD : balises META OG + SEO (VIDÉ comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (titre) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREHISTOIREARONDE1300") . '</h1>
';

// === Introduction (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_HISTOIREARONDE1300INTRO") . '</div>
';

// === Image mascotte dans colonneJustifie25 ===
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/simca/aronde_elysee.jpg"
         title="' . t("IMAGE_ALT_histoirearonde1300") . '"
         alt="' . t("IMAGE_ALT_histoirearonde1300") . '">
';

// === Bloc centre principal (texte complet) ===
$blocks['EditRegion3'] = '
    ' . t("TXT_HISTOIREARONDE1300DESCRIPT") . '
';

// === Bloc bas centre : menus UI ===
$blocks['EditRegion4'] = '
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglerouge">
            <a href="../simca/la-simca-P60-voiture-de-collection.php">
                ' . t("MENU_UI_HISTOIREARONDEP60") . '
                <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                     alt="' . t("MENU_UI_HISTOIREARONDEP60") . '"
                     title="' . t("MENU_UI_HISTOIREARONDEP60") . '">
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
