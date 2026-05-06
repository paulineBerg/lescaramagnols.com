<?php

declare(strict_types=1);

namespace Caramagnols\Seo;

final class StructuredDataRenderer
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function renderScript(array $payload, string $nonce = ''): string
    {
        if ($payload === []) {
            return '';
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($json) || trim($json) === '') {
            return '';
        }

        $nonceAttribute = $nonce !== ''
            ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"'
            : '';

        return '<script type="application/ld+json"' . $nonceAttribute . '>' . "\n"
            . $json . "\n"
            . '</script>' . "\n";
    }

    public static function stripFragmentedJsonLdScripts(string $html): string
    {
        if ($html === '' || stripos($html, 'application/ld+json') === false) {
            return $html;
        }

        $filtered = preg_replace_callback(
            '/\s*<script\b(?=[^>]*\btype\s*=\s*(["\'])application\/ld\+json\1)[^>]*>.*?<\/script>\s*/isu',
            static function (array $matches): string {
                $script = (string) $matches[0];

                return str_contains($script, '#') ? "\n" : $script;
            },
            $html
        );

        return is_string($filtered) ? trim($filtered) : $html;
    }
}
