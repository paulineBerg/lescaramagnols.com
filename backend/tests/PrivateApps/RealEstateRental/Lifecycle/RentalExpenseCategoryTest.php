<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalExpenseCategoryCatalog;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentalExpenseCategoryTest extends TestCase
{
    public function testCategoriesAreNormalizedFromTheRentalReferenceList(): void
    {
        $codes = array_map(
            static fn (array $category): string => (string) $category['code'],
            RentalExpenseCategoryCatalog::options()
        );

        $this->assertContains('taxe_fonciere', $codes);
        $this->assertContains('assurance_pno', $codes);
        $this->assertContains('copropriete', $codes);
        $this->assertContains('emprunt', $codes);
        $this->assertSame('taxe_fonciere', RentalExpenseCategoryCatalog::normalize('taxe-fonciere'));
        $this->assertSame(RentalExpenseCategoryCatalog::DEFAULT, RentalExpenseCategoryCatalog::normalize('categorie libre'));
    }

    public function testRecoverableChargeIsNotAutomaticallyDeductible(): void
    {
        $this->assertTrue(RentalExpenseCategoryCatalog::recoverableDefault('eau'));
        $this->assertFalse(RentalExpenseCategoryCatalog::deductibleCandidateDefault('eau'));
    }
}
