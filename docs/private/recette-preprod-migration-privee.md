# Runbook C0 — Gate preproduction avant go-live (migration privée)

Date de création : 2026-05-29  
Version cible : `docs/private/plan-correction-dettes-migration-privee-2026-05-28.md` — Phase C0  
Objectif : bloquer le go-live si la gate automatisée + recette manuelle minimale n’est pas complète.

## Périmètre de la gate

- commandes automatiques obligatoires :
  - `php backend/core/tools/private_migration_reconcile.php security-checklist`
  - `php backend/core/tools/private_migration_reconcile.php migration-dod`
  - `php backend/core/tools/private_migration_reconcile.php m5-plan`
  - `php backend/core/tools/private_migration_reconcile.php m6-retirement`
  - `composer check-security-headers --working-dir=backend -- --url=$PREPROD_CHECK_URL`
- tests manuels C1, C2, C3 (privé) obligatoires avant Go.

## Table de preuves

| Date | Acteur | Commande | Résultat | Lien preuve |
|---|---|---|---|---|
| 2026-05-29 | Auto (CLI) | `php backend/core/tools/private_migration_reconcile.php security-checklist` | `success=true`, `ready=true` (19/19 checks) | [01-security-checklist.json](./recette-preprod-migration-privee/01-security-checklist.json) |
| 2026-05-29 | Auto (CLI) | `php backend/core/tools/private_migration_reconcile.php migration-dod` | `success=true`, `ready=true` (11/11 checks) | [02-migration-dod.json](./recette-preprod-migration-privee/02-migration-dod.json) |
| 2026-05-29 | Auto (CLI) | `php backend/core/tools/private_migration_reconcile.php m5-plan` | `success=true`, `ready=true` (tous modules) | [03-m5-plan.json](./recette-preprod-migration-privee/03-m5-plan.json) |
| 2026-05-29 | Auto (CLI) | `php backend/core/tools/private_migration_reconcile.php m6-retirement` | `success=true`, `ready=true` (rétro-inventaire OK) | [04-m6-retirement.json](./recette-preprod-migration-privee/04-m6-retirement.json) |
| 2026-05-29 | Auto (PHPUnit) | `php vendor/bin/phpunit tests/PrivatePortalSecurityTest.php tests/PrivatePortalStorageTest.php tests/PrivatePortalFrontControllerTest.php tests/PrivatePortal/PrivacyOperationsTest.php tests/PrivatePortalPhaseCoverageTest.php` | `success=true`, `48 tests`, `432 assertions` | [06-c1-c2-c3-tests.txt](./recette-preprod-migration-privee/06-c1-c2-c3-tests.txt) |
| 2026-05-29 | Auto (CLI) | `composer check-security-headers --working-dir=backend -- --url=https://www.lescaramagnols.com` | `URL atteignable`, `Status 200`, `Headers requis: OK` | [07-check-security-headers-run.txt](./recette-preprod-migration-privee/07-check-security-headers-run.txt) |
| 2026-05-29 | Auto (CLI) | `export PREPROD_CHECK_URL='https://preprod.lescaramagnols.com' && composer check-security-headers --working-dir=backend -- --url=$PREPROD_CHECK_URL` | `URL cible disponible`, `Status 200`, `8 headers manquants` | [05-check-security-headers.txt](./recette-preprod-migration-privee/05-check-security-headers.txt) |
| 2026-05-29 | Auto (CLI) | `composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com` | `URL atteignable`, `Status 200`, `8 headers manquants`, `KO` | [08-check-security-headers-preprod.txt](./recette-preprod-migration-privee/08-check-security-headers-preprod.txt) |
| 2026-05-29 | Auto (CLI) | `composer check-security-headers --working-dir=backend -- --url="$PREPROD_CHECK_URL"` (avec `export PREPROD_CHECK_URL='https://preprod.lescaramagnols.com'`) | `URL atteignable`, `Status 200`, `8 headers manquants`, `KO` | [09-check-security-headers-preprod-exported-url.txt](./recette-preprod-migration-privee/09-check-security-headers-preprod-exported-url.txt) |
| 2026-05-29 | Auto (CLI) | `export PREPROD_CHECK_URL='https://preprod.lescaramagnols.com' && composer check-security-headers --working-dir=backend -- --url=$PREPROD_CHECK_URL` | `URL atteignable`, `Status 200`, `8 headers manquants`, `KO` (revalidation finale) | [10-check-security-headers-final.txt](./recette-preprod-migration-privee/10-check-security-headers-final.txt) |

