<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\Identity;

use Caramagnols\Identity\Repository\TrustedDeviceRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class TrustedDeviceRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testUpsertReusesSameAdminDeviceOnlyForSameIpAndUserAgent(): void
    {
        $repository = new TrustedDeviceRepository($this->editorialSqlDatabase());
        $trustedUntil = gmdate('Y-m-d H:i:s', time() + 2592000);

        $first = $repository->upsert('admin', null, 'identifier-hash', 'Ordinateur de confiance', 'desktop', 'ua-hash', 'ip-one', $trustedUntil);
        $sameDevice = $repository->upsert('admin', null, 'identifier-hash', 'Ordinateur de confiance', 'desktop', 'ua-hash', 'ip-one', $trustedUntil);
        $otherDevice = $repository->upsert('admin', null, 'identifier-hash', 'Ordinateur de confiance', 'desktop', 'ua-hash', 'ip-two', $trustedUntil);

        self::assertSame($first, $sameDevice);
        self::assertNotSame($first, $otherDevice);
    }
}
