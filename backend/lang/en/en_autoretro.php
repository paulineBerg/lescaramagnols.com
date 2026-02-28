<?php
// file:backend/lang/en/en_autoretro.php
// Retourne un tableau associatif avec les clés de traduction et leurs valeurs en français.

return array_merge(
    require __DIR__ . '/autoretro/en_austin.php',
    require __DIR__ . '/autoretro/en_mercedes.php',
    require __DIR__ . '/autoretro/en_panhard.php',
    require __DIR__ . '/autoretro/en_renault.php',
    require __DIR__ . '/autoretro/en_simca.php'
);

return [
    //---------------------------------------------------------
    // AIDE : Les caractères spéciaux français (à, ê, ç) peuvent être directement utilisés.
    // Pour les apostrophes dans le texte, utilisez-les normalement : 'l'Aronde' est correct.
    //---------------------------------------------------------

    //-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
    // DOSSIER AUTORETRO
    //---------------------------------------------------------

//===============================================================
//===============================================================
// page SAVA
//-------------------------------------------------------------

// Boutons UI
'MENU_INSTA_HISTOIRESDEVIEILLES' => 'View on Instagram',
'MENU_FACE_HISTOIRESDEVIEILLES'  => 'View on Facebook',
'MENU_SAVASITE'                      => 'SAVA Website',

// Texte supplémentaire facultatif
'TXT_AVANTMENUBAS'        => 'Thank you for your visit',

//--------------------------------------------------------
// page SAVA
//-------------------------------------------------------
'TITRE_SAVA' => 'SAVA – Your automotive partner in Rioz (70190)',
'INTRO_SAVA' => 'A company that evolves with passion and precision',

'SAVA_2023_TITRE' => '2023 – A mobile workshop for daily service',
'SAVA_2023_PAR1' => 'Created by Julien Perrot, a qualified and passionate automotive technician, SAVA starts its business with an innovative concept: a mobile garage.',
'SAVA_2023_PAR2' => 'His van is fully equipped as a professional workshop with a compressor, a tool trolley, power tools, and diagnostic equipment.',
'SAVA_2023_PAR3' => 'Julien performs routine maintenance, minor mechanical work, as well as more complex mechanical repairs directly at private homes or on professional sites.',

'SAVA_2024_TITRE' => '2024 – Upholstery and classic car restoration',
'SAVA_2024_PAR1' => 'With the arrival of Laetitia Leperchey, who has "fairy hands and fingers," and his life partner, SAVA expands its offer to include automotive interior renovation.',
'SAVA_2024_PAR2' => 'The duo now puts their expertise at the service of old or classic vehicles, which they restore with respect for authenticity: mechanics, light bodywork, and upholstery.',

'SAVA_2025_TITRE' => '2025 – New step: a fixed workshop in Rioz',
'SAVA_2025_PAR1' => 'Faced with increasing demand, the decision has been made to stop the home service.',
'SAVA_2025_PAR2' => 'The main part of the activity is transferred to a full workshop located at:',
'SAVA_2025_ADRESSE' => 'Garage SAVA, Chem. du Bois du Chaillaux, 70190 Rioz',
'SAVA_2025_PAR3' => 'This location allows them to welcome vehicles for in-depth restorations: engine, brakes, running gear, upholstery, and aesthetic refurbishment.',
'SAVA_2025_PAR4' => 'This change offers better working comfort, more safety, and allows for quality support on long-term projects.',

'SAVA_RESEAUX_TITRE' => 'Discover their work',
'SAVA_RESEAUX_PAR' => 'Find their restoration projects on Instagram and Facebook:',
'SAVA_RESEAUX_INSTAGRAM' => 'Instagram: @histoires_de_vieilles',
'SAVA_RESEAUX_FACEBOOK' => 'Facebook: histoiresdevieilles',

'ALT_IMAGE_SAVA' => 'The SAVA logo',

//===============================================================
//===============================================================


// page 2CV RESTAURATION
    //-------------------------------------------------------------

    'TXT_TITRE2CVRESTAURATION'      => 'Restoration of a Citroën 2cv',
    'IMAGE_ALT_2cvrestauration'     => '', // Keeping this empty as in your original
    'TXT_2CVRESTAURATIONINTRO'      => '', // Keeping this empty as in your original
    'TXT_2CVRESTAURATIONDESCRIPT'   => 'Under construction, come back soon :)',

];