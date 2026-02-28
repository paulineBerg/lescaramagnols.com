<?php
//templates/pages/site/auto-retro/austin/histoire-de-austin.php

$blocks = [];

// === HEAD : balises META OG/SEO — contenu vidé comme demandé ===
$blocks['EditRegion10'] = '';

// === Bloc haut (h1 titre principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREAUSTIN") . '</h1>
';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <div id="bloc-haut" class="border">' . t("TXT_AUSTININTRO") . '</div>
';

// === ColonneJustifie25 (image de logo Austin) ===
$blocks['EditRegion8'] = '
    <div id="bloccenter">
        <img src="/assets/images/autoretro/austin/austin_logo.jpg"
             title="les différents logo Austin"
             alt="les différents logo Austin">
    </div>
';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
    <h2>' . t("TXT_AUSTINDESCRIPT") . '</h2>
';

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
                <a href="/site/auto-retro/renault/histoire-de-renault.php">
                    ' . t("MENU_RENAULT") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_RENAULT") . '"
                         title="' . t("MENU_RENAULT") . '">
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

// === Bloc bas centre ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant le pied de page ===
$blocks['EditRegion11'] = '';
