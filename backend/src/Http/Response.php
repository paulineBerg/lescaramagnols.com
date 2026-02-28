<?php

declare(strict_types=1);

namespace Caramagnols\Http;

class Response
{
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public string $body = ''
    ) {
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        return new self($status, $headers, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }
        echo $this->body;
    }
}
