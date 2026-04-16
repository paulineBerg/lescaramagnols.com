<?php

declare(strict_types=1);

use Caramagnols\Database\EditorialSchemaManager;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$projectRoot = dirname(__DIR__, 2);
$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    fwrite(STDERR, "Autoload introuvable. Lancez d'abord 'composer install --working-dir=backend'.\n");
    exit(1);
}

require_once $autoloadPath;

if (!function_exists('load_env')) {
    require_once dirname(__DIR__) . '/env.php';
}

load_env($projectRoot . '/.env');

$options = parse_cli_options(array_slice($argv, 1));

if (isset($options['help']) || isset($options['h'])) {
    print_usage();
    exit(0);
}

$dryRun = isset($options['dry-run']);
$forceOverrides = isset($options['force-overrides']);
$skipLegacySchema = isset($options['skip-legacy-schema']);
$skipEditorialSchema = isset($options['skip-editorial-schema']);
$skipLegacyAdminUser = isset($options['skip-legacy-admin-user']);
$skipOverrides = isset($options['skip-overrides']);

$databaseOverridePath = $projectRoot . '/config/database.override.php';
$adminOverridePath = $projectRoot . '/config/admin.override.php';
$legacySqlPath = $projectRoot . '/sql/install.sql';
$editorialSchemaDirectory = $projectRoot . '/sql/editorial';

$existingDatabaseOverride = load_override_file($databaseOverridePath);
$existingAdminOverride = load_override_file($adminOverridePath);

$dbPrefix = trim((string) (
    $options['db-prefix']
    ?? ($existingDatabaseOverride['prefix'] ?? '')
    ?? 'car_'
));

if ($dbPrefix === '') {
    $dbPrefix = 'car_';
}

if (preg_match('/^[a-z][a-z0-9_]*$/', $dbPrefix) !== 1) {
    fwrite(STDERR, "Prefixe SQL invalide (db-prefix). Utilisez uniquement [a-z0-9_] et commencez par une lettre.\n");
    exit(1);
}

$dbHost = trim((string) (
    $options['db-host']
    ?? ($existingDatabaseOverride['host'] ?? '')
    ?? env('DB_HOST', '127.0.0.1')
));
$dbPort = (int) ($options['db-port'] ?? ($existingDatabaseOverride['port'] ?? env('DB_PORT', 3306)));
$dbName = trim((string) (
    $options['db-name']
    ?? ($existingDatabaseOverride['name'] ?? '')
    ?? env('DB_NAME', '')
));
$dbUser = trim((string) (
    $options['db-user']
    ?? ($existingDatabaseOverride['user'] ?? '')
    ?? env('DB_USER', '')
));
$dbPassword = (string) (
    $options['db-password']
    ?? ($existingDatabaseOverride['password'] ?? '')
    ?? env('DB_PASSWORD', '')
);
$dbCharset = trim((string) (
    $options['db-charset']
    ?? ($existingDatabaseOverride['charset'] ?? '')
    ?? env('DB_CHARSET', 'utf8mb4')
));

if ($dbHost === '' || $dbPort <= 0 || $dbName === '' || $dbUser === '') {
    fwrite(
        STDERR,
        "Configuration DB incomplete. Renseignez au minimum --db-host --db-port --db-name --db-user (ou .env / override).\n"
    );
    exit(1);
}

if (preg_match('/^[A-Za-z0-9_]+$/', $dbCharset) !== 1) {
    fwrite(STDERR, "Charset DB invalide.\n");
    exit(1);
}

$adminIdentifier = trim((string) (
    $options['admin-identifier']
    ?? $options['admin-email']
    ?? ($existingAdminOverride['identifier'] ?? '')
    ?? ($existingAdminOverride['email'] ?? '')
    ?? env('ADMIN_IDENTIFIER', env('ADMIN_EMAIL', ''))
));

if ($adminIdentifier === '') {
    fwrite(STDERR, "Identifiant admin manquant. Utilisez --admin-email ou --admin-identifier.\n");
    exit(1);
}

$rawAdminPassword = (string) ($options['admin-password'] ?? '');
$rawAdminPasswordHash = trim((string) ($options['admin-password-hash'] ?? ''));
$fallbackAdminHash = trim((string) ($existingAdminOverride['password_hash'] ?? env('ADMIN_PASSWORD_HASH', '')));

$adminPasswordHash = '';

