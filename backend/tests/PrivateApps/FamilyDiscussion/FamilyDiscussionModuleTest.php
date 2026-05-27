<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivateApps\FamilyDiscussion\Retention\DiscussionRetentionService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionAccessPolicy;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionService;
use Caramagnols\Http\Request;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../core/bootstrap.php';

final class FamilyDiscussionModuleTest extends TestCase
{
    use EditorialSqlTestTrait;

    private array $previousPrivateConfig = [];
    private string $storageRoot = '';
    private string $sessionName = '';

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        $this->storageRoot = sys_get_temp_dir() . '/caramagnols-family-discussion-' . bin2hex(random_bytes(6));
        mkdir($this->storageRoot, 0700, true);
        $this->sessionName = '_private_discussion_' . bin2hex(random_bytes(4));

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = $this->sessionName;
        $appConfig['private']['discussions'] = [
            'storage_root_path' => $this->storageRoot,
            'retention_days' => 60,
            'max_message_length' => 4000,
            'max_attachments_per_message' => 5,
            'max_attachment_bytes' => 1024 * 1024,
            'allowed_extensions' => ['txt', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf'],
            'allowed_mime_types' => ['text/plain', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'application/pdf'],
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($GLOBALS['csp_nonce']);
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectory($this->storageRoot);

        global $appConfig;
        if ($this->previousPrivateConfig !== []) {
            $appConfig['private'] = $this->previousPrivateConfig;
        } else {
            unset($appConfig['private']);
        }
    }

    public function testDirectConversationMessagesAreVisibleOnlyToParticipants(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $repository = new DiscussionRepository($database);
        $service = new DiscussionService($repository, $userRepository, $this->storage());

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');
        $outsiderId = $this->createPrivateUser($userRepository, 'outsider@example.com');

        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $conversationId = (int) $conversation['id'];

        $duplicate = $service->createDirectConversation($bobId, $aliceId);
        $this->assertIsArray($duplicate);
        $this->assertSame($conversationId, (int) $duplicate['id']);

        $message = $service->sendMessage($aliceId, $conversationId, 'Bonjour Bob');
        $this->assertIsArray($message);

        $bobMessages = $service->listMessages($conversationId, $bobId);
        $this->assertCount(1, $bobMessages);
        $this->assertSame('Bonjour Bob', $bobMessages[0]['body']);

        $this->assertSame([], $service->listMessages($conversationId, $outsiderId));
        $this->assertNull($repository->findConversationForUser($conversationId, $outsiderId));
    }

    public function testGroupOwnerCanAddMembersButRegularMemberCannot(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $repository = new DiscussionRepository($database);
        $service = new DiscussionService($repository, $userRepository, $this->storage());
        $policy = new DiscussionAccessPolicy($repository);

        $ownerId = $this->createPrivateUser($userRepository, 'owner@example.com');
        $memberId = $this->createPrivateUser($userRepository, 'member@example.com');
        $newMemberId = $this->createPrivateUser($userRepository, 'new-member@example.com');

        $conversation = $service->createGroupConversation($ownerId, 'Famille', [$memberId]);
        $this->assertIsArray($conversation);
        $conversationId = (int) $conversation['id'];

        $this->assertFalse($service->addMembers($memberId, $conversationId, [$newMemberId], $policy));
        $this->assertNull($repository->findConversationForUser($conversationId, $newMemberId));

        $this->assertTrue($service->addMembers($ownerId, $conversationId, [$newMemberId], $policy));
        $this->assertIsArray($repository->findConversationForUser($conversationId, $newMemberId));
    }

    public function testAttachmentsArePrivateAndExpiredContentIsPurged(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $repository = new DiscussionRepository($database);
        $storage = $this->storage();
        $service = new DiscussionService($repository, $userRepository, $storage);

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');
        $outsiderId = $this->createPrivateUser($userRepository, 'outsider@example.com');

        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $conversationId = (int) $conversation['id'];

        $activeUpload = $this->createUpload('note-active.txt', 'piece jointe active');
        $message = $service->sendMessage($aliceId, $conversationId, 'Voir le fichier', [
            'discussion_files' => [
                'name' => [$activeUpload['name']],
                'tmp_name' => [$activeUpload['tmp_name']],
                'size' => [$activeUpload['size']],
                'error' => [UPLOAD_ERR_OK],
                'type' => ['text/plain'],
            ],
        ]);
        $this->assertIsArray($message);
        $this->assertCount(1, $message['attachments']);

        $attachment = $message['attachments'][0];
        $attachmentId = (string) $attachment['attachmentId'];
        $this->assertIsArray($repository->findAttachmentForUser($attachmentId, $bobId));
        $this->assertNull($repository->findAttachmentForUser($attachmentId, $outsiderId));
        $activePath = $storage->absolutePath((string) $attachment['storagePath']);
        $this->assertIsString($activePath);
        $this->assertFileExists($activePath);

        $expiredAt = date('Y-m-d H:i:s', time() - 60);
        $expiredMessage = $repository->createMessage($conversationId, $aliceId, 'ancien message', $expiredAt);
        $this->assertIsArray($expiredMessage);
        $expiredUpload = $this->createUpload('note-expired.txt', 'piece jointe expiree');
        $metadata = $storage->validateUploadedFile([
            'name' => $expiredUpload['name'],
            'tmp_name' => $expiredUpload['tmp_name'],
            'size' => $expiredUpload['size'],
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/plain',
        ]);
        $this->assertIsArray($metadata);
        $stored = $storage->store($metadata, 'expiredattachment');
        $this->assertIsArray($stored);
        $expiredAttachment = $repository->createAttachment(
            (int) $expiredMessage['id'],
            (string) $stored['attachmentId'],
            (string) $stored['originalFilename'],
            (string) $stored['storagePath'],
            is_string($stored['previewStoragePath'] ?? null) ? (string) $stored['previewStoragePath'] : null,
            (string) $stored['mimeType'],
            (int) $stored['sizeBytes'],
            (string) $stored['sha256'],
            is_int($stored['width'] ?? null) ? $stored['width'] : null,
            is_int($stored['height'] ?? null) ? $stored['height'] : null,
            $expiredAt
        );
        $this->assertIsArray($expiredAttachment);
        $expiredPath = $storage->absolutePath((string) $stored['storagePath']);
        $this->assertIsString($expiredPath);
        $this->assertFileExists($expiredPath);

        $result = (new DiscussionRetentionService($repository, $storage))->purgeExpiredForUser($bobId);

        $this->assertSame(1, $result['messages']);
        $this->assertSame(1, $result['attachments']);
        $purgedMessage = $repository->findMessageById((int) $expiredMessage['id']);
        $this->assertIsArray($purgedMessage);
        $this->assertSame('', $purgedMessage['body']);
        $this->assertSame('purged', $purgedMessage['purgeStatus']);
        $this->assertFileDoesNotExist($expiredPath);
        $this->assertFileExists($activePath);
    }

    public function testDashboardShowsDiscussionModuleOnlyWhenAssigned(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());

        $userId = $this->createPrivateUser($userRepository, 'family@example.com');
        $this->assertTrue($moduleRepository->setUserModules($userId, ['discussions'], 'admin@example.com'));

        $modules = $moduleRepository->activeModulesForUser($userId);
        $names = array_map(static fn (array $module): string => (string) $module['name'], $modules);

        $this->assertContains('Discussions', $names);
        $this->assertNotContains('Documents', $names);
    }

