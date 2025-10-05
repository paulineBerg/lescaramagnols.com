<?php
// Site LesCaramagnols
// /config/menu_data.php
// Retourne un tableau associatif avec : menu1 (réseaux), banniere, menu2 (navigation principale), menu3 (footer)
// ⚠️ Assurez-vous que la fonction t() est définie avant l'inclusion de ce fichier

return [

    // Flèche remonter (utilisée pour le bouton “Remonter en haut”)
    'remonter' => [
        'titre'  => t('REMONTER_TOP'),
        'alt'    => t('REMONTER_TITRE'),
        'title'  => t('REMONTER_TITRE'),
    ],

// Menu 1 : Réseaux sociaux
'menu1' => [
    [
        'url'   => 'https://www.facebook.com/lescaramagnols',
        'image' => '/assets/images/structure/reseaux-sociaux/facebooklogomini.jpg',
        'alt'   => 'La Page Facebook',
        'title' => 'La Page Facebook',
    ],
    [
        'url'   => 'https://www.flickr.com/photos/130851617@N05',
        'image' => '/assets/images/structure/reseaux-sociaux/flickrlogomini.jpg',
        'alt'   => 'La Page Flickr',
        'title' => 'La Page Flickr',
    ],
    [
        'url'   => 'https://instagram.com/paulineetnoel/',
        'image' => '/assets/images/structure/reseaux-sociaux/instagramlogomini.jpg',
        'alt'   => 'La Page Instagram',
        'title' => 'La Page Instagram',
    ],
    [
        'url'   => 'https://www.pinterest.fr/lescaramagnols/',
        'image' => '/assets/images/structure/reseaux-sociaux/pinterestlogomini.jpg',
        'alt'   => 'La Page Pinterest',
        'title' => 'La Page Pinterest',
    ],
    [
        'url'   => 'https://www.tumblr.com/lescaramagnols/',
        'image' => '/assets/images/structure/reseaux-sociaux/tumblrlogomini.jpg',
        'alt'   => 'La Page Tumblr',
        'title' => 'La Page Tumblr',
    ],
],


// Bannière
'banniere' => [
    'image'      => '/assets/images/structure/banniere.jpg',
    'texte_key'  => t('TXT_BANNIERE'),
    'alt'        => t('TXT_BANNIERE'),
    'title'      => t('TXT_BANNIERE'),
],


// Menu 2 : Navigation principale
'menu2' => [
    [
        'titre'  => t('MENU_ACCUEIL'),
        'chemin' => '/site/accueil/bienvenue-aux-caramagnols.php',
        'alt'    => t('MENU_ACCUEIL'),
        'title'  => t('MENU_ACCUEIL'),
    ],
    [
        'titre'     => t('MENU_AUTORETRO'),
        'chemin'    => '#',
        'alt'       => t('MENU_AUTORETRO'),
        'title'     => t('MENU_AUTORETRO'),
        'sous_menu' => [
            [
                'titre'     => t('MENU_austin'),
                'chemin'    => '#',
                'alt'       => t('MENU_austin'),
                'title'     => t('MENU_austin'),
                'sous_menu' => [
                    ['titre' => t('MENU_austin'),       'chemin' => '/site/auto-retro/austin/histoire-de-austin.php'],
                    ['titre' => t('MENU_histoiremini'), 'chemin' => '/site/auto-retro/austin/aventure-mini-austin.php'],
                    ['titre' => t('MENU_mini'),         'chemin' => '/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php'],
                ],
            ],
            [
                'titre'     => t('MENU_mercedes'),
                'chemin'    => '#',
                'alt'       => t('MENU_mercedes'),
                'title'     => t('MENU_mercedes'),
                'sous_menu' => [
                    ['titre' => t('MENU_mercedes'),    'chemin' => '/site/auto-retro/mercedes/histoire-de-mercedes.php'],
                    ['titre' => t('MENU_histoireslk'), 'chemin' => '/site/auto-retro/mercedes/la-slk-une-voiture-compacte-et-rapide.php'],
                    ['titre' => t('MENU_slk'),         'chemin' => '/site/auto-retro/mercedes/une-slk-dans-le-golfe-de-sttropez.php'],
                ],
            ],   
            [
                'titre'     => t('MENU_panhard'),
                'chemin'    => '#',
                'alt'       => t('MENU_panhard'),
                'title'     => t('MENU_panhard'),
                'sous_menu' => [
                    ['titre' => t('MENU_panhard'),      'chemin' => '/site/auto-retro/panhard/histoire-de-panhard.php'],
                    ['titre' => t('MENU_histoiredyna'), 'chemin' => '/site/auto-retro/panhard/une-dyna-icone-automobile.php'],
                    ['titre' => t('MENU_histoiredynaz'), 'chemin' => '/site/auto-retro/panhard/la-dyna-z-voiture-de-collection.php'],
                    ['titre' => t('MENU_dyna'),         'chemin' => '/site/auto-retro/panhard/une-dynaz12-dans-le-golfe-de-sttropez.php'],
                ],
            ],
            [
                'titre'     => t('MENU_renault'),
                'chemin'    => '#',
                'alt'       => t('MENU_renault'),
                'title'     => t('MENU_renault'),
                'sous_menu' => [
                    ['titre' => t('MENU_renault'),        'chemin' => '/site/auto-retro/renault/histoire-de-renault.php'],
                    ['titre' => t('MENU_histoiretwingo'), 'chemin' => '/site/auto-retro/renault/la-twingo-une-voiture-a-succes.php'],
                    ['titre' => t('MENU_twingo'),         'chemin' => '/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php'],
                ],
            ],
            [
                'titre'     => t('MENU_simca'),
                'chemin'    => '#',
                'alt'       => t('MENU_simca'),
                'title'     => t('MENU_simca'),
                'sous_menu' => [
                    ['titre' => t('MENU_simca'),              'chemin' => '/site/auto-retro/simca/histoire-de-simca.php'],
                    ['titre' => t('MENU_histoirearonde'),     'chemin' => '/site/auto-retro/simca/histoire-simca-aronde-icone-francaise.php'],
                    ['titre' => t('MENU_histoirearonde9'),    'chemin' => '/site/auto-retro/simca/la-simca-9-aronde-voiture-de-collection.php'],
                    ['titre' => t('MENU_histoirearonde1300'), 'chemin' => '/site/auto-retro/simca/la-simca-aronde-1300-voiture-de-collection.php'],
                    ['titre' => t('MENU_histoirearondep60'),  'chemin' => '/site/auto-retro/simca/la-simca-P60-voiture-de-collection.php'],
                    ['titre' => t('MENU_aronde'),             'chemin' => '/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php'],
                    ['titre' => t('MENU_aronderestauration'), 'chemin' => '/site/auto-retro/simca/une-simca-aronde-en-restauration-chez-sava-rioz.php'],
                ],
            ],
            ['titre' => t('MENU_STORY'), 'alt'  => t('MENU_STORY'), 'title' => t('MENU_STORY'), 'chemin' => ''],
        ],
    ],
    [
        'titre'     => t('MENU_BOUGER'),
        'chemin'    => '#',
        'alt'       => t('MENU_BOUGER'),
        'title'     => t('MENU_BOUGER'),
        'sous_menu' => [
            ['titre' => t('MENU_legolfe'), 'chemin' => '/site/bouger/se-promener-dans-le-golfe-de-sttropez.php'],
            [
                'titre'     => t('MENU_lesvillages'),
                'chemin'    => '#',
                'alt'       => t('MENU_lesvillages'),
                'title'     => t('MENU_lesvillages'),
                'sous_menu' => [
                    ['titre' => 'Cogolin',         'chemin' => '/site/bouger/se-promener-a-Cogolin-golfe-de-sttropez.php'],
                    ['titre' => 'La Garde-Freinet','chemin' => '/site/bouger/se-promener-a-La-Garde-Freinet-golfe-de-sttropez.php'],
                    ['titre' => 'Ramatuelle',      'chemin' => '/site/bouger/se-promener-a-Ramatuelle-golfe-de-sttropez.php'],
                    ['titre' => 'St-Tropez',       'chemin' => '/site/bouger/se-promener-a-sttropez.php'],
                ],
            ],
            ['titre' => t('MENU_lesanimations'), 'chemin' => '/site/bouger/les-animations-dans-le-golfe-de-sttropez.php'],
        ],
    ],
    [
        'titre'     => t('MENU_COMMUNIQUER'),
        'chemin'    => '#',
        'alt'       => t('MENU_COMMUNIQUER'),
        'title'     => t('MENU_COMMUNIQUER'),
        'sous_menu' => [
            ['titre' => t('MENU_etreami'),   'chemin' => '/site/communiquer/les-reseaux-sociaux-de-paulineetnoel-facebook-twitter-google-pinterest-instagram-flickr.php'],
            ['titre' => t('MENU_nosnews'),   'chemin' => '/site/communiquer/les-news-de-paulineetnoel.php'],
            ['titre' => t('MENU_vosphotos'), 'chemin' => '/site/communiquer/vos-photos-vacances-les-caramagnols.php'],
        ],
    ],
    [
        'titre'  => t('MENU_SAVA'),
        'chemin' => '/site/sava/sava-auto-retro-rioz.php',
        'alt'    => t('MENU_SAVA'),
        'title'  => t('MENU_SAVA'),
    ],
    [
        'titre'  => t('MENU_BIJOUX'),
        'chemin' => '/site/bc/boulyetcailloux-des-bijoux-artisanaux.php',
        'alt'    => t('MENU_BIJOUX'),
        'title'  => t('MENU_BIJOUX'),
    ],
],

// Menu 3 : Navigation principale du pied de page
// Utilisé pour les mentions légales et le plan du site
// Note: Les titres sont traduits avec la fonction t()
'menu3' => [
    [
        'titre'  => t('MENU_mentions'),
        'chemin' => '/site/accueil/toutes-les-mentions-legales.php',
        'alt'    => t('MENU_mentions'),
        'title'  => t('MENU_mentions'),
    ],
    [
        'titre'  => t('MENU_plan'),
        'chemin' => '/site/accueil/le-plan-du-site-des-caramagnols.php',
        'alt'    => t('MENU_plan'),
        'title'  => t('MENU_plan'),
    ],
],

// Menu fixe droite
'menu_droit' => [
    [
        'chemin'     => '/site/auto-retro/simca/histoire-de-simca.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btsimca.jpg',
        'alt'        => t('MENU_simca'),
        'title'      => t('MENU_simca'),
        'titre'      => t('MENU_simca'),
    ],
    [
        'chemin'     => '/site/auto-retro/panhard/histoire-de-panhard.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btpanhard.jpg',
        'alt'        => t('MENU_panhard'),
        'title'      => t('MENU_panhard'),
        'titre    '  => t('MENU_panhard'),
    ],
    [
        'chemin'     => '/site/auto-retro/austin/histoire-de-austin.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btaustin.jpg',
        'alt'        => t('MENU_austin'),
        'title'      => t('MENU_austin'),
        'titre'      => t('MENU_austin'),
    ],
    [
        'chemin'     => '/site/auto-retro/renault/histoire-de-renault.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btrenault.jpg',
        'alt'        => t('MENU_renault'),
        'title'      => t('MENU_renault'),
        'titre'      => t('MENU_renault'),
    ],
],

// Menu fixe gauche
'menu_gauche' => [
    [
        'chemin'     => '/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btsimca.jpg',
        'alt'        => t('MENU_aronde'),
        'title'      => t('MENU_aronde'),
        'titre'      => t('MENU_aronde'),
    ],
    [
        'chemin'     => '/site/auto-retro/panhard/une-dyna-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btpanhard.jpg',
        'alt'        => t('MENU_dyna'),
        'title'      => t('MENU_dyna'),
        'titre'      => t('MENU_dyna'),
    ],
    [
        'chemin'     => '/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btaustin.jpg',
        'alt'        => t('MENU_mini'),
        'title'      => t('MENU_mini'),
        'titre'      => t('MENU_mini'),
    ],
    [
        'chemin'     => '/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btrenault.jpg',
        'alt'        => t('MENU_twingo'),
        'title'      => t('MENU_twingo'),
        'titre'      => t('MENU_twingo'),
    ],
],

    
];
// Fin du fichier config/menu_data.php