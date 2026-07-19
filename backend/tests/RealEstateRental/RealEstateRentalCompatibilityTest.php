<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalProperty;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalPropertyMember;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalUnit;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/bootstrap.php';

final class RealEstateRentalCompatibilityTest extends TestCase
{
    public function testPhase5NamespaceIsLoadableFromLegacySuitePath(): void
    {
        $this->assertTrue(class_exists(RentalProperty::class));
        $this->assertTrue(class_exists(RentalUnit::class));
        $this->assertTrue(class_exists(RentalPropertyMember::class));
        $this->assertTrue(class_exists(RentalPropertyRepository::class));
        $this->assertTrue(class_exists(RentalUnitRepository::class));
        $this->assertTrue(class_exists(RentalPropertyMemberRepository::class));
    }
}
