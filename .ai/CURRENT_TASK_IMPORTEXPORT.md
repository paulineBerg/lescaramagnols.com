# Tache en cours - Finalisation Document Hub (Import/Export/Archivage)

## Demande originale

Terminer l'implémentation du Document Hub selon les documents :
- `backend/docs/import-export-optimisation.md` (source de vérité principale)
- `backend/docs/IMPLEMENTATION_IMPORTEXPORT_SUMMARY_2026-07-18.md` (état actuel)
- `backend/docs/prompt-optimisation-import-export-locations-immobilieres.md` (prompt d'origine)

La demande consiste à finaliser toutes les phases restantes du chantier documentaire centralisé pour les webapps privées.

## Projet et périmètre

- **Projet** : Les Caramagnols (site public, admin, espace privé, webapps privées)
- **Périmètre** : **Finalisation du Document Hub** - implémentation des phases restantes, migration, tests complets et documentation
- **Branche** : `restore-prod-master-20260716`
- **Source de vérité** : production `https://www.lescaramagnols.com/` (référence fonctionnelle)
- **Cible admin** : accessible via `ADMIN_LOGIN_PATH` (ne jamais coder `espace-admin-7k9m2p` en dur)
- **Module central** : `backend/src/PrivateApps/Documents/` (Document Hub étendu)

### Exclusions critiques
- pas de déploiement en production
- pas d'écriture en base de production
- pas de modification de `ADMIN_LOGIN_PATH`
- pas de suppression de données existantes sans sauvegarde et migration vérifiée
- pas de contact direct avec OVH ou les données de production
- respecter l'invariant : Local -> Production = CODE SEULEMENT

## Niveau de routage

**C** - Tâche de sécurité et architecture touchant :
- Système documentaire central (import, stockage, archivage, export, sauvegarde)
- Schema SQL et migrations de base de données
- Migration de données existantes (rental_documents, private_documents)
- Intégration multi-webapps (RealEstateRental, TaxDeclarationHelper, etc.)
- Cycle de vie des documents et politique de rétention
- Export et sauvegarde avec vérification d'intégrité

## Agent autorisé à modifier

**Codex** - Niveau C : Codex est l'unique agent autorisé à modifier le code. Mistral reste en lecture seule pour l'analyse initiale. Claude Code intervient en lecture seule pour l'analyse d'architecture et de sécurité.

## Contraintes et exclusions

- **Ne pas modifier** : Aucun code applicatif sans analyse préalable complète et validation de l'architecture par Claude
- **Ne pas déployer** : Pas de déploiement sans demande explicite et validation complète
- **Ne pas contacter la production** : Pas d'accès direct à OVH ou aux données de production
- **Ne pas supprimer** : Aucune donnée, table ou fichier existant sans sauvegarde, migration et vérification
- **Respecter AGENTS.md** : Production = source de vérité; toute évolution touchant routes, menus, contenus publics, assets visibles, sécurité HTTP ou espace admin doit être vérifiée contre la production
- **Secrets** : Aucune valeur sensible (mot de passe, token, session, TOTP, clé API, DSN, etc.) ne doit être stockée dans les logs, les fichiers ou le code
- **Propriété intellectuelle** : Ne jamais versionner de contenu éditorial, documents, pièces jointes ou données utilisateurs réelles
- **Stockage Runtime Privé** : Respecter la séparation code/runtime OVH (`/home/lescaramgl-ssh/caramagnols-runtime/private-storage/`)

## Inventaire Mistral

### État actuel du Document Hub (basé sur IMPLEMENTATION_SUMMARY_2026-07-18.md)

#### Architecture implémentée
- **Schéma SQL central** : 7 tables créées (`private_document_objects`, `private_document_library`, `private_document_links`, `private_document_versions`, `private_document_derivatives`, `private_document_import_jobs`, `private_document_taxonomy`)
- **Services noyau** : DocumentPolicy, DocumentValidationService, DocumentStorageService, DocumentHubRepository, DocumentTaxonomyRepository, DocumentClassificationService, DocumentLinkService, DocumentImportService
- **HTTP** : DocumentHubController avec routes hub, composant réutilisable `document-import.php`
- **Centre de documents** : Écran Bibliothèque avec recherche et filtres
- **Cycle de vie** : active -> closed -> archived -> pending_deletion -> deleted + trashed + legal_hold
- **Nouveaux services (2026-07-18)** : DocumentDerivativeService, DocumentExportJobService, DocumentHubBackupExtension
- **Nouveaux CLI** : document_hub_maintenance.php, document_hub_backup.php

#### Webapps privées identifiées
1. **RealEstateRental** - `backend/src/PrivateApps/RealEstateRental/`
   - Intégration DocumentIntegration.php partiellement implémentée
   - Stub `upload_not_yet_implemented` remplacé dans handleRentalDocuments()
   - Liste fusionnée legacy + hub
   - À faire : intégrer composant dans onglets charges/baux/locataires/état des lieux
2. **Documents** - `backend/src/PrivateApps/Documents/`
   - Module central étendu en Document Hub
   - Intégration `user.personal` implantée
3. **BlocNote** - `backend/src/PrivateApps/BlocNote/`
   - Pas de fichiers à gérer (vérifié)
4. **FamilyDiscussion** - `backend/src/PrivateApps/FamilyDiscussion/`
   - Exception chiffrée documentée, reste sur son stockage existant
5. **TaxDeclarationHelper** - `backend/src/PrivateApps/TaxDeclarationHelper/`
   - À faire : intégration type `tax.year` + profils justificatifs fiscaux

#### Points d'intégration existants
- **Ancien système Documents** : `PrivateDocumentStorage` avec `private_documents`, `private_document_categories`
- **RealEstateRental documents** : table `rental_documents` avec FK figées (property/unit/lease/expense)
- **RealEstateRental générés** : table `rental_generated_documents` (quittances avec sha256)
- **FamilyDiscussion** : `DiscussionAttachmentStorage` chiffré AES-256-GCM

#### États des phases (selon import-export-optimisation.md)

| Phase | État | Details |
|-------|------|---------|
| Phase 0-2 | ✅ Complété | Audit, plan, schéma SQL, contrats génériques |
| Phase 3 | ✅ Complété | Services noyau (7 services) |
| Phase 4 | ✅ Complété | HTTP + composant d'import unique |
| Phase 5 | ⚠️ Partiel | Centre de documents (aperçus et doublons UI restants) |
| Phase 6 | ⚠️ Partiel | Intégration RealEstateRental (onglets à finaliser) |
| Phase 7 | ⚠️ Partiel | Intégrations autres modules (TaxDeclarationHelper reste) |
| Phase 8 | ⚠️ Partiel | Migration dry-run exécutée, --apply à faire après sauvegarde |
| Phase 9 | ⚠️ Partiel | Archivage logique implémenté, dérivés UI à faire |
| Phase 10 | ⚠️ Partiel | Export ZIP structuré, tâches de fond à intégrer |
| Phase 11 | ⚠️ Partiel | Sauvegarde étendue, cron center à brancher |
| Phase 12 | ⚠️ Partiel | Intégrité/GC configurés, admin page à finaliser |
| Phase 13 | ⚠️ Partiel | Tests unitaires/intégration OK, tests E2E à terminer |

#### Fichiers clés existants

```
backend/src/PrivateApps/Documents/
├── Contract/
│   ├── DocumentEntityRef.php
│   ├── DocumentEntityType.php
│   ├── DocumentEntityResolver.php
│   ├── DocumentImportProfile.php
│   └── DocumentIntegration.php
├── Http/
│   └── DocumentHubController.php
├── Registry/
│   └── DocumentIntegrationRegistry.php
├── Repository/
│   ├── DocumentHubRepository.php
│   └── DocumentTaxonomyRepository.php
├── Service/
│   ├── DocumentPolicy.php
│   ├── DocumentValidationService.php
│   ├── DocumentStorageService.php
│   ├── DocumentClassificationService.php
│   ├── DocumentLinkService.php
│   ├── DocumentImportService.php
│   ├── DocumentDerivativeService.php (NOUVEAU 2026-07-18)
│   ├── DocumentExportService.php
│   ├── DocumentExportJobService.php (NOUVEAU 2026-07-18)
│   └── DocumentHubBackupExtension.php (NOUVEAU 2026-07-18)
└── PrivateAppManifest.php

backend/core/tools/
├── document_hub_migrate.php
├── document_hub_integrity.php
├── document_hub_gc.php
├── document_hub_maintenance.php (NOUVEAU 2026-07-18)
└── document_hub_backup.php (NOUVEAU 2026-07-18)

backend/sql/private/
├── private_document_objects.sql
├── private_document_library.sql
├── private_document_links.sql
├── private_document_versions.sql
├── private_document_derivatives.sql
├── private_document_import_jobs.sql
├── private_document_taxonomy.sql
└── document_hub_cron_jobs.sql (NOUVEAU)

backend/templates/private/components/
└── document-import.php
```

#### Commandes de validation disponibles
- `composer --working-dir=backend test` (706+ tests)
- `composer --working-dir=backend phpstan` (niveau 5 avec baseline)
- `composer --working-dir=backend phpcs` (PSR-12)
- `php backend/core/tools/document_hub_integrity.php --dry-run --json`
- `php backend/core/tools/document_hub_migrate.php --dry-run --json`
- `php backend/core/tools/document_hub_backup.php --target=/tmp/test-backup --dry-run`

#### État du git status
```
 M .ai/prompts/ROUTER.md
 M backend/config/config.php
 M backend/docs/audit-sql-2026-07-17.md
 M backend/src/Http/FrontController.php
 M backend/src/Logging/AppEventLogger.php
 M backend/src/Logging/SqlLogStore.php
 M backend/src/PrivateApps/RealEstateRental/AgencyManagement/Repository/AgencyImportRepository.php
 M backend/src/PrivateApps/RealEstateRental/Http/RealEstateRentalController.php
 M backend/templates/private/modules/real-estate-rental/agency-imports.php
 M backend/tests/FrontControllerHttpTest.php
 M backend/tests/SqlLogStoreTest.php
?? .ai/CURRENT_TASK_STORAGE.md
?? backend/sql/editorial/014_log_entries_structured_fields.sql
?? backend/src/Logging/LogSanitizer.php
?? nul
```

**Note** : Des modifications de journalisation (CURRENT_TASK.md existant) sont en cours. Ne pas interférer. Le travail Document Hub doit coexister et être indépendant.

### Checklist des tâches restantes par priorité

#### Priorité Haute (bloquantes)
- [ ] Exécuter migration `--apply` après sauvegarde vérifiée (Phase 8)
- [ ] Intégrer composant d'import dans onglets RealEstateRental (Phase 6)
- [ ] Enregistrer quittances générées comme objets hub (Phase 6)
- [ ] Basculer upload personnel historique vers DocumentImportService (Phase 7)

#### Priorité Moyenne
- [ ] Implémenter TaxDeclarationHelper integration (Phase 7)
- [ ] Brancher intégrité + GC au cron center (Phase 12)
- [ ] Bascule lectures restantes vers hub + retrait code legacy (Phase 8)
- [ ] Export portable complet par webapp (Phase 10)
- [ ] Page d'administration Document Hub (Phase 12)
- [ ] Intégrer DocumentDerivativeService dans l'UI (Phase 9)
- [ ] Signalement de doublon dans l'UI (Phase 5)

#### Priorité Basse
- [ ] Aperçus et versions dans l'UI (Phase 5)
- [ ] Bout en bout complet (Phase 13)
- [ ] `composer test` complet (blocage : PrivateRouteResolverTest)
- [ ] Documentation `docs/private/` mise à jour (Phase 13)
- [ ] Retrait du code mort après validation complète (Phase 13)

## Analyse d'architecture Claude

*Analyse effectuée le 2026-07-19 par Claude Code, en lecture seule, sur le code réel du dépôt.*

### Vérifications requises
1. **Compatibilité migration** : Vérifier que `deploy-schema-sync` gère correctement les ALTER TABLE sur tables existantes
2. **Performance OVH** : Évaluer l'impact des nouveaux index sur les écritures en production
3. **Intégration RealEstateRental** : Valider que les modifications du contrôleur n'interfèrent pas avec le WIP utilisateur existant
4. **Cycle de vie** : Confirmer que les transitions de statut respectent les invariants (pas de suppression physique tant que liens/versions/rétention/gel existent)
5. **Sécurité** : Vérifier que la déduplication ne révèle pas à un utilisateur qu'un autre possèe le même fichier (autorisations sur document logique, jamais sur objet physique)

### Réponses aux vérifications requises (2026-07-19)

1. **Compatibilité migration — `deploy-schema-sync` ne fait AUCUN ALTER sur les tables privées.**
   `sync_private_schema()` (`backend/core/tools/sync_deploy_schema.php:162-196`) ne fait que créer
   les tables manquantes déclarées en `CREATE TABLE IF NOT EXISTS` dans `backend/sql/private/*.sql`.
   `DocumentHubRepository::ensureSchema()` fait de même côté runtime. Conséquence : toute évolution
   de colonne/index sur une table hub déjà existante en production ne sera **pas** propagée.
   Contrainte pour Codex : ne pas modifier la définition des 7 tables existantes ; si une colonne
   devient nécessaire, prévoir un mécanisme explicite (vérification de colonne au runtime ou
   procédure documentée), et garder `backend/sql/private/*.sql` strictement synchrone de `ensureSchema()`.

2. **Performance OVH** : les index déclarés (`sha256` unique, `storage_key` unique, index statut/date)
   sont proportionnés à la volumétrie familiale ; pas de risque d'écriture notable. Le vrai risque
   de charge est le cron d'intégrité planifié `*/4 * * * *` (toutes les 4 minutes) dans
   `document_hub_cron_jobs.sql`, alors que `document_hub_integrity.php` re-hache **tous** les objets
   (SHA-256 complet) à chaque passage. Sur mutualisé OVH c'est excessif : passer à une fréquence
   quotidienne (ou incrémentale via `integrity_checked_at`, l'index existe déjà).

3. **Intégration RealEstateRental — conflit WIP confirmé.**
   `RealEstateRentalController.php` porte 139 lignes de modifications non commitées et
   `FrontController.php` 59 lignes (tâche journalisation en cours, cf. note Mistral). La Phase 6
   touche ce même contrôleur (`handleRentalDocuments`, onglets). Ordonnancement impératif : la tâche
   journalisation doit être terminée/commitée avant que Codex ne modifie ce fichier ; à défaut,
   limiter la Phase 6 aux fichiers non concernés (templates onglets, `RentalDocumentIntegration.php`).

4. **Cycle de vie — conforme, avec deux réserves sur le GC.**
   `DocumentHubRepository::transitionStatus()` (ligne 572) applique une matrice stricte :
   `legal_hold` bloque `trashed` et `pending_deletion` ; retour `active` possible depuis
   trashed/archived/closed. La purge physique est protégée par `objectReferenceCount()` qui
   retourne `PHP_INT_MAX` en cas de doute (bon réflexe défensif). Réserves :
   - **Course GC/import** : fenêtre entre `promoteFromQuarantine()` et `createDocument()` pendant
     laquelle un objet a 0 référence SQL ; un GC `--delete-unreferenced` concurrent peut supprimer
     le fichier d'un import en cours malgré la re-vérification (`DocumentGarbageCollector.php:52-58`).
     Correctif recommandé : seuil d'âge (ne jamais supprimer un objet créé depuis moins de 24 h).
   - Le GC supprime le fichier mais laisse la ligne `private_document_objects` : le contrôle
     d'intégrité suivant signalera des `missing_file` attendus. Soit marquer/supprimer la ligne,
     soit documenter ce comportement. Accessoirement `allObjects()` est plafonné à 10 000 sans
     boucle de pagination dans le GC (acceptable à cette volumétrie, à noter).

5. **Sécurité déduplication — correcte au téléchargement, à surveiller à l'import.**
   Les autorisations portent bien sur le document logique : `DocumentLinkService::userCanAccessDocument()`
   (créateur ou entité liée accessible via resolver), jamais sur l'objet physique ; le contrôleur hub
   vérifie ce droit avant tout accès/stream. En revanche le résultat d'import expose `deduplicated: true`,
   ce qui peut révéler qu'un contenu identique existe déjà ailleurs dans le hub (y compris chez un
   autre utilisateur). Pour le « signalement de doublon » (Phase 5) : ne comparer qu'aux documents
   **accessibles à l'utilisateur courant**, et ne jamais afficher la dédup inter-utilisateurs dans l'UI.

### Constats bloquants supplémentaires

- **`backend/sql/private/document_hub_cron_jobs.sql` est défectueux et dangereux en l'état** :
  1. les jobs quotidiens `document_hub_maintenance` et `document_hub_garbage_collection` passent
     `--delete-unreferenced` → suppression physique **automatique** chaque nuit, en violation de
     l'invariant « les scripts CLI ne suppriment jamais automatiquement sans mode explicite ».
     Les jobs cron doivent rester en report-only ; la suppression reste une opération manuelle
     après sauvegarde vérifiée ;
  2. la cible de backup est codée en dur sur un chemin **local dev**
     (`/home/surfacepro8/www/caramagnols/backend/private/storage/backups/...`) : en production cela
     recréerait l'arborescence interdite `backend/private/storage/` (violation de la séparation
     code/runtime). La cible doit dériver de `PRIVATE_STORAGE_ROOT`/config, jamais d'un chemin en dur ;
  3. les INSERT visent la table non préfixée `cron_jobs` alors que `CronJobRepository` utilise
     `$database->table('cron_jobs')` (préfixe `car_`) : le seed est inopérant ou vise la mauvaise table ;
  4. `document_hub_gc.php` ne connaît pas les arguments `--delete-quarantine` / `--delete-exports`
     passés par le job, et la planification `*/4 * * * *` de l'intégrité est vraisemblablement une
     erreur pour `0 */4 * * *` (voir point 2 ci-dessus).
  Ce fichier doit être corrigé **avant** tout branchement au cron center (Phase 11/12).

