# Politique de Stockage Runtime Privé - Les Caramagnols

## Invariant Absolu

**La production OVH est l'unique source de vérité des données.**

### Flux autorisés

```
Local/Git -> Production : code, migrations de structure et assets de build uniquement
Production -> sauvegarde : données SQL et fichiers runtime
Production -> local : copie de test explicitement demandée et protégée
Local -> Production : AUCUNE donnée SQL, AUCUN upload, AUCUN document runtime
```

## Architecture du Stockage

### Emplacement Production

Depuis la migration production du 2026-07-18, le stockage runtime privé réside hors de l'arborescence du backend :

```text
/home/lescaramgl-ssh/caramagnols-runtime/private-storage/
```

Le code reste sous :

```text
/home/lescaramgl-ssh/caramagnols/backend/
```

Cette séparation est volontaire : `caramagnols-runtime/` n'est pas un doublon du projet, mais le conteneur des données utilisateurs et fichiers générés. Il ne doit pas être synchronisé par les scripts de déploiement du code.

### Emplacement Legacy

L'ancien stockage peut encore exister pendant la période de transition :

```text
/home/lescaramgl-ssh/caramagnols/backend/private/storage/
```

Il sert uniquement de fallback/rollback tant que le runbook ne demande pas explicitement son archivage. Il ne doit plus être considéré comme destination d'écriture production.

En local, le chemin historique suivant peut rester utilisé pour le développement :

```text
backend/private/storage/
```

Ce répertoire est **ignoré par Git** (voir `.gitignore`) et contient des données générées par l'application.

### Structure

```
private-storage/
├── uploads/              # Documents privés (PrivateApps/Documents)
│   └── <ab>/<cd>/        # Sharding SHA-256 à 2 niveaux
│       └── <documentId>.<ext>
│
├── document-hub/         # Hub documentaire CAS (Content-Addressable Storage)
│   ├── objects/sha256/<ab>/<cd>/<sha256>  # Objets originaux immuables
│   ├── derivatives/      # Dérivés (vignettes, aperçus)
│   ├── quarantine/       # Fichiers en attente de validation
│   ├── exports-temp/     # Archives d'export temporaires
│   └── restore-temp/     # Espace de restauration isolé
│
├── family-discussion/    # Pièces jointes des discussions familiales
│   └── uploads/<ab>/<cd>/<attachmentId>.<ext>  # Sharding SHA-256
│
├── backups/              # Sauvegardes du document-hub
│   └── document-hub/<YYYYMMDD>/
│       ├── objects/      # Copie des objets CAS
│       ├── document-hub-manifest.json
│       └── document-hub-SHA256SUMS
│
└── exports/              # Exports temporaires (réservé)
```

### Algorithmes de Sharding

#### Documents Privés (uploads/)

**Source** : `PrivateApps/Documents/PrivateDocumentStorage.php`

```php
private function buildStoragePath(string $documentId, string $extension): string
{
    $hash = hash('sha256', $documentId . '|' . (string) time());
    return sprintf('uploads/%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), $documentId, $extension);
}
```

