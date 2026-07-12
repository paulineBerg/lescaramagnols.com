<?php

declare(strict_types=1);

namespace Caramagnols\Tests\PrivatePortal;

use Caramagnols\PrivatePortal\PrivateAppPackageDiscovery;
use PHPUnit\Framework\TestCase;

final class PrivateAppPackageDiscoveryTest extends TestCase
{
    private string $installedPackagesPath;

    protected function setUp(): void
    {
        $this->installedPackagesPath = sys_get_temp_dir()
            . '/caramagnols-installed-packages-'
            . bin2hex(random_bytes(8))
            . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->installedPackagesPath)) {
            unlink($this->installedPackagesPath);
        }
    }

    public function testDiscoversOnlyValidPrivateAppPackageManifests(): void
    {
        $payload = [
            'packages' => [
                [
                    'name' => 'caramagnols/example-private-app',
                    'type' => 'caramagnols-private-app',
                    'extra' => [
                        'caramagnols-private-app' => [
                            'manifests' => [
                                'Caramagnols\\PrivateApps\\Example\\PrivateAppManifest',
                                'invalid class name',
                                'Caramagnols\\PrivateApps\\Example\\PrivateAppManifest',
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'vendor/regular-library',
                    'type' => 'library',
                    'extra' => [
                        'caramagnols-private-app' => [
                            'manifests' => ['Vendor\\RegularLibrary\\Manifest'],
                        ],
                    ],
                ],
            ],
        ];
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        self::assertIsString($encoded);
        self::assertNotFalse(file_put_contents($this->installedPackagesPath, $encoded));

        $discovery = new PrivateAppPackageDiscovery($this->installedPackagesPath);

        self::assertSame(
            ['Caramagnols\\PrivateApps\\Example\\PrivateAppManifest'],
            $discovery->manifestClasses()
        );
    }

    public function testReturnsNoManifestForMissingOrInvalidInstalledMetadata(): void
    {
        $discovery = new PrivateAppPackageDiscovery($this->installedPackagesPath);
        self::assertSame([], $discovery->manifestClasses());

        self::assertNotFalse(file_put_contents($this->installedPackagesPath, '{invalid'));
        self::assertSame([], $discovery->manifestClasses());
    }

    public function testInstalledPrivateAppPackagesExposeTheirManifests(): void
    {
        $discovery = new PrivateAppPackageDiscovery();

        self::assertSame(
            [
                'Caramagnols\\PrivateApps\\FamilyDiscussion\\PrivateAppManifest',
                'Caramagnols\\PrivateApps\\RealEstateRental\\AgencyImportsManifest',
                'Caramagnols\\PrivateApps\\RealEstateRental\\PrivateAppManifest',
                'Caramagnols\\PrivateApps\\TaxDeclarationHelper\\PrivateAppManifest',
            ],
            $discovery->manifestClasses()
        );
    }
}
