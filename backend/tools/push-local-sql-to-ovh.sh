#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
LOCAL_BACKEND="${LOCAL_BACKEND:-${REPO_ROOT}/backend}"
REMOTE_HOST="${REMOTE_HOST:-lescaramgl-ssh@ssh.cluster103.hosting.ovh.net}"
REMOTE_BACKEND="${REMOTE_BACKEND:-/home/lescaramgl-ssh/caramagnols/backend}"
SITEMAP_BASE_URL="${SITEMAP_BASE_URL:-https://www.lescaramagnols.com}"
BACKUP_ROOT="${BACKUP_ROOT:-/home/surfacepro8/backups/caramagnols}"
PHP_BIN="${PHP_BIN:-php}"

LIVE=0
DRY_RUN=0
KEEP_REMOTE_BACKUPS=0

usage() {
  cat <<'USAGE'
Usage:
  bash backend/tools/push-local-sql-to-ovh.sh --live [--keep-remote-backups]

Description:
  Copie le contenu editorial SQL local vers la production OVH.
  La commande passe par core/tools/editorial_backup_restore.php, pas par un dump MySQL brut.
  Elle couvre pages, navigation, blog et discussions.

Etapes:
  - cree un backup SQL local
  - cree un backup SQL prod OVH avant ecriture et le rapatrie hors depot
  - copie le payload local vers OVH avec controle de taille
  - restaure le payload en prod avec --storage=sql
  - regenere index de recherche, sitemap et caches
  - cree un backup prod apres ecriture et compare son contenu au local
  - lance les controles prod
  - supprime les temporaires OVH, sauf --keep-remote-backups

Options:
  --live                 Obligatoire pour autoriser l'ecriture prod.
  --dry-run              Affiche la configuration puis sort sans ecriture.
  --keep-remote-backups  Conserve les backups temporaires aussi sur OVH.
  -h, --help             Affiche cette aide.
USAGE
}

while (($#)); do
  case "$1" in
    --live) LIVE=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --keep-remote-backups) KEEP_REMOTE_BACKUPS=1 ;;
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

if [[ "$LIVE" -ne 1 ]]; then
  echo "Refus: ajoute --live pour autoriser l'ecriture sur la production OVH." >&2
  usage
  exit 1
fi

if [[ ! -d "$LOCAL_BACKEND" ]]; then
  echo "Backend local introuvable: $LOCAL_BACKEND" >&2
  exit 1
fi

if [[ ! -f "$LOCAL_BACKEND/core/tools/editorial_backup_restore.php" ]]; then
  echo "Outil backup/restore introuvable dans $LOCAL_BACKEND." >&2
  exit 1
fi

echo "Push SQL editorial local -> OVH"
echo "Local backend: $LOCAL_BACKEND"
echo "Remote: $REMOTE_HOST:$REMOTE_BACKEND"
echo "Backups: $BACKUP_ROOT"
echo "Sitemap base URL: $SITEMAP_BASE_URL"

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "[dry-run] Aucune sauvegarde ni ecriture distante ne sera lancee."
  exit 0
fi

STAMP="$(date +%Y%m%d-%H%M%S)"
LOCAL_DIR="$BACKUP_ROOT/local"
PROD_DIR="$BACKUP_ROOT/prod"
mkdir -p "$LOCAL_DIR" "$PROD_DIR"

LOCAL_JSON="$LOCAL_DIR/editorial-local-sql-before-push-${STAMP}.json"
LOCAL_GZ="${LOCAL_JSON}.gz"
REMOTE_BACKUP_JSON="var/backups/editorial-prod-sql-before-local-push-${STAMP}.json"
REMOTE_BACKUP_GZ="${REMOTE_BACKUP_JSON}.gz"
REMOTE_PAYLOAD="var/backups/editorial-local-sql-payload-${STAMP}.json"
REMOTE_AFTER_JSON="var/backups/editorial-prod-sql-after-local-push-${STAMP}.json"
REMOTE_AFTER_GZ="${REMOTE_AFTER_JSON}.gz"
TMP_JSON="$(mktemp "/tmp/editorial-local-sql-payload-${STAMP}.XXXXXX.json")"

cleanup_local() {
  rm -f "$TMP_JSON"
}
trap cleanup_local EXIT

echo "[1/8] Backup SQL local"
"$PHP_BIN" "$LOCAL_BACKEND/core/tools/editorial_backup_restore.php" backup --storage=sql --output="$LOCAL_JSON"
gzip -f "$LOCAL_JSON"
chmod 600 "$LOCAL_GZ"
stat -c '%a %s %n' "$LOCAL_GZ"

echo "[2/8] Backup SQL prod OVH avant ecriture"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && mkdir -p var/backups && php core/tools/editorial_backup_restore.php backup --storage=sql --output='$REMOTE_BACKUP_JSON' && gzip -f '$REMOTE_BACKUP_JSON' && stat -c '%s %n' '$REMOTE_BACKUP_GZ'"
scp -q "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_BACKUP_GZ" "$PROD_DIR/"
PROD_BEFORE_LOCAL="$PROD_DIR/$(basename "$REMOTE_BACKUP_GZ")"
chmod 600 "$PROD_BEFORE_LOCAL"
stat -c '%a %s %n' "$PROD_BEFORE_LOCAL"

