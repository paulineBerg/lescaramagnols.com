<?php

declare(strict_types=1);

namespace Caramagnols\Admin\Navigation;

final class NavigationBuilderActionParser
{
    /**
     * @return array{name: string, target: string, extra: string}
     */
    public function parse(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return ['name' => 'save', 'target' => '', 'extra' => ''];
        }

        $segments = explode('@', $payload, 3);

        return [
            'name' => $segments[0] ?? 'save',
            'target' => $segments[1] ?? '',
            'extra' => $segments[2] ?? '',
        ];
    }
}
