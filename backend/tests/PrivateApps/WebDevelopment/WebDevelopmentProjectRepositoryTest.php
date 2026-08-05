<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\WebDevelopment;

use Caramagnols\PrivateApps\WebDevelopment\Repository\WebDevelopmentProjectRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class WebDevelopmentProjectRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
    }

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testSharedAndOwnedProjectsRespectMemberBoundary(): void
    {
        $database = $this->editorialSqlDatabase();
        $projectsTable = $database->table('web_development_projects');
        $releasesTable = $database->table('web_development_releases');
        $pdo = $database->pdo();

        $pdo->exec(sprintf(
            'CREATE TABLE `%s` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `project_key` VARCHAR(80) NOT NULL UNIQUE,
                `display_name` VARCHAR(160) NOT NULL DEFAULT \'\',
                `description` TEXT NULL,
                `current_public_path` VARCHAR(255) NOT NULL DEFAULT \'\',
                `current_release_id` INT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_by_private_user_id` INT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $projectsTable
        ));
        $pdo->exec(sprintf(
            'CREATE TABLE `%s` (
                `id` INT PRIMARY KEY,
                `project_id` INT NOT NULL,
                `public_path` VARCHAR(255) NOT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT \'published\'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            $releasesTable
        ));

        $insertProject = $pdo->prepare(sprintf(
            'INSERT INTO `%s`
                (`id`, `project_key`, `display_name`, `description`, `current_public_path`, `current_release_id`, `is_active`, `created_by_private_user_id`)
             VALUES
                (:id, :project_key, :display_name, \'\', :public_path, NULL, 1, :owner_id)',
            $projectsTable
        ));
        $insertProject->execute([
            'id' => 1,
            'project_key' => 'lordelaroche',
            'display_name' => 'Lor de la Roche',
            'public_path' => 'deployments/lordelaroche/public',
            'owner_id' => null,
        ]);
        $insertProject->execute([
            'id' => 2,
            'project_key' => 'client-prive',
            'display_name' => 'Client privé',
            'public_path' => 'deployments/client-prive/public',
            'owner_id' => 42,
        ]);

        $repository = new WebDevelopmentProjectRepository($database);

        self::assertSame(
            ['lordelaroche'],
            array_column($repository->findPreviewProjectsForUser(7), 'projectKey')
        );
        self::assertSame(
            ['client-prive', 'lordelaroche'],
            array_column($repository->findPreviewProjectsForUser(42), 'projectKey')
        );
        self::assertNotNull($repository->findPreviewProjectByKeyForUser(7, 'lordelaroche'));
        self::assertNull($repository->findPreviewProjectByKeyForUser(7, 'client-prive'));
        self::assertNotNull($repository->findPreviewProjectByKeyForUser(42, 'client-prive'));

        $adminProjects = $repository->listProjectsForAdministration();
        self::assertSame(['client-prive', 'lordelaroche'], array_column($adminProjects, 'projectKey'));
        self::assertTrue($repository->saveProjectConfiguration(
            'lordelaroche',
            'Lordelaroche',
            'Prévisualisation privée',
            42,
            true
        ));
        self::assertTrue($repository->saveProjectConfiguration(
            'nouveau-client',
            'Nouveau client',
            '',
            42,
            false
        ));
        self::assertFalse($repository->saveProjectConfiguration('Clé invalide', 'Projet', '', null, true));

        $saved = $pdo->query(sprintf(
            'SELECT `display_name`, `description`, `current_public_path`, `created_by_private_user_id`, `is_active`
             FROM `%s` WHERE `project_key` = \'lordelaroche\'',
            $projectsTable
        ))->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($saved);
        self::assertSame('Lordelaroche', $saved['display_name']);
        self::assertSame('Prévisualisation privée', $saved['description']);
        self::assertSame(
            'lordelaroche/releases/current/public',
            $saved['current_public_path']
        );
        self::assertSame(42, (int) $saved['created_by_private_user_id']);
        self::assertSame(1, (int) $saved['is_active']);
    }
}
