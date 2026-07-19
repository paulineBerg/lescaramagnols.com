<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Domain;

final class AgencyImportIssue
{
    public const SEVERITY_INFO = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR = 'error';

    public function __construct(
        public readonly string $type,
        public readonly string $severity,
        public readonly string $message,
        public readonly ?int $sourcePage = null
    ) {
    }

    /**
     * @return array{type:string,severity:string,message:string,sourcePage:int|null}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'message' => $this->message,
            'sourcePage' => $this->sourcePage,
        ];
    }
}
