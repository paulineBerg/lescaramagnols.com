<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class DiscussionRecaptchaMode
{
    public const V2_CHECKBOX = 'v2_checkbox';
    public const V3_SCORE = 'v3_score';
    public const V3_ACTION = 'blog_discussion';

    /**
     * @return array<int, string>
     */
    public static function allowedValues(): array
    {
        return [
            self::V2_CHECKBOX,
            self::V3_SCORE,
        ];
    }

    public static function normalize(mixed $value): string
    {
        $normalized = trim(strtolower((string) $value));

        return in_array($normalized, self::allowedValues(), true)
            ? $normalized
            : self::V2_CHECKBOX;
    }

    public static function isVisibleWidget(string $mode): bool
    {
        return self::normalize($mode) === self::V2_CHECKBOX;
    }

    public static function usesScoreVerification(string $mode): bool
    {
        return self::normalize($mode) === self::V3_SCORE;
    }
}
