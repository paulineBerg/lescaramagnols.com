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
     * @return array<int, array{
     *   id: int,
     *   projectKey: string,
     *   displayName: string,
     *   description: string,
     *   publicPath: string,
     *   ownerUserId: int|null,
     *   ownerEmail: string,
     *   active: bool
     * }>
     */
    public function listProjectsForAdministration(): array
    {
        try {
            $statement = $this->database->pdo()->query(
                sprintf(
                    'SELECT p.`id`,
                            p.`project_key`,
                            p.`display_name`,
                            p.`description`,
                            p.`current_public_path`,
                            p.`created_by_private_user_id`,
                            p.`is_active`
                     FROM `%s` p
                     ORDER BY CASE WHEN p.`display_name` = \'\' THEN p.`project_key` ELSE p.`display_name` END, p.`id`',
                    $this->database->table('web_development_projects')
                )
            );
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

            $ownerUserId = (int) ($row['created_by_private_user_id'] ?? 0);
            $displayName = trim((string) ($row['display_name'] ?? ''));
            $projects[] = [
                'id' => $id,
                'projectKey' => $projectKey,
                'displayName' => $displayName !== '' ? $displayName : $projectKey,
                'description' => trim((string) ($row['description'] ?? '')),
                'publicPath' => trim(str_replace('\\', '/', (string) ($row['current_public_path'] ?? ''))),
                'ownerUserId' => $ownerUserId > 0 ? $ownerUserId : null,
                'ownerEmail' => '',
                'active' => (int) ($row['is_active'] ?? 0) === 1,
            ];
        }

        return $projects;
    }

    public function saveProjectConfiguration(
        string $projectKey,
        string $displayName,
        string $description,
        ?int $ownerUserId,
        bool $active
    ): bool {
        $projectKey = $this->normalizeProjectKey($projectKey);
        $displayName = trim($displayName);
        $description = trim($description);
        $ownerUserId = $ownerUserId !== null && $ownerUserId > 0 ? $ownerUserId : null;
        if (
            $projectKey === ''
            || $displayName === ''
            || mb_strlen($displayName) > 160
            || mb_strlen($description) > 2000
        ) {
            return false;
        }

        $publicPath = sprintf('%s/releases/current/public', $projectKey);

        try {
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`project_key`, `display_name`, `description`, `current_public_path`, `is_active`, `created_by_private_user_id`)
                     VALUES
                        (:project_key, :display_name, :description, :public_path, :is_active, :owner_user_id)
                     ON DUPLICATE KEY UPDATE
                        `display_name` = VALUES(`display_name`),
                        `description` = VALUES(`description`),
                        `current_public_path` = VALUES(`current_public_path`),
                        `is_active` = VALUES(`is_active`),
                        `created_by_private_user_id` = VALUES(`created_by_private_user_id`)',
                    $this->database->table('web_development_projects')
                )
            );

            return $statement->execute([
                'project_key' => $projectKey,
                'display_name' => $displayName,
                'description' => $description !== '' ? $description : null,
                'public_path' => $publicPath,
                'is_active' => $active ? 1 : 0,
                'owner_user_id' => $ownerUserId,
            ]);
        } catch (\Throwable) {
            return false;
        }
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

        return preg_match('/\A[a-z0-9][a-z0-9_-]{1,79}\z/', $projectKey) === 1 ? $projectKey : '';
    }
}
