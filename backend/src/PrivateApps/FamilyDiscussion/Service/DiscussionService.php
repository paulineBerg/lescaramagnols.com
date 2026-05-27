<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;

final class DiscussionService
{
    private readonly int $retentionDays;
    private readonly int $maxMessageLength;
    private readonly int $maxAttachmentsPerMessage;

    public function __construct(
        private readonly DiscussionRepository $repository,
        private readonly PrivateUserRepository $userRepository,
        private readonly DiscussionAttachmentStorage $attachmentStorage,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
        $config = is_array(app_config('private.discussions', [])) ? (array) app_config('private.discussions') : [];
        $this->retentionDays = max(1, (int) ($config['retention_days'] ?? 60));
        $this->maxMessageLength = max(1, (int) ($config['max_message_length'] ?? 4000));
        $this->maxAttachmentsPerMessage = max(0, min(10, (int) ($config['max_attachments_per_message'] ?? 5)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConversations(int $userId): array
    {
        return $this->repository->listConversationsForUser($userId, 100);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveMembers(int $currentUserId): array
    {
        $members = $this->userRepository->listMembers('active', '', 200);

        return array_values(array_filter(
            $members,
            static fn (array $member): bool => (int) ($member['id'] ?? 0) !== $currentUserId
        ));
    }

    public function createDirectConversation(int $actorId, int $recipientId): ?array
    {
        if ($actorId <= 0 || $recipientId <= 0 || $actorId === $recipientId || !$this->isActiveUser($recipientId)) {
            return null;
        }

        $conversation = $this->repository->createDirectConversation($actorId, $recipientId);
        if (is_array($conversation)) {
            $this->log('private.discussion.conversation.created', [
                'conversation_id' => (int) ($conversation['id'] ?? 0),
                'type' => 'direct',
            ]);
        }

        return $conversation;
    }

    /**
     * @param array<int, int> $memberIds
     */
    public function createGroupConversation(int $actorId, string $title, array $memberIds): ?array
    {
        if ($actorId <= 0) {
            return null;
        }

        $memberIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $memberIds),
            fn (int $id): bool => $id > 0 && $id !== $actorId && $this->isActiveUser($id)
        ));
        if ($memberIds === []) {
            return null;
        }

        $conversation = $this->repository->createGroupConversation($actorId, $title, $memberIds);
        if (is_array($conversation)) {
            $this->log('private.discussion.conversation.created', [
                'conversation_id' => (int) ($conversation['id'] ?? 0),
                'type' => 'group',
            ]);
        }

        return $conversation;
    }

    /**
     * @param array<int, int> $memberIds
     */
    public function addMembers(int $actorId, int $conversationId, array $memberIds, DiscussionAccessPolicy $policy): bool
    {
        if (!$policy->canManageMembers($conversationId, $actorId)) {
            $this->logAccessDenied($actorId, $conversationId);
            return false;
        }

        $memberIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $memberIds),
            fn (int $id): bool => $id > 0 && $this->isActiveUser($id)
        ));

        $added = $this->repository->addMembers($conversationId, $memberIds);
        if ($added) {
            $this->log('private.discussion.group.member_added', [
                'conversation_id' => $conversationId,
                'count' => count($memberIds),
            ]);
        }

        return $added;
    }

    public function sendMessage(int $actorId, int $conversationId, string $body, array $uploadedFiles = []): ?array
    {
        $policy = new DiscussionAccessPolicy($this->repository);
        if (!$policy->canSendMessage($conversationId, $actorId)) {
            $this->logAccessDenied($actorId, $conversationId);
            return null;
        }

        $body = $this->normalizeBody($body);
        $uploadedFiles = array_slice($this->normalizeUploadedFiles($uploadedFiles), 0, $this->maxAttachmentsPerMessage);
        if ($body === '' && $uploadedFiles === []) {
            return null;
        }

        $expiresAt = $this->expiresAt();
        $message = $this->repository->createMessage($conversationId, $actorId, $body, $expiresAt);
        if (!is_array($message)) {
            return null;
        }

        $attachments = [];
        foreach ($uploadedFiles as $uploadedFile) {
            $metadata = $this->attachmentStorage->validateUploadedFile($uploadedFile);
            if ($metadata === null) {
                continue;
            }

            $attachmentId = $this->attachmentStorage->generateAttachmentId();
            $stored = $this->attachmentStorage->store($metadata, $attachmentId);
            if (!is_array($stored)) {
                continue;
            }

            $attachment = $this->repository->createAttachment(
                (int) $message['id'],
                (string) $stored['attachmentId'],
                (string) $stored['originalFilename'],
                (string) $stored['storagePath'],
                is_string($stored['previewStoragePath'] ?? null) ? (string) $stored['previewStoragePath'] : null,
                (string) $stored['mimeType'],
                (int) $stored['sizeBytes'],
                (string) $stored['sha256'],
                is_int($stored['width'] ?? null) ? $stored['width'] : null,
                is_int($stored['height'] ?? null) ? $stored['height'] : null,
                $expiresAt
            );
            if (is_array($attachment)) {
                $attachments[] = $attachment;
                $this->log('private.discussion.attachment.uploaded', [
                    'conversation_id' => $conversationId,
                    'message_id' => (int) $message['id'],
                    'attachment_id' => (string) $attachment['attachmentId'],
                    'size_bytes' => (int) $attachment['sizeBytes'],
                ]);
            }
        }

        $message['attachments'] = $attachments;
        $this->log('private.discussion.message.sent', [
            'conversation_id' => $conversationId,
            'message_id' => (int) $message['id'],
            'attachments' => count($attachments),
        ]);

        return $message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMessages(int $conversationId, int $userId, int $afterMessageId = 0): array
    {
        if (!$this->repository->isParticipant($conversationId, $userId)) {
            $this->logAccessDenied($userId, $conversationId);
            return [];
        }

        return $this->repository->listMessagesForUser($conversationId, $userId, $afterMessageId, 100);
    }

    public function markRead(int $conversationId, int $userId): bool
    {
        return $this->repository->markConversationRead($conversationId, $userId);
    }

    public function leaveConversation(int $conversationId, int $userId): bool
    {
        return $this->repository->leaveConversation($conversationId, $userId);
    }

    private function isActiveUser(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);

        return is_array($user) && strtolower((string) ($user['status'] ?? '')) === 'active';
    }

    /**
     * @param array<string, mixed> $files
     * @return array<int, array{name:string,tmp_name:string,size:int|string,error:int,type?:string}>
     */
    private function normalizeUploadedFiles(array $files): array
    {
        $raw = $files['discussion_files'] ?? $files['discussion_file'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        if (is_array($raw['name'] ?? null)) {
            $normalized = [];
            foreach ((array) $raw['name'] as $index => $name) {
                $normalized[] = [
                    'name' => is_string($name) ? $name : '',
                    'tmp_name' => is_string($raw['tmp_name'][$index] ?? null) ? (string) $raw['tmp_name'][$index] : '',
                    'size' => is_numeric($raw['size'][$index] ?? null) ? (int) $raw['size'][$index] : 0,
                    'error' => is_numeric($raw['error'][$index] ?? null) ? (int) $raw['error'][$index] : UPLOAD_ERR_NO_FILE,
                    'type' => is_string($raw['type'][$index] ?? null) ? (string) $raw['type'][$index] : '',
                ];
            }

            return $normalized;
        }

        return [[
            'name' => is_string($raw['name'] ?? null) ? (string) $raw['name'] : '',
            'tmp_name' => is_string($raw['tmp_name'] ?? null) ? (string) $raw['tmp_name'] : '',
            'size' => is_numeric($raw['size'] ?? null) ? (int) $raw['size'] : 0,
            'error' => is_numeric($raw['error'] ?? null) ? (int) $raw['error'] : UPLOAD_ERR_NO_FILE,
            'type' => is_string($raw['type'] ?? null) ? (string) $raw['type'] : '',
        ]];
    }

    private function normalizeBody(string $body): string
    {
        $body = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $body));
        if (strlen($body) > $this->maxMessageLength) {
            return substr($body, 0, $this->maxMessageLength);
        }

        return $body;
    }

    private function expiresAt(): string
    {
        return date('Y-m-d H:i:s', time() + ($this->retentionDays * 86400));
    }

    private function logAccessDenied(int $userId, int $conversationId): void
    {
        $this->log('private.discussion.access.denied', [
            'private_user_id' => $userId,
            'conversation_id' => $conversationId,
        ]);
    }

    private function log(string $event, array $context): void
    {
        if ($this->eventLogger === null) {
            return;
        }

        $this->eventLogger->security($event, $context, 'info');
    }
}
