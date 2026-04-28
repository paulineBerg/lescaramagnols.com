<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Admin\AdminEditorialImageService;
use Caramagnols\Content\PageRepository;
use Caramagnols\Http\PublicUrlNormalizer;
use Caramagnols\Logging\AppEventLogger;

final class BlogSaveService
{
    private AppEventLogger $eventLogger;
    private PageRepository $pageRepository;
    private BlogTaxonomy $taxonomy;
    private BlogPublicUrlResolver $publicUrlResolver;

    public function __construct(
        private readonly BlogRepositoryInterface $repository,
        ?AppEventLogger $eventLogger = null,
        ?PageRepository $pageRepository = null,
        ?BlogTaxonomy $taxonomy = null
    ) {
        $this->eventLogger = $eventLogger ?? app_event_logger();
        $this->pageRepository = $pageRepository ?? page_repository(pages_data_path());
        $this->taxonomy = $taxonomy ?? BlogTaxonomy::fromDefaultConfig();
        $this->publicUrlResolver = new BlogPublicUrlResolver(
            $this->repository,
            $this->pageRepository,
            (string) app_config('default_lang', 'fr')
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   ok: bool,
     *   status: int,
     *   data?: array<string, mixed>,
     *   errors?: array<int, string>,
     *   path?: string
     * }
     */
    public function save(array $payload, ?string $actorIdentifier = null): array
    {
        $errors = [];

        $title = sanitize_text_field((string) ($payload['title'] ?? ''), 180, ['strong', 'em']);
        if ($title === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        $slug = $this->sanitizeSlug((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            $errors[] = 'Le slug est obligatoire.';
        }

        $language = $this->sanitizeLanguage((string) ($payload['lang'] ?? (defined('CURRENT_LANG') ? CURRENT_LANG : app_config('default_lang', 'fr'))));
        if ($language === null) {
            $errors[] = 'La langue est invalide.';
            $language = (string) app_config('default_lang', 'fr');
        }

        $status = $this->sanitizeStatus($payload['status'] ?? null);
        if ($status === null) {
            $errors[] = 'Le statut doit être "draft", "scheduled" ou "published".';
            $status = 'draft';
        }

        $content = sanitize_text_field(
            (string) ($payload['content'] ?? ''),
            40000,
            [
                'strong',
                'em',
                'p',
                'ul',
                'ol',
                'li',
                'a',
                'blockquote',
                'br',
                'h2',
                'h3',
                'h4',
                'figure',
                'figcaption',
                'img',
                'video',
                'source',
            ]
        );
        if ($content === '') {
            $errors[] = 'Le contenu est obligatoire.';
        }
        $contentBaseRoute = $this->publicUrlResolver->publicPathForArticle([
            'slug' => $slug,
            'lang' => $language,
            'page_slug' => $payload['page_slug'] ?? '',
        ]) ?? $this->publicUrlResolver->fallbackArticlePath($slug, $language);
        $content = PublicUrlNormalizer::rewriteHtmlFragment($content, $contentBaseRoute);

        $author = sanitize_text_field((string) ($payload['author'] ?? $actorIdentifier ?? ''), 120);
        $rawTags = is_array($payload['tags'] ?? null) ? $payload['tags'] : [];
        $taxonomyResult = $this->taxonomy->validateArticleTaxonomy(
            $payload['category'] ?? '',
            $payload['subcategory'] ?? '',
            $rawTags
        );
        $errors = array_merge($errors, $taxonomyResult['errors']);
        $category = $taxonomyResult['category'];
        $subcategory = $taxonomyResult['subcategory'];
        $excerpt = sanitize_text_field((string) ($payload['excerpt'] ?? $this->deriveExcerpt($content)), 320);
        $featuredImage = AdminEditorialImageService::sanitizeImageMetadata(
            is_array($payload['featured_image'] ?? null) ? $payload['featured_image'] : []
        );
        if (is_array($featuredImage) && trim((string) ($featuredImage['alt'] ?? '')) === '') {
            $featuredImage['alt'] = $title;
        }

        $tags = $taxonomyResult['tags'];

        $translations = $payload['translations'] ?? [];
        if (!is_array($translations)) {
            $errors[] = 'Le bloc "translations" doit être un objet.';
            $translations = [];
        }
        $translations = sanitize_translation_array($translations);

        $comments = [];
        if (isset($payload['comments']) && is_array($payload['comments'])) {
            foreach ($payload['comments'] as $commentPayload) {
                $result = sanitize_comment_payload(is_array($commentPayload) ? $commentPayload : []);
                if ($result['errors'] !== []) {
                    $errors = array_merge($errors, $result['errors']);
                    continue;
                }
                $comments[] = $result['data'];
            }
        }

        $rawDateInput = is_string($payload['date'] ?? null) ? trim((string) $payload['date']) : '';
        $date = $this->normalizeDate($payload['date'] ?? null, $status !== 'scheduled');
        if ($date === null) {
            $errors[] = 'La date est invalide.';
            $date = date('Y-m-d H:i:s');
        } elseif ($status === 'scheduled' && $rawDateInput === '') {
            $errors[] = 'La date de publication planifiée est obligatoire.';
        }

        $pageSlug = $this->sanitizeSlug((string) ($payload['page_slug'] ?? ''));
        if ($pageSlug !== '' && !is_array($this->pageRepository->findBySlug($pageSlug))) {
            $errors[] = 'La page sélectionnée pour l’accroche est introuvable.';
        }

        $parentSlug = $this->sanitizeSlug((string) ($payload['parent_slug'] ?? ''));
        $childSortOrderInput = $payload['child_sort_order'] ?? null;
        $childSortOrder = null;

        if ($parentSlug !== '') {
            [$childSortOrder, $sortOrderError] = $this->normalizeChildSortOrder($childSortOrderInput);
            if ($sortOrderError !== null) {
                $errors[] = $sortOrderError;
            }

            $parentLanguage = $language;

            if ($parentSlug === $slug && $parentLanguage === $language) {
                $errors[] = 'Un article ne peut pas etre son propre parent.';
            } elseif (!is_array($this->repository->find($parentSlug, $parentLanguage))) {
                $errors[] = 'L’article parent selectionne est introuvable dans cette langue.';
            } elseif ($this->wouldCreateHierarchyCycle($slug, $language, $parentSlug, $parentLanguage)) {
                $errors[] = 'Cette relation parent/enfant creerait une boucle.';
            }
        }

        $previousSlug = $this->sanitizeSlug((string) ($payload['previous_slug'] ?? ''));
        $previousLanguage = $this->sanitizeLanguage((string) ($payload['previous_lang'] ?? $language));
        if ($previousLanguage === null) {
            $previousLanguage = $language;
        }

        if (
            $previousSlug !== ''
            && $previousLanguage !== $language
            && $this->repository->hasChildren($previousSlug, $previousLanguage, false)
        ) {
            $errors[] = 'Impossible de changer la langue d’un article qui possede deja des articles enfants.';
        }

        if ($errors !== []) {
            $this->eventLogger->content(
                'blog.article.validation_failed',
                [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'slug' => $slug,
                    'lang' => $language,
                    'errors' => $errors,
                ],
                'warning'
            );

            return [
                'ok' => false,
                'status' => 422,
                'errors' => $errors,
            ];
        }

        $article = [
            'title' => $title,
            'slug' => $slug,
            'lang' => $language,
            'status' => $status,
            'author' => $author,
            'category' => $category,
            'subcategory' => $subcategory,
            'date' => $date,
            'excerpt' => $excerpt,
            'content' => $content,
            'tags' => $tags,
            'featured_image' => $featuredImage,
            'page_slug' => $pageSlug,
            'translations' => $translations,
            'comments' => $comments,
            'parent_slug' => $parentSlug,
            'parent_lang' => $parentSlug !== '' ? $language : '',
            'child_sort_order' => $parentSlug !== '' ? $childSortOrder : null,
            'updated_at' => date('c'),
        ];

        try {
            $saved = $this->repository->save(
                $article,
                $previousSlug !== '' ? $previousSlug : null,
                $previousSlug !== '' ? $previousLanguage : null
            );

            if (
                $previousSlug !== ''
                && $previousLanguage === $language
                && $previousSlug !== $slug
            ) {
                $this->repository->reassignChildrenToParentSlug($previousSlug, $language, $slug);
            }
        } catch (\Throwable $throwable) {
            $this->eventLogger->content(
                'blog.article.save_failed',
                [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'slug' => $slug,
                    'lang' => $language,
                    'exception' => $throwable->getMessage(),
                ],
                'error'
            );

            return [
                'ok' => false,
                'status' => 500,
                'errors' => ['Impossible d’enregistrer l’article pour le moment.'],
            ];
        }

        $this->eventLogger->content(
            'blog.article.saved',
            [
                'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                'slug' => $slug,
                'lang' => $language,
                'status' => $status,
                'created' => $saved['created'],
                'path' => basename($saved['path']),
            ]
        );

        return [
            'ok' => true,
            'status' => $saved['created'] ? 201 : 200,
            'data' => $saved['article'],
            'path' => $saved['path'],
        ];
    }

    private function sanitizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function sanitizeLanguage(string $language): ?string
    {
        $normalized = strtolower(trim($language));

        return in_array($normalized, site_available_languages(), true) ? $normalized : null;
    }

    private function sanitizeStatus(mixed $status): ?string
    {
        if (!is_string($status) || trim($status) === '') {
            return 'draft';
        }

        $normalized = strtolower(trim($status));

        return in_array($normalized, ['draft', 'scheduled', 'published'], true) ? $normalized : null;
    }

    private function normalizeDate(mixed $value, bool $defaultToNow = true): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return $defaultToNow ? date('Y-m-d H:i:s') : null;
        }

        $timestamp = strtotime($value);

        return is_int($timestamp) ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function normalizeChildSortOrder(mixed $value): array
    {
        if ($value === null) {
            return [null, null];
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return [null, null];
            }
        }

        if (!is_numeric($value)) {
            return [null, 'L’ordre manuel des articles enfants doit etre un nombre entier positif.'];
        }

        $normalized = (int) $value;
        if ($normalized < 1) {
            return [null, 'L’ordre manuel des articles enfants doit commencer a 1.'];
        }

        return [$normalized, null];
    }

    private function wouldCreateHierarchyCycle(string $slug, string $language, string $parentSlug, string $parentLanguage): bool
    {
        $visited = [];
        $currentSlug = $parentSlug;
        $currentLanguage = $parentLanguage;

        while ($currentSlug !== '') {
            $key = $currentLanguage . ':' . $currentSlug;

            if (isset($visited[$key])) {
                return true;
            }

            if ($currentSlug === $slug && $currentLanguage === $language) {
                return true;
            }

            $visited[$key] = true;
            $current = $this->repository->find($currentSlug, $currentLanguage);

            if (!is_array($current)) {
                return false;
            }

            $nextSlug = $this->sanitizeSlug((string) ($current['parent_slug'] ?? ''));
            if ($nextSlug === '') {
                return false;
            }

            $nextLanguage = $this->sanitizeLanguage((string) ($current['parent_lang'] ?? $currentLanguage));
            if ($nextLanguage === null) {
                return false;
            }

            $currentSlug = $nextSlug;
            $currentLanguage = $nextLanguage;
        }

        return false;
    }

    private function deriveExcerpt(string $content): string
    {
        $text = trim(strip_tags($content));

        if ($text === '') {
            return '';
        }

        $slice = function_exists('mb_substr')
            ? mb_substr($text, 0, 280)
            : substr($text, 0, 280);

        return strlen((string) $text) > strlen((string) $slice) ? (string) $slice . '…' : (string) $slice;
    }
}
