<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\TaxDeclarationHelper\ValueObject;

final class AnnualDeductibleExpenses
{
    /**
     * @param array<int, array<string, mixed>> $sourceRows
     * @param array<int, string> $blockingControls
     */
    public function __construct(
        public readonly int $year,
        public readonly float $recoverableExpenses,
        public readonly float $deductibleCandidateExpenses,
        public readonly float $nonDeductibleExpenses,
        public readonly int $validatedExpenseCount,
        private readonly array $sourceRows,
        private readonly array $blockingControls = []
    ) {
    }

    public function isBlocked(): bool
    {
        return $this->blockingControls !== [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sourceRows(): array
    {
        return $this->sourceRows;
    }

    /**
     * @return array<int, string>
     */
    public function blockingControls(): array
    {
        return $this->blockingControls;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'recoverableExpenses' => $this->recoverableExpenses,
            'deductibleCandidateExpenses' => $this->deductibleCandidateExpenses,
            'nonDeductibleExpenses' => $this->nonDeductibleExpenses,
            'validatedExpenseCount' => $this->validatedExpenseCount,
            'blocked' => $this->isBlocked(),
            'blockingControls' => $this->blockingControls,
            'sourceRows' => $this->sourceRows,
        ];
    }
}
