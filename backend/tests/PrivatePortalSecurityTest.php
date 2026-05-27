<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePasswordPolicy;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/rate_limiter.php';

final class PrivatePortalSecurityTest extends TestCase
{
    use EditorialSqlTestTrait;

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
        $this->cleanupEditorialSqlDatabase();

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

    public function testLoginRejectsRepositoryUsersNotInActiveStatus(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $passwordHash = password_hash('secret123', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $this->assertIsInt($userRepository->create('family@example.com', $passwordHash, 'invited'));
        $this->configurePrivatePasswordHash($passwordHash);

        $auth = new PrivateAuth(
            new PrivateSession($this->sessionName),
            null,
            $userRepository
        );

        $this->assertFalse($auth->login('family@example.com', 'secret123', '127.0.0.1'));
        $this->assertSame('invalid_credentials', $auth->failureReason());
        $this->assertFalse($auth->isAuthenticated());
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

    public function testSessionExpiresAfterInactivityTimeout(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_ARGON2ID));
        $this->setPrivateConfigValue('inactivity_timeout_seconds', 300);
        $auth = $this->privateAuth();

        $this->assertTrue($auth->login('family@example.com', 'secret123', '127.0.0.1'));
        $_SESSION['private_user']['last_activity_at'] = time() - 301;

        $this->assertFalse($auth->isAuthenticated());
    }

    public function testReauthTimeoutInvalidatesAuthenticatedSession(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_ARGON2ID));
        $this->setPrivateConfigValue('reauth_timeout_seconds', 301);
        $auth = $this->privateAuth();

        $this->assertTrue($auth->login('family@example.com', 'secret123', '127.0.0.1'));

        $context = $_SESSION['private_user'] ?? [];
        $this->assertIsArray($context);
        $context['last_reauth_at'] = time() - 302;
        $_SESSION['private_user'] = $context;

        $this->assertFalse($auth->isAuthenticated());
    }

    public function testPasswordPolicyRejectsWeakPasswordsAndMismatchedConfirmation(): void
    {
        $policy = new PrivatePasswordPolicy();

        $this->assertNotSame([], $policy->validate('weak', 'weak'));
        $this->assertContains('password_confirmation', $policy->validate('StrongPassword1!', 'OtherPassword1!'));
        $this->assertSame([], $policy->validate('StrongPassword1!', 'StrongPassword1!'));
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
