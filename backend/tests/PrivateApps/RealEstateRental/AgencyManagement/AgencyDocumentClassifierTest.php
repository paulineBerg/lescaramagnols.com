<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\AgencyDocumentClassifier;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class AgencyDocumentClassifierTest extends TestCase
{
    public function testClassifiesObservedAgencyDocumentFamilies(): void
    {
        $classifier = new AgencyDocumentClassifier();

        $cases = [
            AgencyDocumentType::ASG_MANAGEMENT_STATEMENT => "Releve de gerance\nASG IMMOBILIER\nQuittance Recettes Depenses",
            AgencyDocumentType::ICS_MANAGEMENT_REPORT => "COMPTE RENDU DE GESTION\nPowered by ICS\nAPPELE REGLE SOMMES DUES REGLEMENTS",
            AgencyDocumentType::COPRO_CHARGE_REGULARIZATION => "CHARGES DE COPROPRIETE\nDont Locatif\nDont Deductible",
            AgencyDocumentType::COPRO_FUND_CALL => "PROVISIONS\nCopropriete HAMEAUX\nQuote-Part Tantiemes",
            AgencyDocumentType::LEASE => "BAIL A LOYER\nCONTRAT DE LOCATION\nCONDITIONS PARTICULIERES",
            AgencyDocumentType::INVENTORY_REPORT => "Etat des lieux d'entree\nRELEVE DES COMPTEURS",
            AgencyDocumentType::INSURANCE => "Certificat Assurance Loyers Impayes\nAttestation",
            AgencyDocumentType::TAX_NOTICE => "COTISATION FONCIERE DES ENTREPRISES\nMONTANT A PAYER",
            AgencyDocumentType::OCCUPANCY_DECLARATION => "DECLARATION D'OCCUPATION ET DE LOYER\nOccupation du bien Loue",
            AgencyDocumentType::ARTISAN_INVOICE => "FACTURE F-20250000125\nTOTAL TTC\nNET A PAYER",
        ];

        foreach ($cases as $expectedType => $text) {
            $classified = $classifier->classify($text);
            $this->assertSame($expectedType, $classified->documentType, 'Failed classifying ' . $expectedType);
            $this->assertTrue($classified->confidence > 0.0);
        }
    }

    public function testUnknownDocumentKeepsUnknownType(): void
    {
        $classified = (new AgencyDocumentClassifier())->classify('Document prive sans signature connue');

        $this->assertSame(AgencyDocumentType::UNKNOWN, $classified->documentType);
        $this->assertFalse($classified->isKnown());
    }
}
