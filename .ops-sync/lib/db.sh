#!/usr/bin/env bash

set -euo pipefail

require_db_sync_prerequisites() {
  require_cmd mysql
  require_cmd mysqldump
  require_cmd gzip
}

format_bytes_human() {
  local bytes="${1:-0}"

  if command -v numfmt >/dev/null 2>&1; then
    numfmt --to=iec --suffix=B "$bytes"
  else
    printf '%s bytes\n' "$bytes"
  fi
}

load_local_db_sync_settings() {
  LOCAL_DB_HOST="${LOCAL_DB_HOST:-127.0.0.1}"
  LOCAL_DB_PORT="${LOCAL_DB_PORT:-3306}"
  LOCAL_DB_NAME="${LOCAL_DB_NAME:-caramagnols}"
  LOCAL_DB_USER="${LOCAL_DB_USER:-root}"
  LOCAL_DB_PASSWORD="${LOCAL_DB_PASSWORD:-}"
  LOCAL_DB_DEFAULTS_FILE="${LOCAL_DB_DEFAULTS_FILE:-}"
  LOCAL_DB_LOGIN_PATH="${LOCAL_DB_LOGIN_PATH:-}"
}

local_db_has_noninteractive_auth() {
  [[ -n "$LOCAL_DB_PASSWORD" || -n "$LOCAL_DB_DEFAULTS_FILE" || -n "$LOCAL_DB_LOGIN_PATH" ]]
}

run_ovh_mysql() {
  MYSQL_PWD="$OVH_DB_PASSWORD" mysql \
    -h "$OVH_DB_HOST" \
    -P "$OVH_DB_PORT" \
    -u "$OVH_DB_USER" \
    "$OVH_DB_NAME" \
    "$@"
}

test_ovh_db_access() {
  run_ovh_mysql -N -B -e "SELECT DATABASE(), CURRENT_USER(), @@hostname;"
}

local_mysql_server() {
  local -a cmd=(mysql)

  if [[ -n "$LOCAL_DB_DEFAULTS_FILE" ]]; then
    cmd+=("--defaults-extra-file=$LOCAL_DB_DEFAULTS_FILE")
  fi

  if [[ -n "$LOCAL_DB_LOGIN_PATH" ]]; then
    cmd+=("--login-path=$LOCAL_DB_LOGIN_PATH")
  fi

  cmd+=(
    -h "$LOCAL_DB_HOST"
    -P "$LOCAL_DB_PORT"
    -u "$LOCAL_DB_USER"
    "$@"
  )

  if [[ -n "$LOCAL_DB_PASSWORD" ]]; then
    MYSQL_PWD="$LOCAL_DB_PASSWORD" "${cmd[@]}"
  else
    "${cmd[@]}"
  fi
}

local_mysql() {
  local_mysql_server "$LOCAL_DB_NAME" "$@"
}

test_local_db_access() {
  local_mysql -N -B -e "SELECT DATABASE(), CURRENT_USER(), @@hostname;"
}

local_db_exists() {
  local result
  result="$(local_mysql_server -N -B -e "SHOW DATABASES LIKE '${LOCAL_DB_NAME}';" || true)"
  [[ "$result" == "$LOCAL_DB_NAME" ]]
}

ensure_local_db_exists() {
  local_mysql_server -e "CREATE DATABASE IF NOT EXISTS \`${LOCAL_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

dump_ovh_db() {
  local dump_file="$1"

  ensure_ops_dirs

  info "Dump OVH vers $dump_file"
  MYSQL_PWD="$OVH_DB_PASSWORD" mysqldump \
    --single-transaction \
    --default-character-set=utf8mb4 \
    --skip-lock-tables \
    --no-tablespaces \
    --routines \
    --triggers \
    -h "$OVH_DB_HOST" \
    -P "$OVH_DB_PORT" \
    -u "$OVH_DB_USER" \
    "$OVH_DB_NAME" | gzip -c > "$dump_file"

  [[ -s "$dump_file" ]] || die "Dump OVH vide ou introuvable: $dump_file"
}

backup_local_db() {
  local backup_file="$1"
  local -a cmd=(mysqldump)

  if ! local_db_exists; then
    warn "Base locale absente, aucune sauvegarde pre-import: $LOCAL_DB_NAME"
    return 0
  fi

  info "Sauvegarde locale avant import: $backup_file"

  if [[ -n "$LOCAL_DB_DEFAULTS_FILE" ]]; then
    cmd+=("--defaults-extra-file=$LOCAL_DB_DEFAULTS_FILE")
  fi

  if [[ -n "$LOCAL_DB_LOGIN_PATH" ]]; then
    cmd+=("--login-path=$LOCAL_DB_LOGIN_PATH")
  fi

  cmd+=(
    --single-transaction
    --default-character-set=utf8mb4
    --skip-lock-tables
    --no-tablespaces
    --routines
    --triggers
    -h "$LOCAL_DB_HOST"
    -P "$LOCAL_DB_PORT"
    -u "$LOCAL_DB_USER"
    "$LOCAL_DB_NAME"
  )

  if [[ -n "$LOCAL_DB_PASSWORD" ]]; then
    MYSQL_PWD="$LOCAL_DB_PASSWORD" "${cmd[@]}" | gzip -c > "$backup_file"
  else
    "${cmd[@]}" | gzip -c > "$backup_file"
  fi
}

import_dump_to_local_db() {
  local dump_file="$1"
  local dump_size

  dump_size="$(stat -c%s "$dump_file")"
  info "Import du dump OVH dans la base locale $LOCAL_DB_NAME"
  info "Taille du dump compresse a importer: $(format_bytes_human "$dump_size")"

  if command -v pv >/dev/null 2>&1; then
    pv -f -p -t -e -r -b -s "$dump_size" "$dump_file" \
      | gzip -cd \
      | local_mysql
  else
    gzip -cd "$dump_file" | local_mysql
  fi
}
