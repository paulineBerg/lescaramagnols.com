<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;

final class DiscussionEventService
{
    public function __construct(private readonly DiscussionRepository $repository)
    {
    }

    /**
     * @param array<string, mixed> $message
     */
    public function messageCreated(array $message, int $attachmentCount = 0, ?string $requestId = null): ?array
    {
        $messageId = $this->numeric($message['id'] ?? null);
        $conversationId = $this->numeric($message['conversationId'] ?? null);
        $actorId = $this->numeric($message['senderPrivateUserId'] ?? null);
        if ($messageId <= 0 || $conversationId <= 0 || $actorId <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'message.created',
            [
                'messageId' => $messageId,
                'attachmentCount' => max(0, $attachmentCount),
                'encrypted' => ($message['encryptionMode'] ?? 'none') !== 'none',
            ],
            'message:' . $messageId . ':created',
            $requestId
        );
    }

    public function messageDeleted(int $conversationId, int $actorId, int $messageId, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0 || $messageId <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'message.deleted',
            ['messageId' => $messageId],
            'message:' . $messageId . ':deleted',
            $requestId
        );
    }

    public function attachmentDeleted(int $conversationId, int $actorId, int $attachmentRowId, int $messageId, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0 || $attachmentRowId <= 0 || $messageId <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'attachment.deleted',
            ['attachmentRowId' => $attachmentRowId, 'messageId' => $messageId],
            'attachment:' . $attachmentRowId . ':deleted',
            $requestId
        );
    }

    public function read(int $conversationId, int $actorId, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'conversation.read',
            [],
            null,
            $requestId
        );
    }

    public function membersAdded(int $conversationId, int $actorId, int $count, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0 || $count <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'member.added',
            ['count' => $count],
            null,
            $requestId
        );
    }

    public function memberLeft(int $conversationId, int $actorId, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'member.left',
            [],
            'member:' . $actorId . ':left:' . $conversationId,
            $requestId
        );
    }

    public function cryptoDeviceAdded(int $conversationId, int $actorId, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'crypto.device.added',
            [],
            null,
            $requestId
        );
    }

    public function cryptoDeviceRevoked(int $conversationId, int $actorId, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'crypto.device.revoked',
            [],
            null,
            $requestId
        );
    }

    public function conversationKeysUpdated(int $conversationId, int $actorId, int $count, ?string $requestId = null): ?array
    {
        if ($conversationId <= 0 || $actorId <= 0 || $count <= 0) {
            return null;
        }

        return $this->repository->createConversationEvent(
            $conversationId,
            $actorId,
            'conversation.keys.updated',
            ['count' => $count],
            null,
            $requestId
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eventsAfter(int $conversationId, int $userId, int $afterEventId = 0, int $limit = 100): array
    {
        return $this->repository->listConversationEventsAfter($conversationId, $userId, $afterEventId, $limit);
    }

    private function numeric(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
