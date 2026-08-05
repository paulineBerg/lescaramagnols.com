#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
REMOTE_HOST="${REMOTE_HOST:-}"
REMOTE_BACKEND="${REMOTE_BACKEND:-}"
SITEMAP_BASE_URL="${SITEMAP_BASE_URL:-}"
DEPLOY_TARGET="${DEPLOY_TARGET:-prod}"
VITE_ASSET_CHECKER="core/tools/check_vite_assets.php"
EDITORIAL_MEDIA_CHECKER="core/tools/check_editorial_media.php"
PROD_TREE_CHECKER="core/tools/check_prod_tree.php"
DEPLOY_SCHEMA_SYNCER="core/tools/sync_deploy_schema.php"

DRY_RUN=0
NO_VENDOR=0
NO_CACHE_CLEAR=0
SCHEMA_SYNC=1

cleanup_remote_non_prod_files() {
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php '$PROD_TREE_CHECKER' --root=. --clean"
}

normalize_deploy_target() {
  case "$1" in
    prod|production) echo "prod" ;;
    preprod|preproduction) echo "preprod" ;;
    *) return 1 ;;
  esac
}

deploys_private_runtime() {
  # backend/private/ est synchronise pour toutes les cibles, en mode additif
  # uniquement (jamais de --delete) pour ne jamais supprimer les fichiers
  # runtime distants (documents prives reels).
  #
  # IMPORTANT: Le stockage runtime sous backend/private/storage/ contient:
  #   - uploads/ : documents privés des utilisateurs
  #   - document-hub/ : hub documentaire CAS
  #   - family-discussion/ : pièces jointes des discussions
  #   - backups/ : sauvegardes document-hub
  #   - exports/ : exports temporaires
  #
  # Ces données sont gérées par l'application et la production est la source
  # maîtresse. Le déploiement synchronise en mode additif uniquement.
  #
  # ATTENTION: Ce script NE DOIT PAS être modifié pour ajouter --delete ou
  # --delete-excluded sur le stockage runtime.
  return 0
}

guard_untracked_source_files() {
  if ! git -C "$REPO_ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    return
  fi

  local untracked
  untracked="$(git -C "$REPO_ROOT" ls-files --others --exclude-standard -- \
    backend/src \
    backend/templates \
    frontend/src \
    | sed '/\/$/d')"

  if [[ -z "$untracked" ]]; then
    return
  fi

  echo "Refus de deploy: fichiers source non suivis detectes (risque d'oubli en production):" >&2
  printf '%s\n' "$untracked" | sed 's/^/  - /' >&2
  echo "Ajoute, commit ou supprime ces fichiers avant de relancer le deploy." >&2
  exit 1
}

