<?php

declare(strict_types=1);

namespace Caramagnols\Security;

class Cookies
{
    public static function secureOptions(): array
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        return [
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
