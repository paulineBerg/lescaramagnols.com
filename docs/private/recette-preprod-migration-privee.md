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
| 2026-05-29 | Auto (CLI) | `composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com` (suite durcissement headers + CSP/HSTS) | `URL atteignable`, `Status 200`, `8 headers manquants`, `KO` (page infra préprod non applicative détectée) | [11-check-security-headers-after-hardening.txt](./recette-preprod-migration-privee/11-check-security-headers-after-hardening.txt) |
| 2026-05-29 | Auto (CLI) | `export PREPROD_CHECK_URL='https://preprod.lescaramagnols.com' && composer check-security-headers --working-dir=backend -- --url=$PREPROD_CHECK_URL` (re-test durcissement headers) | `URL atteignable`, `Status 200`, `8 headers manquants`, `KO` (vhost OVH encore actif) | [12-check-security-headers-preprod-rerun.txt](./recette-preprod-migration-privee/12-check-security-headers-preprod-rerun.txt) |
| 2026-05-29 | Auto (CLI + HTTP preprod) | C1 securite privee preprod : login, CSRF refuse, compte suspendu, permission `documents` retiree, reset password, fichier sans session/sans permission | `C1 OK sauf logout initial`, aucun acces interdit | [15-c1-security-manual-preprod-2026-05-29.txt](./recette-preprod-migration-privee/15-c1-security-manual-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI + HTTP preprod) | C1 logout rerun apres correction | `logout OK`, `POST 302`, dashboard refuse apres logout | [16-c1-logout-rerun-preprod-2026-05-29.txt](./recette-preprod-migration-privee/16-c1-logout-rerun-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (synthèse) | C1 synthèse finale | `C1 OK`, anomalie upload classée hors C1 stricte | [17-c1-synthese-finale-preprod-2026-05-29.txt](./recette-preprod-migration-privee/17-c1-synthese-finale-preprod-2026-05-29.txt) |

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
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/11-check-security-headers-after-hardening.txt 2>&1
export PREPROD_CHECK_URL='https://preprod.lescaramagnols.com'
composer check-security-headers --working-dir=backend -- --url=$PREPROD_CHECK_URL > docs/private/recette-preprod-migration-privee/12-check-security-headers-preprod-rerun.txt 2>&1
php backend/vendor/bin/phpunit tests/PrivatePortalSecurityTest.php tests/PrivatePortalStorageTest.php tests/PrivatePortalFrontControllerTest.php tests/PrivatePortal/PrivacyOperationsTest.php tests/PrivatePortalPhaseCoverageTest.php > docs/private/recette-preprod-migration-privee/13-c1-c2-c3-tests-final.txt 2>&1
{ echo "# Manual C1/C2/C3 — préprod ($(date -Iseconds))"; for path in '/' '/private' '/private/login' '/private/dashboard' '/private/files/TEST'; do echo "\n=== $path ==="; curl -ik -sS --max-time 20 -I "https://preprod.lescaramagnols.com${path}" | head -n 30; done; } > docs/private/recette-preprod-migration-privee/14-c1-c2-c3-manuel-preprod-blocked.txt
```

## Tests manuels requis (phase C1/C2/C3)

- [x] C1 — Recette sécurité privée (login, logout, expiration, CSRF refusé, compte suspendu, permission retirée, reset password, fichier sans session/sans permission) — **OK PREPROD**, preuves: [15-c1-security-manual-preprod-2026-05-29.txt](./recette-preprod-migration-privee/15-c1-security-manual-preprod-2026-05-29.txt), [16-c1-logout-rerun-preprod-2026-05-29.txt](./recette-preprod-migration-privee/16-c1-logout-rerun-preprod-2026-05-29.txt)
- [ ] C2 — Suppression compte suspendu et cron J+20/J+30 — à exécuter sur préprod.
- [ ] C3 — Restauration privée fichier+base en préprod (scénario complet de backup/snapshot/restore dry-run) — à exécuter sur préprod.

Chaque cas doit être signé dans cette section : date, opérateur, preuve (captures / logs), résultat attendu.

## Section bloquante Go / No-Go

### Décision préliminaire
- `Go / No-Go` : **NO-GO**
- Raison : la cible préproduction renvoie une page OVH non applicative (headers HTTP incomplets), donc la passe de validation reste bloquée tant que l’environnement préprod n’est pas orienté vers l’instance PHP attendue.

### Décision finale (mise à jour)
- `Go / No-Go` : **NO-GO**
- Date : 2026-05-29
- Opérateur : auto (validation locale + HTTP préprod)
- Sortie : C1 validée sur préprod; C2 et C3 restent à signer avant GO.
- Preuves : [13-c1-c2-c3-tests-final.txt](./recette-preprod-migration-privee/13-c1-c2-c3-tests-final.txt), [15-c1-security-manual-preprod-2026-05-29.txt](./recette-preprod-migration-privee/15-c1-security-manual-preprod-2026-05-29.txt), [16-c1-logout-rerun-preprod-2026-05-29.txt](./recette-preprod-migration-privee/16-c1-logout-rerun-preprod-2026-05-29.txt), [17-c1-synthese-finale-preprod-2026-05-29.txt](./recette-preprod-migration-privee/17-c1-synthese-finale-preprod-2026-05-29.txt)

### Anomalies classees
- `majeur` : l'upload HTTP documentaire préprod retourne encore `upload_failed` / `storage_unavailable`. Aucun accès interdit n'a été observé; à reprendre avant go-live dans les phases d'exploitation documentaire/restauration.

### Conditions à lever pour lever le blocage
1. Vérifier le mapping vhost préprod (document-root, host `preprod.lescaramagnols.com`, cert+virtualhost) afin que la requête serve l’instance PHP du projet, puis relancer `composer check-security-headers` sur `preprod.lescaramagnols.com` jusqu’au résultat OK.
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
- `docs/private/recette-preprod-migration-privee/11-check-security-headers-after-hardening.txt`
- `docs/private/recette-preprod-migration-privee/12-check-security-headers-preprod-rerun.txt`
- `docs/private/recette-preprod-migration-privee/13-c1-c2-c3-tests-final.txt`
- `docs/private/recette-preprod-migration-privee/14-c1-c2-c3-manuel-preprod-blocked.txt`
- `docs/private/recette-preprod-migration-privee/15-c1-security-manual-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/16-c1-logout-rerun-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/17-c1-synthese-finale-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/12-c1-c2-c3-tests-refresh.txt`
- `docs/private/recette-preprod-migration-privee/12-c1-c2-c3-manuel-preprod-unavailable.txt`

## Prochaine action

Relancer uniquement la passe `Go/No-Go` après disponibilité de la vraie URL préproduction, puis fermer la section de verrouillage ici avec :

- statut
- date
- opérateur
- liens de preuves actualisés
