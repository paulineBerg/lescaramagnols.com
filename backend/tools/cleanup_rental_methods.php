<?php

/**
 * Script de nettoyage pour PrivatePortalController
 * 
 * Ce script supprime automatiquement toutes les methodes handleRental* et renderRental*
 * qui ne sont plus utilisees (car delegees vers RealEstateRentalController).
 * 
 * Usage: php backend/tools/cleanup_rental_methods.php [--dry-run]
 */

declare(strict_types=1);

const SOURCE_FILE = __DIR__ . '/../src/PrivatePortal/Http/PrivatePortalController.php';
const BACKUP_FILE = __DIR__ . '/../src/PrivatePortal/Http/PrivatePortalController.php.backup';
const OUTPUT_FILE = __DIR__ . '/../src/PrivatePortal/Http/PrivatePortalController.php';

// Liste des methodes a supprimer (handleRental* et renderRental*)
const METHODS_TO_REMOVE = [
    'handleRentalPropertyMembers',
    'handleRentalTenants',
    'handleRentalLeases',
    'handleRentalRents',
    'handleRentalPayments',
    'handleRentalExpenses',
    'handleRentalRegularizations',
    'handleRentalDocuments',
    'handleRentalAgencyImports',
    'handleRentalAgencyReview',
    'handleRentalDocumentFile',
    'handleRentalRegularizationFile',
    'renderRentalDashboard',
    'renderRentalProperties',
    'renderRentalUnits',
    'renderRentalMembers',
    'renderRentalTenants',
    'renderRentalLeases',
    'renderRentalRents',
    'renderRentalPayments',
    'renderRentalExpenses',
    'renderRentalRegularizations',
    'renderRentalDocuments',
    'renderRentalAgencyImports',
    'renderRentalAgencyReview',
];

// Imports a supprimer (RealEstateRental et AgencyManagement)
const IMPORTS_TO_REMOVE = [
    'Caramagnols\\PrivateApps\\RealEstateRental\\Domain\\RentalLeaseTypeCatalog',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Domain\\RentalExpenseCategoryCatalog',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalLifecycleRepository',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalLessorRepository',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalPropertyRepository',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalPropertyMemberRepository',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Repository\\RentalUnitRepository',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Domain\\AgencyImportedDocument',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Domain\\AgencyFiscalReviewPolicy',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Import\\AgencyImportService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Repository\\AgencyImportRepository',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Service\\AgencyAdvancedReconciliationService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Service\\AgencyTaxBridgeNormalizer',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\ChargeRegularizationService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentScheduleService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentPaymentStatusService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalAnnualSummaryService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalDashboardService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalExportService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalPaymentRequestService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Service\\RentalReceiptService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\TaxBridge\\RentalTaxDataProvider',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Repository\\AgencyMappingRepository',
    'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyManagement\\Service\\AgencyStatementValidationService',
    'Caramagnols\\PrivateApps\\RealEstateRental\\Http\\RealEstateRentalController',
];

/**
 * Extrait le corps d'une methode (entre { et })
 */
function extractMethodBody(string $content, int $startPos): array
{
    $braceCount = 0;
    $inMethod = false;
    $methodStart = null;
    $methodEnd = null;
    
    for ($i = $startPos; $i < strlen($content); $i++) {
        $char = $content[$i];
        
        if ($char === '{') {
            if (!$inMethod) {
                $inMethod = true;
                $methodStart = $i;
            }
            $braceCount++;
        } elseif ($char === '}') {
            $braceCount--;
            if ($braceCount === 0 && $inMethod) {
                $methodEnd = $i;
                return [
                    'start' => $methodStart,
                    'end' => $methodEnd + 1,
                    'length' => $methodEnd - $methodStart + 1
                ];
            }
        }
    }
    
    return ['start' => null, 'end' => null, 'length' => 0];
}

/**
 * Trouve la position de debut d'une methode (signature)
 */
function findMethodSignature(string $content, string $methodName): ?int
{
    $pattern = '/private\s+function\s+' . preg_quote($methodName, '/') . '\s*\(/m';
    if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        return $matches[0][1];
    }
    return null;
}

/**
 * Extrait une methode complete (signature + corps)
 */
