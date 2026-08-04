<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Repository;

interface PreviewTicketRepositoryInterface
{
    public function create(int $projectId, int $privateUserId, int $ttlSeconds): string;
}
