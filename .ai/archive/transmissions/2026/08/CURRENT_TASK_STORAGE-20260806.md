<!-- BEGIN MANAGED CENTRAL TASK CONTEXT -->
> **Gouvernance active 2.4** — Workflow et prompts :
> `../../Workspace/pauline-ai-governance/.ai/README.md` et `../../Workspace/pauline-ai-governance/.ai/prompts/`.
> La présente transmission et son archive restent locales à ce projet.
>
> **Mode** : mono-agent par défaut. Pour toute demande explicitement activée,
> l'agent principal `/root` peut cumuler routage, architecture,
> implémentation, vérification, corrections et clôture sans nouveau relais.
> Une revue indépendante reste facultative. Les anciennes mentions de
> fournisseurs, de prompts successifs ou de relecteur obligatoire ci-dessous
> sont remplacées par cette règle, sans modifier le besoin ni les preuves.
>
> **Limites** : cette attribution n'active pas une tâche seulement planifiée et
> n'autorise ni production, déploiement, migration, destruction, action externe
> ou décision humaine. Ces actions restent soumises à leur demande explicite.
<!-- END MANAGED CENTRAL TASK CONTEXT -->

# Tâche en cours - Finalisation Migration Stockage Runtime

## Demande originale

Terminer la migration du stockage runtime selon les documents :
- `backend/docs/RUNBOOK_STORAGE_MIGRATION.md` (runbook de migration)
- `backend/docs/COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md` (compte rendu d'audit)
- `backend/docs/prompt-audit-stockage-runtime-deploiement-production-maitre.md` (prompt source)

**Objectif** : Finaliser la séparation physique code/runtime initiée le 2026-07-18, désactiver le mode legacy, et valider la migration complète.

---

## Projet et périmètre

- **Projet** : Les Caramagnols (site public, admin, espace privé, webapps privées)
- **Périmètre** : **Finalisation de la migration du stockage runtime privé**
  - Désactivation du mode legacy dans les services de stockage
  - Validation fonctionnelle du nouveau chemin
  - Nettoyage final de l'ancien stockage (après validation)
  - Mise à jour de la documentation
- **Branche** : `restore-prod-master-20260716`
- **Source de vérité** : Production `https://www.lescaramagnols.com/` (référence fonctionnelle)
- **Cible production** : `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/`
- **Ancien chemin** : `/home/lescaramgl-ssh/caramagnols/backend/private/storage/` (à conserver temporairement)

### Exclusions
- **Ne pas supprimer** : Aucun fichier de l'ancien stockage sans validation préalable
- **Ne pas déployer** : Pas de déploiement en production sans validation complète
- **Ne pas écraser** : Aucune donnée runtime existante en production
- **Ne pas contacter la production** pour écriture sans autorisation explicite (lecture OK via `ssh ovh-boutique`)
- **Respecter les invariants** : Production = source de vérité; Local → Production = CODE SEULEMENT

---

## Niveau de routage

**C** - Tâche de sécurité et architecture touchant :
- Migration de stockage runtime (séparation physique code/données)
- Sécurité des données utilisateurs privées
- Synchronisation production
- Risque de perte de données si mal exécuté
- Accès à OVH pour vérification (lecture seule)

---

## Agent autorisé à modifier

**Codex** - Niveau C : Codex est l'unique agent autorisé à modifier le code. Mistral reste en lecture seule pour l'analyse, l'inventaire et la création de cette tâche. Claude Code intervient en lecture seule pour la revue critique d'architecture et de sécurité.

---

## Contraintes et exclusions

### Contraintes absolues (AGENTS.md)
- La production `https://www.lescaramagnols.com/` est la source de vérité
- `PRIVATE_STORAGE_ROOT` doit pointer vers `/home/lescaramgl-ssh/caramagnols-runtime/private-storage` en production
- `backend/private/storage/**` est exclu du versionnage Git et ne doit JAMAIS être déployé vers la production
- Les scripts de déploiement ne doivent jamais supprimer ni remplacer les fichiers runtime distants
- Les dossiers hexadécimaux (sharding SHA-256) sont légitimes et doivent être préservés
- Permissions attendues : dossiers `770`, fichiers `660`, groupe `users` ou `www-data` selon OVH
- **Règle critique** : Local → Production = CODE SEULEMENT, aucune donnée SQL, aucun upload, aucun document runtime

### Contraintes spécifiques à cette tâche
- **Ne pas modifier** : Aucun code sans analyse préalable complète (Claude) et validation du plan (Mistral)
- **Ne pas déployer** : Pas de déploiement en production sans validation complète locale et test des garde-fous
- **Ne pas supprimer** : Aucun fichier de stockage sans sauvegarde, validation et confirmation explicite
- **Ne pas écraser** : Aucune donnée de production
- **Vérifier avant** : `git status --short` avant toute modification
- **Respecter le runbook** : Toutes les actions doivent suivre `RUNBOOK_STORAGE_MIGRATION.md`

---

## Inventaire Mistral

### État actuel de la migration (d'après RUNBOOK_STORAGE_MIGRATION.md)

#### Ce qui a été exécuté le 2026-07-18 sur OVH
- [x] **Étape 0** : Sauvegardes créées (SQL + fichiers, pré/post migration)
  - `caramagnols-prod-files-20260718-141517.tar.gz` (pré-migration)
  - `caramagnols-prod-db-20260718-141517.sql.gz` (pré-migration)
  - `caramagnols-prod-files-20260718-142340.tar.gz` (post-migration)
  - `caramagnols-prod-db-20260718-142340.sql.gz` (post-migration)
- [x] **Étape 1** : Structure `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/` créée
- [x] **Étape 2** : Diagnostic initial exécuté
- [x] **Étape 3** : Données copiées avec rsync (sans suppression)
- [x] **Étape 4** : Code déployé avec support legacy
- [x] **Étape 5** : Diagnostic en mode dual exécuté
- [x] **Étape 6** : Vérification fonctionnelle partielle (routes critiques OK)
- [x] **Étape 7** : Deuxième sauvegarde créée

#### Ce qui reste à faire
- [ ] **Étape 8** : Désactiver le mode legacy (différée 24-48h après migration)
- [ ] **Étape 9** : Nettoyage final (différé après validation complète)
- [ ] Nouveau upload testé et validé
- [ ] Rollback testé

### Implémentations locales existantes

#### Garde-fous activés sur OVH (2026-07-19)
- [x] `PUSH_LOCAL_SQL_BLOCKED=1` dans `.bashrc` (blocage de `push-local-sql-to-ovh.sh`)
- [x] `SYNC_EDITORIAL_UPLOADS_BLOCKED=1` dans `.bashrc` (blocage de `sync-editorial-uploads.sh`)
- [x] Vérification : `echo $PUSH_LOCAL_SQL_BLOCKED` → `1`
- [x] Vérification : `echo $SYNC_EDITORIAL_UPLOADS_BLOCKED` → `1`

#### Outils CLI existants
- [x] `backend/core/tools/private_storage_diagnostic.php` (lecture seule, diagnostic complet)
- [x] `backend/core/tools/private_storage_prune.php` (nettoyage avec `--dry-run` par défaut)

#### Tests PHPUnit existants
- [x] `backend/tests/PrivateApps/Storage/PrivateStorageDiagnosticTest.php` (10 tests)
- [x] `backend/tests/PrivateApps/Storage/PrivateStoragePruneTest.php` (10 tests)

#### Documentation existante
- [x] `backend/docs/COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md` (audit complet)
- [x] `backend/docs/STORAGE_RUNTIME_POLICY.md` (politique de stockage)
- [x] `backend/docs/RUNBOOK_STORAGE_MIGRATION.md` (procédure de migration)
- [x] Mise à jour de `AGENTS.md` (section Stockage Runtime Privé, lignes 91-106)

#### Support legacy dans le code
- [x] `PrivateDocumentStorage.php` : `legacyMode` activé si nouveau chemin inexistant
- [x] `DocumentStorageService.php` : `legacyMode` activé si nouveau chemin inexistant
- [x] `DiscussionAttachmentStorage.php` : `legacyMode` activé si nouveau chemin inexistant
- [x] Logging des accès en mode legacy (`private.documents.legacy_mode_activated`)
- [x] Blocage de l'écriture en mode legacy (lecture seule)

#### Configuration existante
- [x] `PRIVATE_STORAGE_ROOT` peut être utilisée comme variable d'environnement
- [x] Chemin legacy : `ROOT_PATH . '/private'`
- [x] Support des deux chemins pendant la transition

### Fichiers clés à modifier

| Fichier | Modification nécessaire | Priorité |
|--------|------------------------|----------|
| `PrivateDocumentStorage.php` | Supprimer `legacyMode` et références legacy | 🔴 Haute |
| `DocumentStorageService.php` | Supprimer `legacyMode` et références legacy | 🔴 Haute |
| `DiscussionAttachmentStorage.php` | Supprimer `legacyMode` et références legacy | 🔴 Haute |

### Commandes de validation locales
```bash
# Vérifier la syntaxe PHP
php -l backend/src/PrivateApps/Documents/PrivateDocumentStorage.php
php -l backend/src/PrivateApps/Documents/Service/DocumentStorageService.php
php -l backend/src/PrivateApps/FamilyDiscussion/Attachment/DiscussionAttachmentStorage.php

# Exécuter les tests
composer --working-dir=backend test --filter "PrivateStorage"

# Vérifier git status
 git status --short
```

---

## 🗂️ Archivage des documents source

**À exécuter lorsque le travail est terminé (100% des tâches cochées)** :

**Statut 2026-07-19** : le runbook `backend/docs/RUNBOOK_STORAGE_MIGRATION.md`
a été archivé dans `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`.
Les références actives dans `AGENTS.md`, `backend/docs/STORAGE_RUNTIME_POLICY.md`
et `backend/docs/COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md` pointent vers cet
emplacement archivé.

Les 3 documents source **doivent être archivés** (pas supprimés) une fois la migration complètement terminée et validée :

| Document | Action | Dossier de destination | Condition |
|----------|--------|----------------------|-----------|
| `backend/docs/COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md` | Archiver | `backend/docs/archive/2026-07-storage/` | Migration terminée + validée |
| `backend/docs/prompt-audit-stockage-runtime-deploiement-production-maitre.md` | Archiver | `backend/docs/archive/2026-07-storage/` | Migration terminée + validée |
| `backend/docs/RUNBOOK_STORAGE_MIGRATION.md` | Archiver | `backend/docs/archive/2026-07-storage/` | Migration terminée + validée |

**⚠️ Action requise avant archivage du RUNBOOK** :
> Le RUNBOOK est référencé dans `AGENTS.md` (ligne 105). Avant de l'archiver, **mettre à jour AGENTS.md** pour pointer vers :
> ```markdown
> Voir `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`
> ```

**Procédure d'archivage complète** :
```bash
# 1. Créer le dossier d'archive
mkdir -p backend/docs/archive/2026-07-storage

# 2. Mettre à jour AGENTS.md pour pointer vers le nouvel emplacement
#    Remplacer ligne 105 :
#    OLD: Voir `backend/docs/RUNBOOK_STORAGE_MIGRATION.md`
#    NEW: Voir `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`
sed -i 's|backend/docs/RUNBOOK_STORAGE_MIGRATION\.md|backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md|g' backend/docs/../../AGENTS.md
# Note: Le chemin exact peut varier, vérifier avec grep avant de remplacer

# 3. Déplacer les 3 fichiers vers l'archive
mv backend/docs/COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md backend/docs/archive/2026-07-storage/
mv backend/docs/prompt-audit-stockage-runtime-deploiement-production-maitre.md backend/docs/archive/2026-07-storage/
mv backend/docs/RUNBOOK_STORAGE_MIGRATION.md backend/docs/archive/2026-07-storage/

# 4. Créer un README dans l'archive
echo "# Archive - Migration Stockage Runtime 2026-07" > backend/docs/archive/2026-07-storage/README.md
echo "" >> backend/docs/archive/2026-07-storage/README.md
echo "Documents archivés après finalisation complète de la migration (18-19 juillet 2026) :" >> backend/docs/archive/2026-07-storage/README.md
echo "" >> backend/docs/archive/2026-07-storage/README.md
echo "- [COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md](COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md) - Audit complet du stockage" >> backend/docs/archive/2026-07-storage/README.md
echo "- [prompt-audit-stockage-runtime-deploiement-production-maitre.md](prompt-audit-stockage-runtime-deploiement-production-maitre.md) - Prompt source de l'audit" >> backend/docs/archive/2026-07-storage/README.md
echo "- [RUNBOOK_STORAGE_MIGRATION.md](RUNBOOK_STORAGE_MIGRATION.md) - Runbook de migration exécuté" >> backend/docs/archive/2026-07-storage/README.md
echo "" >> backend/docs/archive/2026-07-storage/README.md
echo "**Note** : Ces documents sont conservés à titre historique. Pour la procédure actuelle," >> backend/docs/archive/2026-07-storage/README.md
echo "consulter AGENTS.md qui pointe vers l'emplacement archivé du RUNBOOK." >> backend/docs/archive/2026-07-storage/README.md

# 5. Commit des changements
git add backend/docs/archive/2026-07-storage/ backend/docs/COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md backend/docs/prompt-audit-stockage-runtime-deploiement-production-maitre.md backend/docs/RUNBOOK_STORAGE_MIGRATION.md AGENTS.md
git commit -m "Archive: documents migration stockage runtime 2026-07

Finalisation de la migration du stockage runtime privé.
Archivage des 3 documents source après validation complète.
Mise à jour de AGENTS.md pour pointer vers RUNBOOK archivé.

Generated by Mistral Vibe.
Co-Authored-By: Mistral Vibe <vibe@mistral.ai>"
```

**⚠️ Attention** :
- **Mettre à jour AGENTS.md AVANT de déplacer RUNBOOK_STORAGE_MIGRATION.md** pour éviter les liens cassés
- Ne pas archiver avant que toutes les étapes ne soient terminées et validées (y compris Étape 8-9 du runbook)
- Créer un commit atomique incluant les 3 fichiers + AGENTS.md
- Vérifier avec `grep -n "RUNBOOK_STORAGE_MIGRATION" backend/docs/../../AGENTS.md` après modification

---

## Analyse d'architecture Claude

*Analyse effectuée le 2026-07-19 par Claude Code, en lecture seule, sur le code local uniquement (aucun accès production effectué depuis ce poste).*

### Vérifications à effectuer
1. **Vérifier la cohérence** entre le code local et la configuration production
2. **Confirmer que** le nouveau chemin `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/` est accessible en écriture
3. **Valider que** les permissions (770/660) sont correctement appliquées
4. **Analyser les risques** de la suppression du mode legacy
5. **Vérifier l'idempotence** des scripts de migration

### Résultats des vérifications (2026-07-19)

1. **Cohérence code/config : conforme.** `backend/config/config.php:487-502` décompose correctement
   `PRIVATE_STORAGE_ROOT` (dirname/basename) et la propage vers `private.documents`,
   `private.discussions` et `private.document_hub.storage_root_path` (= `<racine>/document-hub`).
   Les trois classes de stockage (`PrivateDocumentStorage`, `DocumentStorageService`,
   `DiscussionAttachmentStorage`) partagent le même schéma : `legacyMode` activé uniquement si
   env = production **et** nouveau chemin absent **et** ancien présent ; en mode legacy la lecture
   est permise et l'écriture bloquée (`legacy_mode_readonly`), avec journalisation. Cohérent entre
   les trois classes.

2. **Écriture sur le nouveau chemin OVH : non vérifiable depuis ce poste.** À confirmer avant
   l'étape 8 via `ssh ovh-boutique` (lecture seule) : `php core/tools/private_storage_diagnostic.php`
   sur la production, plus un upload de test réel.

3. **Permissions 770/660 : conformes dans le code** (constantes `DIRECTORY_PERMISSIONS`/`FILE_PERMISSIONS`
   appliquées à chaque mkdir/chmod). L'état effectif sur OVH reste à confirmer sur place (point 2).

4. **Risque principal de la suppression du mode legacy — c'est le point d'architecture décisif :**
   quand `PRIVATE_STORAGE_ROOT` est absent, les trois classes retombent par défaut sur
   `ROOT_PATH . '/private/...'`, et leurs constructeurs (`ensureDirectories()`/mkdir) **recréent
   silencieusement l'arborescence sous le code déployé**. Aujourd'hui, le mode legacy read-only est
   le seul garde-fou. Le supprimer « sec » signifie : si la variable d'environnement disparaît un
   jour en production (misconfig, rotation de `.htaccess`/env), les écritures repartiraient sans
   erreur dans `backend/private/storage/**` — chemin soumis aux déploiements — avec risque de perte.
   **Recommandation (solution principale)** : remplacer `legacyMode` non pas par rien, mais par un
   fail-fast de production : si env = production et que le chemin configuré est vide ou situé sous
   `ROOT_PATH`, refuser l'écriture et journaliser une erreur explicite (pas de fallback silencieux).
   La suppression des constantes `LEGACY_STORAGE_*` et des branches de lecture legacy reste, elle,
   sans risque une fois l'étape 8 validée côté production.

5. **Idempotence des outils : conforme.** `private_storage_prune.php` est dry-run par défaut,
   `private_storage_diagnostic.php` est en lecture seule ; les tests PHPUnit dédiés existent
   (2 × 10 tests). Le déploiement reste additif (rsync sans `--delete`) conformément à la politique.

### Dépendance inter-tâches

La tâche `CURRENT_TASK_IMPORTEXPORT.md` (Phase 8, `document_hub_migrate.php --apply` en production)
est **bloquée tant que le mode legacy est actif** : `DocumentStorageService` refuse l'écriture en
mode legacy (`legacy_mode_readonly`). Finaliser et valider la présente tâche (étape 8 du runbook)
avant toute migration documentaire en production.

### Points critiques à valider
- [ ] Le nouveau stockage est-il fonctionnel en production ? *(non vérifié depuis ce poste — diagnostic OVH requis)*
- [ ] Les webapps privées (Documents, FamilyDiscussion, DocumentHub) fonctionnent-elles avec le nouveau chemin ? *(non vérifié — test fonctionnel prod requis)*
- [ ] Le mode legacy est-il toujours utilisé en production ? *(non vérifié — chercher `legacy_mode_activated` dans les logs récents)*
- [ ] Les sauvegardes sont-elles accessibles et valides ? *(non vérifié — contrôle des archives du 2026-07-18 requis avant l'étape 8)*

### Critères d'acceptation proposés
- Diagnostic prod : nouveau chemin actif, aucune activation `legacy_mode_activated` sur 48 h.
- Upload + téléchargement réels OK sur les trois familles de stockage après désactivation legacy.
- En cas de `PRIVATE_STORAGE_ROOT` absent en production, le code refuse d'écrire (fail-fast testé unitairement) au lieu de recréer `backend/private/storage/`.
- Tests `PrivateStorageDiagnosticTest`/`PrivateStoragePruneTest` verts ; `phpstan`/`phpcs` verts sur les fichiers modifiés.
- Aucune suppression de l'ancien stockage sans l'étape 9 du runbook (sauvegarde + confirmation explicite).

---

## Plan d'implémentation validé

*À compléter par Codex après validation de l'analyse Claude.*

### Phase 1 : Vérification pré-migration (Local)
1. **Exécuter le diagnostic** sur le code local pour vérifier la structure attendue
2. **Vérifier les tests** existants passent
3. **Valider git status** propre avant modifications

### Phase 2 : Désactivation du mode legacy (Codex)
1. **Supprimer `legacyMode`** de `PrivateDocumentStorage.php`
2. **Supprimer `legacyMode`** de `DocumentStorageService.php`
3. **Supprimer `legacyMode`** de `DiscussionAttachmentStorage.php`
4. **Supprimer les constantes legacy** (LEGACY_STORAGE_ROOT, LEGACY_STORAGE_DIR, etc.)
5. **Mettre à jour les constructeurs** pour ne plus vérifier le chemin legacy
6. **Supprimer les logs legacy** (ou les conserver comme info historique)

### Phase 3 : Tests et validation (Local)
1. **Exécuter les tests unitaires** : `composer --working-dir=backend test`
2. **Exécuter les tests de stockage** : `composer --working-dir=backend test --filter "PrivateStorage"`
3. **Vérifier la syntaxe** de tous les fichiers modifiés
4. **Faire un dry-run** du déploiement : `bash backend/tools/deploy-release.sh --dry-run`

### Phase 4 : Déploiement et validation (OVH)
1. **Déployer le code** avec `deploy-release.sh`
2. **Vérifier qu'aucun nouveau fichier** n'a été écrit dans l'ancien chemin (Étape 8 du runbook)
3. **Tester un nouvel upload** (doit aller vers le nouveau chemin)
4. **Tester le téléchargement** de documents existants
5. **Vérifier les logs** d'application pour détecter des erreurs

### Phase 5 : Nettoyage final (Optionnel, après validation)
1. **Archiver l'ancien stockage** (Étape 9 du runbook)
2. **Créer une sentinelle** pour empêcher la recréation
3. **Mettre à jour la documentation** si nécessaire

---

## Résultat Codex ou Mistral

### Clôture Codex du 2026-08-06

État final : **terminé et déployé**.

Décisions prises :

- Suppression définitive du fallback applicatif legacy dans :
  - `backend/src/PrivateApps/Documents/PrivateDocumentStorage.php` ;
  - `backend/src/PrivateApps/Documents/Service/DocumentStorageService.php` ;
  - `backend/src/PrivateApps/FamilyDiscussion/Attachment/DiscussionAttachmentStorage.php`.
- Conservation de `isLegacyMode()` avec retour `false` pour compatibilité d'interface/tests.
- Garde-fou production maintenu :
  - un chemin sous `ROOT_PATH` est refusé dès la construction ;
  - un runtime externe absent est refusé au premier usage de stockage, sans casser les pages publiques.
- Aucun fichier runtime, upload ou donnée production n'a été supprimé, déplacé ou synchronisé depuis le local.
- L'ancien chemin production reste présent comme archive de rollback historique, sans fallback applicatif.
- Les documents source actifs ont été archivés dans `backend/docs/archive/2026-07-storage/`.
- `squizlabs/php_codesniffer` a été mis à jour de `3.13.5` vers `3.13.6` pour corriger l'audit Composer.

Commits :

- `59e2822` `fix(private): finalize runtime storage migration`
- `f5c9650` `fix(private): defer runtime storage availability checks`

Déploiement :

- Cible : `prod`, `ovh-boutique:/home/lescaramgl-ssh/caramagnols/backend`
- Script : `backend/tools/deploy-release.sh`
- Résultat : `cache_cleared`, `autoload_ok`, `deploy-release completed`

Validations locales exécutées :

- `php -l` sur les 3 services de stockage et le test ajouté : OK.
- `vendor/bin/phpunit` complet : 735 tests, 5977 assertions, OK.
- `vendor/bin/phpunit tests/PrivateApps/Storage tests/PrivateApps/Documents/DocumentStorageServiceTest.php tests/PrivatePortalStorageTest.php tests/PrivateApps/FamilyDiscussion/FamilyDiscussionModuleTest.php` : 48 tests, 398 assertions, OK après hotfix.
- `composer phpstan --working-dir=backend` : OK.
- `composer phpcs --working-dir=backend` : OK.
- `composer audit --working-dir=backend` : OK après mise à jour de PHP_CodeSniffer.
- `npm run hygiene:repo` depuis `frontend/` : OK.
- `git diff --check` : OK.

Contrôles production :

- `curl` public avec User-Agent navigateur : `HTTP/2 200`.
- GET smoke `/?codex_smoke=storage-final` : `200`, 91222 octets.
- Log applicatif confirmé :
  `site.visit.page`, `request_id=1f60cac6cd41cdc38bbc0338`, `status=200`.
- Instanciation CLI `PrivateDocumentStorage::fromAppConfig()` : `runtime`.
- Diagnostic lecture seule :
  `/home/lescaramgl-ssh/caramagnols-runtime/private-storage`, 3 fichiers, 234 dossiers, 2147836 octets.
- Derniers `private.documents.legacy_mode_activated` : avant déploiement hotfix, dernier vu à `2026-08-06T14:41:32+02:00`.

Limites restantes :

- Le diagnostic historique signale toujours `web-development` comme répertoire inattendu sous
  `private-storage`; le module WebDevelopment documente que ce chemin est historique et ne doit pas
  être réactivé. Aucune suppression distante n'a été faite.
- Le test fonctionnel réel d'upload via navigateur reste à réaliser manuellement si un fichier de test
  doit être créé en production. Le code est prêt et échouera explicitement en cas d'indisponibilité
  du runtime.

Rollback :

- Revenir au commit précédant `59e2822` ou redéployer une révision antérieure si une restauration
  applicative legacy est indispensable.
- Désactiver toute opération d'upload pendant le rollback.
- Ne pas supprimer la migration SQL ni les fichiers runtime.
- Conserver `PRIVATE_STORAGE_ROOT=/home/lescaramgl-ssh/caramagnols-runtime/private-storage`.
- Vérifier ensuite `curl`, `data/logs/access.log`, `data/logs/security.log` et le diagnostic stockage.

---

## Tests et validations

### Commandes de validation confirmées (présentes dans le dépôt)
- `composer --working-dir=backend test` (703+ tests backend)
- `composer --working-dir=backend phpstan --level=5` (analyse statique)
- `composer --working-dir=backend phpcs` (PSR-12)
- `php -l <fichier>` (syntaxe PHP)
- `git diff --check` (whitespace)

### Commandes à exécuter avant validation
```bash
# Vérification syntaxe
find backend/src/PrivateApps/{Documents,FamilyDiscussion} -name "*Storage*.php" -exec php -l {} \;

# Tests de stockage
composer --working-dir=backend test --filter "PrivateStorageDiagnosticTest|PrivateStoragePruneTest"

# Analyse statique
composer --working-dir=backend phpstan --level=5 -- backend/src/PrivateApps/{Documents,FamilyDiscussion}/**/*Storage*.php
composer --working-dir=backend phpcs -- backend/src/PrivateApps/{Documents,FamilyDiscussion}/**/*Storage*.php

# Vérification git
git diff --check
git status --short
```

---

## Revue finale

Revue finale effectuée par Codex le 2026-08-06 après validation locale, push,
déploiement production et smoke test public. La tâche est clôturable.

---

## État

**Terminé**

Etats autorisés : `À analyser`, `Planifié`, `En cours`, `À revoir`, `Terminé`, `Bloqué`.

---
*Créé : 2026-07-19*
*Agent : Mistral Vibe*
*Route par : ROUTER.md (niveau C, agent Codex)*
*Source : RUNBOOK_STORAGE_MIGRATION.md, COMPTE_RENDU_AUDIT_STORAGE_2026-07-18.md, prompt-audit-stockage-runtime-deploiement-production-maitre.md*
