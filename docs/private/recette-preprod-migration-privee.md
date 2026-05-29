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
| 2026-05-29 | Auto (CLI) | C6 inventaire `anonymize`, `anonymized`, `anonymous` apres nettoyage | traces restantes classees alias internes ou route legacy bloquee, aucun texte visible applicatif | [46-c6-inventory-after-cleanup-2026-05-29.txt](./recette-preprod-migration-privee/46-c6-inventory-after-cleanup-2026-05-29.txt) |
| 2026-05-29 | Auto (PHPUnit) | `php vendor/bin/phpunit tests/PrivatePortal/PrivacyOperationsTest.php tests/PrivatePortal/PrivateLegacyRetirementTest.php tests/PrivatePortalMembersTest.php` | `16 tests`, `405 assertions`, OK | [47-c6-phpunit-privacy-legacy-2026-05-29.txt](./recette-preprod-migration-privee/47-c6-phpunit-privacy-legacy-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | C6 `security-checklist` local | `success=true`, `19/19 checks` | [48-c6-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/48-c6-security-checklist-local-2026-05-29.json) |
| 2026-05-29 | Auto (deploiement) | C6 deploy preprod `deploy-fast --all-changes` | deploiement OK, `deploy-fast completed` | [49-c6-deploy-preprod-2026-05-29.txt](./recette-preprod-migration-privee/49-c6-deploy-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI preprod) | C6 `security-checklist` preprod | `success=true`, `19/19 checks` | [50-c6-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/50-c6-security-checklist-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP preprod) | C6 `check-security-headers` sur `https://preprod.lescaramagnols.com` | `Status 200`, `Headers requis: OK` | [51-c6-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/51-c6-check-security-headers-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | C7 inventaire templates prives | `29` templates controles, aucune occurrence interdite | [52-c7-private-template-inventory-2026-05-29.txt](./recette-preprod-migration-privee/52-c7-private-template-inventory-2026-05-29.txt) |
| 2026-05-29 | Auto (PHPUnit) | C7 syntaxe, PHPCS, `PrivateTemplateGuardTest`, tests locatifs | `52 tests`, `556 assertions`, OK | [53-c7-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/53-c7-local-validation-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | C7 `migration-dod` local | `success=true`, `ready=true`, `11/11 checks` | [54-c7-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/54-c7-migration-dod-local-2026-05-29.json) |
| 2026-05-29 | Auto (CLI) | C7 `security-checklist` local | `success=true`, `19/19 checks` | [55-c7-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/55-c7-security-checklist-local-2026-05-29.json) |
| 2026-05-29 | Auto (deploiement) | C7 deploy preprod `deploy-fast --all-changes` + sync README DoD | templates locatifs et README de controle synchronises, `deploy-fast completed` | [56-c7-deploy-preprod-2026-05-29.txt](./recette-preprod-migration-privee/56-c7-deploy-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI preprod) | C7 `migration-dod` preprod | `success=true`, `ready=true`, `11/11 checks` | [57-c7-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/57-c7-migration-dod-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI preprod) | C7 `security-checklist` preprod | `success=true`, `19/19 checks` | [58-c7-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/58-c7-security-checklist-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP preprod) | C7 `check-security-headers` sur `https://preprod.lescaramagnols.com` | `Status 200`, `Headers requis: OK` | [59-c7-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/59-c7-check-security-headers-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | V1 sauvegarde representative locale avec seuil force | ZIP genere, warning `backup_recommended_size_exceeded`, droits `0700/0600`, dry-run restauration OK | [60-v1-local-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/60-v1-local-representative-backup-2026-05-29.txt) |
| 2026-05-29 | Auto (PHPUnit) | V1 syntaxe, PHPCS, tests backup/suppression/DoD/securite | `10 tests`, `182 assertions`, OK | [61-v1-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/61-v1-local-validation-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | V1 `migration-dod` local | `success=true`, `ready=true`, `11/11 checks` | [62-v1-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/62-v1-migration-dod-local-2026-05-29.json) |
| 2026-05-29 | Auto (CLI) | V1 `security-checklist` local | `success=true`, `19/19 checks` | [63-v1-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/63-v1-security-checklist-local-2026-05-29.json) |
| 2026-05-29 | Auto (deploiement) | V1 deploy preprod `deploy-fast --all-changes` + sync README DoD | code backup et README de controle synchronises, `deploy-fast completed` | [64-v1-deploy-preprod-2026-05-29.txt](./recette-preprod-migration-privee/64-v1-deploy-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI preprod) | V1 sauvegarde representative preprod avec seuil force | ZIP genere, warning `backup_recommended_size_exceeded`, droits `0700/0600`, nettoyage artefacts OK | [65-v1-preprod-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/65-v1-preprod-representative-backup-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI preprod) | V1 `migration-dod` preprod | `success=true`, `ready=true`, `11/11 checks` | [66-v1-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/66-v1-migration-dod-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI preprod) | V1 `security-checklist` preprod | `success=true`, `19/19 checks` | [67-v1-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/67-v1-security-checklist-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP preprod) | V1 `check-security-headers` sur `https://preprod.lescaramagnols.com` | `Status 200`, `Headers requis: OK` | [68-v1-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/68-v1-check-security-headers-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | V3 inventaire Cron Center local | jobs prives presents, scripts autorises, commandes dry-run listees | [78-v3-cron-inventory-local-2026-05-29.json](./recette-preprod-migration-privee/78-v3-cron-inventory-local-2026-05-29.json) |
| 2026-05-29 | Auto (PHPUnit) | V3 idempotence J+20/J+30 | pas de doublon email J+20, seconde purge J+30 sans effet | [79-v3-private-account-cron-idempotence-local-2026-05-29.txt](./recette-preprod-migration-privee/79-v3-private-account-cron-idempotence-local-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | V3 dry-run purge discussions local | `success=true`, `dry_run=true`, aucune mutation | [80-v3-discussion-cron-dry-run-local-2026-05-29.json](./recette-preprod-migration-privee/80-v3-discussion-cron-dry-run-local-2026-05-29.json) |
| 2026-05-29 | Auto (CLI) | V3 dry-run Cron Center local | `success=true`, `jobs_failed=0` | [81-v3-cron-center-dry-run-local-2026-05-29.json](./recette-preprod-migration-privee/81-v3-cron-center-dry-run-local-2026-05-29.json) |
| 2026-05-29 | Auto (PHPUnit/PHPCS) | V3 validation locale | syntaxe, PHPCS, tests cron/retention OK | [82-v3-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/82-v3-local-validation-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | V3 `migration-dod` local | `success=true`, `ready=true` | [83-v3-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/83-v3-migration-dod-local-2026-05-29.json) |
| 2026-05-29 | Auto (CLI) | V3 `security-checklist` local | `success=true`, `ready=true` | [84-v3-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/84-v3-security-checklist-local-2026-05-29.json) |
| 2026-05-29 | Auto (deploiement) | V3 deploy preprod `deploy-fast --all-changes` + sync README DoD | deploiement OK | [85-v3-deploy-preprod-2026-05-29.txt](./recette-preprod-migration-privee/85-v3-deploy-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI preprod) | V3 inventaire Cron Center preprod | jobs prives presents, scripts autorises | [86-v3-cron-inventory-preprod-2026-05-29.json](./recette-preprod-migration-privee/86-v3-cron-inventory-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI preprod) | V3 dry-run Cron Center preprod | `success=true`, `jobs_failed=0` | [87-v3-cron-center-dry-run-preprod-2026-05-29.json](./recette-preprod-migration-privee/87-v3-cron-center-dry-run-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI preprod) | V3 dry-run discussions et suppressions comptes | dry-runs JSON OK | [88-v3-discussion-cron-dry-run-preprod-2026-05-29.json](./recette-preprod-migration-privee/88-v3-discussion-cron-dry-run-preprod-2026-05-29.json), [89-v3-account-deletion-cron-dry-run-preprod-2026-05-29.json](./recette-preprod-migration-privee/89-v3-account-deletion-cron-dry-run-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI preprod) | V3 `migration-dod` et `security-checklist` preprod | `success=true`, `ready=true` | [90-v3-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/90-v3-migration-dod-preprod-2026-05-29.json), [91-v3-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/91-v3-security-checklist-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP preprod) | V3 `check-security-headers` sur `https://preprod.lescaramagnols.com` | `Status 200`, `Headers requis: OK` | [92-v3-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/92-v3-check-security-headers-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (recette responsive) | V4 contrat UI local desktop/mobile | overflow global bloque, menu fixe desktop, retour mobile, messages visibles, tables scroll local | [93-v4-responsive-ui-contract-local-2026-05-29.json](./recette-preprod-migration-privee/93-v4-responsive-ui-contract-local-2026-05-29.json) |
| 2026-05-29 | Auto (PHPUnit/PHPCS/build) | V4 validation locale | syntaxe, PHPCS, `35 tests`, `341 assertions`, Stylelint et build Vite OK | [94-v4-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/94-v4-local-validation-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | V4 `migration-dod` et `security-checklist` local | `success=true`, `ready=true` | [95-v4-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/95-v4-migration-dod-local-2026-05-29.json), [96-v4-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/96-v4-security-checklist-local-2026-05-29.json) |
| 2026-05-29 | Auto (deploiement) | V4 deploy preprod `deploy-fast --all-changes` | templates BO/private et arbre frontend publie, `deploy-fast completed` | [97-v4-deploy-preprod-2026-05-29.txt](./recette-preprod-migration-privee/97-v4-deploy-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (recette responsive preprod) | V4 contrat UI preprod sur CSS compile et templates deployes | `success=true`, asset prive resolve, invariants responsive OK | [98-v4-responsive-ui-contract-preprod-2026-05-29.json](./recette-preprod-migration-privee/98-v4-responsive-ui-contract-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI preprod) | V4 `migration-dod` et `security-checklist` preprod | `success=true`, `ready=true` | [99-v4-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/99-v4-migration-dod-preprod-2026-05-29.json), [100-v4-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/100-v4-security-checklist-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP preprod) | V4 `check-security-headers` sur `https://preprod.lescaramagnols.com` | `Status 200`, `Headers requis: OK` | [101-v4-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/101-v4-check-security-headers-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI fixture locale) | V5 alerte privee backup/cron/email/CSRF/rate-limit sur logs factices isoles | `overall_severity=critical`, secrets absents du rapport | [102-v5-log-alerts-private-fixture-local-2026-05-29.json](./recette-preprod-migration-privee/102-v5-log-alerts-private-fixture-local-2026-05-29.json) |
| 2026-05-29 | Auto (PHPUnit/PHPCS) | V5 validation locale | syntaxe, PHPCS, `7 tests`, `42 assertions`, OK | [103-v5-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/103-v5-local-validation-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI) | V5 `migration-dod` et `security-checklist` local | `success=true`, `ready=true` | [104-v5-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/104-v5-migration-dod-local-2026-05-29.json), [105-v5-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/105-v5-security-checklist-local-2026-05-29.json) |
| 2026-05-29 | Auto (deploiement) | V5 deploy preprod `deploy-fast --all-changes` + sync docs | deploiement OK, `deploy-fast completed` | [106-v5-deploy-preprod-2026-05-29.txt](./recette-preprod-migration-privee/106-v5-deploy-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI fixture preprod) | V5 alerte privee backup/cron/email/CSRF/rate-limit sur logs factices isoles preprod | `overall_severity=critical`, secrets absents du rapport | [107-v5-log-alerts-private-fixture-preprod-2026-05-29.json](./recette-preprod-migration-privee/107-v5-log-alerts-private-fixture-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI preprod) | V5 `migration-dod` et `security-checklist` preprod | `success=true`, `ready=true` | [108-v5-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/108-v5-migration-dod-preprod-2026-05-29.json), [109-v5-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/109-v5-security-checklist-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP preprod) | V5 `check-security-headers` sur `https://preprod.lescaramagnols.com` | `Status 200`, `Headers requis: OK` | [110-v5-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/110-v5-check-security-headers-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (HTTP externe preprod) | Go-live `/private/login` | `NO-GO`, `status=403`, page OVH avant PHP, formulaire prive absent | [111-go-live-private-login-http-preprod-2026-05-29.txt](./recette-preprod-migration-privee/111-go-live-private-login-http-preprod-2026-05-29.txt) |
| 2026-05-29 | Auto (CLI preprod) | Go-live gates `security-checklist`, `migration-dod`, `m5-plan`, `m6-retirement` | `success=true`, `ready=true` | [112-go-live-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/112-go-live-security-checklist-preprod-2026-05-29.json), [113-go-live-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/113-go-live-migration-dod-preprod-2026-05-29.json), [114-go-live-m5-plan-preprod-2026-05-29.json](./recette-preprod-migration-privee/114-go-live-m5-plan-preprod-2026-05-29.json), [115-go-live-m6-retirement-preprod-2026-05-29.json](./recette-preprod-migration-privee/115-go-live-m6-retirement-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (CLI HTTP/ops preprod) | Go-live headers + observabilite logs reels 60 min | headers OK, `check_log_alerts` exit `0`, `overall_severity=ok`, aucune alerte | [116-go-live-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/116-go-live-check-security-headers-preprod-2026-05-29.txt), [117-go-live-log-alerts-preprod-2026-05-29.json](./recette-preprod-migration-privee/117-go-live-log-alerts-preprod-2026-05-29.json) |
| 2026-05-29 | Auto (decision) | Decision go-live exploitation privee | `NO-GO` tant que `/private/login` externe retourne `403` | [118-go-live-decision-exploitation-2026-05-29.txt](./recette-preprod-migration-privee/118-go-live-decision-exploitation-2026-05-29.txt) |
| 2026-05-29 | Auto (sync docs) | Synchronisation runbook preprod apres decision No-Go | runbook preprod mis a jour | [119-go-live-doc-sync-preprod-2026-05-29.txt](./recette-preprod-migration-privee/119-go-live-doc-sync-preprod-2026-05-29.txt) |

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
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/27-c4-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/28-c4-deploy-preprod-2026-05-29.txt 2>&1
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/29-c4-check-security-headers-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/30-c4-security-checklist-preprod-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/36-c4-deploy-preprod-final-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/37-c4-security-checklist-preprod-final-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/38-c5-security-checklist-local-2026-05-29.json
php backend/vendor/bin/phpunit tests/PrivatePortalStorageTest.php tests/PrivatePortal/PrivateSecurityChecklistTest.php > docs/private/recette-preprod-migration-privee/39-c5-phpunit-storage-checklist-2026-05-29.txt 2>&1
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/41-c5-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/42-c5-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/43-c5-check-security-headers-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php -r 'require \"core/bootstrap.php\"; (new Caramagnols\\PrivatePortal\\Documents\\PrivateDocumentRepository(editorial_database()))->ensureSchema(); echo \"private_document_schema_ok\n\";'" > docs/private/recette-preprod-migration-privee/44-c5-schema-preprod-2026-05-29.txt
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/45-c5-security-checklist-preprod-final-2026-05-29.json
{ printf 'Phase C6 - inventaire apres nettoyage\n\n'; printf 'Occurrences restantes compatibles:\n'; rg -n "anonymize|anonymized|anonymous|anonymise|anonymisee|anonymisé|anonymisée" backend/src backend/templates backend/core backend/tests docs/private/README.md docs/private/recette-preprod-migration-privee.md || true; printf '\nTextes visibles applicatifs:\n'; rg -n "anonymize|anonymized|anonymous|anonymise|anonymisee|anonymisé|anonymisée" backend/templates backend/lang backend/public || printf 'Aucune occurrence visible applicative.\n'; } | tee docs/private/recette-preprod-migration-privee/46-c6-inventory-after-cleanup-2026-05-29.txt
php backend/vendor/bin/phpunit tests/PrivatePortal/PrivacyOperationsTest.php tests/PrivatePortal/PrivateLegacyRetirementTest.php tests/PrivatePortalMembersTest.php > docs/private/recette-preprod-migration-privee/47-c6-phpunit-privacy-legacy-2026-05-29.txt 2>&1
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/48-c6-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/49-c6-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/50-c6-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/51-c6-check-security-headers-preprod-2026-05-29.txt 2>&1
{ printf 'Phase C7 - inventaire templates prives apres encadrement\n\n'; printf 'Templates controles:\n'; find backend/templates/private -type f -name '*.php' | sort; printf '\nOccurrences interdites restantes:\n'; rg -n "(<(?:a|div|span|tr|td|summary)[^>]*(?:role=\"button\"|data-private-dialog-open|tabindex=\"0\")|onclick=|onchange=|oninput=|onkeydown=|onkeyup=|<style\b|\sstyle=|->(?:create|update|delete|save|insert|ensure|purge|restore|backup|execute|query|prepare)\s*\(|\b(?:SELECT|INSERT|UPDATE|DELETE)\s+|\bnew\s+[A-Za-z0-9_\\\\]*(?:Repository|Service)\b)" backend/templates/private -g '*.php' || printf 'Aucune occurrence interdite.\n'; } > docs/private/recette-preprod-migration-privee/52-c7-private-template-inventory-2026-05-29.txt
cd backend && { php -l templates/private/modules/real-estate-rental/properties.php; php -l templates/private/modules/real-estate-rental/units.php; php -l tests/PrivatePortal/PrivateTemplateGuardTest.php; php vendor/bin/phpcs tests/PrivatePortal/PrivateTemplateGuardTest.php; php vendor/bin/phpunit tests/PrivatePortal/PrivateTemplateGuardTest.php tests/PrivateApps/RealEstateRental; } > ../docs/private/recette-preprod-migration-privee/53-c7-local-validation-2026-05-29.txt 2>&1
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/54-c7-migration-dod-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/55-c7-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/56-c7-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "mkdir -p /home/lescaramgl-ssh/caramagnols-preprod/docs/private /home/lescaramgl-ssh/caramagnols-preprod/docs/security"
rsync -av docs/private/README.md ovh-boutique:/home/lescaramgl-ssh/caramagnols-preprod/docs/private/README.md >> docs/private/recette-preprod-migration-privee/56-c7-deploy-preprod-2026-05-29.txt 2>&1
rsync -av docs/security/README.md ovh-boutique:/home/lescaramgl-ssh/caramagnols-preprod/docs/security/README.md >> docs/private/recette-preprod-migration-privee/56-c7-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php migration-dod" > docs/private/recette-preprod-migration-privee/57-c7-migration-dod-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/58-c7-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/59-c7-check-security-headers-preprod-2026-05-29.txt 2>&1
TMP_DIR="$(mktemp -d backend/var/private-v1-local-XXXXXX)" && trap 'rm -rf "$TMP_DIR"' EXIT
mkdir -p "$TMP_DIR/files"
printf 'jeu de test representatif V1\n%.0s' {1..200} > "$TMP_DIR/files/document-v1.txt"
php backend/core/tools/private_migration_reconcile.php backup --target-dir="$TMP_DIR/exports" --files-root="$TMP_DIR/files" --recommended-max-bytes=1 --output="$TMP_DIR/backup-result.json"
BACKUP_JSON="$(php -r '$r=json_decode(file_get_contents($argv[1]), true); echo $r["path"] ?? "";' "$TMP_DIR/backup-result.json")"
php backend/core/tools/private_migration_reconcile.php verify-backup "$BACKUP_JSON" --recommended-max-bytes=1 --output="$TMP_DIR/verify.json"
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/62-v1-migration-dod-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/63-v1-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/64-v1-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "mkdir -p /home/lescaramgl-ssh/caramagnols-preprod/docs/private /home/lescaramgl-ssh/caramagnols-preprod/docs/security"
rsync -av docs/private/README.md ovh-boutique:/home/lescaramgl-ssh/caramagnols-preprod/docs/private/README.md >> docs/private/recette-preprod-migration-privee/64-v1-deploy-preprod-2026-05-29.txt 2>&1
rsync -av docs/security/README.md ovh-boutique:/home/lescaramgl-ssh/caramagnols-preprod/docs/security/README.md >> docs/private/recette-preprod-migration-privee/64-v1-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php migration-dod" > docs/private/recette-preprod-migration-privee/66-v1-migration-dod-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/67-v1-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/68-v1-check-security-headers-preprod-2026-05-29.txt 2>&1
php backend/vendor/bin/phpunit tests/PrivatePortal/PrivateTransactionalEmailTest.php tests/PrivatePortalMembersTest.php tests/AdminControllerTest.php --filter 'PrivateMembersEmailTab|PrivateTransactionalEmail|InviteCreates|InviteActivation|TokensAreSingleUse' > docs/private/recette-preprod-migration-privee/69-v2-local-validation-2026-05-29.txt 2>&1
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/71-v2-migration-dod-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/72-v2-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/73-v2-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php migration-dod" > docs/private/recette-preprod-migration-privee/75-v2-migration-dod-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/76-v2-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/77-v2-check-security-headers-preprod-2026-05-29.txt 2>&1
php backend/core/tools/purge_private_discussions.php --dry-run --json > docs/private/recette-preprod-migration-privee/80-v3-discussion-cron-dry-run-local-2026-05-29.json
php backend/core/tools/run_cron_center.php --dry-run --json > docs/private/recette-preprod-migration-privee/81-v3-cron-center-dry-run-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/83-v3-migration-dod-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/84-v3-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/85-v3-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/run_cron_center.php --dry-run --json" > docs/private/recette-preprod-migration-privee/87-v3-cron-center-dry-run-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/purge_private_discussions.php --dry-run --json" > docs/private/recette-preprod-migration-privee/88-v3-discussion-cron-dry-run-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/purge_private_account_deletion_backups.php --dry-run --json" > docs/private/recette-preprod-migration-privee/89-v3-account-deletion-cron-dry-run-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php migration-dod" > docs/private/recette-preprod-migration-privee/90-v3-migration-dod-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/91-v3-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/92-v3-check-security-headers-preprod-2026-05-29.txt 2>&1
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/95-v4-migration-dod-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/96-v4-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/97-v4-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php migration-dod" > docs/private/recette-preprod-migration-privee/99-v4-migration-dod-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/100-v4-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/101-v4-check-security-headers-preprod-2026-05-29.txt 2>&1
php backend/core/tools/check_log_alerts.php --json --strict --log-dir="$TMP_DIR" --private-email-failed-threshold=1 --private-backup-failed-threshold=1 --private-purge-failed-threshold=1 --cron-failed-threshold=1 > docs/private/recette-preprod-migration-privee/102-v5-log-alerts-private-fixture-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php migration-dod > docs/private/recette-preprod-migration-privee/104-v5-migration-dod-local-2026-05-29.json
php backend/core/tools/private_migration_reconcile.php security-checklist > docs/private/recette-preprod-migration-privee/105-v5-security-checklist-local-2026-05-29.json
REMOTE_HOST=ovh-boutique REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols-preprod/backend SITEMAP_BASE_URL=https://preprod.lescaramagnols.com bash backend/tools/deploy-fast.sh --all-changes > docs/private/recette-preprod-migration-privee/106-v5-deploy-preprod-2026-05-29.txt 2>&1
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/check_log_alerts.php --json --strict --log-dir=\\$TMP_DIR --private-email-failed-threshold=1 --private-backup-failed-threshold=1 --private-purge-failed-threshold=1 --cron-failed-threshold=1" > docs/private/recette-preprod-migration-privee/107-v5-log-alerts-private-fixture-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php migration-dod" > docs/private/recette-preprod-migration-privee/108-v5-migration-dod-preprod-2026-05-29.json
ssh ovh-boutique "cd /home/lescaramgl-ssh/caramagnols-preprod/backend && php core/tools/private_migration_reconcile.php security-checklist" > docs/private/recette-preprod-migration-privee/109-v5-security-checklist-preprod-2026-05-29.json
composer check-security-headers --working-dir=backend -- --url=https://preprod.lescaramagnols.com > docs/private/recette-preprod-migration-privee/110-v5-check-security-headers-preprod-2026-05-29.txt 2>&1
```

## Tests manuels requis (phase C1/C2/C3)

- [x] C1 — Recette sécurité privée (login, logout, expiration, CSRF refusé, compte suspendu, permission retirée, reset password, fichier sans session/sans permission) — **OK PREPROD**, preuves: [15-c1-security-manual-preprod-2026-05-29.txt](./recette-preprod-migration-privee/15-c1-security-manual-preprod-2026-05-29.txt), [16-c1-logout-rerun-preprod-2026-05-29.txt](./recette-preprod-migration-privee/16-c1-logout-rerun-preprod-2026-05-29.txt)
- [x] C2 — Suppression compte suspendu et cron J+20/J+30 — **OK PREPROD**, preuve: [18-c2-deletion-cron-preprod-2026-05-29.txt](./recette-preprod-migration-privee/18-c2-deletion-cron-preprod-2026-05-29.txt)
- [x] C3 — Restauration privée fichier+base en préprod (backup ZIP, structure ZIP, `verify-backup`, dry-run, nettoyage fixture et artefacts) — **OK PREPROD**, preuve: [21-c3-preprod-backup-restore-2026-05-29.txt](./recette-preprod-migration-privee/21-c3-preprod-backup-restore-2026-05-29.txt)

Chaque cas doit être signé dans cette section : date, opérateur, preuve (captures / logs), résultat attendu.

## Tests manuels requis (phase C4)

- [x] C4 — CSP privee durcie sans `unsafe-inline` pour `script-src` et `style-src` — **OK LOCAL + PREPROD CLI**, preuves: [27-c4-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/27-c4-security-checklist-local-2026-05-29.json), [37-c4-security-checklist-preprod-final-2026-05-29.json](./recette-preprod-migration-privee/37-c4-security-checklist-preprod-final-2026-05-29.json)
- [x] C4 — Suppression des `<style>` et `style=` dans les templates prives — **OK LOCAL**, preuve: [32-c4-inline-style-inventory-2026-05-29.txt](./recette-preprod-migration-privee/32-c4-inline-style-inventory-2026-05-29.txt)
- [x] C4 — Rendu prive desktop/mobile apres extraction CSS — **OK LOCAL HEADLESS**, preuve: [34-c4-rendu-local-headless-2026-05-29.txt](./recette-preprod-migration-privee/34-c4-rendu-local-headless-2026-05-29.txt)
- [ ] C4 — Rendu HTTP externe preprod sur `/private/login` — **BLOQUE ENVIRONNEMENT**, preuve: [35-c4-reserve-http-preprod-2026-05-29.txt](./recette-preprod-migration-privee/35-c4-reserve-http-preprod-2026-05-29.txt)

## Tests manuels requis (phase C5)

- [x] C5 — Mode sans scanner configure : comportement historique stable, statut `clean`, telechargement autorise — **OK TEST**, preuve: [39-c5-phpunit-storage-checklist-2026-05-29.txt](./recette-preprod-migration-privee/39-c5-phpunit-storage-checklist-2026-05-29.txt)
- [x] C5 — Mode scanner configure avec fichier refuse : statut `infected`, notice quarantaine, telechargement bloque en `403`, erreur technique non exposee a l'utilisateur — **OK TEST**, preuve: [39-c5-phpunit-storage-checklist-2026-05-29.txt](./recette-preprod-migration-privee/39-c5-phpunit-storage-checklist-2026-05-29.txt)
- [x] C5 — Checklist securite locale et preprod apres deploiement — **OK LOCAL + PREPROD CLI**, preuves: [38-c5-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/38-c5-security-checklist-local-2026-05-29.json), [45-c5-security-checklist-preprod-final-2026-05-29.json](./recette-preprod-migration-privee/45-c5-security-checklist-preprod-final-2026-05-29.json)
- [x] C5 — Migration schema preprod `private_documents` pour statuts de scan — **OK PREPROD**, preuve: [44-c5-schema-preprod-2026-05-29.txt](./recette-preprod-migration-privee/44-c5-schema-preprod-2026-05-29.txt)

## Tests manuels requis (phase C6)

- [x] C6 — Inventaire des traces `anonymize`, `anonymized`, `anonymous` et classement des occurrences restantes — **OK LOCAL**, preuve: [46-c6-inventory-after-cleanup-2026-05-29.txt](./recette-preprod-migration-privee/46-c6-inventory-after-cleanup-2026-05-29.txt)
- [x] C6 — Actions visibles et textes applicatifs sans anonymisation ; alias internes documentes pour compatibilite — **OK LOCAL**, preuves: [46-c6-inventory-after-cleanup-2026-05-29.txt](./recette-preprod-migration-privee/46-c6-inventory-after-cleanup-2026-05-29.txt), [47-c6-phpunit-privacy-legacy-2026-05-29.txt](./recette-preprod-migration-privee/47-c6-phpunit-privacy-legacy-2026-05-29.txt)
- [x] C6 — Routes legacy d'anonymisation bloquees et suppression/sauvegarde toujours vertes — **OK TEST**, preuve: [47-c6-phpunit-privacy-legacy-2026-05-29.txt](./recette-preprod-migration-privee/47-c6-phpunit-privacy-legacy-2026-05-29.txt)
- [x] C6 — Checklist securite locale et preprod apres deploiement — **OK LOCAL + PREPROD CLI**, preuves: [48-c6-security-checklist-local-2026-05-29.json](./recette-preprod-migration-privee/48-c6-security-checklist-local-2026-05-29.json), [50-c6-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/50-c6-security-checklist-preprod-2026-05-29.json), [51-c6-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/51-c6-check-security-headers-preprod-2026-05-29.txt)

## Tests manuels requis (phase C7)

- [x] C7 — Checklist de revue templates prives documentee dans `docs/private/README.md` — **OK DOC**, preuve: [53-c7-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/53-c7-local-validation-2026-05-29.txt)
- [x] C7 — Inventaire `backend/templates/private/**` sans style inline, handler inline, pseudo-bouton, acces SQL/base, instanciation service/repository ni operation d'ecriture — **OK LOCAL**, preuve: [52-c7-private-template-inventory-2026-05-29.txt](./recette-preprod-migration-privee/52-c7-private-template-inventory-2026-05-29.txt)
- [x] C7 — Ecarts corriges dans les templates locatifs : ouverture de dialogue par vrais boutons `type="button"` et fermeture `data-private-dialog-close` — **OK TEST**, preuve: [53-c7-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/53-c7-local-validation-2026-05-29.txt)
- [x] C7 — `migration-dod`, checklist securite et headers preprod apres deploiement — **OK LOCAL + PREPROD CLI/HTTP**, preuves: [54-c7-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/54-c7-migration-dod-local-2026-05-29.json), [57-c7-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/57-c7-migration-dod-preprod-2026-05-29.json), [58-c7-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/58-c7-security-checklist-preprod-2026-05-29.json), [59-c7-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/59-c7-check-security-headers-preprod-2026-05-29.txt)

## Tests manuels requis (phase V1)

- [x] V1 — Seuil de taille recommande `512 MiB` et alerte configurable `backup_recommended_size_exceeded` — **OK LOCAL + PREPROD CLI**, preuves: [60-v1-local-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/60-v1-local-representative-backup-2026-05-29.txt), [65-v1-preprod-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/65-v1-preprod-representative-backup-2026-05-29.txt)
- [x] V1 — ZIP genere et verifie avec jeu de test representatif, dry-run restauration fichier/base OK — **OK LOCAL + PREPROD CLI**, preuves: [60-v1-local-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/60-v1-local-representative-backup-2026-05-29.txt), [65-v1-preprod-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/65-v1-preprod-representative-backup-2026-05-29.txt)
- [x] V1 — Droits fichiers/dossiers `0600/0700` controles et chemin hors webroot conserve — **OK LOCAL + PREPROD CLI**, preuves: [60-v1-local-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/60-v1-local-representative-backup-2026-05-29.txt), [65-v1-preprod-representative-backup-2026-05-29.txt](./recette-preprod-migration-privee/65-v1-preprod-representative-backup-2026-05-29.txt)
- [x] V1 — Retention suppression compte documentee et suppression J+30 prouvee par test unitaire — **OK TEST**, preuve: [61-v1-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/61-v1-local-validation-2026-05-29.txt)
- [x] V1 — `migration-dod`, checklist securite et headers preprod apres deploiement — **OK LOCAL + PREPROD CLI/HTTP**, preuves: [62-v1-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/62-v1-migration-dod-local-2026-05-29.json), [66-v1-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/66-v1-migration-dod-preprod-2026-05-29.json), [67-v1-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/67-v1-security-checklist-preprod-2026-05-29.json), [68-v1-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/68-v1-check-security-headers-preprod-2026-05-29.txt)

## Tests manuels requis (phase V2)

- [x] V2 — Catalogue des emails transactionnels affiche en BO avec variables communes, variables par modele et fallback sujet/corps — **OK TEST + PREPROD CLI**, preuves: [69-v2-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/69-v2-local-validation-2026-05-29.txt), [74-v2-mail-template-catalog-preprod-2026-05-29.json](./recette-preprod-migration-privee/74-v2-mail-template-catalog-preprod-2026-05-29.json)
- [x] V2 — Apercu admin sans envoi reel pour invitation et reset avec URL absolues preprod — **OK TEST + PREPROD CLI**, preuves: [70-v2-mail-template-catalog-local-2026-05-29.json](./recette-preprod-migration-privee/70-v2-mail-template-catalog-local-2026-05-29.json), [74-v2-mail-template-catalog-preprod-2026-05-29.json](./recette-preprod-migration-privee/74-v2-mail-template-catalog-preprod-2026-05-29.json)
- [x] V2 — Erreurs SMTP et logs securite sans fuite de token, mot de passe ou secret — **OK TEST**, preuve: [69-v2-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/69-v2-local-validation-2026-05-29.txt)
- [x] V2 — `migration-dod`, checklist securite et headers preprod apres deploiement — **OK LOCAL + PREPROD CLI/HTTP**, preuves: [71-v2-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/71-v2-migration-dod-local-2026-05-29.json), [75-v2-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/75-v2-migration-dod-preprod-2026-05-29.json), [76-v2-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/76-v2-security-checklist-preprod-2026-05-29.json), [77-v2-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/77-v2-check-security-headers-preprod-2026-05-29.txt)

## Tests manuels requis (phase V3)

- [x] V3 — Inventaire des actions Cron Center privees et scripts autorises — **OK LOCAL + PREPROD CLI**, preuves: [78-v3-cron-inventory-local-2026-05-29.json](./recette-preprod-migration-privee/78-v3-cron-inventory-local-2026-05-29.json), [86-v3-cron-inventory-preprod-2026-05-29.json](./recette-preprod-migration-privee/86-v3-cron-inventory-preprod-2026-05-29.json)
- [x] V3 — Dry-run des actions sensibles : Cron Center, suppression comptes, purge discussions — **OK LOCAL + PREPROD CLI**, preuves: [80-v3-discussion-cron-dry-run-local-2026-05-29.json](./recette-preprod-migration-privee/80-v3-discussion-cron-dry-run-local-2026-05-29.json), [81-v3-cron-center-dry-run-local-2026-05-29.json](./recette-preprod-migration-privee/81-v3-cron-center-dry-run-local-2026-05-29.json), [87-v3-cron-center-dry-run-preprod-2026-05-29.json](./recette-preprod-migration-privee/87-v3-cron-center-dry-run-preprod-2026-05-29.json), [88-v3-discussion-cron-dry-run-preprod-2026-05-29.json](./recette-preprod-migration-privee/88-v3-discussion-cron-dry-run-preprod-2026-05-29.json), [89-v3-account-deletion-cron-dry-run-preprod-2026-05-29.json](./recette-preprod-migration-privee/89-v3-account-deletion-cron-dry-run-preprod-2026-05-29.json)
- [x] V3 — J+20 rejoue deux fois sans doublon email et J+30 rejoue sans seconde suppression — **OK TEST**, preuve: [79-v3-private-account-cron-idempotence-local-2026-05-29.txt](./recette-preprod-migration-privee/79-v3-private-account-cron-idempotence-local-2026-05-29.txt)
- [x] V3 — Echec cron visible dans les logs et detecte par le compteur `cron_failed` — **OK TEST**, preuve: [82-v3-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/82-v3-local-validation-2026-05-29.txt)
- [x] V3 — Commande OVH exacte documentee : `* * * * * /usr/bin/php8.2 /home/lescaramgl-ssh/caramagnols/backend/core/tools/run_cron_center.php --quiet >/dev/null 2>&1` — **OK DOC**, preuve: [78-v3-cron-inventory-local-2026-05-29.json](./recette-preprod-migration-privee/78-v3-cron-inventory-local-2026-05-29.json)
- [x] V3 — `migration-dod`, checklist securite et headers preprod apres deploiement — **OK LOCAL + PREPROD CLI/HTTP**, preuves: [83-v3-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/83-v3-migration-dod-local-2026-05-29.json), [90-v3-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/90-v3-migration-dod-preprod-2026-05-29.json), [91-v3-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/91-v3-security-checklist-preprod-2026-05-29.json), [92-v3-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/92-v3-check-security-headers-preprod-2026-05-29.txt)

## Tests manuels requis (phase V4)

- [x] V4 — Checklist UI commune documentee pour BO et modules prives : overflow, navigation, messages, boutons, actions destructrices et responsive — **OK DOC**, preuve: [93-v4-responsive-ui-contract-local-2026-05-29.json](./recette-preprod-migration-privee/93-v4-responsive-ui-contract-local-2026-05-29.json)
- [x] V4 — Absence de debordement horizontal desktop/mobile et scroll local des tables sous `900px` — **OK LOCAL + PREPROD RECETTE**, preuves: [93-v4-responsive-ui-contract-local-2026-05-29.json](./recette-preprod-migration-privee/93-v4-responsive-ui-contract-local-2026-05-29.json), [98-v4-responsive-ui-contract-preprod-2026-05-29.json](./recette-preprod-migration-privee/98-v4-responsive-ui-contract-preprod-2026-05-29.json)
- [x] V4 — Menu gauche fixe sur BO et espace prive desktop, puis retour en flux normal mobile — **OK LOCAL + PREPROD RECETTE**, preuves: [93-v4-responsive-ui-contract-local-2026-05-29.json](./recette-preprod-migration-privee/93-v4-responsive-ui-contract-local-2026-05-29.json), [98-v4-responsive-ui-contract-preprod-2026-05-29.json](./recette-preprod-migration-privee/98-v4-responsive-ui-contract-preprod-2026-05-29.json)
- [x] V4 — Messages visibles en haut du viewport avec roles accessibles `status`/`alert` — **OK TEST**, preuve: [94-v4-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/94-v4-local-validation-2026-05-29.txt)
- [x] V4 — Confirmation destructive BO compte suspendu lisible, annulable, sans `onclick` inline et avec boutons accessibles — **OK TEST**, preuve: [94-v4-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/94-v4-local-validation-2026-05-29.txt)
- [x] V4 — `migration-dod`, checklist securite et headers preprod apres deploiement — **OK LOCAL + PREPROD CLI/HTTP**, preuves: [95-v4-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/95-v4-migration-dod-local-2026-05-29.json), [99-v4-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/99-v4-migration-dod-preprod-2026-05-29.json), [100-v4-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/100-v4-security-checklist-preprod-2026-05-29.json), [101-v4-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/101-v4-check-security-headers-preprod-2026-05-29.txt)

## Tests manuels requis (phase V5)

- [x] V5 — Evenements critiques prives listes et rattaches a une metrique : login, CSRF, rate limit, email, backup, backup warning, purge, cron — **OK DOC + TEST**, preuves: [102-v5-log-alerts-private-fixture-local-2026-05-29.json](./recette-preprod-migration-privee/102-v5-log-alerts-private-fixture-local-2026-05-29.json), [103-v5-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/103-v5-local-validation-2026-05-29.txt)
- [x] V5 — Severite claire `warning` / `error` / `critical` et `overall_severity` dans le rapport JSON et les notifications — **OK TEST**, preuve: [103-v5-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/103-v5-local-validation-2026-05-29.txt)
- [x] V5 — Synthese periodique des erreurs cron conservee dans `cron_failed`, avec seuil strict configurable — **OK LOCAL + PREPROD CLI**, preuves: [102-v5-log-alerts-private-fixture-local-2026-05-29.json](./recette-preprod-migration-privee/102-v5-log-alerts-private-fixture-local-2026-05-29.json), [107-v5-log-alerts-private-fixture-preprod-2026-05-29.json](./recette-preprod-migration-privee/107-v5-log-alerts-private-fixture-preprod-2026-05-29.json)
- [x] V5 — Alerte backup/cron/email testee sur logs factices isoles via `--log-dir`, sans polluer les logs reels — **OK LOCAL + PREPROD CLI**, preuves: [102-v5-log-alerts-private-fixture-local-2026-05-29.json](./recette-preprod-migration-privee/102-v5-log-alerts-private-fixture-local-2026-05-29.json), [107-v5-log-alerts-private-fixture-preprod-2026-05-29.json](./recette-preprod-migration-privee/107-v5-log-alerts-private-fixture-preprod-2026-05-29.json)
- [x] V5 — Absence de contenu sensible dans les alertes : tokens et mots de passe de fixture absents du rapport — **OK TEST**, preuve: [103-v5-local-validation-2026-05-29.txt](./recette-preprod-migration-privee/103-v5-local-validation-2026-05-29.txt)
- [x] V5 — `migration-dod`, checklist securite et headers preprod apres deploiement — **OK LOCAL + PREPROD CLI/HTTP**, preuves: [104-v5-migration-dod-local-2026-05-29.json](./recette-preprod-migration-privee/104-v5-migration-dod-local-2026-05-29.json), [108-v5-migration-dod-preprod-2026-05-29.json](./recette-preprod-migration-privee/108-v5-migration-dod-preprod-2026-05-29.json), [109-v5-security-checklist-preprod-2026-05-29.json](./recette-preprod-migration-privee/109-v5-security-checklist-preprod-2026-05-29.json), [110-v5-check-security-headers-preprod-2026-05-29.txt](./recette-preprod-migration-privee/110-v5-check-security-headers-preprod-2026-05-29.txt)

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
- `majeur / environnement` : l'accès HTTP externe préprod à `/private/login` retourne un `403` Apache/OVH avant exécution PHP pendant C4. Le déploiement applicatif, la checklist preprod CLI et les validations locales sont verts, mais le rendu privé externe préprod reste à rejouer après correction du mapping HTTP.
- `mineur / exploitation` : SMTP préprod non forcé pendant C2; point repris en V2 par audit des templates critiques, configuration SMTP privee pour le reset, apercu sans envoi et tests de non-fuite des erreurs.

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
- `docs/private/recette-preprod-migration-privee/27-c4-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/28-c4-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/29-c4-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/30-c4-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/31-c4-private-login-http-local-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/32-c4-inline-style-inventory-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/33-c4-validations-locales-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/34-c4-rendu-local-headless-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/35-c4-reserve-http-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/36-c4-deploy-preprod-final-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/37-c4-security-checklist-preprod-final-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/38-c5-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/39-c5-phpunit-storage-checklist-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/40-c5-frontend-build-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/41-c5-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/42-c5-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/43-c5-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/44-c5-schema-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/45-c5-security-checklist-preprod-final-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/46-c6-inventory-after-cleanup-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/47-c6-phpunit-privacy-legacy-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/48-c6-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/49-c6-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/50-c6-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/51-c6-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/52-c7-private-template-inventory-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/53-c7-local-validation-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/54-c7-migration-dod-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/55-c7-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/56-c7-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/57-c7-migration-dod-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/58-c7-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/59-c7-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/60-v1-local-representative-backup-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/61-v1-local-validation-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/62-v1-migration-dod-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/63-v1-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/64-v1-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/65-v1-preprod-representative-backup-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/66-v1-migration-dod-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/67-v1-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/68-v1-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/69-v2-local-validation-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/70-v2-mail-template-catalog-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/71-v2-migration-dod-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/72-v2-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/73-v2-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/74-v2-mail-template-catalog-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/75-v2-migration-dod-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/76-v2-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/77-v2-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/78-v3-cron-inventory-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/79-v3-private-account-cron-idempotence-local-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/80-v3-discussion-cron-dry-run-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/81-v3-cron-center-dry-run-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/82-v3-local-validation-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/83-v3-migration-dod-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/84-v3-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/85-v3-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/86-v3-cron-inventory-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/87-v3-cron-center-dry-run-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/88-v3-discussion-cron-dry-run-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/89-v3-account-deletion-cron-dry-run-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/90-v3-migration-dod-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/91-v3-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/92-v3-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/93-v4-responsive-ui-contract-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/94-v4-local-validation-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/95-v4-migration-dod-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/96-v4-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/97-v4-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/98-v4-responsive-ui-contract-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/99-v4-migration-dod-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/100-v4-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/101-v4-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/102-v5-log-alerts-private-fixture-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/103-v5-local-validation-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/104-v5-migration-dod-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/105-v5-security-checklist-local-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/106-v5-deploy-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/107-v5-log-alerts-private-fixture-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/108-v5-migration-dod-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/109-v5-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/110-v5-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/111-go-live-private-login-http-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/112-go-live-security-checklist-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/113-go-live-migration-dod-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/114-go-live-m5-plan-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/115-go-live-m6-retirement-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/116-go-live-check-security-headers-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/117-go-live-log-alerts-preprod-2026-05-29.json`
- `docs/private/recette-preprod-migration-privee/118-go-live-decision-exploitation-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/119-go-live-doc-sync-preprod-2026-05-29.txt`
- `docs/private/recette-preprod-migration-privee/12-c1-c2-c3-tests-refresh.txt`
- `docs/private/recette-preprod-migration-privee/12-c1-c2-c3-manuel-preprod-unavailable.txt`

## Prochaine action

V5 est ferme cote code applicatif, alertes privees locales/preprod, validations locales, deploiement preprod et controles preprod CLI/HTTP.

Le plan de correction des dettes migration privee est ferme pour les phases C0 a C7 et V1 a V5.

Decision go-live exploitation 2026-05-29 : **NO-GO**.

Raison : le controle HTTP externe preprod `/private/login` retourne `403` OVH avant execution PHP, sans formulaire prive.

Condition de levee : corriger le mapping Apache/OVH pour router `/private/*` vers `backend/public/index.php`, puis rejouer les preuves `111` a `117`.
