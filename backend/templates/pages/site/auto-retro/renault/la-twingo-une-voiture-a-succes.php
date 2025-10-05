<?php
//templates/pages/site/auto-retro/renault/la-twingo-une-voiture-a-succes.php

$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = '';

// === Titre principal
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREHISTOIRETWINGO") . '</h1>
';

// === Introduction courte (colonne gauche)
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_HISTOIRETWINGOINTRO") . '</div>
';

// === Image mascotte (colonne droite)
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/renault/affiche_twingo.jpg"
         title="' . t("IMAGE_ALT_histoiretwingo") . '"
         alt="' . t("IMAGE_ALT_histoiretwingo") . '">
';

// === Contenu principal
$blocks['EditRegion3'] = t("TXT_HISTOIRETWINGODESCRIPT");

// === Menu UI (à ne pas modifier) ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglerouge">
                <a href="/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_UI_ARONDE") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_UI_ARONDE") . '"
                         title="' . t("MENU_UI_ARONDE") . '">
                </a>
            </div>
        </div>
        <div id="menurectanglewindows">
            <div id="boutonrectangleorange">
                <a href="/site/auto-retro/panhard/une-dyna-dans-le-golfe-de-sttropez.php">
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
                <a href="/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_UI_MINI") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg"
                         alt="' . t("MENU_UI_MINI") . '"
                         title="' . t("MENU_UI_MINI") . '">
                </a>
            </div>
        </div>
        <div id="menurectanglewindows">
            <div id="boutonrectanglebleuturquoise">
                <a href="/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php">
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
