<?php

declare(strict_types=1);

namespace Caramagnols\Http;

/**
 * Représente une requête HTTP minimaliste pour découpler le legacy
 * des superglobales. Pensée pour être étendue progressivement.
 */
class Request
{
    private readonly string $content;
    private readonly array $files;

    public function __construct(
        private readonly array $server,
        private readonly array $get,
        private readonly array $post,
        private readonly array $cookies,
        private readonly array $headers,
        array|string $content = '',
        array $files = []
    ) {
        $this->content = is_string($content) ? $content : '';
        $this->files = $files;
    }

    public static function fromGlobals(): self
    {
        return new self(
            $_SERVER,
            $_GET,
            $_POST,
            $_COOKIE,
            self::collectHeaders(),
            (string) (file_get_contents('php://input') ?: ''),
            $_FILES
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

    public function server(string $name, mixed $default = null): mixed
    {
        return $this->server[$name] ?? $default;
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

    public function files(): array
    {
        return $this->files;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function json(): array
    {
        if ($this->content === '') {
            return [];
        }

        $decoded = json_decode($this->content, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function clientIp(bool $trustForwardedHeaders = false): ?string
    {
        if ($trustForwardedHeaders) {
            $forwardedFor = trim((string) ($this->header('X-Forwarded-For', '') ?? ''));
            if ($forwardedFor !== '') {
                foreach (explode(',', $forwardedFor) as $candidate) {
                    $ip = trim($candidate);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }

            $realIp = trim((string) ($this->header('X-Real-IP', '') ?? ''));
            if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
                return $realIp;
            }
        }

        $remoteAddr = trim((string) ($this->server['REMOTE_ADDR'] ?? ''));

        return $remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false ? $remoteAddr : null;
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
