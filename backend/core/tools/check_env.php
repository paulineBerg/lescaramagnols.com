<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';

/**
 * Small helper to print formatted output.
 */
function console_error(string $message): void
{
    fwrite(STDERR, "[ERROR] {$message}\n");
}

function console_warning(string $message): void
{
    fwrite(STDERR, "[WARN]  {$message}\n");
}

function console_success(string $message): void
{
    fwrite(STDOUT, "[OK]    {$message}\n");
}

$args = array_slice($_SERVER['argv'] ?? [], 1);
$envPathOption = null;
$forcedEnv = null;
$extraRequired = [];
$jsonOutput = false;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--path=')) {
        $envPathOption = substr($arg, 7);
    } elseif (str_starts_with($arg, '--env=')) {
        $forcedEnv = substr($arg, 6);
    } elseif (str_starts_with($arg, '--require=')) {
        $list = substr($arg, 10);
        $extraRequired = array_filter(array_map(static fn (string $item): string => trim($item), explode(',', $list)));
    } elseif ($arg === '--json') {
        $jsonOutput = true;
    }
}

$projectRoot = dirname(__DIR__, 2);
$envPath = $envPathOption !== null ? $envPathOption : $projectRoot . '/.env';
$envPath = rtrim($envPath);

$errors = [];
$warnings = [];

