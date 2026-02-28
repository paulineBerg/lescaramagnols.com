<?php
//templates/pages/site/auto-retro/austin/aventure-mini-austin.php

// Initialisation des blocs (évite les erreurs si appelés dans le layout)
$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = ''; 

// === Bloc principal (h1 titre) ===
$blocks['EditRegion1'] = '
<h1>' . t('TXT_TITREHISTOIREMINI') . '</h1>
';

// === Bloc introduction colonne 40% ===
$blocks['EditRegion2'] = '
<div id="bloc-haut" class="border">' . t('TXT_HISTOIREMINIINTRO') . '</div>
';

// === Bloc image colonne 25% ===
$blocks['EditRegion8'] = '
<img src="/assets/images/autoretro/austin/emblemes_mini.jpg"
     title="' . t('IMAGE_ALT_histoiremini') . '"
     alt="' . t('IMAGE_ALT_histoiremini') . '">
';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
<h2>' . t('TXT_HISTOIREMINIDESCRIPT') . '</h2>
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
                <a href="/site/auto-retro/panhard/une-dyna-icone-automobile.php">
                    ' . t("MENU_HISTOIREDYNA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg" alt="' . t("MENU_HISTOIREDYNA") . '" title="' . t("MENU_HISTOIREDYNA") . '">
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


// === Bloc bas gauche (vide pour l’instant) ===
$blocks['EditRegion5'] = '';

// === Bloc bas droite (vide pour l’instant) ===
$blocks['EditRegion6'] = '';

// === Bloc bas centre (vide pour l’instant) ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';

