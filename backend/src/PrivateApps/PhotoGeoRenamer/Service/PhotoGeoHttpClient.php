<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\PhotoGeoRenamer\Service;

final class PhotoGeoHttpClient implements PhotoGeoJsonClient
{
    /**
     * @param array<int, string> $allowedHosts
     */
    public function __construct(
        private readonly array $allowedHosts = [
            'geo.api.gouv.fr',
            'data.geopf.fr',
            'api-adresse.data.gouv.fr',
            'nominatim.openstreetmap.org',
        ],
        private readonly string $userAgent = 'LesCaramagnols-PhotoGeoRenamer/1.0 (https://www.lescaramagnols.com)'
    ) {
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    public function getJson(string $url): ?array
    {
        $parts = parse_url($url);
        $host = is_string($parts['host'] ?? null) ? strtolower((string) $parts['host']) : '';
        if (($parts['scheme'] ?? null) !== 'https' || !in_array($host, $this->allowedHosts, true)) {
            return null;
        }

        [$status, $body] = $this->fetch($url);
        if ($status >= 200 && $status < 300 && is_string($body)) {
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : null;
        }

        if (!in_array($status, [0, 429, 500, 502, 503, 504], true)) {
            return null;
        }

        usleep(150000);
        [$status, $body] = $this->fetch($url);
        if ($status >= 200 && $status < 300 && is_string($body)) {
            $decoded = json_decode($body, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * @return array{0: int, 1: string|null}
     */
    private function fetch(string $url): array
    {
        $headers = [
            'User-Agent: ' . $this->userAgent,
            'Accept: application/json',
        ];

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                return [0, null];
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $response = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            return [$status, is_string($response) ? $response : null];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => implode("\r\n", $headers) . "\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header as $header) {
            if (preg_match('/\AHTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }

        return [$status, is_string($response) ? $response : null];
    }
}
