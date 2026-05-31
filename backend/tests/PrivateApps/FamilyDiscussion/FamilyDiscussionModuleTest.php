<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\FamilyDiscussion;

use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivateApps\FamilyDiscussion\Retention\DiscussionRetentionService;
use Caramagnols\PrivateApps\FamilyDiscussion\Realtime\ConversationEventStream;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionAccessPolicy;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionEventService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionService;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionTimelineService;
use Caramagnols\Http\Request;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PDO;
use PHPUnit\Framework\TestCase;

final class FamilyDiscussionModuleTest extends TestCase
{
    use EditorialSqlTestTrait;

    private array $previousPrivateConfig = [];
    private string $storageRoot = '';
    private string $sessionName = '';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
    }

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
            'attachment_encryption_key' => 'base64:' . base64_encode(str_repeat('d', 32)),
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

        $this->assertNull($service->sendMessage($aliceId, $conversationId, 'Bonjour Bob'));

        $message = $service->sendMessage($aliceId, $conversationId, 'Bonjour Bob', [], $this->encryptedPayload());
        $this->assertIsArray($message);

        $bobMessages = $service->listMessages($conversationId, $bobId);
        $this->assertCount(1, $bobMessages);
        $this->assertSame('', $bobMessages[0]['body']);
        $this->assertSame('client_aes_gcm_v1', $bobMessages[0]['encryptionMode']);

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
        $message = $service->sendMessage(
            $aliceId,
            $conversationId,
            'Voir le fichier',
            [
                'discussion_files' => [
                    'name' => [$activeUpload['name']],
                    'tmp_name' => [$activeUpload['tmp_name']],
                    'size' => [$activeUpload['size']],
                    'error' => [UPLOAD_ERR_OK],
                    'type' => ['text/plain'],
                ],
            ],
            $this->encryptedPayload()
        );
        $this->assertIsArray($message);
        $this->assertCount(1, $message['attachments']);

        $attachment = $message['attachments'][0];
        $attachmentId = (string) $attachment['attachmentId'];
        $this->assertIsArray($repository->findAttachmentForUser($attachmentId, $bobId));
        $this->assertNull($repository->findAttachmentForUser($attachmentId, $outsiderId));
        $activePath = $storage->absolutePath((string) $attachment['storagePath']);
        $this->assertIsString($activePath);
        $this->assertFileExists($activePath);
        $this->assertTrue($storage->isEncryptedStoredFile((string) $attachment['storagePath']));
        $storedActiveContent = file_get_contents($activePath);
        $this->assertIsString($storedActiveContent);
        $this->assertStringNotContainsString('piece jointe active', $storedActiveContent);
        $this->assertSame('piece jointe active', $storage->read((string) $attachment['storagePath']));

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

        $retention = new DiscussionRetentionService($repository, $storage);
        $dryRun = $retention->purgeExpiredForUser($bobId, 200, true);

        $this->assertSame(1, $dryRun['messages']);
        $this->assertSame(1, $dryRun['attachments']);
        $this->assertTrue($dryRun['dryRun']);
        $this->assertFileExists($expiredPath);
        $messageBeforePurge = $repository->findMessageById((int) $expiredMessage['id']);
        $this->assertIsArray($messageBeforePurge);
        $this->assertSame('active', $messageBeforePurge['purgeStatus']);

        $result = $retention->purgeExpiredForUser($bobId);

        $this->assertSame(1, $result['messages']);
        $this->assertSame(1, $result['attachments']);
        $this->assertFalse($result['dryRun']);
        $purgedMessage = $repository->findMessageById((int) $expiredMessage['id']);
        $this->assertIsArray($purgedMessage);
        $this->assertSame('', $purgedMessage['body']);
        $this->assertSame('purged', $purgedMessage['purgeStatus']);
        $this->assertFileDoesNotExist($expiredPath);
        $this->assertFileExists($activePath);
    }

    public function testEncryptedTextMessagesStoreOnlyCiphertext(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $repository = new DiscussionRepository($database);
        $service = new DiscussionService($repository, $userRepository, $this->storage());

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');

        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $conversationId = (int) $conversation['id'];

        $ciphertext = base64_encode(random_bytes(48));
        $metadata = json_encode([
            'algorithm' => 'AES-GCM',
            'iv' => base64_encode(random_bytes(12)),
            'version' => 1,
        ]);
        $this->assertIsString($metadata);

        $message = $service->sendMessage($aliceId, $conversationId, 'Texte confidentiel', [], [
            'mode' => 'client_aes_gcm_v1',
            'payload' => $ciphertext,
            'metadata' => $metadata,
        ]);
        $this->assertIsArray($message);
        $this->assertSame('', $message['body']);
        $this->assertSame('client_aes_gcm_v1', $message['encryptionMode']);
        $this->assertSame($ciphertext, $message['encryptedPayload']);
        $this->assertSame($metadata, $message['encryptionMetadata']);

        $statement = $database->pdo()->prepare(sprintf(
            'SELECT `body`, `body_format`, `encrypted_payload` FROM `%s` WHERE `id` = :id',
            $database->table('discussion_messages')
        ));
        $statement->execute(['id' => (int) $message['id']]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertNull($row['body']);
        $this->assertSame('encrypted', $row['body_format']);
        $this->assertSame($ciphertext, $row['encrypted_payload']);

        $messages = $service->listMessages($conversationId, $bobId);
        $this->assertCount(1, $messages);
        $this->assertSame('', $messages[0]['body']);
        $this->assertSame($ciphertext, $messages[0]['encryptedPayload']);

        $conversations = $service->listConversations($bobId);
        $this->assertSame('[message chiffre]', $conversations[0]['lastBody'] ?? '');
    }

    public function testConversationCryptoKeysAreScopedToParticipantDevices(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $repository = new DiscussionRepository($database);
        $service = new DiscussionService($repository, $userRepository, $this->storage());

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');
        $outsiderId = $this->createPrivateUser($userRepository, 'outsider@example.com');

        $aliceDevice = $repository->registerCryptoDevice(
            $aliceId,
            'alice-device-0001',
            $this->publicKeyJwk('alice-key'),
            'Alice'
        );
        $bobDevice = $repository->registerCryptoDevice(
            $bobId,
            'bob-device-000001',
            $this->publicKeyJwk('bob-key'),
            'Bob'
        );
        $outsiderDevice = $repository->registerCryptoDevice(
            $outsiderId,
            'outsider-device1',
            $this->publicKeyJwk('outsider-key'),
            'Outsider'
        );
        $this->assertIsArray($aliceDevice);
        $this->assertIsArray($bobDevice);
        $this->assertIsArray($outsiderDevice);

        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $conversationId = (int) $conversation['id'];

        $inserted = $repository->upsertConversationKeys($conversationId, $aliceId, [
            [
                'privateUserId' => $aliceId,
                'deviceId' => 'alice-device-0001',
                'encryptedKey' => base64_encode(random_bytes(48)),
            ],
            [
                'privateUserId' => $bobId,
                'deviceId' => 'bob-device-000001',
                'encryptedKey' => base64_encode(random_bytes(48)),
            ],
            [
                'privateUserId' => $outsiderId,
                'deviceId' => 'outsider-device1',
                'encryptedKey' => base64_encode(random_bytes(48)),
            ],
        ]);

        $this->assertSame(2, $inserted);
        $this->assertSame(2, $repository->countConversationKeys($conversationId));
        $this->assertCount(1, $repository->listConversationKeysForUser($conversationId, $aliceId));
        $this->assertCount(1, $repository->listConversationKeysForUser($conversationId, $bobId));
        $this->assertSame([], $repository->listConversationKeysForUser($conversationId, $outsiderId));
    }

    public function testMessagesAreIdempotentAndTimelineUsesCursorPagination(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $repository = new DiscussionRepository($database);
        $service = new DiscussionService($repository, $userRepository, $this->storage());
        $timeline = new DiscussionTimelineService($repository);

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');
        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $conversationId = (int) $conversation['id'];

        $first = $service->sendMessage(
            $aliceId,
            $conversationId,
            'Message stable',
            [],
            $this->encryptedPayload(),
            'client-message-0001'
        );
        $second = $service->sendMessage(
            $aliceId,
            $conversationId,
            'Message stable retry',
            [],
            $this->encryptedPayload(),
            'client-message-0001'
        );
        $this->assertIsArray($first);
        $this->assertIsArray($second);
        $this->assertSame((int) $first['id'], (int) $second['id']);
        $this->assertTrue((bool) ($second['idempotentReplay'] ?? false));

        for ($index = 2; $index <= 60; ++$index) {
            $message = $service->sendMessage(
                $aliceId,
                $conversationId,
                'Message ' . $index,
                [],
                $this->encryptedPayload(),
                sprintf('client-message-%04d', $index)
            );
            $this->assertIsArray($message);
        }

        $latest = $timeline->timeline($conversationId, $bobId, limit: 10);
        $this->assertCount(10, $latest['messages']);
        $this->assertTrue($latest['hasMoreBefore']);
        $beforeCursor = (int) ($latest['cursors']['before'] ?? 0);
        $older = $timeline->timeline($conversationId, $bobId, beforeMessageId: $beforeCursor, limit: 10);
        $this->assertCount(10, $older['messages']);
        $this->assertLessThan($beforeCursor, (int) $older['messages'][2]['id']);

        $afterCursor = (int) ($older['cursors']['after'] ?? 0);
        $newer = $timeline->timeline($conversationId, $bobId, afterMessageId: $afterCursor, limit: 10);
        $this->assertNotEmpty($newer['messages']);
        $this->assertGreaterThan($afterCursor, (int) $newer['messages'][0]['id']);
    }

    public function testConversationEventsAreMinimalAndStreamedOnlyToParticipants(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $repository = new DiscussionRepository($database);
        $eventService = new DiscussionEventService($repository);
        $service = new DiscussionService($repository, $userRepository, $this->storage(), null, null, $eventService);

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');
        $outsiderId = $this->createPrivateUser($userRepository, 'outsider@example.com');
        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $conversationId = (int) $conversation['id'];

        $message = $service->sendMessage(
            $aliceId,
            $conversationId,
            'Texte non journalise',
            [],
            $this->encryptedPayload(),
            'client-message-event-1',
            'request-event-1'
        );
        $this->assertIsArray($message);

        $events = $eventService->eventsAfter($conversationId, $bobId);
        $this->assertCount(1, $events);
        $this->assertSame('message.created', $events[0]['eventType']);
        $this->assertSame(['messageId', 'attachmentCount', 'encrypted'], array_keys($events[0]['payload']));
        $encodedEvent = json_encode($events[0], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Texte non journalise', $encodedEvent);
        $this->assertSame([], $eventService->eventsAfter($conversationId, $outsiderId));

        $stream = new ConversationEventStream($eventService);
        $response = $stream->response($conversationId, $bobId);
        $this->assertSame(200, $response->status);
        $this->assertSame('text/event-stream; charset=UTF-8', $response->headers['Content-Type'] ?? null);
        $this->assertStringContainsString('event: message.created', $response->body);
        $this->assertStringNotContainsString('Texte non journalise', $response->body);

        $resumeResponse = $stream->response($conversationId, $bobId, (int) $events[0]['id']);
        $this->assertStringContainsString(': keepalive', $resumeResponse->body);
        $this->assertStringNotContainsString('event: message.created', $resumeResponse->body);

        $moduleRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $this->assertTrue($moduleRepository->setUserModules($outsiderId, ['discussions'], 'admin@example.com'));
        $controller = new PrivatePortalController(
            auth: $this->privateAuth($userRepository, 'outsider@example.com'),
            privateUserRepository: $userRepository,
            modulePermissionRepository: $moduleRepository,
            discussionRepository: $repository,
            discussionAttachmentStorage: $this->storage(),
            discussionService: $service,
            discussionRetentionService: new DiscussionRetentionService($repository, $this->storage())
        );
        $forbidden = $controller->handle(
            'discussion_api_events',
            $this->request('GET', '/private/discussions/api/conversations/' . $conversationId . '/events'),
            ['conversationId' => $conversationId]
        );
        $this->assertSame(403, $forbidden->status);
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
        $service = new DiscussionService($discussionRepository, $userRepository, $storage, null, $moduleRepository);

        $aliceId = $this->createPrivateUser($userRepository, 'alice@example.com');
        $bobId = $this->createPrivateUser($userRepository, 'bob@example.com');
        $outsiderId = $this->createPrivateUser($userRepository, 'outsider@example.com');
        $inviteHash = password_hash('PendingPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($inviteHash);
        $this->assertIsInt($userRepository->create('pending@example.com', $inviteHash, 'invited'));
        $this->assertTrue($moduleRepository->setUserModules($aliceId, ['discussions'], 'admin@example.com'));
        $this->assertTrue($moduleRepository->setUserModules($bobId, ['discussions'], 'admin@example.com'));

        $conversation = $service->createDirectConversation($aliceId, $bobId);
        $this->assertIsArray($conversation);
        $this->assertNull($service->createDirectConversation($aliceId, $outsiderId));
        $this->assertIsArray($service->sendMessage($aliceId, (int) $conversation['id'], 'Bonjour', [], $this->encryptedPayload()));

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
        $this->assertStringContainsString('Chiffrement des discussions', $index->body);
        $this->assertStringContainsString('Nouvelle discussion', $index->body);
        $this->assertStringContainsString('name="recipient_ids[]"', $index->body);
        $this->assertStringContainsString('bob@example.com', $index->body);
        $this->assertStringNotContainsString('outsider@example.com', $index->body);
        $this->assertStringNotContainsString('pending@example.com', $index->body);
        $this->assertStringContainsString('Conversations', $index->body);
        $this->assertStringNotContainsString('Tableau de bord discussions', $index->body);
        $this->assertStringNotContainsString('Conversation directe #', $index->body);

        $post = $controller->handle(
            'discussion_index',
            $this->request('POST', '/private/discussions', [
                'csrf_token' => csrf_token('private_discussions'),
                'type' => 'direct',
                'recipient_ids' => [$bobId],
            ])
        );
        $this->assertSame(302, $post->status);
        $this->assertSame('/private/discussions/' . (int) $conversation['id'], $post->headers['Location'] ?? null);

        $GLOBALS['csp_nonce'] = 'testnonce';
        $detail = $controller->handle(
            'discussion_conversation',
            $this->request('GET', '/private/discussions/' . (int) $conversation['id']),
            ['conversationId' => (int) $conversation['id']]
        );

        $this->assertSame(200, $detail->status);
        $this->assertStringContainsString('Chiffrement des discussions', $detail->body);
        $this->assertStringContainsString('Envoyer un message', $detail->body);
        $this->assertStringContainsString('bob@example.com', $detail->body);
        $this->assertStringContainsString('data-encrypted-body', $detail->body);
        $this->assertStringContainsString('data-discussion-last-message="1"', $detail->body);
        $this->assertStringNotContainsString('Supprimer mes messages', $detail->body);
        $this->assertStringNotContainsString('Confirmer avec SUPPRIMER', $detail->body);
        $this->assertStringContainsString('nonce="testnonce"', $detail->body);

        $payload = $this->encryptedPayload();
        $messagePost = $controller->handle(
            'discussion_conversation',
            $this->request('POST', '/private/discussions/' . (int) $conversation['id'], [
                'csrf_token' => csrf_token('private_discussions'),
                'action' => 'send_message',
                'body' => 'Nouveau message',
                'encryption_mode' => $payload['mode'],
                'encrypted_payload' => $payload['payload'],
                'encryption_metadata' => $payload['metadata'],
            ]),
            ['conversationId' => (int) $conversation['id']]
        );
        $this->assertSame(302, $messagePost->status);
        $this->assertSame(
            '/private/discussions/' . (int) $conversation['id'] . '?notice=sent#discussion-message-last',
            $messagePost->headers['Location'] ?? null
        );

        $detailNotice = $controller->handle(
            'discussion_conversation',
            $this->request('GET', '/private/discussions/' . (int) $conversation['id'] . '?notice=sent', [], ['notice' => 'sent']),
            ['conversationId' => (int) $conversation['id']]
        );
        $this->assertSame(200, $detailNotice->status);
        $this->assertStringContainsString('data-private-toast', $detailNotice->body);
        $this->assertStringContainsString('Message envoyé.', $detailNotice->body);
    }

    /**
     * @return array{mode:string,payload:string,metadata:string}
     */
    private function encryptedPayload(): array
    {
        $metadata = json_encode([
            'algorithm' => 'AES-GCM',
            'iv' => base64_encode(random_bytes(12)),
            'version' => 1,
        ], JSON_THROW_ON_ERROR);

        return [
            'mode' => 'client_aes_gcm_v1',
            'payload' => base64_encode(random_bytes(48)),
            'metadata' => $metadata,
        ];
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }

    private function publicKeyJwk(string $keyId): string
    {
        return json_encode([
            'kty' => 'RSA',
            'alg' => 'RSA-OAEP-256',
            'kid' => $keyId,
            'n' => rtrim(strtr(base64_encode(random_bytes(128)), '+/', '-_'), '='),
            'e' => 'AQAB',
            'ext' => true,
            'key_ops' => ['encrypt'],
        ], JSON_THROW_ON_ERROR);
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

    /**
     * @param array<string, mixed> $post
     */
    private function request(string $method, string $uri, array $post = [], array $query = []): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            $query,
            $post,
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
