<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\Documents\Service;

use Caramagnols\PrivateApps\Documents\Contract\DocumentImportProfile;
use Caramagnols\PrivateApps\Documents\Repository\DocumentTaxonomyRepository;

/**
 * Classement déterministe d'un document, sans aucun service distant :
 * 1. choix explicite de l'utilisateur (100) ;
 * 2. contexte du profil d'import (90-95 : classement automatique) ;
 * 3. règles déterministes sur le nom de fichier (60-89 : proposition) ;
 * 4. « À classer » sinon.
 * Ne crée jamais de catégorie : sélectionne uniquement une catégorie active.
 */
final class DocumentClassificationService
{
    public const SOURCE_USER = 'user';
    public const SOURCE_CONTEXT = 'context';
    public const SOURCE_FILENAME = 'filename';
    public const SOURCE_FALLBACK = 'fallback';

    public const AUTO_THRESHOLD = 90;
    public const SUGGEST_THRESHOLD = 60;

    /** @var array<string, string> motif (insensible à la casse, sans accents) -> code catégorie */
    private const FILENAME_RULES = [
        'quittance' => 'rents.receipt',
        'depot de garantie' => 'rents.deposit',
        'caution' => 'rents.deposit',
        'impaye' => 'rents.unpaid',
        'bail' => 'leases.contract',
        'avenant' => 'leases.amendment',
        'resiliation' => 'leases.amendment',
        'etat des lieux' => 'inventory',
        'edl' => 'inventory',
        'taxe fonciere' => 'tax.property_tax',
        'cfe' => 'tax.cfe',
        'assurance' => 'insurance.contract',
        'sinistre' => 'insurance.claim',
        'dpe' => 'diagnostics.dpe',
        'diagnostic' => 'diagnostics',
        'devis' => 'works.quote',
        'facture' => 'works.invoice',
        'appel de fonds' => 'charges.service_calls',
        'regularisation' => 'charges.regularization',
        'eau' => 'charges.water',
        'veolia' => 'charges.water',
        'suez' => 'charges.water',
        'electricite' => 'charges.electricity',
        'edf' => 'charges.electricity',
        'engie' => 'charges.electricity',
        'copropriete' => 'coownership',
        'syndic' => 'coownership',
        'releve bancaire' => 'bank',
        'courrier' => 'mail',
    ];

    public function __construct(private readonly DocumentTaxonomyRepository $taxonomy)
    {
    }

    /**
     * @return array{category_code: string, source: string, confidence: int}
     */
    public function classify(
        ?DocumentImportProfile $profile,
        string $userCategoryCode,
        string $originalFilename
    ): array {
        $userCategoryCode = strtolower(trim($userCategoryCode));
        if ($userCategoryCode !== '' && $this->isSelectable($profile, $userCategoryCode)) {
            return [
                'category_code' => $userCategoryCode,
                'source' => self::SOURCE_USER,
                'confidence' => 100,
            ];
        }

        if ($profile !== null) {
            $defaultCode = strtolower(trim($profile->defaultCategoryCode));
            if ($defaultCode !== '' && $defaultCode !== DocumentTaxonomyRepository::INBOX_CODE && $this->taxonomy->isActiveCategoryCode($defaultCode)) {
                return [
                    'category_code' => $defaultCode,
                    'source' => self::SOURCE_CONTEXT,
                    'confidence' => 95,
                ];
            }
        }

        $fromFilename = $this->matchFilename($originalFilename);
        if ($fromFilename !== null && $this->isSelectable($profile, $fromFilename)) {
            return [
                'category_code' => $fromFilename,
                'source' => self::SOURCE_FILENAME,
                'confidence' => 75,
            ];
        }

        return [
            'category_code' => DocumentTaxonomyRepository::INBOX_CODE,
            'source' => self::SOURCE_FALLBACK,
            'confidence' => 0,
        ];
    }

    private function matchFilename(string $filename): ?string
    {
        $normalized = $this->normalizeForMatching($filename);
        if ($normalized === '') {
            return null;
        }

        foreach (self::FILENAME_RULES as $pattern => $categoryCode) {
            if (str_contains($normalized, $pattern)) {
                return $categoryCode;
            }
        }

        return null;
    }

    private function isSelectable(?DocumentImportProfile $profile, string $categoryCode): bool
    {
        if (!$this->taxonomy->isActiveCategoryCode($categoryCode)) {
            return false;
        }

        if ($profile !== null && $profile->allowedCategoryCodes !== []) {
            return in_array($categoryCode, $profile->allowedCategoryCodes, true)
                || $categoryCode === DocumentTaxonomyRepository::INBOX_CODE;
        }

        return true;
    }

    private function normalizeForMatching(string $value): string
    {
        $value = strtolower(trim($value));
        $transliterated = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : $value;
        if (is_string($transliterated) && $transliterated !== '') {
            $value = strtolower($transliterated);
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }
}
