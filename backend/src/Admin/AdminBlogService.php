<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Blog\BlogDiscussionRepositoryInterface;
use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Content\PageRepository;

final class AdminBlogService
{
    private readonly PageRepository $pageRepository;
    private readonly BlogDiscussionRepositoryInterface $discussionRepository;

    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        private readonly BlogSaveService $saveService,
        private readonly array $availableLanguages,
        private readonly string $defaultLanguage = 'fr',
        ?PageRepository $pageRepository = null,
        ?BlogDiscussionRepositoryInterface $discussionRepository = null
    ) {
        $this->pageRepository = $pageRepository ?? page_repository(pages_data_path());
        $this->discussionRepository = $discussionRepository ?? blog_discussion_repository();
    }

    /**
     * @return array<int, string>
     */
    public function availableLanguages(): array
    {
        return $this->availableLanguages;
    }

    /**
     * @return array<int, string>
     */
    public function supportedStatuses(): array
    {
        return ['draft', 'scheduled', 'published'];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: ?string, lang: ?string, category: ?string, tag: ?string, q: string}
     */
    public function normalizeFilters(array $query): array
    {
        $status = is_string($query['status'] ?? null) ? strtolower(trim((string) $query['status'])) : '';
        if (!in_array($status, $this->supportedStatuses(), true)) {
            $status = '';
        }

        $language = is_string($query['lang'] ?? null) ? strtolower(trim((string) $query['lang'])) : '';
        if (!in_array($language, $this->availableLanguages, true)) {
            $language = '';
        }

        return [
            'status' => $status !== '' ? $status : null,
            'lang' => $language !== '' ? $language : null,
            'category' => $this->normalizeTextFilter($query['category'] ?? null),
            'tag' => $this->normalizeTextFilter($query['tag'] ?? null),
            'q' => is_string($query['q'] ?? null) ? trim((string) $query['q']) : '',
        ];
    }

    /**
     * @param array{status: ?string, lang: ?string, category: ?string, tag: ?string, q: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function listArticles(array $filters): array
    {
        $articles = $this->repository->allArticles($filters['lang']);
        $search = $this->normalizeTextFilter($filters['q']);

        $articles = array_filter($articles, function (array $article) use ($filters, $search): bool {
            if ($filters['status'] !== null) {
                $requestedStatus = $filters['status'];
                $rawStatus = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));

                if ($requestedStatus === 'published') {
                    if (!$this->isEffectivelyPublished($article)) {
                        return false;
                    }
                } elseif ($requestedStatus === 'scheduled') {
                    if ($rawStatus !== 'scheduled') {
                        return false;
                    }
                } elseif ($requestedStatus === 'draft') {
                    if ($rawStatus !== 'draft') {
                        return false;
                    }
                } elseif ($rawStatus !== $requestedStatus) {
                    return false;
                }
            }

            if ($filters['category'] !== null) {
                $category = $this->normalizeTextFilter((string) ($article['category'] ?? ''));
                if ($category !== $filters['category']) {
                    return false;
                }
            }

            if ($filters['tag'] !== null) {
                $found = false;
                foreach (is_array($article['tags'] ?? null) ? $article['tags'] : [] as $rawTag) {
                    if ($this->normalizeTextFilter((string) $rawTag) === $filters['tag']) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    return false;
                }
            }

            if ($search !== null && !$this->matchesSearch($article, $search)) {
                return false;
            }

            return true;
        });

        return array_values(array_map(
            fn (array $article): array => $this->mapListItem($article),
            $articles
        ));
    }

    /**
     * @return array<int, string>
     */
    public function availableCategories(?string $language = null): array
    {
        return $this->repository->categories($language, false);
    }

    /**
     * @return array<int, string>
     */
    public function availableTags(?string $language = null): array
    {
        return $this->repository->tags($language, false);
    }

    /**
     * @return array<int, array{slug: string, title: string, route: string, status: string}>
     */
    public function availablePageOptions(?string $language = null): array
    {
        $targetLanguage = is_string($language) && $language !== '' ? $language : $this->defaultLanguage;
        $options = [];

        foreach ($this->pageRepository->all() as $page) {
            $slug = trim((string) ($page['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $options[] = [
                'slug' => $slug,
                'title' => $this->pageDisplayTitle($page, $targetLanguage),
                'route' => $this->pageDisplayRoute($page, $targetLanguage),
                'status' => (string) ($page['status'] ?? PageRepository::STATUS_DRAFT),
            ];
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strcasecmp(
                $left['title'] !== '' ? $left['title'] : $left['slug'],
                $right['title'] !== '' ? $right['title'] : $right['slug']
            )
        );

        return $options;
    }

    /**
     * @return array{
     *   total: int,
     *   published: int,
     *   drafts: int,
     *   scheduled: int,
     *   rootArticles: int,
     *   childArticles: int,
     *   manualOrderedChildren: int,
     *   categories: int,
     *   tags: int,
     *   byLanguage: array<string, int>
     * }
     */
    public function dashboardSummary(): array
    {
        $summary = [
            'total' => 0,
            'published' => 0,
            'drafts' => 0,
            'scheduled' => 0,
            'rootArticles' => 0,
            'childArticles' => 0,
            'manualOrderedChildren' => 0,
            'categories' => count($this->repository->categories(null, false)),
            'tags' => count($this->repository->tags(null, false)),
            'byLanguage' => array_fill_keys($this->availableLanguages, 0),
        ];

        foreach ($this->repository->allArticles() as $article) {
            $summary['total']++;

            $rawStatus = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
            if ($rawStatus === 'scheduled') {
                $summary['scheduled']++;
            }

            if ($this->isEffectivelyPublished($article)) {
                $summary['published']++;
            } else {
                $summary['drafts']++;
            }

            $language = (string) ($article['lang'] ?? $this->defaultLanguage);
            if (array_key_exists($language, $summary['byLanguage'])) {
                $summary['byLanguage'][$language]++;
            }

            $parentSlug = trim((string) ($article['parent_slug'] ?? ''));
            if ($parentSlug === '') {
                $summary['rootArticles']++;
            } else {
                $summary['childArticles']++;

                $childSortOrder = $article['child_sort_order'] ?? null;
                if ($childSortOrder !== null && $childSortOrder !== '') {
                    $summary['manualOrderedChildren']++;
                }
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyFormData(): array
    {
        return [
            'title' => '',
            'slug' => '',
            'lang' => $this->defaultLanguage,
            'status' => 'draft',
            'author' => '',
            'date' => date('Y-m-d\TH:i'),
            'scheduled_publish_at' => '',
            'page_slug' => '',
            'parent_slug' => '',
            'child_sort_order' => '',
            'excerpt' => '',
            'category' => '',
            'tags_input' => '',
            'featured_image_src' => '',
            'featured_image_alt' => '',
            'featured_image_title' => '',
            'featured_image_caption' => '',
            'featured_image_width' => '',
            'featured_image_height' => '',
            'content' => '',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formDataForArticle(string $slug, string $language): ?array
    {
        $article = $this->repository->find($slug, $language);

        return is_array($article) ? $this->mapFormData($article) : null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function availableParentArticles(?string $language = null, ?string $currentSlug = null, ?string $currentLanguage = null): array
    {
        $targetLanguage = is_string($language) && $language !== '' ? $language : $this->defaultLanguage;
        $normalizedCurrentSlug = is_string($currentSlug) ? trim($currentSlug) : '';
        $normalizedCurrentLanguage = is_string($currentLanguage) && $currentLanguage !== '' ? $currentLanguage : $targetLanguage;
        $options = [];

        foreach ($this->repository->allArticles($targetLanguage) as $article) {
            $slug = (string) ($article['slug'] ?? '');
            $lang = (string) ($article['lang'] ?? $targetLanguage);

            if ($slug === '' || ($slug === $normalizedCurrentSlug && $lang === $normalizedCurrentLanguage)) {
                continue;
            }

            $options[] = [
                'slug' => $slug,
                'lang' => $lang,
                'title' => (string) ($article['title'] ?? 'Article sans titre'),
                'status' => (string) ($article['status'] ?? 'draft'),
                'date' => (string) ($article['date'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function childArticlesForArticle(string $slug, string $language): array
    {
        return array_map(
            fn (array $article): array => [
                'title' => (string) ($article['title'] ?? 'Article sans titre'),
                'slug' => (string) ($article['slug'] ?? ''),
                'lang' => (string) ($article['lang'] ?? $language),
                'status' => (string) ($article['status'] ?? 'draft'),
                'date' => (string) ($article['date'] ?? ''),
                'createdAt' => (string) ($article['created_at'] ?? ''),
                'childSortOrder' => $article['child_sort_order'] ?? null,
                'editPath' => admin_route_resolver()->articleEditPath(
                    (string) ($article['slug'] ?? ''),
                    (string) ($article['lang'] ?? $language)
                ),
            ],
            $this->repository->childArticles($slug, $language, false)
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array{
     *   success: bool,
     *   form: array<string, mixed>,
     *   error: ?string,
     *   slug?: string,
     *   lang?: string
     * }
     */
    public function save(array $body, ?string $currentSlug = null, ?string $currentLanguage = null, ?string $actorIdentifier = null): array
    {
        $articleData = is_array($body['article'] ?? null) ? $body['article'] : $body;
        $existing = is_string($currentSlug) && $currentSlug !== '' && is_string($currentLanguage) && $currentLanguage !== ''
            ? $this->repository->find($currentSlug, $currentLanguage)
            : null;

        $payload = [
            'title' => is_string($articleData['title'] ?? null) ? trim((string) $articleData['title']) : '',
            'slug' => is_string($articleData['slug'] ?? null) ? trim((string) $articleData['slug']) : '',
            'lang' => is_string($articleData['lang'] ?? null) ? trim((string) $articleData['lang']) : $this->defaultLanguage,
            'status' => is_string($articleData['status'] ?? null) ? trim((string) $articleData['status']) : 'draft',
            'author' => is_string($articleData['author'] ?? null) ? trim((string) $articleData['author']) : '',
            'date' => $this->normalizeDateInput(
                $this->resolveDateInputForStatus(
                    is_string($articleData['status'] ?? null) ? (string) $articleData['status'] : 'draft',
                    is_string($articleData['date'] ?? null) ? (string) $articleData['date'] : '',
                    is_string($articleData['scheduled_publish_at'] ?? null) ? (string) $articleData['scheduled_publish_at'] : ''
                )
            ),
            'page_slug' => is_string($articleData['page_slug'] ?? null)
                ? trim((string) $articleData['page_slug'])
                : (string) ($existing['page_slug'] ?? ''),
            'parent_slug' => is_string($articleData['parent_slug'] ?? null)
                ? trim((string) $articleData['parent_slug'])
                : (string) ($existing['parent_slug'] ?? ''),
            'child_sort_order' => is_scalar($articleData['child_sort_order'] ?? null)
                ? trim((string) $articleData['child_sort_order'])
                : (string) ($existing['child_sort_order'] ?? ''),
            'excerpt' => is_string($articleData['excerpt'] ?? null) ? trim((string) $articleData['excerpt']) : '',
            'category' => is_string($articleData['category'] ?? null) ? trim((string) $articleData['category']) : '',
            'tags' => $this->parseTags(is_string($articleData['tags_input'] ?? null) ? (string) $articleData['tags_input'] : ''),
            'featured_image' => $this->buildFeaturedImagePayload($articleData),
            'content' => is_string($articleData['content'] ?? null) ? trim((string) $articleData['content']) : '',
            'previous_slug' => $currentSlug ?? '',
            'previous_lang' => $currentLanguage ?? '',
            'translations' => is_array($existing['translations'] ?? null) ? $existing['translations'] : [],
            'comments' => is_array($existing['comments'] ?? null) ? $existing['comments'] : [],
        ];
        $form = $this->mapFormData($payload);

        $selectedPageSlug = trim((string) ($payload['page_slug'] ?? ''));
        if ($selectedPageSlug === '') {
            return [
                'success' => false,
                'form' => $form,
                'error' => 'La page parent est obligatoire pour rattacher l’article.',
            ];
        }

        if (!is_array($this->pageRepository->findBySlug($selectedPageSlug))) {
            return [
                'success' => false,
                'form' => $form,
                'error' => 'La page parent sélectionnée est introuvable.',
            ];
        }

        $result = $this->saveService->save($payload, $actorIdentifier);

        if (($result['ok'] ?? false) !== true) {
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : ['Impossible de sauvegarder l’article.'];

            return [
                'success' => false,
                'form' => $form,
                'error' => implode(' ', array_map('strval', $errors)),
            ];
        }

        $savedArticle = is_array($result['data'] ?? null) ? $result['data'] : $payload;

        return [
            'success' => true,
            'form' => $this->mapFormData($savedArticle),
            'error' => null,
            'slug' => (string) ($savedArticle['slug'] ?? ''),
            'lang' => (string) ($savedArticle['lang'] ?? $this->defaultLanguage),
        ];
    }

    /**
     * @return array{success: bool, error: ?string, deletedDiscussions: int, detachedChildren: int}
     */
    public function delete(string $slug, string $language): array
    {
        $normalizedSlug = $this->normalizeSlug($slug);
        $normalizedLanguage = $this->normalizeLanguage($language);

        if ($normalizedSlug === '' || $normalizedLanguage === '') {
            return [
                'success' => false,
                'error' => 'Article invalide.',
                'deletedDiscussions' => 0,
                'detachedChildren' => 0,
            ];
        }

        $article = $this->repository->find($normalizedSlug, $normalizedLanguage);
        if (!is_array($article)) {
            return [
                'success' => false,
                'error' => 'Article introuvable.',
                'deletedDiscussions' => 0,
                'detachedChildren' => 0,
            ];
        }

        try {
            $detachedChildren = $this->repository->detachChildrenFromParent($normalizedSlug, $normalizedLanguage);
            $deletedDiscussions = $this->discussionRepository->deleteThreadForArticle($normalizedSlug, $normalizedLanguage);
            $deleted = $this->repository->delete($normalizedSlug, $normalizedLanguage);
        } catch (\Throwable) {
            return [
                'success' => false,
                'error' => 'Impossible de supprimer l’article.',
                'deletedDiscussions' => 0,
                'detachedChildren' => 0,
            ];
        }

        if (!$deleted) {
            return [
                'success' => false,
                'error' => 'Impossible de supprimer l’article.',
                'deletedDiscussions' => 0,
                'detachedChildren' => 0,
            ];
        }

        return [
            'success' => true,
            'error' => null,
            'deletedDiscussions' => $deletedDiscussions,
            'detachedChildren' => $detachedChildren,
        ];
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private function mapListItem(array $article): array
    {
        $language = (string) ($article['lang'] ?? $this->defaultLanguage);
        $pageSlug = trim((string) ($article['page_slug'] ?? ''));
        $pageTitle = '';
        $pageRoute = '';

        if ($pageSlug !== '') {
            $page = $this->pageRepository->findBySlug($pageSlug);
            if (is_array($page)) {
                $pageTitle = $this->pageDisplayTitle($page, $language);
                $pageRoute = $this->pageDisplayRoute($page, $language);
            }
        }

        return [
            'title' => (string) ($article['title'] ?? 'Article sans titre'),
            'slug' => (string) ($article['slug'] ?? ''),
            'lang' => $language,
            'status' => $this->normalizeStatus((string) ($article['status'] ?? 'draft')),
            'effectiveStatus' => $this->resolveEffectiveStatus($article),
            'author' => (string) ($article['author'] ?? ''),
            'date' => (string) ($article['date'] ?? ''),
            'scheduledPublishAt' => $this->scheduledPublishAt($article),
            'pageSlug' => $pageSlug,
            'pageTitle' => $pageTitle,
            'pageRoute' => $pageRoute,
            'parentSlug' => (string) ($article['parent_slug'] ?? ''),
            'childSortOrder' => $article['child_sort_order'] ?? null,
            'excerpt' => (string) ($article['excerpt'] ?? ''),
            'category' => trim((string) ($article['category'] ?? '')),
            'tags' => array_values(array_map('strval', is_array($article['tags'] ?? null) ? $article['tags'] : [])),
            'editPath' => admin_route_resolver()->articleEditPath(
                (string) ($article['slug'] ?? ''),
                (string) ($article['lang'] ?? $this->defaultLanguage)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $article
     * @return array<string, mixed>
     */
    private function mapFormData(array $article): array
    {
        $data = $this->emptyFormData();
        $data['title'] = (string) ($article['title'] ?? '');
        $data['slug'] = (string) ($article['slug'] ?? '');
        $data['lang'] = (string) ($article['lang'] ?? $this->defaultLanguage);
        $data['status'] = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
        $data['author'] = (string) ($article['author'] ?? '');
        $data['date'] = $this->formatDateInput((string) ($article['date'] ?? ''));
        $data['scheduled_publish_at'] = $data['status'] === 'scheduled'
            ? $this->formatDateInputWithoutFallback((string) ($article['date'] ?? ''))
            : '';
        $data['page_slug'] = (string) ($article['page_slug'] ?? '');
        $data['parent_slug'] = (string) ($article['parent_slug'] ?? '');
        $childSortOrder = $article['child_sort_order'] ?? null;
        $data['child_sort_order'] = $childSortOrder !== null && $childSortOrder !== ''
            ? (string) $childSortOrder
            : '';
        $data['excerpt'] = (string) ($article['excerpt'] ?? '');
        $data['category'] = trim((string) ($article['category'] ?? ''));
        $data['tags_input'] = implode(', ', array_map('strval', is_array($article['tags'] ?? null) ? $article['tags'] : []));
        $featuredImage = AdminEditorialImageService::sanitizeImageMetadata(
            is_array($article['featured_image'] ?? null) ? $article['featured_image'] : []
        );
        $data['featured_image_src'] = (string) ($featuredImage['src'] ?? '');
        $data['featured_image_alt'] = (string) ($featuredImage['alt'] ?? '');
        $data['featured_image_title'] = (string) ($featuredImage['title'] ?? '');
        $data['featured_image_caption'] = (string) ($featuredImage['caption'] ?? '');
        $data['featured_image_width'] = isset($featuredImage['width']) ? (string) $featuredImage['width'] : '';
        $data['featured_image_height'] = isset($featuredImage['height']) ? (string) $featuredImage['height'] : '';
        $data['content'] = (string) ($article['content'] ?? '');

        return $data;
    }

    /**
     * @param array<string, mixed> $articleData
     * @return array<string, mixed>|null
     */
    private function buildFeaturedImagePayload(array $articleData): ?array
    {
        return AdminEditorialImageService::sanitizeImageMetadata([
            'src' => is_string($articleData['featured_image_src'] ?? null) ? (string) $articleData['featured_image_src'] : '',
            'alt' => is_string($articleData['featured_image_alt'] ?? null) ? (string) $articleData['featured_image_alt'] : '',
            'title' => is_string($articleData['featured_image_title'] ?? null) ? (string) $articleData['featured_image_title'] : '',
            'caption' => is_string($articleData['featured_image_caption'] ?? null) ? (string) $articleData['featured_image_caption'] : '',
            'width' => $articleData['featured_image_width'] ?? null,
            'height' => $articleData['featured_image_height'] ?? null,
        ]);
    }

    /**
     * @param array<string, mixed> $article
     */
    private function matchesSearch(array $article, string $search): bool
    {
        $haystacks = [
            (string) ($article['title'] ?? ''),
            (string) ($article['slug'] ?? ''),
            (string) ($article['author'] ?? ''),
            (string) ($article['category'] ?? ''),
            (string) ($article['page_slug'] ?? ''),
            (string) ($article['parent_slug'] ?? ''),
            implode(' ', array_map('strval', is_array($article['tags'] ?? null) ? $article['tags'] : [])),
        ];

        foreach ($haystacks as $haystack) {
            $normalized = $this->normalizeTextFilter($haystack);
            if ($normalized !== null && str_contains($normalized, $search)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function parseTags(string $input): array
    {
        $tags = [];

        foreach (preg_split('/[\r\n,;]+/', $input) ?: [] as $rawTag) {
            $tag = trim((string) $rawTag);
            if ($tag === '') {
                continue;
            }

            $key = $this->normalizeTextFilter($tag) ?? $tag;
            $tags[$key] = $tag;
        }

        return array_values($tags);
    }

    private function normalizeDateInput(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        $normalized = str_replace('T', ' ', $normalized);

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
            $normalized .= ':00';
        }

        return $normalized;
    }

    private function formatDateInput(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $this->emptyFormData()['date'];
        }

        $timestamp = strtotime($trimmed);

        return is_int($timestamp) ? date('Y-m-d\TH:i', $timestamp) : $trimmed;
    }

    private function formatDateInputWithoutFallback(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $timestamp = strtotime($trimmed);

        return is_int($timestamp) ? date('Y-m-d\TH:i', $timestamp) : $trimmed;
    }

    private function normalizeTextFilter(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return function_exists('mb_strtolower')
            ? mb_strtolower($normalized, 'UTF-8')
            : strtolower($normalized);
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));

        return in_array($normalized, $this->availableLanguages, true) ? $normalized : '';
    }

    private function normalizeStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return in_array($normalized, $this->supportedStatuses(), true) ? $normalized : 'draft';
    }

    private function resolveDateInputForStatus(string $status, string $dateInput, string $scheduledPublishAtInput): string
    {
        if ($this->normalizeStatus($status) === 'scheduled') {
            return $scheduledPublishAtInput;
        }

        return $dateInput;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function resolveEffectiveStatus(array $article): string
    {
        return $this->isEffectivelyPublished($article) ? 'published' : $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
    }

    /**
     * @param array<string, mixed> $article
     */
    private function isEffectivelyPublished(array $article): bool
    {
        $status = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));
        if ($status === 'published') {
            return true;
        }

        if ($status !== 'scheduled') {
            return false;
        }

        $timestamp = $this->scheduledPublishTimestamp($article);

        return is_int($timestamp) && $timestamp <= time();
    }

    /**
     * @param array<string, mixed> $article
     */
    private function scheduledPublishTimestamp(array $article): ?int
    {
        $raw = trim((string) ($article['date'] ?? ''));
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);

        return is_int($timestamp) ? $timestamp : null;
    }

    /**
     * @param array<string, mixed> $article
     */
    private function scheduledPublishAt(array $article): string
    {
        if ($this->normalizeStatus((string) ($article['status'] ?? 'draft')) !== 'scheduled') {
            return '';
        }

        return trim((string) ($article['date'] ?? ''));
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageDisplayTitle(array $page, string $language): string
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $firstTranslation = $translations !== [] ? reset($translations) : null;
        $translation = is_array($translations[$language] ?? null)
            ? $translations[$language]
            : (
                is_array($translations[$this->defaultLanguage] ?? null)
                    ? $translations[$this->defaultLanguage]
                    : (is_array($firstTranslation) ? $firstTranslation : [])
            );

        $title = trim((string) ($translation['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $baseTitle = trim((string) ($page['title'] ?? ''));
        if ($baseTitle !== '') {
            return $baseTitle;
        }

        $slug = trim((string) ($page['slug'] ?? ''));

        return $slug !== '' ? $slug : 'Page sans titre';
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageDisplayRoute(array $page, string $language): string
    {
        $translations = is_array($page['translations'] ?? null) ? $page['translations'] : [];
        $translation = is_array($translations[$language] ?? null)
            ? $translations[$language]
            : (
                is_array($translations[$this->defaultLanguage] ?? null)
                    ? $translations[$this->defaultLanguage]
                    : []
            );

        $route = trim((string) ($translation['route'] ?? ''));
        if ($route !== '') {
            return $route;
        }

        $baseRoute = trim((string) ($page['route'] ?? ''));
        if ($baseRoute !== '') {
            return $baseRoute;
        }

        $slug = trim((string) ($page['slug'] ?? ''));

        return $slug !== '' ? '/' . $slug : '';
    }
}
