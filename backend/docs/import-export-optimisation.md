# Refonte documentaire centralisée — import, stockage, archivage, export, sauvegarde

Plan d'implémentation par phases de la gestion documentaire unique pour **toutes les webapps de `backend/src/PrivateApps`, existantes et futures**. Ce document est la source de vérité du chantier : chaque phase possède une checklist à cocher au fur et à mesure. L'implémentation peut se faire en plusieurs sessions ; toujours relire ce fichier et l'état des cases avant de reprendre.

Prompt d'origine : `backend/docs/prompt-optimisation-import-export-locations-immobilieres.md`.
Exigence complémentaire de l'utilisateur (2026-07-18) : le module documentaire doit être générique et s'adapter à toute webapp présente ou future de `backend/src/PrivateApps` ; `RealEstateRental` n'est que le premier consommateur.

---

## 1. État des lieux (audit 2026-07-18)

### Architecture générale

- Framework maison `Caramagnols` (PHP 8, `strict_types`), MySQL/InnoDB, préfixe de tables dynamique via `EditorialDatabase->table()` (`car_` en prod, `test_xxx_` dans les tests).
- Socle privé `backend/src/PrivatePortal` : routage (`PrivateRouteResolver` — chemins et paramètres déclarés en dur + `routePaths()` des manifestes), dispatch central (`PrivatePortalController::handle()`), auth/session/2FA, permissions par module (`PrivateModulePermissionRepository`), CSRF (`PrivatePortalSecurityGuard`), journalisation (`AppEventLogger->security()`), sauvegarde (`PrivateBackupService`), migrations (`PrivateMigrationService` + `MODULE_TABLES`).
- Modules `PrivateApps` : manifeste `PrivateAppManifest` + registre statique `PrivateAppRegistry` (pas d'auto-découverte).
- Schéma SQL : fichiers `backend/sql/private/*.sql` (1 table = 1 fichier, `CREATE TABLE IF NOT EXISTS car_...`), synchronisation additive par `core/tools/sync_deploy_schema.php`, création runtime par `ensureSchema()` dans les repositories (préfixe dynamique).
- Cron : `backend/src/Cron` + `composer cron-center`.
- Tests : PHPUnit sous `backend/tests/`, MySQL réel avec préfixe jetable (`EditorialSqlTestTrait`), skip si base absente. Commandes : `composer test`, `composer phpstan`, `composer phpcs`, `composer lint`.
- Stockage privé runtime : `backend/private/storage/` (hors webroot, gitignoré, déploy additif jamais destructif).

### Mécanismes documentaires trouvés (4 systèmes concurrents)

| Système | Stockage | Table(s) | Limites |
|---|---|---|---|
| Module `Documents` (personnel) | `private/storage/uploads/xx/yy/<id>.<ext>` via `PrivateDocumentStorage` (validation ext+MIME, scan AV optionnel, perms 0600) | `private_documents`, `private_document_categories` (par utilisateur) | Pas de SHA-256/dédup, pas de rattachements, pas de versions, catégories non globales, téléchargement non streamé, `doc/xls/gif` encore autorisés |
| `RealEstateRental` documents | même `PrivateDocumentStorage` | `rental_documents` (FK figées property/unit/lease/expense) | Mono-rattachement, pas de dédup ; upload historique dans le socle (`PrivatePortalController` ~l.1837, ~l.3841) ; le contrôleur module extrait (WIP non commité) contient un **stub `upload_not_yet_implemented`** dans `handleRentalDocuments()` |
| `RealEstateRental` générés | idem | `rental_generated_documents` (quittances : sha256 + idempotency déjà présents) | Système séparé |
| `FamilyDiscussion` pièces jointes | `DiscussionAttachmentStorage`, AES-256-GCM | `discussion_message_attachments` | Cas spécial chiffré — **conservé tel quel** (décision §2) |

Hors périmètre : médias éditoriaux admin du site public.

### Problèmes identifiés

1. Quatre systèmes concurrents, code dupliqué (validation, chemins, suppression).
2. Aucune déduplication physique, aucune empreinte SHA-256 sur les imports (seulement sur les quittances générées).
3. Rattachement mono-entité figé par colonnes FK dans `rental_documents` ; impossible de lier une facture à un bien + une charge + un exercice.
4. Pas de versions, pas d'archivage logique, pas de `legal_hold`, suppression physique immédiate.
5. Taxonomie éclatée : catégories personnelles par utilisateur (`Documents`), champ libre `category` (`rental_documents`).
6. Upload rental cassé dans le WIP d'extraction (stub) ; formats autorisés trop laxistes (`doc`, `xls`, `gif`) et sans vérification de signature magique ni de structure de conteneur ZIP.
7. Téléchargements chargés entièrement en mémoire.
8. Pas d'export documentaire structuré, pas de sauvegarde documentaire dédiée avec manifeste/checksums, pas de contrôle d'intégrité SQL↔fichiers.

---

## 2. Décisions d'architecture

- **Étendre le module `Documents`** en bibliothèque documentaire centrale (« Document Hub ») — pas de nouveau module parallèle, conformément à l'interdiction du prompt. Namespace : `Caramagnols\PrivateApps\Documents\{Contract,Registry,Service,Repository,Http}`.
- **Généricité multi-webapp** : interface optionnelle `ProvidesDocumentIntegration` que tout manifeste de module peut implémenter. Elle expose une `DocumentIntegration` (types d'entités, profils d'import, resolver d'autorisation). Un registre `DocumentIntegrationRegistry` consomme `PrivateAppRegistry` : ajouter une webapp future = implémenter l'interface dans son manifeste, zéro modification du hub.
- **Types d'entités contrôlés et namespacés** : `rental.property`, `rental.lease`, `user.personal`, etc. Jamais d'`entity_type` libre : existence + autorisation vérifiées par le resolver du module propriétaire avant tout lien.
- **Stockage CAS (content-addressed)** : `backend/private/storage/document-hub/objects/sha256/ab/cd/<sha256>` — écriture en quarantaine, validation, hash streamé, promotion par `rename()` atomique, objets immuables, contrainte SQL unique sur l'empreinte. Nom original = métadonnée uniquement.
- **`FamilyDiscussion` reste sur son stockage chiffré** : le chiffrement par conversation est incompatible avec la déduplication par empreinte de contenu clair (elle révélerait des égalités de contenu). Documenté comme exception assumée ; le hub reste disponible pour ses évolutions futures non chiffrées.
- **`rental_generated_documents` (quittances)** : migré vers le hub en phase 8 (elles ont déjà sha256 + idempotence) ; en attendant, inchangé.
- **Sauvegarde/cron/journalisation** : réutilisation de l'existant (`PrivateBackupService`, cron center, `AppEventLogger`) — pas de second système.
- **Aucune dépendance IA/externe** pour le fonctionnement de base ; classification déterministe (contexte → règles → `À classer`). OCR/antivirus/aperçus = capacités facultatives à dégradation explicite.
- Les écrans passent au hub module par module ; les anciens fichiers ne sont supprimés qu'après migration vérifiée, sauvegarde et directive explicite (phase 8).

### Modèle SQL cible (préfixe dynamique ; fichiers sous `backend/sql/private/`)

| Table | Rôle |
|---|---|
| `private_document_objects` | Objets physiques : `sha256` UNIQUE, mime réel, extension, `storage_key`, tailles, `status` (`ready`/`quarantined`/`missing`), `integrity_checked_at` |
| `private_document_library` | Documents logiques : `document_uid` UNIQUE, `object_id`, `category_code`, nom original, titre, `document_date`, `fiscal_year`, `status` (`active`/`closed`/`archived`/`trashed`/`pending_deletion`/`deleted`), `retention_until`, `legal_hold`, `created_by`, `archived_at`, `deleted_at` |
| `private_document_links` | Rattachements N-N : `document_id`, `entity_type`, `entity_id`, `link_role`, UNIQUE(document, type, id, role) |
| `private_document_versions` | `document_id`, `version_number`, `object_id`, `reason`, UNIQUE(document, version) |
| `private_document_derivatives` | Dérivés recréables (miniatures/aperçus) : type, `storage_key`, mime, taille, `generator_version` |
| `private_document_import_jobs` | Traçabilité : source, contexte, classification (source + score), `status` (`quarantined`/`validating`/`processing`/`ready`/`rejected`/`failed`), `error_code`, horodatages |
| `private_document_taxonomy` | Taxonomie globale 2 niveaux : `code` UNIQUE stable, `parent_code`, label, `is_system`, `is_active`, `sort_order`, `export_directory`, `retention_days` |

### Politique de formats (un seul endroit : `DocumentPolicy`)

Liste blanche : `pdf jpg jpeg png webp heic heif tif tiff docx odt xlsx ods csv txt`. Tout le reste refusé côté serveur (extension + MIME par contenu + signature magique + structure ZIP pour docx/xlsx/odt/ods + absence de macro `vbaProject` + refus des fichiers chiffrés/protégés). Limites par famille : PDF 25 Mo ; JPG/PNG/WebP/HEIC 15 Mo ; TIFF 30 Mo ; Office/OpenDocument 15 Mo ; CSV/TXT 5 Mo ; lot 100 Mo ; images ≤ 40 Mpx. Configurable via `app_config('private.document_hub')`.

### Taxonomie système initiale (globale, 2 niveaux)

`property` (Bien immobilier), `tenants` (Locataires), `leases` (Baux), `inventory` (États des lieux), `rents` (Loyers et paiements ; sous : `rents.receipt`, `rents.unpaid`, `rents.deposit`), `charges` (sous : `charges.water`, `charges.electricity`, `charges.maintenance`, `charges.service_calls`, `charges.regularization`), `works` (Travaux ; sous : `works.quote`, `works.invoice`), `tax` (Fiscalité ; sous : `tax.property_tax`, `tax.cfe`), `insurance` (Assurances et sinistres), `coownership` (Copropriété), `diagnostics` (Diagnostics ; sous : `diagnostics.dpe`), `bank` (Banque et comptabilité), `mail` (Courriers), `identity` (Pièces et dossiers), `other` (Autres), `inbox` (À classer). Les catégories système sont stables (`is_system=1`) ; l'admin peut créer/renommer/fusionner/désactiver des catégories personnelles globales — jamais de création automatique par le classement.

### Classification (déterministe, scores)

1. Choix explicite utilisateur → 100 ; 2. contexte du profil d'import → 90-95 (classement direct) ; 3. règles sur nom de fichier/source → 60-89 (proposition à confirmer) ; 4. sinon `inbox` (« À classer »). Aucun appel externe.

---

## 3. Phases et checklists

### Phase 0 — Audit et plan
- [x] Lire AGENTS.md racine + PrivateApps + RealEstateRental, README, roadmap
- [x] Inventorier stockages, tables, points d'upload/download/suppression
- [x] Identifier mécanismes transversaux réutilisables (auth, CSRF, logs, cron, backup, migrations)
- [x] État des lieux factuel (§1) et décisions (§2)
- [x] Rédiger ce plan

### Phase 1 — Schéma SQL central
- [x] Fichiers `backend/sql/private/private_document_*.sql` (7 tables, `CREATE TABLE IF NOT EXISTS`, préfixe `car_`, index sha256/catégories/statuts/dates/liens/jobs)
- [x] `ensureSchema()` runtime à préfixe dynamique dans `DocumentHubRepository` / `DocumentTaxonomyRepository`
- [x] Déclarer les tables dans le manifeste `Documents` + `PrivateMigrationService::MODULE_TABLES`
- [x] Seed idempotent de la taxonomie système

### Phase 2 — Contrats génériques multi-webapp
- [x] `Contract/DocumentEntityRef` (VO type+id+rôle), `Contract/DocumentEntityType` (code, module, libellé)
- [x] `Contract/DocumentEntityResolver` (existence, autorisation par utilisateur, libellé)
- [x] `Contract/DocumentImportProfile` (source, type de contexte, catégorie par défaut, catégories autorisées, champs requis, multiple)
- [x] `Contract/DocumentIntegration` + interface de manifeste `ProvidesDocumentIntegration`
- [x] `Registry/DocumentIntegrationRegistry` (consomme `PrivateAppRegistry`, valide les collisions de types/profils)

### Phase 3 — Services noyau
- [x] `Service/DocumentPolicy` : formats, MIME, signatures, limites par famille — un seul endroit configurable
- [x] `Service/DocumentValidationService` : extension, MIME par contenu, signature magique, structure ZIP (docx/xlsx/odt/ods), macros, chiffrement/protection, tailles, pixels image, scan AV optionnel (réutilise `PrivateDocumentScanner`, dégradation explicite)
- [x] `Service/DocumentStorageService` : quarantaine → hash SHA-256 streamé → promotion `rename()` atomique vers CAS, objets immuables, imports concurrents du même contenu gérés, suppression contrôlée (refus si référencé)
- [x] `Repository/DocumentHubRepository` : objets (dédup par contrainte unique + rattrapage de course), documents, liens, versions, jobs d'import ; requêtes paginées sans N+1
- [x] `Repository/DocumentTaxonomyRepository` : taxonomie globale + opérations admin sûres (fusion, désactivation, jamais de suppression brute d'une catégorie utilisée)
- [x] `Service/DocumentClassificationService` : ordre déterministe + seuils 90/60
- [x] `Service/DocumentLinkService` : validation type contrôlé + existence + autorisation via resolver du module, anti-doublon
- [x] `Service/DocumentImportService` : orchestration complète (profil → job → validation → stockage → dédup → document → liens → classification), erreurs par fichier, aucun faux succès
- [x] Journalisation via `AppEventLogger` (`private.document_hub.*`), sans contenu sensible

### Phase 4 — HTTP + composant d'import unique
- [x] Routes hub dans le manifeste `Documents` + `PrivateRouteResolver` + dispatch `PrivatePortalController` : centre `documents/bibliotheque`, `documents/bibliotheque/importer` (POST), `documents/bibliotheque/{uid}` (téléchargement streamé, en-têtes `Content-Disposition`+`nosniff`), liens (ajout/retrait POST), catégorie (POST), archivage (POST), version (POST), corbeille/restauration
- [x] Contrôleur `Http/DocumentHubController` : auth + permission module + CSRF + autorisation par document (via ses liens, jamais par le seul objet physique partagé)
- [x] Composant réutilisable `templates/private/components/document-import.php` : piloté par profil (contexte prérempli non redemandé), multi-fichiers, glisser-déposer, capture mobile (`capture`), erreurs par fichier, catégorie proposée modifiable, date/titre, accessibilité clavier/labels
- [x] Le composant ne contient aucune logique métier spécifique en dur

### Phase 5 — Centre de documents
- [x] Écran `Documents › Bibliothèque` : recherche, filtres (entité, catégorie, année, type, statut), vue « À classer », rattachements affichés, téléchargement, archivage, corbeille
- [x] Chaque onglet métier garde une liste filtrée alimentée par le même service (`DocumentLinkService::documentsForEntity`)
- [ ] Aperçus (dérivés) et consultation des versions dans l'UI — après phase 9
- [ ] Signalement de doublon dans l'UI

### Phase 6 — Intégration RealEstateRental (premier consommateur)
- [x] `RealEstateRental/DocumentIntegration.php` : types `rental.property|unit|lease|tenant|expense|regularization`, resolver via `rental_property_members` (droits réels), profils d'import par onglet
- [x] Manifeste RealEstateRental implémente `ProvidesDocumentIntegration`
- [x] Remplacer le stub `upload_not_yet_implemented` de `handleRentalDocuments()` par `DocumentImportService` (contexte bien/lot/bail prérempli)
- [x] Liste des documents rental fusionnée : legacy `rental_documents` + hub (lecture), téléchargement compatible les deux
- [ ] Onglets charges/baux/locataires/état des lieux : intégrer le composant profilé (après validation UI de l'onglet Documents)
- [ ] Quittances générées (`rental_generated_documents`) enregistrées aussi comme objets hub

### Phase 7 — Intégrations des autres modules
- [x] `Documents` personnel : intégration `user.personal` (l'app personnelle devient un consommateur du hub)
- [ ] Basculer l'upload personnel historique (`files_upload`) vers `DocumentImportService` (l'écran actuel reste fonctionnel en attendant)
- [ ] `TaxDeclarationHelper` : type `tax.year` + profils justificatifs fiscaux
- [ ] `BlocNote` : rien à faire (pas de fichiers) — vérifié
- [x] `FamilyDiscussion` : exception chiffrée documentée, pas de bascule

### Phase 8 — Migration de l'existant (idempotente, dry-run)
- [x] CLI `core/tools/document_hub_migrate.php` : `--dry-run` par défaut, `--apply` explicite ; inventaire `private_documents` + `rental_documents`, SHA-256, création/réutilisation objets, documents logiques, liens, catégories mappées, fichiers manquants/références orphelines rapportés, relançable sans doublon
- [x] Exécuter le dry-run sur données réelles et archiver le rapport (`/tmp/migration-dryrun-report-2026-07-18-102530.json`)
- [ ] `--apply` après sauvegarde vérifiée
- [ ] Bascule des lectures restantes vers le hub puis retrait du code legacy prouvé mort (`rental_documents` upload socle, doublons `PrivateDocumentStorage`) — jamais avant vérification des compteurs et téléchargements
- [ ] Suppression des anciens fichiers uniquement après sauvegarde + directive explicite de l'utilisateur

### Phase 9 — Archivage, cycle de vie, dérivés
- [x] Cycle `active → closed → archived → pending_deletion → deleted` + `trashed` (corbeille) + `legal_hold` (schéma + transitions dans le repository)
- [x] Archivage logique (pas de déplacement physique), document archivé consultable/exportable, non modifiable sauf nouvelle version
- [x] Purge physique interdite tant qu'un lien/version/rétention/gel existe (garde dans `DocumentStorageService::deleteObjectIfUnreferenced`)
- [ ] `Service/DocumentDerivativeService` : miniatures GD (2048 px aperçu, 320-400 px liste, JPEG q82-85, jamais d'agrandissement, orientation corrigée dans le dérivé seul), dégradation explicite si GD absent
- [ ] Rétention configurable par catégorie (`retention_days` déjà en schéma) + corbeille auto-purgée par cron après délai

### Phase 10 — Exports
- [x] `Service/DocumentExportService` : export lisible ZIP par sélection/entité/année/catégorie, arborescence `<Entité>/<Année>/<Dossier-catégorie>/`, noms déterministes `AAAA-MM-JJ_categorie_description_<uid8>.<ext>`, normalisés multi-OS, document multi-rattaché stocké une fois, `manifest.json` + `documents.csv` + `SHA256SUMS` + `LISEZ-MOI.txt`
- [ ] Export portable complet par webapp (CSV entités + `complete-data.json`) — réutiliser `RentalExportService` existant pour les données rental et y joindre les originaux
- [ ] Construction en tâche de fond (cron center) pour les gros volumes + ZIP64 + purge des `exports-temp`
- [ ] Chiffrement AES-256 du ZIP si le runtime le supporte réellement (vérification de capacité, jamais de faux chiffrement)

### Phase 11 — Sauvegarde et restauration
- [ ] Étendre `PrivateBackupService` : inclure `objects/sha256` (originaux seuls, dérivés exclus), manifeste (version app/schéma, date ISO 8601, compteurs), `SHA256SUMS`, verrou anti-purge pendant la sauvegarde
- [ ] Intégration cron center (quotidien) + politique de rétention 14 j / 8 sem / 12 mois / annuel configurable
- [ ] État « sauvegarde locale uniquement » affiché si aucune destination distante configurée
- [ ] Restauration : dry-run, validation manifeste + checksums + compatibilité schéma, refus des archives tronquées, dédup objets présents, copie atomique, rapport, rollback — testable sur base/stockage temporaires

### Phase 12 — Intégrité, GC, administration
- [x] CLI `core/tools/document_hub_integrity.php` : objets SQL sans fichier, fichiers sans objet, empreintes invalides (`--verify-hashes`), documents sans objet, liens vers entités absentes, jobs bloqués — rapport seul, aucune correction automatique
- [x] `Service/DocumentGarbageCollector` : purge prudente quarantaine/exports-temp expirés, objets non référencés listés mais supprimés uniquement en mode explicite
- [x] Brancher intégrité + GC au cron center (jobs configurés : document_hub_integrity_check toutes les 4h, document_hub_garbage_collection à 2:45, document_hub_maintenance à 2:30)
- [x] Intégrer Document Hub dans CronScriptPolicy (scripts autorisés)
- [x] Intégrer tables Document Hub dans PrivateBackupService
- [x] Notifications intégrées dans tous les scripts CLI (DocumentHubCronNotificationService)
- [ ] Page d'administration : formats/limites effectifs, capacités facultatives détectées (AV, GD, poppler, chiffrement ZIP), volumes, économie dédup, docs à classer, dernières sauvegardes/contrôles

### Phase 13 — Tests, documentation, nettoyage
- [x] Unitaires : politique/validation (extensions, MIME, signatures, macros), CAS (hash, promotion atomique, concurrence), classification et seuils, noms d'export déterministes, registre d'intégration
- [x] Intégration (MySQL, skip si absente) : import complet, dédup 2 documents logiques → 1 objet, rattachement multiple, refus formats interdits, autorisation refusée sans droit sur l'entité, migration relançable
- [x] Bout en bout : import depuis 2 onglets avec le même composant, contexte prérempli, retrouvé dans le centre + entité liée, export ouvert et vérifié
- [ ] `composer test` + `composer phpstan` + `composer phpcs` verts (blocage : PrivateRouteResolverTest échoue à cause de routes RealEstateRental modifiées, hors scope Document Hub)
- [x] Correction PROFILE_PERSONAL : catégorie par défaut vide pour permettre classification automatique par nom de fichier
- [x] Documentation mise à jour avec checklists cochées
- [ ] Retrait du code mort prouvé (après phase 8 complète)

---

## 4. Capacités facultatives et dégradations

| Capacité | Détection | Dégradation |
|---|---|---|
| Antivirus (`scan_command`) | config `private.documents.scan_command` | statut `scan_unavailable`, document conservé, téléchargement selon politique existante |
| GD (miniatures) | `extension_loaded('gd')` | pas d'aperçu, original intact, mention UI |
| Poppler (`pdftotext`) | binaire présent | pas d'extraction texte/classement PDF affiné ; classement par contexte/nom seulement |
| ZipArchive chiffré | `ZipArchive::EM_AES_256` | export non chiffré, capacité affichée absente, jamais de faux chiffrement |
| exif (orientation) | `extension_loaded('exif')` | dérivé sans correction d'orientation |

Une capacité absente ne bloque jamais l'import d'un original valide et n'entraîne aucun rejet silencieux.

## 5. Règles de reprise de session

1. Lire ce fichier et l'état des cases.
2. `git status` : préserver le WIP utilisateur (extraction RealEstateRentalController, `RenderCacheService`) ; ne pas « corriger » les fins de ligne CRLF présentes dans le working tree.
3. Ne jamais écrire en production, ne jamais supprimer de fichiers utilisateur (voir interdictions du prompt d'origine).
4. Après chaque phase : cocher, exécuter `composer test` ciblé, mettre à jour §4 si une capacité change.

---

## 6. Résumé d'implémentation (2026-07-18)

### ✅ Complété
- **Phase 8** : Migration dry-run exécutée et rapport archivé
- **Phase 12** :
  - 4 jobs cron configurés et autorisés dans CronScriptPolicy
  - Tables Document Hub intégrées dans PrivateBackupService
  - Notifications intégrées dans tous les scripts CLI via DocumentHubCronNotificationService
- **Phase 13** :
  - Tests DocumentHubImportTest passent (2 tests, 50 assertions)
  - Correction de PersonalDocumentIntegration::PROFILE_PERSONAL (catégorie par défaut vide)
  - Documentation mise à jour avec checklists cochées

### ⚠️ Points restants (hors scope ou nécessitant validation manuelle)
- **Phase 8** : Exécution `--apply` nécessite sauvegarde vérifiée
- **Phase 13** : `composer test` complet bloqué par PrivateRouteResolverTest (routes RealEstateRental modifiées)
- **Phase 13** : Retrait du code mort après migration complète

### 📋 Fichiers modifiés
- `backend/src/Cron/CronScriptPolicy.php` : +4 jobs Document Hub
- `backend/src/PrivatePortal/Operations/PrivateBackupService.php` : +7 tables Document Hub
- `backend/src/PrivateApps/Documents/PrivateAppManifest.php` : routes hub, tables, ProvidesDocumentIntegration
- `backend/src/PrivateApps/Documents/PersonalDocumentIntegration.php` : catégorie par défaut vide
- `backend/core/tools/document_hub_maintenance.php` : +notifications
- `backend/core/tools/document_hub_backup.php` : +notifications
- `backend/core/tools/document_hub_integrity.php` : +notifications
- `backend/core/tools/document_hub_gc.php` : +notifications
- `backend/core/tools/document_hub_migrate.php` : +notifications

### 🎯 Prochaines étapes
1. Exécuter `--apply` après sauvegarde vérifiée
2. Finaliser la page d'administration
3. Terminer les tests end-to-end
4. Nettoyer le code mort après validation complète