function extractFullMethod(string $content, int $signatureStart): array
{
    // Trouver le debut du corps (le { après la signature)
    $bodyStart = strpos($content, '{', $signatureStart);
    if ($bodyStart === false) {
        return ['start' => null, 'end' => null, 'content' => ''];
    }
    
    // Extraire le corps
    $bodyInfo = extractMethodBody($content, $bodyStart);
    if ($bodyInfo['start'] === null) {
        return ['start' => null, 'end' => null, 'content' => ''];
    }
    
    // Inclure la signature
    return [
        'start' => $signatureStart,
        'end' => $bodyInfo['end'],
        'length' => $bodyInfo['end'] - $signatureStart,
        'content' => substr($content, $signatureStart, $bodyInfo['end'] - $signatureStart)
    ];
}

/**
 * Trouve la ligne de l'import dans le fichier
 */
function findImportLine(string $content, string $className): ?int
{
    $pattern = '/use\s+' . preg_quote($className, '/') . '\s*;/m';
    if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
        // Trouver le debut de la ligne
        $lineStart = strrpos(substr($content, 0, $matches[0][1]), "\n");
        if ($lineStart === false) {
            $lineStart = 0;
        }
        return $lineStart + 1;
    }
    return null;
}

/**
 * Extrait la ligne complete d'un import
 */
function extractImportLine(string $content, int $pos): string
{
    $lineStart = strrpos(substr($content, 0, $pos), "\n");
    if ($lineStart === false) {
        $lineStart = 0;
    }
    
    $lineEnd = strpos($content, "\n", $pos);
    if ($lineEnd === false) {
        $lineEnd = strlen($content);
    }
    
    return substr($content, $lineStart + 1, $lineEnd - $lineStart - 1);
}

/**
 * Nettoie les imports inutilises
 */
function cleanupUnusedImports(string $content, array $removedMethods): string
{
    $lines = explode("\n", $content);
    $newLines = [];
    $inUseSection = false;
    $useSectionEnded = false;
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Detecter la fin de la section use
        if ($inUseSection && ($trimmed === '' || $trimmed === 'final class' || $trimmed === 'class')) {
            $inUseSection = false;
            $useSectionEnded = true;
        }
        
        // Detecter le debut de la section use
        if (str_starts_with($trimmed, 'use ') && !$inUseSection && !$useSectionEnded) {
            $inUseSection = true;
        }
        
        // Si on est dans la section use, verifier si l'import est utile
        if ($inUseSection && str_starts_with($trimmed, 'use ')) {
            $className = trim(str_replace(['use ', ';'], '', $trimmed));
            
            // Conserver l'import s'il n'est pas dans la liste a supprimer
            $shouldRemove = false;
            foreach (IMPORTS_TO_REMOVE as $importToRemove) {
                if (str_contains($className, $importToRemove)) {
                    $shouldRemove = true;
                    break;
                }
            }
            
            if (!$shouldRemove) {
                $newLines[] = $line;
            }
        } else {
            $newLines[] = $line;
        }
    }
    
    return implode("\n", $newLines);
}

/**
 * Nettoie les espaces vides excessifs
 */
function cleanupEmptyLines(string $content): string
{
    $lines = explode("\n", $content);
    $newLines = [];
    $prevEmpty = false;
    
    foreach ($lines as $line) {
        if (trim($line) === '') {
            if (!$prevEmpty) {
                $newLines[] = $line;
                $prevEmpty = true;
            }
        } else {
            $newLines[] = $line;
            $prevEmpty = false;
        }
    }
    
    return implode("\n", $newLines);
}

/**
 * Valide que les methodes a supprimer ne sont pas appelees dans le dispatch
 */
function validateNoCallsInDispatch(string $content, array $methods): bool
{
    foreach ($methods as $method) {
        // Chercher des appels comme $this->methodName(
        $pattern = '/\$this->' . preg_quote($method, '/') . '\s*\(/m';
        if (preg_match($pattern, $content)) {
            echo "ERREUR: La methode $method est encore appelee dans le fichier!\n";
            return false;
        }
    }
    return true;
}

// ============================================================================
// MAIN
// ============================================================================

echo "=== Script de nettoyage PrivatePortalController ===\n\n";

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

$originalSize = strlen($content);
$originalLines = count(explode("\n", $content));

echo "Fichier source: $originalLines lignes, $originalSize octets\n\n";

