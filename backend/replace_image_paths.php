<?php
/**
 * Script pour remplacer les chemins d'images dans les fichiers de langue.
 * Remplace '../../images/autoretro' par '/assets/images/autoretro'
 * dans terminal : php replace_image_paths.php

 */

$targetFile = __DIR__ . '/lang/fr_autoretro.php'; // Modifie ce chemin si besoin

if (!file_exists($targetFile)) {
    echo "❌ Fichier non trouvé : $targetFile\n";
    exit(1);
}

$content = file_get_contents($targetFile);
$updatedContent = str_replace('../../images/autoretro', '/assets/images/autoretro', $content);

if ($content === $updatedContent) {
    echo "✅ Aucun remplacement nécessaire : les chemins sont déjà corrects.\n";
    exit(0);
}

$result = file_put_contents($targetFile, $updatedContent);

if ($result !== false) {
    echo "✅ Remplacement effectué avec succès dans $targetFile.\n";
} else {
    echo "❌ Erreur lors de l'écriture du fichier.\n";
}
