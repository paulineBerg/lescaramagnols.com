<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class BlogRelatedLinksNormalizer
{
    /** @var array<string, string> */
    private const CUE_PATTERNS = [
        'fr' => '/(?:ce repère|cette mise au point|dans le même (?:dossier|thème)|prolonge la page|s.inscrit dans la page)/ui',
        'en' => '/(?:this (?:note|reference|clarification|point).{0,60}(?:extends|belongs)|within the same (?:cluster|dossier|theme))/ui',
        'de' => '/(?:dieser (?:beitrag|hinweis|bezug).{0,60}(?:ergänzt|ergaenzt|gehört|gehoert)|im selben dossier)/ui',
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'fr' => 'Articles associés',
        'en' => 'Related articles',
        'de' => 'Verwandte Artikel',
    ];

    /**
     * @return array{content: string, changed: bool, link_count: int}
     */
    public function normalize(string $content, string $language): array
    {
        $pattern = self::CUE_PATTERNS[$language] ?? null;
        if ($pattern === null || trim($content) === '') {
            return ['content' => $content, 'changed' => false, 'link_count' => 0];
        }

        $changed = false;
        $linkCount = 0;
        $normalized = preg_replace_callback(
            '/<p\b[^>]*>.*?<\/p>/is',
            function (array $match) use ($pattern, $language, &$changed, &$linkCount): string {
                $paragraph = (string) $match[0];
                $plainText = html_entity_decode(strip_tags($paragraph), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match($pattern, $plainText) !== 1) {
                    return $paragraph;
                }

                preg_match_all(
                    '/<a\b[^>]*\bhref\s*=\s*(["\'])(\/[^"\']+)\1[^>]*>.*?<\/a>/is',
                    $paragraph,
                    $anchorMatches,
                    PREG_SET_ORDER
                );
                $anchorsByHref = [];
                foreach ($anchorMatches as $anchorMatch) {
                    $anchor = (string) $anchorMatch[0];
                    $href = (string) $anchorMatch[2];
                    $anchorsByHref[$href] = $anchor;
                }
                if ($anchorsByHref === []) {
                    return $paragraph;
                }

                $items = array_map(
                    static fn (string $anchor): string => '<li>' . $anchor . '</li>',
                    array_values($anchorsByHref)
                );
                $label = htmlspecialchars(
                    self::LABELS[$language],
                    ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                    'UTF-8'
                );
                $changed = true;
                $linkCount += count($items);

                return '<nav class="article-related" aria-label="' . $label . '"><ul>'
                    . implode('', $items)
                    . '</ul></nav>';
            },
            $content
        );

        return [
            'content' => is_string($normalized) ? $normalized : $content,
            'changed' => $changed,
            'link_count' => $linkCount,
        ];
    }
}