if ($rawAdminPassword !== '') {
    if (strlen($rawAdminPassword) < 12) {
        fwrite(STDERR, "Le mot de passe admin doit contenir au moins 12 caracteres.\n");
        exit(1);
    }

    $generatedHash = password_hash($rawAdminPassword, PASSWORD_DEFAULT);
    if (!is_string($generatedHash) || $generatedHash === '') {
        fwrite(STDERR, "Impossible de generer un hash admin.\n");
        exit(1);
    }

    $adminPasswordHash = $generatedHash;
} elseif ($rawAdminPasswordHash !== '') {
    $info = password_get_info($rawAdminPasswordHash);
    if (($info['algo'] ?? null) === null || (int) $info['algo'] === 0) {
        fwrite(STDERR, "--admin-password-hash n'est pas un hash valide.\n");
        exit(1);
    }

    $adminPasswordHash = $rawAdminPasswordHash;
} elseif ($fallbackAdminHash !== '') {
    $info = password_get_info($fallbackAdminHash);
    if (($info['algo'] ?? null) !== null && (int) $info['algo'] !== 0) {
        $adminPasswordHash = $fallbackAdminHash;
    }
}

if ($adminPasswordHash === '') {
    fwrite(
        STDERR,
        "Hash admin introuvable. Utilisez --admin-password (recommande) ou --admin-password-hash.\n"
    );
    exit(1);
}

$adminAllowedIps = parse_allowed_ips((string) ($options['admin-allowed-ips'] ?? ''));
$adminTotpEnabled = parse_nullable_boolean_option($options['admin-totp-enabled'] ?? null);
$adminTotpSecret = normalize_totp_secret((string) ($options['admin-totp-secret'] ?? ''));

if ($adminTotpEnabled === true && $adminTotpSecret === '') {
    fwrite(STDERR, "TOTP activee mais secret absent. Renseignez --admin-totp-secret.\n");
    exit(1);
}

if ($adminTotpSecret !== '' && preg_match('/^[A-Z2-7]+$/', $adminTotpSecret) !== 1) {
    fwrite(STDERR, "Secret TOTP invalide (Base32 attendu).\n");
    exit(1);
}

$databaseOverrideData = $existingDatabaseOverride;
$databaseOverrideData['host'] = $dbHost;
$databaseOverrideData['port'] = $dbPort;
$databaseOverrideData['name'] = $dbName;
$databaseOverrideData['user'] = $dbUser;
$databaseOverrideData['password'] = $dbPassword;
$databaseOverrideData['charset'] = $dbCharset;
$databaseOverrideData['prefix'] = $dbPrefix;

$adminOverrideData = $existingAdminOverride;
$adminOverrideData['identifier'] = $adminIdentifier;
$adminOverrideData['password_hash'] = $adminPasswordHash;
if ($adminAllowedIps !== []) {
    $adminOverrideData['allowed_ips'] = $adminAllowedIps;
}
if ($adminTotpEnabled !== null) {
    $adminOverrideData['totp_enabled'] = $adminTotpEnabled;
}
if ($adminTotpSecret !== '') {
    $adminOverrideData['totp_secret'] = $adminTotpSecret;
}

fwrite(STDOUT, "Initialisation DB + admin\n");
fwrite(STDOUT, sprintf("- DB host: %s\n", $dbHost));
fwrite(STDOUT, sprintf("- DB port: %d\n", $dbPort));
fwrite(STDOUT, sprintf("- DB name: %s\n", $dbName));
fwrite(STDOUT, sprintf("- DB user: %s\n", $dbUser));
fwrite(STDOUT, sprintf("- DB password: %s\n", mask_secret($dbPassword)));
fwrite(STDOUT, sprintf("- DB prefix: %s\n", $dbPrefix));
fwrite(STDOUT, sprintf("- Admin identifier: %s\n", $adminIdentifier));
fwrite(STDOUT, sprintf("- Legacy schema: %s\n", $skipLegacySchema ? 'skip' : 'apply'));
fwrite(STDOUT, sprintf("- Editorial schema: %s\n", $skipEditorialSchema ? 'skip' : 'apply'));
fwrite(STDOUT, sprintf("- Legacy admin user seed: %s\n", $skipLegacyAdminUser ? 'skip' : 'apply'));
fwrite(STDOUT, sprintf("- Overrides config: %s\n", $skipOverrides ? 'skip' : 'apply'));
fwrite(STDOUT, sprintf("- Mode dry-run: %s\n", $dryRun ? 'yes' : 'no'));

if ($dryRun) {
    fwrite(STDOUT, "Dry-run termine. Aucune ecriture effectuee.\n");
    exit(0);
}

