<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Caramagnols\PrivateApps\FamilyDiscussion\Attachment\DiscussionAttachmentStorage;
use Caramagnols\PrivateApps\FamilyDiscussion\Repository\DiscussionRepository;
use Caramagnols\PrivateApps\FamilyDiscussion\Retention\DiscussionRetentionService;

$limit = 1000;
foreach ($argv ?? [] as $argument) {
    if (preg_match('/\A--limit=([0-9]+)\z/', (string) $argument, $matches) === 1) {
        $limit = max(1, min(10000, (int) $matches[1]));
    }
}

$service = new DiscussionRetentionService(
    new DiscussionRepository(editorial_database()),
    DiscussionAttachmentStorage::fromAppConfig(),
    function_exists('app_event_logger') ? app_event_logger() : null
);

$result = $service->purgeExpiredScheduled($limit);

fwrite(STDOUT, sprintf(
    "FamilyDiscussion purge completed: %d messages, %d attachments.\n",
    (int) $result['messages'],
    (int) $result['attachments']
));
