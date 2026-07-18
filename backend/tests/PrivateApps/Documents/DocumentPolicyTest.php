<?php

declare(strict_types=1);

namespace LesCaramagnols\Tests\PrivateApps\Documents;

use Caramagnols\PrivateApps\Documents\Service\DocumentPolicy;
use PHPUnit\Framework\TestCase;

final class DocumentPolicyTest extends TestCase
{
    public function testWhitelistedExtensionsAreAllowed(): void
    {
        $policy = new DocumentPolicy();
        foreach (['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'tif', 'tiff', 'docx', 'odt', 'xlsx', 'ods', 'csv', 'txt'] as $extension) {
            self::assertTrue($policy->isAllowedExtension($extension), $extension . ' devrait être autorisé');
        }
    }

    public function testForbiddenExtensionsAreRejected(): void
    {
        $policy = new DocumentPolicy();
        foreach (['exe', 'msi', 'bat', 'php', 'js', 'py', 'sh', 'ps1', 'html', 'htm', 'svg', 'docm', 'xlsm', 'pptm', 'zip', 'rar', '7z', 'tar', 'doc', 'xls', 'ppt', 'gif', 'bmp'] as $extension) {
            self::assertFalse($policy->isAllowedExtension($extension), $extension . ' devrait être refusé');
        }
    }

    public function testFamilySizeLimits(): void
    {
        $policy = new DocumentPolicy();
        self::assertSame(25 * 1048576, $policy->maxBytesForExtension('pdf'));
        self::assertSame(15 * 1048576, $policy->maxBytesForExtension('jpg'));
        self::assertSame(30 * 1048576, $policy->maxBytesForExtension('tiff'));
        self::assertSame(15 * 1048576, $policy->maxBytesForExtension('docx'));
        self::assertSame(5 * 1048576, $policy->maxBytesForExtension('csv'));
        self::assertSame(0, $policy->maxBytesForExtension('exe'));
        self::assertSame(100 * 1048576, $policy->batchMaxBytes());
        self::assertSame(40_000_000, $policy->maxImagePixels());
    }

    public function testConfigurableLimitsInOnePlace(): void
    {
        $policy = new DocumentPolicy([DocumentPolicy::FAMILY_PDF => 1048576], 2097152, 1_000_000);
        self::assertSame(1048576, $policy->maxBytesForExtension('pdf'));
        self::assertSame(2097152, $policy->batchMaxBytes());
        self::assertSame(1_000_000, $policy->maxImagePixels());
    }

    public function testMimeTypesMatchExtension(): void
    {
        $policy = new DocumentPolicy();
        self::assertContains('application/pdf', $policy->allowedMimeTypesForExtension('pdf'));
        self::assertContains('image/jpeg', $policy->allowedMimeTypesForExtension('jpg'));
        self::assertNotContains('application/pdf', $policy->allowedMimeTypesForExtension('txt'));
    }
}
