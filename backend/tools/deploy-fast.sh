#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
REMOTE_HOST="${REMOTE_HOST:-}"
REMOTE_BACKEND="${REMOTE_BACKEND:-}"
SITEMAP_BASE_URL="${SITEMAP_BASE_URL:-}"
VITE_ASSET_CHECKER="core/tools/check_vite_assets.php"
EDITORIAL_MEDIA_CHECKER="core/tools/check_editorial_media.php"
PROD_TREE_CHECKER="core/tools/check_prod_tree.php"
PUBLISHED_FRONTEND_SYNC_SCRIPT="${REPO_ROOT}/backend/tools/sync-published-frontend-tree.sh"

DRY_RUN=0
WITH_VENDOR=0
NO_CACHE_CLEAR=0
ALL_CHANGES=0

is_deploy_excluded_path() {
  case "$1" in
    .env|.env.*|config/*.override.php|vendor|vendor/*|node_modules|node_modules/*|tests|tests/*|docs|docs/*|README*|var|var/*|phpunit.xml|phpstan.neon*|phpstan.bootstrap.php|phpcs.xml|package.json|package-lock.json|npm-shrinkwrap.json|replace_image_paths.php|data/snapshots|data/snapshots/*|data/logs|data/logs/*|*.bak|*.old|*.orig|*.tmp|*~|.DS_Store|Thumbs.db|public/uploads|public/uploads/*|public/dev-router.php)
      return 0
      ;;
  esac

  return 1
}

cleanup_remote_non_prod_files() {
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php '$PROD_TREE_CHECKER' --root=. --clean"
}

usage() {
  cat <<'USAGE'
Usage:
  REMOTE_HOST="user@host" REMOTE_BACKEND="/home/user/caramagnols/backend" \
  bash backend/tools/deploy-fast.sh [--dry-run] [--with-vendor] [--no-cache-clear] [--all-changes]

Description:
  Deploy only changed files from local backend/ to remote backend/.
  Default scope is staged backend changes (safe mode).
  - Preserves remote .env
  - Preserves remote config/*.override.php (admin runtime settings)
  - Preserves runtime editorial uploads under backend/public/uploads/editorial/
  - Excludes and cleans non-production dev/test/docs/temp files
  - Checks Vite manifest assets locally before deploy and remotely after sync
  - Synchronises the full published frontend tree (.vite, assets, tarteaucitron)
  - Generates static sitemap at backend/public/sitemap.xml and refreshes the public site summary page
  - Clears runtime cache after deploy (unless --no-cache-clear)

Options:
  --dry-run         Preview actions without writing remote files.
  --with-vendor     Also sync backend/vendor/ (recommended if lock changed).
  --all-changes     Include unstaged + untracked backend files.
  --sitemap-base-url=URL  Override base URL for sitemap generation.
  --no-cache-clear  Skip runtime cache clear on remote.
  -h, --help        Show help.
USAGE
}

while (($#)); do
  case "$1" in
    --dry-run) DRY_RUN=1 ;;
    --with-vendor) WITH_VENDOR=1 ;;
    --all-changes) ALL_CHANGES=1 ;;
    --sitemap-base-url=*) SITEMAP_BASE_URL="${1#*=}" ;;
    --no-cache-clear) NO_CACHE_CLEAR=1 ;;
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

if [[ ! -f "$PUBLISHED_FRONTEND_SYNC_SCRIPT" ]]; then
  echo "Published frontend sync script not found: $PUBLISHED_FRONTEND_SYNC_SCRIPT" >&2
  exit 1
fi

if ! git -C "$REPO_ROOT" rev-parse --git-dir >/dev/null 2>&1; then
  echo "Git repository not found from $REPO_ROOT" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
ALL_CHANGED="$TMP_DIR/all_changed.txt"
CHANGED_FILES="$TMP_DIR/changed_files.txt"
DELETED_FILES="$TMP_DIR/deleted_files.txt"

: > "$ALL_CHANGED"
: > "$CHANGED_FILES"
: > "$DELETED_FILES"

if [[ "$ALL_CHANGES" -eq 1 ]]; then
  {
    git -C "$REPO_ROOT" diff --name-only -- backend
    git -C "$REPO_ROOT" diff --cached --name-only -- backend
    git -C "$REPO_ROOT" ls-files -o --exclude-standard backend
  } | sort -u > "$ALL_CHANGED"
else
  git -C "$REPO_ROOT" diff --cached --name-only -- backend | sort -u > "$ALL_CHANGED"
fi

while IFS= read -r rel_path; do
  [[ -n "$rel_path" ]] || continue
  [[ "$rel_path" == backend/* ]] || continue

  path="${rel_path#backend/}"
  if is_deploy_excluded_path "$path"; then
    continue
  fi

  if [[ -e "$LOCAL_BACKEND/$path" ]]; then
    printf '%s\n' "$path" >> "$CHANGED_FILES"
  else
    printf '%s\n' "$path" >> "$DELETED_FILES"
  fi
done < "$ALL_CHANGED"

if [[ ! -s "$CHANGED_FILES" && ! -s "$DELETED_FILES" ]]; then
  if [[ "$ALL_CHANGES" -eq 1 ]]; then
    echo "No backend change detected. Nothing to deploy."
  else
    echo "No staged backend change detected. Use git add or --all-changes."
  fi
  exit 0
fi

echo "Deploy mode: fast"
echo "Dry run: $DRY_RUN"
echo "Scope: $([[ "$ALL_CHANGES" -eq 1 ]] && echo "all changes" || echo "staged only")"
echo "Remote: $REMOTE_HOST:$REMOTE_BACKEND"
echo "Changed files: $(wc -l < "$CHANGED_FILES" | tr -d ' ')"
echo "Deleted files: $(wc -l < "$DELETED_FILES" | tr -d ' ')"
echo "Sitemap base URL override: ${SITEMAP_BASE_URL:-<auto>}"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "[dry-run] Changed files that would be synced:"
  cat "$CHANGED_FILES"
  if [[ -s "$DELETED_FILES" ]]; then
    echo "[dry-run] Files that would be deleted remotely:"
    cat "$DELETED_FILES"
  fi
  if [[ "$WITH_VENDOR" -eq 1 && -d "$LOCAL_BACKEND/vendor" ]]; then
    echo "[dry-run] vendor/ would be synced."
  fi
  echo "[dry-run] Non-production remote files would be cleaned if this deploy ran."
  echo "[dry-run] No remote command executed."
  exit 0
fi

php "$LOCAL_BACKEND/$VITE_ASSET_CHECKER" --public-root="$LOCAL_BACKEND/public"
php "$LOCAL_BACKEND/$EDITORIAL_MEDIA_CHECKER" --check-published-assets --public-root="$LOCAL_BACKEND/public"

ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND'"

RSYNC_FLAGS=(-azv --info=progress2)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(-n)
fi

if [[ -s "$CHANGED_FILES" ]]; then
  rsync "${RSYNC_FLAGS[@]}" --files-from="$CHANGED_FILES" "$LOCAL_BACKEND/" "$REMOTE_HOST:$REMOTE_BACKEND/"
fi

REMOTE_HOST="$REMOTE_HOST" REMOTE_BACKEND="$REMOTE_BACKEND" LOCAL_BACKEND="$LOCAL_BACKEND" \
  bash "$PUBLISHED_FRONTEND_SYNC_SCRIPT"

ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/core/tools'"
rsync "${RSYNC_FLAGS[@]}" "$LOCAL_BACKEND/$VITE_ASSET_CHECKER" "$REMOTE_HOST:$REMOTE_BACKEND/$VITE_ASSET_CHECKER"
rsync "${RSYNC_FLAGS[@]}" "$LOCAL_BACKEND/$PROD_TREE_CHECKER" "$REMOTE_HOST:$REMOTE_BACKEND/$PROD_TREE_CHECKER"

if [[ "$WITH_VENDOR" -eq 1 && -d "$LOCAL_BACKEND/vendor" ]]; then
  rsync "${RSYNC_FLAGS[@]}" --delete "$LOCAL_BACKEND/vendor/" "$REMOTE_HOST:$REMOTE_BACKEND/vendor/"
fi

if [[ -s "$DELETED_FILES" ]]; then
  ssh "$REMOTE_HOST" "while IFS= read -r rel; do [ -n \"\$rel\" ] || continue; rm -rf -- '$REMOTE_BACKEND'/\"\$rel\"; done" < "$DELETED_FILES"
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
chmod -R 775 '$REMOTE_BACKEND/var'"

ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php '$VITE_ASSET_CHECKER' --public-root=public"

if [[ "$NO_CACHE_CLEAR" -eq 0 ]]; then
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\"]); } echo \"cache_cleared\n\";'"
fi

ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'echo file_exists(\"vendor/autoload.php\") ? \"autoload_ok\n\" : \"autoload_missing\n\";'"

echo "deploy-fast completed."
