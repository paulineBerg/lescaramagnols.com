#!/usr/bin/env bash

set -euo pipefail

PROJECT="caramagnols-db"
ACTION="dump"

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"
# shellcheck source=../lib/db.sh
source "$BIN_DIR/../lib/db.sh"

OUTPUT_FILE=""

usage() {
  cat <<'EOT'
Usage:
  bash .ops-sync/bin/dump-db-ovh.sh [--output PATH] [--config PATH]
EOT
}

if ! parse_common_args "$@"; then
  usage
  exit 0
fi
set -- "${SYNC_REMAINING_ARGS[@]}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --output)
      shift
      [[ $# -gt 0 ]] || die "Option --output sans valeur"
      OUTPUT_FILE="$1"
      ;;
    --output=*)
      OUTPUT_FILE="${1#*=}"
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
require_db_sync_prerequisites

if [[ -z "$OUTPUT_FILE" ]]; then
  OUTPUT_FILE="$OPS_SYNC_BACKUP_DIR/$(date '+%Y%m%d-%H%M%S')-caramagnols-db-ovh.sql.gz"
fi

dump_ovh_db "$OUTPUT_FILE"
info "Dump termine: $OUTPUT_FILE"
