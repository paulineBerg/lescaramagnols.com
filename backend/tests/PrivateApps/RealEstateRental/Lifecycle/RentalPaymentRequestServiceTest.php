<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalLifecycleRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyMemberRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalPropertyRepository;
use Caramagnols\PrivateApps\RealEstateRental\Repository\RentalUnitRepository;
use Caramagnols\PrivateApps\RealEstateRental\Service\RentalPaymentRequestService;
use Caramagnols\PrivatePortal\Repository\PrivateUserRepository;
use LesCaramagnols\Tests\Support\EditorialSqlTestTrait;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class RentalPaymentRequestServiceTest extends TestCase
{
    use EditorialSqlTestTrait;

    protected function tearDown(): void
    {
        $this->cleanupEditorialSqlDatabase();
    }

    public function testPreviewAndSendCreateImmutableMaskedSnapshotOnce(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('request-preview@example.com', 'locataire@example.com');
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-04-05', 1000.0);
        $rentId = (int) $rent['id'];
        $sentMessages = [];
        $service = $this->service($repository, static function (string $to, string $subject, string $html, array $attachments) use (&$sentMessages): bool {
            $sentMessages[] = compact('to', 'subject', 'html', 'attachments');

            return true;
        });

        $preview = $service->previewForRent($rentId, [$propertyId]);
        $this->assertIsArray($preview);
        $this->assertSame('locataire@example.com', $preview['recipientEmail']);
        $this->assertStringContainsString('Locataire relance', (string) $preview['message']);
        $this->assertStringContainsString('1000.00', (string) $preview['message']);
        $generated = (string) $preview['subject'] . "\n" . (string) $preview['message'] . "\n" . (string) $preview['signature'];
        $this->assertStringNotContainsString((string) ROOT_PATH, $generated);
        $this->assertStringNotContainsString('.env', $generated);
        $this->assertStringNotContainsString('database.override', $generated);

        $result = $service->send(
            $rentId,
            [$propertyId],
            'locataire@example.com',
            (string) $preview['subject'],
            (string) $preview['message'],
            (string) $preview['signature'],
            $ownerId
        );
        $this->assertSame('sent', $result['status']);
        $this->assertCount(1, $sentMessages);
        $requests = $repository->listPaymentRequestsForRents([$rentId]);
        $this->assertCount(1, $requests);
        $this->assertSame('sent', $requests[0]['status']);
        $this->assertSame('email', $requests[0]['channel']);
        $this->assertStringContainsString('@example.com', (string) ($requests[0]['snapshotPayload'] ?? ''));
        $this->assertMatchesRegularExpression('/"templateVersion":\s*1/', (string) ($requests[0]['snapshotPayload'] ?? ''));
        $this->assertStringNotContainsString('"recipientEmail":"locataire@example.com"', (string) ($requests[0]['snapshotPayload'] ?? ''));

        $duplicate = $service->send(
            $rentId,
            [$propertyId],
            'locataire@example.com',
            (string) $preview['subject'],
            (string) $preview['message'],
            (string) $preview['signature'],
            $ownerId
        );
        $this->assertSame('duplicate', $duplicate['status']);
        $this->assertCount(1, $sentMessages);
        $this->assertCount(1, $repository->listPaymentRequestsForRents([$rentId]));
    }

    public function testInvalidRecipientAndMailFailureAreRefusedOrAudited(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('request-failure@example.com', null);
        $rent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-05-01', 900.0);
        $rentId = (int) $rent['id'];
        $service = $this->service($repository, static fn (): bool => false);
        $preview = $service->previewForRent($rentId, [$propertyId]);
        $this->assertIsArray($preview);
        $this->assertSame('', $preview['recipientEmail']);

        $invalid = $service->send($rentId, [$propertyId], 'not-an-email', 'Sujet', 'Message', 'Signature', $ownerId);
        $this->assertSame('invalid_email', $invalid['status']);
        $this->assertSame([], $repository->listPaymentRequestsForRents([$rentId]));

        $failed = $service->send($rentId, [$propertyId], 'locataire@example.com', 'Sujet', 'Message', 'Signature', $ownerId);
        $this->assertSame('failed', $failed['status']);
        $requests = $repository->listPaymentRequestsForRents([$rentId]);
        $this->assertCount(1, $requests);
        $this->assertSame('failed', $requests[0]['status']);
        $this->assertSame('email_transport_failed', $requests[0]['failureReason']);
    }

    public function testPdfExportIsHistorizedAndForbiddenForPaidRent(): void
    {
        [$repository, $ownerId, $propertyId, $unitId, $leaseId] = $this->rentalContext('request-pdf@example.com', 'pdf@example.com');
        $pendingRent = $this->createRent($repository, $leaseId, $propertyId, $unitId, $ownerId, '2026-06-01', 850.0);
        $paidRent = $repository->createRent($leaseId, $propertyId, $unitId, 2026, 7, '2026-07-01', 850.0, 'paid', $ownerId, null);
        $this->assertIsArray($paidRent);
        $service = $this->service($repository, null);

        $result = $service->recordPdfExport((int) $pendingRent['id'], [$propertyId], 'pdf@example.com', 'Sujet PDF', 'Message PDF', 'Signature PDF', $ownerId);
        $this->assertSame('exported', $result['status']);
        $this->assertIsArray($result['request']);
        $pdf = $service->pdf($result['request'], $result['preview']);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Message PDF', $pdf);

        $this->assertNull($service->previewForRent((int) $paidRent['id'], [$propertyId]));
    }

    /**
     * @return array{0:RentalLifecycleRepository, 1:int, 2:int, 3:int, 4:int}
     */
    private function rentalContext(string $ownerEmail, ?string $tenantEmail): array
    {
        $database = $this->editorialSqlDatabase();
        $userRepository = new PrivateUserRepository($database);
        $propertyRepository = new RentalPropertyRepository($database);
        $memberRepository = new RentalPropertyMemberRepository($database);
        $unitRepository = new RentalUnitRepository($database);
        $lifecycleRepository = new RentalLifecycleRepository($database);
        $ownerId = $this->createPrivateUser($userRepository, $ownerEmail);

        $property = $propertyRepository->create($ownerId, 'Maison relance', '18 rue du Rappel', 'maison', 'indivision', 'active');
        $this->assertNotNull($property);
        $this->assertNotNull($memberRepository->create($property->id, $ownerId, 'owner', $ownerId));
        $unit = $unitRepository->create($property->id, 'Lot relance', 45.0, false, 'available', null, $ownerId);
        $this->assertNotNull($unit);
        $tenant = $lifecycleRepository->createTenant($property->id, $unit->id, 'Locataire relance', $tenantEmail, null, 'validated', $ownerId, null);
        $this->assertIsArray($tenant);
        $lease = $lifecycleRepository->createLease($property->id, $unit->id, (int) $tenant['id'], '2026-01-01', null, 800.0, 50.0, 'validated', $ownerId, null);
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

    private function service(RentalLifecycleRepository $repository, mixed $mailer): RentalPaymentRequestService
    {
        return new RentalPaymentRequestService(
            $repository,
            'Demande de paiement {{period}}',
            "Bonjour {{tenant_name}},\n\nSolde {{balance_due}} EUR pour {{property_name}} {{unit_label}}. Montant attendu {{amount_due}} EUR, encaisse {{amount_paid}} EUR.",
            "Gestion locative {{site_name}}\nContact : {{reply_to}}",
            $mailer
        );
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
