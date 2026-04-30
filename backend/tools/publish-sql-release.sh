#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
LOCAL_FRONTEND="${LOCAL_FRONTEND:-${REPO_ROOT}/frontend}"
PHP_BIN="${PHP_BIN:-php}"
REMOTE_HOST="${REMOTE_HOST:-lescaramgl-ssh@ssh.cluster103.hosting.ovh.net}"
REMOTE_BACKEND="${REMOTE_BACKEND:-/home/lescaramgl-ssh/caramagnols/backend}"
SITEMAP_BASE_URL="${SITEMAP_BASE_URL:-https://www.lescaramagnols.com}"

LIVE=0
DRY_RUN=0
ALLOW_DELETE=0
INCLUDE_DISCUSSIONS=0
KEEP_REMOTE_BACKUPS=0
SYNC_ASSETS=1
EXPORT_JSON=1

usage() {
  cat <<'USAGE'
Usage:
  bash backend/tools/publish-sql-release.sh --live [--allow-delete] [--include-discussions] [--no-assets] [--no-json-export] [--keep-remote-backups] [--dry-run]

Description:
  Workflow unique de publication SQL maitre:
  - exporte le SQL editorial local vers les miroirs JSON versionnables
  - build le frontend local
  - deploie le backend release
  - pousse le SQL editorial local vers OVH
  - relance un clear final des caches runtime distants

Options:
  --live                 Obligatoire pour autoriser la publication.
  --allow-delete         Autorise les suppressions editoriales detectees.
  --include-discussions  Inclut aussi les discussions de blog dans l'export/push.
  --no-assets            N'envoie pas les assets frontend dans le push SQL.
  --no-json-export       N'ecrit pas les miroirs JSON locaux avant publication.
  --keep-remote-backups  Conserve les backups temporaires sur OVH.
  --dry-run              Simule les etapes outillees sans ecriture distante.
  -h, --help             Affiche cette aide.
USAGE
}

while (($#)); do
  case "$1" in
    --live) LIVE=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --allow-delete) ALLOW_DELETE=1 ;;
    --include-discussions) INCLUDE_DISCUSSIONS=1 ;;
    --no-assets) SYNC_ASSETS=0 ;;
    --no-json-export) EXPORT_JSON=0 ;;
    --keep-remote-backups) KEEP_REMOTE_BACKUPS=1 ;;
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
  echo "Refus: ajoute --live pour autoriser la publication." >&2
  usage
  exit 1
fi

if [[ ! -d "$LOCAL_BACKEND" ]]; then
  echo "Backend local introuvable: $LOCAL_BACKEND" >&2
  exit 1
fi

if [[ ! -d "$LOCAL_FRONTEND" ]]; then
  echo "Frontend local introuvable: $LOCAL_FRONTEND" >&2
  exit 1
fi

echo "Publication SQL maitre"
echo "Repo: $REPO_ROOT"
echo "Remote: $REMOTE_HOST:$REMOTE_BACKEND"
echo "Sitemap base URL: $SITEMAP_BASE_URL"
echo "Allow delete: $ALLOW_DELETE"
echo "Include discussions: $INCLUDE_DISCUSSIONS"
echo "Sync assets: $SYNC_ASSETS"
echo "Export JSON: $EXPORT_JSON"
echo "Dry run: $DRY_RUN"

EXPORT_ARGS=()
PUSH_ARGS=(--live)

if [[ "$INCLUDE_DISCUSSIONS" -eq 1 ]]; then
  EXPORT_ARGS+=(--include-discussions)
  PUSH_ARGS+=(--include-discussions)
fi

if [[ "$ALLOW_DELETE" -eq 1 ]]; then
  PUSH_ARGS+=(--allow-delete)
fi

if [[ "$SYNC_ASSETS" -eq 0 ]]; then
  PUSH_ARGS+=(--no-assets)
fi

if [[ "$KEEP_REMOTE_BACKUPS" -eq 1 ]]; then
  PUSH_ARGS+=(--keep-remote-backups)
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
  EXPORT_ARGS+=(--dry-run)
  PUSH_ARGS+=(--dry-run)
fi

if [[ "$EXPORT_JSON" -eq 1 ]]; then
  echo "[1/4] Export SQL -> JSON"
  "$PHP_BIN" "$LOCAL_BACKEND/core/tools/export_sql_editorial_to_json.php" "${EXPORT_ARGS[@]}"
else
  echo "[1/4] Export SQL -> JSON ignore (--no-json-export)"
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "[2/4] Build frontend ignore en dry-run"
else
  echo "[2/4] Build frontend"
  (cd "$LOCAL_FRONTEND" && npm run build)
fi

echo "[3/4] Deploy release"
if [[ "$DRY_RUN" -eq 1 ]]; then
  REMOTE_HOST="$REMOTE_HOST" REMOTE_BACKEND="$REMOTE_BACKEND" SITEMAP_BASE_URL="$SITEMAP_BASE_URL" \
    bash "$REPO_ROOT/backend/tools/deploy-release.sh" --dry-run
else
  REMOTE_HOST="$REMOTE_HOST" REMOTE_BACKEND="$REMOTE_BACKEND" SITEMAP_BASE_URL="$SITEMAP_BASE_URL" \
    bash "$REPO_ROOT/backend/tools/deploy-release.sh"
fi

echo "[4/4] Push SQL editorial"
REMOTE_HOST="$REMOTE_HOST" REMOTE_BACKEND="$REMOTE_BACKEND" SITEMAP_BASE_URL="$SITEMAP_BASE_URL" \
  bash "$REPO_ROOT/backend/tools/push-local-sql-to-ovh.sh" "${PUSH_ARGS[@]}"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "[dry-run] Clear final des caches distants ignore."
  exit 0
fi

ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\",\"tiles\"]); } echo \"cache_cleared_final\n\";'"
