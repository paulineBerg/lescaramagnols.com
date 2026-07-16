<?php

declare(strict_types=1);

namespace Caramagnols\Seo;

final class StructuredDataBuilder
{
    /**
     * @param array<int, string> $languages
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $organizationName,
        private readonly string $organizationDescription,
        private readonly string $personName,
        private readonly string $personJobTitle,
        private readonly string $websiteName,
        private readonly array $languages = ['fr', 'en', 'de']
    ) {
    }

    public static function fromRuntime(): self
    {
        $baseUrl = function_exists('app_base_url') ? (string) app_base_url() : '';
        if ($baseUrl === '' || $baseUrl === '/') {
            $configuredSiteUrl = function_exists('app_config') ? app_config('site.url', []) : [];
            $siteUrl = is_array($configuredSiteUrl) ? $configuredSiteUrl : [];
            $host = trim((string) ($siteUrl['ssl_domain'] ?? $siteUrl['domain'] ?? ''));
            $basePath = function_exists('normalize_public_route')
                ? (normalize_public_route((string) ($siteUrl['base_path'] ?? '/')) ?? '/')
                : '/';

            $baseUrl = $host !== ''
                ? 'https://' . $host . ($basePath === '/' ? '' : $basePath)
                : 'https://www.lescaramagnols.com';
        }

        $translate = static function (string $key, string $fallback): string {
            if (!function_exists('t')) {
                return $fallback;
            }

            $translated = (string) t($key);
            if ($translated === '' || $translated === '[[' . $key . ']]') {
                return $fallback;
            }

            return $translated;
        };

        $languages = function_exists('site_available_languages') ? site_available_languages() : ['fr', 'en', 'de'];

        return new self(
            $baseUrl,
            $translate('TXT_SCHEMA_ORG_NAME', 'Les Caramagnols'),
            $translate('TXT_SCHEMA_ORG_DESCRIPTION', 'Passion auto-retro, voitures anciennes et decouvertes du Golfe de Saint-Tropez.'),
            $translate('TXT_SCHEMA_PERSON_NAME', 'Pauline Bergon'),
            $translate('TXT_SCHEMA_PERSON_JOB_TITLE', 'Editrice du site'),
            $translate('TXT_SCHEMA_WEBSITE_NAME', 'Les Caramagnols'),
            array_values(array_filter($languages, static fn ($language): bool => is_string($language) && trim($language) !== ''))
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function build(array $context): array
    {
        $canonicalUrl = $this->canonicalUrl($context['canonical_url'] ?? null);
        $language = $this->languageCode((string) ($context['language'] ?? 'fr'));
        $image = $this->imageObject(is_array($context['image'] ?? null) ? $context['image'] : []);
        $article = is_array($context['article'] ?? null) ? $context['article'] : null;

        $graph = [
            $this->organizationNode(),
            $this->founderNode(),
            $this->websiteNode(),
        ];

        $articleNode = $article !== null
            ? $this->blogPostingNode($article, $context, $canonicalUrl, $language, $image)
            : null;

        $graph[] = $this->webPageNode($context, $canonicalUrl, $language, $image, $articleNode);

        if ($articleNode !== null) {
            $graph[] = $articleNode;
        }

        $faqNode = $this->faqNode($context, $canonicalUrl, $language);
        if ($faqNode !== null) {
            $graph[] = $faqNode;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter($graph, static fn ($node): bool => is_array($node) && $node !== [])),
        ];
    }

    private function organizationNode(): array
    {
        $node = [
            '@type' => 'Organization',
            '@id' => $this->schemaId('organization'),
            'name' => $this->organizationName,
            'url' => $this->siteUrl(),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $this->absoluteUrl('/assets/images/structure/logo.png'),
                'width' => 816,
                'height' => 815,
            ],
            'description' => $this->organizationDescription,
            'email' => 'accueil@lescaramagnols.com',
            'sameAs' => [
                'https://www.facebook.com/lescaramagnols',
                'https://www.instagram.com/paulineetnoel',
            ],
            'founder' => [
                '@id' => $this->schemaId('person/pauline-bergon'),
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '2738 route de la Mole',
                'postalCode' => '83310',
                'addressLocality' => 'Cogolin',
                'addressCountry' => 'FR',
            ],
            'identifier' => [
                '@type' => 'PropertyValue',
                'propertyID' => 'RCS',
                'value' => '803 935 725',
            ],
        ];

        return $this->withoutEmptyValues($node);
    }

    private function founderNode(): array
    {
        return $this->withoutEmptyValues([
            '@type' => 'Person',
            '@id' => $this->schemaId('person/pauline-bergon'),
            'name' => $this->personName,
            'url' => $this->siteUrl(),
            'jobTitle' => $this->personJobTitle,
            'worksFor' => [
                '@id' => $this->schemaId('organization'),
            ],
        ]);
    }

    private function websiteNode(): array
    {
        $languages = array_values(array_unique(array_map(
            fn (string $language): string => $this->languageCode($language),
            $this->languages !== [] ? $this->languages : ['fr']
        )));

        return $this->withoutEmptyValues([
            '@type' => 'WebSite',
            '@id' => $this->schemaId('website'),
            'url' => $this->siteUrl(),
            'name' => $this->websiteName,
            'publisher' => [
                '@id' => $this->schemaId('organization'),
            ],
            'inLanguage' => $languages,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $image
     * @param array<string, mixed>|null $articleNode
     */
    private function webPageNode(
        array $context,
        string $canonicalUrl,
        string $language,
        ?array $image,
        ?array $articleNode
    ): array {
        $title = trim((string) ($context['title'] ?? ''));
        $description = trim((string) ($context['description'] ?? ''));
        $kind = trim((string) ($context['page_kind'] ?? ''));
        $type = $kind === 'blog_index' ? 'CollectionPage' : 'WebPage';

        $node = [
            '@type' => $type,
            '@id' => $canonicalUrl,
            'url' => $canonicalUrl,
            'name' => $title,
            'description' => $description,
            'inLanguage' => $language,
            'isPartOf' => [
                '@id' => $this->schemaId('website'),
            ],
            'publisher' => [
                '@id' => $this->schemaId('organization'),
            ],
        ];

        if ($image !== null) {
            $node['primaryImageOfPage'] = $image;
            $node['thumbnailUrl'] = (string) ($image['url'] ?? '');
        }

        if ($articleNode !== null && isset($articleNode['@id'])) {
            $node['mainEntity'] = [
                '@id' => (string) $articleNode['@id'],
            ];
        }

        return $this->withoutEmptyValues($node);
    }

