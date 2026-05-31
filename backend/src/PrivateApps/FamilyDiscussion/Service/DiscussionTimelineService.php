<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;

final class DiscussionTimelineService
{
    public function __construct(private readonly DiscussionRepository $repository)
    {
    }

    /**
     * @return array{messages: array<int, array<string, mixed>>, cursors: array{before: int|null, after: int|null}, hasMoreBefore: bool}
     */
    public function timeline(
        int $conversationId,
        int $userId,
        int $beforeMessageId = 0,
        int $afterMessageId = 0,
        int $limit = 100
    ): array {
        $limit = max(1, min(200, $limit));
        if ($beforeMessageId > 0) {
            $messages = $this->repository->findMessagesBefore($conversationId, $userId, $beforeMessageId, $limit);
        } elseif ($afterMessageId > 0) {
            $messages = $this->repository->findMessagesAfter($conversationId, $userId, $afterMessageId, $limit);
        } else {
            $messages = $this->repository->findConversationTimeline($conversationId, $userId, $limit);
        }

        $firstId = $this->messageId($messages[0] ?? null);
        $lastId = $this->messageId($messages[count($messages) - 1] ?? null);
        $hasMoreBefore = $firstId !== null
            && $this->repository->findMessagesBefore($conversationId, $userId, $firstId, 1) !== [];

        return [
            'messages' => $messages,
            'cursors' => [
                'before' => $firstId,
                'after' => $lastId,
            ],
            'hasMoreBefore' => $hasMoreBefore,
        ];
    }

    private function messageId(mixed $message): ?int
    {
        if (!is_array($message) || !is_numeric($message['id'] ?? null)) {
            return null;
        }

        $id = (int) $message['id'];

        return $id > 0 ? $id : null;
    }
}
