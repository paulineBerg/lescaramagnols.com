<?php
/**
 * Script pour générer la baseline PHPStan pour core/ et config/
 *
 * Exécution :
 *   cd backend
 *   php vendor/bin/phpstan analyse --generate-baseline
 *
 * Cela mettra a jour phpstan.baseline.neon pour absorber l'existant sans bloquer
 * l'analyse.
 */

echo "Génération de la baseline PHPStan pour core/ et config/\n";
echo "========================================================\n\n";

echo "Ce script doit être exécuté avec :\n";
echo "  php vendor/bin/phpstan analyse --generate-baseline\n\n";

echo "La configuration actuelle (phpstan.neon.dist) inclut déjà :\n";
echo "  - core\n";
echo "  - src\n";
echo "  - config\n\n";

echo "Après génération, le fichier phpstan.baseline.neon sera mis à jour.\n";
echo "La configuration attendue dans phpstan.neon.dist est :\n\n";

echo "includes:\n";
echo "  - phpstan.baseline.neon\n\n";
echo "parameters:\n";
echo "  level: 5\n";
echo "  paths:\n";
echo "    - core\n";
echo "    - src\n";
echo "    - config\n";
echo "  tmpDir: var/phpstan\n";
echo "  bootstrapFiles:\n";
echo "    - phpstan.bootstrap.php\n";
echo "  treatPhpDocTypesAsCertain: false\n";
echo "  reportUnmatchedIgnoredErrors: false\n\n";

echo "Ensuite, exéutez :\n";
echo "  composer phpstan\n\n";

echo "Pour réduire progressivement la baseline :\n";
echo "1. Corrigez les erreurs dans core/ et config/\n";
echo "2. Régénérez la baseline avec --generate-baseline\n";
echo "3. Validez avec composer phpstan\n";
