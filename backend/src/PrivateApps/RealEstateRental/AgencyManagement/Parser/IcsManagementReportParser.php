<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Parser;

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyDocumentType;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyImportIssue;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain\AgencyStatementLineDraft;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Import\ClassifiedAgencyDocument;

final class IcsManagementReportParser extends AbstractStatementParser implements AgencyParserInterface
{
    public function supports(ClassifiedAgencyDocument $document): bool
    {
        return $document->documentType === AgencyDocumentType::ICS_MANAGEMENT_REPORT
            || $document->parserProfile === 'ics-compte-rendu-gestion-v1';
    }

    public function parse(string $text, array $metadata = []): AgencyParserResult
    {
        $fields = $this->extractFields($text);
        $lines = [];
        $issues = [];
        $currentTenant = null;
        $currentUnit = null;
        $currentPage = 1;

        foreach ($this->lines($text) as $line) {
            $currentPage = $this->sourcePageFromLine($line, $currentPage);
            if (preg_match('/Lot\s+([0-9A-Z]+.*)/iu', $line, $matches) === 1) {
                $currentUnit = trim($matches[1]);
                continue;
            }

            if ($this->looksLikeTenantLine($line)) {
                $currentTenant = trim($line);
                continue;
            }

            if (!$this->isFinancialLine($line)) {
                continue;
            }

            $amounts = $this->amounts($line);
            if ($amounts === []) {
                continue;
            }

            $label = $this->labelWithoutAmounts($line);
            $category = $this->categoryGuesser->guess($label);
            $isDebit = !$this->isCreditCategory($category);
            $lines[] = new AgencyStatementLineDraft(
                $label,
                $category,
                end($amounts) !== false ? (float) end($amounts) : null,
                $isDebit ? (float) end($amounts) : null,
                !$isDebit ? (float) end($amounts) : null,
                count($amounts) > 1 ? $amounts[0] : null,
                count($amounts) > 1 ? (float) end($amounts) : null,
                $category === 'owner_transfer' ? (float) end($amounts) : null,
                null,
                null,
                null,
                is_string($fields['propertyAddress'] ?? null) ? $fields['propertyAddress'] : null,
                $currentUnit,
                $currentTenant,
                $currentPage,
                $category === 'other' ? 'review' : 'suggested',
                $this->lineHash($line)
            );
        }

        if ($lines === []) {
            $issues[] = new AgencyImportIssue('no_statement_lines', AgencyImportIssue::SEVERITY_WARNING, 'Aucune ligne de compte rendu ICS exploitable detectee.');
        }

        return new AgencyParserResult(
            AgencyDocumentType::ICS_MANAGEMENT_REPORT,
            'ics-compte-rendu-gestion-v1',
            $lines !== [] ? 0.82 : 0.45,
            $fields,
            $lines,
            $issues
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function extractFields(string $text): array
    {
        $fields = [
            'agencyName' => 'MON PARTENAIRE GESTION',
            'personalAccountReference' => null,
            'propertyAccountReference' => null,
            'statementDate' => null,
            'propertyAddress' => null,
        ];

        if (preg_match('/COMPTE PERSONNEL\s+([0-9A-Z]+)/iu', $text, $matches) === 1) {
            $fields['personalAccountReference'] = $matches[1];
        }

        if (preg_match('/COMPTE IMMEUBLE\s+([0-9A-Z]+)/iu', $text, $matches) === 1) {
            $fields['propertyAccountReference'] = $matches[1];
        }

        if (preg_match('/le\s+(\d{2}\/\d{2}\/\d{4})/iu', $text, $matches) === 1) {
            $fields['statementDate'] = $this->dateFrToSql($matches[1]);
        }

        if (preg_match('/\n\s*([0-9]{1,4},?\s+rue [^\n]+)\n\s*([0-9]{5}\s+[A-Z -]+)/iu', $text, $matches) === 1) {
            $fields['propertyAddress'] = trim($matches[1] . ' ' . $matches[2]);
        }

        return $fields;
    }

    private function looksLikeTenantLine(string $line): bool
    {
        return preg_match('/\A[\p{L} .\'-]{3,}\z/iu', $line) === 1
            && !str_contains($line, 'COMPTE')
            && !str_contains($line, 'GESTION')
            && !str_contains($line, 'RECAPITULATIF');
    }

    private function isFinancialLine(string $line): bool
    {
        return preg_match('/(LOYER|PROVISIONS|TOTAL DES REGLEMENTS LOCATAIRES|Hono|Honoraires|Prime GLI|TVA\/Honoraires|Reglement virement|Solde d[eé]biteur|Solde cr[eé]diteur)/iu', $line) === 1;
    }

    private function labelWithoutAmounts(string $line): string
    {
        $line = (string) preg_replace('/-?\d{1,3}(?:[ \x{00A0}]?\d{3})*(?:[,.]\d{2})/u', '', $line);
        return trim((string) preg_replace('/\s+/u', ' ', $line));
    }

    private function isCreditCategory(string $category): bool
    {
        return in_array($category, ['rent_income', 'charge_provision_income', 'owner_transfer'], true);
    }
}
