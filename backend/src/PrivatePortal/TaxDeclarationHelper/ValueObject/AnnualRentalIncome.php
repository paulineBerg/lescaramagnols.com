<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\TaxDeclarationHelper\ValueObject;

final class AnnualRentalIncome
{
    /**
     * @param array<int, array<string, mixed>> $sourceRows
     * @param array<int, string> $blockingControls
     */
    public function __construct(
        public readonly int $year,
        public readonly float $rentDue,
        public readonly float $rentPaid,
        public readonly float $unpaidRent,
        public readonly int $validatedPaymentCount,
        public readonly int $partialPaymentCount,
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
            'rentDue' => $this->rentDue,
            'rentPaid' => $this->rentPaid,
            'unpaidRent' => $this->unpaidRent,
            'validatedPaymentCount' => $this->validatedPaymentCount,
            'partialPaymentCount' => $this->partialPaymentCount,
            'blocked' => $this->isBlocked(),
            'blockingControls' => $this->blockingControls,
            'sourceRows' => $this->sourceRows,
        ];
    }
}
