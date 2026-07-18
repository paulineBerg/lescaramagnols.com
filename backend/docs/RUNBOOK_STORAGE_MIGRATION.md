# Runbook - Migration du Stockage Runtime Production

## Contexte

Ce runbook documente la procédure à suivre pour migrer le stockage runtime privé vers une nouvelle architecture où **code et runtime sont physiquement séparés**. Cela fait suite à l'audit du 18 juillet 2026 qui a confirmé que :

1. Les dossiers hexadécimaux sont légitimes (sharding SHA-256 à 2 niveaux)
2. La production OVH est la source maîtresse des données
3. Le local ne doit JAMAIS pousser des données vers la production

## Architecture Cible

### Actuellement

```
/home/lescaramgl-ssh/caramagnols/backend/private/storage/
├── uploads/
├── document-hub/
├── family-discussion/
├── backups/
└── exports/
```

### Futur (recommandé)

```
# Code (synchronisé par deploy)
/home/lescaramgl-ssh/caramagnols/backend/

# Runtime (N'EST PAS synchronisé par deploy, géré séparément)
/home/lescaramgl-ssh/caramagnols-runtime/private-storage/
├── uploads/
├── document-hub/
├── family-discussion/
├── backups/
└── exports/
```

## Prérequis

### 1. Configuration

Mettre à jour les configurations pour pointer vers le nouveau chemin :

```php
// Dans la configuration de l'application (backend/config/)
app_config('private.documents', [
    'storage_root_path' => '/home/lescaramgl-ssh/caramagnols-runtime/private-storage',
    // ...
]);

app_config('private.document_hub', [
    'storage_root_path' => '/home/lescaramgl-ssh/caramagnols-runtime/private-storage/document-hub',
]);

app_config('private.discussions', [
    'storage_root_path' => '/home/lescaramgl-ssh/caramagnols-runtime/private-storage',
]);
```

**IMPORTANT** : La variable d'environnement `PRIVATE_STORAGE_ROOT` peut être utilisée comme alternative.

### 2. Vérification des Permissions

```bash
# Créer le nouveau répertoire runtime
mkdir -p /home/lescaramgl-ssh/caramagnols-runtime/private-storage/{uploads,document-hub/family-discussion,backups,exports}

# Définir les permissions
chown -R lescaramgl-ssh:www-data /home/lescaramgl-ssh/caramagnols-runtime
chmod 770 /home/lescaramgl-ssh/caramagnols-runtime/private-storage
chmod 770 /home/lescaramgl-ssh/caramagnols-runtime/private-storage/*
```

### 3. Configuration de l'Application

S'assurer que l'application peut lire la configuration :

```php
// Dans bootstrap.php ou config loader
if (getenv('PRIVATE_STORAGE_ROOT')) {
    // Utiliser la variable d'environnement
    $storageRoot = getenv('PRIVATE_STORAGE_ROOT');
} else {
    // Default
    $storageRoot = '/home/lescaramgl-ssh/caramagnols-runtime/private-storage';
}
```

## Procédure de Migration (à exécuter sur OVH)

## État d'Exécution - 2026-07-18

Migration production exécutée le 2026-07-18 vers :

```bash
/home/lescaramgl-ssh/caramagnols-runtime/private-storage
```

Décisions appliquées :

- l'ancien stockage `/home/lescaramgl-ssh/caramagnols/backend/private/storage` est conservé pendant la période de transition ;
- aucune suppression ni archive de l'ancien stockage n'a été exécutée le 2026-07-18 ;
- les étapes optionnelles 8 et 9 restent différées au moins 24-48 h ;
- le groupe attendu `www-data` n'a pas pu être appliqué sur OVH sans privilège root ; l'état effectif validé est `lescaramgl-ssh:users` avec permissions `770/660`, sans accès `others`.

Backups validés :

- pré-migration fichiers : `/homez.855/lescaramgl/caramagnols/backups/files/caramagnols-prod-files-20260718-141517.tar.gz` ;
- pré-migration SQL : `/homez.855/lescaramgl/caramagnols/backups/sql/caramagnols-prod-db-20260718-141517.sql.gz` ;
- post-migration fichiers : `/homez.855/lescaramgl/caramagnols/backups/files/caramagnols-prod-files-20260718-142340.tar.gz` ;
- post-migration SQL : `/homez.855/lescaramgl/caramagnols/backups/sql/caramagnols-prod-db-20260718-142340.sql.gz`.

