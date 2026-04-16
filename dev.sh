#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"
FRONTEND_DIR="${ROOT_DIR}/frontend"

PHP_HOST="${PHP_HOST:-127.0.0.1}"
PHP_PORT="${PHP_PORT:-8000}"
VITE_HOST="${VITE_HOST:-localhost}"
VITE_PORT="${VITE_PORT:-5173}"
DEV_LANG="${DEV_LANG:-fr}"
HTTPS_ENABLED="${HTTPS_ENABLED:-1}"
HTTPS_HOST="${HTTPS_HOST:-127.0.0.1}"
HTTPS_PORT="${HTTPS_PORT:-18443}"
HTTPS_CERT_DIR="${HTTPS_CERT_DIR:-${BACKEND_DIR}/var/dev-ssl}"
HTTPS_CERT_PATH="${HTTPS_CERT_PATH:-${HTTPS_CERT_DIR}/localhost.crt}"
HTTPS_KEY_PATH="${HTTPS_KEY_PATH:-${HTTPS_CERT_DIR}/localhost.key}"
REUSE_EXISTING_SERVICES="${REUSE_EXISTING_SERVICES:-1}"

SITE_URL="http://${PHP_HOST}:${PHP_PORT}/?lang=${DEV_LANG}"
SITE_HTTPS_URL="https://${HTTPS_HOST}:${HTTPS_PORT}/?lang=${DEV_LANG}"
VITE_CLIENT_URL="http://${VITE_HOST}:${VITE_PORT}/@vite/client"

PHP_PID=""
VITE_PID=""
HTTPS_PROXY_PID=""
START_PHP="1"
START_VITE="1"

require_command() {
    local command_name="$1"

    if ! command -v "${command_name}" >/dev/null 2>&1; then
        printf 'Commande requise introuvable: %s\n' "${command_name}" >&2
        exit 1
    fi
}

port_is_busy() {
    local port="$1"

    ss -ltn "( sport = :${port} )" | grep -q 'LISTEN'
}

show_port_usage() {
    local port="$1"

    ss -ltnp "( sport = :${port} )" || true
}

load_nvm() {
    local nvm_dir="${NVM_DIR:-${HOME}/.nvm}"

    if [ -s "${nvm_dir}/nvm.sh" ]; then
        # shellcheck disable=SC1090
        . "${nvm_dir}/nvm.sh"
    fi

    if command -v nvm >/dev/null 2>&1 && [ -f "${ROOT_DIR}/.nvmrc" ]; then
        nvm use >/dev/null
    fi
}

ensure_https_certificate() {
    if [ -f "${HTTPS_CERT_PATH}" ] && [ -f "${HTTPS_KEY_PATH}" ]; then
        return
    fi

    mkdir -p "${HTTPS_CERT_DIR}"

    openssl req \
        -x509 \
        -newkey rsa:2048 \
        -sha256 \
        -nodes \
        -days 825 \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1,IP:::1" \
        -keyout "${HTTPS_KEY_PATH}" \
        -out "${HTTPS_CERT_PATH}" >/dev/null 2>&1
}

wait_for_url() {
    local url="$1"
    local label="$2"
    local allow_insecure="${3:-0}"
    local attempts=40
    local try=1
    local curl_args=(--silent --fail --output /dev/null)

    if [ "${allow_insecure}" = "1" ]; then
        curl_args+=(--insecure)
    fi

    while [ "${try}" -le "${attempts}" ]; do
        if curl "${curl_args[@]}" "${url}"; then
            return 0
        fi

        sleep 0.5
        try=$((try + 1))
    done

    printf 'Le service "%s" ne repond pas sur %s\n' "${label}" "${url}" >&2
    return 1
}

cleanup() {
    local exit_code=$?

    trap - EXIT INT TERM

    if [ -n "${PHP_PID}" ] && kill -0 "${PHP_PID}" >/dev/null 2>&1; then
        kill "${PHP_PID}" >/dev/null 2>&1 || true
        wait "${PHP_PID}" 2>/dev/null || true
    fi

    if [ -n "${VITE_PID}" ] && kill -0 "${VITE_PID}" >/dev/null 2>&1; then
        kill "${VITE_PID}" >/dev/null 2>&1 || true
        wait "${VITE_PID}" 2>/dev/null || true
    fi

    if [ -n "${HTTPS_PROXY_PID}" ] && kill -0 "${HTTPS_PROXY_PID}" >/dev/null 2>&1; then
        kill "${HTTPS_PROXY_PID}" >/dev/null 2>&1 || true
        wait "${HTTPS_PROXY_PID}" 2>/dev/null || true
    fi

    exit "${exit_code}"
}

trap cleanup EXIT INT TERM

require_command php
require_command npm
require_command curl
require_command ss
require_command node

if [ "${HTTPS_ENABLED}" = "1" ]; then
    require_command openssl
fi

