#!/usr/bin/env bash

set -euo pipefail

OPS_SYNC_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OPS_SYNC_ROOT="$(cd "$OPS_SYNC_LIB_DIR/.." && pwd)"
OPS_SYNC_CONFIG_DIR="$OPS_SYNC_ROOT/config"
OPS_SYNC_LOG_DIR="$OPS_SYNC_ROOT/logs"
OPS_SYNC_BACKUP_DIR="$OPS_SYNC_ROOT/backups"
OPS_SYNC_DEFAULT_CONFIG="$OPS_SYNC_CONFIG_DIR/ovh.env.local"
OPS_SYNC_REPO_ROOT="$(cd "$OPS_SYNC_ROOT/.." && pwd)"

SYNC_CONFIG_FILE="$OPS_SYNC_DEFAULT_CONFIG"
SYNC_REMAINING_ARGS=()
LOCAL_BACKEND_DIR=""
LOCAL_BACKEND_ENV_FILE=""
OVH_SSH_TARGET=""
OVH_SSH_PORT=""
OVH_REMOTE_APP_ROOT=""
OVH_REMOTE_BACKEND=""
OVH_SITEMAP_BASE_URL=""

BACKEND_BASE_URL=""
OVH_DB_HOST=""
OVH_DB_PORT=""
OVH_DB_NAME=""
OVH_DB_USER=""
OVH_DB_PASSWORD=""

info() {
  printf '[INFO] %s\n' "$*"
}

warn() {
  printf '[WARN] %s\n' "$*" >&2
}

die() {
  printf '[ERROR] %s\n' "$*" >&2
  exit 1
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "Commande requise introuvable: $1"
}

ensure_ops_dirs() {
  mkdir -p "$OPS_SYNC_LOG_DIR" "$OPS_SYNC_BACKUP_DIR"
}

setup_logging() {
  if [[ "${OPS_SYNC_LOG_INITIALIZED:-0}" -eq 1 ]]; then
    return
  fi

  ensure_ops_dirs

  local ts
  ts="$(date '+%Y%m%d-%H%M%S')"
  local project="${PROJECT:-ops-sync}"
  local action="${ACTION:-run}"
  LOG_FILE="$OPS_SYNC_LOG_DIR/${ts}-${project}-${action}.log"
  export LOG_FILE
  export OPS_SYNC_LOG_INITIALIZED=1
  exec > >(tee -a "$LOG_FILE") 2>&1
  info "Log: $LOG_FILE"
}

parse_common_args() {
  SYNC_REMAINING_ARGS=()

  while [[ $# -gt 0 ]]; do
    case "$1" in
      --config)
        shift
        [[ $# -gt 0 ]] || die "Option --config sans valeur"
        SYNC_CONFIG_FILE="$1"
        ;;
      --help|-h)
        return 1
        ;;
      *)
        SYNC_REMAINING_ARGS+=("$1")
        ;;
    esac
    shift
  done

  return 0
}

strip_wrapping_quotes() {
  local value="${1-}"

  if [[ "$value" == \"*\" && "$value" == *\" && "${#value}" -ge 2 ]]; then
    value="${value:1:-1}"
  fi
  if [[ "$value" == \'*\' && "$value" == *\' && "${#value}" -ge 2 ]]; then
    value="${value:1:-1}"
  fi

  printf '%s\n' "$value"
}

read_dotenv_value_optional() {
  local env_file="$1"
  local lookup_key="$2"
  local value

  [[ -f "$env_file" ]] || return 0

  value="$(
    awk -F= -v lookup="$lookup_key" '
      /^[[:space:]]*#/ { next }
      index($0, "=") == 0 { next }
      {
        key = $1
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", key)
        if (key == lookup) {
          sub(/^[^=]*=/, "", $0)
          print $0
          exit
        }
      }
    ' "$env_file"
  )"

  value="${value%$'\r'}"
  strip_wrapping_quotes "$value"
}

read_dotenv_value() {
  local env_file="$1"
  local lookup_key="$2"
  local value

  value="$(read_dotenv_value_optional "$env_file" "$lookup_key")"
  [[ -n "$value" ]] || die "Cle introuvable dans $env_file: $lookup_key"
  printf '%s\n' "$value"
}

load_sync_config() {
  [[ -f "$SYNC_CONFIG_FILE" ]] || die "Fichier de configuration introuvable: $SYNC_CONFIG_FILE"
  # shellcheck disable=SC1090
  source "$SYNC_CONFIG_FILE"

  [[ -n "${LOCAL_BACKEND_DIR:-}" ]] || die "LOCAL_BACKEND_DIR est vide"
  [[ -n "${LOCAL_BACKEND_ENV_FILE:-}" ]] || die "LOCAL_BACKEND_ENV_FILE est vide"
  [[ -n "${OVH_SSH_TARGET:-}" ]] || die "OVH_SSH_TARGET est vide"
  [[ -n "${OVH_SSH_PORT:-}" ]] || die "OVH_SSH_PORT est vide"
  [[ -n "${OVH_REMOTE_APP_ROOT:-}" ]] || die "OVH_REMOTE_APP_ROOT est vide"
  [[ -n "${OVH_REMOTE_BACKEND:-}" ]] || die "OVH_REMOTE_BACKEND est vide"

  [[ -d "$LOCAL_BACKEND_DIR" ]] || die "Dossier backend local introuvable: $LOCAL_BACKEND_DIR"
  [[ -f "$LOCAL_BACKEND_ENV_FILE" ]] || die "Fichier d'environnement backend introuvable: $LOCAL_BACKEND_ENV_FILE"
}

load_backend_runtime_env() {
  BACKEND_BASE_URL="$(read_dotenv_value_optional "$LOCAL_BACKEND_ENV_FILE" "BASE_URL")"
  OVH_DB_HOST="$(read_dotenv_value "$LOCAL_BACKEND_ENV_FILE" "DB_HOST")"
  OVH_DB_PORT="$(read_dotenv_value "$LOCAL_BACKEND_ENV_FILE" "DB_PORT")"
  OVH_DB_NAME="$(read_dotenv_value "$LOCAL_BACKEND_ENV_FILE" "DB_NAME")"
  OVH_DB_USER="$(read_dotenv_value "$LOCAL_BACKEND_ENV_FILE" "DB_USER")"
  OVH_DB_PASSWORD="$(read_dotenv_value "$LOCAL_BACKEND_ENV_FILE" "DB_PASSWORD")"

  if [[ -z "${OVH_SITEMAP_BASE_URL:-}" && -n "$BACKEND_BASE_URL" ]]; then
    OVH_SITEMAP_BASE_URL="$BACKEND_BASE_URL"
  fi
}

require_sync_prerequisites() {
  require_cmd bash
  require_cmd ssh
  require_cmd rsync
}

ssh_base_cmd() {
  printf 'ssh -p %q %q' "$OVH_SSH_PORT" "$OVH_SSH_TARGET"
}

ssh_run() {
  ssh -p "$OVH_SSH_PORT" "$OVH_SSH_TARGET" "$@"
}
