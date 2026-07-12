<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\FamilyDiscussion;

final class PrivateAppManifest implements \Caramagnols\PrivatePortal\PrivateAppManifest
{
    public function migrationCode(): string
    {
        return 'family_discussion';
    }

    public function moduleCode(): string
    {
        return 'discussions';
    }

    public function moduleName(): string
    {
        return 'Discussions';
    }

    public function moduleDescription(): string
    {
        return 'Conversations, membres, messages, fichiers, clés, lectures et retention.';
    }

    public function modulePermissionCode(): string
    {
        return 'discussions';
    }

    public function migrationStatusCode(): string
    {
        return 'discussions';
    }

    public function title(): string
    {
        return 'FamilyDiscussion';
    }

    public function order(): int
    {
        return 3;
    }

    /**
     * @return array<int, string>
     */
    public function routeNames(): array
    {
        return [
            'discussion_index',
            'discussion_new',
            'discussion_api_conversations',
            'discussion_api_events',
            'discussion_api_client_events',
            'discussion_api_crypto_devices',
            'discussion_files',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'discussion_conversations',
            'discussion_conversation_members',
            'discussion_notification_preferences',
            'discussion_conversation_events',
            'discussion_messages',
            'discussion_message_reads',
            'discussion_message_attachments',
            'discussion_conversation_keys',
            'discussion_crypto_devices',
            'discussion_retention_runs',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Repository\\DiscussionRepository',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionService',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionAccessPolicy',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionMediaService',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionObservabilityService',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Attachment\\DiscussionAttachmentStorage',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Retention\\DiscussionRetentionService',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function testClasses(): array
    {
        return [
            'FamilyDiscussionModuleTest',
            'PrivatePortalPhaseCoverageTest',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.discussions.message_created',
            'private.discussions.message_deleted',
            'private.discussions.attachment_deleted',
            'private.discussions.retention_run',
            'private.discussion.media_scan.completed',
            'private.discussion.orphans_cleanup.completed',
            'private.discussion.stream_failed',
            'private.discussion.client_decrypt_failed',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function uiStates(): array
    {
        return ['empty', 'error', 'success'];
    }

    /**
     * @return array<int, string>
     */
    public function legacyRoutes(): array
    {
        return ['discussion PHP routes stay behind PrivateRouteResolver'];
    }

    public function notes(): string
    {
        return 'La retention cible reste 60 jours pour les messages/fichiers expirables.';
    }
}
