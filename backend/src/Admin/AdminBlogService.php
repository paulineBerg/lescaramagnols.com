<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Blog\BlogSaveService;
use Caramagnols\Blog\BlogDiscussionRepositoryInterface;
use Caramagnols\Blog\BlogRepositoryInterface;
use Caramagnols\Blog\BlogTaxonomy;
use Caramagnols\Content\PageRepository;

final class AdminBlogService
{
    private readonly PageRepository $pageRepository;
    private readonly BlogDiscussionRepositoryInterface $discussionRepository;
    private readonly BlogTaxonomy $taxonomy;

    /**
     * @param array<int, string> $availableLanguages
     */
    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        private readonly BlogSaveService $saveService,
        private readonly array $availableLanguages,
        private readonly string $defaultLanguage = 'fr',
        ?PageRepository $pageRepository = null,
        ?BlogDiscussionRepositoryInterface $discussionRepository = null,
        ?BlogTaxonomy $taxonomy = null
    ) {
        $this->pageRepository = $pageRepository ?? page_repository(pages_data_path());
        $this->discussionRepository = $discussionRepository ?? blog_discussion_repository();
        $this->taxonomy = $taxonomy ?? BlogTaxonomy::fromDefaultConfig();
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
            'category' => $this->taxonomy->resolveCategorySlug($query['category'] ?? null),
            'tag' => $this->taxonomy->resolveTagSlug($query['tag'] ?? null),
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
                $category = $this->taxonomy->resolveCategorySlug($article['category'] ?? null);
                if ($category !== $filters['category']) {
                    return false;
                }
            }

            if ($filters['tag'] !== null) {
                $found = false;
                foreach (is_array($article['tags'] ?? null) ? $article['tags'] : [] as $rawTag) {
                    if ($this->taxonomy->resolveTagSlug($rawTag) === $filters['tag']) {
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
     * @param array<int, array<string, mixed>> $articles
     * @return array<int, array<string, mixed>>
     */
    public function groupArticlesBySlug(array $articles): array
    {
        $articleRowsBySlug = [];

        foreach ($articles as $article) {
            if (!is_array($article)) {
                continue;
            }

            $slug = trim((string) ($article['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $language = (string) ($article['lang'] ?? '');
            $articleRowsBySlug[$slug][$language] = $article;
        }

        $groups = [];

        foreach ($articleRowsBySlug as $slug => $visibleRowsByLanguage) {
            $bundle = $this->loadArticleBundle($slug);
            if ($bundle === []) {
                continue;
            }

            $group = [
                'slug' => $slug,
                'title' => '',
                'status' => 'draft',
                'effectiveStatus' => 'draft',
                'author' => '',
                'date' => '',
                'scheduledPublishAt' => null,
                'pageSlug' => '',
                'pageTitle' => '',
                'pageRoute' => '',
                'parentSlug' => '',
                'childSortOrder' => null,
                'excerpt' => '',
                'category' => '',
                'subcategory' => '',
                'tags' => [],
                'languages' => [],
                'missingLanguages' => [],
                'translations' => [],
                'sortTimestamp' => 0,
            ];

            foreach ($this->availableLanguages as $language) {
                if (!is_array($bundle[$language] ?? null)) {
                    continue;
                }

                $translatedRow = $this->mapListItem($bundle[$language]);
                $translatedRow['isVisibleInList'] = array_key_exists($language, $visibleRowsByLanguage);
                $group['translations'][$language] = $translatedRow;
                $group['languages'][] = $language;

                if ($translatedRow['isVisibleInList']) {
                    $group['visibleLanguageCount'] = (int) (($group['visibleLanguageCount'] ?? 0) + 1);
                }
            }

            if ($group['languages'] === []) {
                continue;
            }

            $group['missingLanguages'] = array_values(array_diff($this->availableLanguages, $group['languages']));

            foreach ($this->availableLanguages as $language) {
                if (!is_array($group['translations'][$language] ?? null)) {
                    continue;
                }

                $group = array_replace(
                    $group,
                    array_intersect_key($group['translations'][$language], array_flip([
                        'title',
                        'status',
                        'effectiveStatus',
                        'author',
                        'date',
                        'scheduledPublishAt',
                        'pageSlug',
                        'pageTitle',
                        'pageRoute',
                        'parentSlug',
                        'childSortOrder',
                        'excerpt',
                        'category',
                        'subcategory',
                        'tags',
                    ]))
                );

                $timestamp = strtotime((string) ($group['date'] ?? ''));
                if ($timestamp !== false) {
                    $group['sortTimestamp'] = $timestamp;
                }

                break;
            }

            if ($group['title'] === '') {
                $group['title'] = 'Article sans titre';
            }

            $groups[] = $group;
        }

        usort(
            $groups,
            static fn (array $left, array $right): int => ($right['sortTimestamp'] ?? 0) <=> ($left['sortTimestamp'] ?? 0)
        );

        foreach ($groups as $index => $group) {
            unset($groups[$index]['sortTimestamp']);
        }

        return $groups;
    }

    /**
     * @param array<int, array<string, mixed>> $articles
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
    public function summarizeArticles(array $articles): array
    {
        $summary = $this->emptyArticleSummary();

        $groups = [];
        foreach ($articles as $index => $article) {
            $slug = trim((string) ($article['slug'] ?? ''));
            if ($slug === '') {
                $slug = '__article_' . (string) $index;
            }

            if (!isset($groups[$slug])) {
                $groups[$slug] = [
                    'status' => 'draft',
                    'parentSlug' => '',
                    'hasManualOrder' => false,
                ];
            }

            $effectiveStatus = is_string($article['effectiveStatus'] ?? null)
                ? $this->normalizeStatus((string) $article['effectiveStatus'])
                : $this->resolveEffectiveStatus($article);
            $groups[$slug]['status'] = $this->prioritizedSummaryStatus(
                (string) $groups[$slug]['status'],
                $effectiveStatus
            );

            $language = (string) ($article['lang'] ?? $this->defaultLanguage);
            if (array_key_exists($language, $summary['byLanguage'])) {
                $summary['byLanguage'][$language]++;
            }

            $parentSlug = trim((string) ($article['parentSlug'] ?? $article['parent_slug'] ?? ''));
            if ($parentSlug !== '') {
                $groups[$slug]['parentSlug'] = $parentSlug;
            }

            $childSortOrder = $article['childSortOrder'] ?? $article['child_sort_order'] ?? null;
            if ($childSortOrder !== null && $childSortOrder !== '') {
                $groups[$slug]['hasManualOrder'] = true;
            }
        }

        foreach ($groups as $group) {
            $summary['total']++;

            $status = (string) $group['status'];
            if ($status === 'published') {
                $summary['published']++;
            } elseif ($status === 'scheduled') {
                $summary['scheduled']++;
            } else {
                $summary['drafts']++;
            }

            if (trim((string) $group['parentSlug']) === '') {
                $summary['rootArticles']++;
            } else {
                $summary['childArticles']++;

                if (!empty($group['hasManualOrder'])) {
                    $summary['manualOrderedChildren']++;
                }
            }
        }

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    public function availableCategories(?string $language = null): array
    {
        return array_map(
            fn (array $option): string => $option['label'],
            $this->availableCategoryOptions($language)
        );
    }

    /**
     * @return array<int, string>
     */
    public function availableTags(?string $language = null): array
    {
        return array_map(
            fn (array $option): string => $option['label'],
            $this->availableTagOptions($language)
        );
    }

    /**
     * @return array<int, array{slug: string, label: string, seo: string}>
     */
    public function availableCategoryOptions(?string $language = null): array
    {
        return $this->taxonomy->categoryOptions($language ?? $this->defaultLanguage);
    }

    /**
     * @return array<int, array{slug: string, category: string, label: string, seo: string}>
     */
    public function availableSubcategoryOptions(?string $language = null): array
    {
        return $this->taxonomy->subcategoryOptions(null, $language ?? $this->defaultLanguage);
    }

    /**
     * @return array<int, array{slug: string, label: string, seo: string}>
     */
    public function availableTagOptions(?string $language = null): array
    {
        return $this->taxonomy->tagOptions($language ?? $this->defaultLanguage);
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
        $summary = $this->summarizeArticles($this->repository->allArticles());
        $summary['categories'] = count($this->availableCategoryOptions($this->defaultLanguage));
        $summary['tags'] = count($this->availableTagOptions($this->defaultLanguage));

        return $summary;
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
    private function emptyArticleSummary(): array
    {
        return [
            'total' => 0,
            'published' => 0,
            'drafts' => 0,
            'scheduled' => 0,
            'rootArticles' => 0,
            'childArticles' => 0,
            'manualOrderedChildren' => 0,
            'categories' => 0,
            'tags' => 0,
            'byLanguage' => array_fill_keys($this->availableLanguages, 0),
        ];
    }

    private function prioritizedSummaryStatus(string $currentStatus, string $candidateStatus): string
    {
        $priority = [
            'draft' => 0,
            'scheduled' => 1,
            'published' => 2,
        ];

        $current = $this->normalizeStatus($currentStatus);
        $candidate = $this->normalizeStatus($candidateStatus);

        return ($priority[$candidate] ?? 0) > ($priority[$current] ?? 0) ? $candidate : $current;
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyFormData(): array
    {
        $data = [
            'slug' => '',
            'lang' => $this->defaultLanguage,
            'active_language' => $this->defaultLanguage,
            'status' => 'draft',
            'author' => '',
            'date' => date('Y-m-d\TH:i'),
            'scheduled_publish_at' => '',
            'page_slug' => '',
            'parent_slug' => '',
            'child_sort_order' => '',
            'category' => '',
            'subcategory' => '',
            'tags' => [],
            'tags_input' => '',
            'featured_image_src' => '',
            'featured_image_alt' => '',
            'featured_image_title' => '',
            'featured_image_caption' => '',
            'featured_image_width' => '',
            'featured_image_height' => '',
            'translations' => [],
            'existing_languages' => [],
        ];

        foreach ($this->availableLanguages as $language) {
            $data['translations'][$language] = $this->emptyTranslationFormData();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function formDataForArticle(string $slug, string $language): ?array
    {
        $bundle = $this->loadArticleBundle($slug);
        if ($bundle === []) {
            return null;
        }

        return $this->mapBundleFormData($bundle, $language);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function availableParentArticles(?string $language = null, ?string $currentSlug = null, ?string $currentLanguage = null): array
    {
        $targetLanguage = is_string($language) && $language !== '' ? $language : $this->defaultLanguage;
        $normalizedCurrentSlug = is_string($currentSlug) ? trim($currentSlug) : '';
        $groups = [];

        foreach ($this->repository->allArticles() as $article) {
            $slug = (string) ($article['slug'] ?? '');
            $lang = (string) ($article['lang'] ?? $this->defaultLanguage);

            if ($slug === '' || $slug === $normalizedCurrentSlug) {
                continue;
            }

            if (!isset($groups[$slug])) {
                $groups[$slug] = [];
            }

            $groups[$slug][$lang] = $article;
        }

        $options = [];

        foreach ($groups as $slug => $articlesByLanguage) {
            $representative = $this->resolveBundleRepresentativeArticle($articlesByLanguage, $targetLanguage);
            if ($representative === null) {
                continue;
            }

            $status = 'draft';
            foreach ($articlesByLanguage as $article) {
                $status = $this->prioritizedSummaryStatus($status, $this->resolveEffectiveStatus($article));
            }

            $options[] = [
                'slug' => $slug,
                'title' => (string) ($representative['title'] ?? 'Article sans titre'),
                'status' => $status,
                'date' => (string) ($representative['date'] ?? ''),
                'languages' => array_keys($articlesByLanguage),
                'language_count' => count($articlesByLanguage),
            ];
        }

        usort(
            $options,
            static fn (array $left, array $right): int => strcasecmp(
                (string) ($left['title'] ?? $left['slug'] ?? ''),
                (string) ($right['title'] ?? $right['slug'] ?? '')
            )
        );

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
        $translate = static function (string $key, string $fallback): string {
            if (function_exists('admin_translate')) {
                return admin_translate($key, $fallback);
            }

            if (!function_exists('t')) {
                return $fallback;
            }

            $translated = t($key);
            if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
                return $fallback;
            }

            return $translated;
        };
        $articleData = is_array($body['article'] ?? null) ? $body['article'] : $body;
        $translationData = $this->normalizePostedTranslations($body['translations'] ?? []);
        $existingBundle = is_string($currentSlug) && $currentSlug !== ''
            ? $this->loadArticleBundle($currentSlug)
            : [];
        $activeLanguage = $this->normalizeLanguage((string) ($articleData['active_language'] ?? $currentLanguage ?? $this->defaultLanguage));
        if ($activeLanguage === '') {
            $activeLanguage = $this->defaultLanguage;
        }

        $sharedPayload = [
            'slug' => is_string($articleData['slug'] ?? null) ? trim((string) $articleData['slug']) : '',
            'status' => is_string($articleData['status'] ?? null) ? trim((string) $articleData['status']) : 'draft',
            'author' => is_string($articleData['author'] ?? null) ? trim((string) $articleData['author']) : '',
            'date' => $this->normalizeDateInput(
                $this->resolveDateInputForStatus(
                    is_string($articleData['status'] ?? null) ? (string) $articleData['status'] : 'draft',
                    is_string($articleData['date'] ?? null) ? (string) $articleData['date'] : '',
                    is_string($articleData['scheduled_publish_at'] ?? null) ? (string) $articleData['scheduled_publish_at'] : ''
                )
            ),
            'page_slug' => is_string($articleData['page_slug'] ?? null) ? trim((string) $articleData['page_slug']) : '',
            'parent_slug' => is_string($articleData['parent_slug'] ?? null) ? trim((string) $articleData['parent_slug']) : '',
            'child_sort_order' => is_scalar($articleData['child_sort_order'] ?? null)
                ? trim((string) $articleData['child_sort_order'])
                : '',
            'category' => is_string($articleData['category'] ?? null) ? trim((string) $articleData['category']) : '',
            'subcategory' => is_string($articleData['subcategory'] ?? null) ? trim((string) $articleData['subcategory']) : '',
            'tags' => $this->articleDataTags($articleData),
            'featured_image' => $this->buildFeaturedImagePayload($articleData),
        ];
        $form = $this->mapPostedBundleFormData($sharedPayload, $translationData, $existingBundle, $activeLanguage);

        $selectedPageSlug = trim((string) $sharedPayload['page_slug']);
        if ($selectedPageSlug === '') {
            return [
                'success' => false,
                'form' => $form,
                'error' => $translate('TXT_ADMIN_ARTICLE_PARENT_PAGE_REQUIRED', 'La page parent est obligatoire pour rattacher l’article.'),
            ];
        }

        if (!is_array($this->pageRepository->findBySlug($selectedPageSlug))) {
            return [
                'success' => false,
                'form' => $form,
                'error' => $translate('TXT_ADMIN_ARTICLE_PARENT_PAGE_NOT_FOUND', 'La page parent sélectionnée est introuvable.'),
            ];
        }

        $validatedPayloads = [];
        $errors = [];

        foreach ($this->availableLanguages as $language) {
            $existing = is_array($existingBundle[$language] ?? null) ? $existingBundle[$language] : null;
            $translation = is_array($translationData[$language] ?? null) ? $translationData[$language] : [];

            if (!$this->shouldPersistLanguageVariant($translation, $existing)) {
                continue;
            }

            $payload = array_merge(
                $sharedPayload,
                [
                    'title' => trim((string) ($translation['title'] ?? '')),
                    'excerpt' => trim((string) ($translation['excerpt'] ?? '')),
                    'content' => trim((string) ($translation['content'] ?? '')),
                    'lang' => $language,
                    'previous_slug' => is_array($existing) ? (string) ($existing['slug'] ?? '') : '',
                    'previous_lang' => is_array($existing) ? (string) ($existing['lang'] ?? $language) : '',
                    'translations' => is_array($existing['translations'] ?? null) ? $existing['translations'] : [],
                    'comments' => is_array($existing['comments'] ?? null) ? $existing['comments'] : [],
                ]
            );
            $validation = $this->saveService->validatePayload($payload, $actorIdentifier);

            if (($validation['ok'] ?? false) !== true) {
                foreach (is_array($validation['errors'] ?? null) ? $validation['errors'] : [$translate('TXT_ADMIN_ARTICLE_SAVE_FAILED', 'Impossible de sauvegarder l’article.')] as $error) {
                    $message = strtoupper($language) . ' : ' . (string) $error;
                    $errors[$message] = $message;
                }
                continue;
            }

            $validatedPayloads[$language] = [
                'article' => is_array($validation['data'] ?? null) ? $validation['data'] : $payload,
                'previous_slug' => is_string($validation['previous_slug'] ?? null) ? (string) $validation['previous_slug'] : '',
                'previous_language' => is_string($validation['previous_language'] ?? null) ? (string) $validation['previous_language'] : '',
            ];
        }

        if ($validatedPayloads === []) {
            $errors['bundle'] = $translate('TXT_ADMIN_ARTICLE_AT_LEAST_ONE_TRANSLATION', 'Au moins une traduction complete doit être renseignée.');
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'form' => $form,
                'error' => implode(' ', array_map('strval', array_values($errors))),
            ];
        }

        $savedBundle = $existingBundle;
        foreach ($validatedPayloads as $language => $validatedPayload) {
            $article = is_array($validatedPayload['article'] ?? null) ? $validatedPayload['article'] : [];
            $previousSlug = is_string($validatedPayload['previous_slug'] ?? null) ? (string) $validatedPayload['previous_slug'] : '';
            $previousLanguage = is_string($validatedPayload['previous_language'] ?? null) ? (string) $validatedPayload['previous_language'] : '';

            try {
                $saved = $this->repository->save(
                    $article,
                    $previousSlug !== '' ? $previousSlug : null,
                    $previousSlug !== '' ? $previousLanguage : null
                );
            } catch (\Throwable) {
                return [
                    'success' => false,
                    'form' => $form,
                    'error' => sprintf(
                        $translate('TXT_ADMIN_ARTICLE_TRANSLATION_SAVE_FAILED', 'Impossible d’enregistrer la traduction %s pour le moment.'),
                        strtoupper($language)
                    ),
                ];
            }

            if (
                $previousSlug !== ''
                && $previousLanguage === $language
                && $previousSlug !== (string) ($article['slug'] ?? '')
            ) {
                $this->repository->reassignChildrenToParentSlug($previousSlug, $language, (string) ($article['slug'] ?? ''));
            }

            $savedBundle[$language] = is_array($saved['article'] ?? null) ? $saved['article'] : $article;
        }

        $savedSlug = $sharedPayload['slug'] !== ''
            ? $this->normalizeSlug((string) $sharedPayload['slug'])
            : $this->normalizeSlug((string) ($currentSlug ?? ''));
        $redirectLanguage = isset($savedBundle[$activeLanguage])
            ? $activeLanguage
            : (array_key_first($savedBundle) ?: $this->defaultLanguage);

        return [
            'success' => true,
            'form' => $this->mapBundleFormData($savedBundle, $redirectLanguage),
            'error' => null,
            'slug' => $savedSlug,
            'lang' => $redirectLanguage,
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
            'category' => $this->taxonomy->categoryLabel((string) ($article['category'] ?? ''), $language),
            'subcategory' => $this->taxonomy->subcategoryLabel((string) ($article['subcategory'] ?? ''), $language),
            'tags' => array_values(array_map(
                fn (string $tag): string => $this->taxonomy->tagLabel($tag, $language),
                array_map('strval', is_array($article['tags'] ?? null) ? $article['tags'] : [])
            )),
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
        $data = $this->emptyTranslationFormData();
        $data['title'] = (string) ($article['title'] ?? '');
        $data['excerpt'] = (string) ($article['excerpt'] ?? '');
        $data['content'] = (string) ($article['content'] ?? '');
        $data['exists'] = true;
        $data['status'] = $this->normalizeStatus((string) ($article['status'] ?? 'draft'));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTranslationFormData(): array
    {
        return [
            'title' => '',
            'excerpt' => '',
            'content' => '',
            'exists' => false,
            'status' => 'draft',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $articlesByLanguage
     * @return array<string, mixed>
     */
    private function mapBundleFormData(array $articlesByLanguage, string $preferredLanguage): array
    {
        $data = $this->emptyFormData();
        $representative = $this->resolveBundleRepresentativeArticle($articlesByLanguage, $preferredLanguage);
        if ($representative === null) {
            return $data;
        }

        $data['slug'] = (string) ($representative['slug'] ?? '');
        $data['lang'] = $this->normalizeLanguage($preferredLanguage) !== '' ? $preferredLanguage : $this->defaultLanguage;
        $data['active_language'] = $data['lang'];
        $data['status'] = $this->normalizeStatus((string) ($representative['status'] ?? 'draft'));
        $data['author'] = (string) ($representative['author'] ?? '');
        $data['date'] = $this->formatDateInput((string) ($representative['date'] ?? ''));
        $data['scheduled_publish_at'] = $data['status'] === 'scheduled'
            ? $this->formatDateInputWithoutFallback((string) ($representative['date'] ?? ''))
            : '';
        $data['page_slug'] = (string) ($representative['page_slug'] ?? '');
        $data['parent_slug'] = (string) ($representative['parent_slug'] ?? '');
        $childSortOrder = $representative['child_sort_order'] ?? null;
        $data['child_sort_order'] = $childSortOrder !== null && $childSortOrder !== ''
            ? (string) $childSortOrder
            : '';

        $category = $this->taxonomy->resolveCategorySlug($representative['category'] ?? null);
        $subcategory = $this->taxonomy->resolveSubcategorySlug($representative['subcategory'] ?? null, $category);
        $tags = [];
        foreach (is_array($representative['tags'] ?? null) ? $representative['tags'] : [] as $tag) {
            $tagSlug = $this->taxonomy->resolveTagSlug($tag);
            if ($tagSlug !== null) {
                $tags[$tagSlug] = $tagSlug;
            }
        }
        $data['category'] = $category ?? trim((string) ($representative['category'] ?? ''));
        $data['subcategory'] = $subcategory ?? trim((string) ($representative['subcategory'] ?? ''));
        $data['tags'] = array_values($tags);
        $data['tags_input'] = implode(', ', array_values($tags));

        $featuredImage = AdminEditorialImageService::sanitizeImageMetadata(
            is_array($representative['featured_image'] ?? null) ? $representative['featured_image'] : []
        );
        $data['featured_image_src'] = (string) ($featuredImage['src'] ?? '');
        $data['featured_image_alt'] = (string) ($featuredImage['alt'] ?? '');
        $data['featured_image_title'] = (string) ($featuredImage['title'] ?? '');
        $data['featured_image_caption'] = (string) ($featuredImage['caption'] ?? '');
        $data['featured_image_width'] = isset($featuredImage['width']) ? (string) $featuredImage['width'] : '';
        $data['featured_image_height'] = isset($featuredImage['height']) ? (string) $featuredImage['height'] : '';

        $existingLanguages = [];
        foreach ($this->availableLanguages as $language) {
            $article = is_array($articlesByLanguage[$language] ?? null) ? $articlesByLanguage[$language] : null;
            $data['translations'][$language] = is_array($article) ? $this->mapFormData($article) : $this->emptyTranslationFormData();
            if (is_array($article)) {
                $existingLanguages[] = $language;
            }
        }
        $data['existing_languages'] = $existingLanguages;

        return $data;
    }

    /**
     * @param array<string, mixed> $sharedPayload
     * @param array<string, array<string, mixed>> $translationData
     * @param array<string, array<string, mixed>> $existingBundle
     * @return array<string, mixed>
     */
    private function mapPostedBundleFormData(
        array $sharedPayload,
        array $translationData,
        array $existingBundle,
        string $activeLanguage
    ): array {
        $data = $this->emptyFormData();
        $data['slug'] = (string) ($sharedPayload['slug'] ?? '');
        $data['lang'] = $activeLanguage;
        $data['active_language'] = $activeLanguage;
        $data['status'] = $this->normalizeStatus((string) ($sharedPayload['status'] ?? 'draft'));
        $data['author'] = (string) ($sharedPayload['author'] ?? '');
        $data['date'] = $this->formatDateInput((string) ($sharedPayload['date'] ?? ''));
        $data['scheduled_publish_at'] = $data['status'] === 'scheduled'
            ? $this->formatDateInputWithoutFallback((string) ($sharedPayload['date'] ?? ''))
            : '';
        $data['page_slug'] = (string) ($sharedPayload['page_slug'] ?? '');
        $data['parent_slug'] = (string) ($sharedPayload['parent_slug'] ?? '');
        $data['child_sort_order'] = (string) ($sharedPayload['child_sort_order'] ?? '');
        $data['category'] = (string) ($sharedPayload['category'] ?? '');
        $data['subcategory'] = (string) ($sharedPayload['subcategory'] ?? '');
        $data['tags'] = array_values(array_map('strval', is_array($sharedPayload['tags'] ?? null) ? $sharedPayload['tags'] : []));
        $data['tags_input'] = implode(', ', $data['tags']);

        $featuredImage = AdminEditorialImageService::sanitizeImageMetadata(
            is_array($sharedPayload['featured_image'] ?? null) ? $sharedPayload['featured_image'] : []
        );
        $data['featured_image_src'] = (string) ($featuredImage['src'] ?? '');
        $data['featured_image_alt'] = (string) ($featuredImage['alt'] ?? '');
        $data['featured_image_title'] = (string) ($featuredImage['title'] ?? '');
        $data['featured_image_caption'] = (string) ($featuredImage['caption'] ?? '');
        $data['featured_image_width'] = isset($featuredImage['width']) ? (string) $featuredImage['width'] : '';
        $data['featured_image_height'] = isset($featuredImage['height']) ? (string) $featuredImage['height'] : '';

        $existingLanguages = [];
        foreach ($this->availableLanguages as $language) {
            $translation = is_array($translationData[$language] ?? null) ? $translationData[$language] : [];
            $data['translations'][$language] = array_merge(
                $this->emptyTranslationFormData(),
                [
                    'title' => trim((string) ($translation['title'] ?? '')),
                    'excerpt' => trim((string) ($translation['excerpt'] ?? '')),
                    'content' => trim((string) ($translation['content'] ?? '')),
                    'exists' => is_array($existingBundle[$language] ?? null),
                    'status' => is_array($existingBundle[$language] ?? null)
                        ? $this->normalizeStatus((string) ($existingBundle[$language]['status'] ?? 'draft'))
                        : $data['status'],
                ]
            );
            if (is_array($existingBundle[$language] ?? null)) {
                $existingLanguages[] = $language;
            }
        }
        $data['existing_languages'] = $existingLanguages;

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
     * @return array<string, array<string, mixed>>
     */
    private function loadArticleBundle(string $slug): array
    {
        $normalizedSlug = $this->normalizeSlug($slug);
        if ($normalizedSlug === '') {
            return [];
        }

        $bundle = [];
        foreach ($this->repository->allArticles() as $article) {
            if ($this->normalizeSlug((string) ($article['slug'] ?? '')) !== $normalizedSlug) {
                continue;
            }

            $language = $this->normalizeLanguage((string) ($article['lang'] ?? $this->defaultLanguage));
            if ($language === '') {
                continue;
            }

            $bundle[$language] = $article;
        }

        return $bundle;
    }

    /**
     * @param array<string, array<string, mixed>> $articlesByLanguage
     * @return array<string, mixed>|null
     */
    private function resolveBundleRepresentativeArticle(array $articlesByLanguage, string $preferredLanguage): ?array
    {
        $normalizedPreferredLanguage = $this->normalizeLanguage($preferredLanguage);
        if ($normalizedPreferredLanguage !== '' && is_array($articlesByLanguage[$normalizedPreferredLanguage] ?? null)) {
            return $articlesByLanguage[$normalizedPreferredLanguage];
        }

        if (is_array($articlesByLanguage[$this->defaultLanguage] ?? null)) {
            return $articlesByLanguage[$this->defaultLanguage];
        }

        foreach ($this->availableLanguages as $language) {
            if (is_array($articlesByLanguage[$language] ?? null)) {
                return $articlesByLanguage[$language];
            }
        }

        foreach ($articlesByLanguage as $article) {
            if (is_array($article)) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizePostedTranslations(mixed $value): array
    {
        $translations = [];
        $rawTranslations = is_array($value) ? $value : [];

        foreach ($this->availableLanguages as $language) {
            $translation = is_array($rawTranslations[$language] ?? null) ? $rawTranslations[$language] : [];
            $translations[$language] = [
                'title' => is_string($translation['title'] ?? null) ? trim((string) $translation['title']) : '',
                'excerpt' => is_string($translation['excerpt'] ?? null) ? trim((string) $translation['excerpt']) : '',
                'content' => is_string($translation['content'] ?? null) ? trim((string) $translation['content']) : '',
            ];
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $translation
     * @param array<string, mixed>|null $existing
     */
    private function shouldPersistLanguageVariant(array $translation, ?array $existing): bool
    {
        if (is_array($existing)) {
            return true;
        }

        foreach (['title', 'excerpt', 'content'] as $field) {
            if (trim((string) ($translation[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
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
     * @param array<string, mixed> $articleData
     * @return array<int, string>
     */
    private function articleDataTags(array $articleData): array
    {
        if (is_array($articleData['tags'] ?? null)) {
            return array_values(array_map('strval', $articleData['tags']));
        }

        return $this->parseTags(is_string($articleData['tags_input'] ?? null) ? (string) $articleData['tags_input'] : '');
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
