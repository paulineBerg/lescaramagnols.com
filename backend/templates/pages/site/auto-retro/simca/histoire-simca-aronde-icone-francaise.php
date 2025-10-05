<?php
//templates/pages/site/auto-retro/simca/histoire-simca-aronde-icone-francaise.php

$blocks = [];

// === HEAD : META (VIDÉ comme demandé)
$blocks['EditRegion10'] = '';

// === Bloc haut (titre)
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREHISTOIREARONDE") . '</h1>
';

// === Introduction
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_HISTOIREARONDEINTRO") . '</div>
';

// === Image mascotte
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/simca/mascotte_aronde.jpg"
         title="' . t("IMAGE_ALT_histoirearonde") . '"
         alt="' . t("IMAGE_ALT_histoirearonde") . '">
';

// === Contenu principal
$blocks['EditRegion3'] = '
    ' . t("TXT_HISTOIREARONDEDESCRIPT") . '
';

// === Menu UI (bas centre)
$blocks['EditRegion4'] = '
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglerouge">
            <a href="/site/auto-retro/simca/la-simca-9-aronde-voiture-de-collection.php">
                ' . t("MENU_UI_HISTOIREARONDE9") . '
                <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                     alt="' . t("MENU_UI_HISTOIREARONDE9") . '"
                     title="' . t("MENU_UI_HISTOIREARONDE9") . '">
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
