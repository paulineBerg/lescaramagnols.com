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
        return 'family_discussion';
    }

    public function moduleName(): string
    {
        return 'Discussions familiales';
    }

    public function moduleDescription(): string
    {
        return 'Espace de discussion sécurisé pour la famille avec pièces jointes chiffrées.';
    }

    public function modulePermissionCode(): string
    {
        return 'family_discussion';
    }

    public function migrationStatusCode(): string
    {
        return 'family_discussion';
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
            'discussions',
            'discussions_view',
            'discussions_post',
            'discussions_delete',
            'discussions_attachment_download',
            'discussions_attachment_delete',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tables(): array
    {
        return [
            'family_discussion_threads',
            'family_discussion_posts',
            'family_discussion_attachments',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function contractClasses(): array
    {
        return [
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Repository\\DiscussionRepository',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Attachment\\DiscussionAttachmentStorage',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionService',
            'Caramagnols\\PrivateApps\\FamilyDiscussion\\Service\\DiscussionAccessPolicy',
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
        ];
    }

    /**
     * @return array<int, string>
     */
    public function auditEvents(): array
    {
        return [
            'private.discussion.thread_created',
            'private.discussion.post_created',
            'private.discussion.attachment_uploaded',
            'private.discussion.attachment_downloaded',
            'private.discussion.attachment_deleted',
            'private.discussion.thread_deleted',
            'private.module.access_denied',
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
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function routePaths(): array
    {
        return [
            'discussions' => 'discussions',
            'discussions_view' => 'discussions/voir',
            'discussions_post' => 'discussions/poster',
            'discussions_delete' => 'discussions/supprimer',
            'discussions_attachment_download' => 'discussions/piece-jointe/telecharger',
            'discussions_attachment_delete' => 'discussions/piece-jointe/supprimer',
        ];
    }

    /**
     * @return array{label: string, description: string, stat_code: string}
     */
    public function dashboardTileData(): array
    {
        return [
            'label' => 'Discussions',
            'description' => 'Espace de discussion familiale sécurisé',
            'stat_code' => 'private.discussion.thread_count',
        ];
    }

    public function notes(): string
    {
        return 'Module de discussion familiale avec chiffrement AES-256-GCM des pièces jointes.';
    }
}
