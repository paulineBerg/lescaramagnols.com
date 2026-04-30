<?php

declare(strict_types=1);

use Caramagnols\Http\PublicUrlNormalizer;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../core/bootstrap.php';

final class ContentReferenceIntegrityTest extends TestCase
{
    public function testVersionedEditorialContentDoesNotContainLegacyLocalMediaOrSitePaths(): void
    {
        $legacyReferences = [];

        foreach ($this->versionedEditorialFiles() as $file) {
            foreach ($this->extractStringsFromJsonFile($file) as $value) {
                if (
                    preg_match('#https?://(?:www\.)?lescaramagnols\.com/(?:images|structure/images)/#i', $value) === 1
                    || preg_match('#(?<!/assets)/(?:/images/|/structure/images/)#i', $value) === 1
                    || preg_match('#(?:https?://(?:www\.)?lescaramagnols\.com)?/site/#', $value) === 1
                ) {
                    $legacyReferences[] = $file . ' :: ' . $value;
                }
            }
        }

        $this->assertSame([], $legacyReferences, implode("\n", array_slice($legacyReferences, 0, 20)));
    }

    public function testVersionedEditorialContentReferencesExistingCanonicalPublicImages(): void
    {
        $missingImages = [];

        foreach ($this->versionedEditorialFiles() as $file) {
            foreach ($this->extractStringsFromJsonFile($file) as $value) {
                $matchCount = preg_match_all(
                    '#(?:https?://(?:www\.)?lescaramagnols\.com)?(/assets/images/[^"\'\s<>()]+?\.(?:png|jpe?g|gif|webp|avif|svg))(?:\?[^"\'\s<>()]*)?#i',
                    $value,
                    $matches
                );

                if (!is_int($matchCount) || $matchCount < 1) {
                    continue;
                }

                foreach ($matches[1] as $path) {
                    $normalized = PublicUrlNormalizer::normalizeRoute((string) $path);
                    if (!is_string($normalized) || $normalized === '') {
                        continue;
                    }

                    $absolutePath = ROOT_PATH . '/public' . $normalized;
                    if (!is_file($absolutePath)) {
                        $missingImages[] = $file . ' :: ' . $normalized;
                    }
                }
            }
        }

        $missingImages = array_values(array_unique($missingImages));

        $this->assertSame([], $missingImages, implode("\n", array_slice($missingImages, 0, 20)));
    }

    /**
     * @return array<int, string>
     */
    private function versionedEditorialFiles(): array
    {
        $files = [
            ROOT_PATH . '/data/pages.json',
            ROOT_PATH . '/data/menus.json',
        ];

        foreach (glob(ROOT_PATH . '/data/blog/*.json') ?: [] as $file) {
            $files[] = $file;
        }

        sort($files);

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function extractStringsFromJsonFile(string $file): array
    {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (!is_array($decoded)) {
            return [];
        }

        $strings = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($decoded));

        foreach ($iterator as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }
}
