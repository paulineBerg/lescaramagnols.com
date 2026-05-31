<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyFiscalReviewPolicy
{
    /**
     * @var array<int, string>
     */
    private const FISCAL_REVIEW_CATEGORIES = [
        'rent_income',
        'charge_provision_income',
        'recoverable_tax_income',
        'recoverable_charge_adjustment',
        'recoverable_utility_charge',
        'agency_management_fee',
        'agency_fee_vat',
        'agency_letting_fee',
        'insurance_unpaid_rent',
        'property_tax_service_fee',
        'works_expense',
        'copro_work_fund',
        'condominium_current_charge',
    ];

    /**
     * @var array<int, string>
     */
    private const UNCLASSIFIED_CATEGORIES = [
        '',
        'other',
        'unknown',
        'unclassified',
    ];

    /**
     * @return array<int, string>
     */
    public function fiscalReviewCategories(): array
    {
        return self::FISCAL_REVIEW_CATEGORIES;
    }

    public function requiresManualFiscalReview(string $category): bool
    {
        return in_array($this->normalizeCategory($category), self::FISCAL_REVIEW_CATEGORIES, true);
    }

    public function needsFiscalPeriod(string $category): bool
    {
        return $this->requiresManualFiscalReview($category);
    }

    public function isUnclassified(string $category): bool
    {
        return in_array($this->normalizeCategory($category), self::UNCLASSIFIED_CATEGORIES, true);
    }

    public function isManualReviewConfirmed(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'yes', 'true', 'on'], true);
    }

    private function normalizeCategory(string $category): string
    {
        return strtolower(trim($category));
    }
}
