#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
REMOTE_HOST="${REMOTE_HOST:-lescaramgl-ssh@ssh.cluster103.hosting.ovh.net}"
REMOTE_BACKEND="${REMOTE_BACKEND:-/home/lescaramgl-ssh/caramagnols/backend}"
SITEMAP_BASE_URL="${SITEMAP_BASE_URL:-https://www.lescaramagnols.com}"
BACKUP_ROOT="${BACKUP_ROOT:-/home/surfacepro8/backups/caramagnols}"
PHP_BIN="${PHP_BIN:-php}"
ADMIN_RUNTIME_SNAPSHOT_TOOL="core/tools/export_admin_runtime_settings.php"
EDITORIAL_MEDIA_CHECKER="core/tools/check_editorial_media.php"
EDITORIAL_MEDIA_VALIDATOR_CLASS="src/Editorial/EditorialMediaValidator.php"
UPLOADS_SYNC_SCRIPT="${REPO_ROOT}/backend/tools/sync-editorial-uploads.sh"

LIVE=0
DRY_RUN=0
KEEP_REMOTE_BACKUPS=0
ALLOW_DELETE=0
INCLUDE_DISCUSSIONS=0
SYNC_ASSETS=1
SYNC_UPLOADS=1

usage() {
  cat <<'USAGE'
Usage:
  bash backend/tools/push-local-sql-to-ovh.sh --live [--allow-delete] [--include-discussions] [--no-assets] [--no-uploads] [--keep-remote-backups]

Description:
  Synchronise le contenu editorial SQL local vers la production OVH.
  La commande passe par core/tools/editorial_backup_restore.php, pas par un dump MySQL brut.
  Par defaut elle couvre pages, navigation, articles de blog et tuiles.
  Elle exclut les donnees runtime/sensibles: utilisateurs, logs, meta schema, commentaires legacy et discussions de blog.
  Elle verifie aussi que les reglages admin Cron Center et Sauvegardes restent inchanges.
  Elle synchronise aussi les assets publies par le build frontend: manifest Vite, bundles CSS/JS et images publiques.
  Elle synchronise aussi les uploads editoriaux runtime sous backend/public/uploads/editorial/**, sauf --no-uploads.
  Elle bloque l'envoi si une page, un article, une navigation ou un groupe de tuiles actif reference un media manquant.

Etapes:
  - verifie les references medias editoriales locales
  - synchronise les assets frontend publies, sauf --no-assets
  - synchronise les uploads editoriaux runtime, sauf --no-uploads
  - met a jour l'outil de sync editorial sur OVH
  - capture les reglages admin runtime prod avant ecriture
  - cree un backup SQL local
  - cree un backup SQL prod OVH avant ecriture et le rapatrie hors depot
  - affiche un diff local/prod et bloque les suppressions sauf --allow-delete
  - copie le payload local vers OVH avec controle de taille
  - restaure le payload en prod avec --storage=sql
  - compare les reglages admin Cron/Sauvegardes avant/apres restauration
  - regenere index de recherche, sitemap et caches
  - cree un backup prod apres ecriture et compare son contenu au local
  - lance les controles prod
  - supprime les temporaires OVH, sauf --keep-remote-backups

Options:
  --live                 Obligatoire pour autoriser l'ecriture prod.
  --allow-delete         Autorise les suppressions detectees par le diff editorial.
  --include-discussions  Inclut aussi les discussions de blog; a eviter sauf besoin explicite.
  --no-assets            Ne pousse pas les assets frontend publies.
  --no-uploads           Ne pousse pas les uploads editoriaux runtime.
  --dry-run              Affiche la configuration puis sort sans ecriture.
  --keep-remote-backups  Conserve les backups temporaires aussi sur OVH.
  -h, --help             Affiche cette aide.
USAGE
}

while (($#)); do
  case "$1" in
    --live) LIVE=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --keep-remote-backups) KEEP_REMOTE_BACKUPS=1 ;;
    --allow-delete) ALLOW_DELETE=1 ;;
    --include-discussions) INCLUDE_DISCUSSIONS=1 ;;
    --no-assets) SYNC_ASSETS=0 ;;
    --no-uploads) SYNC_UPLOADS=0 ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Option inconnue: $1" >&2
      usage
      exit 1
      ;;
  esac
  shift
done

if [[ "$LIVE" -ne 1 ]]; then
  echo "Refus: ajoute --live pour autoriser l'ecriture sur la production OVH." >&2
  usage
  exit 1
fi

if [[ ! -d "$LOCAL_BACKEND" ]]; then
  echo "Backend local introuvable: $LOCAL_BACKEND" >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/core/tools/editorial_backup_restore.php" ]]; then
  echo "Outil backup/restore introuvable dans $LOCAL_BACKEND." >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/$ADMIN_RUNTIME_SNAPSHOT_TOOL" ]]; then
  echo "Outil snapshot reglages admin introuvable dans $LOCAL_BACKEND." >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/$EDITORIAL_MEDIA_CHECKER" ]]; then
  echo "Outil validation medias introuvable dans $LOCAL_BACKEND." >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/$EDITORIAL_MEDIA_VALIDATOR_CLASS" ]]; then
  echo "Classe validation medias introuvable dans $LOCAL_BACKEND." >&2
  exit 1
fi

if [[ ! -f "$UPLOADS_SYNC_SCRIPT" ]]; then
  echo "Script sync uploads introuvable: $UPLOADS_SYNC_SCRIPT" >&2
  exit 1
fi

echo "Push SQL editorial local -> OVH"
echo "Local backend: $LOCAL_BACKEND"
echo "Remote: $REMOTE_HOST:$REMOTE_BACKEND"
echo "Backups: $BACKUP_ROOT"
echo "Sitemap base URL: $SITEMAP_BASE_URL"
echo "Allow delete: $ALLOW_DELETE"
echo "Include discussions: $INCLUDE_DISCUSSIONS"
echo "Sync assets: $SYNC_ASSETS"
echo "Sync uploads: $SYNC_UPLOADS"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "[dry-run] Aucune sauvegarde ni ecriture distante ne sera lancee."
  exit 0
fi

SYNC_FLAGS=(--storage=sql)
DIFF_FLAGS=(--storage=sql)
RESTORE_FLAGS=(--force --storage=sql)
REMOTE_SYNC_FLAGS="--storage=sql"
REMOTE_DIFF_FLAGS="--storage=sql"
REMOTE_RESTORE_FLAGS="--force --storage=sql"

if [[ "$INCLUDE_DISCUSSIONS" -eq 1 ]]; then
  SYNC_FLAGS+=(--include-discussions)
  DIFF_FLAGS+=(--include-discussions)
  RESTORE_FLAGS+=(--include-discussions)
  REMOTE_SYNC_FLAGS="$REMOTE_SYNC_FLAGS --include-discussions"
  REMOTE_DIFF_FLAGS="$REMOTE_DIFF_FLAGS --include-discussions"
  REMOTE_RESTORE_FLAGS="$REMOTE_RESTORE_FLAGS --include-discussions"
fi

if [[ "$ALLOW_DELETE" -eq 1 ]]; then
  DIFF_FLAGS+=(--allow-delete)
  RESTORE_FLAGS+=(--allow-delete)
  REMOTE_DIFF_FLAGS="$REMOTE_DIFF_FLAGS --allow-delete"
  REMOTE_RESTORE_FLAGS="$REMOTE_RESTORE_FLAGS --allow-delete"
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
LOCAL_DIR="$BACKUP_ROOT/local"
PROD_DIR="$BACKUP_ROOT/prod"
mkdir -p "$LOCAL_DIR" "$PROD_DIR"

LOCAL_JSON="$LOCAL_DIR/editorial-local-sql-before-push-${STAMP}.json"
LOCAL_GZ="${LOCAL_JSON}.gz"
REMOTE_BACKUP_JSON="var/backups/editorial-prod-sql-before-local-push-${STAMP}.json"
REMOTE_BACKUP_GZ="${REMOTE_BACKUP_JSON}.gz"
REMOTE_PAYLOAD="var/backups/editorial-local-sql-payload-${STAMP}.json"
REMOTE_AFTER_JSON="var/backups/editorial-prod-sql-after-local-push-${STAMP}.json"
REMOTE_AFTER_GZ="${REMOTE_AFTER_JSON}.gz"
REMOTE_ADMIN_BEFORE_JSON="var/backups/admin-runtime-before-local-push-${STAMP}.json"
REMOTE_ADMIN_AFTER_JSON="var/backups/admin-runtime-after-local-push-${STAMP}.json"
TMP_JSON="$(mktemp "/tmp/editorial-local-sql-payload-${STAMP}.XXXXXX.json")"

cleanup_local() {
  rm -f "$TMP_JSON"
}
trap cleanup_local EXIT

echo "[1/15] Verification locale des references medias editoriales"
"$PHP_BIN" "$LOCAL_BACKEND/$EDITORIAL_MEDIA_CHECKER" --check-published-assets --public-root="$LOCAL_BACKEND/public"

if [[ "$SYNC_ASSETS" -eq 1 ]]; then
  echo "[2/15] Synchronisation des assets frontend publies"
  "$PHP_BIN" "$LOCAL_BACKEND/core/tools/check_vite_assets.php" --public-root="$LOCAL_BACKEND/public"
  ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/public/.vite' '$REMOTE_BACKEND/public/assets'"
  rsync -az "$LOCAL_BACKEND/public/.vite/" "$REMOTE_HOST:$REMOTE_BACKEND/public/.vite/"
  rsync -az --prune-empty-dirs \
    --include='*/' \
    --include='*.css' \
    --include='*.js' \
    --include='*.jpg' \
    --include='*.jpeg' \
    --include='*.png' \
    --include='*.webp' \
    --include='*.gif' \
    --include='*.svg' \
    --exclude='*' \
    "$LOCAL_BACKEND/public/assets/" "$REMOTE_HOST:$REMOTE_BACKEND/public/assets/"
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/check_vite_assets.php --public-root=public"
else
  echo "[2/15] Synchronisation des assets frontend publies ignoree (--no-assets)"
fi

if [[ "$SYNC_UPLOADS" -eq 1 ]]; then
  echo "[3/15] Synchronisation des uploads editoriaux runtime"
  REMOTE_HOST="$REMOTE_HOST" REMOTE_BACKEND="$REMOTE_BACKEND" LOCAL_BACKEND="$LOCAL_BACKEND" \
    bash "$UPLOADS_SYNC_SCRIPT"
else
  echo "[3/15] Synchronisation des uploads editoriaux runtime ignoree (--no-uploads)"
fi

echo "[4/15] Mise a jour des outils de sync editorial sur OVH"
ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/core/tools'"
rsync -az "$LOCAL_BACKEND/core/tools/editorial_backup_restore.php" "$REMOTE_HOST:$REMOTE_BACKEND/core/tools/editorial_backup_restore.php"
rsync -az "$LOCAL_BACKEND/$ADMIN_RUNTIME_SNAPSHOT_TOOL" "$REMOTE_HOST:$REMOTE_BACKEND/$ADMIN_RUNTIME_SNAPSHOT_TOOL"
rsync -az "$LOCAL_BACKEND/$EDITORIAL_MEDIA_CHECKER" "$REMOTE_HOST:$REMOTE_BACKEND/$EDITORIAL_MEDIA_CHECKER"
ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/src/Editorial'"
rsync -az "$LOCAL_BACKEND/$EDITORIAL_MEDIA_VALIDATOR_CLASS" "$REMOTE_HOST:$REMOTE_BACKEND/$EDITORIAL_MEDIA_VALIDATOR_CLASS"

echo "[5/15] Snapshot reglages admin prod avant ecriture"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && mkdir -p var/backups && php '$ADMIN_RUNTIME_SNAPSHOT_TOOL' --output='$REMOTE_ADMIN_BEFORE_JSON' && stat -c '%s %n' '$REMOTE_ADMIN_BEFORE_JSON'"
scp -q "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_ADMIN_BEFORE_JSON" "$PROD_DIR/"
PROD_ADMIN_BEFORE_LOCAL="$PROD_DIR/$(basename "$REMOTE_ADMIN_BEFORE_JSON")"
chmod 600 "$PROD_ADMIN_BEFORE_LOCAL"
stat -c '%a %s %n' "$PROD_ADMIN_BEFORE_LOCAL"

echo "[6/15] Backup SQL local"
"$PHP_BIN" "$LOCAL_BACKEND/core/tools/editorial_backup_restore.php" backup "${SYNC_FLAGS[@]}" --output="$LOCAL_JSON"
gzip -f "$LOCAL_JSON"
chmod 600 "$LOCAL_GZ"
stat -c '%a %s %n' "$LOCAL_GZ"

echo "[7/15] Backup SQL prod OVH avant ecriture"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && mkdir -p var/backups && php core/tools/editorial_backup_restore.php backup $REMOTE_SYNC_FLAGS --output='$REMOTE_BACKUP_JSON' && gzip -f '$REMOTE_BACKUP_JSON' && stat -c '%s %n' '$REMOTE_BACKUP_GZ'"
scp -q "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_BACKUP_GZ" "$PROD_DIR/"
PROD_BEFORE_LOCAL="$PROD_DIR/$(basename "$REMOTE_BACKUP_GZ")"
chmod 600 "$PROD_BEFORE_LOCAL"
stat -c '%a %s %n' "$PROD_BEFORE_LOCAL"

echo "[8/15] Diff editorial local -> prod"
"$PHP_BIN" "$LOCAL_BACKEND/core/tools/editorial_backup_restore.php" diff "$LOCAL_GZ" "$PROD_BEFORE_LOCAL" "${DIFF_FLAGS[@]}"

echo "[9/15] Copie du payload local vers OVH"
gzip -dc "$LOCAL_GZ" > "$TMP_JSON"
chmod 600 "$TMP_JSON"
LOCAL_SIZE="$(stat -c '%s' "$TMP_JSON")"
scp -q "$TMP_JSON" "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_PAYLOAD"
REMOTE_SIZE="$(ssh "$REMOTE_HOST" "stat -c '%s' '$REMOTE_BACKEND/$REMOTE_PAYLOAD'")"
echo "Payload local=$LOCAL_SIZE distant=$REMOTE_SIZE"
if [[ "$LOCAL_SIZE" != "$REMOTE_SIZE" ]]; then
  echo "Payload distant incomplet: tailles differentes." >&2
  exit 1
fi

echo "[10/15] Restore SQL prod et regeneration"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/editorial_backup_restore.php restore '$REMOTE_PAYLOAD' $REMOTE_RESTORE_FLAGS && php core/tools/generate_search_index.php && php core/tools/generate_sitemap.php --output=public/sitemap.xml --base-url='$SITEMAP_BASE_URL' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\",\"tiles\"]); } echo \"cache_cleared_after_sql_restore\n\";'"

echo "[11/15] Verification reglages admin Cron/Sauvegardes"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php '$ADMIN_RUNTIME_SNAPSHOT_TOOL' --output='$REMOTE_ADMIN_AFTER_JSON' && stat -c '%s %n' '$REMOTE_ADMIN_AFTER_JSON'"
scp -q "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_ADMIN_AFTER_JSON" "$PROD_DIR/"
PROD_ADMIN_AFTER_LOCAL="$PROD_DIR/$(basename "$REMOTE_ADMIN_AFTER_JSON")"
chmod 600 "$PROD_ADMIN_AFTER_LOCAL"
stat -c '%a %s %n' "$PROD_ADMIN_AFTER_LOCAL"
if ! cmp -s "$PROD_ADMIN_BEFORE_LOCAL" "$PROD_ADMIN_AFTER_LOCAL"; then
  echo "Refus: les reglages admin Cron/Sauvegardes ont change pendant la restauration SQL." >&2
  diff -u "$PROD_ADMIN_BEFORE_LOCAL" "$PROD_ADMIN_AFTER_LOCAL" || true
  exit 1
fi
echo "Reglages admin Cron/Sauvegardes inchanges."

echo "[12/15] Backup prod apres ecriture"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/editorial_backup_restore.php backup $REMOTE_SYNC_FLAGS --output='$REMOTE_AFTER_JSON' && gzip -f '$REMOTE_AFTER_JSON' && stat -c '%s %n' '$REMOTE_AFTER_GZ'"
scp -q "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_AFTER_GZ" "$PROD_DIR/"
PROD_AFTER_LOCAL="$PROD_DIR/$(basename "$REMOTE_AFTER_GZ")"
chmod 600 "$PROD_AFTER_LOCAL"
stat -c '%a %s %n' "$PROD_AFTER_LOCAL"

echo "[13/15] Comparaison local/prod apres restore"
"$PHP_BIN" "$LOCAL_BACKEND/core/tools/editorial_backup_restore.php" compare "$LOCAL_GZ" "$PROD_AFTER_LOCAL" "${SYNC_FLAGS[@]}"

echo "[14/15] Validation medias cote production"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php '$EDITORIAL_MEDIA_CHECKER' --skip-source-assets --check-published-assets --public-root=public"

echo "[15/15] Controles prod"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/check_vite_assets.php --public-root=public && php core/tools/check_prod_tree.php --root=. && php core/tools/check_env.php --env=production --strict-prod-security"

if command -v composer >/dev/null 2>&1; then
  composer check-security-headers --working-dir="$LOCAL_BACKEND" -- --url="$SITEMAP_BASE_URL"
fi

echo "[cleanup] Nettoyage des temporaires"
if [[ "$KEEP_REMOTE_BACKUPS" -eq 1 ]]; then
  ssh "$REMOTE_HOST" "rm -f '$REMOTE_BACKEND/$REMOTE_PAYLOAD'"
  echo "Backups conserves sur OVH a la demande."
else
  ssh "$REMOTE_HOST" "rm -f '$REMOTE_BACKEND/$REMOTE_PAYLOAD' '$REMOTE_BACKEND/$REMOTE_BACKUP_GZ' '$REMOTE_BACKEND/$REMOTE_AFTER_GZ' '$REMOTE_BACKEND/$REMOTE_ADMIN_BEFORE_JSON' '$REMOTE_BACKEND/$REMOTE_ADMIN_AFTER_JSON'"
fi

echo "Push SQL editorial local -> OVH termine."
echo "Backup local source: $LOCAL_GZ"
echo "Backup prod avant: $PROD_BEFORE_LOCAL"
echo "Backup prod apres: $PROD_AFTER_LOCAL"
echo "Snapshot reglages admin avant: $PROD_ADMIN_BEFORE_LOCAL"
echo "Snapshot reglages admin apres: $PROD_ADMIN_AFTER_LOCAL"
