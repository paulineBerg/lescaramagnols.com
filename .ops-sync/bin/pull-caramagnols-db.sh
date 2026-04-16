#!/usr/bin/env bash

set -euo pipefail

PROJECT="caramagnols-db"
ACTION="pull"

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"
# shellcheck source=../lib/db.sh
source "$BIN_DIR/../lib/db.sh"

SYNC_LIVE=0
SYNC_NO_BACKUP=0

usage() {
  cat <<'EOT'
Usage:
  bash .ops-sync/bin/pull-caramagnols-db.sh [--live] [--no-backup] [--config PATH]

Comportement:
  - sans --live : dry-run, aucune ecriture SQL
  - avec --live : dump OVH, sauvegarde locale optionnelle, import dans la base locale
EOT
}

if ! parse_common_args "$@"; then
  usage
  exit 0
fi
set -- "${SYNC_REMAINING_ARGS[@]}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --live)
      SYNC_LIVE=1
      ;;
    --no-backup)
      SYNC_NO_BACKUP=1
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      die "Option inconnue: $1"
      ;;
  esac
  shift
done

setup_logging
load_sync_config
load_backend_runtime_env
load_local_db_sync_settings
require_db_sync_prerequisites

if [[ "$SYNC_LIVE" -eq 1 && ! local_db_has_noninteractive_auth ]]; then
  read -r -s -p "Mot de passe SQL local (${LOCAL_DB_USER}@${LOCAL_DB_HOST}:${LOCAL_DB_PORT}): " LOCAL_DB_PASSWORD
  printf '\n'
fi

info "Base OVH source: $OVH_DB_NAME ($OVH_DB_HOST:$OVH_DB_PORT)"
info "Base locale cible: $LOCAL_DB_NAME ($LOCAL_DB_HOST:$LOCAL_DB_PORT)"
info "Utilisateur local SQL: $LOCAL_DB_USER"

if [[ "$SYNC_LIVE" -eq 0 ]]; then
  info "Dry-run uniquement: aucun dump ni import BDD n'est execute"
  exit 0
fi

ensure_ops_dirs
ensure_local_db_exists
test_local_db_access >/dev/null

ts="$(date '+%Y%m%d-%H%M%S')"
dump_file="$OPS_SYNC_BACKUP_DIR/${ts}-caramagnols-db-ovh.sql.gz"
local_backup_file="$OPS_SYNC_BACKUP_DIR/${ts}-caramagnols-db-local.sql.gz"

if [[ "$SYNC_NO_BACKUP" -eq 0 ]]; then
  backup_local_db "$local_backup_file"
fi

dump_ovh_db "$dump_file"
import_dump_to_local_db "$dump_file"

info "Import termine"
