<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\TaxDeclarationHelper\Service\TaxDeclarationSummaryService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class TaxDeclarationHelperCompatibilityTest extends TestCase
{
    public function testOfficialDisclaimerIsExplicitlyNonOfficial(): void
    {
        $this->assertStringContainsString('Aide non officielle', TaxDeclarationSummaryService::DISCLAIMER);
    }
}