if (!is_file($envPath)) {
    $errors[] = sprintf('Fichier .env introuvable: %s', $envPath);
} else {
    $realPath = realpath($envPath);
    if ($realPath === false) {
        $errors[] = sprintf('Impossible de résoudre le chemin du fichier .env (%s).', $envPath);
    } else {
        if (str_contains($realPath, DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR)) {
            $errors[] = 'Le fichier .env ne doit jamais se trouver dans un répertoire exposé (public/).';
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $perms = @fileperms($realPath);
            if ($perms === false) {
                $warnings[] = 'Impossible de lire les permissions du fichier (fileperms a échoué).';
            } else {
                $mode = $perms & 0o777;
                if (($mode & 0o007) !== 0) {
                    $errors[] = sprintf(
                        'Permissions %o trop permissives. Retirez les droits "other" (chmod 600 ou 640 recommandé).',
                        $mode
                    );
                }
                if (($mode & 0o020) !== 0) {
                    $warnings[] = sprintf(
                        'Permissions %o : le groupe a le droit d’écriture. Vérifiez que cela est intentionnel.',
                        $mode
                    );
                }
            }
        }

        if ($errors === []) {
            load_env($realPath);

            if ($forcedEnv !== null && $forcedEnv !== '') {
                $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $forcedEnv;
                putenv('APP_ENV=' . $forcedEnv);
            }

            $appEnv = strtolower((string) env('APP_ENV', 'production'));
            $allowedEnvs = ['development', 'staging', 'testing', 'production'];
            if (!in_array($appEnv, $allowedEnvs, true)) {
                $warnings[] = sprintf(
                    'APP_ENV="%s" n’est pas reconnu. Valeurs attendues : %s.',
                    $appEnv,
                    implode(', ', $allowedEnvs)
                );
            }

            $baseRequired = array_unique(array_merge(['BASE_URL', 'DEFAULT_LANG'], $extraRequired));
            try {
                require_env($baseRequired, 'configuration de base');
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }

            $environmentMatrix = [
                'production' => [
                    'DB_HOST',
                    'DB_NAME',
                    'DB_USER',
                    'DB_PASSWORD',
                    'MAIL_SMTP_HOST',
                    'MAIL_SMTP_PORT',
                    'MAIL_SMTP_USER',
                    'MAIL_SMTP_PASSWORD',
                    'MAIL_SMTP_ENCRYPTION',
                    'MAIL_FROM_ADDRESS',
                ],
                'staging' => [
                    'DB_HOST',
                    'DB_NAME',
                    'DB_USER',
                    'DB_PASSWORD',
                    'MAIL_SMTP_HOST',
                    'MAIL_SMTP_PORT',
                ],
            ];

            if (isset($environmentMatrix[$appEnv])) {
                try {
                    require_env($environmentMatrix[$appEnv], sprintf('configuration %s', $appEnv));
                } catch (RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }

            $defaultLang = env('DEFAULT_LANG');
            if (is_string($defaultLang) && $defaultLang !== '') {
                $langFile = $projectRoot . '/lang/' . $defaultLang . '.php';
                if (!is_file($langFile)) {
                    $warnings[] = sprintf(
                        'DEFAULT_LANG="%s" ne correspond à aucun fichier %s. Vérifiez vos traductions.',
                        $defaultLang,
                        basename($langFile)
                    );
                }
            }

            if ($appEnv === 'production') {
                $baseUrl = env('BASE_URL', '');
                if ($baseUrl === '/' || $baseUrl === '') {
                    $warnings[] = 'BASE_URL est vide ou égal à "/" alors que vous êtes en production.';
                }
            }

            $mailHost = env('MAIL_SMTP_HOST');
            if ($mailHost) {
                $mailUser = env('MAIL_SMTP_USER');
                $mailPassword = env('MAIL_SMTP_PASSWORD');
                $mailEncryption = strtolower((string) env('MAIL_SMTP_ENCRYPTION', ''));
                if ($mailUser === null || $mailUser === '') {
                    $warnings[] = 'MAIL_SMTP_USER est vide alors que MAIL_SMTP_HOST est défini.';
                }
                if ($mailPassword === null || $mailPassword === '') {
                    $warnings[] = 'MAIL_SMTP_PASSWORD est vide alors que MAIL_SMTP_HOST est défini.';
                }
                if ($appEnv === 'production' && $mailEncryption === '') {
                    $errors[] = 'MAIL_SMTP_ENCRYPTION est requis en production lorsque MAIL_SMTP_HOST est défini.';
                }
            }

            $mailFrom = env('MAIL_FROM_ADDRESS');
            if ($mailFrom && filter_var($mailFrom, FILTER_VALIDATE_EMAIL) === false) {
                $warnings[] = sprintf('MAIL_FROM_ADDRESS "%s" n’est pas une adresse e-mail valide.', $mailFrom);
            }

            $mailEncryption = strtolower((string) env('MAIL_SMTP_ENCRYPTION', ''));
            if ($mailEncryption !== '' && !in_array($mailEncryption, ['ssl', 'tls', 'starttls'], true)) {
                $warnings[] = sprintf(
                    'MAIL_SMTP_ENCRYPTION "%s" n’est pas supporté (utilisez "", "ssl", "tls" ou "starttls").',
                    $mailEncryption
                );
            }

            $adminEmail = env('ADMIN_EMAIL');
            if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
                $warnings[] = sprintf('ADMIN_EMAIL "%s" n’est pas une adresse e-mail valide.', $adminEmail);
            }

            $defaultAdminHash = '$2y$10$nGij1lrgL7sdDTzAVt.Rt.UZPw3qF8/TWguRFVASVVrM038294rAS';
            $adminHash = env('ADMIN_PASSWORD_HASH');
            if ($adminHash === $defaultAdminHash) {
                $warnings[] = 'ADMIN_PASSWORD_HASH utilise encore la valeur par défaut fournie dans le dépôt.';
            } elseif (is_string($adminHash) && $adminHash !== '') {
                $info = password_get_info($adminHash);
                if (($info['algo'] ?? 0) === 0) {
                    $warnings[] = 'ADMIN_PASSWORD_HASH ne semble pas être un hash valide généré via password_hash.';
                }
            }

            $oauthProviders = env('OAUTH_PROVIDERS', '');
            if (is_string($oauthProviders) && trim($oauthProviders) !== '') {
                $providers = array_filter(array_map(
                    static fn (string $provider): string => strtolower(trim($provider)),
                    explode(',', $oauthProviders)
                ));

                foreach ($providers as $provider) {
                    if ($provider === '') {
                        continue;
                    }

                    $prefix = strtoupper($provider);
                    $expectedKeys = [
                        "OAUTH_{$prefix}_CLIENT_ID",
                        "OAUTH_{$prefix}_CLIENT_SECRET",
                    ];

                    $redirectKey = "OAUTH_{$prefix}_REDIRECT_URI";
                    if (env($redirectKey) !== null) {
                        $expectedKeys[] = $redirectKey;
                    }

                    try {
                        require_env($expectedKeys, sprintf('OAuth provider "%s"', $provider));
                    } catch (RuntimeException $exception) {
                        $errors[] = $exception->getMessage();
                    }

                    $audienceKey = "OAUTH_{$prefix}_AUDIENCE";
                    $audienceValue = env($audienceKey);
                    if ($audienceValue !== null && $audienceValue === '') {
                        $warnings[] = sprintf('%s est défini mais vide.', $audienceKey);
                    }
                }
            }
        }
    }
}

if ($jsonOutput) {
    $payload = [
        'path' => $envPath,
        'errors' => $errors,
        'warnings' => $warnings,
        'status' => $errors === [] ? 'ok' : 'failed',
    ];
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit($errors === [] ? 0 : 1);
}

foreach ($errors as $error) {
    console_error($error);
}

foreach ($warnings as $warning) {
    console_warning($warning);
}

if ($errors !== []) {
    exit(1);
}

$envName = $forcedEnv ?? env('APP_ENV', 'production');
console_success(sprintf('Vérification .env OK pour "%s" (%s).', $envName, $envPath));
exit(0);
