<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Agent;

final class AgentStatus
{
    public const ACTIVE = 'active';
    public const REVOKED = 'revoked';
    public const PENDING = 'pending';
    public const RECOVERY = 'recovery';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::ACTIVE, self::REVOKED, self::PENDING, self::RECOVERY];
    }
}
