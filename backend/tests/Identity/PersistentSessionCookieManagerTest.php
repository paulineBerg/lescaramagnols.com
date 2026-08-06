<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Identity;

use Caramagnols\Http\Request;
use Caramagnols\Identity\PersistentSession\PersistentSessionCookieManager;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class PersistentSessionCookieManagerTest extends TestCase
{
    protected function setUp(): void
    {
        global $appConfig;
        $appConfig['env'] = 'testing';
        $appConfig['admin']['login_path'] = 'admin-test';
        $appConfig['private']['enabled'] = true;
        $appConfig['private']['base_path'] = 'private-test';
        $appConfig['identity']['persistent'] = [
            'admin_cookie_name' => 'caramagnols_admin_persistent',
            'private_cookie_name' => 'caramagnols_private_persistent',
            'identity_cookie_name' => 'caramagnols_identity',
        ];
    }

    public function testReadsSelectorAndSecretFromScopedCookie(): void
    {
        $manager = new PersistentSessionCookieManager();
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/private-test/dashboard'],
            [],
            [],
            ['caramagnols_private_persistent' => '0123456789abcdef0123456789abcdef.secret_ABCDEFGHIJKLMNOPQRSTUVWXYZ012345'],
            [],
        );

        self::assertSame(
            [
                'selector' => '0123456789abcdef0123456789abcdef',
                'secret' => 'secret_ABCDEFGHIJKLMNOPQRSTUVWXYZ012345',
            ],
            $manager->read($request, 'private')
        );
    }

    public function testRejectsMalformedCookie(): void
    {
        $manager = new PersistentSessionCookieManager();
        $request = new Request(
            ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin-test'],
            [],
            [],
            ['caramagnols_admin_persistent' => 'not-a-valid-cookie'],
            [],
        );

        self::assertNull($manager->read($request, 'admin'));
    }

    public function testIssueHeaderHardensCookieAndScopesPath(): void
    {
        $manager = new PersistentSessionCookieManager();
        $header = $manager->issueHeader('admin', '0123456789abcdef0123456789abcdef', 'secret_ABCDEFGHIJKLMNOPQRSTUVWXYZ012345', time() + 3600);

        self::assertStringStartsWith('caramagnols_admin_persistent=', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
        self::assertStringContainsString('/admin-test', $header);
        self::assertStringNotContainsString('caramagnols_private_persistent', $header);
    }
}
