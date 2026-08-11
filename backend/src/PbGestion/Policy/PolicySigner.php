<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Policy;

final class PolicySigner
{
    /**
     * @param array<string, mixed> $policy
     */
    public function sign(array $policy): string
    {
        ksort($policy);
        $json = json_encode($policy, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }

        return base64_encode(hash('sha256', $json, true));
    }
}
