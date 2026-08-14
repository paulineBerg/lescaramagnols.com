<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Domain;

final class PhotoFilenameNormalizer
{
    private const RESERVED_WINDOWS_NAMES = [
        'con', 'prn', 'aux', 'nul',
        'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
        'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
    ];

    public function normalizePart(string $value, string $separator = '-'): string
    {
        $separator = $this->separator($separator);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $converted = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }

        $value = preg_replace('/[\x00-\x1F<>:"\/\\\\|?*]+/', $separator, $value) ?? '';
        $value = preg_replace('/\s+/', $separator, $value) ?? '';
        $quotedSeparator = preg_quote($separator, '/');
        $value = preg_replace('/' . $quotedSeparator . '{2,}/', $separator, $value) ?? '';
        $value = trim($value, " .\t\n\r\0\x0B" . $separator);

        if ($value === '') {
            return '';
        }

        if (in_array(strtolower($value), self::RESERVED_WINDOWS_NAMES, true)) {
            $value .= $separator . 'file';
        }

        return $value;
    }

    public function normalizeFilename(string $baseName, string $extension, string $separator = '-', int $maxLength = 180): string
    {
        $extension = ltrim(trim($extension), '.');
        $baseName = $this->normalizePart($baseName, $separator);
        if ($baseName === '') {
            $baseName = 'photo';
        }

        $extensionPart = $extension !== '' ? '.' . $extension : '';
        $maxBaseLength = max(20, $maxLength - strlen($extensionPart));
        if (strlen($baseName) > $maxBaseLength) {
            $baseName = rtrim(substr($baseName, 0, $maxBaseLength), " .-_");
        }

        return $baseName . $extensionPart;
    }

    public function separator(string $separator): string
    {
        return in_array($separator, ['_', '-', ' '], true) ? $separator : '-';
    }
}
