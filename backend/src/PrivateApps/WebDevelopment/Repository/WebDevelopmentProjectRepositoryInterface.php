<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Repository;

interface WebDevelopmentProjectRepositoryInterface
{
    /**
     * Un projet sans propriétaire est partagé avec tous les membres autorisés
     * à utiliser le module. Un projet affecté reste limité à son propriétaire.
     *
     * @return array<int, array{id: int, projectKey: string, displayName: string, description: string}>
     */
    public function findPreviewProjectsForUser(int $privateUserId): array;

    /**
     * @return array{id: int, projectKey: string, publicPath: string}|null
     */
    public function findPreviewProjectById(int $projectId): ?array;

    /**
     * @return array{id: int, projectKey: string, publicPath: string}|null
     */
    public function findPreviewProjectByKey(string $projectKey): ?array;

    /**
     * @return array{id: int, projectKey: string, publicPath: string}|null
     */
    public function findPreviewProjectByKeyForUser(int $privateUserId, string $projectKey): ?array;
}
