<?php

declare(strict_types=1);

namespace Caramagnols\Editorial;

use Caramagnols\Http\PublicUrlNormalizer;

final class EditorialMediaValidator
{
    private const LOCAL_MEDIA_PATTERN = '#(?:https?://(?:www\.)?lescaramagnols\.com)?(/(?:assets/images|uploads/editorial|images|structure/images)/[^"\'\s<>()]+?\.(?:png|jpe?g|gif|webp|avif|svg))(?:\?[^"\'\s<>()]*)?#i';

    public function __construct(
        private readonly string $frontendImageRoot,
        private readonly string $publicRoot
    ) {
    }

    /**
     * @param array<int, array{scope: string, entity: string, payload: array<string, mixed>}> $entries
     * @return array{entry_count: int, reference_count: int, issues: array<int, array<string, string>>}
     */
    public function validate(
        array $entries,
        bool $checkPublishedAssets = false,
        bool $skipSourceAssets = false
    ): array {
        $issues = [];
        $referenceCount = 0;

        foreach ($entries as $entry) {
            $scope = trim((string) ($entry['scope'] ?? 'editorial'));
            $entity = trim((string) ($entry['entity'] ?? 'unknown'));
            $payload = $entry['payload'] ?? null;

            if (!is_array($payload)) {
                continue;
            }

            if ($scope === 'blog') {
                $this->validateBlogMediaRequirements($issues, $entity, $payload);
            }

            foreach ($this->extractStringFields($payload) as $field => $value) {
                $matchCount = preg_match_all(
                    self::LOCAL_MEDIA_PATTERN,
                    $value,
                    $matches
                );

                if (!is_int($matchCount) || $matchCount < 1) {
                    continue;
                }

                foreach ($matches[1] as $rawReference) {
                    if (!is_string($rawReference) || trim($rawReference) === '') {
                        continue;
                    }

                    $referenceCount++;
                    $normalized = $this->normalizeReference($rawReference);
                    if ($normalized === null) {
                        $this->rememberIssue(
                            $issues,
                            'invalid_reference',
                            $scope,
                            $entity,
                            $field,
                            $rawReference
                        );
                        continue;
                    }

                    if ($this->isLegacyReference($rawReference)) {
                        $this->rememberIssue(
                            $issues,
                            'legacy_path',
                            $scope,
                            $entity,
                            $field,
                            $normalized
                        );
                    }

                    if (str_starts_with($normalized, '/assets/images/')) {
                        if (!$skipSourceAssets && !$this->sourceAssetExists($normalized)) {
                            $this->rememberIssue(
                                $issues,
                                'source_missing',
                                $scope,
                                $entity,
                                $field,
                                $normalized
                            );
                        }

                        if ($checkPublishedAssets && !$this->publishedFileExists($normalized)) {
                            $this->rememberIssue(
                                $issues,
                                'published_missing',
                                $scope,
                                $entity,
                                $field,
                                $normalized
                            );
                        }

                        continue;
                    }

                    if (str_starts_with($normalized, '/uploads/editorial/')) {
                        if (!$this->publishedFileExists($normalized)) {
                            $this->rememberIssue(
                                $issues,
                                'runtime_missing',
                                $scope,
                                $entity,
                                $field,
                                $normalized
                            );
                        }
                    }
                }
            }
        }

        return [
            'entry_count' => count($entries),
            'reference_count' => $referenceCount,
            'issues' => array_values($issues),
        ];
    }

