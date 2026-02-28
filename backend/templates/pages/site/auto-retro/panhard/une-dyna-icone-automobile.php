<?php
//templates/pages/site/auto-retro/panhard/une-dyna-icone-automobile.php

$blocks = [];

// === HEAD ===
$blocks['EditRegion10'] = '';

// === Titre principal
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREHISTOIREDYNA") . '</h1>
';

// === Introduction droite
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_HISTOIREDIANAINTRO") . '</div>
';

// === Image colonne gauche
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/panhard/dyna_centre_volant.jpg"
         title="' . t("IMAGE_ALT_histoiredyna") . '"
         alt="' . t("IMAGE_ALT_histoiredyna") . '">
';

// === Contenu principal
$blocks['EditRegion3'] = t("TXT_HISTOIREDIANADESCRIPT");

// === Bloc bas centre : menu UI Windows ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="/site/auto-retro/austin/aventure-mini-austin.php">
                    ' . t("MENU_HISTOIREMINI") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg" alt="' . t("MENU_HISTOIREMINI") . '" title="' . t("MENU_HISTOIREMINI") . '">
                </a>
            </div>
        </div>
        <div id="menurectanglewindows">
            <div id="boutonrectangleblanc">
                <a href="/site/auto-retro/mercedes/la-slk-une-voiture-compacte-sportive.php">
                    ' . t("MENU_HISTOIRESLK") . '
                    <img src="/assets/images/structure/menu/auto-retro/uimercedes.jpg"
                         alt="' . t("MENU_HISTOIRESLK") . '"
                         title="' . t("MENU_HISTOIRESLK") . '">
                </a>
            </div>
        </div>      
        <div id="menurectanglewindows">
            <div id="boutonrectangleorange">
                <a href="/site/auto-retro/panhard/une-dynaz12-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_DYNA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg" alt="' . t("MENU_DYNA") . '" title="' . t("MENU_DYNA") . '">
                </a>
            </div>
        </div>
    </div> 
    <div id="bloccenter">       
        <div id="menurectanglewindows">
            <div id="boutonrectanglebleufonce">
                <a href="/site/auto-retro/renault/la-twingo-une-voiture-a-succes.php">
                    ' . t("MENU_HISTOIRETWINGO") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_HISTOIRETWINGO") . '"
                         title="' . t("MENU_HISTOIRETWINGO") . '">
                </a>
            </div>
        </div>   
        <div id="menurectanglewindows">
            <div id="boutonrectanglerouge">
                <a href="/site/auto-retro/simca/simca-aronde-icone-francaise.php">
                    ' . t("MENU_HISTOIREARONDE") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_HISTOIREARONDE") . '"
                         title="' . t("MENU_HISTOIREARONDE") . '">
                </a>
            </div>
        </div>
    </div> 
';

// === Bas  ===
// === Bloc bas gauche ===
$blocks['EditRegion5'] = '';

// === Bloc bas droite ===
$blocks['EditRegion6'] = '';

// === Bloc bas centre (au cas où) ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';
