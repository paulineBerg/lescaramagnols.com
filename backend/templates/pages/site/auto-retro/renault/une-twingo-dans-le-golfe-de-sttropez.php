<?php
//templates/pages/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php

$blocks = [];

// === HEAD : balises META OG + SEO (VIDÉ comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (titre principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITRETWINGO") . '</h1>
';

// === Introduction page (vide dans le modèle Dreamweaver) ===
$blocks['EditRegion2'] = '';

// === Image mascotte ou décorative (vide aussi ici) ===
$blocks['EditRegion8'] = '';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
    <h2>' . t("TXT_TWINGODESCRIPT") . '</h2>
';

// === Bloc bas centre avec menus UI ===
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
                <a href="/site/auto-retro/renault/histoire-de-renault.php">
                    ' . t("MENU_UI_RENAULT") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_UI_RENAULT") . '"
                         title="' . t("MENU_UI_RENAULT") . '">
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
