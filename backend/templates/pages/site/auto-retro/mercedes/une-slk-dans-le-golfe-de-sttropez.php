<?php
//templates/pages/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php

$blocks = [];

// === HEAD : balises META OG/SEO (VIDÉ comme demandé) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (h1 principal) ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITRESLK") . '</h1>
';

// === Bloc introduction (colonneJustifie40) ===
$blocks['EditRegion2'] = '
<div id="bloc-haut" class="border">' . t('TXT_SLKINTRO') . '</div>
';

// === Image mascotte ou secondaire (colonneJustifie25) ===
$blocks['EditRegion8'] = '
    <!-- Aucune image spécifiée dans ce fichier -->
';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
    <h2>' . t("TXT_SLKDESCRIPT") . '</h2>
';

// === Bloc bas centre : menu UI Windows ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_MINI") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg" alt="' . t("MENU_MINI") . '" title="' . t("MENU_MINI") . '">
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
                <a href="/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_TWINGO") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_TWINGO") . '"
                         title="' . t("MENU_TWINGO") . '">
                </a>
            </div>
        </div>   
        <div id="menurectanglewindows">
            <div id="boutonrectanglerouge">
                <a href="/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php">
                    ' . t("MENU_ARONDE") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_ARONDE") . '"
                         title="' . t("MENU_ARONDE") . '">
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
