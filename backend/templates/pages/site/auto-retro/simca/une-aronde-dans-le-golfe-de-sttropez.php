<?php
//templates/pages/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php

$blocks = [];

// === HEAD : balises META OG + SEO (VIDÉ comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (titre principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREARONDE") . '</h1>
';

// === Introduction (colonneJustifie40 avec image dans le bloc) ===
$blocks['EditRegion2'] = '
    <img src="/assets/images/autoretro/simca/aronde/exposition_simca_aronde.jpg"
         title="' . t("IMAGE_ALT_aronde") . '"
         alt="' . t("IMAGE_ALT_aronde") . '">
';

// === Image secondaire (colonneJustifie25) ===
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/simca/aronde/simca_aronde_a_la_maison.jpg"
         title="' . t("IMAGE_ALT_aronde") . '"
         alt="' . t("IMAGE_ALT_aronde") . '">
';

// === Bloc centre principal (contenu texte) ===
$blocks['EditRegion3'] = '
    ' . t("TXT_ARONDEDESCRIPT") . '
';

// === Bloc bas centre : menu UI Windows ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_UI_MINI") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg" alt="' . t("MENU_UI_MINI") . '" title="' . t("MENU_UI_MINI") . '">
                </a>
            </div>
        </div>
        <div id="menurectanglewindows">
            <div id="boutonrectangleblanc">
                <a href="/site/auto-retro/mercedes/une-slk-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_UI_SLK") . '
                    <img src="/assets/images/structure/menu/auto-retro/uimercedes.jpg"
                         alt="' . t("MENU_UI_SLK") . '"
                         title="' . t("MENU_UI_SLK") . '">
                </a>
            </div>
        </div>      
        <div id="menurectanglewindows">
            <div id="boutonrectangleorange">
                <a href="/site/auto-retro/panhard/une-dynaz12-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_UI_DYNA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg" alt="' . t("MENU_UI_DYNA") . '" title="' . t("MENU_UI_DYNA") . '">
                </a>
            </div>
        </div>
    </div> 
    <div id="bloccenter">       
        <div id="menurectanglewindows">
            <div id="boutonrectanglebleufonce">
                <a href="/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_UI_TWINGO") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_UI_TWINGO") . '"
                         title="' . t("MENU_UI_TWINGO") . '">
                </a>
            </div>
        </div>   
        <div id="menurectanglewindows">
            <div id="boutonrectanglerouge">
                <a href="/site/auto-retro/simca/une-simca-aronde-en-restauration-chez-sava-rioz.php">
                    ' . t("MENU_UI_ARONDERESTAURATION") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_UI_ARONDERESTAURATION") . '"
                         title="' . t("MENU_UI_ARONDERESTAURATION") . '">
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
