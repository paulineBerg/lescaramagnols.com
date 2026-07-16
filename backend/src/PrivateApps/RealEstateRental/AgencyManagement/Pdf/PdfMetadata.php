<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf;

final class PdfMetadata
{
    public function __construct(
        public readonly string $path,
        public readonly ?int $pages = null,
        public readonly ?int $fileSize = null,
        public readonly ?string $pdfVersion = null,
        public readonly ?bool $encrypted = null,
        public readonly ?string $title = null,
        public readonly ?string $creator = null,
        public readonly ?string $producer = null,
        public readonly string $status = 'extracted',
        public readonly string $error = ''
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'pages' => $this->pages,
            'fileSize' => $this->fileSize,
            'pdfVersion' => $this->pdfVersion,
            'encrypted' => $this->encrypted,
            'title' => $this->title,
            'creator' => $this->creator,
            'producer' => $this->producer,
            'status' => $this->status,
            'error' => $this->error,
        ];
    }
}
