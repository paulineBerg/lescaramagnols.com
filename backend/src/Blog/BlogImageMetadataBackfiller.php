<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class BlogImageMetadataBackfiller
{
    /**
     * @return array{content: string, changed: bool, image_count: int}
     */
    public function addMissingTitles(string $content): array
    {
        $imageCount = 0;
        $updated = preg_replace_callback(
            '/<img\b[^>]*>/i',
            static function (array $match) use (&$imageCount): string {
                $tag = (string) $match[0];
                if (preg_match('/\btitle\s*=/i', $tag) === 1) {
                    return $tag;
                }
                if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i', $tag, $altMatch) !== 1) {
                    return $tag;
                }
                $alt = trim((string) $altMatch[2]);
                if ($alt === '') {
                    return $tag;
                }
                $replacement = (string) $altMatch[0] . ' title=' . $altMatch[1] . $alt . $altMatch[1];
                $tag = preg_replace('/\balt\s*=\s*(["\'])(.*?)\1/i', $replacement, $tag, 1) ?? $tag;
                $imageCount++;

                return $tag;
            },
            $content
        );

        return [
            'content' => is_string($updated) ? $updated : $content,
            'changed' => $imageCount > 0,
            'image_count' => $imageCount,
        ];
    }
}