try {
    $serverConnection = connect_server($dbHost, $dbPort, $dbUser, $dbPassword, $dbCharset);
    create_database_if_missing($serverConnection, $dbName, $dbCharset);

    $databaseConnection = connect_database($dbHost, $dbPort, $dbName, $dbUser, $dbPassword, $dbCharset);

    if (!$skipLegacySchema) {
        if (!is_file($legacySqlPath)) {
            throw new RuntimeException(sprintf('Schema legacy introuvable: %s', $legacySqlPath));
        }

        $legacyStatements = load_legacy_sql_statements($legacySqlPath, $dbPrefix);
        $executedLegacyStatements = execute_sql_statements($databaseConnection, $legacyStatements);
        fwrite(STDOUT, sprintf("- Legacy schema: %d requete(s) executee(s)\n", $executedLegacyStatements));
    }

    if (!$skipEditorialSchema) {
        $schemaManager = new EditorialSchemaManager($dbPrefix, editorial_migration_files($editorialSchemaDirectory));
        $schemaManager->ensureSchema($databaseConnection);
        fwrite(STDOUT, "- Editorial schema: migrations appliquees.\n");
    }

    if (!$skipLegacyAdminUser) {
        if (legacy_users_table_exists($databaseConnection, $dbPrefix)) {
            seed_legacy_admin_user($databaseConnection, $dbPrefix, $adminIdentifier, $adminPasswordHash);
            fwrite(STDOUT, "- Legacy admin user: upsert OK.\n");
        } else {
            fwrite(STDOUT, "- Legacy admin user: skip (table absente).\n");
        }
    }

    if (!$skipOverrides) {
        persist_override_file($databaseOverridePath, $databaseOverrideData, $forceOverrides);
        persist_override_file($adminOverridePath, $adminOverrideData, $forceOverrides);
    }

    fwrite(STDOUT, "Initialisation terminee avec succes.\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Initialisation echouee: " . $exception->getMessage() . "\n");
    exit(1);
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}

function print_usage(): void
{
    $usage = <<<'TXT'
Usage:
  composer init-db-admin --working-dir=backend -- \
    --db-host=127.0.0.1 --db-port=3306 --db-name=caramagnols --db-user=root --db-password=secret \
    --admin-email=admin@example.com --admin-password='motdepassefort'

Options:
  --db-host=...                 Hote MySQL/MariaDB
  --db-port=...                 Port MySQL/MariaDB
  --db-name=...                 Nom de la base
  --db-user=...                 Utilisateur SQL
  --db-password=...             Mot de passe SQL
  --db-charset=...              Charset SQL (defaut: utf8mb4)
  --db-prefix=...               Prefixe tables (defaut: car_)
  --admin-email=...             Email admin (alias de --admin-identifier)
  --admin-identifier=...        Identifiant admin canonique
  --admin-password=...          Mot de passe admin en clair (hash genere)
  --admin-password-hash=...     Hash admin deja calcule
  --admin-allowed-ips=a,b,c     Allowlist IP admin (optionnel)
  --admin-totp-enabled=true|false
  --admin-totp-secret=...       Secret Base32 TOTP
  --skip-legacy-schema          N'applique pas backend/sql/install.sql
  --skip-editorial-schema       N'applique pas backend/sql/editorial/*.sql
  --skip-legacy-admin-user      N'ecrit pas l'admin dans table {prefix}users
  --skip-overrides              N'ecrit pas config/database.override.php ni config/admin.override.php
  --force-overrides             Autorise l'ecrasement des overrides existants
  --dry-run                     Simulation sans ecriture
  --help                        Afficher cette aide

TXT;

    fwrite(STDOUT, $usage);
}

/**
 * @return array<string, mixed>
 */
function load_override_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $data = include $path;

    return is_array($data) ? $data : [];
}

function mask_secret(string $value): string
{
    if ($value === '') {
        return '(vide)';
    }

    if (strlen($value) <= 4) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 2) . str_repeat('*', strlen($value) - 4) . substr($value, -2);
}

/**
 * @return array<int, string>
 */
function parse_allowed_ips(string $rawValue): array
{
    $parts = preg_split('/[\s,;]+/', trim($rawValue), -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return [];
    }

    $allowed = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        if (str_contains($part, '/')) {
            [$subnet, $mask] = array_pad(explode('/', $part, 2), 2, '');
            $subnet = trim($subnet);
            $mask = trim($mask);

            if (filter_var($subnet, FILTER_VALIDATE_IP) === false || !ctype_digit($mask)) {
                continue;
            }

            $packed = @inet_pton($subnet);
            if ($packed === false) {
                continue;
            }

            $maxBits = strlen($packed) * 8;
            $maskInt = (int) $mask;
            if ($maskInt < 0 || $maskInt > $maxBits) {
                continue;
            }

            $normalized = $subnet . '/' . $maskInt;
        } else {
            if (filter_var($part, FILTER_VALIDATE_IP) === false && $part !== 'localhost') {
                continue;
            }

            $normalized = $part;
        }

        if (!in_array($normalized, $allowed, true)) {
            $allowed[] = $normalized;
        }
    }

    return $allowed;
}

