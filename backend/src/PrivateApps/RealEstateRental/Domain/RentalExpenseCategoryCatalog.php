<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Domain;

final class RentalExpenseCategoryCatalog
{
    public const DEFAULT = 'autre';

    /**
     * @var array<string, array{label:string,recoverableDefault:bool,deductibleCandidateDefault:bool}>
     */
    private const CATEGORIES = [
        'taxe_fonciere' => [
            'label' => 'Taxe fonciere',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => true,
        ],
        'assurance_pno' => [
            'label' => 'Assurance PNO',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => true,
        ],
        'copropriete' => [
            'label' => 'Copropriete',
            'recoverableDefault' => true,
            'deductibleCandidateDefault' => true,
        ],
        'travaux' => [
            'label' => 'Travaux',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => true,
        ],
        'entretien' => [
            'label' => 'Entretien',
            'recoverableDefault' => true,
            'deductibleCandidateDefault' => true,
        ],
        'agence' => [
            'label' => 'Agence',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => true,
        ],
        'banque' => [
            'label' => 'Banque',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => true,
        ],
        'eau' => [
            'label' => 'Eau',
            'recoverableDefault' => true,
            'deductibleCandidateDefault' => false,
        ],
        'electricite' => [
            'label' => 'Electricite',
            'recoverableDefault' => true,
            'deductibleCandidateDefault' => false,
        ],
        'internet' => [
            'label' => 'Internet',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => false,
        ],
        'mobilier' => [
            'label' => 'Mobilier',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => false,
        ],
        'emprunt' => [
            'label' => 'Emprunt',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => true,
        ],
        'autre' => [
            'label' => 'Autre',
            'recoverableDefault' => false,
            'deductibleCandidateDefault' => false,
        ],
    ];

    /**
     * @return array<int, array{code:string,label:string,recoverableDefault:bool,deductibleCandidateDefault:bool}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::CATEGORIES as $code => $definition) {
            $options[] = [
                'code' => $code,
                'label' => $definition['label'],
                'recoverableDefault' => $definition['recoverableDefault'],
                'deductibleCandidateDefault' => $definition['deductibleCandidateDefault'],
            ];
        }

        return $options;
    }

    public static function normalize(string $category): string
    {
        $category = strtolower(trim($category));
        $category = str_replace('-', '_', $category);

        return isset(self::CATEGORIES[$category]) ? $category : self::DEFAULT;
    }

    public static function label(string $category): string
    {
        $category = self::normalize($category);

        return self::CATEGORIES[$category]['label'];
    }

    public static function recoverableDefault(string $category): bool
    {
        $category = self::normalize($category);

        return self::CATEGORIES[$category]['recoverableDefault'];
    }

    public static function deductibleCandidateDefault(string $category): bool
    {
        $category = self::normalize($category);

        return self::CATEGORIES[$category]['deductibleCandidateDefault'];
    }
}
