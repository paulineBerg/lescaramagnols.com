# Scripts de nettoyage RealEstateRental

Ce dossier contient des scripts pour automatiser le nettoyage du code mort dans `PrivatePortalController` après l'extraction du module RealEstateRental.

## Contexte

Lors de l'extraction du module RealEstateRental vers `PrivateApps/RealEstateRental/Http/RealEstateRentalController`, de nombreuses méthodes `handleRental*` et `renderRental*` sont restées dans `PrivatePortalController` alors qu'elles ne sont plus utilisées (toutes les routes délèguent maintenant vers `rentalController()->handle()`).

## Prérequis

- PHP 8.2+
- Le fichier `backend/src/PrivatePortal/Http/PrivatePortalController.php` doit exister
- Toutes les routes rental* doivent être délégues vers `rentalController()` (déjà fait)

## Scripts disponibles

### 1. `analyze_rental_methods.php` - Analyse

**Description :** Identifie toutes les méthodes handleRental* et renderRental* et vérifie leur utilisation.

**Usage :**
```bash
cd backend
php tools/analyze_rental_methods.php
```

**Sortie :**
- Liste des méthodes handleRental* avec numéro de ligne et nombre de lignes
- Liste des méthodes renderRental* avec numéro de ligne et nombre de lignes
- Statistiques (total lignes, pourcentage du fichier)
- Liste des imports RealEstateRental
- Statut : "✓ non appelee" ou "❌ APPELEE"

**Exemple de sortie :**
```
=== Methodes handleRental* (14) ===
  Ligne  557: handleRentalPropertyMembers                 85 lignes - ✓ non appelee
  Ligne  706: handleRentalTenants                       152 lignes - ✓ non appelee
  ...
  Total: 1245 lignes

=== Methodes renderRental* (15) ===
  Ligne 3064: renderRentalDashboard                      45 lignes - ✓ non appelee
  Ligne 3117: renderRentalProperties                    12 lignes - ✓ non appelee
  ...
  Total: 312 lignes

=== Statistiques ===
Total methodes RealEstateRental: 29
Total lignes: 1557
Pourcentage du fichier: 28.8%
```

### 2. `cleanup_rental_methods.php` - Nettoyage

**Description :** Supprime automatiquement toutes les méthodes handleRental* et renderRental* non utilisées, nettoie les imports et les lignes vides.

**Usage :**
```bash
# Mode dry-run (simulation, pas de modification)
cd backend
php tools/cleanup_rental_methods.php --dry-run

# Mode normal (applique les modifications)
cd backend
php tools/cleanup_rental_methods.php
```

**Fonctionnalités :**
- Crée une sauvegarde automatique : `backend/src/PrivatePortal/Http/PrivatePortalController.php.backup`
- Valide que les méthodes ne sont pas appelées avant suppression
- Supprime 29 méthodes (14 handleRental* + 15 renderRental*)
- Nettoie les imports RealEstateRental et AgencyManagement inutilisés
- Nettoie les lignes vides excessives
- Affiche un résumé des changements

**Sortie attendue :**
```
=== Script de nettoyage PrivatePortalController ===

Fichier source: 5401 lignes, 185432 octets

✓ Validation: Aucune methode a supprimer n'est appelee dans le dispatch
✓ Sauvegarde cree: backend/src/PrivatePortal/Http/PrivatePortalController.php.backup
✓ Supprime: handleRentalPropertyMembers (85 lignes)
✓ Supprime: handleRentalTenants (152 lignes)
...
✓ Imports nettoyes
✓ Lignes vides nettoyees

=== Resultat ===
Fichier resultante: 3844 lignes, 129876 octets
Reduction: 1557 lignes, 55556 octets

✓ Fichier mis a jour: backend/src/PrivatePortal/Http/PrivatePortalController.php

=== Resume ===
Methodes a supprimer: 22
Methodes supprimees: 22
Lignes supprimees: 1557
Octets economises: 55556

✓ Nettoyage termine avec succes!

Prochaine etape:
1. Verifier le fichier: git diff backend/src/PrivatePortal/Http/PrivatePortalController.php
2. Executer les tests: cd backend && composer test
3. Verifier PHPStan: cd backend && composer phpstan
4. Verifier PHPCS: cd backend && composer phpcs
```

## Workflow recommandé

### Étape 1 : Analyse
```bash
cd backend
php tools/analyze_rental_methods.php
```
Vérifiez que toutes les méthodes sont marquées "✓ non appelee". Si certaines sont marquées "❌ APPELEE", il faut d'abord les déléguer vers rentalController().

### Étape 2 : Dry-run
```bash
cd backend
php tools/cleanup_rental_methods.php --dry-run
```
Cela vous montre ce qui sera supprimé sans modifier le fichier.

### Étape 3 : Nettoyage
```bash
cd backend
php tools/cleanup_rental_methods.php
```
Applique les modifications.

### Étape 4 : Validation
```bash
cd backend
git diff backend/src/PrivatePortal/Http/PrivatePortalController.php  # Vérifier les changements
composer test                                                    # Exécuter les tests
composer phpstan                                                 # Vérifier PHPStan
composer phpcs                                                   # Vérifier PHPCS
```

### Étape 5 : Commit
```bash
cd backend
git add src/PrivatePortal/Http/PrivatePortalController.php
git commit -m "Nettoyage: supprimer methodes RealEstateRental de PrivatePortalController"
```

## Récupération en cas d'erreur

Une sauvegarde est créée automatiquement :
```bash
backend/src/PrivatePortal/Http/PrivatePortalController.php.backup
```

Pour restaurer :
```bash
cp backend/src/PrivatePortal/Http/PrivatePortalController.php.backup \
   backend/src/PrivatePortal/Http/PrivatePortalController.php
```

## Méthodes concernées

### Méthodes handleRental* (14 méthodes)
- handleRentalPropertyMembers
- handleRentalTenants
- handleRentalLeases
- handleRentalRents
- handleRentalPayments
- handleRentalExpenses
- handleRentalRegularizations
- handleRentalDocuments
- handleRentalAgencyImports
- handleRentalAgencyReview
- handleRentalDocumentFile
- handleRentalRegularizationFile

### Méthodes renderRental* (15 méthodes)
- renderRentalDashboard
- renderRentalProperties
- renderRentalUnits
- renderRentalMembers
- renderRentalTenants
- renderRentalLeases
- renderRentalRents
- renderRentalPayments
- renderRentalExpenses
- renderRentalRegularizations
- renderRentalDocuments
- renderRentalAgencyImports
- renderRentalAgencyReview

## Imports concernés

Tous les imports du namespace `Caramagnols\[PrivateApps\]\[RealEstateRental\]\[...\]` qui ne sont plus utilisés après la suppression des méthodes.

## Notes

- Les scripts sont conçus pour être sûrs : ils vérifient que les méthodes ne sont pas appelées avant de les supprimer
- Une sauvegarde est toujours créée avant toute modification
- Le mode `--dry-run` permet de voir les changements sans appliquer
- Les scripts peuvent être adaptés pour d'autres nettoyages similaires

## Liens

- Roadmap : `docs/roadmap/optimisation-2026-07.md`
- Architecture : `backend/src/PrivateApps/AGENTS.md`
- Module RealEstateRental : `backend/src/PrivateApps/RealEstateRental/`
