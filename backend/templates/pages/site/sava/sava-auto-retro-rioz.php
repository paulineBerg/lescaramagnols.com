<?php
//templates/pages/site/sava/sava-auto-retro-rioz.php

$blocks = [];

// === Titre principal
$blocks['EditRegion1'] = '<h1>' . t('TITRE_SAVA') . '</h1>';

// === Introduction
$blocks['EditRegion2'] = '<div id="blocHaut" class="border">' . t('INTRO_SAVA') . '</div>';

// === Logo SAVA
$blocks['EditRegion8'] = '
    <img src="/assets/images/autoretro/sava_logo.jpg"
         title="' . t('ALT_IMAGE_SAVA') . '"
         alt="' . t('ALT_IMAGE_SAVA') . '">
';

// === Contenu principal
$blocks['EditRegion3'] = '
    <h3>' . t('SAVA_2023_TITRE') . '</h3>
    <p>' . t('SAVA_2023_PAR1') . '</p>
    <p>' . t('SAVA_2023_PAR2') . '</p>
    <p>' . t('SAVA_2023_PAR3') . '</p>

    <h3>' . t('SAVA_2024_TITRE') . '</h3>
    <p>' . t('SAVA_2024_PAR1') . '</p>
    <p>' . t('SAVA_2024_PAR2') . '</p>

    <h3>' . t('SAVA_2025_TITRE') . '</h3>
    <p>' . t('SAVA_2025_PAR1') . '</p>
    <p>' . t('SAVA_2025_PAR2') . '</p>
    <p><strong>' . t('SAVA_2025_ADRESSE') . '</strong></p>
    <p>' . t('SAVA_2025_PAR3') . '</p>
    <p>' . t('SAVA_2025_PAR4') . '</p>

    <h2>' . t('SAVA_RESEAUX_TITRE') . '</h2>
    <p>' . t('SAVA_RESEAUX_PAR') . '</p>
    <h3>' . t('SAVA_RESEAUX_INSTAGRAM') . '</h3>
    <h3>' . t('SAVA_RESEAUX_FACEBOOK') . '</h3>
';

// === Boutons réseaux sociaux ===
$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="https://www.instagram.com/histoires_de_vieilles/" target="_blank">
                    ' . t('MENU_INSTA_HISTOIRESDEVIEILLES') . '
                    <img src="/assets/images/structure/menu/sava/uihistoiresdevieilles.jpg"
                         alt="' . t('MENU_INSTA_HISTOIRESDEVIEILLES') . '"
                         title="' . t('MENU_INSTA_HISTOIRESDEVIEILLES') . '">
                </a>
            </div>
        </div>

        <div id="menurectanglewindows">
            <div id="boutonrectanglebleuvert">
                <a href="https://www.facebook.com/histoiresdevieilles" target="_blank">
                    ' . t('MENU_FACE_HISTOIRESDEVIEILLES') . '
                    <img src="/assets/images/structure/menu/sava/uihistoiresdevieilles.jpg"
                         alt="' . t('MENU_FACE_HISTOIRESDEVIEILLES') . '"
                         title="' . t('MENU_FACE_HISTOIRESDEVIEILLES') . '">
                </a>
            </div>
        </div>

        <div id="menurectanglewindows">
            <div id="boutonrectanglejaune">
                <a href="https://www.sarl-sava.com" target="_blank">
                    ' . t('MENU_SAVASITE') . '
                    <img src="/assets/images/structure/menu/sava/uisava.jpg"
                         alt="' . t('MENU_SAVASITE') . '"
                         title="' . t('MENU_SAVASITE') . '">
                </a>
            </div>
        </div>
    </div>
';

// === Bloc bas gauche ===
$blocks['EditRegion5'] = '';
// === Bloc bas droite ===
$blocks['EditRegion6'] = '';
// === Bloc bas centre ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';
