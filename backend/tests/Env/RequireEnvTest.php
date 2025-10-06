<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

final class RequireEnvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('FOO');
        putenv('BAR');
        unset(['FOO'], ['BAR']);
        unset(['FOO'], ['BAR']);
    }

    public function testRequireEnvDoesNotThrowWhenVariablesPresent(): void
    {
        ['FOO'] = 'value';
        ['BAR'] = 'value';

        require_env(['FOO', 'BAR']);

        ->assertTrue(true); // If no exception, test passes
    }

    public function testRequireEnvThrowsWhenVariableMissing(): void
    {
        ->expectException(RuntimeException::class);
        ->expectExceptionMessage('Missing required environment variables');

        require_env(['FOO', 'BAR']);
    }
}
