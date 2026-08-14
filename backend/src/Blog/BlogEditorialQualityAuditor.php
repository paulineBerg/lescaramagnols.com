<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class BlogEditorialQualityAuditor
{
    private const LANGUAGES = ['fr', 'en', 'de'];
    private const MINIMUM_FRENCH_WORDS = 600;
    private const MINIMUM_TRANSLATION_RATIO = 0.8;
    private const REPEATED_HEADING_SLUG_THRESHOLD = 3;
    private const OVERUSED_LEXICON_MINIMUM_SLUGS = 10;
    private const OVERUSED_LEXICON_RATIO = 0.1;

    /** @var array<string, array<int, string>> */
    private const FORBIDDEN_PHRASES = [
        'fr' => [
            'ce brouillon',
            'cet article',
            "l'article doit",
            'le but est',
            'utile pour le lecteur',
            'pour le lecteur',
            'le premier réflexe utile consiste à',
            'segmenter le sujet',
            'pour la lire correctement',
            'la lecture visuelle',
            'la lecture de profil',
            'il faut donc lire ensemble',
            'la bonne identification ne repose pas sur un seul signe',
            'replacer la voiture dans la dernière grande phase',
            'ce repère prolonge la page',
            'dans le même dossier, la suite',
        ],
        'en' => [
            'this draft',
            'this article',
            'useful for the reader',
            'this reference extends the page',
            'in the same dossier',
        ],
        'de' => [
            'dieser entwurf',
            'dieser artikel',
            'nützlich für den leser',
            'dieser beitrag ergänzt die seite',
            'im selben dossier',
        ],
    ];

    /** @var array<string, array<string, string>> */
    private const CORPUS_LEXICON_PATTERNS = [
        'fr' => [
            'vocabulaire_lire_lecture' => '/\b(?:lire|lit|lisez|lu|lecture|lectures|lisible|lisibles|lisibilité)\b/ui',
            'substitution_examiner' => '/\b(?:examiner|examine|examinent|examiné|examinée|examinés|examinées)\b/ui',
            'coherence' => '/\bcohérence\b/ui',
            'repere' => '/\brepères?\b/ui',
            'tournure_il_faut' => '/\bil faut\b/ui',
            'substitution_mieux_vaut' => '/\bmieux vaut\b/ui',
        ],
        'en' => [
            'read_reading_vocabulary' => '/\b(?:read|reads|reading|readable|legibility)\b/ui',
            'examine_assessment_vocabulary' => '/\b(?:examine|examines|examined|examining|assessment|assessments)\b/ui',
            'coherence' => '/\bcoherence\b/ui',
            'reference_point' => '/\breference points?\b/ui',
            'one_must_turn' => '/\bone must\b/ui',
        ],
        'de' => [
            'lesen_lektuere_wortschatz' => '/\b(?:lesen|liest|lesbar|lesbarkeit|lektüre|lesart)\b/ui',
            'pruefen_pruefung_wortschatz' => '/\b(?:prüfen|prüft|geprüft|prüfung|prüfungen)\b/ui',
            'stimmigkeit' => '/\bstimmigkeit\b/ui',
            'orientierungspunkt' => '/\borientierungspunkte?\b/ui',
            'man_muss_formulierung' => '/\bman muss\b/ui',
        ],
    ];

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array{
     *   article_count: int,
     *   slug_count: int,
     *   error_count: int,
     *   warning_count: int,
     *   issues: array<int, array{severity: string, type: string, entity: string, field: string, detail: string}>
     * }
     */
    public function audit(array $articles): array
    {
        $issues = [];
        $variants = [];
        $wordCounts = [];
        $sentences = [];
        $headings = [];
        $lexicalFootprints = [];

        foreach ($articles as $article) {
            if (!is_array($article)) {
                continue;
            }

            $slug = $this->slug((string) ($article['slug'] ?? ''));
            $language = strtolower(trim((string) ($article['lang'] ?? '')));
            $entity = ($slug !== '' ? $slug : 'unknown') . '.' . ($language !== '' ? $language : 'unknown');
            if ($slug === '' || !in_array($language, self::LANGUAGES, true)) {
                $this->issue($issues, 'error', 'invalid_identity', $entity, 'slug|lang', 'Slug ou langue invalide.');
                continue;
            }

            if (isset($variants[$slug][$language])) {
                $this->issue($issues, 'error', 'duplicate_variant', $entity, 'slug|lang', 'Variante dupliquée.');
                continue;
            }
            $variants[$slug][$language] = true;

            foreach (['title', 'excerpt', 'content'] as $field) {
                if (trim((string) ($article[$field] ?? '')) === '') {
                    $this->issue($issues, 'error', 'required_field_missing', $entity, $field, 'Champ obligatoire vide.');
                }
            }

            $content = is_string($article['content'] ?? null) ? (string) $article['content'] : '';
            $title = is_string($article['title'] ?? null) ? (string) $article['title'] : '';
            $excerpt = is_string($article['excerpt'] ?? null) ? (string) $article['excerpt'] : '';
            $plainText = $this->plainText($title . ' ' . $excerpt . ' ' . $content);
            $contentWordCount = $this->wordCount($this->plainText($content));
            $wordCounts[$slug][$language] = $contentWordCount;

            if ($language === 'fr' && $contentWordCount < self::MINIMUM_FRENCH_WORDS) {
                $this->issue(
                    $issues,
                    'error',
                    'content_too_short',
                    $entity,
                    'content',
                    sprintf('%d mots ; minimum de contrôle : %d.', $contentWordCount, self::MINIMUM_FRENCH_WORDS)
                );
            }

            foreach (self::FORBIDDEN_PHRASES[$language] as $phrase) {
                if (str_contains($this->lower($plainText), $this->lower($phrase))) {
                    $this->issue($issues, 'error', 'forbidden_phrase', $entity, 'content', $phrase);
                }
            }

            $this->auditSources($issues, $entity, $language, $content);
            $this->auditLinks($issues, $entity, $content);

            foreach ($this->significantSentences($content) as $sentence) {
                $sentences[$language][$sentence][$slug] = true;
            }
            foreach ($this->significantHeadings($content) as $heading) {
                $headings[$language][$heading][$slug] = true;
            }
            foreach (self::CORPUS_LEXICON_PATTERNS[$language] as $label => $pattern) {
                if (preg_match($pattern, $plainText) === 1) {
                    $lexicalFootprints[$language][$label][$slug] = true;
                }
            }
        }

        foreach ($variants as $slug => $languages) {
            foreach (self::LANGUAGES as $language) {
                if (!isset($languages[$language])) {
                    $this->issue(
                        $issues,
                        'error',
                        'translation_missing',
                        $slug,
                        'lang',
                        sprintf('Variante %s absente.', $language)
                    );
                }
            }

            $frenchWords = (int) ($wordCounts[$slug]['fr'] ?? 0);
            if ($frenchWords <= 0) {
                continue;
            }
            foreach (['en', 'de'] as $language) {
                if (!isset($wordCounts[$slug][$language])) {
                    continue;
                }
                $ratio = $wordCounts[$slug][$language] / $frenchWords;
                if ($ratio < self::MINIMUM_TRANSLATION_RATIO) {
                    $this->issue(
                        $issues,
                        'error',
                        'translation_too_short',
                        $slug . '.' . $language,
                        'content',
                        sprintf('Ratio par rapport au français : %.2f ; minimum : %.2f.', $ratio, self::MINIMUM_TRANSLATION_RATIO)
                    );
                }
            }
        }

        foreach ($sentences as $language => $languageSentences) {
            foreach ($languageSentences as $sentence => $slugs) {
                if (count($slugs) < 3) {
                    continue;
                }
                $this->issue(
                    $issues,
                    'error',
                    'repeated_sentence',
                    implode(',', array_keys($slugs)),
                    'content.' . $language,
                    $sentence
                );
            }
        }

        foreach ($headings as $language => $languageHeadings) {
            foreach ($languageHeadings as $heading => $slugs) {
                if (count($slugs) < self::REPEATED_HEADING_SLUG_THRESHOLD) {
                    continue;
                }
                $this->issue(
                    $issues,
                    'error',
                    'repeated_heading',
                    implode(',', array_keys($slugs)),
                    'content.' . $language,
                    $heading
                );
            }
        }

        foreach ($lexicalFootprints as $language => $languageFootprints) {
            $languageSlugCount = count(array_filter(
                $variants,
                static fn (array $languages): bool => isset($languages[$language])
            ));
            foreach ($languageFootprints as $label => $slugs) {
                $slugCount = count($slugs);
                if ($slugCount < self::OVERUSED_LEXICON_MINIMUM_SLUGS
                    || $languageSlugCount === 0
                    || ($slugCount / $languageSlugCount) < self::OVERUSED_LEXICON_RATIO
                ) {
                    continue;
                }
                $this->issue(
                    $issues,
                    'error',
                    'overused_corpus_lexicon',
                    implode(',', array_keys($slugs)),
                    'content.' . $language,
                    sprintf('%s présent dans %d article(s) sur %d.', $label, $slugCount, $languageSlugCount)
                );
            }
        }

        $errorCount = count(array_filter(
            $issues,
            static fn (array $issue): bool => $issue['severity'] === 'error'
        ));

        return [
            'article_count' => count($articles),
            'slug_count' => count($variants),
            'error_count' => $errorCount,
            'warning_count' => count($issues) - $errorCount,
            'issues' => array_values($issues),
        ];
    }

    /**
     * @param array<string, array{severity: string, type: string, entity: string, field: string, detail: string}> $issues
     */
    private function auditSources(array &$issues, string $entity, string $language, string $content): void
    {
        $sourceHeading = match ($language) {
            'fr' => 'sources',
            'de' => 'quellen',
            default => 'sources',
        };
        $normalizedContent = $this->lower($this->plainText($content));
        if (!str_contains($normalizedContent, $sourceHeading)) {
            $this->issue($issues, 'error', 'sources_missing', $entity, 'content', 'Section de sources absente.');

            return;
        }

        $sourceListPosition = strripos($content, '<h2');
        $sourceFragment = $sourceListPosition !== false ? substr($content, $sourceListPosition) : $content;
        $sourceCount = preg_match_all('/<li\b/i', $sourceFragment);
        if (!is_int($sourceCount) || $sourceCount < 2) {
            $this->issue(
                $issues,
                'error',
                'sources_insufficient',
                $entity,
                'content',
                sprintf('%d source(s) identifiable(s) ; minimum de contrôle : 2.', is_int($sourceCount) ? $sourceCount : 0)
            );
        }
    }

    /**
     * @param array<string, array{severity: string, type: string, entity: string, field: string, detail: string}> $issues
     */
    private function auditLinks(array &$issues, string $entity, string $content): void
    {
        $linkCount = preg_match_all('/<a\b[^>]*\bhref\s*=/i', $content);
        if (!is_int($linkCount) || $linkCount < 2) {
            $this->issue(
                $issues,
                'warning',
                'internal_link_review_required',
                $entity,
                'content',
                'Moins de deux liens détectés ; vérifier le maillage et les sources.'
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function significantSentences(string $html): array
    {
        $contentWithoutSources = preg_replace('/<h2\b[^>]*>\s*(?:Sources|Quellen)\s*<\/h2>.*\z/is', '', $html) ?? $html;
        $proseOnly = preg_replace('/<figure\b[^>]*>.*?<\/figure>/is', '', $contentWithoutSources)
            ?? $contentWithoutSources;
        $plainText = $this->plainText($proseOnly);
        $parts = preg_split('/(?<=[.!?])\s+/u', $plainText) ?: [];
        $sentences = [];
        foreach ($parts as $part) {
            $normalized = $this->lower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $part)));
            if ($this->wordCount($normalized) < 10) {
                continue;
            }
            $sentences[$normalized] = $normalized;
        }

        return array_values($sentences);
    }

    /**
     * @return array<int, string>
     */
    private function significantHeadings(string $html): array
    {
        preg_match_all('/<h[2-4]\b[^>]*>(.*?)<\/h[2-4]>/is', $html, $matches);
        $headings = [];
        foreach ($matches[1] as $heading) {
            $normalized = $this->lower(trim((string) preg_replace(
                '/[^\p{L}\p{N}]+/u',
                ' ',
                $this->plainText((string) $heading)
            )));
            if ($normalized === '' || in_array($normalized, ['sources', 'quellen'], true)) {
                continue;
            }
            $headings[$normalized] = $normalized;
        }

        return array_values($headings);
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private function wordCount(string $text): int
    {
        $count = preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}’\'-]*/u', $text);

        return is_int($count) ? $count : 0;
    }

    private function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    private function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    /**
     * @param array<string, array{severity: string, type: string, entity: string, field: string, detail: string}> $issues
     */
    private function issue(
        array &$issues,
        string $severity,
        string $type,
        string $entity,
        string $field,
        string $detail
    ): void {
        $issue = compact('severity', 'type', 'entity', 'field', 'detail');
        $issues[implode('|', $issue)] = $issue;
    }
}
