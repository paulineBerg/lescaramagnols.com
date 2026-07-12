#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
REMOTE_HOST="${REMOTE_HOST:-}"
REMOTE_BACKEND="${REMOTE_BACKEND:-}"
DRY_RUN=0

usage() {
  cat <<'USAGE'
Usage:
  REMOTE_HOST="user@host" REMOTE_BACKEND="/home/user/caramagnols/backend" \
  bash backend/tools/sync-published-frontend-tree.sh [--dry-run]

Description:
  Synchronise le front publie vers OVH sans filtrer les nouveautes par extension.
  Le miroir couvre :
  - backend/public/.vite/**
  - backend/public/assets/**
  - backend/public/tarteaucitron/**
USAGE
}

while (($#)); do
  case "$1" in
    --dry-run) DRY_RUN=1 ;;
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

if [[ -z "$REMOTE_HOST" || -z "$REMOTE_BACKEND" ]]; then
  echo "REMOTE_HOST et REMOTE_BACKEND sont obligatoires." >&2
  exit 1
fi

if [[ ! -d "$LOCAL_BACKEND/public/assets" ]]; then
  echo "Assets publics locaux introuvables: $LOCAL_BACKEND/public/assets" >&2
  exit 1
fi

if [[ ! -d "$LOCAL_BACKEND/public/.vite" ]]; then
  echo "Manifest Vite local introuvable: $LOCAL_BACKEND/public/.vite" >&2
  exit 1
fi

RSYNC_FLAGS=(-az --delete)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(-n)
fi

ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/public/.vite' '$REMOTE_BACKEND/public/assets'"
rsync "${RSYNC_FLAGS[@]}" "$LOCAL_BACKEND/public/.vite/" "$REMOTE_HOST:$REMOTE_BACKEND/public/.vite/"
rsync "${RSYNC_FLAGS[@]}" "$LOCAL_BACKEND/public/assets/" "$REMOTE_HOST:$REMOTE_BACKEND/public/assets/"

if [[ -d "$LOCAL_BACKEND/public/tarteaucitron" ]]; then
  ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/public/tarteaucitron'"
  rsync "${RSYNC_FLAGS[@]}" "$LOCAL_BACKEND/public/tarteaucitron/" "$REMOTE_HOST:$REMOTE_BACKEND/public/tarteaucitron/"
else
  ssh "$REMOTE_HOST" "rm -rf '$REMOTE_BACKEND/public/tarteaucitron'"
fi

ssh "$REMOTE_HOST" "find '$REMOTE_BACKEND/public/.vite' '$REMOTE_BACKEND/public/assets' -type d -exec chmod 755 {} \; && \
find '$REMOTE_BACKEND/public/.vite' '$REMOTE_BACKEND/public/assets' -type f -exec chmod 644 {} \; && \
if [ -d '$REMOTE_BACKEND/public/tarteaucitron' ]; then \
  find '$REMOTE_BACKEND/public/tarteaucitron' -type d -exec chmod 755 {} \; && \
  find '$REMOTE_BACKEND/public/tarteaucitron' -type f -exec chmod 644 {} \; ; \
fi"

echo "sync-published-frontend-tree completed."
