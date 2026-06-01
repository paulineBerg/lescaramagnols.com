<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminRecoveryService;

require_once dirname(__DIR__) . '/bootstrap.php';

$command = $argv[1] ?? '';
$options = parse_options(array_slice($argv, 2));
if ($command === '' || in_array($command, ['-h', '--help'], true) || isset($options['help'])) {
    usage();
    exit($command === '' ? 1 : 0);
}

$adminOverridePath = is_string($options['admin-override'] ?? null)
    ? expand_home((string) $options['admin-override'])
    : ROOT_PATH . '/config/admin.override.php';
$keyFile = is_string($options['key-file'] ?? null) ? (string) $options['key-file'] : '';
$count = is_numeric($options['count'] ?? null) ? (int) $options['count'] : AdminRecoveryService::DEFAULT_KEY_COUNT;
$environment = is_string($options['env'] ?? null) && trim((string) $options['env']) !== ''
    ? trim((string) $options['env'])
    : 'local';
$force = array_key_exists('force', $options);
$service = new AdminRecoveryService($adminOverridePath);

try {
    if ($command === 'create') {
        if ($keyFile === '' || $keyFile === '-') {
            fwrite(STDERR, "--key-file est obligatoire pour create.\n");
            exit(1);
        }

        $keyFile = expand_home($keyFile);
        if (file_exists($keyFile) && !$force) {
            fwrite(STDERR, "Le fichier de cles existe deja. Utilisez --force pour le remplacer.\n");
            exit(1);
        }

        $plainKeys = $service->generatePlainKeys($count);
        $install = !array_key_exists('no-install', $options);
        $installedCount = $install ? $service->installPlainKeys($plainKeys) : count($plainKeys);
        write_keys_file($keyFile, $environment, $plainKeys);
        fwrite(STDOUT, sprintf("%d cles de recuperation admin creees.\n", $installedCount));
        fwrite(STDOUT, sprintf("Fichier local: %s\n", $keyFile));
        fwrite(
            STDOUT,
            $install
                ? sprintf("Configuration serveur: %s\n", $adminOverridePath)
                : "Configuration serveur: non modifiee (--no-install)\n"
        );
        fwrite(STDOUT, "Conservez ce fichier hors depot et ne le partagez pas.\n");
        exit(0);
    }

    if ($command === 'install') {
        if ($keyFile === '') {
            fwrite(STDERR, "--key-file est obligatoire pour install, ou utilisez --key-file=- pour stdin.\n");
            exit(1);
        }

        $content = $keyFile === '-'
            ? (string) stream_get_contents(STDIN)
            : read_key_file(expand_home($keyFile));
        $plainKeys = parse_keys_from_content($content);
        $installedCount = $service->installPlainKeys($plainKeys);
        fwrite(STDOUT, sprintf("%d hash(es) de cles de recuperation installes.\n", $installedCount));
        fwrite(STDOUT, sprintf("Configuration serveur: %s\n", $adminOverridePath));
        exit(0);
    }

    if ($command === 'status') {
        fwrite(
            STDOUT,
            sprintf(
                "Cles de recuperation admin: %s\n",
                $service->hasUsableRecoveryKey() ? 'configurees' : 'absentes ou toutes utilisees'
            )
        );
        exit(0);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

usage();
exit(1);

function usage(): void
{
    fwrite(
        STDOUT,
        <<<'USAGE'
Usage:
  php backend/core/tools/admin_recovery_keys.php create --key-file=~/.caramagnols/admin-local.keys [--count=10] [--env=local] [--force] [--no-install]
  php backend/core/tools/admin_recovery_keys.php install --key-file=~/.caramagnols/admin-local.keys
  php backend/core/tools/admin_recovery_keys.php install --key-file=-
  php backend/core/tools/admin_recovery_keys.php status

Description:
  Cree ou installe 10 cles de recuperation admin a usage unique.
  Le fichier local contient les cles en clair. Le serveur ne stocke que leurs hashes dans config/admin.override.php.
  Pour la preprod, generez le fichier sur votre PC puis installez les hashes via SSH avec --key-file=-.

USAGE
    );
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|bool>
 */
function parse_options(array $arguments): array
{
    $options = [];
    for ($i = 0, $count = count($arguments); $i < $count; ++$i) {
        $argument = $arguments[$i];
        if (!str_starts_with($argument, '--')) {
            continue;
        }

        $option = substr($argument, 2);
        if (str_contains($option, '=')) {
            [$name, $value] = explode('=', $option, 2);
            $options[$name] = $value;
            continue;
        }

        if (in_array($option, ['force', 'help', 'no-install'], true)) {
            $options[$option] = true;
            continue;
        }

        $next = $arguments[$i + 1] ?? null;
        if (is_string($next) && !str_starts_with($next, '--')) {
            $options[$option] = $next;
            ++$i;
        }
    }

    return $options;
}

function expand_home(string $path): string
{
    if ($path === '~' || str_starts_with($path, '~/')) {
        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            return $home . substr($path, 1);
        }
    }

    return $path;
}

/**
 * @param array<int, array{label: string, key: string}> $plainKeys
 */
function write_keys_file(string $path, string $environment, array $plainKeys): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de creer le dossier du fichier de cles.');
    }

    $lines = [
        '# Cles de recuperation admin Les Caramagnols',
        '# Environnement: ' . $environment,
        '# Cree le: ' . date('c'),
        '# Une cle est a usage unique. Conserver ce fichier hors depot.',
        '',
    ];
    foreach ($plainKeys as $entry) {
        $lines[] = $entry['label'] . ': ' . $entry['key'];
    }

    if (file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Impossible d ecrire le fichier de cles.');
    }

    @chmod($path, 0600);
}

function read_key_file(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('Fichier de cles introuvable.');
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        throw new RuntimeException('Fichier de cles illisible.');
    }

    return $content;
}

/**
 * @return array<int, array{label: string, key: string}>
 */
function parse_keys_from_content(string $content): array
{
    preg_match_all('/(?:^|\s)([A-Za-z0-9_.-]+)?\s*:?\s*(CAR-REC(?:-[A-Z2-9]{4}){8})/mi', $content, $matches, PREG_SET_ORDER);

    $keys = [];
    foreach ($matches as $index => $match) {
        $rawLabel = trim((string) $match[1]);
        $label = $rawLabel !== ''
            ? $rawLabel
            : 'recovery-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        $keys[] = [
            'label' => $label,
            'key' => strtoupper((string) $match[2]),
        ];
    }

    return $keys;
}
