<?php
//templates/pages/site/auto-retro/simca/histoire-de-simca.php

$blocks = [];

// === HEAD (balises META OG + SEO) ===
$blocks['EditRegion10'] = '';

// === Bloc haut (titre principal) ===
$blocks['EditRegion1'] = '<h1>' . t('TXT_TITRESIMCA') . '</h1>';

// === Introduction courte dans bloc gauche ===
$blocks['EditRegion2'] = '<div id="blocHaut" class="border">' . t('TXT_SIMCAINTRO') . '</div>';

// === Logo Simca dans bloc droit ===
$blocks['EditRegion8'] = '
<img src="/assets/images/autoretro/simca/simcal_logo.png"
     title="' . t('IMAGE_ALT_simcalogo') . '"
     alt="' . t('IMAGE_ALT_simcalogo') . '">
';

// === Texte principal ===
$blocks['EditRegion3'] = t('TXT_SIMCADESCRIPT');

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
                <a href="/site/auto-retro/simca/histoire-simca-aronde-icone-francaise.php">
                    ' . t("MENU_HISTOIREARONDE") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_HISTOIREARONDE") . '"
                         title="' . t("MENU_HISTOIREARONDE") . '">
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
