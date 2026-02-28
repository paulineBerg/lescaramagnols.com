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
    <div id="bloc-haut" class="border">' . t("TXT_RENAULTINTRO") . '</div>
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

// === Bloc bas centre : menu UI Windows ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="/site/auto-retro/austin/histoire-de-austin.php">
                    ' . t("MENU_AUSTIN") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg" alt="' . t("MENU_AUSTIN") . '" title="' . t("MENU_AUSTIN") . '">
                </a>
            </div>
        </div>
        <div id="menurectanglewindows">
            <div id="boutonrectangleblanc">
                <a href="/site/auto-retro/mercedes/histoire-de-mercedes.php">
                    ' . t("MENU_MERCEDES") . '
                    <img src="/assets/images/structure/menu/auto-retro/uimercedes.jpg"
                         alt="' . t("MENU_MERCEDES") . '"
                         title="' . t("MENU_MERCEDES") . '">
                </a>
            </div>
        </div>      
        <div id="menurectanglewindows">
            <div id="boutonrectangleorange">
                <a href="/site/auto-retro/panhard/histoire-de-panhard.php">
                    ' . t("MENU_PANHARD") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg" alt="' . t("MENU_PANHARD") . '" title="' . t("MENU_PANHARD") . '">
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
                <a href="/site/auto-retro/simca/histoire-de-simca.php">
                    ' . t("MENU_SIMCA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_SIMCA") . '"
                         title="' . t("MENU_SIMCA") . '">
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
