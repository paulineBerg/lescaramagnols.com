<?php

declare(strict_types=1);

namespace Caramagnols\PbGestion\Protocol;

final class CanonicalRequest
{
    public static function bodySha256(string $body): string
    {
        return hash('sha256', $body);
    }

    public static function pathWithCanonicalQuery(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $query = parse_url($uri, PHP_URL_QUERY);
        if (!is_string($query) || trim($query) === '') {
            return $path;
        }

        parse_str($query, $params);
        if (!is_array($params) || $params === []) {
            return $path;
        }

        ksort($params);
        $canonicalQuery = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        return $canonicalQuery !== '' ? $path . '?' . $canonicalQuery : $path;
    }

    public static function build(
        string $method,
        string $uri,
        string $body,
        string $timestamp,
        int $sequence,
        string $requestUuid
    ): string {
        return implode("\n", [
            strtoupper(trim($method)),
            self::pathWithCanonicalQuery($uri),
            self::bodySha256($body),
            trim($timestamp),
            (string) $sequence,
            strtolower(trim($requestUuid)),
        ]);
    }
}
