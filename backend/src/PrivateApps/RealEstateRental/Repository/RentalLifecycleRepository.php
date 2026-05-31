<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Repository;

use Caramagnols\Database\EditorialDatabase;
use Caramagnols\PrivateApps\RealEstateRental\Domain\RentalLeaseTypeCatalog;
use PDO;

final class RentalLifecycleRepository
{
    private const VALID_STATUSES = ['draft', 'validated', 'cancelled'];
    private const LEASE_STATUSES = ['draft', 'validated', 'cancelled', 'ended'];
    private const ACTIVE_LEASE_STATUSES = ['draft', 'validated'];
    private const MAX_TEXT_LENGTH = 160;
    private const MAX_NOTES_LENGTH = 2000;
    private bool $schemaReady = false;

    public function __construct(private readonly EditorialDatabase $database)
    {
    }

    public function tenantsTable(): string
    {
        return $this->database->table('rental_tenants');
    }

    public function leasesTable(): string
    {
        return $this->database->table('rental_leases');
    }

    public function paymentsTable(): string
    {
        return $this->database->table('rental_payments');
    }

    public function rentsTable(): string
    {
        return $this->database->table('rental_rents');
    }

    public function expensesTable(): string
    {
        return $this->database->table('rental_expenses');
    }

    public function documentsTable(): string
    {
        return $this->database->table('rental_documents');
    }

