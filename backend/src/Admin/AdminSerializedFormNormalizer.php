<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

final class AdminSerializedFormNormalizer
{
    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function menuBuilder(array $body): array
    {
        return $this->normalize($body, 'builder_state_json', ['builder_action', 'csrf_token']);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function pageEditor(array $body): array
    {
        return $this->normalize($body, 'page_state_json', ['page_action', 'csrf_token']);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function tileEditor(array $body): array
    {
        return $this->normalize($body, 'tile_state_json', ['tile_action', 'csrf_token']);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<int, string> $preservedKeys
     * @return array<string, mixed>
     */
    public function normalize(array $body, string $stateField, array $preservedKeys): array
    {
        $stateJson = $body[$stateField] ?? null;
        if (!is_string($stateJson) || trim($stateJson) === '') {
            return $body;
        }

        $decoded = json_decode($stateJson, true);
        if (!is_array($decoded)) {
            return $body;
        }

        foreach ($preservedKeys as $key) {
            if (array_key_exists($key, $body)) {
                $decoded[$key] = $body[$key];
            }
        }

        return $this->deepFillMissing($decoded, $body);
    }

    /**
     * @param array<string, mixed> $decoded
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    private function deepFillMissing(array $decoded, array $fallback): array
    {
        foreach ($fallback as $key => $fallbackValue) {
            if (!array_key_exists($key, $decoded)) {
                $decoded[$key] = $fallbackValue;
                continue;
            }

            if (is_array($decoded[$key]) && is_array($fallbackValue)) {
                $decoded[$key] = $this->deepFillMissing($decoded[$key], $fallbackValue);
            }
        }

        return $decoded;
    }
}