function parse_nullable_boolean_option(string|bool|int|null $value): ?bool
{
    if ($value === null || $value === '') {
        return null;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value !== 0;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return null;
    }

    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return null;
}

function normalize_totp_secret(string $secret): string
{
    $secret = strtoupper(trim($secret));
    if ($secret === '') {
        return '';
    }

    return preg_replace('/[\s\-=]+/', '', $secret) ?? '';
}

function connect_server(string $host, int $port, string $user, string $password, string $charset): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);

    try {
        return new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        throw new RuntimeException('Connexion SQL serveur impossible: ' . $exception->getMessage(), previous: $exception);
    }
}

function connect_database(string $host, int $port, string $name, string $user, string $password, string $charset): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

    try {
        return new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        throw new RuntimeException('Connexion SQL base impossible: ' . $exception->getMessage(), previous: $exception);
    }
}

function create_database_if_missing(PDO $connection, string $databaseName, string $charset): void
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
        throw new RuntimeException('Nom de base invalide.');
    }

    if (preg_match('/^[A-Za-z0-9_]+$/', $charset) !== 1) {
        throw new RuntimeException('Charset de base invalide.');
    }

    $escapedDatabase = str_replace('`', '``', $databaseName);
    $sql = sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s',
        $escapedDatabase,
        $charset
    );

    $connection->exec($sql);
}

/**
 * @return array<int, string>
 */
function load_legacy_sql_statements(string $path, string $prefix): array
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException(sprintf('Impossible de lire %s', $path));
    }

    if ($prefix !== 'car_') {
        foreach (['users', 'articles', 'comments'] as $suffix) {
            $sql = (string) preg_replace(
                '/\bcar_' . preg_quote($suffix, '/') . '\b/',
                $prefix . $suffix,
                $sql
            );
        }
    }

    return split_sql_statements($sql);
}

/**
 * @param array<int, string> $statements
 */
function execute_sql_statements(PDO $connection, array $statements): int
{
    $executed = 0;
    foreach ($statements as $statement) {
        $connection->exec($statement);
        $executed++;
    }

    return $executed;
}

/**
 * @return array<int, string>
 */
function split_sql_statements(string $sql): array
{
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

    return array_values(
        array_filter(
            array_map(static fn (string $statement): string => trim($statement), $parts),
            static fn (string $statement): bool => $statement !== ''
        )
    );
}

/**
 * @return array<int, string>
 */
function editorial_migration_files(string $directory): array
{
    $files = [];

    foreach (glob(rtrim($directory, '/') . '/*.sql') ?: [] as $filePath) {
        $basename = basename($filePath);
        if (preg_match('/^(\d+)_.*\.sql$/', $basename, $matches) !== 1) {
            continue;
        }

        $files[(int) $matches[1]] = $filePath;
    }

    ksort($files);

    return $files;
}

function seed_legacy_admin_user(PDO $connection, string $prefix, string $identifier, string $passwordHash): void
{
    $table = sprintf('`%s`', str_replace('`', '``', $prefix . 'users'));

    try {
        $statement = $connection->prepare(
            sprintf(
                'INSERT INTO %s (`email`, `password`, `role`) VALUES (:email, :password, :role)
                 ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `role` = VALUES(`role`)',
                $table
            )
        );

        $statement->execute([
            'email' => $identifier,
            'password' => $passwordHash,
            'role' => 'admin',
        ]);
    } catch (Throwable $exception) {
        throw new RuntimeException(
            sprintf("Impossible d'ecrire le compte admin dans %s: %s", $prefix . 'users', $exception->getMessage()),
            previous: $exception
        );
    }
}

function legacy_users_table_exists(PDO $connection, string $prefix): bool
{
    $tableName = $prefix . 'users';
    $statement = $connection->prepare('SHOW TABLES LIKE :table_name');
    $statement->execute(['table_name' => $tableName]);

    return $statement->fetchColumn() !== false;
}

/**
 * @param array<string, mixed> $data
 */
function persist_override_file(string $path, array $data, bool $force): void
{
    $exists = is_file($path);
    if ($exists && !$force) {
        fwrite(STDOUT, sprintf("- Override conserve (non ecrase): %s (utilisez --force-overrides)\n", $path));
        return;
    }

    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Impossible de creer le dossier %s', $directory));
    }

    $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
    $temporaryPath = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

    $written = file_put_contents($temporaryPath, $content, LOCK_EX);
    if ($written === false) {
        throw new RuntimeException(sprintf("Impossible d'ecrire %s", $temporaryPath));
    }

    @chmod($temporaryPath, 0600);

    if (!rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        throw new RuntimeException(sprintf("Impossible de deplacer %s vers %s", $temporaryPath, $path));
    }

    @chmod($path, 0600);
    fwrite(STDOUT, sprintf("- Override ecrit: %s\n", $path));
}
