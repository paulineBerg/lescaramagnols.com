<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class SecurityHttpsTest extends TestCase
{
    private array $serverBackup = [];
    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->envBackup = [
            'TRUST_PROXY_HEADERS' => array_key_exists('TRUST_PROXY_HEADERS', $_ENV) ? (string) $_ENV['TRUST_PROXY_HEADERS'] : null,
            'ADMIN_TRUST_PROXY_HEADERS' => array_key_exists('ADMIN_TRUST_PROXY_HEADERS', $_ENV) ? (string) $_ENV['ADMIN_TRUST_PROXY_HEADERS'] : null,
        ];

        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        foreach ($this->envBackup as $key => $value) {
            $this->setEnv($key, $value);
        }
    }

    public function testForwardedProtoIgnoredWhenProxyHeadersNotTrusted(): void
    {
        $this->setEnv('TRUST_PROXY_HEADERS', 'false');
        $this->setEnv('ADMIN_TRUST_PROXY_HEADERS', 'false');

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['SERVER_PORT'] = '80';
        unset($_SERVER['HTTPS']);

        $this->assertFalse(request_is_secure());
    }

    public function testForwardedProtoUsedWhenProxyHeadersTrusted(): void
    {
        $this->setEnv('TRUST_PROXY_HEADERS', 'true');

        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['SERVER_PORT'] = '80';
        unset($_SERVER['HTTPS']);

        $this->assertTrue(request_is_secure());
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
            return;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }
}
