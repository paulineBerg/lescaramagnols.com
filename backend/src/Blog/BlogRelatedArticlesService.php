<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class BlogRelatedArticlesService
{
    public function __construct(private readonly BlogTaxonomy $taxonomy)
    {
    }

    /**
     * @param array<string, mixed> $article
     * @param array<int, array<string, mixed>> $candidates
     * @param array<int, string> $excludedSlugs
     * @return array<int, array<string, mixed>>
     */
    public function suggest(array $article, array $candidates, array $excludedSlugs = [], int $limit = 3): array
    {
        $currentSlug = trim((string) ($article['slug'] ?? ''));
        $currentLanguage = trim((string) ($article['lang'] ?? ''));
        $currentCategory = $this->taxonomy->resolveCategorySlug($article['category'] ?? null);
        $currentSubcategory = $this->taxonomy->resolveSubcategorySlug($article['subcategory'] ?? null);
        $currentTags = $this->normalizedTags($article);
        $excluded = array_fill_keys($excludedSlugs, true);
        $scored = [];

        foreach ($candidates as $candidate) {
            $candidateSlug = trim((string) ($candidate['slug'] ?? ''));
            $candidateLanguage = trim((string) ($candidate['lang'] ?? ''));
            if (
                $candidateSlug === ''
                || ($candidateSlug === $currentSlug && $candidateLanguage === $currentLanguage)
                || isset($excluded[$candidateSlug])
            ) {
                continue;
            }

            $score = 0;
            $candidateCategory = $this->taxonomy->resolveCategorySlug($candidate['category'] ?? null);
            $candidateSubcategory = $this->taxonomy->resolveSubcategorySlug($candidate['subcategory'] ?? null);

            if ($currentSubcategory !== null && $candidateSubcategory === $currentSubcategory) {
                $score += 60;
            }

            if ($currentCategory !== null && $candidateCategory === $currentCategory) {
                $score += 40;
            }

            $sharedTags = count(array_intersect($currentTags, $this->normalizedTags($candidate)));
            if ($sharedTags >= 2) {
                $score += 20 + $sharedTags;
            }

            if ($score <= 0) {
                continue;
            }

            $scored[] = [
                'score' => $score,
                'timestamp' => $this->articleTimestamp($candidate),
                'article' => $candidate,
            ];
        }

        usort(
            $scored,
            static fn (array $left, array $right): int => ($right['score'] <=> $left['score'])
                ?: ($right['timestamp'] <=> $left['timestamp'])
        );

        return array_map(
            static fn (array $item): array => $item['article'],
            array_slice($scored, 0, max(0, $limit))
        );
    }

    /**
     * @param array<string, mixed> $article
     * @return array<int, string>
     */
    private function normalizedTags(array $article): array
    {
        $tags = [];

        foreach (is_array($article['tags'] ?? null) ? $article['tags'] : [] as $tag) {
            $slug = $this->taxonomy->resolveTagSlug($tag);
            if ($slug !== null) {
                $tags[$slug] = $slug;
            }
        }

        return array_values($tags);
    }

    /**
     * @param array<string, mixed> $article
     */
    private function articleTimestamp(array $article): int
    {
        $timestamp = strtotime((string) ($article['date'] ?? $article['updated_at'] ?? ''));

        return is_int($timestamp) ? $timestamp : 0;
    }
}
