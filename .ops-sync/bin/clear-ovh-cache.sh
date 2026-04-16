#!/usr/bin/env bash

set -euo pipefail

PROJECT="ops-sync"
ACTION="clear-cache"

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"

SYNC_LIVE=0

usage() {
  cat <<'EOT'
Usage:
  bash .ops-sync/bin/clear-ovh-cache.sh [--live] [--config PATH]
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

remote_cmd="cd '$OVH_REMOTE_BACKEND' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\"]); } echo \"cache_cleared\n\";'"

if [[ "$SYNC_LIVE" -eq 0 ]]; then
  info "Dry-run: aucune commande distante executee"
  info "Commande SSH: $(ssh_base_cmd) $(printf '%q' "$remote_cmd")"
  exit 0
fi

ssh_run "$remote_cmd"
