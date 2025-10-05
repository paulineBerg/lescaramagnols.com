<?php
//templates/pages/site/auto-retro/renault/histoire-de-renault.php

$blocks = [];

// === HEAD : balises META OG + SEO (VIDÉ comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (titre principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITRERENAULT") . '</h1>
';

// === Introduction (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_RENAULTINTRO") . '</div>
';

// === Image logo Renault (colonneJustifie25) ===
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/renault/renault_logo.jpg"
         title="' . t("IMAGE_ALT_renault") . '"
         alt="' . t("IMAGE_ALT_renault") . '">
';

// === Bloc central principal (descriptif) ===
$blocks['EditRegion3'] = '
    <h2>' . t("TXT_RENAULTDESCRIPT") . '</h2>
';

// === Bloc centre bas (menus UI Renault) ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglerouge">
                <a href="/site/auto-retro/simca/histoire-de-simca.php">
                    ' . t("MENU_UI_SIMCA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_UI_SIMCA") . '"
                         title="' . t("MENU_UI_SIMCA") . '">
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
                <a href="/site/auto-retro/renault/la-twingo-une-voiture-a-succes.php">
                    ' . t("MENU_UI_HISTOIRETWINGO") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_UI_HISTOIRETWINGO") . '"
                         title="' . t("MENU_UI_HISTOIRETWINGO") . '">
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
