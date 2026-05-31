<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion\Realtime;

use Caramagnols\Http\Response;
use Caramagnols\PrivateApps\FamilyDiscussion\Service\DiscussionEventService;

final class ConversationEventStream
{
    public function __construct(private readonly DiscussionEventService $eventService)
    {
    }

    public function response(int $conversationId, int $userId, int $afterEventId = 0, int $limit = 100): Response
    {
        $events = $this->eventService->eventsAfter($conversationId, $userId, $afterEventId, $limit);
        $body = "retry: 10000\n\n";
        if ($events === []) {
            $body .= ": keepalive\n\n";
        }

        foreach ($events as $event) {
            $encoded = json_encode($this->publicEvent($event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                continue;
            }

            $body .= 'id: ' . (int) ($event['id'] ?? 0) . "\n";
            $body .= 'event: ' . (string) ($event['eventType'] ?? 'message') . "\n";
            $body .= 'data: ' . $encoded . "\n\n";
        }

        return new Response(200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ], $body);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>
     */
    private function publicEvent(array $event): array
    {
        return [
            'id' => (int) ($event['id'] ?? 0),
            'conversationId' => (int) ($event['conversationId'] ?? 0),
            'type' => is_string($event['eventType'] ?? null) ? (string) $event['eventType'] : '',
            'payload' => is_array($event['payload'] ?? null) ? $event['payload'] : [],
            'createdAt' => is_string($event['createdAt'] ?? null) ? (string) $event['createdAt'] : '',
        ];
    }
}
