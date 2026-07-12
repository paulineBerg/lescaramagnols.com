<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être exécutée en CLI.\n");
    exit(1);
}

$options = parseCliOptions(array_slice($argv, 1));
$strict = isset($options['strict']) || isset($options['require-live']);
$jsonOutput = isset($options['json']);

$rawConfig = app_config('site.instagram', []);
if (!is_array($rawConfig)) {
    $rawConfig = [];
}

$username = trim((string) ($rawConfig['username'] ?? ''));
$userId = trim((string) ($rawConfig['user_id'] ?? ''));
$accessToken = trim((string) ($rawConfig['access_token'] ?? ''));
$enabled = (bool) ($rawConfig['enabled'] ?? false);

$credentialsConfigured = $accessToken !== '';
$probe = null;
$probeError = null;

if ($credentialsConfigured) {
    try {
        $probe = instagram_feed_service()->probe($rawConfig);
    } catch (Throwable $exception) {
        $probeError = $exception->getMessage();
    }
}

$status = [
    'generated_at' => date('c'),
    'enabled' => $enabled,
    'username' => $username,
    'user_id_configured' => $userId !== '',
    'access_token_configured' => $credentialsConfigured,
    'probe' => $probe,
    'probe_error' => $probeError,
];

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} else {
    fwrite(STDOUT, "Vérification Instagram (accueil)\n");
    fwrite(STDOUT, sprintf("- bloc activé: %s\n", $enabled ? 'oui' : 'non'));
    fwrite(STDOUT, sprintf("- username: %s\n", $username !== '' ? $username : '(vide)'));
    fwrite(STDOUT, sprintf("- user_id configuré: %s\n", $userId !== '' ? 'oui' : 'non'));
    fwrite(STDOUT, sprintf("- access token configuré: %s\n", $credentialsConfigured ? 'oui' : 'non'));

    if ($probeError !== null) {
        fwrite(STDOUT, sprintf("- probe API: erreur (%s)\n", $probeError));
    } elseif (is_array($probe)) {
        fwrite(
            STDOUT,
            sprintf(
                "- probe API: %s (posts=%d, username=%s)\n",
                ($probe['success'] ?? false) ? 'OK' : 'KO',
                (int) ($probe['postCount'] ?? 0),
                (string) ($probe['username'] ?? '')
            )
        );

        if (($probe['success'] ?? false) !== true) {
            fwrite(STDOUT, sprintf("  - détail: %s\n", (string) ($probe['error'] ?? 'erreur inconnue')));
        }
    } else {
        fwrite(STDOUT, "- probe API: non exécuté (token absent)\n");
    }
}

if (!$strict) {
    exit(0);
}

if (!$credentialsConfigured) {
    fwrite(STDERR, "[ERROR] Access token Instagram manquant en mode strict.\n");
    exit(2);
}

if ($probeError !== null) {
    fwrite(STDERR, sprintf("[ERROR] Probe Instagram en erreur: %s\n", $probeError));
    exit(2);
}

if (!is_array($probe) || ($probe['success'] ?? false) !== true) {
    fwrite(
        STDERR,
        sprintf(
            "[ERROR] Probe Instagram KO: %s\n",
            is_array($probe) ? (string) ($probe['error'] ?? 'erreur inconnue') : 'réponse invalide'
        )
    );
    exit(2);
}

exit(0);

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parseCliOptions(array $arguments): array
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
