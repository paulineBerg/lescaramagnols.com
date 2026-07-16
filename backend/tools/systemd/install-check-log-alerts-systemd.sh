#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR_DEFAULT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

BACKEND_DIR="${BACKEND_DIR:-${BACKEND_DIR_DEFAULT}}"
SYSTEMD_DIR="${SYSTEMD_DIR:-/etc/systemd/system}"
ENV_DIR="${ENV_DIR:-/etc/caramagnols}"
ENV_FILE="${ENV_FILE:-${ENV_DIR}/check-log-alerts.env}"
SERVICE_NAME="${SERVICE_NAME:-caramagnols-check-log-alerts.service}"
TIMER_NAME="${TIMER_NAME:-caramagnols-check-log-alerts.timer}"
ON_CALENDAR="${ON_CALENDAR:-*:0/5}"
DRY_RUN=0

usage() {
  cat <<'USAGE'
Usage:
  sudo bash backend/tools/systemd/install-check-log-alerts-systemd.sh [options]

Options:
  --backend-dir=PATH    Backend cible (defaut: auto-detecte depuis le repo).
  --systemd-dir=PATH    Dossier des units systemd (defaut: /etc/systemd/system).
  --env-file=PATH       Fichier d'environnement runtime (defaut: /etc/caramagnols/check-log-alerts.env).
  --on-calendar=EXPR    Frequence timer systemd (defaut: *:0/5, soit toutes les 5 min).
  --service-name=NAME   Nom de l'unite service (defaut: caramagnols-check-log-alerts.service).
  --timer-name=NAME     Nom de l'unite timer (defaut: caramagnols-check-log-alerts.timer).
  --dry-run             Previsualise les fichiers generes sans installation.
  -h, --help            Affiche cette aide.
USAGE
}

while (($#)); do
  case "$1" in
    --backend-dir=*) BACKEND_DIR="${1#*=}" ;;
    --systemd-dir=*) SYSTEMD_DIR="${1#*=}" ;;
    --env-file=*)
      ENV_FILE="${1#*=}"
      ENV_DIR="$(dirname "$ENV_FILE")"
      ;;
    --on-calendar=*) ON_CALENDAR="${1#*=}" ;;
    --service-name=*) SERVICE_NAME="${1#*=}" ;;
    --timer-name=*) TIMER_NAME="${1#*=}" ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Option inconnue: $1" >&2
      usage
      exit 1
      ;;
  esac
  shift
done

if [[ ! -d "$BACKEND_DIR" || ! -f "$BACKEND_DIR/core/tools/check_log_alerts.php" ]]; then
  echo "Backend invalide: $BACKEND_DIR" >&2
  exit 1
fi

if [[ ! -f "$BACKEND_DIR/tools/check-log-alerts-runner.sh" ]]; then
  echo "Runner introuvable: $BACKEND_DIR/tools/check-log-alerts-runner.sh" >&2
  exit 1
fi

SERVICE_TEMPLATE="$BACKEND_DIR/tools/systemd/caramagnols-check-log-alerts.service.template"
TIMER_TEMPLATE="$BACKEND_DIR/tools/systemd/caramagnols-check-log-alerts.timer.template"
ENV_TEMPLATE="$BACKEND_DIR/tools/systemd/check-log-alerts.env.example"

if [[ ! -f "$SERVICE_TEMPLATE" || ! -f "$TIMER_TEMPLATE" || ! -f "$ENV_TEMPLATE" ]]; then
  echo "Templates systemd incomplets dans $BACKEND_DIR/tools/systemd" >&2
  exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

GENERATED_SERVICE="$TMP_DIR/$SERVICE_NAME"
GENERATED_TIMER="$TMP_DIR/$TIMER_NAME"

sed \
  -e "s|__BACKEND_DIR__|$BACKEND_DIR|g" \
  -e "s|__ENV_FILE__|$ENV_FILE|g" \
  "$SERVICE_TEMPLATE" > "$GENERATED_SERVICE"

sed \
  -e "s|__ON_CALENDAR__|$ON_CALENDAR|g" \
  -e "s|__SERVICE_NAME__|$SERVICE_NAME|g" \
  "$TIMER_TEMPLATE" > "$GENERATED_TIMER"

echo "Install check-log-alerts systemd"
echo "- backend: $BACKEND_DIR"
echo "- systemd dir: $SYSTEMD_DIR"
echo "- env file: $ENV_FILE"
echo "- service: $SERVICE_NAME"
echo "- timer: $TIMER_NAME"
echo "- on-calendar: $ON_CALENDAR"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo
  echo "[dry-run] Service genere:"
  cat "$GENERATED_SERVICE"
  echo
  echo "[dry-run] Timer genere:"
  cat "$GENERATED_TIMER"
  echo
  echo "[dry-run] Aucune ecriture systeme executee."
  exit 0
fi

# Le runner est versionne dans le repo: on garantit juste son bit executable.
chmod 0755 "$BACKEND_DIR/tools/check-log-alerts-runner.sh"
install -D -m 0644 "$GENERATED_SERVICE" "$SYSTEMD_DIR/$SERVICE_NAME"
install -D -m 0644 "$GENERATED_TIMER" "$SYSTEMD_DIR/$TIMER_NAME"

if [[ ! -f "$ENV_FILE" ]]; then
  install -D -m 0640 "$ENV_TEMPLATE" "$ENV_FILE"
  echo "Fichier env cree: $ENV_FILE"
else
  echo "Fichier env deja present (non ecrase): $ENV_FILE"
fi

systemctl daemon-reload
systemctl enable --now "$TIMER_NAME"

echo
echo "Timer actif:"
systemctl status --no-pager "$TIMER_NAME" | sed -n '1,14p'
echo
echo "Prochaine execution:"
systemctl list-timers --all "$TIMER_NAME" --no-pager
echo
echo "Pense a verifier et ajuster le fichier: $ENV_FILE"
