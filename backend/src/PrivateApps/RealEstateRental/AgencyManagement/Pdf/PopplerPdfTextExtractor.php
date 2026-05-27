<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf;

final class PopplerPdfTextExtractor implements DocumentTextExtractorInterface
{
    public function __construct(private readonly ?string $binaryPath = null)
    {
    }

    public function supports(string $path, string $mimeType): bool
    {
        return is_file($path)
            && is_readable($path)
            && (strtolower($mimeType) === 'application/pdf' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf');
    }

    public function extract(string $path): ExtractedTextResult
    {
        if (!$this->supports($path, 'application/pdf')) {
            return new ExtractedTextResult(ExtractedTextResult::STATUS_UNSUPPORTED, '', null, 'Unsupported document.');
        }

        $binary = $this->resolveBinary();
        if ($binary === '') {
            return new ExtractedTextResult(ExtractedTextResult::STATUS_FAILED, '', null, 'pdftotext binary not found.');
        }

        $result = $this->runProcess([$binary, '-layout', $path, '-']);
        if ($result['exitCode'] !== 0) {
            return new ExtractedTextResult(
                ExtractedTextResult::STATUS_FAILED,
                $result['stdout'],
                $result['exitCode'],
                $result['stderr']
            );
        }

        $text = trim($result['stdout']);
        if (mb_strlen($text, 'UTF-8') < 20) {
            return new ExtractedTextResult(ExtractedTextResult::STATUS_NEEDS_OCR_OR_MANUAL_ENTRY, $text, 0, '');
        }

        return new ExtractedTextResult(ExtractedTextResult::STATUS_EXTRACTED, $text, 0, '');
    }

    private function resolveBinary(): string
    {
        $candidates = [];
        if (is_string($this->binaryPath) && trim($this->binaryPath) !== '') {
            $candidates[] = trim($this->binaryPath);
        }

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            $candidates[] = rtrim($home, '/') . '/.local/bin/pdftotext';
        }

        $candidates[] = '/usr/bin/pdftotext';
        $candidates[] = '/usr/local/bin/pdftotext';
        $candidates[] = 'pdftotext';

        foreach ($candidates as $candidate) {
            if ($candidate === 'pdftotext') {
                return $candidate;
            }

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param array<int, string> $command
     * @return array{stdout:string,stderr:string,exitCode:int}
     */
    private function runProcess(array $command): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return ['stdout' => '', 'stderr' => 'Unable to start pdftotext.', 'exitCode' => 1];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
            'exitCode' => is_int($exitCode) ? $exitCode : 1,
        ];
    }
}
