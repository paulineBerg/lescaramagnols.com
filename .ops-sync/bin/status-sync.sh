#!/usr/bin/env bash

set -euo pipefail

PROJECT="ops-sync"
ACTION="status"

BIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/common.sh
source "$BIN_DIR/../lib/common.sh"
# shellcheck source=../lib/db.sh
source "$BIN_DIR/../lib/db.sh"

usage() {
  cat <<'EOT'
Usage:
  bash .ops-sync/bin/status-sync.sh [--config PATH]
EOT
}

if ! parse_common_args "$@"; then
  usage
  exit 0
fi
set -- "${SYNC_REMAINING_ARGS[@]}"

setup_logging
load_sync_config
load_backend_runtime_env
require_sync_prerequisites
require_db_sync_prerequisites

info "Configuration chargee: $SYNC_CONFIG_FILE"
info "OVH target: $OVH_SSH_TARGET"
info "OVH port: $OVH_SSH_PORT"
info "OVH app root: $OVH_REMOTE_APP_ROOT"
info "OVH backend: $OVH_REMOTE_BACKEND"
info "Backend local: $LOCAL_BACKEND_DIR"
info "Backend env: $LOCAL_BACKEND_ENV_FILE"
info "DB OVH: $OVH_DB_HOST:$OVH_DB_PORT/$OVH_DB_NAME ($OVH_DB_USER)"

info "Test acces SSH"
ssh_run "printf 'ssh-ok\n'"

info "Test racine applicative distante"
ssh_run "test -d '$OVH_REMOTE_APP_ROOT' && printf 'remote-app-ok\n'"

info "Test backend distant"
ssh_run "test -d '$OVH_REMOTE_BACKEND' && printf 'remote-backend-ok\n'"

info "Test PHP distant"
ssh_run "command -v php >/dev/null 2>&1 && printf 'php-ok\n'"

info "Test .env distant"
ssh_run "test -f '$OVH_REMOTE_BACKEND/.env' && printf 'remote-env-ok\n' || printf 'remote-env-missing\n'"

info "Test acces SQL OVH"
test_ovh_db_access