Journal distant :

```bash
/home/lescaramgl-ssh/caramagnols-runtime/migration-log-20260718-storage-runtime.txt
```

### Étape 0 : Préparation et Sauvegarde

**Durée estimée** : 10-30 minutes

```bash
# 1. Créer les sauvegardes
BACKUP_DATE=$(date +%Y%m%d-%H%M%S)
BACKUP_ROOT="/home/lescaramgl-ssh/backups/caramagnols-storage-migration"

# Sauvegarde SQL complète
mysqldump --user=... --password=... --single-transaction --routines --triggers caramagnols > "$BACKUP_ROOT/sql-full-$BACKUP_DATE.sql"
gzip "$BACKUP_ROOT/sql-full-$BACKUP_DATE.sql"

# Sauvegarde du stockage actuel
tar czf "$BACKUP_ROOT/storage-current-$BACKUP_DATE.tar.gz" \
  /home/lescaramgl-ssh/caramagnols/backend/private/storage

# Vérifier les sauvegardes
du -sh "$BACKUP_ROOT/"
sha256sum "$BACKUP_ROOT/"* > "$BACKUP_ROOT/checksums-$BACKUP_DATE.sha256"

# 2. Vérifier l'espace disque
df -h /home
```

**Validation** :
- [x] Sauvegardes créées avec succès
- [x] Checksums calculés
- [x] Espace disque suffisant pour la copie

### Étape 1 : Créer la Nouvelle Structure Runtime

**Durée estimée** : 5 minutes

```bash
# Créer la structure
mkdir -p /home/lescaramgl-ssh/caramagnols-runtime/private-storage/{uploads,document-hub/objects/sha256,document-hub/derivatives,document-hub/quarantine,document-hub/exports-temp,document-hub/restore-temp,family-discussion/uploads,backups,exports}

# Définir les permissions
chown -R lescaramgl-ssh:www-data /home/lescaramgl-ssh/caramagnols-runtime
find /home/lescaramgl-ssh/caramagnols-runtime -type d -exec chmod 770 {} \;
find /home/lescaramgl-ssh/caramagnols-runtime -type f -exec chmod 660 {} \;

# 3. Vérifier la structure
ls -la /home/lescaramgl-ssh/caramagnols-runtime/private-storage/
```

**Validation** :
- [x] Structure de répertoires créée
- [x] Permissions correctes (770 pour les dossiers, 660 pour les fichiers)
- [ ] Propriétaire correct (lescaramgl-ssh:www-data) - non applicable sur OVH sans privilège root ; état validé : `lescaramgl-ssh:users`

### Étape 2 : Exécuter le Diagnostic Initial

**Durée estimée** : 2-5 minutes

```bash
# Sur le code actuel (avant migration)
cd /home/lescaramgl-ssh/caramagnols/backend
php core/tools/private_storage_diagnostic.php --root=/home/lescaramgl-ssh/caramagnols/backend/private/storage --json > /tmp/storage-diagnostic-before-$BACKUP_DATE.json

# Compter les fichiers et dossiers
find /home/lescaramgl-ssh/caramagnols/backend/private/storage -type f | wc -l > /tmp/files-before.txt
find /home/lescaramgl-ssh/caramagnols/backend/private/storage -type d | wc -l > /tmp/dirs-before.txt

cat /tmp/storage-diagnostic-before-$BACKUP_DATE.json | jq '.summary'
```

**Validation** :
- [x] Diagnostic exécuté avec succès
- [x] Nombre de fichiers et dossiers noté
- [x] Aucune erreur dans le diagnostic

### Étape 3 : Copie des Données (Sans Suppression)

**Durée estimée** : 5-15 minutes (selon la taille)

