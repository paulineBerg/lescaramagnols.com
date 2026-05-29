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
| 2026-05-29 | Auto (CLI preprod) | C2 suppression compte suspendu + cron J+20/J+30 ciblé | `C2 OK`, sauvegarde JSON/ZIP, purge immédiate, J+20 dry-run, J+30 purge, relance idempotente | [18-c2-deletion-cron-preprod-2026-05-29.txt](./recette-preprod-migration-privee/18-c2-deletion-cron-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (PHPUnit) | Suite privée C1/C2/C3 après correction C2 | `59 tests`, `581 assertions`, OK | [19-c2-phpunit-private-suite-2026-05-29.txt](./recette-preprod-migration-privee/19-c2-phpunit-private-suite-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI local) | C3 implémentation backup ZIP + `verify-backup` + dry-run | `PHP lint OK`, `51 tests`, `527 assertions`, CLI C3 OK | [20-c3-local-backup-restore-cli-2026-05-29.txt](./recette-preprod-migration-privee/20-c3-local-backup-restore-cli-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI preprod) | C3 restauration fichier + base après déploiement `d971b72` | `C3 OK`, ZIP structuré, `verify-backup` OK, dry-run OK, nettoyage fixture + artefacts OK | [21-c3-preprod-backup-restore-2026-05-29.txt](./recette-preprod-migration-privee/21-c3-preprod-backup-restore-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | C0 final `security-checklist` | `success=true`, `ready=true`, `19/19 checks` | [22-c0-security-checklist-final-2026-05-29.json](./recette-preprod-migration-privee/22-c0-security-checklist-final-2026-05-29.json) |
| 2026-05-29 | Auto (CLI) | C0 final `migration-dod` | `success=true`, `ready=true`, `11/11 checks` | [23-c0-migration-dod-final-2026-05-29.json](./recette-preprod-migration-privee/23-c0-migration-dod-final-2026-05-29.json) |
| 2026-05-29 | Auto (CLI) | C0 final `m5-plan` | `success=true`, `ready=true` | [24-c0-m5-plan-final-2026-05-29.json](./recette-preprod-migration-privee/24-c0-m5-plan-final-2026-05-29.json) |
| 2026-05-29 | Auto (CLI) | C0 final `m6-retirement` | `success=true`, `ready=true` | [25-c0-m6-retirement-final-2026-05-29.json](./recette-preprod-migration-privee/25-c0-m6-retirement-final-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP preprod) | C0 final `check-security-headers` sur `https://preprod.lescaramagnols.com` | `Status 200`, `Headers requis: OK` | [26-c0-check-security-headers-preprod-final-2026-05-29.txt](./recette-preprod-migration-privee/26-c0-check-security-headers-preprod-final-2026-05-29.txt) |

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
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/22-c0-security-checklist-final-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/23-c0-migration-dod-final-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php m5-plan > docs/private/recette-preprod-migration-privee/24-c0-m5-plan-final-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php m6-retirement > docs/private/recette-preprod-migration-privee/25-c0-m6-retirement-final-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/26-c0-check-security-headers-preprod-final-2026-05-29.txt 2>&1
```

## Tests manuels requis (phase C1/C2/C3)

- [x] C1 — Recette sécurité privée (login, logout, expiration, CSRF refusé, compte suspendu, permission retirée, reset password, fichier sans session/sans permission) — **OK PREPROD**, preuves: [15-c1-security-manual-preprod-2026-05-29.txt](./recette-preprod-migration-privee/15-c1-security-manual-preprod-2026-05-29.txt), [16-c1-logout-rerun-preprod-2026-05-29.txt](./recette-preprod-migration-privee/16-c1-logout-rerun-preprod-2026-05-29.txt)
- [x] C2 — Suppression compte suspendu et cron J+20/J+30 — **OK PREPROD**, preuve: [18-c2-deletion-cron-preprod-2026-05-29.txt](./recette-preprod-migration-privee/18-c2-deletion-cron-preprod-2026-05-29.txt)
- [x] C3 — Restauration privée fichier+base en préprod (backup ZIP, structure ZIP, `verify-backup`, dry-run, nettoyage fixture et artefacts) — **OK PREPROD**, preuve: [21-c3-preprod-backup-restore-2026-05-29.txt](./recette-preprod-migration-privee/21-c3-preprod-backup-restore-2026-05-29.txt)

Chaque cas doit être signé dans cette section : date, opérateur, preuve (captures / logs), résultat attendu.

## Procédure C3 — restauration fichier + base

Objectif : prouver que la sauvegarde privée contient à la fois les lignes SQL et les fichiers privés, que le ZIP est structuré, et que la restauration dry-run détecte explicitement les conflits au lieu de les masquer.

Préparation :

- créer ou réutiliser un compte privé jetable en préprod ;
- rattacher au moins un document privé réel au compte ;
- conserver le stockage fichiers hors webroot ;
- ne jamais lancer de restauration réelle depuis cette procédure.

Commandes recommandées sur préprod :

```bash
php core/tools/private_migration_reconcile.php backup --output=var/private-c3-backup-result.json
BACKUP_JSON="$(php -r '$r=json_decode(file_get_contents("var/private-c3-backup-result.json"), true); echo $r["path"] ?? "";')"
php core/tools/private_migration_reconcile.php verify-backup "$BACKUP_JSON" --output=var/private-c3-verify.json
```

Contrôles attendus dans `var/private-c3-verify.json` :

- `verification.valid=true` ;
- `verification.archiveAvailable=true` ;
- `verification.storedFileCount >= 1` quand le jeu de test contient un document ;
- `restoreDryRun.success=true` ;
- `restoreDryRun.files.restorable=true` ;
- `restoreDryRun.sql.conflictCount` explicite si la sauvegarde est rejouée sur la même base ;
- `restoreDryRun.requiredConditions` liste les conditions d'une restauration réelle.

Restauration réelle : elle reste volontairement bloquée par `PrivateBackupService::restoreBackup($path, false)` tant qu'un runbook d'exploitation séparé n'a pas été signé. Avant toute écriture réelle, prendre un snapshot SQL et fichiers de la cible, traiter les conflits d'index, restaurer les fichiers hors webroot, puis journaliser opérateur, checksum et résultat.

## Section bloquante Go / No-Go

### Décision préliminaire
- `Go / No-Go` : **NO-GO**
- Raison : la cible préproduction renvoie une page OVH non applicative (headers HTTP incomplets), donc la passe de validation reste bloquée tant que l’environnement préprod n’est pas orienté vers l’instance PHP attendue.

### Décision finale (mise à jour)
- `Go / No-Go` : **GO C0**
- Date : 2026-05-29
- Opérateur : auto (CLI + HTTP préprod)
- Sortie : C0 validée. Les commandes obligatoires retournent `ready=true` ou `Headers requis: OK`, et les scénarios C1, C2, C3 sont signés.
- Preuves : [22-c0-security-checklist-final-2026-05-29.json](./recette-preprod-migration-privee/22-c0-security-checklist-final-2026-05-29.json), [23-c0-migration-dod-final-2026-05-29.json](./recette-preprod-migration-privee/23-c0-migration-dod-final-2026-05-29.json), [24-c0-m5-plan-final-2026-05-29.json](./recette-preprod-migration-privee/24-c0-m5-plan-final-2026-05-29.json), [25-c0-m6-retirement-final-2026-05-29.json](./recette-preprod-migration-privee/25-c0-m6-retirement-final-2026-05-29.json), [26-c0-check-security-headers-preprod-final-2026-05-29.txt](./recette-preprod-migration-privee/26-c0-check-security-headers-preprod-final-2026-05-29.txt), [15-c1-security-manual-preprod-2026-05-29.txt](./recette-preprod-migration-privee/15-c1-security-manual-preprod-2026-05-29.txt), [18-c2-deletion-cron-preprod-2026-05-29.txt](./recette-preprod-migration-privee/18-c2-deletion-cron-preprod-2026-05-29.txt), [21-c3-preprod-backup-restore-2026-05-29.txt](./recette-preprod-migration-privee/21-c3-preprod-backup-restore-2026-05-29.txt)
- Portée : ce `GO C0` ferme la gate préproduction C0. Les phases C4, C5 et V1-V5 restent suivies dans le plan, avec décisions explicites, et ne sont pas implicitement clôturées par cette validation.

### Anomalies classees
- `majeur` : l'upload HTTP documentaire préprod retourne encore `upload_failed` / `storage_unavailable`. Aucun accès interdit n'a été observé; à reprendre avant go-live dans les phases d'exploitation documentaire/restauration.
- `mineur / exploitation` : SMTP préprod non forcé pendant C2; les emails de suppression sont préparés via templates, mais la livraison réelle reste à auditer dans V2 emails transactionnels.

### Conditions levées pour C0
1. Mapping préprod et headers vérifiés : `Status 200`, `Headers requis: OK`.
2. C1, C2 et C3 finalisés avec preuves horodatées.
3. Aucune dette critique ouverte ne reste sans décision explicite dans le périmètre C0.
4. Sorties finales `ready=true` archivées.

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
- `docs/private/recette-preprod-migration-privee/18-c2-deletion-cron-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/19-c2-phpunit-private-suite-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/20-c3-local-backup-restore-cli-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/21-c3-preprod-backup-restore-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/22-c0-security-checklist-final-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/23-c0-migration-dod-final-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/24-c0-m5-plan-final-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/25-c0-m6-retirement-final-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/26-c0-check-security-headers-preprod-final-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/12-c1-c2-c3-tests-refresh.txt`
- `docs/private/recette-preprod-migration-privee/12-c1-c2-c3-manuel-preprod-unavailable.txt`

## Prochaine action

C0 est ferme en `GO C0`.

Les prochaines actions appartiennent aux phases suivantes du plan :

- C4 : durcissement CSP et retrait progressif de `style-src 'unsafe-inline'` ;
- C5 : antivirus ou quarantaine documentaire ;
- V1-V5 : validations d'exploitation restant suivies hors C0.
