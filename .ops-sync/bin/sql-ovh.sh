#!/usr/bin/env bash

set -euo pipefail

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"
# shellcheck source=../lib/db.sh
source "$BIN_DIR/../lib/db.sh"

QUERY=""

usage() {
  cat <<'EOT'
Usage:
  bash .ops-sync/bin/sql-ovh.sh [--query="SELECT 1"] [--config PATH]
EOT
}

if ! parse_common_args "$@"; then
  usage
  exit 0
fi
set -- "${SYNC_REMAINING_ARGS[@]}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --query=*)
      QUERY="${1#*=}"
      ;;
    --query)
      shift
      [[ $# -gt 0 ]] || die "Option --query sans valeur"
      QUERY="$1"
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

load_sync_config
load_backend_runtime_env
require_db_sync_prerequisites

if [[ -n "$QUERY" ]]; then
  run_ovh_mysql -t -e "$QUERY"
  exit 0
fi

info "Connexion MySQL interactive sur $OVH_DB_HOST:$OVH_DB_PORT/$OVH_DB_NAME avec $OVH_DB_USER"
exec env MYSQL_PWD="$OVH_DB_PASSWORD" mysql \
  -h "$OVH_DB_HOST" \
  -P "$OVH_DB_PORT" \
  -u "$OVH_DB_USER" \
  "$OVH_DB_NAME"
