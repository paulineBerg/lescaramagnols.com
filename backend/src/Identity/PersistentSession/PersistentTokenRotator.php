<?php

declare(strict_types=1);

namespace Caramagnols\Identity\PersistentSession;

use Caramagnols\Identity\Repository\PersistentTokenRepository;

final class PersistentTokenRotator
{
    public function __construct(private readonly PersistentTokenRepository $tokens)
    {
    }

    /**
     * @return array{id: int, selector: string, secret: string, family: string, expires_at: string}
     */
    public function rotate(array $token, int $ttlSeconds): array
    {
        if (!(bool) app_config('identity.persistent.rotation_enabled', true)) {
            return [
                'id' => (int) ($token['id'] ?? 0),
                'selector' => (string) ($token['selector'] ?? ''),
                'secret' => '',
                'family' => (string) ($token['token_family_id'] ?? ''),
                'expires_at' => (string) ($token['expires_at'] ?? ''),
            ];
        }

        return $this->tokens->rotate($token, $ttlSeconds);
    }
}
