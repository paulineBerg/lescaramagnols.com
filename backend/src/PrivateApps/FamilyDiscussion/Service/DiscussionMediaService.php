<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;

final class DiscussionMediaService
{
    private readonly int $maxUserStorageBytes;
    private readonly int $maxConversationStorageBytes;

    public function __construct(
        private readonly DiscussionRepository $repository,
        private readonly DiscussionAttachmentStorage $storage,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
        $config = is_array(app_config('private.discussions', [])) ? (array) app_config('private.discussions') : [];
        $this->maxUserStorageBytes = max(0, (int) ($config['max_user_storage_bytes'] ?? 256 * 1024 * 1024));
        $this->maxConversationStorageBytes = max(0, (int) ($config['max_conversation_storage_bytes'] ?? 512 * 1024 * 1024));
    }

    public function withinQuota(int $conversationId, int $userId, int $incomingBytes): bool
    {
        $incomingBytes = max(0, $incomingBytes);
        if ($conversationId <= 0 || $userId <= 0) {
            return false;
        }

        if (
            $this->maxUserStorageBytes > 0
            && $this->repository->attachmentUsageForUser($userId) + $incomingBytes > $this->maxUserStorageBytes
        ) {
            return false;
        }

        if (
            $this->maxConversationStorageBytes > 0
            && $this->repository->attachmentUsageForConversation($conversationId) + $incomingBytes > $this->maxConversationStorageBytes
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $attachment
     * @return array{status:string,updated:bool,error:string}
     */
    public function scanAttachment(array $attachment, bool $dryRun = false): array
    {
        $rowId = is_numeric($attachment['id'] ?? null) ? (int) $attachment['id'] : 0;
        $storagePath = is_string($attachment['storagePath'] ?? null) ? (string) $attachment['storagePath'] : '';
        $sha256 = is_string($attachment['sha256'] ?? null) ? strtolower(trim((string) $attachment['sha256'])) : '';
        $thumbnailStoragePath = is_string($attachment['thumbnailStoragePath'] ?? null) && trim((string) $attachment['thumbnailStoragePath']) !== ''
            ? (string) $attachment['thumbnailStoragePath']
            : (is_string($attachment['previewStoragePath'] ?? null) ? (string) $attachment['previewStoragePath'] : null);

        if ($rowId <= 0 || $storagePath === '') {
            return ['status' => 'blocked', 'updated' => false, 'error' => 'invalid_attachment'];
        }

        $content = $this->storage->read($storagePath);
        if ($content === null) {
            return $this->scanResult($rowId, 'blocked', 'read_failed', $thumbnailStoragePath, $dryRun);
        }
        if (!$this->storage->isEncryptedStoredFile($storagePath)) {
            return $this->scanResult($rowId, 'blocked', 'unencrypted_storage', $thumbnailStoragePath, $dryRun);
        }
        if ($sha256 !== '' && hash('sha256', $content) !== $sha256) {
            return $this->scanResult($rowId, 'blocked', 'checksum_mismatch', $thumbnailStoragePath, $dryRun);
        }

        return $this->scanResult($rowId, 'available', '', $thumbnailStoragePath, $dryRun);
    }

    /**
     * @return array{pending:int,available:int,blocked:int,dryRun:bool,items:array<int, array<string, scalar|null>>}
     */
    public function scanPendingAttachments(int $limit = 100, bool $dryRun = false): array
    {
        $attachments = $this->repository->listPendingScanAttachments($limit);
        $available = 0;
        $blocked = 0;
        $items = [];

        foreach ($attachments as $attachment) {
            $result = $this->scanAttachment($attachment, $dryRun);
            if ($result['status'] === 'available') {
                ++$available;
            } elseif ($result['status'] === 'blocked') {
                ++$blocked;
            }

            $items[] = [
                'id' => is_numeric($attachment['id'] ?? null) ? (int) $attachment['id'] : 0,
                'attachmentId' => is_string($attachment['attachmentId'] ?? null) ? (string) $attachment['attachmentId'] : '',
                'status' => $result['status'],
                'error' => $result['error'] !== '' ? $result['error'] : null,
            ];
        }

        $this->log('private.discussion.media_scan.completed', [
            'dry_run' => $dryRun,
            'pending' => count($attachments),
            'available' => $available,
            'blocked' => $blocked,
        ], $blocked > 0 ? 'warning' : 'info');

        return [
            'pending' => count($attachments),
            'available' => $available,
            'blocked' => $blocked,
            'dryRun' => $dryRun,
            'items' => $items,
        ];
    }

    /**
     * @return array{scanned:int,orphans:int,deleted:int,dryRun:bool,items:array<int, string>}
     */
    public function cleanupOrphans(int $limit = 5000, bool $dryRun = false): array
    {
        $storedFiles = $this->storage->listStoredFiles($limit);
        $referenced = array_flip($this->repository->attachmentStoragePaths());
        $orphans = [];
        $deleted = 0;

        foreach ($storedFiles as $storedFile) {
            if (isset($referenced[$storedFile])) {
                continue;
            }

            $orphans[] = $storedFile;
            if (!$dryRun && $this->storage->delete($storedFile)) {
                ++$deleted;
            }
        }

        $this->log('private.discussion.orphans_cleanup.completed', [
            'dry_run' => $dryRun,
            'scanned' => count($storedFiles),
            'orphans' => count($orphans),
            'deleted' => $deleted,
        ], count($orphans) > 0 ? 'warning' : 'info');

        return [
            'scanned' => count($storedFiles),
            'orphans' => count($orphans),
            'deleted' => $deleted,
            'dryRun' => $dryRun,
            'items' => array_slice($orphans, 0, min(200, $limit)),
        ];
    }

    /**
     * @return array{status:string,updated:bool,error:string}
     */
    private function scanResult(int $rowId, string $status, string $error, ?string $thumbnailStoragePath, bool $dryRun): array
    {
        $updated = false;
        if (!$dryRun) {
            $updated = $this->repository->markAttachmentAvailability(
                $rowId,
                $status,
                $error !== '' ? $error : null,
                $status === 'available' ? $thumbnailStoragePath : null
            );
        }

        return ['status' => $status, 'updated' => $updated, 'error' => $error];
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function log(string $event, array $context, string $severity): void
    {
        if ($this->eventLogger === null) {
            return;
        }

        $this->eventLogger->security($event, $context, $severity);
    }
}
