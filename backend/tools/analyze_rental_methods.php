<?php

/**
 * Script d'analyse pour PrivatePortalController
 * 
 * Ce script identifie toutes les methodes handleRental* et renderRental*
 * et verifie leur utilisation dans le fichier.
 * 
 * Usage: php backend/tools/analyze_rental_methods.php
 */

declare(strict_types=1);

const SOURCE_FILE = __DIR__ . '/../src/PrivatePortal/Http/PrivatePortalController.php';

echo "=== Analyse de PrivatePortalController ===\n\n";

// Lire le fichier source
if (!file_exists(SOURCE_FILE)) {
    echo "ERREUR: Fichier source introuvable: " . SOURCE_FILE . "\n";
    exit(1);
}

$content = file_get_contents(SOURCE_FILE);
if ($content === false) {
    echo "ERREUR: Impossible de lire le fichier source\n";
    exit(1);
}

$lines = explode("\n", $content);
$totalLines = count($lines);

echo "Fichier: $totalLines lignes\n\n";

// Trouver toutes les methodes handleRental* et renderRental*
$methodPattern = '/private\s+function\s+(handleRental|renderRental)(\w+)\(\s*[^)]*\)\s*:/m';
preg_match_all($methodPattern, $content, $matches, PREG_OFFSET_CAPTURE);

$methods = [];
foreach ($matches[0] as $i => $match) {
    $methodName = $matches[2][$i][0];
    $fullMethodName = $matches[1][$i][0] . $methodName;
    $position = $matches[0][$i][1];
    
    // Trouver la ligne
    $lineNumber = 0;
    $pos = 0;
    foreach ($lines as $lineNum => $line) {
        if ($pos + strlen($line) >= $position) {
            $lineNumber = $lineNum + 1;
            break;
        }
        $pos += strlen($line) + 1; // +1 pour le saut de ligne
    }
    
    // Trouver la fin de la methode (le } correspondant)
    $bodyStart = strpos($content, '{', $position);
    if ($bodyStart !== false) {
        $braceCount = 0;
        $methodEnd = null;
        for ($j = $bodyStart; $j < strlen($content); $j++) {
            if ($content[$j] === '{') {
                $braceCount++;
            } elseif ($content[$j] === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    $methodEnd = $j;
                    break;
                }
            }
        }
        
        if ($methodEnd !== null) {
            $methodContent = substr($content, $position, $methodEnd - $position + 1);
            $methodLines = count(explode("\n", $methodContent));
            
            // Vérifier si la méthode est appelée
            $calledPattern = '/\$this->' . preg_quote($fullMethodName, '/') . '\s*\(/m';
            $isCalled = preg_match($calledPattern, $content) ? '❌ APPELEE' : '✓ non appelee';
            
            $methods[] = [
                'name' => $fullMethodName,
                'line' => $lineNumber,
                'lines_count' => $methodLines,
                'status' => $isCalled
            ];
        }
    }
}

// Trier par ligne
usort($methods, static fn($a, $b) => $a['line'] <=> $b['line']);

// Afficher les methodes handleRental*
echo "=== Methodes handleRental* (" . count(array_filter($methods, static fn($m) => str_starts_with($m['name'], 'handleRental'))) . ") ===\n";
$handleRentalTotalLines = 0;
foreach ($methods as $method) {
    if (str_starts_with($method['name'], 'handleRental')) {
        echo sprintf("  Ligne %4d: %-40s %5d lignes - %s\n", 
            $method['line'], $method['name'], $method['lines_count'], $method['status']);
        $handleRentalTotalLines += $method['lines_count'];
    }
}
echo "  Total: $handleRentalTotalLines lignes\n\n";

// Afficher les methodes renderRental*
echo "=== Methodes renderRental* (" . count(array_filter($methods, static fn($m) => str_starts_with($m['name'], 'renderRental'))) . ") ===\n";
$renderRentalTotalLines = 0;
foreach ($methods as $method) {
    if (str_starts_with($method['name'], 'renderRental')) {
        echo sprintf("  Ligne %4d: %-40s %5d lignes - %s\n", 
            $method['line'], $method['name'], $method['lines_count'], $method['status']);
        $renderRentalTotalLines += $method['lines_count'];
    }
}
echo "  Total: $renderRentalTotalLines lignes\n\n";

// Statistiques
echo "=== Statistiques ===\n";
echo "Total methodes RealEstateRental: " . count($methods) . "\n";
echo "Total lignes: " . ($handleRentalTotalLines + $renderRentalTotalLines) . "\n";
echo "Pourcentage du fichier: " . number_format(($handleRentalTotalLines + $renderRentalTotalLines) / $totalLines * 100, 1) . "%\n";

// Vérifier les imports RealEstateRental
echo "\n=== Imports RealEstateRental ===\n";
$importPattern = '/use\s+Caramagnols\\\\PrivateApps\\\\RealEstateRental\\/m';
preg_match_all($importPattern, $content, $importMatches);

echo "Nombre d'imports RealEstateRental: " . count($importMatches[0]) . "\n";

foreach ($importMatches[0] as $import) {
    $import = trim($import);
    if (str_ends_with($import, ';')) {
        $import = substr($import, 0, -1);
    }
    echo "  " . $import . "\n";
}

echo "\n=== Recommandation ===\n";
echo "Pour nettoyer automatiquement, executez:\n";
echo "  php backend/tools/cleanup_rental_methods.php --dry-run\n";
echo "\nPour voir les differences:\n";
echo "  git diff backend/src/PrivatePortal/Http/PrivatePortalController.php\n";
