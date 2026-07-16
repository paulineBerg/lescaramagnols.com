<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentPaymentStatusService;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentPaymentStatusServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testPartialAndPaidStatusesAfterValidatedPayments(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('payment-status-paid@example.com');
        $service = new RentPaymentStatusService($repository);
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-12-01', 1000.0);
        $rentId = (int) $rent['id'];

        $tenantPayment = $repository->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            '2026-12-03',
            2026,
            12,
            0.0,
            400.0,
            'validated',
            $ownerId,
            null,
            $rentId,
            'tenant',
            'bank_transfer',
            'VIR-400'
        );
        $this->assertIsArray($tenantPayment);
        $this->assertSame('tenant', $tenantPayment['paymentKind']);
        $this->assertSame('bank_transfer', $tenantPayment['paymentMethod']);
        $this->assertSame('VIR-400', $tenantPayment['paymentReference']);

        $partial = $service->refreshRentStatus($rentId, new DateTimeImmutable('2026-11-30'));
        $this->assertIsArray($partial);
        $this->assertSame('partial', $partial['status']);

        $this->assertIsArray($repository->createPayment(
            $leaseId,
            $propertyId,
            $unitId,
            '2026-12-05',
            2026,
            12,
            0.0,
            600.0,
            'validated',
            $ownerId,
            null,
            $rentId,
            'caf',
            'bank_transfer',
            'CAF-600'
        ));

        $paid = $service->refreshRentStatus($rentId, new DateTimeImmutable('2026-11-30'));
        $this->assertIsArray($paid);
        $this->assertSame('paid', $paid['status']);
        $this->assertSame(1000.0, $service->effectivePaidAmount($repository->listPaymentsForRent($rentId)));
    }

    public function testCorrectionCancellationAndRefundRecomputeRentStatus(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('payment-status-correction@example.com');
        $service = new RentPaymentStatusService($repository);
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-12-01', 1000.0);
        $rentId = (int) $rent['id'];

        $payment = $repository->createPayment($leaseId, $propertyId, $unitId, '2026-12-03', 2026, 12, 0.0, 300.0, 'validated', $ownerId, null, $rentId);
        $this->assertIsArray($payment);
        $this->assertSame('partial', $service->refreshRentStatus($rentId, new DateTimeImmutable('2026-11-30'))['status'] ?? null);

        $updated = $repository->updatePayment(
            (int) $payment['id'],
            [$propertyId],
            '2026-12-03',
            1000.0,
            'validated',
            $ownerId,
            null,
            'tenant',
            'bank_transfer',
            'CORR-1000'
        );
        $this->assertIsArray($updated);
        $this->assertSame('paid', $service->refreshRentStatus($rentId, new DateTimeImmutable('2026-11-30'))['status'] ?? null);

        $refund = $repository->createPayment($leaseId, $propertyId, $unitId, '2026-12-10', 2026, 12, 0.0, 200.0, 'validated', $ownerId, null, $rentId, 'refund', 'bank_transfer', 'REM-200');
        $this->assertIsArray($refund);
        $this->assertSame(800.0, $service->effectivePaidAmount($repository->listPaymentsForRent($rentId)));
        $this->assertSame('partial', $service->refreshRentStatus($rentId, new DateTimeImmutable('2026-11-30'))['status'] ?? null);

        $this->assertIsArray($repository->cancelPayment((int) $payment['id'], [$propertyId]));
        $this->assertSame(-200.0, $service->effectivePaidAmount($repository->listPaymentsForRent($rentId)));
        $this->assertSame('pending', $service->refreshRentStatus($rentId, new DateTimeImmutable('2026-11-30'))['status'] ?? null);
    }

    public function testLateStatusAndOverpaymentControl(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('payment-status-late@example.com');
        $service = new RentPaymentStatusService($repository);
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-01-01', 1000.0);
        $rentId = (int) $rent['id'];

        $late = $service->refreshRentStatus($rentId, new DateTimeImmutable('2026-01-10'));
        $this->assertIsArray($late);
        $this->assertSame('late', $late['status']);

        $this->assertIsArray($repository->createPayment($leaseId, $propertyId, $unitId, '2026-01-10', 2026, 1, 0.0, 800.0, 'validated', $ownerId, null, $rentId));
        $this->assertTrue($service->wouldOverpay($rentId, 300.0, 'tenant'));
        $this->assertFalse($service->wouldOverpay($rentId, 300.0, 'refund'));
    }

    /**
     * @return array{0:RentalLifecycleRepository, 1:int, 2:int, 3:int, 4:int}
     */
    private function rentalContext(string $email): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, $email);

        $property = $propertyRepository->create($ownerId, 'Maison statut paiement', '12 rue du Statut', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot statut paiement', 40.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire statut', null, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 900.0, 100.0, 'validated', $ownerId, null);
        $this->assertIsArray($lease);

        return [$lifecycleRepository, $ownerId, $property->id, $unit->id, (int) $lease['id']];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRent(
        RentalLifecycleRepository $repository,
        int $leaseId,
        int $propertyId,
        int $unitId,
        int $ownerId,
        string $dueDate,
        float $amount
    ): array {
        $rent = $repository->createRent($leaseId, $propertyId, $unitId, (int) substr($dueDate, 0, 4), (int) substr($dueDate, 5, 2), $dueDate, $amount, 'pending', $ownerId, null);
        $this->assertIsArray($rent);

        return $rent;
    }

    private function createPrivateUser(PrivateUserRepository $repository, string $email): int
    {
        $hash = password_hash('StrongPassword1!', PASSWORD_ARGON2ID);
        $this->assertIsString($hash);
        $userId = $repository->create($email, $hash, 'active');
        $this->assertIsInt($userId);

        return $userId;
    }
}
