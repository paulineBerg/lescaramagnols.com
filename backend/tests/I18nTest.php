<?php

declare(strict_types=1);

use Caramagnols\Http\Request;
use Caramagnols\I18n\LanguageResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class I18nTest extends TestCase
{
    public function testResolveLangFromQuery(): void
    {
        $resolver = new LanguageResolver();
        $request = new Request([], ['lang' => 'en'], [], [], [], []);

        $this->assertSame('en', $resolver->resolve($request));
    }

    public function testFallbackToDefault(): void
    {
        $resolver = new LanguageResolver();
        $request = new Request([], ['lang' => 'es'], [], [], [], []);

        $this->assertSame('fr', $resolver->resolve($request));
    }
}
