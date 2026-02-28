<?php
//templates/pages/site/auto-retro/simca/la-simca-9-aronde-voiture-de-collection.php

$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = ''; // Nettoyé comme demandé

// === Bloc haut : titre principal ===
$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREHISTOIREARONDE9") . '</h1>
';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '
    <div id="bloc-haut" class="border">' . t("TXT_HISTOIREARONDE9INTRO") . '</div>
';

// === Image mascotte ou d’intro dans colonneJustifie25 ===
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/simca/aronde/simca_9_calandre_escalier.jpg"
         title="' . t("IMAGE_ALT_histoirearonde9") . '"
         alt="' . t("IMAGE_ALT_histoirearonde9") . '">
';

// === Bloc centre principal (texte) ===
$blocks['EditRegion3'] = '
    ' . t("TXT_HISTOIREARONDE9DESCRIPT") . '
';

// === Bloc bas centre : menu UI de navigation vers autres pages ===
$blocks['EditRegion4'] = '
<!-- MENU UI DE WINDOWS-->
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglerouge">
            <a href="../simca/la-simca-aronde-1300-voiture-de-collection.php">
                ' . t("MENU_HISTOIREARONDE1300") . '
                <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                     alt="' . t("MENU_HISTOIREARONDE1300") . '"
                     title="' . t("MENU_HISTOIREARONDE1300") . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectangleorange">
            <a href="../panhard/une-dyna-dans-le-golfe-de-sttropez.php">
                ' . t("MENU_DYNA") . '
                <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg"
                     alt="' . t("MENU_DYNA") . '"
                     title="' . t("MENU_DYNA") . '">
            </a>
        </div>
    </div>
</div>

<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglevertfonce">
            <a href="une-mini-dans-le-golfe-de-sttropez.php">
                ' . t("MENU_MINI") . '
                <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg"
                     alt="' . t("MENU_MINI") . '"
                     title="' . t("MENU_MINI") . '">
            </a>
        </div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectanglebleuturquoise">
            <a href="../renault/une-twingo-dans-le-golfe-de-sttropez.php">
                ' . t("MENU_TWINGO") . '
                <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                     alt="' . t("MENU_TWINGO") . '"
                     title="' . t("MENU_TWINGO") . '">
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
