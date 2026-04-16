<?php

declare(strict_types=1);

namespace Caramagnols\Feed;

use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Http\Response;

final class RssFeedService
{
    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly BlogRepositoryInterface $blogRepository,
        private readonly string $baseUrl,
        private readonly array $availableLanguages = ['fr', 'en', 'de'],
        private readonly string $defaultLanguage = 'fr'
    ) {
    }

    public function response(?string $requestedLanguage): Response
    {
        $language = $this->resolveLanguage($requestedLanguage);

        return new Response(
            200,
            ['Content-Type' => 'application/rss+xml; charset=UTF-8'],
            $this->render($language)
        );
    }

    public function render(string $requestedLanguage): string
    {
        $language = $this->resolveLanguage($requestedLanguage);
        $blogUrl = $this->buildUrl($language . '/blog');
        $items = $this->publishedItems($language);

        $xml = [
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>",
            '<rss version="2.0">',
            '  <channel>',
            '    <title>Les Caramagnols - Actualités</title>',
            '    <link>' . $this->xmlEscape($blogUrl) . '</link>',
            '    <description>Derniers articles publiés sur Les Caramagnols</description>',
            '    <language>' . $this->xmlEscape($language) . '</language>',
        ];

        foreach ($items as $item) {
            $xml[] = '    <item>';
            $xml[] = '      <title>' . $this->xmlEscape($item['title']) . '</title>';
            $xml[] = '      <link>' . $this->xmlEscape($item['link']) . '</link>';
            $xml[] = '      <guid isPermaLink="true">' . $this->xmlEscape($item['link']) . '</guid>';
            $xml[] = '      <pubDate>' . $this->xmlEscape($item['pubDate']) . '</pubDate>';
            $xml[] = '      <description>' . $this->xmlEscape($item['description']) . '</description>';
            $xml[] = '    </item>';
        }

        $xml[] = '  </channel>';
        $xml[] = '</rss>';

        return implode(PHP_EOL, $xml) . PHP_EOL;
    }

    /**
     * @return array<int, array{title: string, link: string, pubDate: string, description: string, timestamp: int}>
     */
    private function publishedItems(string $language): array
    {
        $items = [];

        foreach ($this->blogRepository->publishedArticles($language) as $data) {
            $title = $data['title'] ?? null;
            $slug = $data['slug'] ?? null;
            $date = $data['date'] ?? null;
            $content = $data['content'] ?? '';
            $timestamp = is_string($date) ? strtotime($date) : false;

            if (!is_string($title) || $title === '' || !is_string($slug) || $slug === '' || $timestamp === false) {
                continue;
            }

            $items[] = [
                'title' => $title,
                'link' => $this->buildUrl($language . '/blog/article/' . rawurlencode($slug)),
                'pubDate' => date(DATE_RSS, $timestamp),
                'description' => $this->excerpt($content),
                'timestamp' => $timestamp,
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => $right['timestamp'] <=> $left['timestamp']
        );

        return $items;
    }

    private function resolveLanguage(?string $requestedLanguage): string
    {
        if (is_string($requestedLanguage) && in_array($requestedLanguage, $this->availableLanguages, true)) {
            return $requestedLanguage;
        }

        return $this->defaultLanguage;
    }

    private function buildUrl(string $path): string
    {
        $baseUrl = rtrim($this->baseUrl, '/');
        $relativePath = ltrim($path, '/');

        if ($baseUrl === '') {
            return '/' . $relativePath;
        }

        return $baseUrl . '/' . $relativePath;
    }

    private function excerpt(mixed $content): string
    {
        $text = trim(strip_tags((string) $content));
        if ($text === '') {
            return '';
        }

        $slice = function_exists('mb_substr')
            ? mb_substr($text, 0, 300)
            : substr($text, 0, 300);

        return strlen((string) $text) > strlen((string) $slice) ? (string) $slice . '...' : (string) $slice;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
