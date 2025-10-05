<?php
// templates/pages/bienvenue-aux-caramagnols.php

// Initialisation des blocs (évite les erreurs si appelés)
$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = '';

// === Contenu visuel principal (image + titres accueil) ===
$blocks['EditRegion1'] = '
<span class="ImgLargeurContenu">
    <img src="/assets/images/bouger/golfe/montage.webp" title="' . t('IMAGE_ALT_montage') . '" alt="' . t('IMAGE_ALT_montage') . '">
</span>

    <h1>' . t('TXT_BIENVENUE') . '</h1>
    <br><h2>' . t('TXT_TITREINDEX') . '</h2>

';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '
<div class="border" id="blocHaut">
    <span class="flottedroite">
        <img src="/assets/images/accueil/raisin.jpg" alt="' . t('IMAGE_ALT_dynaz12') . '" width="150" height="77" title="' . t('IMAGE_ALT_dynaz12') . '">
    </span> 
    ' . t('TXT_INDEXINTRO') . '
</div>
';

// === ColonneJustifie25 vide ici, zone dédiée à TXT_INTRO (non présent dans l'exemple) ===
$blocks['EditRegion8'] = ''; // Prévu pour future intro additionnelle

// === Bloc centre principal : texte d'accueil principal ===
$blocks['EditRegion3'] = t('TXT_INDEX');

// === Bloc bas centre (vide dans l'exemple) ===
$blocks['EditRegion4'] = '';

// === Bloc bas gauche : image Aronde ===
$blocks['EditRegion5'] = '
<img src="/assets/images/accueil/aronde.jpg" alt="' . t('IMAGE_ALT_indexaronde') . '" title="' . t('IMAGE_ALT_indexaronde') . '">
';

// === Bloc bas droite : image Mini ===
$blocks['EditRegion6'] = '
<img src="/assets/images/accueil/mini.jpg" alt="' . t('IMAGE_ALT_indexmini') . '" title="' . t('IMAGE_ALT_indexmini') . '">
';

// === Bloc bas centre : fin texte ===
$blocks['EditRegion7'] = t('TXT_FININDEX');

// === Bloc juste avant menu bas 
$blocks['EditRegion11'] = ''; // Zone réservée

// === Pied de page : 
$blocks['EditRegion9'] = ''; // Zone réservée

// === Région modifiable en bas de page :
$blocks['EditRegion12'] = '';
