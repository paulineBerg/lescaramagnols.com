<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyStatementLineDraft
{
    public function __construct(
        public readonly string $rawLabel,
        public readonly string $mappedCategory,
        public readonly ?float $amount = null,
        public readonly ?float $debitAmount = null,
        public readonly ?float $creditAmount = null,
        public readonly ?float $calledAmount = null,
        public readonly ?float $paidAmount = null,
        public readonly ?float $ownerTransferAmount = null,
        public readonly ?string $periodStart = null,
        public readonly ?string $periodEnd = null,
        public readonly ?string $lineDate = null,
        public readonly ?string $propertyLabel = null,
        public readonly ?string $unitLabel = null,
        public readonly ?string $tenantName = null,
        public readonly int $sourcePage = 1,
        public readonly string $confidenceStatus = 'review',
        public readonly string $sourceLineHash = ''
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rawLabel' => $this->rawLabel,
            'mappedCategory' => $this->mappedCategory,
            'amount' => $this->amount,
            'debitAmount' => $this->debitAmount,
            'creditAmount' => $this->creditAmount,
            'calledAmount' => $this->calledAmount,
            'paidAmount' => $this->paidAmount,
            'ownerTransferAmount' => $this->ownerTransferAmount,
            'periodStart' => $this->periodStart,
            'periodEnd' => $this->periodEnd,
            'lineDate' => $this->lineDate,
            'propertyLabel' => $this->propertyLabel,
            'unitLabel' => $this->unitLabel,
            'tenantName' => $this->tenantName,
            'sourcePage' => $this->sourcePage,
            'confidenceStatus' => $this->confidenceStatus,
            'sourceLineHash' => $this->sourceLineHash,
        ];
    }
}
