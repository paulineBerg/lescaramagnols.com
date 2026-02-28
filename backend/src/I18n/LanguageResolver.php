<?php
declare(strict_types=1);

namespace Caramagnols\I18n;

use Caramagnols\Http\Request;

class LanguageResolver
{
    public function __construct(private array $available = ['fr', 'en', 'de'], private string $fallback = 'fr')
    {
    }

    public function resolve(Request $request): string
    {
        $queryLang = $request->query()['lang'] ?? null;
        if ($this->isValid($queryLang)) {
            return $queryLang;
        }

        // Langue dans l'URL (ex: /en/page)
        $uri = parse_url($request->uri(), PHP_URL_PATH);
        $segments = explode('/', trim((string) $uri, '/'));
        if (isset($segments[0]) && $this->isValid($segments[0])) {
            return $segments[0];
        }

        $cookies = $request->cookies();
        if (isset($cookies['lang']) && $this->isValid($cookies['lang'])) {
            return $cookies['lang'];
        }

        $accept = $request->header('Accept-Language', '') ?? '';
        foreach (explode(',', $accept) as $lang) {
            $code = substr(trim($lang), 0, 2);
            if ($this->isValid($code)) {
                return $code;
            }
        }

        return $this->fallback;
    }

    private function isValid(?string $code): bool
    {
        return is_string($code) && in_array($code, $this->available, true);
    }
}
