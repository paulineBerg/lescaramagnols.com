<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Service;

use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;

final class DiscussionAccessPolicy
{
    public function __construct(private readonly DiscussionRepository $repository)
    {
    }

    public function canReadConversation(int $conversationId, int $userId): bool
    {
        return $this->repository->isParticipant($conversationId, $userId);
    }

    public function canSendMessage(int $conversationId, int $userId): bool
    {
        return $this->canReadConversation($conversationId, $userId);
    }

    public function canManageMembers(int $conversationId, int $userId): bool
    {
        $conversation = $this->repository->findConversationForUser($conversationId, $userId);
        if (!is_array($conversation) || ($conversation['type'] ?? '') !== 'group') {
            return false;
        }

        return $this->repository->userRoleInConversation($conversationId, $userId) === 'owner';
    }
}
