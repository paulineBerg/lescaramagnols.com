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

// === Bloc bas centre (au cas où) ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';