<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Repository;

use Caramagnols\Database\EditorialDatabase;
use PDO;

final class WebDevelopmentProjectRepository implements WebDevelopmentProjectRepositoryInterface
{
    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function findPreviewProjectById(int $projectId): ?array
    {
        if ($projectId <= 0) {
            return null;
        }

        return $this->findPreviewProject('p.`id` = :project_id', ['project_id' => $projectId]);
    }

    public function findPreviewProjectsForUser(int $privateUserId): array
    {
        $privateUserId = max(0, $privateUserId);
        if ($privateUserId <= 0) {
            return [];
        }

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT p.`id`,
                            p.`project_key`,
                            p.`display_name`,
                            p.`description`
                     FROM `%s` p
                     LEFT JOIN `%s` r ON r.`id` = p.`current_release_id` AND r.`project_id` = p.`id`
                     WHERE (p.`created_by_private_user_id` IS NULL OR p.`created_by_private_user_id` = :private_user_id)
                       AND COALESCE(p.`is_active`, 1) = 1
                       AND COALESCE(r.`public_path`, p.`current_public_path`, \'\') <> \'\'
                       AND (r.`id` IS NULL OR COALESCE(r.`status`, \'published\') = \'published\')
                     ORDER BY CASE WHEN p.`display_name` = \'\' THEN p.`project_key` ELSE p.`display_name` END, p.`id`
                     LIMIT 100',
                    $this->database->table('web_development_projects'),
                    $this->database->table('web_development_releases')
                )
            );
            $statement->execute(['private_user_id' => $privateUserId]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        $projects = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? 0);
            $projectKey = $this->normalizeProjectKey((string) ($row['project_key'] ?? ''));
            if ($id <= 0 || $projectKey === '') {
                continue;
            }

            $displayName = trim((string) ($row['display_name'] ?? ''));
            $projects[] = [
                'id' => $id,
                'projectKey' => $projectKey,
                'displayName' => $displayName !== '' ? $displayName : $projectKey,
                'description' => trim((string) ($row['description'] ?? '')),
            ];
        }

        return $projects;
    }

    public function findPreviewProjectByKey(string $projectKey): ?array
    {
        $projectKey = $this->normalizeProjectKey($projectKey);
        if ($projectKey === '') {
            return null;
        }

        return $this->findPreviewProject('p.`project_key` = :project_key', ['project_key' => $projectKey]);
    }

    public function findPreviewProjectByKeyForUser(int $privateUserId, string $projectKey): ?array
    {
        $projectKey = $this->normalizeProjectKey($projectKey);
        $privateUserId = max(0, $privateUserId);
        if ($projectKey === '' || $privateUserId <= 0) {
            return null;
        }

        return $this->findPreviewProject(
            'p.`project_key` = :project_key AND (p.`created_by_private_user_id` IS NULL OR p.`created_by_private_user_id` = :private_user_id)',
            [
                'project_key' => $projectKey,
                'private_user_id' => $privateUserId,
            ]
        );
    }

    /**
     * @param array<string, int|string> $params
     * @return array{id: int, projectKey: string, publicPath: string}|null
     */
    private function findPreviewProject(string $where, array $params): ?array
    {
        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT p.`id`,
                            p.`project_key`,
                            COALESCE(r.`public_path`, p.`current_public_path`, \'\') AS `public_path`
                     FROM `%s` p
                     LEFT JOIN `%s` r ON r.`id` = p.`current_release_id` AND r.`project_id` = p.`id`
                     WHERE %s
                       AND COALESCE(p.`is_active`, 1) = 1
                       AND (r.`id` IS NULL OR COALESCE(r.`status`, \'published\') = \'published\')
                     LIMIT 1',
                    $this->database->table('web_development_projects'),
                    $this->database->table('web_development_releases'),
                    $where
                )
            );
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($row)) {
            return null;
        }

        $id = (int) ($row['id'] ?? 0);
        $key = $this->normalizeProjectKey((string) ($row['project_key'] ?? ''));
        $publicPath = trim(str_replace('\\', '/', (string) ($row['public_path'] ?? '')));
        if ($id <= 0 || $key === '' || $publicPath === '') {
            return null;
        }

        return [
            'id' => $id,
            'projectKey' => $key,
            'publicPath' => $publicPath,
        ];
    }

    private function normalizeProjectKey(string $projectKey): string
    {
        $projectKey = strtolower(trim($projectKey));

        return preg_match('/\A[a-z0-9][a-z0-9_-]{1,80}\z/', $projectKey) === 1 ? $projectKey : '';
    }
}
