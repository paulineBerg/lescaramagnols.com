<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\PhotoGeoRenamer;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoSequenceRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class PhotoSequenceRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testReservesAtomicRangesPerNormalizedCommune(): void
    {
        $database = $this->editorialSqlDatabase();
        $this->installPhotoGeoSchema();
        $repository = new PhotoSequenceRepository($database);

        $first = $repository->reserveForCommune('COGOLIN', 2);
        $second = $repository->reserveForCommune('Cogolin', 1);
        $multi = $repository->reserve(['Gassin' => 2, 'Saint-Tropez' => 1]);

        $this->assertSame([1, 2], $first->numbers);
        $this->assertSame([3], $second->numbers);
        $this->assertSame([1, 2], $multi['gassin']->numbers);
        $this->assertSame([1], $multi['saint-tropez']->numbers);
    }

    public function testManualInitializationNeverDecreasesCounter(): void
    {
        $this->editorialSqlDatabase();
        $this->installPhotoGeoSchema();
        $repository = new PhotoSequenceRepository($this->editorialSqlDatabase());

        $repository->initializeManualCounter('Ramatuelle', 42);
        $repository->initializeManualCounter('ramatuelle', 12);
        $reservation = $repository->reserveForCommune('Ramatuelle', 1);

        $this->assertSame([43], $reservation->numbers);
    }

    private function installPhotoGeoSchema(): void
    {
        $database = $this->editorialSqlDatabase();
        $sql = (string) file_get_contents(ROOT_PATH . '/sql/private/photo_geo_renamer.sql');
        $prefix = substr($database->table('x'), 0, -1);
        if ($prefix !== 'car_') {
            $sql = (string) preg_replace('/\bcar_([a-zA-Z0-9_]+)\b/', $prefix . '$1', $sql);
        }

        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $database->pdo()->exec($statement);
            }
        }
    }
}
