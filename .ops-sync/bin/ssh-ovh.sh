#!/usr/bin/env bash

set -euo pipefail

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"

usage() {
  cat <<'EOT'
Usage:
  bash .ops-sync/bin/ssh-ovh.sh [commande distante]
  bash .ops-sync/bin/ssh-ovh.sh --config PATH [commande distante]
EOT
}

if ! parse_common_args "$@"; then
  usage
  exit 0
fi
set -- "${SYNC_REMAINING_ARGS[@]}"

load_sync_config

if [[ $# -gt 0 ]]; then
  exec ssh -p "$OVH_SSH_PORT" "$OVH_SSH_TARGET" "$*"
fi

exec ssh -p "$OVH_SSH_PORT" "$OVH_SSH_TARGET"
