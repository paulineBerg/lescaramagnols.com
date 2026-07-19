#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# GARDE-FOU : PRODUCTION MAÎTRESSE - AUCUN UPLOAD LOCAL VERS PRODUCTION
# =============================================================================
# Ce script synchronise les uploads éditoriaux runtime local vers OVH.
#
# ATTENTION : Conformément à la politique "production maîtresse", ce script ne doit
# être utilisé que pour la synchronisation initiale ou de récupération.
#
# Pour bloquer complètement (recommandé), définissez :
#   export SYNC_EDITORIAL_UPLOADS_BLOCKED=1
#
# Ce script NE DOIT PAS être appelé par les scripts de déploiement normal.
# =============================================================================

# Bloquer complètement si la variable d'environnement est définie
if [[ "${SYNC_EDITORIAL_UPLOADS_BLOCKED:-0}" == "1" ]]; then
  echo "BLOQUÉ: SYNC_EDITORIAL_UPLOADS_BLOCKED=1 - ce script est désactivé pour respecter la politique production maîtresse." >&2
  echo "Aucun upload local ne doit être synchronisé vers la production." >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
REMOTE_HOST="${REMOTE_HOST:-}"
REMOTE_BACKEND="${REMOTE_BACKEND:-}"
LOCAL_UPLOADS_DIR="${LOCAL_UPLOADS_DIR:-${LOCAL_BACKEND}/public/uploads/editorial}"

DRY_RUN=0

usage() {
  cat <<'USAGE'
Usage:
  REMOTE_HOST="user@host" REMOTE_BACKEND="/home/user/caramagnols/backend" \
  bash backend/tools/sync-editorial-uploads.sh [--dry-run]

Description:
  Synchronise les uploads editoriaux runtime vers OVH sans suppression distante.
  Source locale: backend/public/uploads/editorial/**
  Cible distante: backend/public/uploads/editorial/**

Options:
  --dry-run  Affiche la synchronisation sans ecriture distante.
  -h, --help Affiche cette aide.
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
  echo "REMOTE_HOST et REMOTE_BACKEND sont requis." >&2
  usage
  exit 1
fi

if [[ ! -d "$LOCAL_BACKEND" ]]; then
  echo "Backend local introuvable: $LOCAL_BACKEND" >&2
  exit 1
fi

if [[ ! -d "$LOCAL_UPLOADS_DIR" ]]; then
  echo "[uploads-sync] Aucun dossier local a synchroniser: $LOCAL_UPLOADS_DIR"
  ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/public/uploads/editorial'"
  exit 0
fi

echo "[uploads-sync] Local: $LOCAL_UPLOADS_DIR"
echo "[uploads-sync] Remote: $REMOTE_HOST:$REMOTE_BACKEND/public/uploads/editorial"
echo "[uploads-sync] Dry run: $DRY_RUN"

ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_BACKEND/public/uploads/editorial'"

RSYNC_FLAGS=(-azv)
if [[ "$DRY_RUN" -eq 1 ]]; then
  RSYNC_FLAGS+=(-n)
fi

rsync "${RSYNC_FLAGS[@]}" \
  "$LOCAL_UPLOADS_DIR/" "$REMOTE_HOST:$REMOTE_BACKEND/public/uploads/editorial/"

echo "[uploads-sync] Terminé."
