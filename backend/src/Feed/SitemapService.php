<?php

declare(strict_types=1);

namespace Caramagnols\Feed;

use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Content\PageRepository;
use Caramagnols\Http\Response;

final class SitemapService
{
    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly BlogRepositoryInterface $blogRepository,
        private readonly string $baseUrl,
        private readonly array $availableLanguages = ['fr', 'en', 'de'],
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    public function response(): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8'],
            $this->render()
        );
    }

    public function render(): string
    {
        $entries = (new SitemapEntryCollector(
            $this->pageRepository,
            $this->blogRepository,
            $this->baseUrl,
            $this->availableLanguages,
            $this->defaultLanguage
        ))->collectEntries();
        ksort($entries, SORT_STRING);

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($entries as $entry) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . $this->xmlEscape((string) ($entry['loc'] ?? '')) . '</loc>';

            $lastmod = $entry['lastmod'] ?? null;
            if (is_int($lastmod) && $lastmod > 0) {
                $xml[] = '    <lastmod>' . $this->xmlEscape(gmdate('c', $lastmod)) . '</lastmod>';
            }

            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode(PHP_EOL, $xml) . PHP_EOL;
    }
    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
