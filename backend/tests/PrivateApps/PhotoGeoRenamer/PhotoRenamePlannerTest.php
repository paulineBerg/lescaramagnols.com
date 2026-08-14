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

    public function testDetectsDuplicateTargetsExistingConflictsAndSupportsPermutations(): void
    {
        $planner = new PhotoRenamePlanner();
        $duplicate = $planner->preview(
            [
                ['current_name' => 'A.jpg', 'city' => 'Cogolin'],
                ['current_name' => 'B.jpg', 'city' => 'Cogolin'],
            ],
            ['A.jpg', 'B.jpg'],
            [['type' => 'city']],
        );
        $this->assertFalse($duplicate['ok']);
        $this->assertSame('duplicate_in_batch', $duplicate['conflicts'][0]['issues'][0]);

        $existing = $planner->preview(
            [['current_name' => 'A.jpg', 'city' => 'Cogolin']],
            ['A.jpg'],
            [['type' => 'city']],
            ['Cogolin.jpg']
        );
        $this->assertFalse($existing['ok']);
        $this->assertSame('target_exists', $existing['conflicts'][0]['issues'][0]);

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
        $this->assertSame('43.253:6.530', (new PhotoGeoCacheKey())->forCoordinates(43.25291, 6.53033, 3));
    }
}