- **Phase 8 `--apply` : contradiction de périmètre à arbitrer.** `document_hub_migrate.php --apply`
  écrit en base et dans le stockage. Les données réelles étant en production OVH, l'exécution utile
  contredit les exclusions de cette tâche (« pas d'écriture en base de production », « pas de contact
  OVH »). Périmètre Codex : dry-run et apply **locaux uniquement** ; l'exécution production est une
  opération séparée (humaine, via SSH, après sauvegarde vérifiée) à autorisation explicite, hors tâche.
  Dépendance d'ordre : en production, `--apply` échouerait tant que le mode legacy storage est actif
  (écriture bloquée `legacy_mode_readonly`) → la tâche STORAGE (étape 8 du runbook) doit être finalisée d'abord.

- **Migration : idempotence bornée.** Le test « déjà migré » repose sur `documentsForEntity(..., 500)` :
  au-delà de 500 documents par entité, un re-run pourrait créer des doublons logiques (acceptable à
  volumétrie familiale, à connaître). Les lignes legacy avec `user_id`/`property_id` invalides sont
  ignorées silencieusement sans compteur dédié : ajouter un compteur `skipped` au rapport serait utile.
  Points conformes : outil copy-only (jamais de suppression des fichiers legacy), dry-run par défaut,
  gestion de la course d'unicité sha256 via le catch 23000 de `findOrCreateObject()`.

