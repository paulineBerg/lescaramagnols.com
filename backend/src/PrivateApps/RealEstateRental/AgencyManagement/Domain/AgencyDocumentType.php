<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyDocumentType
{
    public const UNKNOWN = 'unknown';
    public const RENT_RECEIPT = 'rent_receipt';
    public const MANAGEMENT_STATEMENT = 'management_statement';
    public const OTHER_AGENCY_DOCUMENT = 'other_agency_document';
    public const ASG_MANAGEMENT_STATEMENT = 'asg_management_statement';
    public const ICS_MANAGEMENT_REPORT = 'ics_management_report';
    public const COPRO_FUND_CALL = 'copro_fund_call';
    public const COPRO_CHARGE_REGULARIZATION = 'copro_charge_regularization';
    public const ARTISAN_INVOICE = 'artisan_invoice';
    public const LEASE = 'lease';
    public const INVENTORY_REPORT = 'inventory_report';
    public const INSURANCE = 'insurance';
    public const TAX_NOTICE = 'tax_notice';
    public const OCCUPANCY_DECLARATION = 'occupancy_declaration';
    public const COMPLETE_DOSSIER = 'complete_dossier';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::UNKNOWN,
            self::RENT_RECEIPT,
            self::MANAGEMENT_STATEMENT,
            self::OTHER_AGENCY_DOCUMENT,
            self::ASG_MANAGEMENT_STATEMENT,
            self::ICS_MANAGEMENT_REPORT,
            self::COPRO_FUND_CALL,
            self::COPRO_CHARGE_REGULARIZATION,
            self::ARTISAN_INVOICE,
            self::LEASE,
            self::INVENTORY_REPORT,
            self::INSURANCE,
            self::TAX_NOTICE,
            self::OCCUPANCY_DECLARATION,
            self::COMPLETE_DOSSIER,
        ];
    }

    public static function isKnown(string $type): bool
    {
        return in_array($type, self::all(), true) && $type !== self::UNKNOWN;
    }

    public static function normalizeUserChoice(?string $type): ?string
    {
        $type = is_string($type) ? strtolower(trim($type)) : '';
        return match ($type) {
            self::RENT_RECEIPT => self::RENT_RECEIPT,
            self::MANAGEMENT_STATEMENT => self::MANAGEMENT_STATEMENT,
            self::OTHER_AGENCY_DOCUMENT, 'other' => self::OTHER_AGENCY_DOCUMENT,
            default => null,
        };
    }
}
