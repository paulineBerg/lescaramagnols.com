<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyLineCategoryGuesser;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyLineCategoryGuesserTest extends TestCase
{
    public function testMapsCoproWorkFundBeforeGenericWorksExpense(): void
    {
        $guesser = new AgencyLineCategoryGuesser();

        $this->assertSame('copro_work_fund', $guesser->guess('Appel Fonds Travaux Loi ALUR'));
        $this->assertSame('works_expense', $guesser->guess('Facture travaux plomberie'));
    }
}