    public function exportLogsTable(): string
    {
        return $this->database->table('rental_export_logs');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createTenant(
        int $propertyId,
        int $unitId,
        string $fullName,
        ?string $email,
        ?string $phone,
        string $status,
        int $actorPrivateUserId,
        ?string $notes = null
    ): ?array {
        $fullName = $this->normalizeText($fullName, self::MAX_TEXT_LENGTH);
        $email = $email !== null ? strtolower($this->normalizeText($email, 190)) : null;
        $phone = $phone !== null ? $this->normalizeText($phone, 64) : null;
        $notes = $notes !== null ? $this->normalizeText($notes, self::MAX_NOTES_LENGTH) : null;
        $status = $this->normalizeStatus($status, self::VALID_STATUSES);

        if (
            $propertyId <= 0
            || $unitId <= 0
            || $actorPrivateUserId <= 0
            || $fullName === ''
            || $status === ''
            || ($email !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_property_id`, `rental_unit_id`, `full_name`, `email`, `phone`, `status`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:property_id, :unit_id, :full_name, :email, :phone, :status, :notes, :created_by)',
                    $this->tenantsTable()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $actorPrivateUserId,
            ]);

            return $this->findTenantById((int) $this->database->pdo()->lastInsertId());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findTenantById(int $tenantId): ?array
    {
        if ($tenantId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->tenantsTable())
            );
            $statement->execute(['id' => $tenantId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateTenant(
        int $tenantId,
        int $propertyId,
        int $unitId,
        string $fullName,
        ?string $email,
        ?string $phone,
        string $status,
        ?string $notes = null
    ): ?array {
        $fullName = $this->normalizeText($fullName, self::MAX_TEXT_LENGTH);
        $email = $email !== null ? strtolower($this->normalizeText($email, 190)) : null;
        $phone = $phone !== null ? $this->normalizeText($phone, 64) : null;
        $notes = $notes !== null ? $this->normalizeText($notes, self::MAX_NOTES_LENGTH) : null;
        $status = $this->normalizeStatus($status, self::VALID_STATUSES);

        if (
            $tenantId <= 0
            || $propertyId <= 0
            || $unitId <= 0
            || $fullName === ''
            || $status === ''
            || ($email !== null && $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false)
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `rental_property_id` = :property_id,
                         `rental_unit_id` = :unit_id,
                         `full_name` = :full_name,
                         `email` = :email,
                         `phone` = :phone,
                         `status` = :status,
                         `notes` = :notes,
                         `updated_at` = :updated_at
                     WHERE `id` = :id
                       AND `is_active` = 1',
                    $this->tenantsTable()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'full_name' => $fullName,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $tenantId,
            ]);
        } catch (\Throwable) {
            return null;
        }

        return $this->findTenantById($tenantId);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listTenants(array $propertyIds, int $limit = 200): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $limit = $this->normalizeLimit($limit);
        if ($propertyIds === [] || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT t.*, p.`name` AS `property_name`, u.`label` AS `unit_label`
                     FROM `%s` t
                     INNER JOIN `%s` p ON p.`id` = t.`rental_property_id`
                     LEFT JOIN `%s` u ON u.`id` = t.`rental_unit_id`
                     WHERE t.`rental_property_id` IN (%s)
                       AND t.`is_active` = 1
                     ORDER BY t.`rental_property_id` ASC, u.`label` ASC, t.`full_name` ASC, t.`id` ASC
                     LIMIT :limit',
                    $this->tenantsTable(),
                    $this->database->table('rental_properties'),
                    $this->database->table('rental_units'),
                    implode(',', $placeholders)
                )
            );
            $this->bindIds($statement, 'property_id', $propertyIds);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createLease(
        int $propertyId,
        int $unitId,
        int $tenantId,
        string $startDate,
        ?string $endDate,
        float $monthlyRent,
        float $chargesProvision,
        string $status,
        int $actorPrivateUserId,
        ?string $notes = null,
        string $leaseType = RentalLeaseTypeCatalog::DEFAULT
    ): ?array {
        $leaseType = RentalLeaseTypeCatalog::normalize($leaseType);
        $taxCategory = RentalLeaseTypeCatalog::taxCategory($leaseType);
        $status = $this->normalizeStatus($status, self::LEASE_STATUSES);
        $startDate = $this->normalizeDate($startDate);
        $endDate = $endDate !== null && trim($endDate) !== '' ? $this->normalizeDate($endDate) : null;
        $endDate ??= RentalLeaseTypeCatalog::defaultEndDate($leaseType, $startDate);
        $notes = $notes !== null ? $this->normalizeText($notes, self::MAX_NOTES_LENGTH) : null;
        $monthlyRent = round($monthlyRent, 2);
        $chargesProvision = round($chargesProvision, 2);

        if (
            $propertyId <= 0
            || $unitId <= 0
            || $tenantId <= 0
            || $actorPrivateUserId <= 0
            || $startDate === ''
            || $status === ''
            || $monthlyRent <= 0
            || $chargesProvision < 0
            || ($endDate !== null && $endDate < $startDate)
        ) {
            return null;
        }

        if (!$this->unitCanReceiveNewLease($propertyId, $unitId) || $this->hasActiveLeaseForUnit($unitId)) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_property_id`, `rental_unit_id`, `rental_tenant_id`, `lease_type`, `tax_category`,
                         `start_date`, `end_date`,
                         `monthly_rent`, `charges_provision`, `status`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:property_id, :unit_id, :tenant_id, :lease_type, :tax_category,
                         :start_date, :end_date,
                         :monthly_rent, :charges_provision, :status, :notes, :created_by)',
                    $this->leasesTable()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'tenant_id' => $tenantId,
                'lease_type' => $leaseType,
                'tax_category' => $taxCategory,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'monthly_rent' => $monthlyRent,
                'charges_provision' => $chargesProvision,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $actorPrivateUserId,
            ]);

            return $this->findLeaseById((int) $this->database->pdo()->lastInsertId());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLeaseById(int $leaseId): ?array
    {
        if ($leaseId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->leasesTable())
            );
            $statement->execute(['id' => $leaseId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function hasActiveLeaseForUnit(int $unitId, int $excludedLeaseId = 0): bool
    {
        if ($unitId <= 0) {
            return true;
        }

        try {
            $this->ensureSchema();
            $excludedClause = $excludedLeaseId > 0 ? ' AND `id` <> :excluded_lease_id' : '';
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*)
                     FROM `%s`
                     WHERE `rental_unit_id` = :unit_id
                       AND `status` IN ("draft", "validated")%s',
                    $this->leasesTable(),
                    $excludedClause
                )
            );
            $statement->bindValue(':unit_id', $unitId, PDO::PARAM_INT);
            if ($excludedLeaseId > 0) {
                $statement->bindValue(':excluded_lease_id', $excludedLeaseId, PDO::PARAM_INT);
            }

            $statement->execute();
            return (int) $statement->fetchColumn() > 0;
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<string, mixed>|null
     */
    public function updateLease(
        int $leaseId,
        array $propertyIds,
        int $propertyId,
        int $unitId,
        int $tenantId,
        string $startDate,
        ?string $endDate,
        float $monthlyRent,
        float $chargesProvision,
        string $status,
        ?string $notes = null,
        string $leaseType = RentalLeaseTypeCatalog::DEFAULT
    ): ?array {
        $propertyIds = $this->normalizeIds($propertyIds);
        $leaseType = RentalLeaseTypeCatalog::normalize($leaseType);
        $taxCategory = RentalLeaseTypeCatalog::taxCategory($leaseType);
        $status = $this->normalizeStatus($status, self::LEASE_STATUSES);
        $startDate = $this->normalizeDate($startDate);
        $endDate = $endDate !== null && trim($endDate) !== '' ? $this->normalizeDate($endDate) : null;
        $endDate ??= RentalLeaseTypeCatalog::defaultEndDate($leaseType, $startDate);
        $notes = $notes !== null ? $this->normalizeText($notes, self::MAX_NOTES_LENGTH) : null;
        $monthlyRent = round($monthlyRent, 2);
        $chargesProvision = round($chargesProvision, 2);

        if (
            $leaseId <= 0
            || $propertyIds === []
            || !in_array($propertyId, $propertyIds, true)
            || $propertyId <= 0
            || $unitId <= 0
            || $tenantId <= 0
            || $startDate === ''
            || $status === ''
            || $monthlyRent <= 0
            || $chargesProvision < 0
            || ($endDate !== null && $endDate < $startDate)
        ) {
            return null;
        }

        $current = $this->findLeaseById($leaseId);
        if (!is_array($current)) {
            return null;
        }

        if (in_array($status, self::ACTIVE_LEASE_STATUSES, true)) {
            $currentUnitId = is_numeric($current['rentalUnitId'] ?? null) ? (int) $current['rentalUnitId'] : 0;
            $currentStatus = is_string($current['status'] ?? null) ? (string) $current['status'] : '';
            $sameActiveLease = $currentUnitId === $unitId && in_array($currentStatus, self::ACTIVE_LEASE_STATUSES, true);
            if ($this->hasActiveLeaseForUnit($unitId, $leaseId)) {
                return null;
            }
            if (!$sameActiveLease && !$this->unitCanReceiveNewLease($propertyId, $unitId)) {
                return null;
            }
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `rental_property_id` = :property_id,
                         `rental_unit_id` = :unit_id,
                         `rental_tenant_id` = :tenant_id,
                         `lease_type` = :lease_type,
                         `tax_category` = :tax_category,
                         `start_date` = :start_date,
                         `end_date` = :end_date,
                         `monthly_rent` = :monthly_rent,
                         `charges_provision` = :charges_provision,
                         `status` = :status,
                         `notes` = :notes,
                         `updated_at` = :updated_at
                     WHERE `id` = :id
                       AND `rental_property_id` IN (%s)',
                    $this->leasesTable(),
                    implode(',', $placeholders)
                )
            );
            $statement->bindValue(':property_id', $propertyId, PDO::PARAM_INT);
            $statement->bindValue(':unit_id', $unitId, PDO::PARAM_INT);
            $statement->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $statement->bindValue(':lease_type', $leaseType);
            $statement->bindValue(':tax_category', $taxCategory);
            $statement->bindValue(':start_date', $startDate);
            if ($endDate === null) {
                $statement->bindValue(':end_date', null, PDO::PARAM_NULL);
            } else {
                $statement->bindValue(':end_date', $endDate);
            }
            $statement->bindValue(':monthly_rent', $monthlyRent);
            $statement->bindValue(':charges_provision', $chargesProvision);
            $statement->bindValue(':status', $status);
            if ($notes === null || $notes === '') {
                $statement->bindValue(':notes', null, PDO::PARAM_NULL);
            } else {
                $statement->bindValue(':notes', $notes);
            }
            $statement->bindValue(':updated_at', date('Y-m-d H:i:s'));
            $statement->bindValue(':id', $leaseId, PDO::PARAM_INT);
            $this->bindIds($statement, 'property_id', $propertyIds);
            $statement->execute();
        } catch (\Throwable) {
            return null;
        }

        $updated = $this->findLeaseById($leaseId);
        $updatedPropertyId = is_array($updated) && is_numeric($updated['rentalPropertyId'] ?? null)
            ? (int) $updated['rentalPropertyId']
            : 0;

        return $updatedPropertyId === $propertyId && in_array($updatedPropertyId, $propertyIds, true) ? $updated : null;
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listLeases(array $propertyIds, int $limit = 200): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $limit = $this->normalizeLimit($limit);
        if ($propertyIds === [] || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT l.*, p.`name` AS `property_name`, u.`label` AS `unit_label`, t.`full_name` AS `tenant_name`
                     FROM `%s` l
                     INNER JOIN `%s` p ON p.`id` = l.`rental_property_id`
                     INNER JOIN `%s` u ON u.`id` = l.`rental_unit_id`
                     INNER JOIN `%s` t ON t.`id` = l.`rental_tenant_id`
                     WHERE l.`rental_property_id` IN (%s)
                     ORDER BY l.`start_date` DESC, l.`id` DESC
                     LIMIT :limit',
                    $this->leasesTable(),
                    $this->database->table('rental_properties'),
                    $this->database->table('rental_units'),
                    $this->tenantsTable(),
                    implode(',', $placeholders)
                )
            );
            $this->bindIds($statement, 'property_id', $propertyIds);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createRent(
        int $leaseId,
        int $propertyId,
        int $unitId,
        int $periodYear,
        int $periodMonth,
        string $dueDate,
        float $amountDue,
        string $status,
        int $actorPrivateUserId,
        ?string $notes = null
    ): ?array {
        $dueDate = $this->normalizeDate($dueDate);
        $status = $this->normalizeStatus($status, self::VALID_STATUSES);
        $notes = $notes !== null ? $this->normalizeText($notes, self::MAX_NOTES_LENGTH) : null;
        $amountDue = round($amountDue, 2);

        if (
            $leaseId <= 0
            || $propertyId <= 0
            || $unitId <= 0
            || $periodYear < 2000
            || $periodYear > 2100
            || $periodMonth < 1
            || $periodMonth > 12
            || $dueDate === ''
            || $amountDue <= 0
            || $status === ''
            || $actorPrivateUserId <= 0
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            if ($this->findRentByLeasePeriod($leaseId, $periodYear, $periodMonth) !== null) {
                return null;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_lease_id`, `rental_property_id`, `rental_unit_id`, `period_year`, `period_month`,
                         `due_date`, `amount_due`, `status`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:lease_id, :property_id, :unit_id, :period_year, :period_month,
                         :due_date, :amount_due, :status, :notes, :created_by)',
                    $this->rentsTable()
                )
            );
            $statement->execute([
                'lease_id' => $leaseId,
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'due_date' => $dueDate,
                'amount_due' => $amountDue,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $actorPrivateUserId,
            ]);

            return $this->findRentById((int) $this->database->pdo()->lastInsertId());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRentById(int $rentId): ?array
    {
        if ($rentId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->rentsTable())
            );
            $statement->execute(['id' => $rentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRentByLeasePeriod(int $leaseId, int $periodYear, int $periodMonth): ?array
    {
        if ($leaseId <= 0 || $periodYear < 2000 || $periodYear > 2100 || $periodMonth < 1 || $periodMonth > 12) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT * FROM `%s`
                     WHERE `rental_lease_id` = :lease_id
                       AND `period_year` = :period_year
                       AND `period_month` = :period_month
                     LIMIT 1',
                    $this->rentsTable()
                )
            );
            $statement->execute([
                'lease_id' => $leaseId,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
            ]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listRents(array $propertyIds, ?int $year = null, int $limit = 500): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $limit = $this->normalizeLimit($limit);
        if ($propertyIds === [] || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $sql = sprintf(
                'SELECT rent.*,
                        prop.`name` AS `property_name`,
                        u.`label` AS `unit_label`,
                        l.`lease_type` AS `lease_type`,
                        l.`tax_category` AS `tax_category`,
                        t.`full_name` AS `tenant_name`,
                        COALESCE(pmt_sum.`amount_paid`, 0) AS `amount_paid`,
                        COALESCE(pmt_sum.`payment_count`, 0) AS `payment_count`
                 FROM `%s` rent
                 INNER JOIN `%s` prop ON prop.`id` = rent.`rental_property_id`
                 INNER JOIN `%s` u ON u.`id` = rent.`rental_unit_id`
                 INNER JOIN `%s` l ON l.`id` = rent.`rental_lease_id`
                 INNER JOIN `%s` t ON t.`id` = l.`rental_tenant_id`
                 LEFT JOIN (
                    SELECT `rental_rent_id`,
                           SUM(CASE WHEN `status` = "validated" THEN `amount_paid` ELSE 0 END) AS `amount_paid`,
                           COUNT(`id`) AS `payment_count`
                    FROM `%s`
                    WHERE `rental_rent_id` IS NOT NULL
                    GROUP BY `rental_rent_id`
                 ) pmt_sum ON pmt_sum.`rental_rent_id` = rent.`id`
                 WHERE rent.`rental_property_id` IN (%s)',
                $this->rentsTable(),
                $this->database->table('rental_properties'),
                $this->database->table('rental_units'),
                $this->leasesTable(),
                $this->tenantsTable(),
                $this->paymentsTable(),
                implode(',', $placeholders)
            );
            if ($year !== null) {
                $sql .= ' AND rent.`period_year` = :year';
            }
            $sql .= ' ORDER BY rent.`period_year` DESC, rent.`period_month` DESC, rent.`id` DESC
                      LIMIT :limit';
            $statement = $this->database->pdo()->prepare($sql);
            $this->bindIds($statement, 'property_id', $propertyIds);
            if ($year !== null) {
                $statement->bindValue(':year', $year, PDO::PARAM_INT);
            }
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @param array<int, int> $propertyIds
     */
    public function deleteRent(int $rentId, array $propertyIds): bool
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        if ($rentId <= 0 || $propertyIds === []) {
            return false;
        }

        try {
            $this->ensureSchema();
            $rent = $this->findRentById($rentId);
            $rentPropertyId = is_array($rent) && is_numeric($rent['rentalPropertyId'] ?? null)
                ? (int) $rent['rentalPropertyId']
                : 0;
            if (!in_array($rentPropertyId, $propertyIds, true)) {
                return false;
            }

            $this->database->pdo()->prepare(
                sprintf('UPDATE `%s` SET `rental_rent_id` = NULL WHERE `rental_rent_id` = :id', $this->paymentsTable())
            )->execute(['id' => $rentId]);
        } catch (\Throwable) {
            return false;
        }

        return $this->deleteRowByPropertyIds($this->rentsTable(), $rentId, $propertyIds);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createPayment(
        int $leaseId,
        int $propertyId,
        int $unitId,
        string $paymentDate,
        int $periodYear,
        int $periodMonth,
        float $amountDue,
        float $amountPaid,
        string $status,
        int $actorPrivateUserId,
        ?string $notes = null,
        ?int $rentId = null
    ): ?array {
        $paymentDate = $this->normalizeDate($paymentDate);
        $status = $this->normalizeStatus($status, self::VALID_STATUSES);
        $notes = $notes !== null ? $this->normalizeText($notes, self::MAX_NOTES_LENGTH) : null;
        $amountDue = round($amountDue, 2);
        $amountPaid = round($amountPaid, 2);
        $rentId = $rentId !== null && $rentId > 0 ? $rentId : null;

        if (
            $leaseId <= 0
            || $propertyId <= 0
            || $unitId <= 0
            || $actorPrivateUserId <= 0
            || $paymentDate === ''
            || $periodYear < 2000
            || $periodYear > 2100
            || $periodMonth < 1
            || $periodMonth > 12
            || $amountDue < 0
            || $amountPaid < 0
            || ($amountDue <= 0 && $amountPaid <= 0)
            || $status === ''
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            if ($rentId === null && $amountDue > 0) {
                $rent = $this->findRentByLeasePeriod($leaseId, $periodYear, $periodMonth);
                if (!is_array($rent)) {
                    $rent = $this->createRent(
                        $leaseId,
                        $propertyId,
                        $unitId,
                        $periodYear,
                        $periodMonth,
                        sprintf('%04d-%02d-01', $periodYear, $periodMonth),
                        $amountDue,
                        $status,
                        $actorPrivateUserId,
                        null
                    );
                }
                $rentId = is_array($rent) && is_numeric($rent['id'] ?? null) ? (int) $rent['id'] : null;
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_rent_id`, `rental_lease_id`, `rental_property_id`, `rental_unit_id`, `payment_date`, `period_year`,
                         `period_month`, `amount_due`, `amount_paid`, `status`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:rent_id, :lease_id, :property_id, :unit_id, :payment_date, :period_year,
                         :period_month, :amount_due, :amount_paid, :status, :notes, :created_by)',
                    $this->paymentsTable()
                )
            );
            $statement->execute([
                'rent_id' => $rentId,
                'lease_id' => $leaseId,
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'payment_date' => $paymentDate,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
                'amount_due' => $amountDue,
                'amount_paid' => $amountPaid,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $actorPrivateUserId,
            ]);

