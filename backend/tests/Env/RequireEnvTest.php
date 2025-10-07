<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

final class RequireEnvTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Unset any existing env values we may affect
        putenv('FOO');
        putenv('BAR');
        // Also clear from superglobals used by env()
        unset($_ENV['FOO'], $_ENV['BAR']);
        unset($_SERVER['FOO'], $_SERVER['BAR']);
    }

    public function testRequireEnvDoesNotThrowWhenVariablesPresent(): void
    {
        putenv('FOO=value');
        putenv('BAR=value');
        $_ENV['FOO'] = 'value';
        $_ENV['BAR'] = 'value';

        // Should not throw
        require_env(['FOO', 'BAR']);

        $this->assertTrue(true); // If no exception, test passes
    }

    public function testRequireEnvThrowsWhenVariableMissing(): void
    {
        // Ensure variables are not present
        putenv('FOO');
        putenv('BAR');
        unset($_ENV['FOO'], $_ENV['BAR']);
        unset($_SERVER['FOO'], $_SERVER['BAR']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required environment variables');

        require_env(['FOO', 'BAR']);
    }
}
