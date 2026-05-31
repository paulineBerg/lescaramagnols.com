<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivateEnvironmentValidator;
use Caramagnols\PrivatePortal\Security\PrivatePasswordPolicy;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;
use Caramagnols\PrivatePortal\Security\PrivateSession;
use Caramagnols\PrivatePortal\Http\PrivateErrorResponder;
use Caramagnols\PrivatePortal\Http\PrivatePortalController;
use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use Caramagnols\Http\Request;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/rate_limiter.php';

final class PrivatePortalSecurityTest extends TestCase
{
    use EditorialSqlTestTrait;

    private string $logDir;
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

        $this->logDir = sys_get_temp_dir() . '/caramagnols-private-security-logs-' . bin2hex(random_bytes(6));
        $this->rateLimitDir = sys_get_temp_dir() . '/caramagnols-private-security-rate-limits-' . bin2hex(random_bytes(6));
        mkdir($this->logDir, 0777, true);
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

        $logFiles = glob($this->logDir . '/*');
        if (is_array($logFiles)) {
            foreach ($logFiles as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->logDir);

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

    public function testPrivateSessionUsesDedicatedCookieNameAfterBootstrapSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name('caramagnols_session');
        session_start();

        $this->assertSame('caramagnols_session', session_name());

        $session = new PrivateSession($this->sessionName);
        $session->start();

        $this->assertSame($session->name(), session_name());
        $this->assertTrue($session->isStarted());
        $privateSessionId = session_id();
        $_SESSION['private_marker'] = 'kept';

        session_write_close();
        $_COOKIE[$session->name()] = $privateSessionId;
        session_name('caramagnols_session');
        session_id('');
        session_start();

        $restartedSession = new PrivateSession($this->sessionName);
        $restartedSession->start();

        $this->assertSame($privateSessionId, session_id());
        $this->assertSame('kept', $_SESSION['private_marker'] ?? null);
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

    public function testReauthTimeoutDoesNotInvalidateActiveSessionButFreshGuardCanRequireIt(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_ARGON2ID));
        $this->setPrivateConfigValue('reauth_timeout_seconds', 301);
        $auth = $this->privateAuth();

        $this->assertTrue($auth->login('family@example.com', 'secret123', '127.0.0.1'));

        $context = $_SESSION['private_user'] ?? [];
        $this->assertIsArray($context);
        $context['last_reauth_at'] = time() - 302;
        $_SESSION['private_user'] = $context;

        $this->assertTrue($auth->isAuthenticated());
        $this->assertFalse($auth->isReauthFresh());

