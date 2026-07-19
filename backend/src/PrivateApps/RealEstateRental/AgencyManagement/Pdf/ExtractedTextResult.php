<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf;

final class ExtractedTextResult
{
    public const STATUS_EXTRACTED = 'extracted';
    public const STATUS_NEEDS_OCR_OR_MANUAL_ENTRY = 'needs_ocr_or_manual_entry';
    public const STATUS_UNSUPPORTED = 'unsupported';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        public readonly string $status,
        public readonly string $text = '',
        public readonly ?int $exitCode = null,
        public readonly string $error = ''
    ) {
    }

    public function hasUsefulText(): bool
    {
        return $this->status === self::STATUS_EXTRACTED && mb_strlen(trim($this->text), 'UTF-8') >= 20;
    }
}
