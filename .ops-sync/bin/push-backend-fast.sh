#!/usr/bin/env bash

set -euo pipefail

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"

usage() {
  cat <<'EOT'
Usage:
  bash .ops-sync/bin/push-backend-fast.sh [options de backend/tools/deploy-fast.sh]
EOT
}

if ! parse_common_args "$@"; then
  usage
  exit 0
fi
set -- "${SYNC_REMAINING_ARGS[@]}"

load_sync_config

cmd=(
  env
  "REMOTE_HOST=$OVH_SSH_TARGET"
  "REMOTE_BACKEND=$OVH_REMOTE_BACKEND"
  "LOCAL_BACKEND=$LOCAL_BACKEND_DIR"
)

if [[ -n "${OVH_SITEMAP_BASE_URL:-}" ]]; then
  cmd+=("SITEMAP_BASE_URL=$OVH_SITEMAP_BASE_URL")
fi

cmd+=(
  bash
  "$OPS_SYNC_REPO_ROOT/backend/tools/deploy-fast.sh"
  "$@"
)

exec "${cmd[@]}"
