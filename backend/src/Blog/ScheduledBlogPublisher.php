<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Logging\AppEventLogger;

final class ScheduledBlogPublisher
{
    private AppEventLogger $logger;

    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        ?AppEventLogger $logger = null
    ) {
        $this->logger = $logger ?? app_event_logger();
    }

    /**
     * @return array{
     *   checked: int,
     *   due: int,
     *   published: int,
     *   dry_run: bool,
     *   articles: array<int, array{slug: string, lang: string, date: string}>
     * }
     */
    public function publishDueArticles(bool $dryRun = false, ?int $now = null): array
    {
        $timestamp = $now ?? time();
        $checked = 0;
        $due = 0;
        $published = 0;
        $articles = [];

        foreach ($this->repository->allArticles() as $article) {
            if (!is_array($article)) {
                continue;
            }

            $checked++;

            $status = strtolower(trim((string) ($article['status'] ?? 'draft')));
            if ($status !== 'scheduled') {
                continue;
            }

            $date = trim((string) ($article['date'] ?? ''));
            if ($date === '') {
                continue;
            }

            $publishAt = strtotime($date);
            if (!is_int($publishAt) || $publishAt > $timestamp) {
                continue;
            }

            $slug = trim((string) ($article['slug'] ?? ''));
            $language = trim((string) ($article['lang'] ?? 'fr'));
            if ($slug === '') {
                continue;
            }

            $due++;
            $articles[] = [
                'slug' => $slug,
                'lang' => $language !== '' ? $language : 'fr',
                'date' => $date,
            ];

            if ($dryRun) {
                continue;
            }

            $updatedArticle = $article;
            $updatedArticle['status'] = 'published';

            $this->repository->save(
                $updatedArticle,
                $slug,
                $language !== '' ? $language : 'fr'
            );

            $published++;
        }

        if (!$dryRun && $published > 0) {
            $this->logger->content(
                'blog.article.publish_scheduled',
                [
                    'published' => $published,
                    'articles' => $articles,
                ]
            );
        }

        return [
            'checked' => $checked,
            'due' => $due,
            'published' => $published,
            'dry_run' => $dryRun,
            'articles' => $articles,
        ];
    }
}
