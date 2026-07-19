<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class BlogTaxonomy
{
    public const MIN_TAGS = 3;
    public const MAX_TAGS = 5;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function fromDefaultConfig(): self
    {
        $configPath = ROOT_PATH . '/config/blog_taxonomy.php';
        $config = is_file($configPath) ? require $configPath : [];

        return new self(is_array($config) ? $config : []);
    }

    /**
     * @return array<int, array{slug: string, label: string, seo: string}>
     */
    public function categoryOptions(string $language = 'fr'): array
    {
        $options = [];

        foreach ($this->categories() as $slug => $category) {
            $options[] = [
                'slug' => $slug,
                'label' => $this->labelFromNode($category, $language, $slug),
                'seo' => $this->seoStatus($category),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{slug: string, category: string, label: string, seo: string}>
     */
    public function subcategoryOptions(?string $categorySlug = null, string $language = 'fr'): array
    {
        $resolvedCategory = $this->resolveCategorySlug($categorySlug);
        $options = [];

        foreach ($this->subcategories() as $slug => $subcategory) {
            $parentCategory = (string) ($subcategory['category'] ?? '');
            if ($resolvedCategory !== null && $parentCategory !== $resolvedCategory) {
                continue;
            }

            $options[] = [
                'slug' => $slug,
                'category' => $parentCategory,
                'label' => $this->labelFromNode($subcategory, $language, $slug),
                'seo' => $this->seoStatus($subcategory),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{slug: string, label: string, seo: string}>
     */
    public function tagOptions(string $language = 'fr'): array
    {
        $options = [];
        $defaultSeo = $this->tagDefaultSeoStatus();

        foreach ($this->tags() as $slug => $tag) {
            $options[] = [
                'slug' => $slug,
                'label' => $this->labelFromNode(is_array($tag) ? $tag : [], $language, $slug),
                'seo' => $this->seoStatus(is_array($tag) ? $tag : [], $defaultSeo),
            ];
        }

        return $options;
    }

    public function categoryLabel(?string $value, string $language = 'fr'): string
    {
        $slug = $this->resolveCategorySlug($value);
        if ($slug === null) {
            return trim((string) $value);
        }

        $category = $this->categories()[$slug] ?? [];

        return $this->labelFromNode(is_array($category) ? $category : [], $language, $slug);
    }

    public function subcategoryLabel(?string $value, string $language = 'fr'): string
    {
        $slug = $this->resolveSubcategorySlug($value);
        if ($slug === null) {
            return trim((string) $value);
        }

        $subcategory = $this->subcategories()[$slug] ?? [];

        return $this->labelFromNode(is_array($subcategory) ? $subcategory : [], $language, $slug);
    }

    public function tagLabel(?string $value, string $language = 'fr'): string
    {
        $slug = $this->resolveTagSlug($value);
        if ($slug === null) {
            return trim((string) $value);
        }

        $tag = $this->tags()[$slug] ?? [];

        return $this->labelFromNode(is_array($tag) ? $tag : [], $language, $slug);
    }

    public function resolveCategorySlug(mixed $value): ?string
    {
        return $this->resolveSlug($value, $this->categories(), $this->aliases('categories'));
    }

    public function resolveSubcategorySlug(mixed $value, ?string $categorySlug = null): ?string
    {
        $slug = $this->resolveSlug($value, $this->subcategories(), $this->aliases('subcategories'));
        if ($slug === null) {
            return null;
        }

        $resolvedCategory = $this->resolveCategorySlug($categorySlug);
        if ($resolvedCategory === null) {
            return $slug;
        }

        $subcategory = $this->subcategories()[$slug] ?? [];

        return (string) ($subcategory['category'] ?? '') === $resolvedCategory ? $slug : null;
    }

    public function resolveTagSlug(mixed $value): ?string
    {
        return $this->resolveSlug($value, $this->tags(), $this->aliases('tags'));
    }

    /**
     * @param array<int, mixed> $tags
     * @return array{category: string, subcategory: string, tags: array<int, string>, errors: array<int, string>}
     */
    public function validateArticleTaxonomy(mixed $category, mixed $subcategory, array $tags): array
    {
        $errors = [];
        $categorySlug = $this->resolveCategorySlug($category);
        if ($categorySlug === null) {
            $errors[] = trim((string) $category) === ''
                ? 'La catégorie blog est obligatoire.'
                : 'La catégorie blog sélectionnée n’est pas autorisée.';
        }

        $subcategoryValues = is_array($subcategory)
            ? array_values(array_filter(array_map('strval', $subcategory), static fn (string $value): bool => trim($value) !== ''))
            : [trim((string) $subcategory)];
        if (count($subcategoryValues) > 1) {
            $errors[] = 'Un article blog ne peut avoir qu’une seule sous-catégorie.';
        }

        $subcategoryValue = trim((string) ($subcategoryValues[0] ?? ''));
        $subcategorySlug = '';
        if ($subcategoryValue !== '') {
            $resolvedSubcategory = $this->resolveSubcategorySlug($subcategoryValue);
            if ($resolvedSubcategory === null) {
                $errors[] = 'La sous-catégorie blog sélectionnée n’est pas autorisée.';
            } elseif ($categorySlug !== null && !$this->subcategoryBelongsTo($resolvedSubcategory, $categorySlug)) {
                $errors[] = 'La sous-catégorie blog ne correspond pas à la catégorie sélectionnée.';
            } else {
                $subcategorySlug = $resolvedSubcategory;
            }
        }

        $resolvedTags = [];
        $unknownTags = [];
        foreach ($tags as $rawTag) {
            $raw = trim((string) $rawTag);
            if ($raw === '') {
                continue;
            }

            $tagSlug = $this->resolveTagSlug($raw);
            if ($tagSlug === null) {
                $unknownTags[] = $raw;
                continue;
            }

            if (isset($resolvedTags[$tagSlug])) {
                $errors[] = sprintf('Le tag blog "%s" est un doublon.', $raw);
                continue;
            }

            $resolvedTags[$tagSlug] = $tagSlug;
        }

        foreach (array_values(array_unique($unknownTags)) as $unknownTag) {
            $errors[] = sprintf('Le tag blog "%s" n’est pas autorisé.', $unknownTag);
        }

        $tagSlugs = array_values($resolvedTags);
        $tagCount = count($tagSlugs);
        if ($tagCount < self::MIN_TAGS) {
            $errors[] = sprintf('Un article blog doit avoir au moins %d tags autorisés.', self::MIN_TAGS);
        }
        if ($tagCount > self::MAX_TAGS) {
            $errors[] = sprintf('Un article blog ne peut pas avoir plus de %d tags.', self::MAX_TAGS);
        }

        return [
            'category' => $categorySlug ?? '',
            'subcategory' => $subcategorySlug,
            'tags' => array_slice($tagSlugs, 0, self::MAX_TAGS),
            'errors' => $errors,
        ];
    }

    public function subcategoryBelongsTo(string $subcategorySlug, string $categorySlug): bool
    {
        $subcategory = $this->subcategories()[$subcategorySlug] ?? [];

        return (string) ($subcategory['category'] ?? '') === $categorySlug;
    }

    public function tagDefaultSeoStatus(): string
    {
        $seo = is_array($this->config['seo'] ?? null) ? $this->config['seo'] : [];
        $status = (string) ($seo['tag_default'] ?? 'noindex');

        return in_array($status, ['index', 'noindex'], true) ? $status : 'noindex';
    }

    public function normalizeKebabSlug(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $transliterated = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized)
            : $normalized;
        if (!is_string($transliterated) || trim($transliterated) === '') {
            $transliterated = $normalized;
        }

        $slug = strtolower(trim($transliterated));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    /**
     * @param array<string, mixed> $allowed
     * @param array<string, string> $aliases
     */
    private function resolveSlug(mixed $value, array $allowed, array $aliases): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (array_key_exists($raw, $allowed)) {
            return $raw;
        }

        $normalized = $this->normalizeKebabSlug($raw);
        if ($normalized !== '' && array_key_exists($normalized, $allowed)) {
            return $normalized;
        }

        foreach ($aliases as $alias => $target) {
            if ($normalized !== '' && $this->normalizeKebabSlug($alias) === $normalized && array_key_exists($target, $allowed)) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function categories(): array
    {
        return is_array($this->config['categories'] ?? null) ? $this->config['categories'] : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function subcategories(): array
    {
        return is_array($this->config['subcategories'] ?? null) ? $this->config['subcategories'] : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tags(): array
    {
        return is_array($this->config['tags'] ?? null) ? $this->config['tags'] : [];
    }

    /**
     * @return array<string, string>
     */
    private function aliases(string $kind): array
    {
        $aliases = is_array($this->config['aliases'] ?? null) ? $this->config['aliases'] : [];
        $values = is_array($aliases[$kind] ?? null) ? $aliases[$kind] : [];

        $normalized = [];
        foreach ($values as $alias => $target) {
            if (is_string($alias) && is_string($target)) {
                $normalized[$alias] = $target;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function labelFromNode(array $node, string $language, string $fallback): string
    {
        $labels = is_array($node['label'] ?? null) ? $node['label'] : [];
        if ($labels === []) {
            $labels = array_filter(
                $node,
                static fn (mixed $value, string $key): bool => in_array($key, ['fr', 'en', 'de'], true) && is_string($value),
                ARRAY_FILTER_USE_BOTH
            );
        }

        return $this->translatedLabel($labels, $language, $fallback);
    }

    /**
     * @param array<string, mixed> $labels
     */
    private function translatedLabel(array $labels, string $language, string $fallback): string
    {
        $normalizedLanguage = strtolower(trim($language));
        $label = $labels[$normalizedLanguage] ?? $labels['fr'] ?? reset($labels);

        return is_string($label) && trim($label) !== '' ? $label : $fallback;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function seoStatus(array $node, string $default = 'index'): string
    {
        $status = (string) ($node['seo'] ?? $default);

        return in_array($status, ['index', 'noindex'], true) ? $status : $default;
    }
}
