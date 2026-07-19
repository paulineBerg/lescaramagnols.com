<?php

// Fichier : backend/lang/de/de_autoretro.php
// Retourne un tableau associatif avec les clés de traduction et leurs valeurs en français.

return array_merge(
    require __DIR__ . '/autoretro/de_austin.php',
    require __DIR__ . '/autoretro/de_mercedes.php',
    require __DIR__ . '/autoretro/de_panhard.php',
    require __DIR__ . '/autoretro/de_renault.php',
    require __DIR__ . '/autoretro/de_simca.php',

    // --- Clés locales (SAVA, etc.) ---
    [
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

        // UI-Buttons
        'MENU_INSTA_HISTOIRESDEVIEILLES' => 'Auf Instagram ansehen',
        'MENU_FACE_HISTOIRESDEVIEILLES'  => 'Auf Facebook ansehen',
        'MENU_SAVASITE'                      => 'SAVA-Website',

        // Texte supplémentaire facultatif
        'TXT_AVANTMENUBAS'        => 'Vielen Dank für Ihren Besuch',

        //--------------------------------------------------------
        // page SAVA
        //-------------------------------------------------------
        'TITRE_SAVA' => 'SAVA – Ihr Automobilpartner in Rioz (70190)',
        'INTRO_SAVA' => 'Ein Unternehmen, das sich mit Leidenschaft und Präzision weiterentwickelt',

        'SAVA_2023_TITRE' => '2023 – Eine mobile Werkstatt für den täglichen Service',
        'SAVA_2023_PAR1' => 'Gegründet von Julien Perrot, einem qualifizierten und leidenschaftlichen Automechaniker, startet SAVA seine Tätigkeit mit einem innovativen Konzept: einer mobilen Garage.',
        'SAVA_2023_PAR2' => 'Sein Lieferwagen ist komplett als professionelle Werkstatt ausgestattet, mit Kompressor, Werkzeugkasten, Elektrowerkzeugen und Diagnosegeräten.',
        'SAVA_2023_PAR3' => 'Julien führt routinemäßige Wartungen, kleinere Reparaturen sowie komplexere mechanische Arbeiten direkt bei Privatpersonen oder an professionellen Standorten durch.',

        'SAVA_2024_TITRE' => '2024 – Polsterei und Oldtimer-Restauration',
        'SAVA_2024_PAR1' => 'Mit dem Einstieg von Laetitia Leperchey, die "Feenhände und -finger" hat und seine Partnerin im Leben ist, erweitert SAVA sein Angebot auf die Renovierung von Auto-Innenräumen.',
        'SAVA_2024_PAR2' => 'Das Duo setzt nun sein Know-how für alte oder Sammlerfahrzeuge ein, die sie unter Beachtung der Authentizität restaurieren: Mechanik, leichte Karosseriearbeiten und Polsterei.',

        'SAVA_2025_TITRE' => '2025 – Neue Etappe: eine feste Werkstatt in Rioz',
        'SAVA_2025_PAR1' => 'Aufgrund der steigenden Nachfrage wurde beschlossen, den Service vor Ort einzustellen.',
        'SAVA_2025_PAR2' => 'Die Hauptaktivität wird in eine feste Werkstatt verlagert, die sich befindet:',
        'SAVA_2025_ADRESSE' => 'Garage SAVA, Chem. du Bois du Chaillaux, 70190 Rioz',
        'SAVA_2025_PAR3' => 'Diese Werkstatt ermöglicht es, Fahrzeuge für umfangreiche Restaurationen aufzunehmen: Motor, Bremsen, Fahrwerk, Polsterung, ästhetische Instandsetzung.',
        'SAVA_2025_PAR4' => 'Diese Veränderung bietet einen besseren Arbeitskomfort, mehr Sicherheit und ermöglicht eine qualitativ hochwertige Betreuung von Langzeitprojekten.',

        'SAVA_RESEAUX_TITRE' => 'Entdecken Sie ihre Projekte',
        'SAVA_RESEAUX_PAR' => 'Sie können ihre Restaurierungsprojekte auf Instagram und Facebook verfolgen:',
        'SAVA_RESEAUX_INSTAGRAM' => 'Instagram: @histoires_de_vieilles',
        'SAVA_RESEAUX_FACEBOOK' => 'Facebook: histoiresdevieilles',

        'ALT_IMAGE_SAVA' => 'Das SAVA-Logo',

        //===============================================================
        //===============================================================


        // page 2CV RESTAURATION
        //-------------------------------------------------------------
        'TXT_TITRE2CVRESTAURATION'      => 'Restauration eines Citroën 2CV',
        'IMAGE_ALT_2cvrestauration'     => '', // Wie im Original leer gelassen
        'TXT_2CVRESTAURATIONINTRO'      => '', // Wie im Original leer gelassen
        'TXT_2CVRESTAURATIONDESCRIPT'   => 'Im Aufbau, bitte kommen Sie bald wieder :)',

    ]
);