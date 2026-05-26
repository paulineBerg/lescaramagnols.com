<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/rate_limiter.php';

final class PrivatePortalSecurityTest extends TestCase
{
    private string $rateLimitDir;
    private ?string $previousRateLimitDir = null;
    private string $sessionName;
    private array $previousPrivateConfig = [];

    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        global $appConfig;
        $this->previousPrivateConfig = is_array($appConfig['private'] ?? null) ? $appConfig['private'] : [];
        $this->previousRateLimitDir = is_string($appConfig['security']['rate_limit_dir'] ?? null)
            ? $appConfig['security']['rate_limit_dir']
            : null;

        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-private-security-rate-limits-' . bin2hex(random_bytes(6));
        mkdir($this->rateLimitDir, 0777, true);

        $this->sessionName = '_private_auth_' . bin2hex(random_bytes(4));

        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['session_name'] = $this->sessionName;
        $appConfig['private']['local_user_email'] = 'family@example.com';
        $appConfig['private']['login_rate_limit_attempts'] = 5;
        $appConfig['private']['login_rate_limit_window'] = 900;
        $appConfig['private']['account_lockout_attempts'] = 3;
        $appConfig['private']['account_lockout_seconds'] = 86400;
        $appConfig['private']['inactivity_timeout_seconds'] = 3600;
        $appConfig['private']['reauth_timeout_seconds'] = 1800;
        $appConfig['private']['trust_proxy_headers'] = false;

        $appConfig['security']['rate_limit_dir'] = $this->rateLimitDir;
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        $files = glob($this->rateLimitDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        @rmdir($this->rateLimitDir);

        global $appConfig;
        if ($this->previousRateLimitDir !== null) {
            $appConfig['security']['rate_limit_dir'] = $this->previousRateLimitDir;
        } else {
            unset($appConfig['security']['rate_limit_dir']);
        }

        if ($this->previousPrivateConfig !== []) {
            $appConfig['private'] = $this->previousPrivateConfig;
        } else {
            unset($appConfig['private']);
        }
    }

    public function testLoginRejectsUnsupportedPasswordHashAlgorithm(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_BCRYPT));

        $auth = $this->privateAuth();
        $result = $auth->login('family@example.com', 'secret123', '127.0.0.1');

        $this->assertFalse($result);
        $this->assertSame('invalid_credentials', $auth->failureReason());
        $this->assertFalse($auth->isAuthenticated());
    }

    public function testLoginRequiresValidCredentialsAndLocksAfterFailedAttempts(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_ARGON2ID));
        $this->setPrivateConfigValue('account_lockout_attempts', 1);
        $auth = $this->privateAuth();

        $this->assertFalse($auth->login('family@example.com', 'wrong', '127.0.0.1'));
        $this->assertSame('invalid_credentials', $auth->failureReason());
        $this->assertFalse($auth->isAuthenticated());

        $this->assertFalse($auth->login('family@example.com', 'secret123', '127.0.0.1'));
        $this->assertSame('account_locked', $auth->failureReason());
        $this->assertFalse($auth->isAuthenticated());
    }

    public function testLoginSuccessUpdatesSessionAndLogoutInvalidates(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_ARGON2ID));

        $auth = $this->privateAuth();
        $this->assertFalse($auth->isAuthenticated());

        $this->assertTrue($auth->login('family@example.com', 'secret123', '127.0.0.1'));
        $this->assertTrue($auth->isAuthenticated());
        $identifier = $auth->currentIdentifier();
        $this->assertSame('family@example.com', $identifier);

        $sessionBefore = $this->sessionId();
        $auth->logout('manual');

        $this->assertFalse($auth->isAuthenticated());

        $this->assertNotSame($sessionBefore, $this->sessionId());
    }

    private function privateAuth(): PrivateAuth
    {
        return new PrivateAuth(
            new PrivateSession($this->sessionName)
        );
    }

    private function configurePrivatePasswordHash(string $hash): void
    {
        global $appConfig;
        $appConfig['private']['local_user_password_hash'] = $hash;
    }

    private function setPrivateConfigValue(string $key, mixed $value): void
    {
        global $appConfig;
        $appConfig['private'][$key] = $value;
    }

    private function sessionId(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return session_id();
    }
}
