<?php

declare(strict_types=1);

use Caramagnols\Cron\CronExpression;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class CronExpressionTest extends TestCase
{
    public function testDailyExpressionFindsPreviousAndNextRun(): void
    {
        $expression = CronExpression::parse('00 12 * * *');
        $now = new DateTimeImmutable('2026-04-28 12:34:00');

        $this->assertSame('2026-04-28 12:00:00', $expression->previousRunBeforeOrAt($now)?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-29 12:00:00', $expression->nextRunAfter($now)?->format('Y-m-d H:i:s'));
        $this->assertSame('Tous les jours à 12:00', $expression->humanSummary());
    }

    public function testWeekdaySevenMatchesSunday(): void
    {
        $expression = CronExpression::parse('0 9 * * 7');

        $this->assertTrue($expression->matches(new DateTimeImmutable('2026-05-03 09:00:00')));
        $this->assertFalse($expression->matches(new DateTimeImmutable('2026-05-04 09:00:00')));
    }

    public function testStepExpressionSummary(): void
    {
        $expression = CronExpression::parse('*/5 * * * *');

        $this->assertTrue($expression->matches(new DateTimeImmutable('2026-04-28 08:10:00')));
        $this->assertFalse($expression->matches(new DateTimeImmutable('2026-04-28 08:11:00')));
        $this->assertSame('Toutes les 5 minutes', $expression->humanSummary());
    }

    public function testInvalidExpressionIsRejected(): void
    {
        $this->assertFalse(CronExpression::isValid('not a cron'));
        $this->assertFalse(CronExpression::isValid('61 * * * *'));
        $this->assertFalse(CronExpression::isValid('* * *'));
    }
}
