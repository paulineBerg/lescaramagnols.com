#!/usr/bin/env bash

set -euo pipefail

PROJECT="caramagnols-db"
ACTION="import-local"

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"
# shellcheck source=../lib/db.sh
source "$BIN_DIR/../lib/db.sh"

LOCAL_DB_HOST="127.0.0.1"
LOCAL_DB_PORT="3306"
LOCAL_DB_NAME=""
LOCAL_DB_USER=""
LOCAL_DB_PASSWORD="${LOCAL_DB_PASSWORD:-}"
OUTPUT_FILE=""
DRY_RUN=0
LOCAL_DB_DEFAULTS_FILE="${LOCAL_DB_DEFAULTS_FILE:-}"
LOCAL_DB_LOGIN_PATH="${LOCAL_DB_LOGIN_PATH:-}"

usage() {
  cat <<'EOT'
Usage:
  LOCAL_DB_PASSWORD='motdepasse' \
  bash .ops-sync/bin/import-ovh-db-to-local.sh \
    --local-db=caramagnols \
    --local-user=caramagnols \
    [--local-host=127.0.0.1] \
    [--local-port=3306] \
    [--output=/chemin/dump.sql.gz] \
    [--dry-run]
EOT
}

if ! parse_common_args "$@"; then
  usage
  exit 0
fi
set -- "${SYNC_REMAINING_ARGS[@]}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --local-host=*)
      LOCAL_DB_HOST="${1#*=}"
      ;;
    --local-host)
      shift
      [[ $# -gt 0 ]] || die "Option --local-host sans valeur"
      LOCAL_DB_HOST="$1"
      ;;
    --local-port=*)
      LOCAL_DB_PORT="${1#*=}"
      ;;
    --local-port)
      shift
      [[ $# -gt 0 ]] || die "Option --local-port sans valeur"
      LOCAL_DB_PORT="$1"
      ;;
    --local-db=*)
      LOCAL_DB_NAME="${1#*=}"
      ;;
    --local-db)
      shift
      [[ $# -gt 0 ]] || die "Option --local-db sans valeur"
      LOCAL_DB_NAME="$1"
      ;;
    --local-user=*)
      LOCAL_DB_USER="${1#*=}"
      ;;
    --local-user)
      shift
      [[ $# -gt 0 ]] || die "Option --local-user sans valeur"
      LOCAL_DB_USER="$1"
      ;;
    --local-password=*)
      LOCAL_DB_PASSWORD="${1#*=}"
      ;;
    --local-password)
      shift
      [[ $# -gt 0 ]] || die "Option --local-password sans valeur"
      LOCAL_DB_PASSWORD="$1"
      ;;
    --output=*)
      OUTPUT_FILE="${1#*=}"
      ;;
    --output)
      shift
      [[ $# -gt 0 ]] || die "Option --output sans valeur"
      OUTPUT_FILE="$1"
      ;;
    --dry-run)
      DRY_RUN=1
      ;;
    --local-defaults-file=*)
      LOCAL_DB_DEFAULTS_FILE="${1#*=}"
      ;;
    --local-defaults-file)
      shift
      [[ $# -gt 0 ]] || die "Option --local-defaults-file sans valeur"
      LOCAL_DB_DEFAULTS_FILE="$1"
      ;;
    --local-login-path=*)
      LOCAL_DB_LOGIN_PATH="${1#*=}"
      ;;
    --local-login-path)
      shift
      [[ $# -gt 0 ]] || die "Option --local-login-path sans valeur"
      LOCAL_DB_LOGIN_PATH="$1"
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

[[ -n "$LOCAL_DB_NAME" ]] || die "--local-db est requis"
[[ -n "$LOCAL_DB_USER" ]] || die "--local-user est requis"

if [[ "$DRY_RUN" -eq 0 && -z "$LOCAL_DB_PASSWORD" && -z "$LOCAL_DB_DEFAULTS_FILE" && -z "$LOCAL_DB_LOGIN_PATH" ]]; then
  read -r -s -p "Mot de passe SQL local: " LOCAL_DB_PASSWORD
  printf '\n'
fi

setup_logging
load_sync_config
load_backend_runtime_env
require_db_sync_prerequisites

if [[ -z "$OUTPUT_FILE" ]]; then
  OUTPUT_FILE="$OPS_SYNC_BACKUP_DIR/$(date '+%Y%m%d-%H%M%S')-caramagnols-db-ovh.sql.gz"
fi

info "Source OVH: $OVH_DB_HOST:$OVH_DB_PORT/$OVH_DB_NAME ($OVH_DB_USER)"
info "Cible locale: $LOCAL_DB_HOST:$LOCAL_DB_PORT/$LOCAL_DB_NAME ($LOCAL_DB_USER)"
info "Dump intermediaire: $OUTPUT_FILE"

if [[ "$DRY_RUN" -eq 1 ]]; then
  info "Dry-run: aucune ecriture SQL effectuee"
  exit 0
fi

info "Verification acces base locale"
MYSQL_PWD="$LOCAL_DB_PASSWORD" mysql \
  -h "$LOCAL_DB_HOST" \
  -P "$LOCAL_DB_PORT" \
  -u "$LOCAL_DB_USER" \
  "$LOCAL_DB_NAME" \
  -N -B -e "SELECT 1" >/dev/null

dump_ovh_db "$OUTPUT_FILE"

info "Import du dump OVH dans la base locale"
gunzip -c "$OUTPUT_FILE" | MYSQL_PWD="$LOCAL_DB_PASSWORD" mysql \
  -h "$LOCAL_DB_HOST" \
  -P "$LOCAL_DB_PORT" \
  -u "$LOCAL_DB_USER" \
  "$LOCAL_DB_NAME"

info "Import termine"
