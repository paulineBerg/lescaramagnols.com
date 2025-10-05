<?php
//templates/pages/site/auto-retro/panhard/une-dynaz12-dans-le-golfe-de-sttropez.php

$blocks = [];

// === HEAD : balises META OG + SEO (vidé comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (h1 titre principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREDYNA") . '</h1>
';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_DYNAINTRO") . '</div>
';

// === ColonneJustifie25 (image éventuelle) ===
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/panhard/dynaz12/achat_panhard_dyna_z12.jpg"
         title="' . t("IMAGE_ALT_dyna") . '"
         alt="' . t("IMAGE_ALT_dyna") . '">
'; 

// === Bloc centre principal (contenu) ===
$blocks['EditRegion3'] = '
    <h2>' . t("TXT_DYNADESCRIPT") . '</h2>
';

// === Bloc bas centre : menu UI Windows ===
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
                <a href="/site/auto-retro/panhard/histoire-de-panhard.php">
                    ' . t("MENU_UI_PANHARD") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg"
                         alt="' . t("MENU_UI_PANHARD") . '"
                         title="' . t("MENU_UI_PANHARD") . '">
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