```bash
# Copie atomique avec rsync (sans suppression)
rsync -av --progress \
  /home/lescaramgl-ssh/caramagnols/backend/private/storage/ \
  /home/lescaramgl-ssh/caramagnols-runtime/private-storage/

# Vérifier la copie
find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type f | wc -l > /tmp/files-after.txt
find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type d | wc -l > /tmp/dirs-after.txt

echo "Fichiers avant: $(cat /tmp/files-before.txt), après: $(cat /tmp/files-after.txt)"
echo "Dossiers avant: $(cat /tmp/dirs-before.txt), après: $(cat /tmp/dirs-after.txt)"

# Vérifier les checksums de quelques fichiers
cd /home/lescaramgl-ssh/caramagnols/backend/private/storage
find uploads -type f -name "*.pdf" -o -name "*.jpg" | head -5 | while read f; do
  sha256_old=$(sha256sum "$f" | awk '{print $1}')
  sha256_new=$(sha256sum "/home/lescaramgl-ssh/caramagnols-runtime/private-storage/$f" | awk '{print $1}')
  if [ "$sha256_old" != "$sha256_new" ]; then
    echo "ERREUR: Checksum différent pour $f"
  fi
done
```

**Validation** :
- [x] Copie terminée avec succès
- [x] Nombre de fichiers identique
- [x] Checksums vérifiés (échantillon)

### Étape 4 : Configuration du Code avec Support Legacy

**Durée estimée** : 5 minutes

```bash
# Déployer le code avec support des deux chemins
cd /home/lescaramgl-ssh/caramagnols

# Le code doit avoir :
# 1. PRIVATE_STORAGE_ROOT pointant vers le nouveau chemin
# 2. Support de lecture depuis l'ancien chemin (fallback)
# 3. Écriture vers le nouveau chemin uniquement

# Déployer avec deploy-release.sh
DEPLOY_TARGET=prod REMOTE_HOST=localhost REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols/backend \
  bash backend/tools/deploy-release.sh --no-vendor --no-schema-sync
```

**Configuration requise dans le code** :

```php
// Dans PrivateDocumentStorage.php, DocumentStorageService.php, DiscussionAttachmentStorage.php
// Modifier le constructeur pour supporter un chemin legacy

public function __construct(string $storageRootPath, ...)
{
    $this->storageRootPath = $storageRootPath;

    // Si le nouveau chemin n'existe pas, basculer vers l'ancien (lecture seule)
    if (!is_dir($this->storageRootPath)) {
        $legacyPath = '/home/lescaramgl-ssh/caramagnols/backend/private/storage';
        if (is_dir($legacyPath)) {
            $this->storageRootPath = $legacyPath;
            $this->legacyMode = true; // Marquer comme mode legacy (lecture seule)
        }
    }
}

// Dans la méthode store(), refuser l'écriture en mode legacy
public function store(...): ?array
{
    if ($this->legacyMode) {
        $this->uploadError = 'legacy_mode_readonly';
        return null;
    }
    // ... suite normale
}
```

**Validation** :
- [x] Code déployé avec succès
- [x] Configuration du nouveau chemin validée
- [x] Support legacy en lecture seule disponible

### Étape 5 : Exécution du Diagnostic en Mode Dual

**Durée estimée** : 2-5 minutes

```bash
# Tester que l'application trouve les fichiers dans les deux emplacements
cd /home/lescaramgl-ssh/caramagnols/backend

# Diagnostic sur le nouveau chemin
php core/tools/private_storage_diagnostic.php --root=/home/lescaramgl-ssh/caramagnols-runtime/private-storage --json > /tmp/storage-diagnostic-new.json

# Vérifier que tous les fichiers sont présents
jq '.summary.total_files' /tmp/storage-diagnostic-before-$BACKUP_DATE.json
jq '.summary.total_files' /tmp/storage-diagnostic-new.json
```

**Validation** :
- [x] Diagnostic sur nouveau chemin exécuté
- [x] Nombre de fichiers identique
- [x] Application fonctionne avec la nouvelle configuration

### Étape 6 : Vérification Fonctionnelle

**Durée estimée** : 10-30 minutes

```bash
# Tester les fonctionnalités critiques :

# 1. Téléchargement de documents
curl -I https://www.lescaramagnols.com/espace-private-4h6F1c/documents/download?document_id=TEST_ID

# 2. Affichage des images du document hub
curl -I https://www.lescaramagnols.com/espace-private-4h6F1c/documents/view?document_id=TEST_ID

# 3. Accès aux pièces jointes des discussions
curl -I https://www.lescaramagnols.com/espace-private-4h6F1c/discussions/attachment?attachment_id=TEST_ID

# 4. Upload d'un nouveau document (doit aller vers le nouveau chemin)
#    À tester via l'interface web

# 5. Vérifier les logs d'application
#    tail -f /home/lescaramgl-ssh/caramagnols/backend/var/log/app.log | grep -i "storage\|upload\|document"
```

