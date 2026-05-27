<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentRepository;
use Caramagnols\PrivatePortal\Documents\PrivateDocumentStorage;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivatePortalStorageTest extends TestCase
{
    use EditorialSqlTestTrait;

    private array $previousPrivateConfig = [];
    private string $storageRootPath = '';
    private string $sessionName = '';

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        $this->storageRootPath = sys_get_temp_dir() . '/caramagnols-private-docs-' . bin2hex(random_bytes(6));
        mkdir($this->storageRootPath, 0777, true);

        $this->sessionName = '_private_storage_' . bin2hex(random_bytes(4));

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = $this->sessionName;
        $appConfig['private']['local_user_email'] = 'family@example.com';
        $appConfig['private']['local_user_password_hash'] = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $appConfig['private']['login_rate_limit_attempts'] = 5;
        $appConfig['private']['login_rate_limit_window'] = 900;
        $appConfig['private']['account_lockout_attempts'] = 3;
        $appConfig['private']['account_lockout_seconds'] = 86400;
        $appConfig['private']['documents'] = [
            'storage_root_path' => $this->storageRootPath,
            'storage_directory' => 'storage',
            'uploads_directory' => 'uploads',
            'exports_directory' => 'exports',
            'allowed_extensions' => ['txt', 'pdf'],
            'allowed_mime_types' => ['text/plain', 'application/pdf'],
            'max_upload_bytes' => 1024,
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectory($this->storageRootPath);

        global $appConfig;
        if ($this->previousPrivateConfig !== []) {
            $appConfig['private'] = $this->previousPrivateConfig;
        } else {
            unset($appConfig['private']);
        }
    }

    public function testFilesEndpointReturnsDocumentWhenUserHasDocumentsModule(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $documentData = $this->seedDocument($storage, $documentRepository, $userId, 'rapport.txt', 'compte-rendu');
        $auth = $this->privateAuth($userRepository, $userId);

        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $response = $controller->handle(
            'files',
            $this->request('GET', '/private/files/' . $documentData['documentId']),
            ['documentId' => $documentData['documentId']]
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('text/plain', $response->headers['Content-Type'] ?? null);
        $this->assertSame('attachment; filename="rapport.txt"', $response->headers['Content-Disposition'] ?? null);
        $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
        $this->assertSame('compte-rendu', $response->body);
    }

    public function testFilesEndpointDeniesDownloadWithoutDocumentsModule(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $documentData = $this->seedDocument($storage, $documentRepository, $userId, 'access.txt', 'interdit');
        $auth = $this->privateAuth($userRepository, $userId);

        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $response = $controller->handle(
            'files',
            $this->request('GET', '/private/files/' . $documentData['documentId']),
            ['documentId' => $documentData['documentId']]
        );

        $this->assertSame(403, $response->status);
        $this->assertSame('Forbidden', $response->body);
    }

    public function testFilesEndpointDeniesDownloadWhenModuleRemovedAfterUpload(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $documentData = $this->seedDocument($storage, $documentRepository, $userId, 'transfert.txt', 'accessible');
        $auth = $this->privateAuth($userRepository, $userId);

        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $responseWithModule = $controller->handle(
            'files',
            $this->request('GET', '/private/files/' . $documentData['documentId']),
            ['documentId' => $documentData['documentId']]
        );
        $this->assertSame(200, $responseWithModule->status);

        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $responseWithoutModule = $controller->handle(
            'files',
            $this->request('GET', '/private/files/' . $documentData['documentId']),
            ['documentId' => $documentData['documentId']]
        );

        $this->assertSame(403, $responseWithoutModule->status);
        $this->assertSame('Forbidden', $responseWithoutModule->body);
    }

    public function testFilesEndpointReturnsNotFoundWhenStorageFileIsMissing(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $documentData = $this->seedDocument($storage, $documentRepository, $userId, 'deleted.txt', 'orphan');
        @unlink($documentData['absolutePath']);

        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $response = $controller->handle(
            'files',
            $this->request('GET', '/private/files/' . $documentData['documentId']),
            ['documentId' => $documentData['documentId']]
        );

        $this->assertSame(404, $response->status);
        $this->assertSame('Not Found', $response->body);
    }

    public function testFilesUploadStoresDocumentAndAppearsInDashboard(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $upload = $this->createUploadFixture('notes.txt', 'text/plain', 'contenu');

        $response = $controller->handle(
            'files_upload',
            $this->request(
                'POST',
                '/private/files/upload',
                ['csrf_token' => csrf_token('private_documents')],
                ['document_file' => $upload]
            )
        );

        $this->cleanupUploadFixture($upload['tmp_name']);

        $this->assertSame(302, $response->status);
        $location = is_string($response->headers['Location'] ?? null) ? $response->headers['Location'] : '';
        $this->assertStringContainsString('notice=document_uploaded', $location);

        $documents = $documentRepository->listActiveByUser($userId, 10);
        $this->assertCount(1, $documents);
        $storedDocument = is_array($documents[0] ?? null) ? $documents[0] : null;
        $this->assertIsArray($storedDocument);
        $storagePath = is_string($storedDocument['storagePath'] ?? null) ? (string) $storedDocument['storagePath'] : '';
        $absolutePath = $storage->absolutePath($storagePath);
        $this->assertNotNull($absolutePath);
        $this->assertTrue(is_file($absolutePath));
    }

    public function testFilesCategoriesCanBeCreatedAndAssignedOnUpload(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $controller = new PrivatePortalController(
            $this->privateAuth($userRepository, $userId),
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $categoryResponse = $controller->handle(
            'files_categories',
            $this->request('POST', '/private/files/categories', [
                'csrf_token' => csrf_token('private_documents'),
                'category_name' => 'Assurances habitation',
                'category_color' => '#2563eb',
            ])
        );
        $this->assertSame(302, $categoryResponse->status);
        $categories = $documentRepository->listCategoriesForUser($userId);
        $this->assertCount(1, $categories);
        $categoryId = (int) ($categories[0]['id'] ?? 0);
        $this->assertGreaterThan(0, $categoryId);

        $upload = $this->createUploadFixture('assurance.txt', 'text/plain', 'attestation');
        $uploadResponse = $controller->handle(
            'files_upload',
            $this->request(
                'POST',
                '/private/files/upload',
                [
                    'csrf_token' => csrf_token('private_documents'),
                    'category_id' => $categoryId,
                ],
                ['document_file' => $upload]
            )
        );
        $this->cleanupUploadFixture($upload['tmp_name']);

        $this->assertSame(302, $uploadResponse->status);
        $documents = $documentRepository->listActiveByUser($userId, 10);
        $this->assertCount(1, $documents);
        $this->assertSame($categoryId, $documents[0]['categoryId']);
        $this->assertSame('Assurances habitation', $documents[0]['categoryName']);
    }

    public function testFilesDeleteRemovesDocumentAndStorageFile(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $storageDocument = $this->seedDocument($storage, $documentRepository, $userId, 'suppression.txt', 'contenu');
        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $response = $controller->handle(
            'files_delete',
            $this->request(
                'POST',
                '/private/files/' . $storageDocument['documentId'] . '/delete',
                ['csrf_token' => csrf_token('private_documents')]
            ),
            ['documentId' => $storageDocument['documentId']]
        );

        $this->assertSame(302, $response->status);
        $location = is_string($response->headers['Location'] ?? null) ? $response->headers['Location'] : '';
        $this->assertStringContainsString('notice=document_deleted', $location);
        $this->assertNull($documentRepository->findByDocumentIdAndUser($storageDocument['documentId'], $userId));
        $this->assertFalse(is_file((string) $storageDocument['absolutePath']));
    }

    public function testFilesUploadRejectedWithoutDocumentsModule(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $upload = $this->createUploadFixture('notes.txt', 'text/plain', 'contenu');

        $response = $controller->handle(
            'files_upload',
            $this->request(
                'POST',
                '/private/files/upload',
                ['csrf_token' => csrf_token('private_documents')],
                ['document_file' => $upload]
            )
        );

        $this->cleanupUploadFixture($upload['tmp_name']);

        $this->assertSame(403, $response->status);
        $this->assertSame('Forbidden', $response->body);
        $this->assertSame([], $documentRepository->listActiveByUser($userId, 10));
    }

    public function testFilesUploadRejectedWithoutCsrf(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $upload = $this->createUploadFixture('notes.txt', 'text/plain', 'contenu');
        $response = $controller->handle(
            'files_upload',
            $this->request(
                'POST',
                '/private/files/upload',
                ['not_the_expected_csrf' => 'ko'],
                ['document_file' => $upload]
            )
        );
        $this->cleanupUploadFixture($upload['tmp_name']);

        $this->assertSame(302, $response->status);
        $location = is_string($response->headers['Location'] ?? null) ? $response->headers['Location'] : '';
        $this->assertStringContainsString('error=upload_forbidden', $location);
        $this->assertSame([], $documentRepository->listActiveByUser($userId, 10));
    }

    public function testFilesUploadRejectedIfNoFileSubmitted(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $response = $controller->handle(
            'files_upload',
            $this->request(
                'POST',
                '/private/files/upload',
                ['csrf_token' => csrf_token('private_documents')]
            )
        );

        $this->assertSame(302, $response->status);
        $location = is_string($response->headers['Location'] ?? null) ? $response->headers['Location'] : '';
        $this->assertStringContainsString('error=missing_file', $location);
        $this->assertSame([], $documentRepository->listActiveByUser($userId, 10));
    }

    public function testFilesDeleteRejectedWithoutDocumentsModule(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['dashboard'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $storageDocument = $this->seedDocument($storage, $documentRepository, $userId, 'suppression.txt', 'contenu');
        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $response = $controller->handle(
            'files_delete',
            $this->request(
                'POST',
                '/private/files/' . $storageDocument['documentId'] . '/delete',
                ['csrf_token' => csrf_token('private_documents')]
            ),
            ['documentId' => $storageDocument['documentId']]
        );

        $this->assertSame(403, $response->status);
        $this->assertSame('Forbidden', $response->body);
        $this->assertSame(1, count($documentRepository->listActiveByUser($userId, 10)));
    }

    public function testFilesDeleteRejectedIfDocumentNotFound(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $documentRepository = new PrivateDocumentRepository($database);
        $passwordHash = password_hash('SecretPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);

        $userId = $userRepository->create('family@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);
        $this->assertTrue($moduleRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $storage = PrivateDocumentStorage::fromAppConfig();
        $auth = $this->privateAuth($userRepository, $userId);
        $controller = new PrivatePortalController(
            $auth,
            null,
            null,
            $userRepository,
            $moduleRepository,
            $documentRepository,
            $storage
        );

        $response = $controller->handle(
            'files_delete',
            $this->request(
                'POST',
                '/private/files/unknown/delete',
                ['csrf_token' => csrf_token('private_documents')]
            ),
            ['documentId' => 'unknown']
        );

        $this->assertSame(302, $response->status);
        $location = is_string($response->headers['Location'] ?? null) ? $response->headers['Location'] : '';
        $this->assertStringContainsString('error=delete_not_found', $location);
    }

    public function testStorageRejectsInvalidUploadAndAcceptsAllowedUpload(): void
    {
        $storage = PrivateDocumentStorage::fromAppConfig();
        $documentId = $storage->generateDocumentId();

        $invalidExtension = $this->createUploadFixture(
            'script.exe',
            'text/plain',
            'payload'
        );
        $this->assertNull($storage->validateUploadedFile($invalidExtension));
        $this->assertSame('invalid_extension', $storage->uploadError());
        $this->cleanupUploadFixture($invalidExtension['tmp_name']);

        $invalidMime = $this->createUploadFixture(
            'notes.txt',
            'application/octet-stream',
            "\x00\x00\x00\x01payload\x00\x00\x00\x02\x03\x04\x05\x06\x07\x00\x00\x01\x01\x01\x04"
        );
        $this->assertNull($storage->validateUploadedFile($invalidMime));
        $this->assertSame('invalid_mime', $storage->uploadError());
        $this->cleanupUploadFixture($invalidMime['tmp_name']);

        $oversized = $this->createUploadFixture(
            'too-big.txt',
            'text/plain',
            str_repeat('a', 2048)
        );
        $this->assertNull($storage->validateUploadedFile($oversized));
        $this->assertSame('invalid_size', $storage->uploadError());
        $this->cleanupUploadFixture($oversized['tmp_name']);

        $valid = $this->createUploadFixture(
            'notes.txt',
            'text/plain',
            'payload'
        );
        $validated = $storage->validateUploadedFile($valid);
        $this->assertNotNull($validated);
        $stored = $storage->storeUploadedFile($validated, $documentId);
        $this->assertIsArray($stored);
        $this->cleanupUploadFixture($valid['tmp_name']);
        $this->assertSame('payload', file_get_contents((string) $storage->absolutePath((string) $stored['storagePath'])));
        $storage->deleteStoredDocument((string) $stored['storagePath'], $documentId);
    }

    private function seedDocument(
        PrivateDocumentStorage $storage,
        PrivateDocumentRepository $documentRepository,
        int $userId,
        string $filename,
        string $content
    ): array {
        $documentId = $storage->generateDocumentId();
        $storagePath = sprintf(
            'uploads/%s/%s/%s.txt',
            substr($documentId, 0, 2),
            substr($documentId, 2, 2),
            $documentId
        );
        $absolutePath = $storage->absolutePath($storagePath);
        $this->assertIsString($absolutePath);

        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            $this->fail('Failed to create storage directory for private document fixture.');
        }

        $this->assertTrue(file_put_contents($absolutePath, $content) !== false);

        $created = $documentRepository->create(
            $userId,
            $documentId,
            $storagePath,
            $filename,
            'txt',
            'text/plain',
            strlen($content),
            $userId
        );
        $this->assertIsArray($created);

        return [
            'documentId' => $documentId,
            'storagePath' => $storagePath,
            'absolutePath' => $absolutePath,
        ];
    }

    private function privateAuth(PrivateUserRepository $userRepository, int $userId): PrivateAuth
    {
        $session = new PrivateSession($this->sessionName);
        $auth = new PrivateAuth($session, null, $userRepository);
        $this->assertTrue($auth->login('family@example.com', 'SecretPassword1!', '127.0.0.1'));
        $this->assertTrue($auth->isAuthenticated());

        return $auth;
    }

    private function createUploadFixture(string $name, string $mimeType, string $content): array
    {
        $tmpName = tempnam(sys_get_temp_dir(), 'private-doc-upload-');
        $this->assertIsString($tmpName);
        $this->assertTrue(file_put_contents($tmpName, $content) !== false);

        return [
            'name' => $name,
            'tmp_name' => $tmpName,
            'size' => strlen($content),
            'error' => UPLOAD_ERR_OK,
            'type' => $mimeType,
        ];
    }

    private function cleanupUploadFixture(?string $tmpName): void
    {
        if (is_string($tmpName) && is_file($tmpName)) {
            @unlink($tmpName);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = glob($path . '/*');
        $items = is_array($items) ? $items : [];
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->removeDirectory($item);
            } else {
                @unlink($item);
            }
        }

        @rmdir($path);
    }

    private function request(string $method, string $uri, array $post = [], array $files = []): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            [],
            $post,
            [],
            ['Host' => '127.0.0.1:8000'],
            '',
            $files
        );
    }
}
