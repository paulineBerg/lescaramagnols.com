<?php
// templates/pages/site/bouger/se-promener-dans-le-golfe-de-sttropez.php

// Initialisation des blocs (évite les erreurs si appelés dans le layout)
$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = ''; 



$blocks['EditRegion1'] = '
<span><h1>' . t('TITREBOUGER') . '</h1></span>

<div id="diaporamaSurvol">
  <div class="diaporamaSurvolDeuximg1">
    <div class="diaporamaSurvolimg1"><a href="#" title="' . t('IMAGE_ALT_indexsttropez') . '" alt="' . t('IMAGE_ALT_indexsttropez') . '"></a>' . t('TXT_INDEXSTTROPEZ') . '</div>
    <div class="diaporamaSurvolimg2"><a href="#" title="' . t('IMAGE_ALT_indexnature') . '" alt="' . t('IMAGE_ALT_indexnature') . '"></a>' . t('TXT_INDEXNATURE') . '</div>
  </div>
  <div class="diaporamaSurvolDeuximg2">
    <div class="diaporamaSurvolimg3"><a href="#" title="' . t('IMAGE_ALT_indexanimations') . '" alt="' . t('IMAGE_ALT_indexanimations') . '"></a>' . t('TXT_INDEXANIMATIONS') . '</div>
    <div class="diaporamaSurvolimg4"><a href="#" title="' . t('IMAGE_ALT_indexplage') . '" alt="' . t('IMAGE_ALT_indexplage') . '"></a>' . t('TXT_INDEXPLAGE') . '</div>
  </div>
</div>

';

$blocks['EditRegion2'] = '
<div id="blocHaut" class="border">
  ' . t('TXT_GOLFEINTRO') . '
</div>
';

$blocks['EditRegion3'] = '
' . t('TXT_GOLFE') . '

<div id="menuwindows">
  <div id="menurectanglewindows">
    <div id="boutonrectangleorange">
      <a href="site/bouger/se-promener-dans-les-villages-du-golfe-de-sttropez.php">' . t('MENU_UI_VILLAGES') . '
        <img src="/assets/images/structure/menu/bouger/uivillages.jpg" alt="' . t('MENU_UI_VILLAGESALT') . '" title="' . t('MENU_UI_VILLAGES') . '">
      </a>
    </div>
  </div>	

  <div id="menurectanglewindows">
    <div id="boutonrectanglebleuvert">
      <a href="les-animations-dans-le-golfe-de-sttropez.php">' . t('MENU_UI_ANIMATIONS') . '
        <img src="/assets/images/structure/menu/bouger/uianimations.jpg" alt="' . t('MENU_UI_ANIMATIONSALT') . '" title="' . t('MENU_UI_ANIMATIONS') . '">
      </a>
    </div>					
  </div>	
</div>
';

$blocks['EditRegion4'] = '
<div class="img">
  <img src="/assets/images/bouger/golfe/Golfe_et_Caps.jpg" title="' . t('IMAGE_ALT_cap') . '" alt="' . t('IMAGE_ALT_cap') . '">
  <img src="/assets/images/bouger/golfe/Golphe_De_Grimaut.jpg" title="' . t('IMAGE_ALT_grimaud') . '" alt="' . t('IMAGE_ALT_grimaud') . '">
</div>
<div class="img">
  <img src="/assets/images/bouger/golfe/mediterranee.jpg" title="' . t('IMAGE_ALT_mediterranee') . '" alt="' . t('IMAGE_ALT_mediterranee') . '">
</div>
';

$blocks['EditRegion5'] = ''; // laissé vide volontairement
$blocks['EditRegion6'] = ''; // laissé vide volontairement
$blocks['EditRegion7'] = ''; // laissé vide volontairement

$blocks['EditRegion8'] = ''; // laissé vide volontairement

$blocks['EditRegion11'] = ''; // contenu possible avant le menu bas
