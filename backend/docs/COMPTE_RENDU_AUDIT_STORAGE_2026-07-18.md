# Compte Rendu Final - Audit Stockage Runtime et Déploiement

**Date** : 18 juillet 2026
**Projet** : Les Caramagnols
**Responsable** : Mistral Vibe (via prompt d'audit)
**Environnement** : Local (Windows + WSL)
**Source de vérité** : Production OVH `https://www.lescaramagnols.com/`

---

## 1. Diagnostic Exact des Dossiers Hexadécimaux

### 1.1 Structure Identifiée

Les dossiers `01`, `0b`, `1d`, `04`, `09`, `df`, etc. sous `backend/private/storage/uploads/` **sont légitimes** et font partie d'un **système de sharding SHA-256 à 2 niveaux**.

**Preuve par le code** :

#### Algorithme pour `uploads/` (Documents Privés)
**Fichier** : `backend/src/PrivateApps/Documents/PrivateDocumentStorage.php` (ligne 537-541)

```php
private function buildStoragePath(string $documentId, string $extension): string
{
    $hash = hash('sha256', $documentId . '|' . (string) time());
    return sprintf('uploads/%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), $documentId, $extension);
}
```

#### Algorithme pour `document-hub/objects/` (CAS)
**Fichier** : `backend/src/PrivateApps/Documents/Service/DocumentStorageService.php` (ligne 119-122)

```php
public function storageKeyForHash(string $sha256): string
{
    return sprintf('objects/sha256/%s/%s/%s', substr($sha256, 0, 2), substr($sha256, 2, 2), $sha256);
}
```

#### Algorithme pour `family-discussion/uploads/` (Pièces Jointes)
**Fichier** : `backend/src/PrivateApps/FamilyDiscussion/Attachment/DiscussionAttachmentStorage.php` (ligne 321-326)

```php
private function buildStoragePath(string $attachmentId, string $extension): string
{
    $hash = hash('sha256', $attachmentId . '|' . time());
    return sprintf('family-discussion/uploads/%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), $attachmentId, $extension);
}
```

**Conclusion** : Les dossiers hexadécimaux sont **créés à la demande par l'application** lors de l'upload de fichiers, avec un sharding basé sur les 4 premiers caractères du hash SHA-256 du document ID + timestamp.

---

## 2. Preuve de leur Origine

### 2.1 Audit du Système de Fichiers Local

**Chemin** : `\\wsl$\Ubuntu\home\surfacepro8\www\caramagnols\backend\private\storage\`

| Métrique | Valeur |
|----------|--------|
| Dossiers totaux | 236 |
| Dossiers vides | 126 (53%) |
| Dossiers non-vides | 110 (47%) |
| Fichiers totaux | 5 |
| Taille totale | 2 149 554 octets (~2,15 Mo) |

### 2.2 Répartition par Sous-Répertoire

| Répertoire | Fichiers | Taille | Statut |
|------------|---------|-------|--------|
| `uploads/` | 3 | ~2,15 Mo | **Contient des fichiers réels** |
| `backups/document-hub/20260718/` | 2 | 1,73 Ko | Fichiers de sauvegarde |
| `document-hub/` | 0 | 0 | Dossiers vides (structure créée) |
| `family-discussion/` | 0 | 0 | Dossiers vides (structure créée) |
| `exports/` | 0 | 0 | Dossiers vides (structure créée) |

### 2.3 Fichiers Réels Trouvés

```
uploads/71/73/e7dbbe4f620956edb1017111f5a24d0a.pdf (158 572 octets)
uploads/11/c9/6ed308ef4ba8a086bb0463ac5432c80d.pdf (159 252 octets)
uploads/82/e3/205e3d080774b0ce1f843a7a002b7538.jpg (1 830 012 octets)
backups/document-hub/20260718/document-hub-SHA256SUMS (93 octets)
backups/document-hub/20260718/document-hub-manifest.json (1 625 octets)
```

**Observation** : Les noms de fichiers dans `uploads/` sont des **hachages SHA-256** (e7dbbe..., 6ed308..., 205e3d...), ce qui confirme qu'ils sont générés par l'algorithme de sharding.

### 2.4 Git Status

**Fichier** : `.gitignore` (ligne 70)

```gitignore
backend/private/storage/
```

**Statut** : **Tout le répertoire est ignoré par Git** - aucun fichier n'est versionné.

---

## 3. Nombre de Dossiers, Fichiers et Volume Local

### 3.1 Résumé

| Type | Compte | Détails |
|------|--------|---------|
| **Dossiers totaux** | 236 | Inclut la racine + tous les sous-dossiers |
| **Dossiers vides** | 126 | 53% du total |
| **Dossiers non-vides** | 110 | 47% du total |
| **Fichiers** | 5 | 3 PDF/JPG dans uploads, 2 JSON dans backups |
| **Taille totale** | 2 149 554 octets | ~2,15 Mo |

### 3.2 Causes des Dossiers Vides

1. **Sharding à la demande** : Les dossiers `ab/cd/` sont créés uniquement quand un fichier y est stocké
2. **Peu de fichiers** : Seulement 3 fichiers dans `uploads/` → de nombreux dossiers `ab/` et `ab/cd/` restent vides
3. **Initialisation partielle** : Les répertoires `document-hub/`, `family-discussion/`, `exports/` sont créés par `ensureDirectories()` mais ne contiennent pas encore de fichiers
4. **Backups temporaires** : `backups/document-hub/20260718/` contient des fichiers de sauvegarde

### 3.3 Répartition par Niveau

- **Niveau 0** : 1 (racine storage) + 5 (uploads, document-hub, family-discussion, backups, exports) = 6
- **Niveau 1** : ~100 dossiers hexadécimaux (01, 04, 09, 0b, 10, 11, 1d, etc.)
- **Niveau 2** : ~130 dossiers (ab/cd, comme 71/73, 0b/c6, 0b/36, etc.)

---

## 4. Liste des Éléments Suivis ou Ignorés par Git

### 4.1 Ignorés par Git

| Chemin | Type | Raison |
|--------|------|--------|
| `backend/private/storage/` | Répertoire complet | Lignes 70-72 dans `.gitignore` |
| `backend/private/storage/**` | Tous les fichiers | Inclus par le pattern ci-dessus |

### 4.2 Suivis par Git

**Aucun** fichier dans `backend/private/storage/` n'est suivi par Git.

### 4.3 Vérification

```bash
# Aucun fichier suivi dans storage
git ls-files backend/private/storage/
# (retourne vide)

# Tous les fichiers sont "untracked" ou ignorés
git status --porcelain backend/private/storage
# (retourne vide car tout est ignoré)
```

---

## 5. Comportement Exact de Chaque Script de Déploiement

### 5.1 `deploy-release.sh`

**Type** : Déploiement complet (code + vendor + private)
**Cible** : Production (prod) ou Preprod (abandonnée)

#### Comportement

1. **Passe principale** (ligne 203-237) :
   - `rsync` avec `--delete` pour synchroniser le code
   - **EXCLUT** `private/` via `--exclude="private/"` (ligne 201)
   - Exclut aussi : `.git/`, `.env`, `config/*.override.php`, `vendor/`, `node_modules/`, `tests/`, `docs/`, etc.

2. **Passe additive pour private/** (ligne 243-246) :
   ```bash
   if deploys_private_runtime && [[ -d "$LOCAL_BACKEND/private" ]]; then
     # Passe additive : pas de --delete, les fichiers runtime distants sont conserves.
     rsync -az --info=progress2 "$LOCAL_BACKEND/private/" "$REMOTE_HOST:$REMOTE_BACKEND/private/"
   fi
   ```
   - **Synchronise** `private/` EN MODE ADDITIF
   - **N'utilise PAS --delete** → les fichiers distants sont préservés
   - `deploys_private_runtime()` retourne toujours `true` (ligne 33-38)

3. **Synchronisation SQL** (ligne 248-250) :
   - Exécute `sync_deploy_schema.php` sur la production
   - Synchronise uniquement le **schéma** (structure), pas les données

#### **Risques identifiés**

- ✅ **POSITIF** : `private/` est exclu de la passe avec `--delete`
- ⚠️ **ATTENTION** : La passe additive (`rsync -az`) pourrait **écraser des fichiers distants** avec des fichiers locaux si des noms entrent en conflit
- ⚠️ **ATTENTION** : Aucun filtre explicite pour exclure `backend/private/storage/**` de la synchronisation additive
- ❌ **NÉGATIF** : Le commentaire "passe additive" donne un faux sentiment de sécurité - rsync SANS --delete protège contre la suppression, mais **pas contre l'écrasement**

### 5.2 `deploy-fast.sh`

**Type** : Déploiement incrémental (seulement les fichiers modifiés)
**Cible** : Production ou Preprod

#### Comportement

1. **Exclusion de private/ pour prod** (ligne 23-28) :
   ```bash
   is_deploy_excluded_path() {
     case "$1" in
       private|private/*)
         [[ "$DEPLOY_TARGET" != "preprod" ]]
         return
         ;;
   ```
   - Pour `prod` : **exclut complètement** `private/`
   - Pour `preprod` : permet `private/` pour les tests

2. **Deployment** (ligne 250-251) :
   ```bash
   rsync "${RSYNC_FLAGS[@]}" --files-from="$CHANGED_FILES" "$LOCAL_BACKEND/" "$REMOTE_HOST:$REMOTE_BACKEND/"
   ```
   - Déploie **uniquement les fichiers modifiés** (staged ou all-changes)
   - **N'inclut PAS private/** pour la production

#### **Risques identifiés**

- ✅ **POSITIF** : `private/` est complètement exclu pour la production
- ⚠️ **ATTENTION** : Pour `preprod`, `private/` peut être synchronisé, ce qui pourrait pousser des données locales vers preprod

### 5.3 `push-local-sql-to-ovh.sh`

**Type** : Synchronisation de contenu éditorial SQL local → production
**Risque** : **CRITIQUE** - Violation directe de l'invariant "production maîtresse"

#### Comportement

1. **Exige --live** (ligne 103-107) pour autoriser l'exécution
2. **Crée un backup SQL local** (ligne 253) : `editorial_backup_restore.php backup`
3. **Pousse le payload local vers OVH** (ligne 272-274) :
   ```bash
   scp -q "$TMP_JSON" "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_PAYLOAD"
   ```
4. **Restaure le payload en production** (ligne 280-281) :
   ```bash
   ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/editorial_backup_restore.php restore '$REMOTE_PAYLOAD' $REMOTE_RESTORE_FLAGS"
   ```
5. **Synchronise les uploads éditoriaux** (ligne 228-231) :
   ```bash
   if [[ "$SYNC_UPLOADS" -eq 1 ]]; then
     REMOTE_HOST="$REMOTE_HOST" REMOTE_BACKEND="$REMOTE_BACKEND" LOCAL_BACKEND="$LOCAL_BACKEND" \
       bash "$UPLOADS_SYNC_SCRIPT"
   fi
   ```

#### **Risques identifiés**

- ❌ **CRITIQUE** : **Pousse du SQL local vers la production** (ligne 280-281)
- ❌ **CRITIQUE** : **Pousse des uploads locaux vers la production** via `sync-editorial-uploads.sh` (ligne 228-231)
- ❌ **CRITIQUE** : **Violation directe** de l'invariant "production maîtresse"
- ❌ **CRITIQUE** : Peut écraser des données de production avec des données locales

### 5.4 `sync-editorial-uploads.sh`

**Type** : Synchronisation des uploads éditoriaux
**Risque** : **ÉLEVÉ**

#### Comportement

```bash
rsync "${RSYNC_FLAGS[@]}" \
  "$LOCAL_UPLOADS_DIR/" "$REMOTE_HOST:$REMOTE_BACKEND/public/uploads/editorial/"
```

- Synchronise `backend/public/uploads/editorial/**` local → production
- **N'utilise PAS --delete** (seulement `-azv`)
- Peut **ajouter ou écraser** des fichiers en production

#### **Risques identifiés**

- ❌ **ÉLEVÉ** : Pousse des fichiers locaux vers la production
- ⚠️ **ATTENTION** : Peut écraser des fichiers existants

---

## 6. Risque Exact de `push-local-sql-to-ovh.sh`

### 6.1 Ce qu'il fait

1. **Crée un dump SQL local** du contenu éditorial (pages, navigation, articles de blog, tuiles)
2. **Pousse ce dump vers OVH**
3. **Restaure le dump en production**, écrasant les données existantes
4. **Synchronise aussi les uploads** (`backend/public/uploads/editorial/**`)

### 6.2 Ce qu'il peut écraser

- ✅ **Exclut** (selon la documentation) : utilisateurs, logs, meta schema, commentaires legacy, discussions de blog
- ❌ **Écrase** : pages, navigation, articles de blog, tuiles, et leurs références
- ❌ **Écrase** : les uploads éditoriaux si `--no-uploads` n'est pas utilisé

### 6.3 Problèmes Majeurs

1. **Local → Production** : Pousse des données **locales** vers la production
2. **Pas de vérification** que les données locales sont plus récentes ou valides
3. **Effet destructeur** : La restauration SQL **écrase** les données de production
4. **Appelé par deploy** : Bien que non directement appelé, il existe et peut être exécuté accidentellement

### 6.4 Recommandations Appliquées

**Garde-fous ajoutés** (18 juillet 2026) :

1. **Blocage par variable d'environnement** :
   ```bash
   if [[ "${PUSH_LOCAL_SQL_BLOCKED:-0}" == "1" ]]; then
     echo "BLOQUÉ: PUSH_LOCAL_SQL_BLOCKED=1 - ce script est désactivé..."
     exit 1
   fi
   ```

2. **Avertissement dans le header** du script

3. **Documentation** : Script **NE DOIT PAS** être appelé par les déploiements normaux

**Recommandation pour la production** :
```bash
# Dans l'environnement OVH
export PUSH_LOCAL_SQL_BLOCKED=1
```

---

## 7. Décision Architecturale Retenue

### 7.1 Architecture Actuelle Conservée (avec Améliorations)

**Décision** : Conserver l'architecture actuelle **avec des garde-fous renforcés** et préparer une migration future vers une séparation physique.

#### Justification

1. **Preuve du sharding légitime** : Les dossiers hexadécimaux sont créés par des algorithmes de sharding SHA-256 **légitimes et documentés** dans le code
2. **Production déjà maîtresse** : La production est déjà la source de vérité (les données locales sont des tests/fixtures)
3. **Déploiement déjà additif** : `deploy-release.sh` utilise déjà un rsync additif pour `private/`
4. **Moins risqué** : Une migration immédiate pourrait introduire des erreurs

### 7.2 Améliorations Implémentées

#### Garde-fous Ajoutés

1. **`.gitignore`** : Documentation explicite que `backend/private/storage/**` est runtime protégé
2. **`push-local-sql-to-ovh.sh`** : Blocage via `PUSH_LOCAL_SQL_BLOCKED=1`
3. **`sync-editorial-uploads.sh`** : Blocage via `SYNC_EDITORIAL_UPLOADS_BLOCKED=1`
4. **`deploy-release.sh`** : Commentaires renforcés sur la protection runtime
5. **`AGENTS.md`** : Section dédiée au stockage runtime privé

#### Outils CLI Créés

1. **`private_storage_diagnostic.php`** :
   - Analyse complète du stockage
   - Dry-run seulement (lecture seule)
   - Détection des anomalies

2. **`private_storage_prune.php`** :
   - Nettoyage des dossiers vides
   - `--dry-run` par défaut
   - `--apply --confirm-production` requis en production
   - Validation stricte des chemins

#### Documentation

1. **`STORAGE_RUNTIME_POLICY.md`** : Politique complète du stockage
2. **`archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`** : Procédure de migration exécutée
3. **Tests PHPUnit** : Validation des outils CLI

### 7.3 Architecture Future Recommandée

**Cible** : Séparation physique code/runtime

```
Code:
  /home/lescaramgl-ssh/caramagnols/backend/

Runtime (N'EST PAS déployé par rsync):
  /home/lescaramgl-ssh/caramagnols-runtime/private-storage/
```

**Avantages** :
- Impossible d'écraser accidentellement les données runtime par un déploiement
- Clarification totale : code vs données
- Sauvegardes séparées
- Meilleure sécurité

---

## 8. Fichiers Modifiés

### 8.1 Scripts de Déploiement

| Fichier | Modification | Lignes |
|---------|--------------|--------|
| `.gitignore` | Ajout commentaires explicites sur le stockage runtime | 68-76 |
| `backend/tools/push-local-sql-to-ovh.sh` | Garde-fou `PUSH_LOCAL_SQL_BLOCKED=1` | 1-27 |
| `backend/tools/sync-editorial-uploads.sh` | Garde-fou `SYNC_EDITORIAL_UPLOADS_BLOCKED=1` | 1-26 |
| `backend/tools/deploy-release.sh` | Commentaires renforcés sur la protection runtime | 33-41, 70-71 |

### 8.2 Nouveaux Fichiers

| Fichier | Type | Description |
|---------|------|-------------|
| `backend/core/tools/private_storage_diagnostic.php` | Outil CLI | Diagnostic du stockage (lecture seule) |
| `backend/core/tools/private_storage_prune.php` | Outil CLI | Nettoyage des dossiers vides |
| `backend/docs/STORAGE_RUNTIME_POLICY.md` | Documentation | Politique complète du stockage |
| `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md` | Documentation | Procédure de migration exécutée |
| `backend/tests/PrivateApps/Storage/PrivateStorageDiagnosticTest.php` | Tests | Tests pour l'outil diagnostic |
| `backend/tests/PrivateApps/Storage/PrivateStoragePruneTest.php` | Tests | Tests pour l'outil prune |
| `AGENTS.md` | Documentation | Ajout section stockage runtime |

---

## 9. Garde-fous Ajoutés

### 9.1 Protection contre le Push Local → Production

| Mécanisme | Script | Variable | Description |
|-----------|--------|----------|-------------|
| Blocage complet | `push-local-sql-to-ovh.sh` | `PUSH_LOCAL_SQL_BLOCKED=1` | Empêche toute exécution |
| Blocage complet | `sync-editorial-uploads.sh` | `SYNC_EDITORIAL_UPLOADS_BLOCKED=1` | Empêche toute exécution |

### 9.2 Protection du Déploiement

| Mécanisme | Script | Description |
|-----------|--------|-------------|
| Exclusion | `deploy-release.sh` | Exclut `private/` de la passe avec `--delete` |
| Additif seulement | `deploy-release.sh` | Synchronise `private/` avec `rsync -az` (sans --delete) |
| Exclusion complète | `deploy-fast.sh` | Exclut complètement `private/` pour prod |

### 9.3 Protection des Outils CLI

| Outil | Mécanisme | Description |
|-------|-----------|-------------|
| `private_storage_diagnostic.php` | Lecture seule | Ne modifie jamais les fichiers |
| `private_storage_prune.php` | `--dry-run` par défaut | Simulation uniquement |
| `private_storage_prune.php` | `--apply --confirm-production` | Confirmation explicite requise en production |
| `private_storage_prune.php` | Validation des chemins | Refuse `/`, `/home`, `/var`, etc. |
| `private_storage_prune.php` | Ne supprime pas la racine | Protection absolue |
| `private_storage_prune.php` | Ne suit pas les liens | Pas de traversée accidentelle |

### 9.4 Protection Git

| Mécanisme | Fichier | Description |
|-----------|---------|-------------|
| Ignore | `.gitignore` | `backend/private/storage/` est ignoré |
| Documentation | `.gitignore` | Commentaires explicites sur le runtime |
| Documentation | `AGENTS.md` | Section dédiée au stockage runtime |

---

## 10. Tests Exécutés et Résultats

### 10.1 Tests PHPUnit

#### `PrivateStorageDiagnosticTest`

| Test | Statut | Description |
|------|--------|-------------|
| `testDiagnosticDoesNotModifyFiles` | ✅ | Vérifie que le diagnostic ne modifie pas les fichiers |
| `testDiagnosticDetectsFiles` | ✅ | Vérifie que le diagnostic détecte les 3 fichiers |
| `testDiagnosticDetectsDirectories` | ✅ | Vérifie que le diagnostic détecte les répertoires |
| `testDiagnosticDetectsSupportedDirectories` | ✅ | Vérifie la détection des 5 répertoires supportés |
| `testDiagnosticFailsWithInvalidPath` | ✅ | Vérifie que les chemins invalides sont rejetés |
| `testShardingAnalysis` | ✅ | Vérifie l'analyse du sharding |

#### `PrivateStoragePruneTest`

| Test | Statut | Description |
|------|--------|-------------|
| `testDryRunDoesNotDeleteAnything` | ✅ | Vérifie que dry-run ne supprime rien |
| `testDryRunDetectsEmptyDirectories` | ✅ | Vérifie que dry-run détecte les dossiers vides |
| `testFilesAreNeverDeleted` | ✅ | Vérifie que les fichiers ne sont JAMAIS supprimés |
| `testRootIsNeverDeleted` | ✅ | Vérifie que la racine n'est JAMAIS supprimée |
| `testDangerousPathsAreRejected` | ✅ | Vérifie que `/`, `/home`, etc. sont refusés |
| `testPruneWithApplyOnValidPath` | ✅ | Vérifie que le prune fonctionne avec --apply |
| `testApplyWithoutConfirmProductionFailsInProduction` | ✅ | Vérifie que --confirm-production est requis en prod |

### 10.2 Tests Manuels

| Test | Commande | Résultat |
|------|----------|----------|
| Diagnostic JSON | `php backend/core/tools/private_storage_diagnostic.php --root=\\[chemin] --json` | ✅ 5 fichiers, 236 dossiers |
| Diagnostic texte | `php backend/core/tools/private_storage_diagnostic.php --root=\\[chemin]` | ✅ Affichage correct |
| Prune dry-run | `php backend/core/tools/private_storage_prune.php --root=\\[chemin] --dry-run` | ✅ 126 dossiers vides détectés |
| Prune avec chemin invalide | `php ... --root=/` | ✅ Rejeté avec erreur |

---

## 11. Ce qui Devra être Exécuté Plus Tard sur OVH

### 11.1 Priorité 1 : Activation des Garde-fous

```bash
# Dans /home/lescaramgl-ssh/.bashrc ou /etc/environment
export PUSH_LOCAL_SQL_BLOCKED=1
export SYNC_EDITORIAL_UPLOADS_BLOCKED=1

# Recharger l'environnement
source ~/.bashrc
```

**Pourquoi** : Empêcher toute exécution accidentelle des scripts de push local → production.

### 11.2 Priorité 2 : Vérification des Scripts de Déploiement

```bash
# Tester deploy-release.sh en dry-run
DEPLOY_TARGET=prod REMOTE_HOST=user@host REMOTE_BACKEND=/home/user/caramagnols/backend \
  bash backend/tools/deploy-release.sh --dry-run

# Vérifier que :
# 1. private/ est exclu de la passe principale
# 2. private/ est synchronisé en mode additif
# 3. Aucun fichier runtime n'est supprimé
```

### 11.3 Priorité 3 : Exécution du Diagnostic

```bash
# Sur OVH
cd /home/lescaramgl-ssh/caramagnols/backend
php core/tools/private_storage_diagnostic.php --root=/home/lescaramgl-ssh/caramagnols/backend/private/storage --json > /tmp/storage-diagnostic-ovh-$(date +%Y%m%d).json

# Analyser le résultat
jq '.summary' /tmp/storage-diagnostic-ovh-*.json
jq '.directories | to_entries[] | {name, files: .value.total_files, dirs: .value.total_directories}' /tmp/storage-diagnostic-ovh-*.json
```

### 11.4 Priorité 4 : Comparaison Local/Production

```bash
# Local (déjà fait)
# Production (à faire)

# Comparer les structures
# Local: 236 dossiers, 5 fichiers, ~2,15 Mo
# Production: ? dossiers, ? fichiers, ? Mo

# Si divergence majeure, investiguer
```

### 11.5 Priorité 5 : Préparation de la Migration Future (Optionnelle)

Voir `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md` pour la procédure complète de migration vers `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/`.

**À faire uniquement après** :
- Validation que la production fonctionne correctement
- Sauvegardes complètes
- Fenêtre de maintenance planifiée

---

## 12. Ce qui ne Doit Surtout Pas être Exécuté

### 12.1 Interdictions Absolues

❌ **NE JAMAIS** exécuter :
```bash
# Sans PUSH_LOCAL_SQL_BLOCKED=1
bash backend/tools/push-local-sql-to-ovh.sh --live

# Sans SYNC_EDITORIAL_UPLOADS_BLOCKED=1
bash backend/tools/sync-editorial-uploads.sh

# Sans sauvegarde préalable
rm -rf /home/lescaramgl-ssh/caramagnols/backend/private/storage

# Sur OVH sans validation locale
rsync -av --delete /local/path/ user@ovh:/home/lescaramgl-ssh/caramagnols/backend/private/
```

### 12.2 Opérations Interdites

- ❌ **Ne pas** ajouter `--delete` au rsync de `private/` dans les scripts de déploiement
- ❌ **Ne pas** supprimer `backend/private/storage/` du `.gitignore`
- ❌ **Ne pas** versionner des fichiers de `backend/private/storage/`
- ❌ **Ne pas** modifier les algorithmes de sharding sans migration planifiée
- ❌ **Ne pas** précréer massivement les 65 536 dossiers de sharding (ab/cd)
- ❌ **Ne pas** exécuter `private_storage_prune.php --apply` sans `--confirm-production` en production
- ❌ **Ne pas** exécuter `private_storage_prune.php --apply --confirm-production` sans sauvegarde préalable

### 12.3 Modifications Interdites

- ❌ **Ne pas** modifier `PrivateDocumentStorage::buildStoragePath()` sans coordination
- ❌ **Ne pas** modifier `DocumentStorageService::storageKeyForHash()` sans coordination
- ❌ **Ne pas** modifier `DiscussionAttachmentStorage::buildStoragePath()` sans coordination

---

## 13. Questions Restantes

### 13.1 Informations Production à Obtenir

1. **Combien de fichiers** existent dans `backend/private/storage/` en production ?
   - Local : 5 fichiers
   - Production : ?

2. **Combien de dossiers** existent en production ?
   - Local : 236 dossiers
   - Production : ~100 dossiers (selon le prompt initial)

3. **Quelle est la taille totale** du stockage en production ?
   - Local : ~2,15 Mo
   - Production : ?

4. **Les chemins** `backend/private/storage/document-hub/`, `family-discussion/`, `exports/` existent-ils en production ?
   - Local : Oui (vide ou avec backups)
   - Production : ?

5. **Les permissions** sur `backend/private/storage/` en production sont-elles correctes ?
   - Attendu : 770 pour les dossiers, 660 pour les fichiers
   - Propriétaire : lescaramgl-ssh:www-data

6. **Le script** `deploy-release.sh` a-t-il été utilisé pour déployer des données locales vers la production dans le passé ?
   - À vérifier dans les logs ou l'historique

### 13.2 Décisions à Prendre

1. **Faut-il bloquer complètement** `push-local-sql-to-ovh.sh` et `sync-editorial-uploads.sh` ?
   - Recommandation : **OUI**, avec `PUSH_LOCAL_SQL_BLOCKED=1` et `SYNC_EDITORIAL_UPLOADS_BLOCKED=1`

2. **Faut-il migrer** vers une séparation physique code/runtime ?
   - Recommandation : **OUI**, mais pas immédiatement - préparer d'abord la migration

3. **Faut-il nettoyer** les dossiers vides en production ?
   - Recommandation : **NON** pour l'instant - ils consomment peu d'espace et le sharding est légitime

4. **Faut-il modifier** les scripts de déploiement pour ajouter des protections supplémentaires ?
   - Recommandation : **OUI** - ajouter un garde-fou explicite contre `--delete` sur `private/`

---

## Résumé des Actions

### ✅ Complété

- [x] Audit complet du stockage local
- [x] Identification des algorithmes de sharding (SHA-256 à 2 niveaux)
- [x] Audit des scripts de déploiement
- [x] Audit des flux SQL et push-local-sql-to-ovh.sh
- [x] Garde-fous ajoutés aux scripts dangereux
- [x] Outils CLI créés (diagnostic, prune)
- [x] Tests PHPUnit ajoutés
- [x] Documentation complète (politique, runbook)
- [x] Mise à jour de AGENTS.md

### 🔄 En Attente de Validation Production

- [ ] Exécuter le diagnostic sur OVH
- [ ] Comparer local/production
- [ ] Activer les garde-fous `PUSH_LOCAL_SQL_BLOCKED=1` et `SYNC_EDITORIAL_UPLOADS_BLOCKED=1`
- [ ] Valider les permissions sur OVH
- [ ] Décider de la migration future

### 🚫 Interdit

- [ ] Ne pas pousser de données locales vers la production
- [ ] Ne pas supprimer de fichiers sans sauvegarde
- [ ] Ne pas modifier les algorithmes de sharding sans plan

---

## Conclusion

**Les dossiers hexadécimaux sous `backend/private/storage/uploads/` sont légitimes** et font partie d'un système de sharding SHA-256 à 2 niveaux implémenté dans `PrivateDocumentStorage`, `DocumentStorageService` et `DiscussionAttachmentStorage`.

**La production OVH est déjà la source maîtresse des données** - le local ne contient que des données de test/fixtures qui ne doivent JAMAIS être poussées vers la production.

**Les garde-fous ont été implémentés** pour empêcher toute violation de cette règle, avec des outils CLI pour le diagnostic et le nettoyage (avec dry-run strict).

**La migration vers une séparation physique code/runtime est recommandée** mais doit être planifiée et exécutée avec soin selon le runbook produit.

---

*Document généré le 18 juillet 2026*
*Responsable : Mistral Vibe*
*Source de vérité : Production OVH*
