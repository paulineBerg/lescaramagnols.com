#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
REMOTE_HOST="${REMOTE_HOST:-}"
REMOTE_BACKEND="${REMOTE_BACKEND:-}"
SITEMAP_BASE_URL="${SITEMAP_BASE_URL:-}"

DRY_RUN=0
WITH_VENDOR=0
NO_CACHE_CLEAR=0
ALL_CHANGES=0

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
  - Generates static sitemap at backend/public/sitemap.xml
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

if ! git -C "$REPO_ROOT" rev-parse --git-dir >/dev/null 2>&1; then
  echo "Git repository not found from $REPO_ROOT" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
ALL_CHANGED="$TMP_DIR/all_changed.txt"
CHANGED_FILES="$TMP_DIR/changed_files.txt"
DELETED_FILES="$TMP_DIR/deleted_files.txt"

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
  case "$path" in
    .env|.env.*|config/*.override.php|vendor/*|node_modules/*|tests/*|docs/*|README*|var/*|phpunit.xml|phpstan.neon*|phpcs.xml|data/snapshots/*|data/logs/*|data/*.bak|public/assets/images/*|public/uploads/*)
      continue
      ;;
  esac

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
  echo "[dry-run] No remote command executed."
  exit 0
fi

ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND'"
ssh "$REMOTE_HOST" "test -f '$REMOTE_BACKEND/.env' && cp '$REMOTE_BACKEND/.env' '$REMOTE_BACKEND/.env.bak.$(date +%F-%H%M%S)' || true"

RSYNC_FLAGS=(-azv --info=progress2)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(-n)
fi

if [[ -s "$CHANGED_FILES" ]]; then
  rsync "${RSYNC_FLAGS[@]}" --files-from="$CHANGED_FILES" "$LOCAL_BACKEND/" "$REMOTE_HOST:$REMOTE_BACKEND/"
fi

if [[ "$WITH_VENDOR" -eq 1 && -d "$LOCAL_BACKEND/vendor" ]]; then
  rsync "${RSYNC_FLAGS[@]}" --delete "$LOCAL_BACKEND/vendor/" "$REMOTE_HOST:$REMOTE_BACKEND/vendor/"
fi

if [[ -s "$DELETED_FILES" ]]; then
  ssh "$REMOTE_HOST" "while IFS= read -r rel; do [ -n \"\$rel\" ] || continue; rm -rf -- '$REMOTE_BACKEND'/\"\$rel\"; done" < "$DELETED_FILES"
fi

if [[ -n "$SITEMAP_BASE_URL" ]]; then
  escaped_sitemap_base_url="$(printf '%q' "$SITEMAP_BASE_URL")"
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/generate_sitemap.php --output=public/sitemap.xml --base-url=${escaped_sitemap_base_url}"
else
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/generate_sitemap.php --output=public/sitemap.xml"
fi

ssh "$REMOTE_HOST" "find '$REMOTE_BACKEND' -type d -exec chmod 755 {} \; && \
find '$REMOTE_BACKEND' -type f -exec chmod 644 {} \; && \
test ! -f '$REMOTE_BACKEND/.env' || chmod 640 '$REMOTE_BACKEND/.env' && \
mkdir -p '$REMOTE_BACKEND/var/cache' '$REMOTE_BACKEND/var/log' && \
chmod -R 775 '$REMOTE_BACKEND/var'"

if [[ "$NO_CACHE_CLEAR" -eq 0 ]]; then
  ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\"]); } echo \"cache_cleared\n\";'"
fi

ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'echo file_exists(\"vendor/autoload.php\") ? \"autoload_ok\n\" : \"autoload_missing\n\";'"

echo "deploy-fast completed."