if port_is_busy "${PHP_PORT}"; then
    if [ "${REUSE_EXISTING_SERVICES}" = "1" ]; then
        START_PHP="0"
        printf 'Le port PHP %s est deja utilise: reutilisation du serveur existant.\n' "${PHP_PORT}" >&2
    else
        printf 'Le port PHP %s est deja utilise.\n' "${PHP_PORT}" >&2
        show_port_usage "${PHP_PORT}" >&2
        exit 1
    fi
fi

if port_is_busy "${VITE_PORT}"; then
    if [ "${REUSE_EXISTING_SERVICES}" = "1" ]; then
        START_VITE="0"
        printf 'Le port Vite %s est deja utilise: reutilisation du serveur existant.\n' "${VITE_PORT}" >&2
    else
        printf 'Le port Vite %s est deja utilise.\n' "${VITE_PORT}" >&2
        show_port_usage "${VITE_PORT}" >&2
        exit 1
    fi
fi

if [ "${HTTPS_ENABLED}" = "1" ] && port_is_busy "${HTTPS_PORT}"; then
    printf 'Le port HTTPS local %s est deja utilise.\n' "${HTTPS_PORT}" >&2
    show_port_usage "${HTTPS_PORT}" >&2
    exit 1
fi

load_nvm

if [ "${HTTPS_ENABLED}" = "1" ]; then
    ensure_https_certificate
fi

if [ "${START_PHP}" = "1" ]; then
    (
        cd "${BACKEND_DIR}"
        if [ "${HTTPS_ENABLED}" = "1" ]; then
            FORCE_HTTPS=true \
            FORCE_HTTPS_ON_LOCALHOST=true \
            FORCE_HTTPS_PORT="${HTTPS_PORT}" \
            php -S "${PHP_HOST}:${PHP_PORT}" -t public public/dev-router.php
        else
            php -S "${PHP_HOST}:${PHP_PORT}" -t public public/dev-router.php
        fi
    ) > >(sed 's/^/[php] /') 2> >(sed 's/^/[php] /' >&2) &
    PHP_PID=$!
fi

if [ "${START_VITE}" = "1" ]; then
    (
        cd "${FRONTEND_DIR}"
        npm run dev -- --host "${VITE_HOST}" --port "${VITE_PORT}"
    ) > >(sed 's/^/[vite] /') 2> >(sed 's/^/[vite] /' >&2) &
    VITE_PID=$!
fi

if [ "${HTTPS_ENABLED}" = "1" ]; then
    (
        cd "${ROOT_DIR}"
        HTTPS_PROXY_HOST="${HTTPS_HOST}" \
        HTTPS_PROXY_PORT="${HTTPS_PORT}" \
        HTTPS_TARGET_HOST="${PHP_HOST}" \
        HTTPS_TARGET_PORT="${PHP_PORT}" \
        HTTPS_CERT_PATH="${HTTPS_CERT_PATH}" \
        HTTPS_KEY_PATH="${HTTPS_KEY_PATH}" \
        node "${FRONTEND_DIR}/tools/dev-https-proxy.mjs"
    ) > >(sed 's/^/[https] /') 2> >(sed 's/^/[https] /' >&2) &
    HTTPS_PROXY_PID=$!
fi

wait_for_url "http://${PHP_HOST}:${PHP_PORT}/" "site PHP"
wait_for_url "${VITE_CLIENT_URL}" "client Vite"
if [ "${HTTPS_ENABLED}" = "1" ]; then
    wait_for_url "https://${HTTPS_HOST}:${HTTPS_PORT}/" "proxy HTTPS local" 1
fi

printf '\nSite disponible : %s\n' "${SITE_URL}"
if [ "${HTTPS_ENABLED}" = "1" ]; then
    printf 'Site HTTPS local : %s\n' "${SITE_HTTPS_URL}"
fi
printf 'Assets dev Vite : %s\n' "${VITE_CLIENT_URL}"
if [ "${START_PHP}" = "0" ] || [ "${START_VITE}" = "0" ]; then
    printf 'Mode reutilisation: PHP=%s, Vite=%s\n' "$([ "${START_PHP}" = "1" ] && echo "lance" || echo "existant")" "$([ "${START_VITE}" = "1" ] && echo "lance" || echo "existant")"
fi
printf 'Ctrl+C pour arreter les deux serveurs.\n\n'

WAIT_PIDS=()

if [ -n "${PHP_PID}" ]; then
    WAIT_PIDS+=("${PHP_PID}")
fi

if [ -n "${VITE_PID}" ]; then
    WAIT_PIDS+=("${VITE_PID}")
fi

if [ "${HTTPS_ENABLED}" = "1" ] && [ -n "${HTTPS_PROXY_PID}" ]; then
    WAIT_PIDS+=("${HTTPS_PROXY_PID}")
fi

if [ "${#WAIT_PIDS[@]}" -gt 0 ]; then
    wait -n "${WAIT_PIDS[@]}"
else
    printf 'Aucun processus dev demarre (mode reutilisation sans proxy HTTPS actif).\n' >&2
    exit 1
fi
