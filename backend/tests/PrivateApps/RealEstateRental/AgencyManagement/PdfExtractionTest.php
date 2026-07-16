<?php

declare(strict_types=1);

use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\ExtractedTextResult;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\PdfMetadataExtractor;
use Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf\PopplerPdfTextExtractor;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../core/bootstrap.php';

final class PdfExtractionTest extends TestCase
{
    private string $tempPdf = '';

    protected function tearDown(): void
    {
        if ($this->tempPdf !== '' && is_file($this->tempPdf)) {
            @unlink($this->tempPdf);
        }
    }

    public function testPopplerExtractorReadsTextPdfAndMetadata(): void
    {
        $binary = $this->resolveBinary('pdftotext');
        $pdfinfo = $this->resolveBinary('pdfinfo');
        if ($binary === '' || $pdfinfo === '') {
            self::markTestSkipped('Poppler binaries are not available.');
        }

        $this->tempPdf = $this->writeMinimalPdf('Releve de gerance ASG IMMOBILIER');

        $result = (new PopplerPdfTextExtractor($binary))->extract($this->tempPdf);
        $metadata = (new PdfMetadataExtractor($pdfinfo))->extract($this->tempPdf);

        $this->assertSame(ExtractedTextResult::STATUS_EXTRACTED, $result->status);
        $this->assertStringContainsString('Releve de gerance', $result->text);
        $this->assertSame(1, $metadata->pages);
        $this->assertFalse($metadata->encrypted);
    }

    private function resolveBinary(string $name): string
    {
        $home = getenv('HOME');
        $candidates = [];
        if (is_string($home) && $home !== '') {
            $candidates[] = rtrim($home, '/') . '/.local/bin/' . $name;
        }
        $candidates[] = '/usr/bin/' . $name;
        $candidates[] = '/usr/local/bin/' . $name;

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function writeMinimalPdf(string $text): string
    {
        $path = tempnam(sys_get_temp_dir(), 'agency-pdf-');
        $this->assertIsString($path);
        $escapedText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 144] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];
        $stream = "BT /F1 12 Tf 24 100 Td ({$escapedText}) Tj ET";
        $objects[] = "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\n";

        $content = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($content);
            $content .= $object;
        }

        $xrefOffset = strlen($content);
        $content .= "xref\n0 " . (count($objects) + 1) . "\n";
        $content .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $content .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $content .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $content .= "startxref\n{$xrefOffset}\n%%EOF\n";
        file_put_contents($path, $content);

        return $path;
    }
}
