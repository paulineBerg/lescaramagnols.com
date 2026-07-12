<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\TaxDeclarationHelper\Repository\TaxDeclarationRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class TaxDeclarationHelperRoutesTest extends TestCase
{
    public function testTaxDeclarationRepositoryClassIsAvailableForPrivatePortal(): void
    {
        $this->assertTrue(class_exists(TaxDeclarationRepository::class));
    }
}
