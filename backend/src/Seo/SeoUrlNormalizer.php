<?php

declare(strict_types=1);

namespace Caramagnols\Seo;

final class SeoUrlNormalizer
{
    public static function withoutFragment(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $fragmentPosition = strpos($url, '#');
        if ($fragmentPosition === false) {
            return $url;
        }

        return trim(substr($url, 0, $fragmentPosition));
    }

    public static function absoluteWithoutFragment(string $url, string $baseUrl): string
    {
        $url = self::withoutFragment($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        $baseUrl = rtrim(self::withoutFragment($baseUrl), '/');
        if ($baseUrl === '') {
            return $url;
        }

        return $baseUrl . '/' . ltrim($url, '/');
    }
}