            return $this->findPaymentById((int) $this->database->pdo()->lastInsertId());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPaymentById(int $paymentId): ?array
    {
        if ($paymentId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->paymentsTable())
            );
            $statement->execute(['id' => $paymentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listPayments(array $propertyIds, ?int $year = null, int $limit = 500): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $limit = $this->normalizeLimit($limit);
        if ($propertyIds === [] || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $sql = sprintf(
                'SELECT pmt.*, prop.`name` AS `property_name`, u.`label` AS `unit_label`,
                        l.`start_date` AS `lease_start_date`,
                        l.`lease_type` AS `lease_type`,
                        l.`tax_category` AS `tax_category`,
                        rent.`period_year` AS `rent_period_year`,
                        rent.`period_month` AS `rent_period_month`,
                        rent.`amount_due` AS `rent_amount_due`,
                        rent.`status` AS `rent_status`,
                        t.`full_name` AS `tenant_name`
                 FROM `%s` pmt
                 INNER JOIN `%s` prop ON prop.`id` = pmt.`rental_property_id`
                 INNER JOIN `%s` u ON u.`id` = pmt.`rental_unit_id`
                 INNER JOIN `%s` l ON l.`id` = pmt.`rental_lease_id`
                 LEFT JOIN `%s` rent ON rent.`id` = pmt.`rental_rent_id`
                 LEFT JOIN `%s` t ON t.`id` = l.`rental_tenant_id`
                 WHERE pmt.`rental_property_id` IN (%s)',
                $this->paymentsTable(),
                $this->database->table('rental_properties'),
                $this->database->table('rental_units'),
                $this->leasesTable(),
                $this->rentsTable(),
                $this->tenantsTable(),
                implode(',', $placeholders)
            );
            if ($year !== null) {
                $sql .= ' AND pmt.`period_year` = :year';
            }
            $sql .= ' ORDER BY pmt.`period_year` DESC, pmt.`period_month` DESC, pmt.`id` DESC LIMIT :limit';
            $statement = $this->database->pdo()->prepare($sql);
            $this->bindIds($statement, 'property_id', $propertyIds);
            if ($year !== null) {
                $statement->bindValue(':year', $year, PDO::PARAM_INT);
            }
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createExpense(
        int $propertyId,
        ?int $unitId,
        string $expenseDate,
        string $label,
        float $amount,
        bool $recoverable,
        bool $deductibleCandidate,
        string $status,
        int $actorPrivateUserId,
        ?string $notes = null
    ): ?array {
        $expenseDate = $this->normalizeDate($expenseDate);
        $label = $this->normalizeText($label, self::MAX_TEXT_LENGTH);
        $status = $this->normalizeStatus($status, self::VALID_STATUSES);
        $notes = $notes !== null ? $this->normalizeText($notes, self::MAX_NOTES_LENGTH) : null;
        $amount = round($amount, 2);
        $unitId = $unitId !== null && $unitId > 0 ? $unitId : null;

        if (
            $propertyId <= 0
            || $actorPrivateUserId <= 0
            || $expenseDate === ''
            || $label === ''
            || $amount <= 0
            || $status === ''
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_property_id`, `rental_unit_id`, `expense_date`, `label`, `amount`, `is_recoverable`,
                         `is_deductible_candidate`, `status`, `notes`, `created_by_private_user_id`)
                     VALUES
                        (:property_id, :unit_id, :expense_date, :label, :amount, :is_recoverable,
                         :is_deductible_candidate, :status, :notes, :created_by)',
                    $this->expensesTable()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'expense_date' => $expenseDate,
                'label' => $label,
                'amount' => $amount,
                'is_recoverable' => $recoverable ? 1 : 0,
                'is_deductible_candidate' => $deductibleCandidate ? 1 : 0,
                'status' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $actorPrivateUserId,
            ]);

            return $this->findExpenseById((int) $this->database->pdo()->lastInsertId());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findExpenseById(int $expenseId): ?array
    {
        if ($expenseId <= 0) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `id` = :id LIMIT 1', $this->expensesTable())
            );
            $statement->execute(['id' => $expenseId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listExpenses(array $propertyIds, ?int $year = null, int $limit = 500): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $limit = $this->normalizeLimit($limit);
        if ($propertyIds === [] || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $sql = sprintf(
                'SELECT exp.*, prop.`name` AS `property_name`, u.`label` AS `unit_label`
                 FROM `%s` exp
                 INNER JOIN `%s` prop ON prop.`id` = exp.`rental_property_id`
                 LEFT JOIN `%s` u ON u.`id` = exp.`rental_unit_id`
                 WHERE exp.`rental_property_id` IN (%s)',
                $this->expensesTable(),
                $this->database->table('rental_properties'),
                $this->database->table('rental_units'),
                implode(',', $placeholders)
            );
            if ($year !== null) {
                $sql .= ' AND YEAR(exp.`expense_date`) = :year';
            }
            $sql .= ' ORDER BY exp.`expense_date` DESC, exp.`id` DESC LIMIT :limit';
            $statement = $this->database->pdo()->prepare($sql);
            $this->bindIds($statement, 'property_id', $propertyIds);
            if ($year !== null) {
                $statement->bindValue(':year', $year, PDO::PARAM_INT);
            }
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function createDocument(
        int $propertyId,
        ?int $unitId,
        ?int $leaseId,
        string $documentId,
        string $storagePath,
        string $originalName,
        string $extension,
        string $mimeType,
        int $sizeBytes,
        int $actorPrivateUserId
    ): ?array {
        $documentId = $this->normalizeIdentifier($documentId);
        $storagePath = trim(str_replace('\\', '/', $storagePath));
        $originalName = $this->normalizeText($originalName, 255);
        $extension = strtolower($this->normalizeText($extension, 16));
        $mimeType = strtolower($this->normalizeText($mimeType, 120));
        $unitId = $unitId !== null && $unitId > 0 ? $unitId : null;
        $leaseId = $leaseId !== null && $leaseId > 0 ? $leaseId : null;

        if (
            $propertyId <= 0
            || $actorPrivateUserId <= 0
            || $documentId === ''
            || $storagePath === ''
            || $originalName === ''
            || $extension === ''
            || $mimeType === ''
            || $sizeBytes <= 0
            || !str_starts_with($storagePath, 'uploads/')
            || str_contains($storagePath, '..')
        ) {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s`
                        (`rental_property_id`, `rental_unit_id`, `rental_lease_id`, `document_id`, `storage_path`,
                         `original_name`, `extension`, `mime_type`, `size_bytes`, `uploaded_by_private_user_id`)
                     VALUES
                        (:property_id, :unit_id, :lease_id, :document_id, :storage_path,
                         :original_name, :extension, :mime_type, :size_bytes, :uploaded_by)',
                    $this->documentsTable()
                )
            );
            $statement->execute([
                'property_id' => $propertyId,
                'unit_id' => $unitId,
                'lease_id' => $leaseId,
                'document_id' => $documentId,
                'storage_path' => $storagePath,
                'original_name' => $originalName,
                'extension' => $extension,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'uploaded_by' => $actorPrivateUserId,
            ]);

            return $this->findDocumentByDocumentId($documentId);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDocumentByDocumentId(string $documentId): ?array
    {
        $documentId = $this->normalizeIdentifier($documentId);
        if ($documentId === '') {
            return null;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf('SELECT * FROM `%s` WHERE `document_id` = :document_id AND `is_active` = 1 LIMIT 1', $this->documentsTable())
            );
            $statement->execute(['document_id' => $documentId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, array<string, mixed>>
     */
    public function listDocuments(array $propertyIds, int $limit = 200): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $limit = $this->normalizeLimit($limit);
        if ($propertyIds === [] || $limit <= 0) {
            return [];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT doc.*, prop.`name` AS `property_name`, u.`label` AS `unit_label`
                     FROM `%s` doc
                     INNER JOIN `%s` prop ON prop.`id` = doc.`rental_property_id`
                     LEFT JOIN `%s` u ON u.`id` = doc.`rental_unit_id`
                     WHERE doc.`rental_property_id` IN (%s)
                       AND doc.`is_active` = 1
                     ORDER BY doc.`uploaded_at` DESC, doc.`id` DESC
                     LIMIT :limit',
                    $this->documentsTable(),
                    $this->database->table('rental_properties'),
                    $this->database->table('rental_units'),
                    implode(',', $placeholders)
                )
            );
            $this->bindIds($statement, 'property_id', $propertyIds);
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }

        return $this->normalizeRows($rows);
    }

    /**
     * @param array<int, int> $propertyIds
     */
    public function deactivateDocumentByDocumentId(string $documentId, array $propertyIds): bool
    {
        $documentId = $this->normalizeIdentifier($documentId);
        $propertyIds = $this->normalizeIds($propertyIds);
        if ($documentId === '' || $propertyIds === []) {
            return false;
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s`
                     SET `is_active` = 0
                     WHERE `document_id` = :document_id
                       AND `rental_property_id` IN (%s)
                       AND `is_active` = 1',
                    $this->documentsTable(),
                    implode(',', $placeholders)
                )
            );
            $statement->bindValue(':document_id', $documentId, PDO::PARAM_STR);
            $this->bindIds($statement, 'property_id', $propertyIds);
            $statement->execute();

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, int> $propertyIds
     */
    public function deleteTenant(int $tenantId, array $propertyIds): bool
    {
        return $this->deleteByPropertyIds(
            $this->tenantsTable(),
            $tenantId,
            $propertyIds,
            '`full_name` = :label, `email` = NULL, `phone` = NULL, `notes` = NULL, `status` = "cancelled", `is_active` = 0',
            ['label' => 'Locataire supprime #' . $tenantId]
        );
    }

    /**
     * @param array<int, int> $propertyIds
     */
    public function deleteLease(int $leaseId, array $propertyIds): bool
    {
        if ($leaseId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $this->database->pdo()->prepare(
                sprintf('UPDATE `%s` SET `rental_lease_id` = NULL WHERE `rental_lease_id` = :id', $this->documentsTable())
            )->execute(['id' => $leaseId]);
        } catch (\Throwable) {
            return false;
        }

        return $this->deleteRowByPropertyIds($this->leasesTable(), $leaseId, $propertyIds);
    }

    /**
     * @param array<int, int> $propertyIds
     */
    public function deletePayment(int $paymentId, array $propertyIds): bool
    {
        return $this->deleteRowByPropertyIds($this->paymentsTable(), $paymentId, $propertyIds);
    }

    /**
     * @param array<int, int> $propertyIds
     */
    public function deleteExpense(int $expenseId, array $propertyIds): bool
    {
        return $this->deleteRowByPropertyIds($this->expensesTable(), $expenseId, $propertyIds);
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array{tenants:int, leases:int, payments:int, expenses:int, documents:int}
     */
    public function deleteLifecycleDataByPropertyIds(array $propertyIds): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        $deleted = ['tenants' => 0, 'leases' => 0, 'rents' => 0, 'payments' => 0, 'expenses' => 0, 'documents' => 0];
        if ($propertyIds === []) {
            return $deleted;
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $pdo = $this->database->pdo();
            $pdo->beginTransaction();

            $deleteDefinitions = [
                'documents' => ['table' => $this->documentsTable(), 'sql' => 'UPDATE `%s` SET `is_active` = 0 WHERE `rental_property_id` IN (%s) AND `is_active` = 1'],
                'payments' => ['table' => $this->paymentsTable(), 'sql' => 'DELETE FROM `%s` WHERE `rental_property_id` IN (%s)'],
                'rents' => ['table' => $this->rentsTable(), 'sql' => 'DELETE FROM `%s` WHERE `rental_property_id` IN (%s)'],
                'expenses' => ['table' => $this->expensesTable(), 'sql' => 'DELETE FROM `%s` WHERE `rental_property_id` IN (%s)'],
                'leases' => ['table' => $this->leasesTable(), 'sql' => 'DELETE FROM `%s` WHERE `rental_property_id` IN (%s)'],
                'tenants' => ['table' => $this->tenantsTable(), 'sql' => 'UPDATE `%s` SET `full_name` = \'Locataire supprime\', `email` = NULL, `phone` = NULL, `notes` = NULL, `status` = \'cancelled\', `is_active` = 0 WHERE `rental_property_id` IN (%s) AND `is_active` = 1'],
            ];

            foreach ($deleteDefinitions as $key => $definition) {
                $statement = $pdo->prepare(sprintf($definition['sql'], $definition['table'], implode(',', $placeholders)));
                $this->bindIds($statement, 'property_id', $propertyIds);
                $statement->execute();
                $deleted[$key] = $statement->rowCount();
            }

            $pdo->commit();
        } catch (\Throwable) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['tenants' => 0, 'leases' => 0, 'rents' => 0, 'payments' => 0, 'expenses' => 0, 'documents' => 0];
        }

        return $deleted;
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, string>
     */
    public function draftIssues(array $propertyIds, int $year): array
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        if ($propertyIds === [] || $year < 2000 || $year > 2100) {
            return ['Aucun bien autorise pour la synthese annuelle.'];
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $issues = [];

            $issues = array_merge(
                $issues,
                $this->draftIssueRows(
                    sprintf(
                        'SELECT CONCAT("Bail brouillon #", `id`) AS label
                         FROM `%s`
                         WHERE `rental_property_id` IN (%s)
                           AND `status` = "draft"
                           AND `start_date` <= :year_end
                           AND (`end_date` IS NULL OR `end_date` >= :year_start)',
                        $this->leasesTable(),
                        implode(',', $placeholders)
                    ),
                    $propertyIds,
                    $year
                )
            );
            $issues = array_merge(
                $issues,
                $this->draftIssueRows(
                    sprintf(
                        'SELECT CONCAT("Loyer brouillon #", `id`) AS label
                         FROM `%s`
                         WHERE `rental_property_id` IN (%s)
                           AND `status` = "draft"
                           AND `period_year` = :year',
                        $this->rentsTable(),
                        implode(',', $placeholders)
                    ),
                    $propertyIds,
                    $year
                )
            );
            $issues = array_merge(
                $issues,
                $this->draftIssueRows(
                    sprintf(
                        'SELECT CONCAT("Paiement brouillon #", `id`) AS label
                         FROM `%s`
                         WHERE `rental_property_id` IN (%s)
                           AND `status` = "draft"
                           AND `period_year` = :year',
                        $this->paymentsTable(),
                        implode(',', $placeholders)
                    ),
                    $propertyIds,
                    $year
                )
            );
            $issues = array_merge(
                $issues,
                $this->draftIssueRows(
                    sprintf(
                        'SELECT CONCAT("Charge brouillon #", `id`) AS label
                         FROM `%s`
                         WHERE `rental_property_id` IN (%s)
                           AND `status` = "draft"
                           AND YEAR(`expense_date`) = :year',
                        $this->expensesTable(),
                        implode(',', $placeholders)
                    ),
                    $propertyIds,
                    $year
                )
            );
        } catch (\Throwable) {
            return ['Verification des brouillons locatifs impossible.'];
        }

        return array_values(array_unique($issues));
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function createExportLog(int $privateUserId, int $year, string $format, array $summary): bool
    {
        $format = strtolower(trim($format));
        if ($privateUserId <= 0 || $year < 2000 || $year > 2100 || !in_array($format, ['csv', 'pdf'], true)) {
            return false;
        }

        try {
            $this->ensureSchema();
            $payload = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) {
                $payload = '{}';
            }

            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'INSERT INTO `%s` (`private_user_id`, `year`, `format`, `summary_payload`)
                     VALUES (:private_user_id, :year, :format, :summary_payload)',
                    $this->exportLogsTable()
                )
            );

            return $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'format' => $format,
                'summary_payload' => $payload,
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    public function countExportLogs(int $privateUserId, int $year, string $format): int
    {
        $format = strtolower(trim($format));
        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*) FROM `%s` WHERE `private_user_id` = :private_user_id AND `year` = :year AND `format` = :format',
                    $this->exportLogsTable()
                )
            );
            $statement->execute([
                'private_user_id' => $privateUserId,
                'year' => $year,
                'format' => $format,
            ]);

            return (int) $statement->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function ensureSchema(): void
    {
        if ($this->schemaReady) {
            return;
        }

        $this->database->ensureReady();
        $pdo = $this->database->pdo();
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_property_id` INT NOT NULL,
                    `rental_unit_id` INT NULL,
                    `full_name` VARCHAR(160) NOT NULL,
                    `email` VARCHAR(190) NULL,
                    `phone` VARCHAR(64) NULL,
                    `status` ENUM("draft", "validated", "cancelled") NOT NULL DEFAULT "draft",
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `notes` TEXT NULL,
                    `created_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_rental_tenants_property` (`rental_property_id`, `is_active`),
                    KEY `idx_rental_tenants_unit` (`rental_unit_id`, `is_active`),
                    KEY `idx_rental_tenants_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->tenantsTable()
            )
        );
        $this->ensureColumn($pdo, $this->tenantsTable(), 'rental_unit_id', '`rental_unit_id` INT NULL AFTER `rental_property_id`');
        $this->ensureIndex($pdo, $this->tenantsTable(), 'idx_rental_tenants_unit', '`rental_unit_id`, `is_active`');
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_property_id` INT NOT NULL,
                    `rental_unit_id` INT NOT NULL,
                    `rental_tenant_id` INT NOT NULL,
                    `lease_type` VARCHAR(64) NOT NULL DEFAULT "residential_unfurnished",
                    `tax_category` VARCHAR(64) NOT NULL DEFAULT "property_income",
                    `start_date` DATE NOT NULL,
                    `end_date` DATE NULL,
                    `monthly_rent` DECIMAL(10,2) NOT NULL,
                    `charges_provision` DECIMAL(10,2) NOT NULL DEFAULT 0,
                    `status` ENUM("draft", "validated", "cancelled", "ended") NOT NULL DEFAULT "draft",
                    `notes` TEXT NULL,
                    `created_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_rental_leases_property` (`rental_property_id`, `status`),
                    KEY `idx_rental_leases_type` (`lease_type`, `tax_category`),
                    KEY `idx_rental_leases_unit` (`rental_unit_id`),
                    KEY `idx_rental_leases_tenant` (`rental_tenant_id`),
                    KEY `idx_rental_leases_period` (`start_date`, `end_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->leasesTable()
            )
        );
        $this->ensureColumn(
            $pdo,
            $this->leasesTable(),
            'lease_type',
            '`lease_type` VARCHAR(64) NOT NULL DEFAULT "residential_unfurnished" AFTER `rental_tenant_id`'
        );
        $this->ensureColumn(
            $pdo,
            $this->leasesTable(),
            'tax_category',
            '`tax_category` VARCHAR(64) NOT NULL DEFAULT "property_income" AFTER `lease_type`'
        );
        $this->ensureIndex($pdo, $this->leasesTable(), 'idx_rental_leases_type', '`lease_type`, `tax_category`');
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_lease_id` INT NOT NULL,
                    `rental_property_id` INT NOT NULL,
                    `rental_unit_id` INT NOT NULL,
                    `period_year` SMALLINT NOT NULL,
                    `period_month` TINYINT NOT NULL,
                    `due_date` DATE NOT NULL,
                    `amount_due` DECIMAL(10,2) NOT NULL DEFAULT 0,
                    `status` ENUM("draft", "validated", "cancelled") NOT NULL DEFAULT "draft",
                    `notes` TEXT NULL,
                    `created_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_rental_rents_lease_period` (`rental_lease_id`, `period_year`, `period_month`),
                    KEY `idx_rental_rents_property_year` (`rental_property_id`, `period_year`, `status`),
                    KEY `idx_rental_rents_lease` (`rental_lease_id`),
                    KEY `idx_rental_rents_unit` (`rental_unit_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->rentsTable()
            )
        );
        $this->ensureUniqueIndex(
            $pdo,
            $this->rentsTable(),
            'uq_rental_rents_lease_period',
            '`rental_lease_id`, `period_year`, `period_month`'
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_rent_id` INT NULL,
                    `rental_lease_id` INT NOT NULL,
                    `rental_property_id` INT NOT NULL,
                    `rental_unit_id` INT NOT NULL,
                    `payment_date` DATE NOT NULL,
                    `period_year` SMALLINT NOT NULL,
                    `period_month` TINYINT NOT NULL,
                    `amount_due` DECIMAL(10,2) NOT NULL DEFAULT 0,
                    `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0,
                    `status` ENUM("draft", "validated", "cancelled") NOT NULL DEFAULT "draft",
                    `notes` TEXT NULL,
                    `created_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_rental_payments_property_year` (`rental_property_id`, `period_year`, `status`),
                    KEY `idx_rental_payments_rent` (`rental_rent_id`),
                    KEY `idx_rental_payments_lease` (`rental_lease_id`),
                    KEY `idx_rental_payments_unit` (`rental_unit_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->paymentsTable()
            )
        );
        $this->ensureColumn($pdo, $this->paymentsTable(), 'rental_rent_id', '`rental_rent_id` INT NULL AFTER `id`');
        $this->ensureIndex($pdo, $this->paymentsTable(), 'idx_rental_payments_rent', '`rental_rent_id`');
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_property_id` INT NOT NULL,
                    `rental_unit_id` INT NULL,
                    `expense_date` DATE NOT NULL,
                    `label` VARCHAR(160) NOT NULL,
                    `amount` DECIMAL(10,2) NOT NULL,
                    `is_recoverable` TINYINT(1) NOT NULL DEFAULT 0,
                    `is_deductible_candidate` TINYINT(1) NOT NULL DEFAULT 0,
                    `status` ENUM("draft", "validated", "cancelled") NOT NULL DEFAULT "draft",
                    `notes` TEXT NULL,
                    `created_by_private_user_id` INT NOT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY `idx_rental_expenses_property_date` (`rental_property_id`, `expense_date`, `status`),
                    KEY `idx_rental_expenses_flags` (`is_recoverable`, `is_deductible_candidate`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->expensesTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rental_property_id` INT NOT NULL,
                    `rental_unit_id` INT NULL,
                    `rental_lease_id` INT NULL,
                    `document_id` VARCHAR(64) NOT NULL,
                    `storage_path` VARCHAR(255) NOT NULL,
                    `original_name` VARCHAR(255) NOT NULL,
                    `extension` VARCHAR(16) NOT NULL,
                    `mime_type` VARCHAR(120) NOT NULL,
                    `size_bytes` INT NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `uploaded_by_private_user_id` INT NOT NULL,
                    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_rental_documents_document_id` (`document_id`),
                    KEY `idx_rental_documents_property` (`rental_property_id`, `is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->documentsTable()
            )
        );
        $pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS `%s` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `private_user_id` INT NOT NULL,
                    `year` SMALLINT NOT NULL,
                    `format` ENUM("csv", "pdf") NOT NULL,
                    `summary_payload` JSON NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_rental_export_logs_user_year` (`private_user_id`, `year`, `format`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
                $this->exportLogsTable()
            )
        );

        $this->schemaReady = true;
    }

    /**
     * @param array<int, int> $propertyIds
     * @param array<string, mixed> $params
     */
    private function deleteByPropertyIds(string $table, int $id, array $propertyIds, string $setClause, array $params = []): bool
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        if ($id <= 0 || $propertyIds === []) {
            return false;
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'UPDATE `%s` SET %s WHERE `id` = :id AND `rental_property_id` IN (%s)',
                    $table,
                    $setClause,
                    implode(',', $placeholders)
                )
            );
            $statement->bindValue(':id', $id, PDO::PARAM_INT);
            foreach ($params as $name => $value) {
                $statement->bindValue(':' . $name, $value);
            }
            $this->bindIds($statement, 'property_id', $propertyIds);
            $statement->execute();

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, int> $propertyIds
     */
    private function deleteRowByPropertyIds(string $table, int $id, array $propertyIds): bool
    {
        $propertyIds = $this->normalizeIds($propertyIds);
        if ($id <= 0 || $propertyIds === []) {
            return false;
        }

        try {
            $this->ensureSchema();
            $placeholders = $this->placeholders('property_id', $propertyIds);
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'DELETE FROM `%s` WHERE `id` = :id AND `rental_property_id` IN (%s)',
                    $table,
                    implode(',', $placeholders)
                )
            );
            $statement->bindValue(':id', $id, PDO::PARAM_INT);
            $this->bindIds($statement, 'property_id', $propertyIds);
            $statement->execute();

            return $statement->rowCount() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<int, int> $propertyIds
     * @return array<int, string>
     */
    private function draftIssueRows(string $sql, array $propertyIds, int $year): array
    {
        $statement = $this->database->pdo()->prepare($sql);
        $this->bindIds($statement, 'property_id', $propertyIds);
        if (str_contains($sql, ':year_start')) {
            $statement->bindValue(':year_start', sprintf('%04d-01-01', $year));
            $statement->bindValue(':year_end', sprintf('%04d-12-31', $year));
        }
        if (preg_match('/\:year(?!_)/', $sql) === 1) {
            $statement->bindValue(':year', $year, PDO::PARAM_INT);
        }
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $issues = [];
        foreach ($rows as $row) {
            $label = is_array($row) && is_string($row['label'] ?? null) ? trim((string) $row['label']) : '';
            if ($label !== '') {
                $issues[] = $label;
            }
        }

        return $issues;
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    private function placeholders(string $prefix, array $ids): array
    {
        $placeholders = [];
        foreach (array_keys($ids) as $index) {
            $placeholders[] = ':' . $prefix . $index;
        }

        return $placeholders;
    }

    /**
     * @param array<int, int> $ids
     */
    private function bindIds(\PDOStatement $statement, string $prefix, array $ids): void
    {
        foreach ($ids as $index => $id) {
            $statement->bindValue(':' . $prefix . $index, $id, PDO::PARAM_INT);
        }
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (!is_numeric($id) || (int) $id <= 0) {
                continue;
            }
            $normalized[] = (int) $id;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeLimit(int $limit): int
    {
        if ($limit <= 0) {
            return 0;
        }

        return min($limit, 1000);
    }

    private function normalizeText(string $value, int $maxLength): string
    {
        $value = sanitize_text_field($value, $maxLength);
        return trim($value);
    }

    /**
     * @param array<int, string> $allowedStatuses
     */
    private function normalizeStatus(string $status, array $allowedStatuses): string
    {
        $status = strtolower(trim($status));
        return in_array($status, $allowedStatuses, true) ? $status : '';
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return '';
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year) ? $value : '';
    }

    private function normalizeIdentifier(string $value): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/\A[A-Za-z0-9._-]{1,160}\z/', $value) !== 1) {
            return '';
        }

        return $value;
    }

    private function unitCanReceiveNewLease(int $propertyId, int $unitId): bool
    {
        if ($propertyId <= 0 || $unitId <= 0) {
            return false;
        }

        try {
            $this->ensureSchema();
            $statement = $this->database->pdo()->prepare(
                sprintf(
                    'SELECT COUNT(*)
                     FROM `%s`
                     WHERE `id` = :unit_id
                       AND `rental_property_id` = :property_id
                       AND `is_active` = 1
                       AND `status` = "available"',
                    $this->database->table('rental_units')
                )
            );
            $statement->execute([
                'unit_id' => $unitId,
                'property_id' => $propertyId,
            ]);

            return (int) $statement->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized[$this->camelKey($key)] = $value;
        }

        return $normalized;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $normalized[] = $this->normalizeRow($row);
            }
        }

        return $normalized;
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column'
            );
            $statement->execute(['table' => $table, 'column' => $column]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD COLUMN %s', $table, $definition));
        } catch (\Throwable) {
            return;
        }
    }

    private function ensureIndex(PDO $pdo, string $table, string $index, string $columns): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND INDEX_NAME = :index_name'
            );
            $statement->execute(['table' => $table, 'index_name' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD KEY `%s` (%s)', $table, $index, $columns));
        } catch (\Throwable) {
            return;
        }
    }

    private function ensureUniqueIndex(PDO $pdo, string $table, string $index, string $columns): void
    {
        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND INDEX_NAME = :index_name'
            );
            $statement->execute(['table' => $table, 'index_name' => $index]);
            if ((int) $statement->fetchColumn() > 0) {
                return;
            }

            $pdo->exec(sprintf('ALTER TABLE `%s` ADD UNIQUE KEY `%s` (%s)', $table, $index, $columns));
        } catch (\Throwable) {
            return;
        }
    }

    private function camelKey(string $key): string
    {
        $key = strtolower(trim($key));
        if ($key === '') {
            return '';
        }

        return preg_replace_callback(
            '/_([a-z0-9])/',
            static fn (array $matches): string => strtoupper((string) $matches[1]),
            $key
        ) ?? $key;
    }
}
