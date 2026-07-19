<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Repository\AgencyMappingRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyMappingRepositoryTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testSeedsAndFindsDefaultMappings(): void
    {
        $repository = new AgencyMappingRepository($this->editorialSqlDatabase());

        $this->assertGreaterThanOrEqual(20, $repository->seedDefaults());

        $fund = $repository->findForLabel('Appel Fonds Travaux Loi ALUR');
        $this->assertNotNull($fund);
        $this->assertSame('copro_work_fund', $fund->mappedCategory);
        $this->assertTrue($fund->requiresReview);

        $rent = $repository->findForLabel('Loyer fevrier 2026');
        $this->assertNotNull($rent);
        $this->assertSame('rent_income', $rent->mappedCategory);
        $this->assertFalse($rent->requiresReview);
    }
}
