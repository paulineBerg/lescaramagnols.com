<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class RouterTest extends TestCase
{
    public function testHomeRouteResolvesToAccueil(): void
    {
        $this->assertSame(
            'pages/dynamic.php',
            resolve_route('/')
        );
    }

    public function testUnknownRouteFallsbackTo404(): void
    {
        $this->assertSame(
            'pages/404.php',
            resolve_route('/does-not-exist')
        );
    }

}
