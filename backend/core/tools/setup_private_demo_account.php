<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Documents\PrivateDocumentRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit être executee en CLI.\n");
    exit(1);
}

if (array_slice($argv, 1) === []) {
    print_usage();
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));

$email = trim((string) ($options['email'] ?? ''));
$password = (string) ($options['password'] ?? '');
$force = isset($options['force']);
$withDemoDocument = parse_flag($options['with-demo-document'] ?? ($options['with_demo_document'] ?? '1'));
$documentName = trim((string) ($options['document-name'] ?? ($options['document_name'] ?? 'document-demo-prive.txt')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Option --email invalide.\n");
    exit(1);
}

if (!is_string($password) || strlen($password) < (int) app_config('private.password_min_length', 14)) {
    fwrite(STDERR, "Mot de passe trop court pour le compte privé (min 14 caracteres).\n");
    exit(1);
}

if ($documentName === '') {
    fwrite(STDERR, "Option --document_name invalide.\n");
    exit(1);
}

$database = editorial_database();
$userRepository = new PrivateUserRepository($database);
$moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
$documentRepository = new PrivateDocumentRepository($database);
$storage = PrivateDocumentStorage::fromAppConfig();

$existingUser = $userRepository->findByEmail($email);

if ($existingUser !== null && strtolower((string) ($existingUser['status'] ?? '')) === 'deleted' && !$force) {
    fwrite(STDERR, "Le compte existe en status 'deleted'. Utilisez --force pour le reactiver.\n");
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_ARGON2ID);

$userId = null;
if ($existingUser !== null) {
    $existingUserId = is_int($existingUser['id'] ?? null) ? (int) $existingUser['id'] : null;
    if ($existingUserId === null && is_numeric($existingUser['id'] ?? null)) {
        $existingUserId = (int) $existingUser['id'];
    }
    $userId = $existingUserId;
    if ($userId === null) {
        fwrite(STDERR, "Identifiant membre prive incoherent pour cet email.\n");
        exit(1);
    }

    $ok = $userRepository->setPasswordHash($userId, $passwordHash) && $userRepository->updateStatus($userId, 'active');
    if (!$ok) {
        fwrite(STDERR, "Mise a jour du compte prive echouee.\n");
        exit(1);
    }
} else {
    $userId = $userRepository->create($email, $passwordHash, 'active');
    if ($userId === null) {
        fwrite(STDERR, "Creation du compte prive echouee.\n");
        exit(1);
    }
}

$modulesAssigned = $moduleRepository->setUserModules($userId, ['dashboard', 'documents'], 'setup_private_demo_account');
if (!$modulesAssigned) {
    fwrite(STDERR, "Attribution des modules dashboard/documents echouee.\n");
    exit(1);
}

if ($withDemoDocument) {
    $documentId = createDemoDocument(
        $userId,
        $storage,
        $documentRepository,
        $documentName
    );
    if ($documentId === '') {
        fwrite(STDERR, "Creation du document de demonstration echec.\n");
        exit(1);
    }

    fwrite(STDOUT, sprintf("Document demo cree: %s\n", $documentId));
}

fwrite(
    STDOUT,
    sprintf(
        "Compte prive de demo pret : email=%s, user_id=%d, modules=dashboard,documents.\n",
        $email,
        $userId
    )
);
fwrite(STDOUT, "Parcours test: /private/login -> /private/dashboard -> /private/files/{document_id}\n");
if ($withDemoDocument) {
    $latestDocument = $documentRepository->listActiveByUser($userId, 1);
    if (isset($latestDocument[0]['documentId'])) {
        $documentId = (string) $latestDocument[0]['documentId'];
        fwrite(STDOUT, sprintf("Document demo (dernier) : %s\n", $documentId));
    }
}
exit(0);

/**
 * @param array<int, string> $options
 * @return array<string, string|true>
 */
function parse_cli_options(array $options): array
{
    $parsed = [];
    foreach ($options as $option) {
        if (!is_string($option) || !str_starts_with($option, '--')) {
            continue;
        }

        $parts = explode('=', substr($option, 2), 2);
        if (!isset($parts[1])) {
            $parsed[$parts[0]] = true;
            continue;
        }

        $parsed[$parts[0]] = $parts[1];
    }

    return $parsed;
}

/**
 * @return bool
 */
function parse_flag(mixed $value): bool
{
    if ($value === true) {
        return true;
    }
    if (!is_string($value)) {
        return false;
    }

    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function createDemoDocument(
    int $userId,
    PrivateDocumentStorage $storage,
    PrivateDocumentRepository $documentRepository,
    string $documentName
): string {
    $documentId = $storage->generateDocumentId();
    if ($documentId === '') {
        return '';
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'private-demo-doc-');
    if ($tmpPath === false) {
        return '';
    }

    try {
        $content = implode(
            "\n",
            [
                'Exemple de document prive',
                'Ce fichier sert au test de parcours /private/files/{documentId}.',
                'Date de generation: ' . date('c'),
            ]
        );
        if (file_put_contents($tmpPath, $content) === false) {
            return '';
        }

        $uploaded = [
            'name' => $documentName,
            'tmp_name' => $tmpPath,
            'size' => strlen($content),
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain',
        ];

        $validated = $storage->validateUploadedFile($uploaded);
        if (!is_array($validated)) {
            return '';
        }

        $stored = $storage->storeUploadedFile($validated, $documentId);
        if (!is_array($stored)) {
            return '';
        }

        $created = $documentRepository->create(
            $userId,
            (string) $stored['documentId'],
            (string) $stored['storagePath'],
            (string) $stored['originalName'],
            (string) $stored['extension'],
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            $userId
        );

        return is_array($created) && isset($created['documentId']) && is_string($created['documentId'])
            ? (string) $created['documentId']
            : '';
    } finally {
        if (is_string($tmpPath) && is_file($tmpPath)) {
            @unlink($tmpPath);
        }
    }
}

function print_usage(): void
{
    $usage = <<<'TXT'
Usage:
  php core/tools/setup_private_demo_account.php --email=demo@example.com --password='SecretPass123!' [--force] [--with-demo-document=0|1] [--document-name=demo.txt]

Options:
  --email=...                  Email du compte prive de demonstration
  --password=...               Mot de passe fort (min 14)
  --force                      Reactiver le compte meme si status deleted
  --with-demo-document=1|0     Cree un document de test (defaut: 1)
  --document-name=...          Nom du document demo

TXT;
    fwrite(STDOUT, $usage);
}
