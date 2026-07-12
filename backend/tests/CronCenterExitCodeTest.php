<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests;

use Caramagnols\Cron\CronCenterExitCode;
use PHPUnit\Framework\TestCase;

final class CronCenterExitCodeTest extends TestCase
{
    public function testJobFailureDoesNotFailDefaultOvhExitCode(): void
    {
        $result = [
            'success' => true,
            'locked' => false,
            'runs' => [
                ['status' => 'failed'],
            ],
        ];

        $this->assertSame(0, CronCenterExitCode::forResult($result));
        $this->assertSame(2, CronCenterExitCode::forResult($result, true));
    }

    public function testSchedulerFailureStillFailsExitCode(): void
    {
        $result = [
            'success' => false,
            'locked' => false,
            'runs' => [],
        ];

        $this->assertSame(1, CronCenterExitCode::forResult($result));
    }
}