usage() {
  cat <<'USAGE'
Usage:
  DEPLOY_TARGET="prod|preprod" REMOTE_HOST="user@host" REMOTE_BACKEND="/home/user/caramagnols/backend" \
  bash backend/tools/deploy-release.sh [--dry-run] [--no-vendor] [--no-cache-clear] [--target=prod|preprod]

Description:
  Full release deploy of backend/ to remote host.
  - backend/private/ is synced additively (rsync without --delete): remote
    runtime files are never deleted by a deploy
  - Target preprod is abandoned (2026-07-17); prod is the only maintained target
  - Keeps remote .env in place
  - Keeps remote config/*.override.php in place (admin runtime settings)
  - Keeps runtime editorial uploads under backend/public/uploads/editorial/
  - Keeps OVH runtime config under backend/public/.ovhconfig
  - Preserves runtime/local artifact directories under backend/var/, backend/data/logs/ and backend/data/snapshots/
  - Excludes and cleans non-production dev/test/docs/temp files
  - Syncs backend/vendor/ by default (OVH without composer)
  - Checks Vite manifest assets locally before deploy and remotely after sync
  - Generates static sitemap at backend/public/sitemap.xml and refreshes the public site summary page
  - Syncs the SQL schema expected by the deployed code unless --no-schema-sync is used
  - Clears runtime cache after deploy (unless --no-cache-clear)

RUNTIME DATA PROTECTION:
  backend/private/storage/** is PROTECTED - it contains user-uploaded private data
  and production is the master source. This directory is synced additively only.
  NO data from local backend/private/storage/ is pushed to production.

Options:
  --dry-run         Preview sync and deletions only.
  --no-vendor       Do not sync backend/vendor/.
  --target=TARGET   Deploy target: prod or preprod (default: prod).
  --prod            Alias for --target=prod.
  --preprod         Alias for --target=preprod.
  --sitemap-base-url=URL  Override base URL for sitemap generation.
  --no-cache-clear  Skip runtime cache clear on remote.
  --no-schema-sync  Skip deploy-time SQL schema synchronization.
  -h, --help        Show help.
USAGE
}

while (($#)); do
  case "$1" in
    --dry-run) DRY_RUN=1 ;;
    --no-vendor) NO_VENDOR=1 ;;
    --target=*) DEPLOY_TARGET="${1#*=}" ;;
    --prod) DEPLOY_TARGET="prod" ;;
    --preprod) DEPLOY_TARGET="preprod" ;;
    --sitemap-base-url=*) SITEMAP_BASE_URL="${1#*=}" ;;
    --no-cache-clear) NO_CACHE_CLEAR=1 ;;
    --no-schema-sync) SCHEMA_SYNC=0 ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
  shift
done

if [[ -z "$REMOTE_HOST" || -z "$REMOTE_BACKEND" ]]; then
  echo "REMOTE_HOST and REMOTE_BACKEND are required." >&2
  usage
  exit 1
fi

if ! DEPLOY_TARGET="$(normalize_deploy_target "$DEPLOY_TARGET")"; then
  echo "DEPLOY_TARGET must be prod or preprod." >&2
  usage
  exit 1
fi

if [[ ! -d "$LOCAL_BACKEND" ]]; then
  echo "Local backend directory not found: $LOCAL_BACKEND" >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/$VITE_ASSET_CHECKER" ]]; then
  echo "Vite asset checker not found: $LOCAL_BACKEND/$VITE_ASSET_CHECKER" >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/$EDITORIAL_MEDIA_CHECKER" ]]; then
  echo "Editorial media checker not found: $LOCAL_BACKEND/$EDITORIAL_MEDIA_CHECKER" >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/$PROD_TREE_CHECKER" ]]; then
  echo "Production tree checker not found: $LOCAL_BACKEND/$PROD_TREE_CHECKER" >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/$DEPLOY_SCHEMA_SYNCER" ]]; then
  echo "Deploy schema syncer not found: $LOCAL_BACKEND/$DEPLOY_SCHEMA_SYNCER" >&2
  exit 1
fi

if [[ "$DRY_RUN" -eq 0 && "$DEPLOY_TARGET" == "prod" ]]; then
  guard_untracked_source_files
fi

echo "Deploy mode: release"
echo "Deploy target: $DEPLOY_TARGET"
echo "Dry run: $DRY_RUN"
echo "Remote: $REMOTE_HOST:$REMOTE_BACKEND"
echo "Sync vendor: $((1 - NO_VENDOR))"
echo "Sync private runtime: additif (sans --delete)"
echo "Sync SQL schema: $SCHEMA_SYNC"
echo "Sitemap base URL override: ${SITEMAP_BASE_URL:-<auto>}"

if [[ "$DRY_RUN" -eq 1 ]]; then
  FILE_COUNT="$(find "$LOCAL_BACKEND" -type f | wc -l | tr -d ' ')"
  echo "[dry-run] backend files detected locally: $FILE_COUNT"
  echo "[dry-run] full rsync would run with --delete and standard excludes."
  echo "[dry-run] private/ sync: additive pass without --delete (remote runtime preserved)"
  if [[ "$SCHEMA_SYNC" -eq 1 ]]; then
    echo "[dry-run] SQL schema sync would run after file sync."
  else
    echo "[dry-run] SQL schema sync disabled."
  fi
  if [[ "$NO_VENDOR" -eq 0 && -d "$LOCAL_BACKEND/vendor" ]]; then
    echo "[dry-run] vendor/ would be synced."
  fi
  echo "[dry-run] Non-production remote files would be cleaned if this deploy ran."
  echo "[dry-run] No remote command executed."
  exit 0
fi

php "$LOCAL_BACKEND/$VITE_ASSET_CHECKER" --public-root="$LOCAL_BACKEND/public"
php "$LOCAL_BACKEND/$EDITORIAL_MEDIA_CHECKER" --check-published-assets --public-root="$LOCAL_BACKEND/public"

ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND'"

RSYNC_FLAGS=(-azv --delete --info=progress2)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(-n)
fi

# /private/ est toujours exclu de la passe principale (qui utilise --delete) ;
# il est synchronise ensuite par une passe additive dediee.
PRIVATE_RSYNC_EXCLUDES=(--exclude="/private/")

rsync "${RSYNC_FLAGS[@]}" \
  --exclude=".git/" \
  --exclude=".env" \
  --exclude=".env.*" \
  --exclude="config/database.override.php" \
  --exclude="config/admin.override.php" \
  --exclude="config/site.override.php" \
  --exclude="vendor/" \
  --exclude="node_modules/" \
  --exclude="tests/" \
  --exclude="docs/" \
  --exclude="README*" \
  "${PRIVATE_RSYNC_EXCLUDES[@]}" \
  --exclude="phpunit.xml" \
  --exclude="phpstan.neon*" \
  --exclude="phpstan.bootstrap.php" \
  --exclude="phpcs.xml" \
  --exclude="package.json" \
  --exclude="package-lock.json" \
  --exclude="npm-shrinkwrap.json" \
  --exclude="replace_image_paths.php" \
  --exclude="*.bak" \
  --exclude="*.old" \
  --exclude="*.orig" \
  --exclude="*.tmp" \
  --exclude="*~" \
  --exclude=".DS_Store" \
  --exclude="Thumbs.db" \
  --exclude="data/logs/" \
  --exclude="data/snapshots/" \
  --exclude="data/*.bak" \
  --exclude="var/" \
  --exclude="public/.ovhconfig" \
  --exclude="public/uploads/" \
  --exclude="public/dev-router.php" \
  "$LOCAL_BACKEND/" "$REMOTE_HOST:$REMOTE_BACKEND/"

if [[ "$NO_VENDOR" -eq 0 && -d "$LOCAL_BACKEND/vendor" ]]; then
  rsync "${RSYNC_FLAGS[@]}" "$LOCAL_BACKEND/vendor/" "$REMOTE_HOST:$REMOTE_BACKEND/vendor/"
fi

if deploys_private_runtime && [[ -d "$LOCAL_BACKEND/private" ]]; then
  # Passe additive : pas de --delete, les fichiers runtime distants sont conserves.
  rsync -az --info=progress2 "$LOCAL_BACKEND/private/" "$REMOTE_HOST:$REMOTE_BACKEND/private/"
fi

if [[ "$SCHEMA_SYNC" -eq 1 ]]; then
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php '$DEPLOY_SCHEMA_SYNCER'"
fi

cleanup_remote_non_prod_files

if [[ -n "$SITEMAP_BASE_URL" ]]; then
  escaped_sitemap_base_url="$(printf '%q' "$SITEMAP_BASE_URL")"
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/generate_sitemap.php --output=public/sitemap.xml --base-url=${escaped_sitemap_base_url} && php core/tools/generate_site_summary.php --base-url=${escaped_sitemap_base_url}"
else
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/generate_sitemap.php --output=public/sitemap.xml && php core/tools/generate_site_summary.php"
fi

ssh "$REMOTE_HOST" "find '$REMOTE_BACKEND' -type d -exec chmod 755 {} \; && \
find '$REMOTE_BACKEND' -type f -exec chmod 644 {} \; && \
test ! -f '$REMOTE_BACKEND/.env' || chmod 640 '$REMOTE_BACKEND/.env' && \
mkdir -p '$REMOTE_BACKEND/var/cache' '$REMOTE_BACKEND/var/log' && \
chmod -R 775 '$REMOTE_BACKEND/var' && \
if [ -d '$REMOTE_BACKEND/private' ]; then \
  find '$REMOTE_BACKEND/private' -type d -exec chmod 770 {} \; && \
  find '$REMOTE_BACKEND/private' -type f -exec chmod 660 {} \; ; \
fi"

ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php '$VITE_ASSET_CHECKER' --public-root=public"

if [[ "$NO_CACHE_CLEAR" -eq 0 ]]; then
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\"]); } echo \"cache_cleared\n\";'"
fi

ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'echo file_exists(\"vendor/autoload.php\") ? \"autoload_ok\n\" : \"autoload_missing\n\";'"

echo "deploy-release completed."
