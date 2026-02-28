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
                'titre'     => t('MENU_AUSTIN'),
                'chemin'    => '#',
                'alt'       => t('MENU_AUSTIN'),
                'title'     => t('MENU_AUSTIN'),
                'sous_menu' => [
                    ['titre' => t('MENU_AUSTIN'),       'chemin' => '/site/auto-retro/austin/histoire-de-austin.php'],
                    ['titre' => t('MENU_HISTOIREMINI'), 'chemin' => '/site/auto-retro/austin/aventure-mini-austin.php'],
                    ['titre' => t('MENU_MINI'),         'chemin' => '/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php'],
                ],
            ],
            [
                'titre'     => t('MENU_MERCEDES'),
                'chemin'    => '#',
                'alt'       => t('MENU_MERCEDES'),
                'title'     => t('MENU_MERCEDES'),
                'sous_menu' => [
                    ['titre' => t('MENU_MERCEDES'),    'chemin' => '/site/auto-retro/mercedes/histoire-de-mercedes.php'],
                    ['titre' => t('MENU_HISTOIRESLK'), 'chemin' => '/site/auto-retro/mercedes/la-slk-une-voiture-compacte-sportive.php'],
                    ['titre' => t('MENU_SLK'),         'chemin' => '/site/auto-retro/mercedes/une-slk-dans-le-golfe-de-sttropez.php'],
                ],
            ],   
            [
                'titre'     => t('MENU_PANHARD'),
                'chemin'    => '#',
                'alt'       => t('MENU_PANHARD'),
                'title'     => t('MENU_PANHARD'),
                'sous_menu' => [
                    ['titre' => t('MENU_PANHARD'),      'chemin' => '/site/auto-retro/panhard/histoire-de-panhard.php'],
                    ['titre' => t('MENU_HISTOIREDYNA'), 'chemin' => '/site/auto-retro/panhard/une-dyna-icone-automobile.php'],
                    ['titre' => t('MENU_HISTOIREDYNAZ'), 'chemin' => '/site/auto-retro/panhard/la-dyna-z-voiture-de-collection.php'],
                    ['titre' => t('MENU_DYNA'),         'chemin' => '/site/auto-retro/panhard/une-dynaz12-dans-le-golfe-de-sttropez.php'],
                ],
            ],
            [
                'titre'     => t('MENU_RENAULT'),
                'chemin'    => '#',
                'alt'       => t('MENU_RENAULT'),
                'title'     => t('MENU_RENAULT'),
                'sous_menu' => [
                    ['titre' => t('MENU_RENAULT'),        'chemin' => '/site/auto-retro/renault/histoire-de-renault.php'],
                    ['titre' => t('MENU_HISTOIRETWINGO'), 'chemin' => '/site/auto-retro/renault/la-twingo-une-voiture-a-succes.php'],
                    ['titre' => t('MENU_TWINGO'),         'chemin' => '/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php'],
                ],
            ],
            [
                'titre'     => t('MENU_SIMCA'),
                'chemin'    => '#',
                'alt'       => t('MENU_SIMCA'),
                'title'     => t('MENU_SIMCA'),
                'sous_menu' => [
                    ['titre' => t('MENU_SIMCA'),              'chemin' => '/site/auto-retro/simca/histoire-de-simca.php'],
                    ['titre' => t('MENU_HISTOIREARONDE'),     'chemin' => '/site/auto-retro/simca/histoire-simca-aronde-icone-francaise.php'],
                    ['titre' => t('MENU_HISTOIREARONDE9'),    'chemin' => '/site/auto-retro/simca/la-simca-9-aronde-voiture-de-collection.php'],
                    ['titre' => t('MENU_HISTOIREARONDE1300'), 'chemin' => '/site/auto-retro/simca/la-simca-aronde-1300-voiture-de-collection.php'],
                    ['titre' => t('MENU_HISTOIREARONDEp60'),  'chemin' => '/site/auto-retro/simca/la-simca-P60-voiture-de-collection.php'],
                    ['titre' => t('MENU_ARONDE'),             'chemin' => '/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php'],
                    ['titre' => t('MENU_ARONDERESTAURATION'), 'chemin' => '/site/auto-retro/simca/une-simca-aronde-en-restauration-chez-sava-rioz.php'],
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
            ['titre' => t('MENU_LEGOLFE'), 'chemin' => '/site/bouger/se-promener-dans-le-golfe-de-sttropez.php'],
            [
                'titre'     => t('MENU_LESVILLAGES'),
                'chemin'    => '#',
                'alt'       => t('MENU_LESVILLAGES'),
                'title'     => t('MENU_LESVILLAGES'),
                'sous_menu' => [
                    ['titre' => 'Cogolin',         'chemin' => '/site/bouger/se-promener-a-Cogolin-golfe-de-sttropez.php'],
                    ['titre' => 'La Garde-Freinet','chemin' => '/site/bouger/se-promener-a-La-Garde-Freinet-golfe-de-sttropez.php'],
                    ['titre' => 'Ramatuelle',      'chemin' => '/site/bouger/se-promener-a-Ramatuelle-golfe-de-sttropez.php'],
                    ['titre' => 'St-Tropez',       'chemin' => '/site/bouger/se-promener-a-sttropez.php'],
                ],
            ],
            ['titre' => t('MENU_ANIMATIONS'), 'chemin' => '/site/bouger/les-animations-dans-le-golfe-de-sttropez.php'],
        ],
    ],
    [
        'titre'     => t('MENU_COMMUNIQUER'),
        'chemin'    => '#',
        'alt'       => t('MENU_COMMUNIQUER'),
        'title'     => t('MENU_COMMUNIQUER'),
        'sous_menu' => [
            ['titre' => t('MENU_ETREAMI'),   'chemin' => '/site/communiquer/les-reseaux-sociaux-de-paulineetnoel-facebook-twitter-google-pinterest-instagram-flickr.php'],
            ['titre' => t('MENU_NOSNEWS'),   'chemin' => '/site/communiquer/les-news-de-paulineetnoel.php'],
            ['titre' => t('MENU_VOSPHOTOS'), 'chemin' => '/site/communiquer/vos-photos-vacances-les-caramagnols.php'],
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
        'titre'  => t('MENU_MENTIONS'),
        'chemin' => '/site/accueil/toutes-les-mentions-legales.php',
        'alt'    => t('MENU_MENTIONS'),
        'title'  => t('MENU_MENTIONS'),
    ],
    [
        'titre'  => t('MENU_PLAN'),
        'chemin' => '/site/accueil/le-plan-du-site-des-caramagnols.php',
        'alt'    => t('MENU_PLAN'),
        'title'  => t('MENU_PLAN'),
    ],
],

