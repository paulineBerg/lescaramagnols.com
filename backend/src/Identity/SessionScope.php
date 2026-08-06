<?php

declare(strict_types=1);

namespace Caramagnols\Identity;

final class SessionScope
{
    public const IDENTITY = 'identity';
    public const PRIVATE = 'private';
    public const ADMIN = 'admin';

    public static function normalize(string $scope): string
    {
        $normalized = strtolower(trim($scope));

        return in_array($normalized, [self::IDENTITY, self::PRIVATE, self::ADMIN], true)
            ? $normalized
            : '';
    }
}
