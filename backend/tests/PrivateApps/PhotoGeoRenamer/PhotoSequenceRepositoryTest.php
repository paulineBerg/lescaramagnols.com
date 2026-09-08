<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\PhotoGeoRenamer;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoSequenceRepository;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoBatchRepository;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Repository\PhotoOperationRepository;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Service\PhotoRenameBatchService;
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

    public function testAgentPreviewIsStoredThenExecutionReservesStableNumbers(): void
    {
        $database = $this->editorialSqlDatabase();
        $this->installPhotoGeoSchema();
        $service = new PhotoRenameBatchService(
            $database,
            new PhotoBatchRepository($database),
            new PhotoOperationRepository($database),
            new PhotoSequenceRepository($database)
        );
        $batchUid = str_repeat('a', 32);
        $previewUid = str_repeat('b', 32);
        $agent = ['id' => 7, 'agent_uid' => str_repeat('c', 32)];

        $stored = $service->ingestAgentPreviews(5, $agent, [[
            'batch_uid' => $batchUid,
            'preview_uid' => $previewUid,
            'root_uid' => 'photos-principales',
            'relative_dir' => '2026/vacances',
            'template' => [
                ['type' => 'city'],
                ['type' => 'counter'],
            ],
            'separator' => '-',
            'counter_digits' => 2,
            'sort_order' => 'chronological',
            'operations' => [
                ['old_name' => 'IMG_0002.jpg', 'relative_path' => 'IMG_0002.jpg', 'commune_name' => 'Cogolin', 'taken_at' => '2026-09-08 10:02:00'],
                ['old_name' => 'IMG_0001.jpg', 'relative_path' => 'IMG_0001.jpg', 'commune_name' => 'Cogolin', 'taken_at' => '2026-09-08 10:01:00'],
            ],
        ]]);

        $this->assertSame(['stored' => 1, 'rejected' => 0], $stored);

        $payload = $service->executePayload(5, $agent, $batchUid, $previewUid);
        $this->assertTrue($payload['no_overwrite']);
        $this->assertTrue($payload['two_pass']);
        $this->assertSame('Cogolin-01.jpg', $payload['operations'][0]['new_name']);
        $this->assertSame('Cogolin-02.jpg', $payload['operations'][1]['new_name']);

        $again = $service->executePayload(5, $agent, $batchUid, $previewUid);
        $this->assertSame($payload['operations'][0]['new_name'], $again['operations'][0]['new_name']);
        $this->assertSame(2, (int) (new PhotoSequenceRepository($database))->allSequences()[0]['last_number']);
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
