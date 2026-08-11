<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Enrollment;

final class EnrollmentRules
{
    public const CODE_LENGTH = 12;
    public const VALIDITY_SECONDS = 600;
    public const MAX_ATTEMPTS = 5;

    public static function normalizeCode(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/i', '', $code) ?? '');

        return preg_match('/\A[A-Z2-9]{12}\z/', $normalized) === 1 ? $normalized : '';
    }
}
