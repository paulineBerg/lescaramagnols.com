<?php

declare(strict_types=1);

namespace Caramagnols\Tests\PrivatePortal;

use Caramagnols\Admin\AdminSettingsService;
use Caramagnols\Admin\AdminPrivateMembersService;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Logging\LoggerFactory;
use Caramagnols\Mailer\Mailer;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;
use Caramagnols\PrivatePortal\Repository\PrivateModulePermissionRepository;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

final class PrivateTransactionalEmailTest extends TestCase
{
    use EditorialSqlTestTrait;

    /** @var array<string, mixed> */
    private array $previousAppConfig = [];
    private array $previousServer = [];
    private array $previousEnv = [];
    private string|false $previousForceHttpsOnLocalhost = false;
    private string $tempDir = '';

    public static function setUpBeforeClass(): void
    {
        require_once ROOT_PATH . '/core/bootstrap.php';
        require_once ROOT_PATH . '/core/mailer.php';
    }

    protected function setUp(): void
    {
        global $appConfig;

        $this->previousAppConfig = is_array($appConfig) ? $appConfig : [];
        $this->previousServer = $_SERVER;
        $this->previousEnv = $_ENV;
        $this->previousForceHttpsOnLocalhost = getenv('FORCE_HTTPS_ON_LOCALHOST');
        $this->tempDir = sys_get_temp_dir() . '/caramagnols-private-mail-' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0700, true);

