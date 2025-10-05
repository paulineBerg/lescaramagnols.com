<?php
// templates/pages/site/auto-retro/panhard/histoire-de-panhard.php

$blocks = [];

$blocks['EditRegion10'] = ''; // META HEAD vide

$blocks['EditRegion1'] = '
    <h1>' . t("TXT_TITREPANHARD") . '</h1>
';

$blocks['EditRegion2'] = '
    <div id="blocHaut" class="border">' . t("TXT_PANHARDINTRO") . '</div>
';

$blocks['EditRegion8'] = '
    <div id="bloccenter">
        <img src="/assets/images/autoretro/panhard/affiche_panhard.jpg"
             title="affiche panhard"
             alt="affiche panhard">
    </div>
';

$blocks['EditRegion3'] = t("TXT_PANHARDDESCRIPT");

$blocks['EditRegion4'] = '
    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglerouge">
                <a href="/site/auto-retro/simca/histoire-de-simca.php">' . t("MENU_UI_SIMCA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uisimca.jpg"
                         alt="' . t("MENU_UI_SIMCA") . '"
                         title="' . t("MENU_UI_SIMCA") . '">
                </a>
            </div>					
        </div>	
        <div id="menurectanglewindows">
            <div id="boutonrectangleorange">
                <a href="/site/auto-retro/panhard/une-dyna-icone-automobile">' . t("MENU_UI_HISTOIREDYNA") . '
                    <img src="/assets/images/structure/menu/auto-retro/uipanhard.jpg"
                         alt="' . t("MENU_UI_HISTOIREDYNA") . '"
                         title="' . t("MENU_UI_HISTOIREDYNA") . '">
                </a>
            </div>					
        </div>	
    </div>

    <div id="bloccenter">
        <div id="menurectanglewindows">
            <div id="boutonrectanglevertfonce">
                <a href="/site/auto-retro/austin/histoire-de-austin.php">' . t("MENU_UI_AUSTIN") . '
                    <img src="/assets/images/structure/menu/auto-retro/uiaustin.jpg"
                         alt="' . t("MENU_UI_AUSTIN") . '"
                         title="' . t("MENU_UI_AUSTIN") . '">
                </a>
            </div>					
        </div>	
        <div id="menurectanglewindows">
            <div id="boutonrectanglebleuturquoise">
                <a href="/site/auto-retro/renault/histoire-de-renault.php">' . t("MENU_UI_RENAULT") . '
                    <img src="/assets/images/structure/menu/auto-retro/uirenault.jpg"
                         alt="' . t("MENU_UI_RENAULT") . '"
                         title="' . t("MENU_UI_RENAULT") . '">
                </a>
            </div>					
        </div>	
    </div>
';

$blocks['EditRegion5'] = '';
$blocks['EditRegion6'] = '';
$blocks['EditRegion7'] = '';
$blocks['EditRegion11'] = '<!-- AVANT MENU BAS : ' . t("TXT_AVANTMENUBAS") . ' -->';