    public function testAuthorizedMemberCanRenderDiscussionScreens(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $discussionRepository = new DiscussionRepository($database);
        $storage = $this->storage();
        $service = new DiscussionService($discussionRepository, $userRepository, $storage);

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');
        $this->assertTrue($moduleRepository->setUserModules($aliceId, ['discussions'], 'admin@example.com'));

        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $this->assertIsArray($service->sendMessage($aliceId, (int) $conversation['id'], 'Bonjour'));

        $controller = new PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'alice@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            discussionRepository: $discussionRepository,
            discussionAttachmentStorage: $storage,
            discussionService: $service,
            discussionRetentionService: new DiscussionRetentionService($discussionRepository, $storage)
        );

        $index = $controller->handle('discussion_index', $this->request('GET', '/private/discussions'));
        $this->assertSame(200, $index->status);
        $this->assertStringContainsString('Nouvelle discussion', $index->body);
        $this->assertStringContainsString('Conversations', $index->body);

        $GLOBALS['csp_nonce'] = 'testnonce';
        $detail = $controller->handle(
            'discussion_conversation',
            $this->request('GET', '/private/discussions/' . (int) $conversation['id']),
            ['conversationId' => (int) $conversation['id']]
        );

        $this->assertSame(200, $detail->status);
        $this->assertStringContainsString('Envoyer un message', $detail->body);
        $this->assertStringContainsString('Bonjour', $detail->body);
        $this->assertStringContainsString('nonce="testnonce"', $detail->body);
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    /**
     * @return array{name:string,tmp_name:string,size:int}
     */
    private function createUpload(string $name, string $content): array
    {
        $path = $this->storageRoot . '/' . bin2hex(random_bytes(4)) . '-' . $name;
        file_put_contents($path, $content);

        return [
            'name' => $name,
            'tmp_name' => $path,
            'size' => strlen($content),
        ];
    }

    private function storage(): DiscussionAttachmentStorage
    {
        return new DiscussionAttachmentStorage(
            $this->storageRoot,
            1024 * 1024,
            ['txt', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf'],
            ['text/plain', 'image/png', 'image/jpeg', 'image/webp', 'image/gif', 'application/pdf']
        );
    }

    private function privateAuth(PrivateUserRepository $userRepository, string $email): PrivateAuth
    {
        $session = new PrivateSession($this->sessionName);
        $auth = new PrivateAuth($session, null, $userRepository);
        $this->assertTrue($auth->login($email, 'StrongPassword1!', '127.0.0.1'));
        $this->assertTrue($auth->isAuthenticated());

        return $auth;
    }

    private function request(string $method, string $uri): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            [],
            [],
            [],
            ['Host' => '127.0.0.1:8000']
        );
    }

    private function removeDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }
}
