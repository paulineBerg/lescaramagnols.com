<?php
//templates/pages/404.php

// Initialisation des blocs (évite les erreurs si appelés dans le layout)
$blocks = [];

$blocks['EditRegion1'] =
'<h1>' . t("TXT_TITREERREUR") . '</h1>
';
$blocks['EditRegion2'] = ' ';

// Contenu principal de la page
$blocks['EditRegion3'] =' 
 <div>' . t("TXT_ERREUR") . '</div>
 '; 
$blocks['EditRegion4'] = ' 
 <div id="bloc-haut" class="border">' . t('TXT_FINERREUR') . '</div>
 ';

// === Bloc bas gauche ===
$blocks['EditRegion5'] = '';
// === Bloc bas droite ===
$blocks['EditRegion6'] = '';
// === Bloc bas centre ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';
