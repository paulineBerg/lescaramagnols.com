<?php
//templates/pages/site/auto-retro/austin/histoire-de-austin.php

$blocks = [];

// === HEAD : balises META OG/SEO — contenu vidé comme demandé ===
$blocks['EditRegion10'] = '';

// === Bloc haut (h1 titre principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREMERCEDES") . '</h1>
';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_MERCEDESINTRO") . '</div>
';

// === ColonneJustifie25 (image de logo Austin) ===
$blocks['EditRegion8'] = '
    <div id="bloccenter">

    </div>
';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
    <h2>' . t("TXT_MERCEDESDESCRIPT") . '</h2>
';

// === Bloc bas centre : menu UI Windows ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="/site/auto-retro/austin/histoire-de-austin.php">
                    ' . t("MENU_UI_AUSTIN") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg" alt="' . t("MENU_UI_AUSTIN") . '" title="' . t("MENU_UI_AUSTIN") . '">
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
                <a href="/site/auto-retro/panhard/histoire-de-panhard.php">
                    ' . t("MENU_UI_PANHARD") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg" alt="' . t("MENU_UI_PANHARD") . '" title="' . t("MENU_UI_PANHARD") . '">
                </a>
            </div>
        </div>
    </div> 
    <div id="bloccenter">       
        <div id="menurectanglewindows">
            <div id="boutonrectanglebleufonce">
                <a href="/site/auto-retro/renault/histoire-de-renault.php">
                    ' . t("MENU_UI_RENAULT") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_UI_RENAULT") . '"
                         title="' . t("MENU_UI_RENAULT") . '">
                </a>
            </div>
        </div>   
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
