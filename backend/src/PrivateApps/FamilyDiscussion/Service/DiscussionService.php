<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
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
        private readonly ?AppEventLogger $eventLogger = null,
        private readonly ?PrivateModulePermissionRepository $modulePermissionRepository = null
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
            fn (array $member): bool => (int) ($member['id'] ?? 0) !== $currentUserId
                && $this->hasDiscussionAccess((int) ($member['id'] ?? 0))
        ));
    }

    public function createDirectConversation(int $actorId, int $recipientId): ?array
    {
        if (
            $actorId <= 0
            || $recipientId <= 0
            || $actorId === $recipientId
            || !$this->hasDiscussionAccess($actorId)
            || !$this->isActiveUser($recipientId)
            || !$this->hasDiscussionAccess($recipientId)
        ) {
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
        if ($actorId <= 0 || !$this->hasDiscussionAccess($actorId)) {
            return null;
        }

        $memberIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $memberIds),
            fn (int $id): bool => $id > 0
                && $id !== $actorId
                && $this->isActiveUser($id)
                && $this->hasDiscussionAccess($id)
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
        if (!$this->hasDiscussionAccess($actorId) || !$policy->canManageMembers($conversationId, $actorId)) {
            $this->logAccessDenied($actorId, $conversationId);
            return false;
        }

        $memberIds = array_values(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $memberIds),
            fn (int $id): bool => $id > 0
                && $this->isActiveUser($id)
                && $this->hasDiscussionAccess($id)
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

    public function updateGroupTitle(int $actorId, int $conversationId, string $title): bool
    {
        $conversation = $this->repository->findConversationForUser($conversationId, $actorId);
        if (
            !is_array($conversation)
            || ($conversation['type'] ?? '') !== 'group'
            || (int) ($conversation['createdByPrivateUserId'] ?? 0) !== $actorId
        ) {
            $this->logAccessDenied($actorId, $conversationId);
            return false;
        }

        $updated = $this->repository->updateGroupTitle($conversationId, $actorId, $title);
        if ($updated) {
            $this->log('private.discussion.group.title_updated', [
                'conversation_id' => $conversationId,
            ]);
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $encryption
     */
    public function sendMessage(
        int $actorId,
        int $conversationId,
        string $body,
        array $uploadedFiles = [],
        array $encryption = []
    ): ?array {
        $policy = new DiscussionAccessPolicy($this->repository);
        if (!$policy->canSendMessage($conversationId, $actorId)) {
            $this->logAccessDenied($actorId, $conversationId);
            return null;
        }

        $body = $this->normalizeBody($body);
        $encryptionPayload = $this->normalizeEncryptionPayload($encryption);
        $uploadedFiles = array_slice($this->normalizeUploadedFiles($uploadedFiles), 0, $this->maxAttachmentsPerMessage);
        if ($body !== '' && $encryptionPayload === null) {
            return null;
        }
        if ($body === '' && $uploadedFiles === [] && $encryptionPayload === null) {
            return null;
        }

        $expiresAt = $this->expiresAt();
        $message = $this->repository->createMessage(
            $conversationId,
            $actorId,
            $encryptionPayload === null ? $body : '',
            $expiresAt,
            is_array($encryptionPayload) ? (string) $encryptionPayload['mode'] : 'none',
            is_array($encryptionPayload) ? (string) $encryptionPayload['payload'] : null,
            is_array($encryptionPayload) ? (string) $encryptionPayload['metadata'] : null
        );
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

        if ($body === '' && $encryptionPayload === null && $attachments === []) {
            $this->repository->purgeMessageContent((int) $message['id']);
            return null;
        }

        $message['attachments'] = $attachments;
        $this->log('private.discussion.message.sent', [
            'conversation_id' => $conversationId,
            'message_id' => (int) $message['id'],
            'attachments' => count($attachments),
            'encrypted' => ($message['encryptionMode'] ?? 'none') !== 'none',
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

    public function deleteMessage(int $actorId, int $messageId): bool
    {
        $message = $this->repository->findMessageForUser($messageId, $actorId);
        if (!is_array($message)) {
            return false;
        }

        $conversationId = (int) ($message['conversationId'] ?? 0);
        $senderId = (int) ($message['senderPrivateUserId'] ?? 0);
        if ($senderId !== $actorId && $this->repository->userRoleInConversation($conversationId, $actorId) !== 'owner') {
            $this->logAccessDenied($actorId, $conversationId);
            return false;
        }

        foreach ($this->repository->listActiveAttachmentsForMessage($messageId) as $attachment) {
            $this->deleteAttachmentFiles($attachment);
            $this->repository->purgeAttachment((int) ($attachment['id'] ?? 0));
        }

        $deleted = $this->repository->purgeMessageContent($messageId);
        if ($deleted) {
            $this->log('private.discussion.message.deleted', [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'private_user_id' => $actorId,
            ]);
        }

        return $deleted;
    }

    public function deleteAttachment(int $actorId, string $attachmentId): bool
    {
        $attachment = $this->repository->findAttachmentForUser($attachmentId, $actorId);
        if (!is_array($attachment)) {
            return false;
        }

        $conversationId = (int) ($attachment['conversationId'] ?? 0);
        $senderId = (int) ($attachment['senderPrivateUserId'] ?? 0);
        if ($senderId !== $actorId && $this->repository->userRoleInConversation($conversationId, $actorId) !== 'owner') {
            $this->logAccessDenied($actorId, $conversationId);
            return false;
        }

        $this->deleteAttachmentFiles($attachment);
        $deleted = $this->repository->purgeAttachment((int) ($attachment['id'] ?? 0));
        if ($deleted) {
            $this->log('private.discussion.attachment.deleted', [
                'conversation_id' => $conversationId,
                'attachment_id' => $attachmentId,
                'private_user_id' => $actorId,
            ]);
        }

        return $deleted;
    }

    public function deleteOwnConversationData(int $actorId, int $conversationId): int
    {
        if (!$this->repository->isParticipant($conversationId, $actorId)) {
            $this->logAccessDenied($actorId, $conversationId);
            return 0;
        }

        $deleted = 0;
        foreach ($this->repository->listActiveMessageIdsForSender($conversationId, $actorId) as $messageId) {
            if ($this->deleteMessage($actorId, $messageId)) {
                ++$deleted;
            }
        }

        return $deleted;
    }

    private function isActiveUser(int $userId): bool
    {
        $user = $this->userRepository->findById($userId);

        return is_array($user) && strtolower((string) ($user['status'] ?? '')) === 'active';
    }

    private function hasDiscussionAccess(int $userId): bool
    {
        return $this->modulePermissionRepository === null
            || $this->modulePermissionRepository->userHasModuleAccess($userId, 'discussions');
    }

    /**
     * @param array<string, mixed> $attachment
     */
    private function deleteAttachmentFiles(array $attachment): void
    {
        $storagePath = is_string($attachment['storagePath'] ?? null) ? (string) $attachment['storagePath'] : '';
        $previewStoragePath = is_string($attachment['previewStoragePath'] ?? null) ? (string) $attachment['previewStoragePath'] : '';
        if ($storagePath !== '') {
            $this->attachmentStorage->delete($storagePath);
        }
        if ($previewStoragePath !== '' && $previewStoragePath !== $storagePath) {
            $this->attachmentStorage->delete($previewStoragePath);
        }
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

    /**
     * @param array<string, mixed> $encryption
     * @return array{mode:string,payload:string,metadata:string}|null
     */
    private function normalizeEncryptionPayload(array $encryption): ?array
    {
        $mode = is_string($encryption['mode'] ?? null)
            ? strtolower(trim((string) $encryption['mode']))
            : '';
        $payload = is_string($encryption['payload'] ?? null) ? trim((string) $encryption['payload']) : '';
        $metadata = is_string($encryption['metadata'] ?? null) ? trim((string) $encryption['metadata']) : '';
        if ($mode !== 'client_aes_gcm_v1' || $payload === '' || $metadata === '') {
            return null;
        }

        if (strlen($payload) > 50000 || preg_match('/\A[A-Za-z0-9+\/=_-]+\z/', $payload) !== 1) {
            return null;
        }

        $decoded = json_decode($metadata, true);
        if (
            !is_array($decoded)
            || ($decoded['algorithm'] ?? '') !== 'AES-GCM'
            || !is_string($decoded['iv'] ?? null)
            || preg_match('/\A[A-Za-z0-9+\/=_-]+\z/', (string) $decoded['iv']) !== 1
        ) {
            return null;
        }

        return ['mode' => $mode, 'payload' => $payload, 'metadata' => $metadata];
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
