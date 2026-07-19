<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\RealEstateRental\AgencyManagement\Pdf;

final class PdfMetadataExtractor
{
    public function __construct(private readonly ?string $binaryPath = null)
    {
    }

    public function extract(string $path): PdfMetadata
    {
        if (!is_file($path) || !is_readable($path)) {
            return new PdfMetadata($path, status: 'failed', error: 'Unreadable PDF.');
        }

        $binary = $this->resolveBinary();
        if ($binary === '') {
            return new PdfMetadata($path, fileSize: filesize($path) ?: null, status: 'failed', error: 'pdfinfo binary not found.');
        }

        $result = $this->runProcess([$binary, $path]);
        if ($result['exitCode'] !== 0) {
            return new PdfMetadata(
                $path,
                fileSize: filesize($path) ?: null,
                status: 'failed',
                error: $result['stderr']
            );
        }

        $fields = $this->parseFields($result['stdout']);

        return new PdfMetadata(
            $path,
            $this->integer($fields['Pages'] ?? null),
            $this->integer($fields['File size'] ?? null),
            $fields['PDF version'] ?? null,
            $this->boolean($fields['Encrypted'] ?? null),
            $fields['Title'] ?? null,
            $fields['Creator'] ?? null,
            $fields['Producer'] ?? null
        );
    }

    private function resolveBinary(): string
    {
        $candidates = [];
        if (is_string($this->binaryPath) && trim($this->binaryPath) !== '') {
            $candidates[] = trim($this->binaryPath);
        }

        $home = getenv('HOME');
        if (is_string($home) && $home !== '') {
            $candidates[] = rtrim($home, '/') . '/.local/bin/pdfinfo';
        }

        $candidates[] = '/usr/bin/pdfinfo';
        $candidates[] = '/usr/local/bin/pdfinfo';
        $candidates[] = 'pdfinfo';

        foreach ($candidates as $candidate) {
            if ($candidate === 'pdfinfo') {
                return $candidate;
            }

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array<string, string>
     */
    private function parseFields(string $output): array
    {
        $fields = [];
        foreach (preg_split('/\R/u', $output) ?: [] as $line) {
            if (preg_match('/\A([^:]+):\s*(.+)\z/u', $line, $matches) !== 1) {
                continue;
            }

            $fields[trim($matches[1])] = trim($matches[2]);
        }

        return $fields;
    }

    private function integer(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/-?\d+/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }

    private function boolean(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        if ($value === 'yes') {
            return true;
        }

        if ($value === 'no') {
            return false;
        }

        return null;
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
            return ['stdout' => '', 'stderr' => 'Unable to start pdfinfo.', 'exitCode' => 1];
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
