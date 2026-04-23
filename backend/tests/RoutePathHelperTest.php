<?php

declare(strict_types=1);

use Caramagnols\Http\RoutePathHelper;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class RoutePathHelperTest extends TestCase
{
    public function testRequestPathNormalizesTrailingSlash(): void
    {
        $this->assertSame('/blog', RoutePathHelper::requestPath('/blog/'));
        $this->assertSame('/', RoutePathHelper::requestPath('/'));
    }

    public function testNormalizePublicRouteSupportsExternalAndInternalPaths(): void
    {
        $this->assertSame('/contact', RoutePathHelper::normalizePublicRoute('contact'));
        $this->assertSame('https://example.test/a', RoutePathHelper::normalizePublicRoute('https://example.test/a'));
        $this->assertSame('/assets/images/structure/banniere.jpg', RoutePathHelper::normalizePublicRoute('/images/structure/banniere.jpg'));
        $this->assertNull(RoutePathHelper::normalizePublicRoute('#'));
    }
}
