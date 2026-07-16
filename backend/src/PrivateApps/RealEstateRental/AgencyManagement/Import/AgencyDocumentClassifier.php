<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;

final class AgencyDocumentClassifier
{
    public function classify(string $text, string $filename = ''): ClassifiedAgencyDocument
    {
        $haystack = $this->normalize($filename . "\n" . $text);
        $candidates = [
            $this->candidate(
                AgencyDocumentType::OCCUPANCY_DECLARATION,
                'declaration-occupation-v1',
                ['declaration d occupation et de loyer', 'occupation du bien']
            ),
            $this->candidate(
                AgencyDocumentType::ASG_MANAGEMENT_STATEMENT,
                'asg-releve-gerance-v1',
                ['releve de gerance', 'asg immobilier', 'recettes', 'depenses']
            ),
            $this->candidate(
                AgencyDocumentType::ICS_MANAGEMENT_REPORT,
                'ics-compte-rendu-gestion-v1',
                ['compte rendu de gestion', 'powered by ics', 'appele', 'regle']
            ),
            $this->candidate(
                AgencyDocumentType::COPRO_CHARGE_REGULARIZATION,
                'copro-regularisation-v1',
                ['charges de copropriete', 'dont locatif', 'dont deductible']
            ),
            $this->candidate(
                AgencyDocumentType::COPRO_FUND_CALL,
                'copro-appel-fonds-v1',
                ['provisions', 'copropriete', 'quote-part']
            ),
            $this->candidate(
                AgencyDocumentType::LEASE,
                'bail-agence-v1',
                ['bail', 'contrat de location', 'conditions particulieres']
            ),
            $this->candidate(
                AgencyDocumentType::INVENTORY_REPORT,
                'edl-nockee-v1',
                ['etat des lieux', 'releve des compteurs']
            ),
            $this->candidate(
                AgencyDocumentType::INSURANCE,
                'assurance-gli-v1',
                ['assurance loyers impayes', 'certificat', 'attestation']
            ),
            $this->candidate(
                AgencyDocumentType::TAX_NOTICE,
                'taxe-fonciere-cfe-v1',
                ['taxes foncieres', 'cotisation fonciere des entreprises', 'montant a payer']
            ),
            $this->candidate(
                AgencyDocumentType::ARTISAN_INVOICE,
                'artisan-facture-v1',
                ['facture', 'total ttc', 'net a payer']
            ),
        ];

        $best = new ClassifiedAgencyDocument(AgencyDocumentType::UNKNOWN, '', 0.0, []);
        foreach ($candidates as $candidate) {
            $matches = [];
            foreach ($candidate['signatures'] as $signature) {
                if (str_contains($haystack, $signature)) {
                    $matches[] = $signature;
                }
            }

            if ($matches === []) {
                continue;
            }

            $confidence = count($matches) / count($candidate['signatures']);
            if ($confidence > $best->confidence) {
                $best = new ClassifiedAgencyDocument(
                    $candidate['type'],
                    $candidate['profile'],
                    round($confidence, 2),
                    $matches
                );
            }
        }

        if ($best->confidence < 0.34) {
            return new ClassifiedAgencyDocument(AgencyDocumentType::UNKNOWN, '', 0.0, []);
        }

        return $best;
    }

    /**
     * @return array{type:string,profile:string,signatures:array<int, string>}
     */
    private function candidate(string $type, string $profile, array $signatures): array
    {
        return ['type' => $type, 'profile' => $profile, 'signatures' => $signatures];
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $value = strtr($value, [
            'à' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'é' => 'e',
            'è' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            '’' => "'",
        ]);

        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }
}
