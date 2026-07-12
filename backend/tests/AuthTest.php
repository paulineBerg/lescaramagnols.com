<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/auth/admin.php';

final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        ensure_session_started();
        $_SESSION = [];

        global $appConfig;
        $appConfig['env'] = 'testing';
        $appConfig['admin']['email'] = 'admin@example.com';
        $appConfig['admin']['password_hash'] = password_hash('topsecret', PASSWORD_DEFAULT);
        $appConfig['admin']['session_key'] = '_admin_user_test';
        $appConfig['admin']['local_passwordless_localhost'] = false;
        $appConfig['admin']['inactivity_timeout_seconds'] = 1200;
        $appConfig['admin']['reauth_timeout_seconds'] = 600;
        $appConfig['admin']['totp_enabled'] = false;
        $appConfig['admin']['totp_secret'] = '';
        $appConfig['admin']['totp_skip_localhost'] = true;
        $appConfig['admin']['totp_period_seconds'] = 30;
        $appConfig['admin']['totp_allowed_drift_steps'] = 1;

        $_SERVER['REMOTE_ADDR'] = '198.51.100.10';
        unset($_SERVER['HTTP_HOST'], $_SERVER['SERVER_NAME']);
    }

    public function testAdminLoginSuccess(): void
    {
        $result = admin_login('admin@example.com', 'topsecret');

        $this->assertTrue($result);
        $this->assertTrue(admin_is_authenticated());

        $session = admin_current_user();
        $this->assertIsArray($session);
        $this->assertSame('admin@example.com', $session['email']);
    }

    public function testAdminLoginFailure(): void
    {
        $this->assertFalse(admin_login('admin@example.com', 'wrong-password'));
        $this->assertFalse(admin_is_authenticated());
    }

    public function testAdminLocalPasswordlessLoginAllowsOnlyNonProductionLoopback(): void
    {
        global $appConfig;
        $appConfig['admin']['local_passwordless_localhost'] = true;
        $appConfig['env'] = 'development';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertTrue(admin_login('admin@example.com', ''));
        $this->assertTrue(admin_is_authenticated());
    }

    public function testAdminLocalPasswordlessLoginIsRejectedInProduction(): void
    {
        global $appConfig;
        $appConfig['admin']['local_passwordless_localhost'] = true;
        $appConfig['env'] = 'production';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertFalse(admin_login('admin@example.com', ''));
        $this->assertFalse(admin_is_authenticated());
    }

    public function testAdminLocalPasswordlessLoginIgnoresSpoofedLocalhostHost(): void
    {
        global $appConfig;
        $appConfig['admin']['local_passwordless_localhost'] = true;
        $appConfig['env'] = 'development';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
        $_SERVER['HTTP_HOST'] = 'localhost';

        $this->assertFalse(admin_login('admin@example.com', ''));
        $this->assertFalse(admin_is_authenticated());
    }

    public function testAdminLoginRejectsNonEmailIdentifier(): void
    {
        $this->assertFalse(admin_login('admin', 'topsecret'));
        $this->assertFalse(admin_is_authenticated());
    }

    public function testAdminLoginIsDisabledWhenConfiguredIdentifierIsNotAnEmail(): void
    {
        global $appConfig;
        $appConfig['admin']['email'] = 'admin';

        $this->assertFalse(admin_login('admin', 'topsecret'));
        $this->assertFalse(admin_is_authenticated());
    }

    public function testAdminLogout(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $this->assertTrue(admin_is_authenticated());

        admin_logout();
        $this->assertFalse(admin_is_authenticated());
    }

    public function testAdminSessionExpiresAfterInactivityTimeout(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $this->assertTrue(admin_is_authenticated());

        $sessionKey = admin_session_key();
        $_SESSION[$sessionKey]['last_activity_at'] = time() - 1210;

        $this->assertFalse(admin_is_authenticated());
        $this->assertSame('inactive_timeout', admin_pop_notice_code());
    }

    public function testAdminFlashMessageRoundTrip(): void
    {
        admin_set_flash_message('success', 'Duplication réussie.');

        $this->assertSame(
            [
                'type' => 'success',
                'message' => 'Duplication réussie.',
            ],
            admin_pop_flash_message()
        );
        $this->assertNull(admin_pop_flash_message());

        admin_set_flash_message('error', 'Duplication impossible.');
        $this->assertSame(
            [
                'type' => 'error',
                'message' => 'Duplication impossible.',
            ],
            admin_pop_flash_message()
        );
    }

    public function testAdminLoginRequiresTotpWhenEnabledOutsideLocalBypass(): void
    {
        global $appConfig;
        $appConfig['admin']['totp_enabled'] = true;
        $appConfig['admin']['totp_skip_localhost'] = false;
        $appConfig['admin']['totp_secret'] = 'JBSWY3DPEHPK3PXP';

        $validCode = admin_totp_code_at_timestamp(time());
        $this->assertIsString($validCode);
        $this->assertSame(6, strlen((string) $validCode));

        $this->assertFalse(admin_login('admin@example.com', 'topsecret', null));
        $this->assertSame('totp_required', admin_pop_login_failure_reason());

        $this->assertFalse(admin_login('admin@example.com', 'topsecret', '000000'));
        $this->assertSame('totp_invalid', admin_pop_login_failure_reason());

        $this->assertTrue(admin_login('admin@example.com', 'topsecret', $validCode));
        $this->assertTrue(admin_is_authenticated());
    }

    public function testAdminReauthWindowExpires(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $this->assertTrue(admin_reauth_is_fresh());

        $sessionKey = admin_session_key();
        $_SESSION[$sessionKey]['last_reauth_at'] = time() - 700;

        $this->assertFalse(admin_reauth_is_fresh());
    }
}
