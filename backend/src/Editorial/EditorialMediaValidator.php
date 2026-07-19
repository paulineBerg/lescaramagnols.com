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
