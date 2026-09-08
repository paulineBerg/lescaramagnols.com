<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\PhotoGeoRenamer;

use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoGeoCacheKey;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoRenamePlanner;
use Caramagnols\PrivateApps\PhotoGeoRenamer\Domain\PhotoRollbackPlanner;
use PHPUnit\Framework\TestCase;

final class PhotoRenamePlannerTest extends TestCase
{
    public function testBuildsPreviewFromTemplateWithoutConflicts(): void
    {
        $planner = new PhotoRenamePlanner();
        $preview = $planner->preview(
            [
                ['current_name' => 'IMG_0002.jpg', 'city' => 'Cogolin', 'taken_at' => '2026-08-13 12:05:00'],
                ['current_name' => 'IMG_0001.jpg', 'city' => 'Cogolin', 'taken_at' => '2026-08-13 12:00:00'],
            ],
            ['IMG_0001.jpg', 'IMG_0002.jpg'],
            [
                ['type' => 'text', 'value' => 'Vacances'],
                ['type' => 'city'],
                ['type' => 'date'],
                ['type' => 'counter'],
            ],
            [],
            '_',
            1,
            3,
            'chronological',
            str_repeat('a', 32)
        );

        $this->assertTrue($preview['ok']);
        $this->assertSame('Vacances_Cogolin_2026-08-13_001.jpg', $preview['operations'][0]['new_name']);
        $this->assertSame('Vacances_Cogolin_2026-08-13_002.jpg', $preview['operations'][1]['new_name']);
        $this->assertSame(2, $preview['summary']['ready']);
    }

    public function testDetectsDuplicateTargetsIgnoresDestinationScanAndSupportsPermutations(): void
    {
        $planner = new PhotoRenamePlanner();
        $duplicate = $planner->preview(
            [
                ['current_name' => 'A.jpg', 'city' => 'Cogolin', 'taken_at' => '2026-09-08 10:00:00'],
                ['current_name' => 'B.jpg', 'city' => 'Cogolin', 'taken_at' => '2026-09-08 10:01:00'],
            ],
            ['A.jpg', 'B.jpg'],
            [['type' => 'city']],
        );
        $this->assertFalse($duplicate['ok']);
        $this->assertSame('duplicate_in_batch', $duplicate['conflicts'][0]['issues'][0]);

        $existing = $planner->preview(
            [['current_name' => 'A.jpg', 'city' => 'Cogolin', 'taken_at' => '2026-09-08 10:00:00']],
            ['A.jpg'],
            [['type' => 'city']],
            ['Cogolin.jpg']
        );
        $this->assertTrue($existing['ok']);
        $this->assertSame([], $existing['conflicts']);

        $permutation = $planner->preview(
            [
                ['current_name' => 'A.jpg', 'city' => 'B'],
                ['current_name' => 'B.jpg', 'city' => 'A'],
            ],
            ['A.jpg', 'B.jpg'],
            [['type' => 'city']],
            ['A.jpg', 'B.jpg'],
            '-',
            1,
            3,
            'name',
            str_repeat('b', 32)
        );
        $this->assertTrue($permutation['ok']);
        $this->assertStringStartsWith('.pbgestion-bbbbbbbbbbbb-', $permutation['operations'][0]['temporary_name']);
    }

    public function testRollbackPreviewBlocksOverwriteAndGeoCacheRoundsCoordinates(): void
    {
        $rollback = new PhotoRollbackPlanner();
        $blocked = $rollback->preview(
            [['old_name' => 'IMG_0001.jpg', 'new_name' => 'Cogolin.jpg']],
            ['IMG_0001.jpg', 'Cogolin.jpg']
        );

        $this->assertFalse($blocked['ok']);
        $this->assertSame('restore_target_exists', $blocked['conflicts'][0]['issues'][0]);
        $this->assertSame('43.25291:6.53033', (new PhotoGeoCacheKey())->forCoordinates(43.25291, 6.53033));
    }

    public function testDefaultNameUsesCommuneCounterTwoDigitsAndChronologicalOrderByCommune(): void
    {
        $preview = (new PhotoRenamePlanner())->preview(
            [
                ['current_name' => 'B.JPG', 'city' => 'Cogolin', 'DateTimeOriginal' => '2026:09:08 10:02:00'],
                ['current_name' => 'A.JPG', 'city' => 'Cogolin', 'DateTimeOriginal' => '2026:09:08 10:01:00'],
                ['current_name' => 'C.heic', 'city' => 'Gassin', 'DateTimeOriginal' => '2026:09:08 09:00:00'],
            ],
            ['A.JPG', 'B.JPG', 'C.heic'],
            [],
            [],
            '-',
            1,
            2,
            'chronological',
            str_repeat('c', 32)
        );

        $this->assertTrue($preview['ok']);
        $this->assertSame('Cogolin-01.JPG', $preview['operations'][0]['new_name']);
        $this->assertSame('Cogolin-02.JPG', $preview['operations'][1]['new_name']);
        $this->assertSame('Gassin-01.heic', $preview['operations'][2]['new_name']);
        $this->assertSame(2, $preview['summary']['communes']);
    }

    public function testMissingCommuneAndDateAreExplicitConflicts(): void
    {
        $preview = (new PhotoRenamePlanner())->preview(
            [['current_name' => 'IMG.jpg']],
            ['IMG.jpg'],
            [],
            [],
            '-',
            1,
            2,
            'chronological'
        );

        $this->assertFalse($preview['ok']);
        $this->assertContains('commune_missing', $preview['conflicts'][0]['issues']);
        $this->assertContains('taken_at_missing', $preview['conflicts'][0]['issues']);
    }
}