- **Phase 7 confirmée** : `DocumentsController::upload()` passe encore par
  `PrivateDocumentStorage::validateUploadedFile()` (legacy). `TaxDeclarationHelper` n'implémente pas
  `ProvidesDocumentIntegration` et n'a pas de contrôleur HTTP propre : l'intégration `tax.year`
  exige un `DocumentEntityResolver` dédié (entité = année fiscale rattachée à l'utilisateur) et une
  décision d'ancrage UI — à concevoir, pas seulement à « brancher ».

### Solution principale recommandée (ordre d'exécution révisé)

1. **Lot 0 — corriger le socle avant tout branchement** (petit, isolé, testable) :
   `document_hub_cron_jobs.sql` (préfixe, report-only, cible backup dérivée de la config, fréquences),
   seuil d'âge dans le GC, compteur `skipped` migration. C'est un préalable aux Phases 11/12.
2. **Phases 6/7** : intégrations RealEstateRental (après commit du WIP journalisation),
   upload personnel vers `DocumentImportService`, puis TaxDeclarationHelper (conception resolver incluse).
3. **Phase 8** : dry-run puis apply **en local uniquement** ; produire le runbook de migration
   production (séparé, autorisation explicite requise, après validation tâche STORAGE).
4. **Phases 5/9/10/12** : UI doublons (scopée aux documents accessibles), dérivés, exports, page admin.
5. **Phase 13** : tests, doc, retrait du code legacy uniquement après bascule des lectures validée.

### Critères d'acceptation

- `composer --working-dir=backend test -- --filter "Document"` vert ; `phpstan` et `phpcs` verts sur les fichiers touchés.
- `document_hub_migrate.php --dry-run --json` sans erreurs ; re-run `--apply` local idempotent (0 nouvelle migration au 2e passage).
- Aucun job cron ne porte de drapeau destructif ; aucun chemin en dur hors `PRIVATE_STORAGE_ROOT`/config.
- Aucune écriture hors du stockage runtime configuré ; aucune suppression de fichier legacy par les outils.
- L'UI de doublon ne révèle jamais un document non accessible à l'utilisateur courant.

### Incertitudes signalées (non inventées)

- État réel de la production (tables hub présentes ? mode legacy encore actif ?) : non vérifié depuis ce poste, nécessite `ssh ovh-boutique` (lecture seule) avant la Phase 8 production.
- Blocage `PrivateRouteResolverTest` sur `composer test` complet : rapporté par Mistral, non reproduit ici.

### Risques identifiés
- Migration `--apply` : Nécessite sauvegarde complète vérifiée avant exécution
- Déduplication : Gestion de la concurrence sur les imports simultanés du même fichier
- Stockage Runtime : Respect de la séparation `caramagnols-runtime/private-storage/` en production
- Intégrité : Vérification que les scripts CLI ne suppriment jamais automatiquement sans mode explicite
  → **confirmé non respecté par `document_hub_cron_jobs.sql` en l'état (voir constats bloquants)**

## Plan d'implémentation validé

*À compléter par Codex après validation de l'analyse Claude.*

### Ordre d'exécution recommandé

1. **Phase 8 - Migration**
   - Vérifier sauvegarde existante
   - Exécuter `document_hub_migrate.php --dry-run` sur données réelles
   - Corriger éventuels problèmes détectés
   - Exécuter `document_hub_migrate.php --apply` après validation
   - Valider migration via `document_hub_integrity.php`

2. **Phase 7 - Intégrations restantes**
   - Compléter TaxDeclarationHelper integration
   - Basculer upload personnel historique
   - Tester chaque webapp individuellement

3. **Phase 6 - Finalisation RealEstateRental**
   - Intégrer composant dans onglets charges/baux/locataires/état des lieux
   - Enregistrer quittances générées
   - Valider fusion legacy + hub

4. **Phase 12 - Administration**
   - Finaliser page d'administration
   - Brancher intégrité + GC au cron center
   - Tester notifications

5. **Phase 10 - Exports**
   - Compléter export portable complet
   - Tester construction en tâche de fond
   - Vérifier chiffrement AES-256 si disponible

6. **Phase 11 - Sauvegarde**
   - Tester DocumentHubBackupExtension
   - Configurer politique de rétention
   - Vérifier restauration dry-run

7. **Phase 13 - Tests et nettoyage**
   - Finaliser tests bout en bout
   - Corriger tests unitaires/intégration
   - Retirer code mort prouvé
   - Mettre à jour documentation

8. **Validation finale**
   - Exécuter tous les tests
   - Vérifier intégrité complète
   - Archiver les documents source

## Résultat Codex ou Mistral

*À compléter par Codex après implémentation.*

## Tests et validations

*À compléter après exécution.*

### Commandes confirmées
- `composer --working-dir=backend test`
- `composer --working-dir=backend phpstan`
- `composer --working-dir=backend phpcs`
- `php backend/core/tools/document_hub_*.php --dry-run --json`

### Commandes à exécuter avant validation
```bash
# Vérification syntaxe
find backend/src/PrivateApps/Documents -name "*.php" -exec php -l {} \;

# Tests Document Hub
composer --working-dir=backend test -- --filter "DocumentHub|DocumentImport|DocumentExport"

# Vérification intégrité
php backend/core/tools/document_hub_integrity.php --dry-run --json

# Migration dry-run
php backend/core/tools/document_hub_migrate.php --dry-run --json > /tmp/migration-dryrun-report-$(date +%Y%m%d-%H%M%S).json

# Analyse statique
composer --working-dir=backend phpstan -- --level=5
composer --working-dir=backend phpcs

# Vérification git
git diff --check
git status
```

## Revue finale

*À compléter après implémentation.*

## État

Planifié

*(passé de « À analyser » à « Planifié » le 2026-07-19 après analyse Claude ; le Lot 0 — correction de `document_hub_cron_jobs.sql` et garde-fous GC — est un préalable aux Phases 11/12, et la Phase 8 production reste hors périmètre Codex.)*

États autorisés : `À analyser`, `Planifié`, `En cours`, `À revoir`, `Terminé`, `Bloqué`.

---

*Créé : 2026-07-19*
*Agent : Mistral Vibe*
*Route par : ROUTER.md (niveau C, agent Codex)*
*Source : import-export-optimisation.md, IMPLEMENTATION_SUMMARY_2026-07-18.md, prompt-optimisation-import-export-locations-immobilieres.md*

---

## 🗂️ Archivage des documents source

### Procédure d'archivage des fichiers source après finalisation

Une fois toutes les phases terminées et validées, archiver les documents source dans une structure dédiée :

```
.ai/archives/import-export-2026-07-19/
├── CURRENT_TASK_IMPORTEXPORT.md          # Ce fichier de transmission
├── analysis/
│   ├── architecture-audit.md            # Audit initial complet
│   ├── migration-report.json             # Rapport de migration dry-run
│   └── integrity-check-*.json            # Rapports de vérification d'intégrité
├── implementation/
│   ├── phase-8-migration/                 # Scripts et logs de migration
│   ├── phase-9-archivage/                # Implémentation cycle de vie
│   ├── phase-10-exports/                  # Export et sauvegarde
│   └── phase-12-admin/                   # Interface d'administration
├── tests/
│   ├── test-results-*.json               # Résultats des tests automatisés
│   ├── coverage-report/                  # Rapport de couverture
│   └── manual-verification.md            # Vérifications manuelles effectuées
└── documentation/
    ├── final-architecture.md             # Architecture finale validée
    ├── user-guide.md                     # Guide utilisateur
    └── admin-guide.md                    # Guide administrateur
```

### Contenu à archiver

1. **Documents de conception**
   - Copie des prompts originaux
   - États des lieux initiaux
   - Décisions d'architecture
   - Schémas SQL finaux

2. **Rapports d'exécution**
   - Sorties JSON de `--dry-run` pour toutes les migrations
   - Logs des exécutions `--apply` (sans données sensibles)
   - Résultats des contrôles d'intégrité
   - Rapports de garbage collection

3. **Preuves de validation**
   - Résultats des tests unitaires/intégration/bout en bout
   - Sorties de phpstan et phpcs
   - Vérifications manuelles documentées
   - Preuves de déduplication fonctionnelle

4. **Documentation finale**
   - Architecture as-built
   - Procédures d'exploitation
   - Guide de dépannage
   - Liste des dépendances facultatives et leur état

### Exclusions de l'archivage

- ❌ Fichiers binaires ou documents réels
- ❌ Données utilisateurs ou informations personnelles
- ❌ Secrets, tokens, mots de passe
- ❌ Fichiers temporaires ou caches
- ❌ Logs de production

### Format et localisation

- **Format** : Markdown pour la documentation, JSON pour les rapports machine
- **Encodage** : UTF-8
- **Localisation** : `.ai/archives/import-export-YYYY-MM-DD/` (hors git, gitignoré)
- **Durée de conservation** : 12 mois minimum, ou jusqu'à la prochaine refonte majeure
- **Compression** : Archive ZIP optionnelle pour les gros volumes, avec `SHA256SUMS`

### Vérification de l'archivage

Pour valider l'archivage :

```bash
# Vérifier que l'archive existe et est complète
test -d .ai/archives/import-export-2026-07-19/

# Vérifier la structure
tree .ai/archives/import-export-2026-07-19/ -L 3

# Vérifier les checksums si archive ZIP
sha256sum .ai/archives/import-export-2026-07-19.zip
```

### Procédure de nettoyage post-archivage

1. Vérifier que tous les tests passent
2. Vérifier que la documentation est à jour
3. Vérifier que le code mort a été retiré
4. Archiver les fichiers sources dans `.ai/archives/`
5. Mettre à jour `.ai/README.md` avec référence à l'archive
6. Supprimer les fichiers temporaires du workspace
7. Valider avec `git status` que seul le code intentionnel reste
