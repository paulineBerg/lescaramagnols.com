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
        $appConfig['admin']['email'] = 'admin@example.com';
        $appConfig['admin']['password_hash'] = password_hash('topsecret', PASSWORD_DEFAULT);
        $appConfig['admin']['session_key'] = '_admin_user_test';
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

    public function testAdminLogout(): void
    {
        admin_login('admin@example.com', 'topsecret');
        $this->assertTrue(admin_is_authenticated());

        admin_logout();
        $this->assertFalse(admin_is_authenticated());
    }
}