    /**
     * @param array<string, array<string, string>> $issues
     * @param array<string, mixed> $payload
     */
    private function validateBlogMediaRequirements(array &$issues, string $entity, array $payload): void
    {
        $featuredImage = is_array($payload['featured_image'] ?? null) ? $payload['featured_image'] : [];
        $featuredSource = trim((string) ($featuredImage['src'] ?? ''));
        if ($featuredSource === '') {
            $this->rememberIssue($issues, 'featured_image_missing', 'blog', $entity, 'featured_image.src', 'required');
        }

        foreach (['alt', 'title', 'caption'] as $field) {
            if (trim((string) ($featuredImage[$field] ?? '')) === '') {
                $this->rememberIssue(
                    $issues,
                    'featured_metadata_missing',
                    'blog',
                    $entity,
                    'featured_image.' . $field,
                    'required'
                );
            }
        }

        foreach (['width', 'height'] as $field) {
            if (!is_numeric($featuredImage[$field] ?? null) || (int) $featuredImage[$field] <= 0) {
                $this->rememberIssue(
                    $issues,
                    'featured_dimension_invalid',
                    'blog',
                    $entity,
                    'featured_image.' . $field,
                    (string) ($featuredImage[$field] ?? '')
                );
            }
        }

        $content = is_string($payload['content'] ?? null) ? (string) $payload['content'] : '';
        $matchCount = preg_match_all('/<img\b[^>]*>/i', $content, $matches);
        $imageTags = is_int($matchCount) && $matchCount > 0 ? $matches[0] : [];
        if ($imageTags === []) {
            $this->rememberIssue($issues, 'body_image_missing', 'blog', $entity, 'content', 'required');

            return;
        }

        foreach ($imageTags as $index => $imageTag) {
            if (!is_string($imageTag)) {
                continue;
            }

            $bodySource = $this->htmlAttribute($imageTag, 'src');
            foreach (['src', 'alt', 'title'] as $attribute) {
                if ($this->htmlAttribute($imageTag, $attribute) === '') {
                    $this->rememberIssue(
                        $issues,
                        'body_image_metadata_missing',
                        'blog',
                        $entity,
                        sprintf('content.img.%d.%s', $index, $attribute),
                        'required'
                    );
                }
            }

            foreach (['width', 'height'] as $attribute) {
                $dimension = $this->htmlAttribute($imageTag, $attribute);
                if (!ctype_digit($dimension) || (int) $dimension <= 0) {
                    $this->rememberIssue(
                        $issues,
                        'body_image_dimension_invalid',
                        'blog',
                        $entity,
                        sprintf('content.img.%d.%s', $index, $attribute),
                        $dimension
                    );
                }
            }

            if ($featuredSource !== '' && $bodySource !== '' && $this->sameMediaPath($featuredSource, $bodySource)) {
                $this->rememberIssue(
                    $issues,
                    'featured_body_duplicate',
                    'blog',
                    $entity,
                    sprintf('content.img.%d.src', $index),
                    $bodySource
                );
            }
        }
    }

    private function htmlAttribute(string $tag, string $attribute): string
    {
        $quotedAttribute = preg_quote($attribute, '/');
        if (preg_match('/\b' . $quotedAttribute . '\s*=\s*(["\'])(.*?)\1/is', $tag, $matches) !== 1) {
            return '';
        }

        return trim(html_entity_decode((string) ($matches[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function sameMediaPath(string $left, string $right): bool
    {
        $leftPath = parse_url(trim($left), PHP_URL_PATH);
        $rightPath = parse_url(trim($right), PHP_URL_PATH);

        return is_string($leftPath) && $leftPath !== '' && $leftPath === $rightPath;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function extractStringFields(array $payload, string $prefix = ''): array
    {
        $strings = [];

        foreach ($payload as $key => $value) {
            $segment = is_int($key) ? (string) $key : trim((string) $key);
            $path = $prefix === '' ? $segment : $prefix . '.' . $segment;

            if (is_string($value)) {
                $strings[$path] = $value;
                continue;
            }

            if (is_array($value)) {
                $strings += $this->extractStringFields($value, $path);
            }
        }

        return $strings;
    }

    private function normalizeReference(string $reference): ?string
    {
        $normalized = PublicUrlNormalizer::normalizeRoute($reference);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        $path = parse_url($normalized, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        return $path;
    }

    private function isLegacyReference(string $reference): bool
    {
        $path = parse_url(trim($reference), PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        return preg_match('#^/(?:images|structure/images)/#i', $path) === 1;
    }

    private function sourceAssetExists(string $normalizedPath): bool
    {
        $relative = substr($normalizedPath, strlen('/assets/images/'));
        if (!is_string($relative) || $relative === '') {
            return false;
        }

        return is_file($this->frontendImageRoot . '/' . ltrim($relative, '/'));
    }

    private function publishedFileExists(string $normalizedPath): bool
    {
        return is_file($this->publicRoot . $normalizedPath);
    }

    /**
     * @param array<string, array<string, string>> $issues
     */
    private function rememberIssue(
        array &$issues,
        string $type,
        string $scope,
        string $entity,
        string $field,
        string $path
    ): void {
        $issue = [
            'type' => $type,
            'scope' => $scope !== '' ? $scope : 'editorial',
            'entity' => $entity !== '' ? $entity : 'unknown',
            'field' => $field !== '' ? $field : 'payload',
            'path' => $path,
        ];

        $key = implode('|', $issue);
        $issues[$key] = $issue;
    }
}
