<?php

declare(strict_types=1);

namespace Caramagnols\Identity\Reauthentication;

final class ReauthenticationService
{
    public function adminIsFresh(): bool
    {
        return function_exists('admin_reauth_is_fresh') && admin_reauth_is_fresh();
    }
}