// Menu fixe droite
'menu_droit' => [
    [
        'chemin'     => '/site/auto-retro/austin/histoire-de-austin.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btaustin.jpg',
        'alt'        => t('MENU_AUSTIN'),
        'title'      => t('MENU_AUSTIN'),
        'titre'      => t('MENU_AUSTIN'),
    ],    
  
    [
        'chemin'     => '/site/auto-retro/mercedes/histoire-de-mercedes.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btmercedes.jpg',
        'alt'        => t('MENU_MERCEDES'),
        'title'      => t('MENU_MERCEDES'),
        'titre'      => t('MENU_MERCEDES'),
    ],  
    [
        'chemin'     => '/site/auto-retro/panhard/histoire-de-panhard.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btpanhard.jpg',
        'alt'        => t('MENU_PANHARD'),
        'title'      => t('MENU_PANHARD'),
        'titre    '  => t('MENU_PANHARD'),
    ],        
    [
        'chemin'     => '/site/auto-retro/renault/histoire-de-renault.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btrenault.jpg',
        'alt'        => t('MENU_RENAULT'),
        'title'      => t('MENU_RENAULT'),
        'titre'      => t('MENU_RENAULT'),
    ],    
    [
        'chemin'     => '/site/auto-retro/simca/histoire-de-simca.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btsimca.jpg',
        'alt'        => t('MENU_SIMCA'),
        'title'      => t('MENU_SIMCA'),
        'titre'      => t('MENU_SIMCA'),
    ],

],

// Menu fixe gauche
'menu_gauche' => [
    [
        'chemin'     => '/site/auto-retro/austin/une-mini-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btaustin.jpg',
        'alt'        => t('MENU_MINI'),
        'title'      => t('MENU_MINI'),
        'titre'      => t('MENU_MINI'),
    ],
    [
        'chemin'     => '/site/auto-retro/mercedes/une-slk-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btmercedes.jpg',
        'alt'        => t('MENU_SLK'),
        'title'      => t('MENU_SLK'),
        'titre'      => t('MENU_SLK'),
    ],    
    [
        'chemin'     => '/site/auto-retro/panhard/une-dyna-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btpanhard.jpg',
        'alt'        => t('MENU_DYNA'),
        'title'      => t('MENU_DYNA'),
        'titre'      => t('MENU_DYNA'),
    ], 
    [
        'chemin'     => '/site/auto-retro/renault/une-twingo-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btrenault.jpg',
        'alt'        => t('MENU_TWINGO'),
        'title'      => t('MENU_TWINGO'),
        'titre'      => t('MENU_TWINGO'),
    ],       
    [
        'chemin'     => '/site/auto-retro/simca/une-aronde-dans-le-golfe-de-sttropez.php',
        'image'      => '/assets/images/structure/menu/auto-retro/btsimca.jpg',
        'alt'        => t('MENU_ARONDE'),
        'title'      => t('MENU_ARONDE'),
        'titre'      => t('MENU_ARONDE'),
    ],



],

    
];
// Fin du fichier config/menu_data.php