<?php
//templates/pages/site/auto-retro/simca/une-simca-aronde-en-restauration-chez-sava-rioz.php

$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = ''; // contenu vidé comme demandé

// === Bloc haut : Titre principal ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREARONDERESTAURATION") . '</h1>
';

// === ColonneJustifie40 : image principale ===
$blocks['EditRegion2'] = '
    <img src="/assets/images/autoretro/simca/aronde/restauration_simca_aronde_elysee_fin.jpg"
         title="' . t("IMAGE_ALT_aronderestauration") . '"
         alt="' . t("IMAGE_ALT_aronderestauration") . '">
';

// === ColonneJustifie25 : deuxième image ===
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/simca/aronde/acquisition_aronde_1300.jpg"
         title="' . t("IMAGE_ALT_aronderestauration") . '"
         alt="' . t("IMAGE_ALT_aronderestauration") . '">
';

// === Bloc centre principal : description ===
$blocks['EditRegion3'] = '
    ' . t("TXT_ARONDERESTAURATIONDESCRIPT") . '
';

// === Bloc bas centre : menu UI en rectangles ===
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