    /**
     * @param array<string, mixed> $article
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $image
     * @return array<string, mixed>|null
     */
    private function blogPostingNode(array $article, array $context, string $canonicalUrl, string $language, ?array $image): ?array
    {
        $slug = $this->slug((string) ($article['slug'] ?? ''));
        $headline = trim((string) ($article['title'] ?? $context['title'] ?? ''));
        if ($slug === '' || $headline === '') {
            return null;
        }

        $description = trim((string) ($article['excerpt'] ?? $context['description'] ?? ''));
        $datePublished = $this->isoDate($article['date'] ?? $article['created_at'] ?? null);
        $dateModified = $this->isoDate($article['updated_at'] ?? null) ?: $datePublished;
        $authorName = trim((string) ($article['author'] ?? ''));
        if ($authorName === '') {
            $authorName = $this->organizationName;
        }

        $node = [
            '@type' => 'BlogPosting',
            '@id' => $this->schemaId('blog-posting/' . rawurlencode($language) . '/' . rawurlencode($slug)),
            'mainEntityOfPage' => [
                '@id' => $canonicalUrl,
            ],
            'url' => $canonicalUrl,
            'headline' => $headline,
            'description' => $description,
            'inLanguage' => $language,
            'author' => $this->authorNode($authorName),
            'publisher' => [
                '@id' => $this->schemaId('organization'),
            ],
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
        ];

        if ($image !== null) {
            $node['image'] = $image;
        }

        $tags = is_array($article['tags'] ?? null) ? $article['tags'] : [];
        $keywords = array_values(array_filter(array_map(
            static fn ($tag): string => trim((string) $tag),
            $tags
        )));
        if ($keywords !== []) {
            $node['keywords'] = implode(', ', $keywords);
        }

        return $this->withoutEmptyValues($node);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function faqNode(array $context, string $canonicalUrl, string $language): ?array
    {
        $items = is_array($context['faq'] ?? null) ? $context['faq'] : [];
        $questions = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim(strip_tags((string) ($item['question'] ?? '')));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $questions[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($questions === []) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => $this->schemaId('faq/' . md5($canonicalUrl)),
            'url' => $canonicalUrl,
            'inLanguage' => $language,
            'mainEntity' => $questions,
        ];
    }

    /**
     * @param array<string, mixed> $image
     * @return array<string, mixed>|null
     */
    private function imageObject(array $image): ?array
    {
        $url = $this->absoluteUrl((string) ($image['url'] ?? $image['src'] ?? ''));
        if ($url === '') {
            return null;
        }

        $object = [
            '@type' => 'ImageObject',
            'url' => $url,
        ];

        $width = $this->positiveInt($image['width'] ?? null);
        $height = $this->positiveInt($image['height'] ?? null);
        if ($width !== null) {
            $object['width'] = $width;
        }

        if ($height !== null) {
            $object['height'] = $height;
        }

        $alt = trim((string) ($image['alt'] ?? ''));
        if ($alt !== '') {
            $object['name'] = $alt;
        }

        return $object;
    }

    /**
     * @return array<string, string>
     */
    private function authorNode(string $name): array
    {
        if (preg_match('/caramagnols/i', $name) === 1) {
            return [
                '@type' => 'Organization',
                '@id' => $this->schemaId('organization'),
                'name' => $name,
            ];
        }

        return [
            '@type' => 'Person',
            'name' => $name,
        ];
    }

    private function canonicalUrl(mixed $value): string
    {
        $url = is_scalar($value) ? trim((string) $value) : '';
        if ($url === '') {
            return $this->siteUrl();
        }

        return $this->absoluteUrl($url);
    }

    private function absoluteUrl(string $url): string
    {
        return SeoUrlNormalizer::absoluteWithoutFragment($url, $this->siteUrl());
    }

    private function siteUrl(): string
    {
        return rtrim(SeoUrlNormalizer::withoutFragment($this->baseUrl), '/');
    }

    private function schemaId(string $path): string
    {
        return $this->siteUrl() . '/schema/' . trim($path, '/');
    }

    private function languageCode(string $language): string
    {
        return match (strtolower(trim($language))) {
            'fr', 'fr-fr' => 'fr-FR',
            'en', 'en-us', 'en-gb' => 'en',
            'de', 'de-de' => 'de',
            default => strtolower(trim($language)) !== '' ? strtolower(trim($language)) : 'fr-FR',
        };
    }

    private function isoDate(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $timestamp = strtotime($raw);
        if (!is_int($timestamp)) {
            return '';
        }

        return date(DATE_ATOM, $timestamp);
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]+/i', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function positiveInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function withoutEmptyValues(array $node): array
    {
        foreach ($node as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                unset($node[$key]);
            }
        }

        return $node;
    }
}