// Valider que les methodes ne sont pas appelees
if (!validateNoCallsInDispatch($content, METHODS_TO_REMOVE)) {
    echo "\nABANDON: Certaines methodes sont encore utilisees. Veuillez d'abord deleguer toutes les routes.\n";
    exit(1);
}

echo "✓ Validation: Aucune methode a supprimer n'est appelee dans le dispatch\n\n";

// Creer une sauvegarde
if (!file_exists(BACKUP_FILE)) {
    if (!copy(SOURCE_FILE, BACKUP_FILE)) {
        echo "ERREUR: Impossible de creer la sauvegarde\n";
        exit(1);
    }
    echo "✓ Sauvegarde cree: " . BACKUP_FILE . "\n\n";
} else {
    echo "⚠ Sauvegarde existe deja: " . BACKUP_FILE . "\n\n";
}

// Trouver et supprimer les methodes
$newContent = $content;
$methodsRemoved = 0;
$linesRemoved = 0;

foreach (METHODS_TO_REMOVE as $methodName) {
    $signaturePos = findMethodSignature($newContent, $methodName);
    
    if ($signaturePos !== null) {
        $methodInfo = extractFullMethod($newContent, $signaturePos);
        
        if ($methodInfo['start'] !== null) {
            // Calculer le nombre de lignes supprimees
            $methodContent = substr($newContent, $methodInfo['start'], $methodInfo['length']);
            $methodLines = count(explode("\n", $methodContent));
            
            // Supprimer la methode
            $newContent = substr_replace($newContent, '', $methodInfo['start'], $methodInfo['length']);
            
            $methodsRemoved++;
            $linesRemoved += $methodLines;
            
            echo "✓ Supprime: $methodName ($methodLines lignes)\n";
        } else {
            echo "⚠ Non trouvee (corps): $methodName\n";
        }
    } else {
        echo "⚠ Non trouvee (signature): $methodName\n";
    }
}

echo "\n";

// Nettoyer les imports
$contentAfterMethods = $newContent;
$newContent = cleanupUnusedImports($contentAfterMethods, METHODS_TO_REMOVE);

echo "✓ Imports nettoyes\n\n";

// Nettoyer les lignes vides excessives
$newContent = cleanupEmptyLines($newContent);

echo "✓ Lignes vides nettoyees\n\n";

// Calculer les statistiques
$newSize = strlen($newContent);
$newLines = count(explode("\n", $newContent));

$sizeDiff = $originalSize - $newSize;
$linesDiff = $originalLines - $newLines;

echo "=== Resultat ===\n";
$dryRun = isset($argv[1]) && $argv[1] === '--dry-run';

if ($dryRun) {
    echo "Mode: DRY RUN (pas de modification)\n";
    echo "Fichier resultante: $newLines lignes, $newSize octets\n";
    echo "Reduction: $linesDiff lignes, $sizeDiff octets\n";
    echo "\nPour appliquer les changements, executez sans --dry-run\n";
    exit(0);
}

echo "Fichier resultante: $newLines lignes, $newSize octets\n";
echo "Reduction: $linesDiff lignes, $sizeDiff octets\n";

// Ecrire le nouveau fichier
if (file_put_contents(OUTPUT_FILE, $newContent) === false) {
    echo "\nERREUR: Impossible d'ecrire le fichier resultante\n";
    exit(1);
}

echo "\n✓ Fichier mis a jour: " . OUTPUT_FILE . "\n";

// Afficher un resume
$methodsRemovedCount = count(METHODS_TO_REMOVE);
echo "\n=== Resume ===\n";
echo "Methodes a supprimer: $methodsRemovedCount\n";
echo "Methodes supprimees: $methodsRemoved\n";
echo "Lignes supprimees: $linesDiff\n";
echo "Octets economises: $sizeDiff\n";

echo "\n✓ Nettoyage termine avec succes!\n";
echo "\nProchaine etape:\n";
echo "1. Verifier le fichier: git diff backend/src/PrivatePortal/Http/PrivatePortalController.php\n";
echo "2. Executer les tests: cd backend && composer test\n";
echo "3. Verifier PHPStan: cd backend && composer phpstan\n";
echo "4. Verifier PHPCS: cd backend && composer phpcs\n";
