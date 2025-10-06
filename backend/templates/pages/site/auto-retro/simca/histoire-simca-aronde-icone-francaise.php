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

// === Bloc bas centre : menu UI Windows ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="/site/auto-retro/austin/aventure-mini-austin.php">
                    ' . t("MENU_UI_HISTOIREMINI") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg" alt="' . t("MENU_UI_HISTOIREMINI") . '" title="' . t("MENU_UI_HISTOIREMINI") . '">
                </a>
            </div>
        </div>
        <div id="menurectanglewindows">
            <div id="boutonrectangleblanc">
                <a href="/site/auto-retro/mercedes/la-slk-une-voiture-compacte-sportive.php">
                    ' . t("MENU_UI_HISTOIRESLK") . '
                    <img src="/assets/images/structure/menu/auto-retro/uimercedes.jpg"
                         alt="' . t("MENU_UI_HISTOIRESLK") . '"
                         title="' . t("MENU_UI_HISTOIRESLK") . '">
                </a>
            </div>
        </div>      
        <div id="menurectanglewindows">
            <div id="boutonrectangleorange">
                <a href="/site/auto-retro/panhard/une-dyna-icone-automobile.php">
                    ' . t("MENU_UI_HISTOIREDYNA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg" alt="' . t("MENU_UI_HISTOIREDYNA") . '" title="' . t("MENU_UI_HISTOIREDYNA") . '">
                </a>
            </div>
        </div>
    </div> 
    <div id="bloccenter">       
        <div id="menurectanglewindows">
            <div id="boutonrectanglebleufonce">
                <a href="/site/auto-retro/renault/la-twingo-une-voiture-a-succes.php">
                    ' . t("MENU_UI_HISTOIRETWINGO") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_UI_HISTOIRETWINGO") . '"
                         title="' . t("MENU_UI_HISTOIRETWINGO") . '">
                </a>
            </div>
        </div>   
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