- **Niveau 1** : 2 premiers caractères du hash SHA-256
- **Niveau 2** : 2 caractères suivants du hash SHA-256
- **Nom du fichier** : documentId (jusqu'à 64 caractères alphanumériques)

#### Hub Documentaire (document-hub/objects/)

**Source** : `PrivateApps/Documents/Service/DocumentStorageService.php`

```php
public function storageKeyForHash(string $sha256): string
{
    return sprintf('objects/sha256/%s/%s/%s', substr($sha256, 0, 2), substr($sha256, 2, 2), $sha256);
}
```

- **Niveau 1** : 2 premiers caractères du hash SHA-256
- **Niveau 2** : 2 caractères suivants du hash SHA-256
- **Nom du fichier** : hash SHA-256 complet (64 caractères hexadécimaux)

#### Discussions Familiales (family-discussion/uploads/)

**Source** : `PrivateApps/FamilyDiscussion/Attachment/DiscussionAttachmentStorage.php`

```php
private function buildStoragePath(string $attachmentId, string $extension): string
{
    $hash = hash('sha256', $attachmentId . '|' . time());
    return sprintf('family-discussion/uploads/%s/%s/%s.%s', substr($hash, 0, 2), substr($hash, 2, 2), $attachmentId, $extension);
}
```

- **Niveau 1** : 2 premiers caractères du hash SHA-256
- **Niveau 2** : 2 caractères suivants du hash SHA-256
- **Nom du fichier** : attachmentId (jusqu'à 64 caractères)
- **Particularité** : Les fichiers sont chiffrés avec AES-256-GCM

## Règles de Protection

### 1. Git

- `backend/private/storage/` est **ignoré par Git**
- Aucun fichier de données utilisateur ne doit être versionné
- Les fichiers de configuration (`.gitkeep`, `.htaccess`) sont autorisés

### 2. Déploiement

#### deploy-release.sh

- ne doit pas synchroniser `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/` ;
- ne doit jamais supprimer ni remplacer les fichiers runtime distants ;
- l'ancien `backend/private/` ne doit rester qu'un périmètre de code/config ou de transition, jamais une source locale de données production.

#### deploy-fast.sh

- **exclut complètement** les données runtime privées pour la cible `prod` ;
- ne doit jamais pousser d'uploads, documents ou sauvegardes runtime depuis le local.

### 3. Scripts Bloqués

Les scripts suivants sont **désactivables par variable d'environnement** :

| Script | Variable | Description |
|--------|----------|-------------|
| `push-local-sql-to-ovh.sh` | `PUSH_LOCAL_SQL_BLOCKED=1` | Bloque tout push SQL local vers production |
| `sync-editorial-uploads.sh` | `SYNC_EDITORIAL_UPLOADS_BLOCKED=1` | Bloque la sync des uploads locaux |

**Recommandation** : Définir ces variables dans l'environnement de production pour prévenir toute exécution accidentelle.

## Outils CLI

### Diagnostic du Stockage

Analyse l'état du stockage sans modifier les données.

```bash
# Diagnostic complet
php backend/core/tools/private_storage_diagnostic.php

# Avec sortie JSON
php backend/core/tools/private_storage_diagnostic.php --json

# Chemin racine personnalisé
php backend/core/tools/private_storage_diagnostic.php --root=/chemin/vers/private/storage
```

**Sortie** :
- Nombre total de dossiers et fichiers
- Dossiers vides vs non-vides
- Taille totale
- Analyse du sharding
- Avertissements pour les répertoires inattendus

### Nettoyage des Dossiers Vides

Supprime uniquement les dossiers **VIDES** selon des règles strictes.

```bash
# Simulation (dry-run)
php backend/core/tools/private_storage_prune.php --dry-run

# Suppression effective (nécessite confirmation en production)
php backend/core/tools/private_storage_prune.php --apply --confirm-production

# Avec options
php backend/core/tools/private_storage_prune.php \
  --dry-run \
  --min-age=86400 \  # 24 heures
  --max-depth=5 \
  --json
```

**Règles de sécurité** :
- Ne supprime **JAMAIS** de fichiers
- Ne supprime **JAMAIS** la racine du stockage
- Ne suit **PAS** les liens symboliques
- Vérifie que le dossier est encore vide au moment de la suppression
- Nécessite `--apply` ET `--confirm-production` en environnement de production
- La racine et les chemins dangereux sont refusés

## Configuration

### Chemins de Stockage

Le chemin production est piloté par :

```bash
PRIVATE_STORAGE_ROOT=/home/lescaramgl-ssh/caramagnols-runtime/private-storage
```

`backend/config/config.php` déduit alors :

- `private.documents.storage_root_path=/home/lescaramgl-ssh/caramagnols-runtime`
- `private.documents.storage_directory=private-storage`
- `private.document_hub.storage_root_path=/home/lescaramgl-ssh/caramagnols-runtime/private-storage/document-hub`
- `private.discussions.storage_root_path=/home/lescaramgl-ssh/caramagnols-runtime`
- `private.discussions.storage_directory=private-storage`

Sans `PRIVATE_STORAGE_ROOT`, les chemins de stockage restent configurables via `app_config()` :

```php
// Pour les documents privés
app_config('private.documents', [
    'storage_root_path' => ROOT_PATH . '/private',
    'storage_directory' => 'storage',
    'uploads_directory' => 'uploads',
    'exports_directory' => 'exports',
    // ...
]);

// Pour le document hub
app_config('private.document_hub', [
    'storage_root_path' => ROOT_PATH . '/private/storage/document-hub',
]);

// Pour les discussions familiales
app_config('private.discussions', [
    'storage_root_path' => ROOT_PATH . '/private',
    // ...
]);
```

### Configuration Recommandée pour la Production

```bash
# Dans l'environnement OVH (via .env ou CARAMAGNOLS_OPS_ENV_FILE)
export PRIVATE_STORAGE_ROOT=/home/lescaramgl-ssh/caramagnols-runtime/private-storage
export PUSH_LOCAL_SQL_BLOCKED=1
export SYNC_EDITORIAL_UPLOADS_BLOCKED=1
```

## Procédures Production

### Migration du Stockage vers un Nouveau Chemin

La migration vers `/home/lescaramgl-ssh/caramagnols-runtime/private-storage` a été exécutée le 2026-07-18. Le runbook et ses checklists sont archivés dans `backend/docs/archive/2026-07-storage/RUNBOOK_STORAGE_MIGRATION.md`.

Si une nouvelle migration est nécessaire :

1. **Configurer le nouveau chemin** via `PRIVATE_STORAGE_ROOT`
2. **Vérifier** les chemins déduits par `app_config()`
3. **Vérifier** les permissions attendues (`770` dossiers, `660` fichiers)
4. **Tester** en local avec les nouveaux chemins
5. **Sur OVH** :
   - Créer le nouveau répertoire
   - Synchroniser les fichiers existants
   - Mettre à jour la configuration
   - Vérifier que tout fonctionne
   - Maintenir l'ancien chemin en lecture seule pendant une période de transition
   - Ne supprimer l'ancien stockage qu'après le délai prévu par le runbook

### Nettoyage des Dossiers Vides

**À exécuter sur OVH uniquement** :

```bash
# 1. D'abord une simulation
php backend/core/tools/private_storage_prune.php --root=/home/lescaramgl-ssh/caramagnols/backend/private/storage --dry-run

# 2. Revuer la liste des dossiers à supprimer

# 3. Exécuter avec confirmation
php backend/core/tools/private_storage_prune.php \
  --root=/home/lescaramgl-ssh/caramagnols/backend/private/storage \
  --apply \
  --confirm-production \
  --min-age=86400  # 24 heures (optionnel)
```

## Sauvegardes

### Ce qui doit être sauvegardé

- **Base de données** : Toutes les tables privées
- **Stockage runtime** : `/home/lescaramgl-ssh/caramagnols-runtime/private-storage/` complet
- **Configuration** : `backend/private/` (sans le storage)

### Script de Sauvegarde Recommandé

```bash
#!/bin/bash
BACKUP_DATE=$(date +%Y%m%d-%H%M%S)
BACKUP_ROOT="/home/user/backups/caramagnols"
PROD_ROOT="/home/lescaramgl-ssh/caramagnols/backend"
RUNTIME_ROOT="/home/lescaramgl-ssh/caramagnols-runtime/private-storage"

# Sauvegarde SQL
mysqldump --user=... --password=... caramagnols_private > "$BACKUP_ROOT/sql/caramagnols-private-$BACKUP_DATE.sql"

# Sauvegarde du stockage runtime
tar czf "$BACKUP_ROOT/storage/private-storage-$BACKUP_DATE.tar.gz" "$RUNTIME_ROOT"

# Sauvegarde de la configuration
tar czf "$BACKUP_ROOT/config/private-config-$BACKUP_DATE.tar.gz" "$PROD_ROOT/private"
```

## Tests

### Vérification de l'Intégrité

```bash
# Vérifier les checksums du document hub
cd /home/lescaramgl-ssh/caramagnols-runtime/private-storage/backups/document-hub/<DATE>
sha256sum -c document-hub-SHA256SUMS
```

### Validation du Sharding

```bash
# Compter les dossiers de niveau 1 dans uploads
find /home/lescaramgl-ssh/caramagnols-runtime/private-storage/uploads -mindepth 1 -maxdepth 1 -type d | wc -l

# Compter les fichiers totaux
find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type f | wc -l
```

## Résolution des Problèmes

### Problème : Beaucoup de dossiers vides

**Cause** : Le sharding SHA-256 crée des dossiers à la demande. Avec peu de fichiers, de nombreux dossiers de niveau 2 restent vides.

**Solution** :
1. C'est **normal** et ne consomme que peu d'espace
2. Ne pas précréer massivement les dossiers
3. Utiliser `private_storage_prune.php` si nécessaire, mais d'abord identifier la cause

### Problème : Fichiers manquants

**Vérifications** :
1. Exécuter `private_storage_diagnostic.php --json`
2. Vérifier les logs d'application pour les erreurs d'upload
3. Vérifier que le `documentId` ou `sha256` est correct
4. Vérifier les permissions sur les répertoires

### Problème : Accès refusé

**Vérifications** :
1. Les permissions doivent être `770` pour les répertoires, `660` pour les fichiers
2. Le propriétaire doit être l'utilisateur web/SSH OVH (`lescaramgl-ssh`)
3. Le groupe attendu peut être `users` sur OVH si `www-data` n'est pas applicable par `chown`

```bash
# Corriger les permissions
find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type d -exec chmod 770 {} \;
find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type f -exec chmod 660 {} \;
# Sur OVH, chown vers www-data peut être refusé sans privilège root.
```

## Historique des Décisions

| Date | Décision | Justification |
|------|----------|---------------|
| 2026-07-18 | Sharding SHA-256 confirmé | Audit du code a révélé les algorithmes dans PrivateDocumentStorage, DocumentStorageService, DiscussionAttachmentStorage |
| 2026-07-18 | backend/private/storage/ marqué comme runtime protégé | Déjà ignoré par Git, officiellement documenté |
| 2026-07-18 | Garde-fous ajoutés à push-local-sql-to-ovh.sh | Bloque l'exécution accidentelle avec PUSH_LOCAL_SQL_BLOCKED=1 |
| 2026-07-18 | Garde-fous ajoutés à sync-editorial-uploads.sh | Bloque l'exécution accidentelle avec SYNC_EDITORIAL_UPLOADS_BLOCKED=1 |
| 2026-07-18 | Outils CLI créés | private_storage_diagnostic.php et private_storage_prune.php |
| 2026-07-18 | Production maître confirmée | Aucune donnée locale ne doit être poussée en production |
| 2026-07-18 | Migration runtime production exécutée | Données privées séparées du code sous `/home/lescaramgl-ssh/caramagnols-runtime/private-storage` |

## Références

- **Architecture** : Voir `backend/src/PrivateApps/AGENTS.md`
- **Sécurité** : Voir `AGENTS.md` (racine du dépôt)
- **Déploiement** : Voir `backend/tools/deploy-release.sh`
- **Configuration** : Voir `backend/config/`

---

*Document généré suite à l'audit du 18 juillet 2026*
*Source de vérité : production OVH*
