<?php
//templates/pages/site/bouger/se-promener-a-Cogolin-golfe-de-sttropez.php

// Initialisation des blocs
$blocks = [];

$blocks['EditRegion1'] = '<h1>' . t("TXT_TITRECOGOLIN") . '</h1>';

$blocks['EditRegion2'] = '
<div id="blocHaut" class="border">' . t('TXT_COGOLININTRO') . '</div>
';
$blocks['EditRegion8'] = '
';

$blocks['EditRegion3'] = '
<div class="img">
<img src="/assets/images/bouger/communes/cogolin/mairie_de_cogolin.jpg" alt="' . t("IMAGE_ALT_cogolinmairie") . '" title="' . t("IMAGE_ALT_cogolinmairie") . '">
<img src="/assets/images/bouger/communes/cogolin/facade_en_pierre_a_cogolin.jpg" alt="' . t("IMAGE_ALT_cogolinfacade") . '" title="' . t("IMAGE_ALT_cogolinfacade") . '">
</div>
<div class="img">
<img src="/assets/images/bouger/communes/cogolin/place_des_boules_cogolin.jpg" alt="' . t("IMAGE_ALT_cogolinboule") . '" title="' . t("IMAGE_ALT_cogolinboule") . '">
<img src="/assets/images/bouger/communes/cogolin/plage_a_cogolin.jpg" alt="' . t("IMAGE_ALT_cogolinplage") . '" title="' . t("IMAGE_ALT_cogolinplage") . '">
</div>

<p>' . t("TXT_COGOLINCENTRE") . '</p>
<img class="flottegauche" src="/assets/images/bouger/communes/cogolin/fontaine_a_cogolin.jpg" alt="' . t("IMAGE_ALT_cogolinfontaine") . '" title="' . t("IMAGE_ALT_cogolinfontaine") . '">
<img class="flottedroite" src="/assets/images/bouger/communes/cogolin/un_coq_a_cogolin.jpg" alt="' . t("IMAGE_ALT_cogolincroix") . '" title="' . t("IMAGE_ALT_cogolincroix") . '">

<p>' . t("TXT_COGOLINCONCLUSION") . '</p>
<div class="img">
<img src="/assets/images/bouger/communes/cogolin/vue_de_cogolin.jpg" alt="' . t("IMAGE_ALT_cogolinvue") . '" title="' . t("IMAGE_ALT_cogolinvue") . '">
</div>

';

$blocks['EditRegion4'] = '
<div id="bloccenter">
    <div id="menurectanglewindows">
        <div id="boutonrectangleblanc"><a href="/site/bouger/se-promener-a-La-Garde-Freinet-golfe-de-sttropez.php">' . t("MENU_LESVILLAGESGARDEFREINET") . '<img src="/assets/images/structure/menu/bouger/uigardefreinet.jpg" alt="' . t("MENU_LESVILLAGESGARDEFREINETALT") . '" title="' . t("MENU_LESVILLAGESGARDEFREINET") . '"></a></div>					
    </div>	
    <div id="menurectanglewindows">
        <div id="boutonrectanglebleufonce"><a href="/site/bouger/se-promener-a-Ramatuelle-golfe-de-sttropez.php">' . t("MENU_LESVILLAGESRAMATUELLE") . '<img src="/assets/images/structure/menu/bouger/uiramatuelle.jpg" alt="' . t("MENU_LESVILLAGESRAMATUELLEALT") . '" title="' . t("MENU_LESVILLAGESRAMATUELLE") . '"></a></div>					
    </div>	
    <div id="menurectanglewindows">
        <div id="boutonrectanglenoir"><a href="/site/bouger/se-promener-a-sttropez.php">' . t("MENU_LESVILLAGESSTTROPEZ") . '<img src="/assets/images/structure/menu/bouger/uisttropez.jpg" alt="' . t("MENU_LESVILLAGESSTTROPEZALT") . '" title="' . t("MENU_LESVILLAGESSTTROPEZ") . '"></a></div>					
    </div>	
</div>
';

$blocks['EditRegion5'] = ''; 

$blocks['EditRegion6'] = ''; 

$blocks['EditRegion7'] = '
<div class="blocInfosPratiques">
    <h3>' . t("TITRE_INFOSPRATIQUES") . '</h3>
    <ul>
        <li><strong>' . t("MARCHE_MERCREDI") . '</strong><br>' . t("MARCHE_MERCREDI_INFOS") . '</li>
        <li><strong>' . t("MARCHE_SAMEDI") . '</strong><br>' . t("MARCHE_SAMEDI_INFOS") . '</li>
        <li><strong>' . t("BROCANTE_JEUDI") . '</strong><br>' . t("BROCANTE_JEUDI_INFOS") . '</li>
        <li><strong>' . t("BROCANTE_DIMANCHE") . '</strong><br>' . t("BROCANTE_DIMANCHE_INFOS") . '</li>
    </ul>
    <p class="note">' . t("INFOS_MARCHE_NOTE") . '</p>
</div>
';

$blocks['EditRegion11'] = ''; // Rien à afficher ici pour l’instant