echo "[3/8] Copie du payload local vers OVH"
gzip -dc "$LOCAL_GZ" > "$TMP_JSON"
chmod 600 "$TMP_JSON"
LOCAL_SIZE="$(stat -c '%s' "$TMP_JSON")"
scp -q "$TMP_JSON" "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_PAYLOAD"
REMOTE_SIZE="$(ssh "$REMOTE_HOST" "stat -c '%s' '$REMOTE_BACKEND/$REMOTE_PAYLOAD'")"
echo "Payload local=$LOCAL_SIZE distant=$REMOTE_SIZE"
if [[ "$LOCAL_SIZE" != "$REMOTE_SIZE" ]]; then
  echo "Payload distant incomplet: tailles differentes." >&2
  exit 1
fi

echo "[4/8] Restore SQL prod et regeneration"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/editorial_backup_restore.php restore '$REMOTE_PAYLOAD' --force --storage=sql && php core/tools/generate_search_index.php && php core/tools/generate_sitemap.php --output=public/sitemap.xml --base-url='$SITEMAP_BASE_URL' && php -r 'require \"core/bootstrap.php\"; if (function_exists(\"app_runtime_cache_clear\")) { app_runtime_cache_clear([\"pages\",\"navigation\",\"translations\"]); } echo \"cache_cleared_after_sql_restore\n\";'"

echo "[5/8] Backup prod apres ecriture"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/editorial_backup_restore.php backup --storage=sql --output='$REMOTE_AFTER_JSON' && gzip -f '$REMOTE_AFTER_JSON' && stat -c '%s %n' '$REMOTE_AFTER_GZ'"
scp -q "$REMOTE_HOST:$REMOTE_BACKEND/$REMOTE_AFTER_GZ" "$PROD_DIR/"
PROD_AFTER_LOCAL="$PROD_DIR/$(basename "$REMOTE_AFTER_GZ")"
chmod 600 "$PROD_AFTER_LOCAL"
stat -c '%a %s %n' "$PROD_AFTER_LOCAL"

echo "[6/8] Comparaison local/prod apres restore"
"$PHP_BIN" -r '
function readBackup(string $path): array {
    $raw = (string) file_get_contents($path);
    if (str_ends_with($path, ".gz")) {
        $decoded = gzdecode($raw);
        if (!is_string($decoded)) {
            fwrite(STDERR, "Backup gzip illisible: {$path}\n");
            exit(1);
        }
        $raw = $decoded;
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    return is_array($data) ? $data : [];
}
function normalizeBackup(array $data): array {
    unset($data["meta"]["generatedAt"], $data["meta"]["storageMode"], $data["meta"]["paths"]);
    if (isset($data["pages"]["pages"]) && is_array($data["pages"]["pages"])) {
        usort($data["pages"]["pages"], static fn (array $left, array $right): int => strcmp((string) ($left["slug"] ?? ""), (string) ($right["slug"] ?? "")));
    }
    if (isset($data["blog"]["articles"]) && is_array($data["blog"]["articles"])) {
        usort($data["blog"]["articles"], static fn (array $left, array $right): int => strcmp((string) ($left["lang"] ?? "fr") . ":" . (string) ($left["slug"] ?? ""), (string) ($right["lang"] ?? "fr") . ":" . (string) ($right["slug"] ?? "")));
    }
    return $data;
}
function hashBackup(array $data): string {
    return hash("sha256", json_encode(normalizeBackup($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}
$localHash = hashBackup(readBackup($argv[1]));
$prodHash = hashBackup(readBackup($argv[2]));
echo "local_hash={$localHash}\nprod_after_hash={$prodHash}\n";
if ($localHash !== $prodHash) {
    fwrite(STDERR, "Le backup prod apres restore ne correspond pas au backup local.\n");
    exit(1);
}
echo "content_match\n";
' "$LOCAL_GZ" "$PROD_AFTER_LOCAL"

echo "[7/8] Controles prod"
ssh "$REMOTE_HOST" "cd '$REMOTE_BACKEND' && php core/tools/check_vite_assets.php --public-root=public && php core/tools/check_prod_tree.php --root=. && php core/tools/check_env.php --env=production --strict-prod-security"

if command -v composer >/dev/null 2>&1; then
  composer check-security-headers --working-dir="$LOCAL_BACKEND" -- --url="$SITEMAP_BASE_URL"
fi

echo "[8/8] Nettoyage des temporaires"
if [[ "$KEEP_REMOTE_BACKUPS" -eq 1 ]]; then
  ssh "$REMOTE_HOST" "rm -f '$REMOTE_BACKEND/$REMOTE_PAYLOAD'"
  echo "Backups conserves sur OVH a la demande."
else
  ssh "$REMOTE_HOST" "rm -f '$REMOTE_BACKEND/$REMOTE_PAYLOAD' '$REMOTE_BACKEND/$REMOTE_BACKUP_GZ' '$REMOTE_BACKEND/$REMOTE_AFTER_GZ'"
fi

echo "Push SQL editorial local -> OVH termine."
echo "Backup local source: $LOCAL_GZ"
echo "Backup prod avant: $PROD_BEFORE_LOCAL"
echo "Backup prod apres: $PROD_AFTER_LOCAL"