**Validation** :
- [x] Routes publiques critiques fonctionnelles (`/`, `/espace-private-4h6F1c/login`, `/espace-admin-7k9m2p`)
- [x] `/private/login` reste non fonctionnel publiquement (HTTP 404)
- [x] Configuration applicative d'upload validée vers le nouveau chemin
- [ ] Nouveau upload manuel authentifié testé
- [x] Aucun nouvel avertissement bloquant après validation finale

### Étape 7 : Deuxième Sauvegarde

**Durée estimée** : 10 minutes

```bash
# Sauvegarde après migration
BACKUP_DATE=$(date +%Y%m%d-%H%M%S)-post-migration

mysqldump --user=... --password=... --single-transaction caramagnols > "$BACKUP_ROOT/sql-post-$BACKUP_DATE.sql"
gzip "$BACKUP_ROOT/sql-post-$BACKUP_DATE.sql"

tar czf "$BACKUP_ROOT/storage-post-$BACKUP_DATE.tar.gz" \
  /home/lescaramgl-ssh/caramagnols-runtime/private-storage

sha256sum "$BACKUP_ROOT/"* > "$BACKUP_ROOT/checksums-post-$BACKUP_DATE.sha256"
```

**Validation** :
- [x] Deuxième sauvegarde créée
- [x] Checksums calculés

### Étape 8 : Désactivation du Legacy (Optionnel, après délai)

**À exécuter 24-48 heures après la migration**

```bash
# 1. Vérifier qu'aucun nouveau fichier n'a été écrit dans l'ancien chemin
find /home/lescaramgl-ssh/caramagnols/backend/private/storage -type f -newer /tmp/storage-diagnostic-before-$BACKUP_DATE.json | wc -l

# Si 0 fichiers récents, on peut désactiver le mode legacy
# 2. Mettre à jour le code pour enlever le support legacy
#    (ou simplement supprimer la vérification du legacy path)

# 3. Redéployer
DEPLOY_TARGET=prod REMOTE_HOST=localhost REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols/backend \
  bash backend/tools/deploy-release.sh --no-vendor --no-schema-sync
```

**Validation** :
- [ ] Aucun nouveau fichier dans l'ancien chemin
- [ ] Code mis à jour et redéployé
- [ ] Étape différée 24-48 h après la migration initiale

### Étape 9 : Nettoyage Final (Optionnel)

**À exécuter après confirmation que tout fonctionne**

```bash
# Nettoyer l'ancien stockage (après sauvegarde finale)
# NE JAMAIS supprimer sans être sûr que tout fonctionne !

# Option 1: Archiver l'ancien stockage
mv /home/lescaramgl-ssh/caramagnols/backend/private/storage \
   /home/lescaramgl-ssh/caramagnols/backend/private/storage-archive-$BACKUP_DATE

# Option 2: Supprimer (APRÈS confirmation absolue)
# rm -rf /home/lescaramgl-ssh/caramagnols/backend/private/storage

# Créer une sentinelle pour empêcher la recréation
mkdir -p /home/lescaramgl-ssh/caramagnols/backend/private/storage
chmod 555 /home/lescaramgl-ssh/caramagnols/backend/private/storage
echo "STORAGE MIGRATED TO /home/lescaramgl-ssh/caramagnols-runtime/private-storage" \
  > /home/lescaramgl-ssh/caramagnols/backend/private/storage/README.txt
```

**Validation** :
- [ ] Ancien stockage archivé ou supprimé
- [ ] Sentinelle mise en place
- [ ] Étape différée après validation complète et sauvegarde finale

## Rollback

### Procédure de Rollback

Si la migration échoue :

