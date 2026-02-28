<?php

declare(strict_types=1);

namespace Caramagnols\Http;

/**
 * Représente une requête HTTP minimaliste pour découpler le legacy
 * des superglobales. Pensée pour être étendue progressivement.
 */
class Request
{
    public function __construct(
        private readonly array $server,
        private readonly array $get,
        private readonly array $post,
        private readonly array $cookies,
        private readonly array $headers
    ) {
    }

    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            self::collectHeaders()
        );
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return (string) ($this->server['REQUEST_URI'] ?? '/');
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }
        return $default;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    public function query(): array
    {
        return $this->get;
    }

    public function body(): array
    {
        return $this->post;
    }

    public function json(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function collectHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return is_array($headers) ? $headers : [];
        }

        // Fallback pour serveurs sans getallheaders
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }
}
