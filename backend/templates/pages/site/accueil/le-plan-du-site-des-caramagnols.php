<?php
// template: templates/pages/site/accueil/le-plan-du-site-des-caramagnols.php

// Initialisation des blocs (évite les erreurs si appelés dans le layout)
$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = '';

// === Haut : image + titres ===
$blocks['EditRegion1'] = '';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '<div id="blocHaut" class="border">' . t('TXT_SOMMAIREINTRO') . '</div>';

// === Prévu pour future intro additionnelle ===
$blocks['EditRegion8'] = '';

// === Bloc centre principal : titre et plan du site (à adapter selon affichage souhaité) ===
ob_start();
include ROOT_PATH . '/templates/partials/sitemap.php';
$blocks['EditRegion3'] = ob_get_clean();

// === Bloc bas centre ===
$blocks['EditRegion4'] = '';

// === Bloc bas gauche ===
$blocks['EditRegion5'] = '';

// === Bloc bas droite ===
$blocks['EditRegion6'] = '';

// === Bloc bas centre : fin texte ===
$blocks['EditRegion7'] = t('TXT_SOMMAIRECONCLUSION');

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';

// === Pied de page : zone réservée ===
$blocks['EditRegion9'] = '';

// === Bas de page : scripts/SEO/etc. ===
$blocks['EditRegion12'] = '';