```bash
# 1. Restaurer la configuration
#    Revert des changements dans app_config() pour pointer vers l'ancien chemin

# 2. Redéployer le code avec l'ancienne configuration
DEPLOY_TARGET=prod REMOTE_HOST=localhost REMOTE_BACKEND=/home/lescaramgl-ssh/caramagnols/backend \
  bash backend/tools/deploy-release.sh --no-vendor --no-schema-sync

# 3. Restaurer depuis la sauvegarde si nécessaire
#    rsync -av /home/lescaramgl-ssh/caramagnols-runtime/private-storage/ \
#      /home/lescaramgl-ssh/caramagnols/backend/private/storage/

# 4. Vérifier le fonctionnement
cd /home/lescaramgl-ssh/caramagnols/backend
php core/tools/private_storage_diagnostic.php
```

## Journal de la Migration

Créer un fichier de journal pendant la migration :

```bash
MIGRATION_LOG="/home/lescaramgl-ssh/caramagnols-runtime/migration-log-$BACKUP_DATE.txt"

cat >> "$MIGRATION_LOG" << EOF
=== MIGRATION DU STOCKAGE - $(date) ===

Étape 0 - Sauvegarde: $(date)
  - SQL: OK/FAIL
  - Storage: OK/FAIL

Étape 1 - Structure: $(date)
  - Création: OK/FAIL
  - Permissions: OK/FAIL

Étape 2 - Diagnostic initial: $(date)
  - Fichiers: X
  - Dossiers: Y

Étape 3 - Copie: $(date)
  - Début: TIME
  - Fin: TIME
  - Statut: OK/FAIL

Étape 4 - Déploiement: $(date)
  - Statut: OK/FAIL

Étape 5 - Validation: $(date)
  - Statut: OK/FAIL

Étape 6 - Sauvegarde post-migration: $(date)
  - Statut: OK/FAIL

Problèmes rencontrés:
  -

Solution appliquée:
  -

Signataire: _____________________
Date: _____________________
EOF
```

## Checklist Pré-Migration

- [x] Sauvegardes SQL et storage vérifiées
- [x] Espace disque suffisant sur OVH
- [x] Nouvelle structure de répertoires créée
- [x] Permissions correctes sur la nouvelle structure
- [x] Code configuré avec support legacy
- [x] Code déployé avec succès
- [x] Diagnostic exécuté sur l'ancien stockage
- [ ] Équipe informée de la maintenance
- [ ] Fenêtre de maintenance annoncée aux utilisateurs

## Checklist Post-Migration

- [x] Copie des données vérifiée
- [x] Diagnostic exécuté sur le nouveau stockage
- [x] Tests fonctionnels passés
- [ ] Nouveau upload testé
- [x] Deuxième sauvegarde créée
- [ ] Rollback testé (si possible)
- [x] Documentation mise à jour

## Variables d'Environnement Recommandées

```bash
# Dans /home/lescaramgl-ssh/.bashrc ou /etc/environment
export PRIVATE_STORAGE_ROOT="/home/lescaramgl-ssh/caramagnols-runtime/private-storage"
export PUSH_LOCAL_SQL_BLOCKED=1
export SYNC_EDITORIAL_UPLOADS_BLOCKED=1
```

## Sécurité

### Permissions

- Tous les répertoires : `770` (lescaramgl-ssh:www-data)
- Tous les fichiers : `660` (lescaramgl-ssh:www-data)
- Pas de permissions pour `others` (0 pour le dernier chiffre)

### Accès

- Seuls l'utilisateur web et l'administrateur doivent avoir accès
- Aucun accès en lecture pour les autres utilisateurs
- Le répertoire ne doit pas être dans le webroot

## Monitoring

### Vérifications Régulières

```bash
# Vérifier l'espace disque
 du -sh /home/lescaramgl-ssh/caramagnols-runtime/private-storage

# Vérifier le nombre de fichiers
 find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type f | wc -l

# Vérifier les permissions
 find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type d ! -perm 770 -ls
 find /home/lescaramgl-ssh/caramagnols-runtime/private-storage -type f ! -perm 660 -ls
```

### Alertes

Configurer des alertes si :
- Espace disque > 90%
- Nombre de fichiers change brutalement
- Permissions incorrectes détectées

---

*Runbook généré suite à l'audit du 18 juillet 2026*
*À exécuter sur OVH par un administrateur autorisé*
*NE JAMAIS exécuter sans sauvegarde préalable*
