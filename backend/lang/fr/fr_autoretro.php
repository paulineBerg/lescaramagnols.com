<?php
// Fichier: lang/fr/fr_autoretro.php
// Retourne un tableau associatif avec les clés de traduction et leurs valeurs en français.

return array_merge(
    require __DIR__ . '/autoretro/fr_austin.php',
    require __DIR__ . '/autoretro/fr_mercedes.php',
    require __DIR__ . '/autoretro/fr_panhard.php',
    require __DIR__ . '/autoretro/fr_renault.php',
    require __DIR__ . '/autoretro/fr_simca.php',

    // --- Clés locales (SAVA, etc.) ---
    [
        // Boutons UI
        'MENU_INSTA_HISTOIRESDEVIEILLES' => 'Voir sur Instagram',
        'MENU_FACE_HISTOIRESDEVIEILLES'  => 'Voir sur Facebook',
        'MENU_SAVASITE'                      => 'Site de SAVA',

        // Texte supplémentaire facultatif
        'TXT_AVANTMENUBAS' => 'Merci pour votre visite',

        // Page SAVA
        'TITRE_SAVA' => 'SAVA – Votre partenaire automobile à Rioz (70190)',
        'INTRO_SAVA' => 'Une entreprise qui évolue avec passion et précision',

        'SAVA_2023_TITRE' => '2023 – Un atelier mobile au service du quotidien',
        'SAVA_2023_PAR1' => 'Créé par Julien Perrot, technicien qualifié et passionné d’automobile, SAVA démarre son activité avec un concept innovant : un garage mobile.',
        'SAVA_2023_PAR2' => 'Son fourgon est entièrement aménagé comme un atelier professionnel avec compresseur, servante d’atelier, outillage électroportatif et de diagnostic.',
        'SAVA_2023_PAR3' => 'Julien réalise directement chez les particuliers ou sur site professionnel l’entretien courant, la petite mécanique, ainsi que des réparations mécaniques plus poussées.',

        'SAVA_2024_TITRE' => '2024 – Sellerie et restauration de collection',
        'SAVA_2024_PAR1' => 'Avec l’arrivée de Laetitia Leperchey "aux mains et doigts de fée", et son binôme dans la vie, SAVA élargit son offre à la rénovation d’intérieurs automobiles.',
        'SAVA_2024_PAR2' => 'Le duo met désormais son savoir-faire au service de véhicules anciens ou de collection, qu’ils restaurent avec respect de l’authenticité : mécanique, carrosserie légère et sellerie.',

        'SAVA_2025_TITRE' => '2025 – Nouvelle étape : un atelier fixe à Rioz',
        'SAVA_2025_PAR1' => 'Face à la demande croissante, la décision est de stopper le service à domicile .',
        'SAVA_2025_PAR2' => 'L’essentiel de l’activité est transféré dans un atelier complet situé à :',
        'SAVA_2025_ADRESSE' => 'Garage SAVA, Chem. du Bois du Chaillaux, 70190 Rioz',
        'SAVA_2025_PAR3' => 'Ce local permet d’accueillir les véhicules pour restaurations approfondies : moteur, freins, trains roulants, sellerie, remise en état esthétique.',
        'SAVA_2025_PAR4' => 'Ce changement offre un meilleur confort de travail, plus de sécurité, et permet un accompagnement de qualité sur les projets de long terme.',

        'SAVA_RESEAUX_TITRE' => 'Découvrez leurs réalisations',
        'SAVA_RESEAUX_PAR' => 'Retrouvez leurs projets de restauration sur Instagram et Facebook :',
        'SAVA_RESEAUX_INSTAGRAM' => 'Instagram : @histoires_de_vieilles',
        'SAVA_RESEAUX_FACEBOOK' => 'Facebook : histoiresdevieilles',

        'ALT_IMAGE_SAVA' => 'Le logo de SAVA',

        // Page 2CV RESTAURATION
        'TXT_TITRE2CVRESTAURATION'    => 'Restauration d\'une Citroën 2cv',
        'IMAGE_ALT_2cvrestauration'   => '',
        'TXT_2CVRESTAURATIONINTRO'    => '',
        'TXT_2CVRESTAURATIONDESCRIPT' => 'En construction, revenez bientôt :)',
    ]
);
