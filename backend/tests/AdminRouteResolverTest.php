<?php

declare(strict_types=1);

use Caramagnols\Admin\AdminRouteResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class AdminRouteResolverTest extends TestCase
{
    public function testCanonicalPathsUseSanitizedConfiguredSegment(): void
    {
        $resolver = new AdminRouteResolver(' Admin moderne ');

        $this->assertSame('admin-moderne', $resolver->loginPath());
        $this->assertSame('/admin-moderne', $resolver->canonicalPath());
        $this->assertSame('/admin-moderne/dashboard', $resolver->canonicalPath('dashboard'));
        $this->assertSame('/admin-moderne/pages', $resolver->canonicalPath('pages'));
        $this->assertSame('/admin-moderne/menus', $resolver->canonicalPath('menus'));
        $this->assertSame('/admin-moderne/discussions', $resolver->canonicalPath('discussions'));
        $this->assertSame('/admin-moderne/media', $resolver->canonicalPath('media'));
        $this->assertSame('/admin-moderne/logs', $resolver->canonicalPath('logs'));
        $this->assertSame('/admin-moderne/settings', $resolver->canonicalPath('settings'));
        $this->assertSame('/admin-moderne/logout', $resolver->canonicalPath('logout'));
        $this->assertSame('/admin-moderne/session/ping', $resolver->sessionPingPath());
        $this->assertSame('/admin-moderne/pages/new', $resolver->pageCreatePath());
        $this->assertSame('/admin-moderne/pages/association', $resolver->pageEditPath('association'));
        $this->assertSame('/admin-moderne/articles/save', $resolver->blogSavePath());
    }

    public function testCanonicalPathsIncludeConfiguredBasePathWhenProvided(): void
    {
        $resolver = new AdminRouteResolver('admin', '/catalogue');

        $this->assertSame('/catalogue/admin', $resolver->canonicalPath());
        $this->assertSame('/catalogue/admin/settings', $resolver->canonicalPath('settings'));
    }

    public function testLegacyAliasesAreNotRegistered(): void
    {
        $resolver = new AdminRouteResolver('admin');
        $paths = array_column($resolver->routeDefinitions(), 'path');

        $this->assertNotContains('/legacy-admin', $paths);
        $this->assertNotContains('/legacy-admin/index.php', $paths);
        $this->assertNotContains('/legacy-admin/dashboard.php', $paths);
        $this->assertContains('/admin/pages', $paths);
        $this->assertContains('/admin/pages/new', $paths);
        $this->assertContains('/admin/pages/{slug:[A-Za-z0-9_-]+}', $paths);
        $this->assertContains('/admin/discussions', $paths);
        $this->assertContains('/admin/media', $paths);
        $this->assertNotContains('/legacy-admin/discussions.php', $paths);
        $this->assertContains('/admin/logs', $paths);
        $this->assertNotContains('/legacy-admin/menus.php', $paths);
        $this->assertNotContains('/legacy-admin/logs.php', $paths);
        $this->assertContains('/admin/settings', $paths);
        $this->assertNotContains('/legacy-admin/settings.php', $paths);
        $this->assertContains('/admin/session/ping', $paths);
        $this->assertContains('/admin/articles/save', $paths);
    }

    public function testLegacySegmentConfiguredAsLoginPathIsOnlyUsedAsCanonicalPath(): void
    {
        $resolver = new AdminRouteResolver('legacy-admin-2026');

        $this->assertSame('/legacy-admin-2026', $resolver->canonicalPath());
    }
}