        $appConfig['base_url'] = 'https://preprod.lescaramagnols.com';
        $appConfig['site']['url'] = [];
        $appConfig['site']['name'] = 'Les Caramagnols';
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private';
        $appConfig['private']['mail'] = [
            'enabled' => true,
            'smtp_host' => 'ssl0.ovh.net',
            'smtp_port' => 465,
            'smtp_user' => 'ne-pas-repondre@lescaramagnols.com',
            'smtp_password' => 'configured-secret',
            'smtp_encryption' => 'ssl',
            'from_address' => 'ne-pas-repondre@lescaramagnols.com',
            'from_name' => 'Les Caramagnols',
            'reply_to' => 'private@lescaramagnols.com',
            'templates' => [],
        ];
    }

    protected function tearDown(): void
    {
        global $appConfig;

        $appConfig = $this->previousAppConfig;
        $_SERVER = $this->previousServer;
        $_ENV = $this->previousEnv;
        if ($this->previousForceHttpsOnLocalhost === false) {
            putenv('FORCE_HTTPS_ON_LOCALHOST');
        } else {
            putenv('FORCE_HTTPS_ON_LOCALHOST=' . $this->previousForceHttpsOnLocalhost);
        }
        $this->cleanupEditorialSqlDatabase();
        $this->removeDirectory($this->tempDir);
    }

    public function testPrivateMailViewModelDocumentsTemplatesVariablesAndPreviewUrls(): void
    {
        $service = new AdminSettingsService(
            $this->tempDir . '/database.override.php',
            $this->tempDir . '/admin.override.php',
            new AppEventLogger(new LoggerFactory($this->tempDir . '/logs')),
            $this->tempDir . '/site.override.php'
        );

        $viewModel = $service->privateMailViewModel();
        $catalog = is_array($viewModel['templateCatalog'] ?? null) ? $viewModel['templateCatalog'] : [];
        $previews = is_array($viewModel['previews'] ?? null) ? $viewModel['previews'] : [];

        self::assertCount(12, $catalog);
        self::assertSame(['email', 'today', 'login_url', 'private_url', 'reply_to', 'site_name'], $viewModel['commonVariables']);

        $variablesByBodyKey = [];
        foreach ($catalog as $template) {
            self::assertIsArray($template);
            self::assertNotSame('', $template['subject_key'] ?? '');
            self::assertNotSame('', $template['body_key'] ?? '');
            self::assertNotSame('', $template['fallback_subject'] ?? '');
            self::assertNotSame('', $template['fallback_body'] ?? '');
            $variablesByBodyKey[(string) ($template['body_key'] ?? '')] = $template['variables'] ?? [];
        }

        self::assertContains('activation_url', $variablesByBodyKey['admin_invite_body'] ?? []);
        self::assertContains('activation_url', $variablesByBodyKey['discussion_invite_body'] ?? []);
        self::assertContains('reset_url', $variablesByBodyKey['password_reset_body'] ?? []);
        self::assertContains('delete_after', $variablesByBodyKey['member_deletion_scheduled_body'] ?? []);
        self::assertContains('tenant_name', $variablesByBodyKey['rental_payment_request_body'] ?? []);
        self::assertContains('balance_due', $variablesByBodyKey['rental_payment_request_body'] ?? []);
        self::assertStringContainsString('Locataire exemple', (string) ($previews['rental_payment_request_body']['body'] ?? ''));
        self::assertStringContainsString(
            'https://preprod.lescaramagnols.com/private/activate/preview-token',
            (string) ($previews['admin_invite_body']['body'] ?? '')
        );
        self::assertStringContainsString(
            'https://preprod.lescaramagnols.com/private/password/reset/preview-token',
            (string) ($previews['password_reset_body']['body'] ?? '')
        );
    }

    public function testMailerErrorSanitizerRedactsSecretsAndTokens(): void
    {
        self::assertTrue(function_exists('sanitize_mailer_error_message'));

        $message = sanitize_mailer_error_message(
            'SMTP failed for smtps://smtp-user:clear-secret@smtp.example.test:465?password=raw-password token=reset-token username member@example.com'
        );

        self::assertStringNotContainsString('clear-secret', $message);
        self::assertStringNotContainsString('raw-password', $message);
        self::assertStringNotContainsString('reset-token', $message);
        self::assertStringNotContainsString('member@example.com', $message);
        self::assertStringContainsString('[redacted]', $message);
        self::assertStringContainsString('[email redacted]', $message);
    }

    public function testMailerDeliveryIsDisabledDuringAutomatedTests(): void
    {
        $result = send_notification_email_with_error(
            'recipient@example.com',
            'Test sans réseau',
            '<p>Aucun envoi réel.</p>',
            [],
            [
                'transport' => 'smtp',
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_user' => 'sender@example.com',
                'smtp_password' => 'test-secret',
                'smtp_encryption' => 'tls',
                'from_address' => 'sender@example.com',
            ]
        );

        self::assertFalse($result['sent']);
        self::assertSame('mail delivery disabled in test environment', $result['error']);
    }

    public function testPrivateMailSettingsCanSendMaskedSmtpTest(): void
    {
        $sentMessages = [];
        $logger = new AppEventLogger(new LoggerFactory($this->tempDir . '/smtp-test-logs'));
        $service = new AdminSettingsService(
            $this->tempDir . '/database.override.php',
            $this->tempDir . '/admin.override.php',
            $logger,
            $this->tempDir . '/site.override.php',
            privateMailSender: static function (string $to, string $subject, string $html, array $attachments) use (&$sentMessages): bool {
                $sentMessages[] = compact('to', 'subject', 'html', 'attachments');

                return true;
            }
        );

        $result = $service->savePrivateMail([
            'private_mail' => [
                'enabled' => '1',
                'smtp_host' => 'ssl0.ovh.net',
                'smtp_port' => '465',
                'smtp_encryption' => 'ssl',
                'smtp_user' => 'ne-pas-repondre@lescaramagnols.com',
                'smtp_password' => 'smtp-secret',
                'from_address' => 'ne-pas-repondre@lescaramagnols.com',
                'from_name' => 'Les Caramagnols',
                'reply_to' => 'private@lescaramagnols.com',
                'test_recipient' => 'smtp-test@example.com',
                'send_test' => '1',
            ],
        ], 'admin@example.com');

        self::assertTrue($result['success']);
        self::assertStringContainsString('Test SMTP envoyé.', (string) $result['message']);
        self::assertCount(1, $sentMessages);
        self::assertSame('smtp-test@example.com', $sentMessages[0]['to']);

        $log = (string) file_get_contents($this->tempDir . '/smtp-test-logs/security.log');
        self::assertStringContainsString('admin.private_mail.test_sent', $log);
        self::assertStringNotContainsString('smtp-test@example.com', $log);
        self::assertStringNotContainsString('admin@example.com', $log);
        self::assertStringContainsString('@example.com', $log);
    }

    public function testPrivateMailDeliveryConfigsAddOvhNetworkFallbacks(): void
    {
        self::assertTrue(function_exists('private_mail_delivery_configs'));

        $configs = private_mail_delivery_configs([
            'smtp_host' => 'ssl0.ovh.net',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_user' => 'ne-pas-repondre@lescaramagnols.com',
            'smtp_password' => 'configured-secret',
            'from_address' => 'ne-pas-repondre@lescaramagnols.com',
            'from_name' => 'Les Caramagnols',
            'reply_to' => 'private@lescaramagnols.com',
        ]);

        self::assertCount(4, $configs);
        self::assertSame(465, $configs[0]['smtp_port']);
        self::assertSame('ssl', $configs[0]['smtp_encryption']);
        self::assertSame(587, $configs[1]['smtp_port']);
        self::assertSame('tls', $configs[1]['smtp_encryption']);
        self::assertSame('native', $configs[2]['transport'] ?? null);
        self::assertSame('', $configs[2]['smtp_user'] ?? null);
        self::assertSame('', $configs[2]['smtp_password'] ?? null);
        self::assertSame('sendmail', $configs[3]['transport'] ?? null);
        self::assertSame('', $configs[3]['smtp_user'] ?? null);
        self::assertSame('', $configs[3]['smtp_password'] ?? null);
    }

    public function testPrivateMailFallbackIsLimitedToNetworkErrors(): void
    {
        self::assertTrue(private_mail_error_allows_transport_fallback('Connection refused'));
        self::assertTrue(private_mail_error_allows_transport_fallback('Unable to connect to ssl0.ovh.net'));
        self::assertFalse(private_mail_error_allows_transport_fallback('Failed to authenticate on SMTP server'));
    }

    public function testMailerAcceptsLocalSendmailTransport(): void
    {
        $mailer = new Mailer([
            'transport' => 'sendmail',
            'sendmail_command' => '/usr/sbin/sendmail -t -i',
            'from_address' => 'no-reply@example.test',
            'from_name' => 'Les Caramagnols',
        ]);

        self::assertInstanceOf(Mailer::class, $mailer);
    }

    public function testAdminResetReturnsManualLinkWhenPrivateMailCannotBeSent(): void
    {
        global $appConfig;

        $appConfig['private']['mail']['smtp_password'] = '';

        $database = $this->editorialSqlDatabase();
        $repository = new PrivateUserRepository($database);
        $passwordHash = password_hash('TempPassword1!', PASSWORD_ARGON2ID);
        self::assertIsString($passwordHash);
        $userId = $repository->create('family@example.com', $passwordHash, 'active');
        self::assertIsInt($userId);

        $service = new AdminPrivateMembersService(
            $repository,
            new PrivateModulePermissionRepository($database, new PrivateModuleRegistry())
        );

        $result = $service->handleAction([
            'private_member_action' => 'reset',
            'private_user_id' => (string) $userId,
        ], 'admin@example.com', '127.0.0.1', 'phpunit');

        self::assertTrue($result['success']);
        self::assertIsString($result['message']);
        self::assertStringContainsString('Lien de réinitialisation à usage unique', $result['message']);
        self::assertStringContainsString('https://preprod.lescaramagnols.com/private/password/reset/', $result['message']);
    }

    public function testPrivatePasswordResetActionUsesHttpForLocalhostWithoutTls(): void
    {
        global $appConfig;

        $appConfig['base_url'] = 'https://127.0.0.1:8000';
        $appConfig['site']['url'] = [];
        $appConfig['private']['mail']['smtp_password'] = '';
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
        unset($_SERVER['FORCE_HTTPS_ON_LOCALHOST'], $_ENV['FORCE_HTTPS_ON_LOCALHOST']);
        putenv('FORCE_HTTPS_ON_LOCALHOST');

        $database = $this->editorialSqlDatabase();
        $repository = new PrivateUserRepository($database);
        $passwordHash = password_hash('TempPassword1!', PASSWORD_ARGON2ID);
        self::assertIsString($passwordHash);
        $userId = $repository->create('family-local@example.com', $passwordHash, 'active');
        self::assertIsInt($userId);

        $service = new AdminPrivateMembersService(
            $repository,
            new PrivateModulePermissionRepository($database, new PrivateModuleRegistry())
        );

        $result = $service->handleAction([
            'private_member_action' => 'reset',
            'private_user_id' => (string) $userId,
        ], 'admin@example.com', '127.0.0.1', 'phpunit');

        self::assertTrue($result['success']);
        self::assertIsString($result['message']);
        self::assertStringContainsString('http://127.0.0.1:8000/private/password/reset/', $result['message']);
        self::assertStringNotContainsString('https://127.0.0.1:8000/private/password/reset/', $result['message']);
    }

    public function testSecurityLoggerRedactsTokenAndPasswordFields(): void
    {
        $logger = new AppEventLogger(new LoggerFactory($this->tempDir . '/security-logs'));
        $logger->security('private.password_reset.email_failed', [
            'reset_token' => 'clear-reset-token',
            'smtp_password' => 'clear-smtp-password',
            'identifier' => 'membre@example.com',
        ], 'warning');

        $logPath = $this->tempDir . '/security-logs/security.log';
        self::assertFileExists($logPath);
        $log = (string) file_get_contents($logPath);

        self::assertStringNotContainsString('clear-reset-token', $log);
        self::assertStringNotContainsString('clear-smtp-password', $log);
        self::assertStringContainsString('[redacted]', $log);
    }

    private function removeDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }

    public function testAppUrlKeepsConfiguredHttpsSchemeWithSiteBasePathInCli(): void
    {
        $GLOBALS['appConfig']['base_url'] = 'https://preprod.lescaramagnols.com';
        $GLOBALS['appConfig']['site']['url'] = ['base_path' => '/'];

        self::assertSame(
            'https://preprod.lescaramagnols.com/private/activate/preview-token',
            app_url(private_route_resolver()->canonicalPath('activate') . '/preview-token')
        );
    }
}
