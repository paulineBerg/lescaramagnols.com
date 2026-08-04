<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Repository;

interface WebDevelopmentProjectRepositoryInterface
{
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
