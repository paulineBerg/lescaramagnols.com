<?php
// templates/pages/site/accueil/toutes-les-mentions-legales.php

// Initialisation des blocs (évite les erreurs si appelés dans le layout)
$blocks = [];

// === HEAD : balises META OG + SEO ===
$blocks['EditRegion10'] = ''; // contenu supprimé volontairement

// === Bloc haut avec image + titres ===
$blocks['EditRegion1'] = '
    <div id="bloc-haut"><h1>' . t('TXT_TITREMENTION') . '</h1></div>
</span>
<p></p>
';

// === Introduction page (colonneJustifie40) ===
$blocks['EditRegion2'] = '
<div id="bloc-haut" class="border">
    ' . t('TXT_MENTIONINTRO') . '
</div>
';

// === Prévu pour future intro additionnelle ===
$blocks['EditRegion8'] = '';

// === Bloc centre principal ===
$blocks['EditRegion3'] = '
<p>&nbsp;</p>

' . t('TXT_MENTIONCENTRE') . '

<div id="bloc-haut">
    - Pour placer ma bannière sur votre site, merci de copier/coller le code ci-dessous<br>
    <textarea rows="3" name="S1" cols="70">
<p align="center"><a href="https://www.lescaramagnols.com" target="_blank">
<img src="https://www.lescaramagnols.com/structure/images/menu/banniere.gif" alt="Les caramagnols, et le golfe de St-Tropez" width="434" height="60" border="0"></a></p>
<p align="center"><a href="https://www.lescaramagnols.com" target="_blank">www.lescaramagnols.com</a></p>
    </textarea>
    <br>
    <p align="center"><a href="https://www.lescaramagnols.com" target="_blank">
    <img src="https://www.lescaramagnols.com/structure/images/menu/banniere.gif" alt="Les caramagnols, le golfe de St-Tropez" width="434" height="60" border="0"></a></p>
    <p align="center"><a href="https://www.lescaramagnols.com" target="_blank">www.lescaramagnols.com</a></p>
</div>
';

// === Bloc bas centre ===
$blocks['EditRegion4'] = t('TXT_MENTIONCONCLUSION');

// === Bloc bas gauche ===
$blocks['EditRegion5'] = '';

// === Bloc bas droite ===
$blocks['EditRegion6'] = '';

// === Bloc bas centre : vide ici (rien dans HTML) ===
$blocks['EditRegion7'] = '';

// === Bloc juste avant menu bas ===
$blocks['EditRegion11'] = '';
