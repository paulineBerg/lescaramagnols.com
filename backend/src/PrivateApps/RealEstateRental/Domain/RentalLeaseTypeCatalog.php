<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\Domain;

final class RentalLeaseTypeCatalog
{
    public const DEFAULT = 'residential_unfurnished';

    /**
     * @var array<string, array{label:string,taxCategory:string,taxLabel:string,durationMonths:int|null,description:string}>
     */
    private const TYPES = [
        'residential_unfurnished' => [
            'label' => 'Habitation vide',
            'taxCategory' => 'property_income',
            'taxLabel' => 'Revenus fonciers',
            'durationMonths' => 36,
            'description' => 'Fin proposee a 3 ans.',
        ],
        'residential_furnished' => [
            'label' => 'Habitation meublee',
            'taxCategory' => 'bic_furnished',
            'taxLabel' => 'BIC location meublee',
            'durationMonths' => 12,
            'description' => 'Fin proposee a 1 an.',
        ],
        'student_furnished' => [
            'label' => 'Meuble etudiant',
            'taxCategory' => 'bic_furnished',
            'taxLabel' => 'BIC location meublee',
            'durationMonths' => 9,
            'description' => 'Fin proposee a 9 mois.',
        ],
        'mobility_furnished' => [
            'label' => 'Bail mobilite',
            'taxCategory' => 'bic_furnished',
            'taxLabel' => 'BIC location meublee',
            'durationMonths' => 10,
            'description' => 'Fin proposee a 10 mois maximum.',
        ],
        'other' => [
            'label' => 'Autre bail',
            'taxCategory' => 'manual_review',
            'taxLabel' => 'A qualifier',
            'durationMonths' => null,
            'description' => 'Fin et traitement fiscal a renseigner manuellement.',
        ],
    ];

    /**
     * @return array<int, array{code:string,label:string,taxCategory:string,taxLabel:string,durationMonths:int|null,description:string}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::TYPES as $code => $definition) {
            $options[] = [
                'code' => $code,
                'label' => $definition['label'],
                'taxCategory' => $definition['taxCategory'],
                'taxLabel' => $definition['taxLabel'],
                'durationMonths' => $definition['durationMonths'],
                'description' => $definition['description'],
            ];
        }

        return $options;
    }

    public static function normalize(string $type): string
    {
        $type = strtolower(trim($type));

        return isset(self::TYPES[$type]) ? $type : self::DEFAULT;
    }

    public static function label(string $type): string
    {
        $type = self::normalize($type);

        return self::TYPES[$type]['label'];
    }

    public static function taxCategory(string $type): string
    {
        $type = self::normalize($type);

        return self::TYPES[$type]['taxCategory'];
    }

    public static function taxLabel(string $type): string
    {
        $type = self::normalize($type);

        return self::TYPES[$type]['taxLabel'];
    }

    public static function defaultEndDate(string $type, string $startDate): ?string
    {
        $type = self::normalize($type);
        $durationMonths = self::TYPES[$type]['durationMonths'];
        if ($durationMonths === null || trim($startDate) === '') {
            return null;
        }

        $startDate = trim($startDate);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        if (!$date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $startDate) {
            return null;
        }

        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $targetMonthIndex = ($month - 1) + $durationMonths;
        $targetYear = $year + intdiv($targetMonthIndex, 12);
        $targetMonth = ($targetMonthIndex % 12) + 1;
        $lastTargetDay = (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $targetYear, $targetMonth)))
            ->modify('last day of this month')
            ->format('j');

        return (new \DateTimeImmutable(sprintf(
            '%04d-%02d-%02d',
            $targetYear,
            $targetMonth,
            min($day, $lastTargetDay)
        )))
            ->modify('-1 day')
            ->format('Y-m-d');
    }
}
