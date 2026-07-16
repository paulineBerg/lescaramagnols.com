<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyFiscalReviewPolicy;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyFiscalReviewPolicyTest extends TestCase
{
    public function testRequiresManualReviewForFiscalCategoriesOnly(): void
    {
        $policy = new AgencyFiscalReviewPolicy();

        $this->assertTrue($policy->requiresManualFiscalReview('rent_income'));
        $this->assertTrue($policy->requiresManualFiscalReview('agency_management_fee'));
        $this->assertTrue($policy->requiresManualFiscalReview('copro_work_fund'));
        $this->assertFalse($policy->requiresManualFiscalReview('owner_transfer'));
        $this->assertFalse($policy->requiresManualFiscalReview('security_deposit'));
        $this->assertFalse($policy->requiresManualFiscalReview('other'));
    }

    public function testNormalizesManualReviewConfirmationValues(): void
    {
        $policy = new AgencyFiscalReviewPolicy();

        $this->assertTrue($policy->isManualReviewConfirmed(true));
        $this->assertTrue($policy->isManualReviewConfirmed('on'));
        $this->assertTrue($policy->isManualReviewConfirmed('1'));
        $this->assertFalse($policy->isManualReviewConfirmed(false));
        $this->assertFalse($policy->isManualReviewConfirmed(''));
        $this->assertFalse($policy->isManualReviewConfirmed('0'));
    }
}
