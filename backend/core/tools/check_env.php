<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';

function print_error(string ): void
{
    fwrite(STDERR, "[ERROR] {}\n");
}

function print_warning(string ): void
{
    fwrite(STDERR, "[WARN] {}\n");
}

function print_success(string ): void
{
    fwrite(STDOUT, "[OK] {}\n");
}

 = array_slice(, 1);
 = null;
 = null;

foreach ( as ) {
    if (str_starts_with(, '--path=')) {
         = substr(, 7);
    } elseif (str_starts_with(, '--env=')) {
         = substr(, 6);
    }
}

 = dirname(__DIR__, 2) . '/.env';
 =  !== null ?  : ;
 = rtrim();

if (!is_file()) {
    print_error(sprintf('Fichier .env introuvable: %s', ));
    exit(1);
}

 = realpath();
if ( === false) {
    print_error(sprintf('Impossible de résoudre le chemin du fichier .env (%s).', ));
    exit(1);
}

if (str_contains(, DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR)) {
    print_error('Le fichier .env ne doit jamais se trouver dans un répertoire exposé (public/).');
    exit(1);
}

 = @fileperms();
if ( !== false && PHP_OS_FAMILY !== 'Windows') {
     =  & 0o777;
    if (( & 0o007) !== 0) {
        print_error(sprintf(
            'Permissions %o trop permissives. Retirez les droits "other" (chmod 600 ou 640 recommandé).',
            
        ));
        exit(1);
    }
    if (( & 0o020) !== 0) {
        print_warning(sprintf(
            'Permissions %o : le groupe a le droit d’écriture. Vérifiez que cela est intentionnel.',
            
        ));
    }
} elseif ( === false) {
    print_warning('Impossible de lire les permissions du fichier (fileperms a échoué).');
}

load_env();

 =  ?? env('APP_ENV', 'production');
 = strtolower((string) );

 = [];
 = [];

 = ['BASE_URL', 'DEFAULT_LANG'];
try {
    require_env(, 'configuration de base');
} catch (RuntimeException ) {
    [] = ->getMessage();
}

 = [
    'production' => ['DB_HOST', 'DB_NAME', 'DB_USER', 'MAIL_SMTP_HOST', 'MAIL_FROM_ADDRESS'],
    'staging' => ['DB_HOST', 'DB_NAME', 'DB_USER'],
];

if (array_key_exists(, )) {
    try {
        require_env([], sprintf('configuration %s', ));
    } catch (RuntimeException ) {
        [] = ->getMessage();
    }
}

 = env('DEFAULT_LANG');
if ( !== null) {
     = dirname(__DIR__, 2) . '/lang';
     =  . '/' .  . '.php';
    if (!is_file()) {
        [] = sprintf(
            'DEFAULT_LANG="%s" ne correspond à aucun fichier %s.php. Vérifiez vos traductions.',
            ,
            
        );
    }
}

 = env('MAIL_SMTP_HOST');
 = env('MAIL_SMTP_PASSWORD');
if ( === 'production' &&  && ( === null ||  === '')) {
    [] = 'MAIL_SMTP_PASSWORD est vide alors que MAIL_SMTP_HOST est défini. Assurez-vous de l’avoir configuré en production.';
}

if ( !== []) {
    foreach ( as ) {
        print_error();
    }
    exit(1);
}

if ( !== []) {
    foreach ( as ) {
        print_warning();
    }
}

print_success(sprintf('Vérification .env OK pour "%s" (%s).', , ));
exit(0);