> `PREPROD_CHECK_URL` doit être défini avec l’URL réelle de préproduction (`https://preprod.lescaramagnols.com`) avant la vraie passe.

## Paramètres préproduction retenus (recommandation opérationnelle)

- URL préproduction : `https://preprod.lescaramagnols.com`
- Base de données : `DB_NAME=caramagnols_preprod`
- Compte SQL préprod : `DB_USER=lescaramgpreprod`
- Mot de passe SQL : stocké hors dépôt (secret manager / variable d’environnement), **jamais en clair dans Git**
- Vérification des headers : via `PREPROD_CHECK_URL` (pas de placeholder)

## Commandes réelles exécutées

```bash
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/01-security-checklist.json
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/02-migration-dod.json
php backend/core/tools/private_migration_reconcile.php m5-plan > docs/private/recette-preprod-migration-privee/03-m5-plan.json
php backend/core/tools/private_migration_reconcile.php m6-retirement > docs/private/recette-preprod-migration-privee/04-m6-retirement.json
php backend/vendor/bin/phpunit tests/PrivatePortalSecurityTest.php tests/PrivatePortalStorageTest.php tests/PrivatePortalFrontControllerTest.php tests/PrivatePortal/PrivacyOperationsTest.php tests/PrivatePortalPhaseCoverageTest.php > docs/private/recette-preprod-migration-privee/06-c1-c2-c3-tests.txt 2>&1
composer check-security-headers --working-dir=backend -- --url=https://www.lescaramagnols.com > docs/private/recette-preprod-migration-privee/07-check-security-headers-run.txt 2>&1
export PREPROD_CHECK_URL='https://preprod.lescaramagnols.com'
composer check-security-headers --working-dir=backend -- --url=$PREPROD_CHECK_URL > docs/private/recette-preprod-migration-privee/05-check-security-headers.txt 2>&1
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/08-check-security-headers-preprod.txt 2>&1
composer check-security-headers --working-dir=backend -- --url="$PREPROD_CHECK_URL" > docs/private/recette-preprod-migration-privee/09-check-security-headers-preprod-exported-url.txt 2>&1
```

## Tests manuels requis (phase C1/C2/C3)

- [ ] C1 — Recette sécurité privée (login, logout, expiration, CSRF refusé, compte suspendu, permission retirée)
- [ ] C2 — Contrôles d’accès fichier/accès privé sans session puis avec session + module `documents` refusé/autorisé
- [ ] C3 — Restauration privée fichier+base en préprod (scénario complet de backup/snapshot/restore dry-run)

Chaque cas doit être signé dans cette section : date, opérateur, preuve (captures / logs), résultat attendu.

## Section bloquante Go / No-Go

### Décision préliminaire
- `Go / No-Go` : **NO-GO**
- Raison : la cible préproduction répond en 200, mais la règle de headers sécurité n’est pas encore alignée (`8` headers requis manquants sur `preprod.lescaramagnols.com`), donc la passe de validation reste bloquée.

### Conditions à lever pour lever le blocage
1. Corriger la configuration de sécurité de préprod (CSP/HSTS/Protection headers), puis relancer `composer check-security-headers` sur `preprod.lescaramagnols.com` jusqu’au résultat OK.
2. Finaliser C1/C2/C3 avec preuve horodatée.
3. Vérifier qu’aucune dette critique P0/P1 n’est demeurée non décidée depuis le plan.
4. Mettre à jour cette table avec les sorties finales (`ready=true`) avant bascule.

## Arborescence des preuves

- `docs/private/recette-preprod-migration-privee/01-security-checklist.json`
- `docs/private/recette-preprod-migration-privee/02-migration-dod.json`
- `docs/private/recette-preprod-migration-privee/03-m5-plan.json`
- `docs/private/recette-preprod-migration-privee/04-m6-retirement.json`
- `docs/private/recette-preprod-migration-privee/05-check-security-headers.txt`
- `docs/private/recette-preprod-migration-privee/06-c1-c2-c3-tests.txt`
- `docs/private/recette-preprod-migration-privee/07-check-security-headers-run.txt`
- `docs/private/recette-preprod-migration-privee/08-check-security-headers-preprod.txt`
- `docs/private/recette-preprod-migration-privee/09-check-security-headers-preprod-exported-url.txt`
- `docs/private/recette-preprod-migration-privee/10-check-security-headers-final.txt`

## Prochaine action

Relancer uniquement la passe `Go/No-Go` après disponibilité de la vraie URL préproduction, puis fermer la section de verrouillage ici avec :

- statut
- date
- opérateur
- liens de preuves actualisés
