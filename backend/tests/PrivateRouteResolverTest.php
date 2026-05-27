<?php

declare(strict_types=1);

use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PrivateRouteResolverTest extends TestCase
{
    public function testCanonicalPathsPreserveConfiguredBasePathCase(): void
    {
        $resolver = new PrivateRouteResolver(' private-4h6F1c ');

        $this->assertSame('/private-4h6F1c', $resolver->basePath());
        $this->assertSame('/private-4h6F1c/login', $resolver->canonicalPath('login'));
        $this->assertSame('/private-4h6F1c/dashboard', $resolver->canonicalPath('dashboard'));
        $this->assertSame('/private-4h6F1c/files/categories', $resolver->canonicalPath('files_categories'));
        $this->assertSame('/private-4h6F1c/locations', $resolver->canonicalPath('rental_dashboard'));
        $this->assertSame('/private-4h6F1c/locations/locataires', $resolver->canonicalPath('rental_tenants'));
        $this->assertSame('/private-4h6F1c/locations/agence/imports', $resolver->canonicalPath('rental_agency_imports'));
    }

    public function testCanonicalPathsSanitizeConfiguredBasePath(): void
    {
        $resolver = new PrivateRouteResolver(' /Private Portal 2026! ');

        $this->assertSame('/Private-Portal-2026', $resolver->basePath());
        $this->assertSame('/Private-Portal-2026/login', $resolver->canonicalPath('login'));
    }
}
