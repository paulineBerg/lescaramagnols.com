<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Retention;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;

final class DiscussionRetentionService
{
    public function __construct(
        private readonly DiscussionRepository $repository,
        private readonly DiscussionAttachmentStorage $storage,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    /**
     * @return array{messages:int,attachments:int,dryRun:bool}
     */
    public function purgeExpiredForUser(int $userId, int $limit = 200, bool $dryRun = false): array
    {
        if ($userId <= 0) {
            return ['messages' => 0, 'attachments' => 0, 'dryRun' => $dryRun];
        }

        $attachments = $this->repository->listExpiredAttachmentsForUser($userId, $limit);
        $messageIds = $this->repository->listExpiredMessageIdsForUser($userId, $limit);
        if ($dryRun) {
            return ['messages' => count($messageIds), 'attachments' => count($attachments), 'dryRun' => true];
        }

        $runId = $this->repository->createRetentionRun($userId, 'user_open');

        return $this->runPurge($runId, $attachments, $messageIds, $userId);
    }

    /**
     * @return array{messages:int,attachments:int,dryRun:bool}
     */
    public function purgeExpiredScheduled(int $limit = 1000, bool $dryRun = false): array
    {
        $attachments = $this->repository->listExpiredAttachmentsAll($limit);
        $messageIds = $this->repository->listExpiredMessageIdsAll($limit);
        if ($dryRun) {
            return ['messages' => count($messageIds), 'attachments' => count($attachments), 'dryRun' => true];
        }

        $runId = $this->repository->createRetentionRun(null, 'scheduled');

        return $this->runPurge($runId, $attachments, $messageIds, null);
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @param array<int, int> $messageIds
     * @return array{messages:int,attachments:int,dryRun:bool}
     */
    private function runPurge(int $runId, array $attachments, array $messageIds, ?int $userId): array
    {
        $purgedAttachments = 0;
        $purgedMessages = 0;

        foreach ($attachments as $attachment) {
            $storagePath = is_string($attachment['storagePath'] ?? null) ? (string) $attachment['storagePath'] : '';
            $previewStoragePath = is_string($attachment['previewStoragePath'] ?? null) ? (string) $attachment['previewStoragePath'] : '';
            $thumbnailStoragePath = is_string($attachment['thumbnailStoragePath'] ?? null) ? (string) $attachment['thumbnailStoragePath'] : '';
            if ($storagePath !== '') {
                $this->storage->delete($storagePath);
            }
            if ($previewStoragePath !== '' && $previewStoragePath !== $storagePath) {
                $this->storage->delete($previewStoragePath);
            }
            if ($thumbnailStoragePath !== '' && !in_array($thumbnailStoragePath, [$storagePath, $previewStoragePath], true)) {
                $this->storage->delete($thumbnailStoragePath);
            }

            $rowId = is_numeric($attachment['id'] ?? null) ? (int) $attachment['id'] : 0;
            if ($this->repository->purgeAttachment($rowId)) {
                ++$purgedAttachments;
            }
        }

        foreach ($messageIds as $messageId) {
            if ($this->repository->purgeMessageContent($messageId)) {
                ++$purgedMessages;
            }
        }

        $this->repository->finishRetentionRun($runId, $purgedMessages, $purgedAttachments);
        $this->logPurge($userId, $purgedMessages, $purgedAttachments);

        return ['messages' => $purgedMessages, 'attachments' => $purgedAttachments, 'dryRun' => false];
    }

    private function logPurge(?int $userId, int $messages, int $attachments): void
    {
        if ($this->eventLogger === null || ($messages === 0 && $attachments === 0)) {
            return;
        }

        $this->eventLogger->security('private.discussion.retention.purged', [
            'private_user_id' => $userId ?? 0,
            'messages' => $messages,
            'attachments' => $attachments,
        ], 'info');
    }
}
