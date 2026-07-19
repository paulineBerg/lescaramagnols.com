<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyLineMapping;

final class DefaultAgencyLineMappings
{
    /**
     * @return array<int, AgencyLineMapping>
     */
    public static function all(): array
    {
        return [
            self::mapping('reglement virement', 'owner_transfer', 'transfer', false, false, false, 'Rapprochement bancaire optionnel.'),
            self::mapping('solde', 'agency_balance', 'balance', false, false, true, 'Solde technique non fiscal sans lignes sources.'),
            self::mapping('depot garan', 'security_deposit', 'liability', false, false, true, 'Depot de garantie separe des revenus.'),
            self::mapping('depot de garantie', 'security_deposit', 'liability', false, false, true, 'Depot de garantie separe des revenus.'),
            self::mapping('taxe ordures', 'recoverable_tax_income', 'income', true, false, true, 'Verifier la periode et le signe du montant.'),
            self::mapping('ordures menageres', 'recoverable_tax_income', 'income', true, false, true, 'Verifier la periode et le signe du montant.'),
            self::mapping('provisions/charges', 'charge_provision_income', 'income', true, false, false, 'Provision de charges locatives.'),
            self::mapping('provisions', 'charge_provision_income', 'income', true, false, false, 'Provision de charges locatives.'),
            self::mapping('loyer', 'rent_income', 'income', false, false, false, 'Recette locative brute.'),
            self::mapping('tva sur honoraires', 'agency_fee_vat', 'expense', false, true, true, 'A rattacher si possible a l honoraire parent.'),
            self::mapping('tva honoraires', 'agency_fee_vat', 'expense', false, true, true, 'A rattacher si possible a l honoraire parent.'),
            self::mapping('tva/honoraires', 'agency_fee_vat', 'expense', false, true, true, 'A rattacher si possible a l honoraire parent.'),
            self::mapping('honoraires location', 'agency_letting_fee', 'expense', false, true, true, 'Distinguer mise en location et gestion courante.'),
            self::mapping('location lots', 'agency_letting_fee', 'expense', false, true, true, 'Distinguer mise en location et gestion courante.'),
            self::mapping('ouverture de dossier', 'agency_letting_fee', 'expense', false, true, true, 'Distinguer mise en location et gestion courante.'),
            self::mapping('honoraires', 'agency_management_fee', 'expense', false, true, true, 'Deductible candidate sous validation annuelle.'),
            self::mapping('hono', 'agency_management_fee', 'expense', false, true, true, 'Deductible candidate sous validation annuelle.'),
            self::mapping('assurance', 'insurance_unpaid_rent', 'expense', false, true, true, 'Verifier le bail assure et la periode.'),
            self::mapping('gli', 'insurance_unpaid_rent', 'expense', false, true, true, 'Verifier le bail assure et la periode.'),
            self::mapping('forfait foncier', 'property_tax_service_fee', 'expense', false, true, true, 'Ne pas confondre avec la taxe fonciere reelle.'),
            self::mapping('facture eau', 'recoverable_utility_charge', 'expense', true, false, true, 'Rapprochement compteur ou facture demande.'),
            self::mapping('eau froide', 'recoverable_utility_charge', 'expense', true, false, true, 'Rapprochement compteur ou facture demande.'),
            self::mapping('eau chaude', 'recoverable_utility_charge', 'expense', true, false, true, 'Rapprochement compteur ou facture demande.'),
            self::mapping('fonds travaux', 'copro_work_fund', 'expense', false, false, true, 'Fonds travaux separe des charges courantes.'),
            self::mapping('loi alur', 'copro_work_fund', 'expense', false, false, true, 'Fonds travaux separe des charges courantes.'),
            self::mapping('travaux', 'works_expense', 'expense', false, true, true, 'Qualifier entretien, reparation, amelioration ou non deductible.'),
            self::mapping('plomberie', 'works_expense', 'expense', false, true, true, 'Qualifier entretien, reparation, amelioration ou non deductible.'),
            self::mapping('toiture', 'works_expense', 'expense', false, true, true, 'Qualifier entretien, reparation, amelioration ou non deductible.'),
            self::mapping('menuiserie', 'works_expense', 'expense', false, true, true, 'Qualifier entretien, reparation, amelioration ou non deductible.'),
            self::mapping('charges courantes', 'condominium_current_charge', 'expense', false, true, true, 'Attendre la regularisation pour part locative/deductible.'),
            self::mapping('chg courante', 'condominium_current_charge', 'expense', false, true, true, 'Attendre la regularisation pour part locative/deductible.'),
        ];
    }

    private static function mapping(
        string $rawLabelPattern,
        string $mappedCategory,
        string $direction,
        bool $recoverable,
        bool $taxDeductibleCandidate,
        bool $requiresReview,
        string $validationHint
    ): AgencyLineMapping {
        return new AgencyLineMapping(
            $rawLabelPattern,
            AgencyDocumentType::UNKNOWN,
            $mappedCategory,
            $direction,
            $recoverable,
            $taxDeductibleCandidate,
            $requiresReview,
            $validationHint,
            0.8
        );
    }
}
