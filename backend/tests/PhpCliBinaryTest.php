<?php

declare(strict_types=1);

use Caramagnols\Support\PhpCliBinary;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class PhpCliBinaryTest extends TestCase
{
    public function testNormalizeRejectsPhpFpmBinary(): void
    {
        $this->assertNull(PhpCliBinary::normalize('/images/stable64/usr/local/php8.2/sbin/php-fpm'));
        $this->assertSame('/usr/local/php8.2/bin/php', PhpCliBinary::normalize('/usr/local/php8.2/bin/php'));
    }

    public function testDetectFallsBackFromPhpFpmToCliCandidate(): void
    {
        $this->assertSame(
            'php',
            PhpCliBinary::detect('/images/stable64/usr/local/php8.2/sbin/php-fpm', 'php', null, 'fpm-fcgi')
        );
    }
}
