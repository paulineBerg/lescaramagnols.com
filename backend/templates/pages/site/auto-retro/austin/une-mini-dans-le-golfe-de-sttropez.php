<?php
//templates/pages/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php

$blocks = [];

// === HEAD : balises META OG/SEO (VIDÉ comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (h1 principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREMINI") . '</h1>
';

// === Bloc introduction (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <!--INTRODUCTION DE LA PAGE-->
';

// === Image mascotte ou secondaire (colonneJustifie25) ===
$blocks['EditRegion8'] = '
    <!-- Aucune image spécifiée dans ce fichier -->
';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
    <h2>' . t("TXT_MINIDESCRIPT") . '</h2>
';

// === Bloc bas centre (menu UI rectangulaires) ===
$blocks['EditRegion4'] = '
    <!-- MENU UI DE WINDOWS -->
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
                <a href="/site/auto-retro/austin/histoire-de-austin.php">
                    ' . t("MENU_UI_AUSTIN") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg"
                         alt="' . t("MENU_UI_AUSTIN") . '"
                         title="' . t("MENU_UI_AUSTIN") . '">
                </a>
            </div>
        </div>
        <div id="menurectanglewindows">
            <div id="boutonrectanglebleuturquoise">
                <a href="/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_UI_RENAULT") . '
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

// === Bloc bas centre : fin texte ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';