        $guard = new PrivatePortalSecurityGuard($auth);
        $response = $guard->requireAuthenticated($this->request('GET', '/private/privacy/export'), '/private/login', true);

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status);
        $this->assertSame('/private/login', $response->headers['Location'] ?? null);
    }

    public function testPrivateSessionPingRenewsAuthenticatedSession(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_ARGON2ID));
        $this->setPrivateConfigValue('inactivity_timeout_seconds', 300);
        $auth = $this->privateAuth();
        $this->assertTrue($auth->login('family@example.com', 'secret123', '127.0.0.1'));

        $context = $_SESSION['private_user'] ?? [];
        $this->assertIsArray($context);
        $context['last_activity_at'] = time() - 250;
        $_SESSION['private_user'] = $context;

        $token = csrf_token('private_session');
        $controller = new PrivatePortalController($auth);
        $response = $controller->handle(
            'session_ping',
            $this->request('POST', '/private/session/ping', ['csrf_token' => $token])
        );

        $this->assertSame(200, $response->status);
        $payload = json_decode($response->body, true);
        $this->assertIsArray($payload);
        $this->assertTrue($payload['ok'] ?? false);
        $this->assertSame(300, $payload['timeoutSeconds'] ?? null);
        $this->assertGreaterThan(240, (int) ($payload['remainingSeconds'] ?? 0));
    }

    public function testPasswordPolicyRejectsWeakPasswordsAndMismatchedConfirmation(): void
    {
        $policy = new PrivatePasswordPolicy();

        $this->assertNotSame([], $policy->validate('weak', 'weak'));
        $this->assertContains('password_confirmation', $policy->validate('StrongPassword1!', 'OtherPassword1!'));
        $this->assertSame([], $policy->validate('StrongPassword1!', 'StrongPassword1!'));
    }

    public function testPrivateEnvironmentValidatorRejectsUnsafePrivateConfig(): void
    {
        $this->configurePrivatePasswordHash(password_hash('secret123', PASSWORD_BCRYPT));
        $this->setPrivateConfigValue('local_user_email', 'invalid-email');
        $this->setPrivateConfigValue('session_name', 'bad session name');

        $validator = new PrivateEnvironmentValidator(new PrivateRouteResolver('private'));
        $issues = $validator->issues();

        $this->assertContains('private_local_password_hash_algo_invalid', $issues);
        $this->assertContains('private_local_user_email_invalid', $issues);
        $this->assertContains('private_session_name_invalid', $issues);
        $this->assertFalse($validator->isValid());
    }

    public function testSecurityGuardRejectsInvalidCsrfAndLogsAuditEvent(): void
    {
        csrf_token('private_documents');
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));
        $guard = new PrivatePortalSecurityGuard($this->privateAuth(), $logger);

        $request = $this->request('POST', '/private/files/upload', ['csrf_token' => 'invalid-secret-token']);

        $this->assertFalse($guard->validateCsrf($request, 'private_documents'));
        $log = $this->securityLogContent();
        $this->assertStringContainsString('private.csrf.rejected', $log);
        $this->assertStringContainsString('private_documents', $log);
        $this->assertStringNotContainsString('invalid-secret-token', $log);
    }

    public function testPrivateModulePermissionRepositoryEnforcesRbac(): void
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $permissionRepository = new PrivateModulePermissionRepository($database, new PrivateModuleRegistry());
        $passwordHash = password_hash('secret123', PASSWORD_ARGON2ID);
        $this->assertIsString($passwordHash);
        $userId = $userRepository->create('rbac@example.com', $passwordHash, 'active');
        $this->assertIsInt($userId);

        $this->assertTrue($permissionRepository->setUserModules($userId, ['documents'], 'admin@example.com'));

        $this->assertTrue($permissionRepository->userHasModuleAccess($userId, 'documents'));
        $this->assertFalse($permissionRepository->userHasModuleAccess($userId, 'blocnote'));
        $this->assertFalse($permissionRepository->userHasModuleAccess(0, 'documents'));
    }

    public function testPrivateAuditLogRedactsSensitiveContextAndAppendsEvents(): void
    {
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));

        $logger->security('private.audit.first', [
            'identifier' => 'family@example.com',
            'password' => 'RawPassword123!',
            'reset_token' => 'raw-reset-token',
        ]);
        $logger->security('private.audit.second', [
            'csrf_token' => 'raw-csrf-token',
        ]);

        $log = $this->securityLogContent();
        $this->assertStringContainsString('private.audit.first', $log);
        $this->assertStringContainsString('private.audit.second', $log);
        $this->assertGreaterThanOrEqual(2, substr_count(trim($log), "\n") + 1);
        $this->assertStringNotContainsString('RawPassword123!', $log);
        $this->assertStringNotContainsString('raw-reset-token', $log);
        $this->assertStringNotContainsString('raw-csrf-token', $log);
        $this->assertStringContainsString('[redacted]', $log);
    }

    public function testPrivateErrorResponderDoesNotLeakExceptionDetails(): void
    {
        $logger = new AppEventLogger(new LoggerFactory($this->logDir, 'test'));
        $responder = new PrivateErrorResponder($logger);
        $response = $responder->exception(
            $this->request('GET', '/private/dashboard'),
            new RuntimeException('secret database password leaked')
        );

        $this->assertSame(500, $response->status);
        $this->assertSame('noindex, nofollow, noarchive', $response->headers['X-Robots-Tag'] ?? null);
        $this->assertStringContainsString('Erreur interne', $response->body);
        $this->assertStringNotContainsString('secret database password leaked', $response->body);

        $log = $this->securityLogContent();
        $this->assertStringContainsString('private.request.error', $log);
        $this->assertStringContainsString('RuntimeException', $log);
        $this->assertStringNotContainsString('secret database password leaked', $log);
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

    /**
     * @param array<string, mixed> $post
     */
    private function request(string $method, string $uri, array $post = []): Request
    {
        return new Request(
            [
                'REQUEST_METHOD' => $method,
                'REQUEST_URI' => $uri,
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            [],
            $post,
            [],
            ['Host' => '127.0.0.1:8000']
        );
    }

    private function securityLogContent(): string
    {
        $path = $this->logDir . '/security.log';
        if (!is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : '';
    }
}
