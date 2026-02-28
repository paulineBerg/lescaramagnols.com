<?php
// templates/pages/site/bouger/se-promener-a-La-Garde-Freinet-golfe-de-sttropez.php

$blocks = [];

// Titre de la page
$blocks['EditRegion1'] = '<h1>' . t('TXT_TITREGARDEFREINET') . '</h1>
<img src="/assets/images/bouger/communes/lagardefreinet/la_garde_freinet_village.jpg" alt="' . t('IMAGE_ALT_gardefreinetvillage') . '" title="' . t('IMAGE_ALT_gardefreinetvillage') . '">';

// Colonne gauche (intro 40%)
$blocks['EditRegion2'] = '<div class="border">' . t('TXT_GARDEFREINETINTRO') . '</div>';

// Colonne droite (images 25%)
$blocks['EditRegion8'] = '
<img src="/assets/images/bouger/communes/lagardefreinet/la_garde_freinet_la_croix.jpg" alt="' . t('IMAGE_ALT_gardefreinetcroix') . '" title="' . t('IMAGE_ALT_gardefreinetcroix') . '">
';

// Bloc central (description historique)
$blocks['EditRegion3'] = '
<p>' . t('TXT_GARDEFREINETCENTRE') . '</p>
<div class="img">
    <img class="imgpetit" src="/assets/images/bouger/communes/lagardefreinet/place.jpg" alt="' . t('IMAGE_ALT_gardefreinetplace') . '" title="' . t('IMAGE_ALT_gardefreinetplace') . '">
    <img class="imgpetit" src="/assets/images/bouger/communes/lagardefreinet/la_garde_freinet_la_croix.jpg" alt="' . t('IMAGE_ALT_gardefreinetcroix') . '" title="' . t('IMAGE_ALT_gardefreinetcroix') . '">
</div>
<p>' . t('TXT_GARDEFREINETCONCLUSION') . '</p>
<div class="img">
<img src="/assets/images/bouger/communes/lagardefreinet/escalier.jpg" alt="' . t('IMAGE_ALT_gardefreinetescalier') . '" title="' . t('IMAGE_ALT_gardefreinetescalier') . '">
</div>
';

// Menu bas centre (menu UI)
$blocks['EditRegion4'] = '

';

$blocks['EditRegion5'] = ''; // Petit bloc gauche
$blocks['EditRegion6'] = ''; // Informations pratiques à compléter plus tard
$blocks['EditRegion7'] = '';
$blocks['EditRegion7'] = '
<div>
    <h3>' . t("TITRE_ASAVOIR_GF") . '</h3>
    <p>' . t("CONTENU_ASAVOIR_GF") . '</p>
</div>


';

$blocks['EditRegion11'] = '
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectanglegris"><a href="/site/bouger/se-promener-a-Cogolin-golfe-de-sttropez.php">' . t('MENU_LESVILLAGESCOGOLIN') . '<img src="/assets/images/structure/menu/bouger/uicogolin.jpg" alt="' . t('MENU_LESVILLAGESCOGOLINALT') . '" title="' . t('MENU_LESVILLAGESCOGOLIN') . '"></a></div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectanglebleufonce"><a href="/site/bouger/se-promener-a-Ramatuelle-golfe-de-sttropez.php">' . t('MENU_LESVILLAGESRAMATUELLE') . '<img src="/assets/images/structure/menu/bouger/uiramatuelle.jpg" alt="' . t('MENU_LESVILLAGESRAMATUELLEALT') . '" title="' . t('MENU_LESVILLAGESRAMATUELLE') . '"></a></div>
    </div>
    <div id="menurectanglewindows">
        <div id="boutonrectanglenoir"><a href="/site/bouger/se-promener-a-sttropez.php">' . t('MENU_LESVILLAGESSTTROPEZ') . '<img src="/assets/images/structure/menu/bouger/uisttropez.jpg" alt="' . t('MENU_LESVILLAGESSTTROPEZALT') . '" title="' . t('MENU_LESVILLAGESSTTROPEZ') . '"></a></div>
    </div>
</div>
'; // Avant menu bas
