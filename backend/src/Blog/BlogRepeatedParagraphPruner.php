<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class BlogRepeatedParagraphPruner
{
    private const MINIMUM_SENTENCE_WORDS = 10;
    private const MINIMUM_DISTINCT_SLUGS = 3;

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array<string, array<string, true>>
     */
    public function repeatedSentenceIndex(array $articles): array
    {
        $occurrences = [];
        foreach ($articles as $article) {
            $language = strtolower(trim((string) ($article['lang'] ?? '')));
            $slug = trim((string) ($article['slug'] ?? ''));
            $content = (string) ($article['content'] ?? '');
            if ($language === '' || $slug === '' || $content === '') {
                continue;
            }
            foreach ($this->eligibleParagraphs($content) as $paragraph) {
                foreach ($this->significantSentences($paragraph) as $sentence) {
                    $occurrences[$language][$sentence][$slug] = true;
                }
            }
        }

        $repeated = [];
        foreach ($occurrences as $language => $languageOccurrences) {
            foreach ($languageOccurrences as $sentence => $slugs) {
                if (count($slugs) >= self::MINIMUM_DISTINCT_SLUGS) {
                    $repeated[$language][$sentence] = true;
                }
            }
        }

        return $repeated;
    }

    /**
     * @param array<string, true> $repeatedSentences
     * @return array{content: string, changed: bool, paragraph_count: int, heading_count: int}
     */
    public function prune(string $content, array $repeatedSentences): array
    {
        if ($content === '') {
            return [
                'content' => $content,
                'changed' => false,
                'paragraph_count' => 0,
                'heading_count' => 0,
            ];
        }

        $removed = 0;
        $pruned = preg_replace_callback(
            '/<p\b[^>]*>.*?<\/p>/is',
            function (array $match) use ($repeatedSentences, &$removed): string {
                $paragraph = (string) $match[0];
                if (!$this->isEligibleParagraph($paragraph)) {
                    return $paragraph;
                }
                $sentences = $this->significantSentences($paragraph);
                if ($sentences === []) {
                    return $paragraph;
                }
                foreach ($sentences as $sentence) {
                    if (!isset($repeatedSentences[$sentence])) {
                        return $paragraph;
                    }
                }
                $removed++;

                return '';
            },
            $content
        );
        if (!is_string($pruned)) {
            return [
                'content' => $content,
                'changed' => false,
                'paragraph_count' => 0,
                'heading_count' => 0,
            ];
        }
        $headingCount = 0;
        do {
            $pruned = preg_replace(
                '/<h([2-4])\b[^>]*>.*?<\/h\1>\s*(?=<h[2-4]\b|<nav\b|\z)/is',
                '',
                $pruned,
                -1,
                $roundHeadingCount
            ) ?? $pruned;
            $headingCount += $roundHeadingCount;
        } while ($roundHeadingCount > 0);
        $pruned = (string) preg_replace('/\n{3,}/', "\n\n", $pruned);

        return [
            'content' => $pruned,
            'changed' => $removed > 0 || $headingCount > 0,
            'paragraph_count' => $removed,
            'heading_count' => $headingCount,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function eligibleParagraphs(string $content): array
    {
        preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $content, $matches);

        return array_values(array_filter(
            $matches[0],
            fn (string $paragraph): bool => $this->isEligibleParagraph($paragraph)
        ));
    }

    private function isEligibleParagraph(string $paragraph): bool
    {
        return preg_match('/<(?:a|img|nav|figure)\b/i', $paragraph) !== 1;
    }

    /**
     * @return array<int, string>
     */
    private function significantSentences(string $html): array
    {
        $plainText = $this->plainText($html);
        $parts = preg_split('/(?<=[.!?])\s+/u', $plainText) ?: [];
        $sentences = [];
        foreach ($parts as $part) {
            $normalized = mb_strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $part)), 'UTF-8');
            if ($this->wordCount($normalized) < self::MINIMUM_SENTENCE_WORDS) {
                continue;
            }
            $sentences[$normalized] = $normalized;
        }

        return array_values($sentences);
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function wordCount(string $text): int
    {
        $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}’\'-]*/u', $text);

        return is_int($count) ? $count : 0;
    }
}
