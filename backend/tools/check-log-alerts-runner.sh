#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_BACKEND_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

BACKEND_DIR="${BACKEND_DIR:-${DEFAULT_BACKEND_DIR}}"
PHP_BIN="${PHP_BIN:-php}"
CHECK_LOG_ALERTS_SINCE_MINUTES="${CHECK_LOG_ALERTS_SINCE_MINUTES:-60}"
CHECK_LOG_ALERTS_STRICT="${CHECK_LOG_ALERTS_STRICT:-true}"
CHECK_LOG_ALERTS_EXTRA_ARGS="${CHECK_LOG_ALERTS_EXTRA_ARGS:-}"
CHECK_LOG_ALERTS_RUN_LOG="${CHECK_LOG_ALERTS_RUN_LOG:-${BACKEND_DIR}/var/log/check-log-alerts-runner.log}"

if [[ ! -d "$BACKEND_DIR" || ! -f "$BACKEND_DIR/core/tools/check_log_alerts.php" ]]; then
  echo "[check-log-alerts-runner] Backend directory invalid: $BACKEND_DIR" >&2
  exit 1
fi

mkdir -p "$(dirname "$CHECK_LOG_ALERTS_RUN_LOG")"

CMD=("$PHP_BIN" "core/tools/check_log_alerts.php" "--since-minutes=${CHECK_LOG_ALERTS_SINCE_MINUTES}")

case "${CHECK_LOG_ALERTS_STRICT,,}" in
  1|true|yes|on)
    CMD+=("--strict")
    ;;
esac

if [[ -n "$CHECK_LOG_ALERTS_EXTRA_ARGS" ]]; then
  read -r -a EXTRA <<< "$CHECK_LOG_ALERTS_EXTRA_ARGS"
  CMD+=("${EXTRA[@]}")
fi

{
  echo "[$(date -Is)] check-log-alerts start"
  echo "[$(date -Is)] command: ${CMD[*]}"
} >> "$CHECK_LOG_ALERTS_RUN_LOG"

set +e
(
  cd "$BACKEND_DIR"
  "${CMD[@]}"
) 2>&1 | tee -a "$CHECK_LOG_ALERTS_RUN_LOG"
STATUS=${PIPESTATUS[0]}
set -e

echo "[$(date -Is)] check-log-alerts exit=${STATUS}" >> "$CHECK_LOG_ALERTS_RUN_LOG"

exit "$STATUS"

