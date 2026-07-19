<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

final class RecaptchaVerifier
{
    public function __construct(
        private readonly string $endpoint = 'https://www.google.com/recaptcha/api/siteverify'
    ) {
    }

    /**
     * @return array{
     *   success: bool,
     *   score: float|null,
     *   action: string|null,
     *   errorCodes: array<int, string>
     * }
     */
    public function verify(string $secretKey, string $token, ?string $remoteIp = null, int $timeoutSeconds = 8): array
    {
        $payload = [
            'secret' => trim($secretKey),
            'response' => trim($token),
        ];

        if (is_string($remoteIp) && trim($remoteIp) !== '') {
            $payload['remoteip'] = trim($remoteIp);
        }

        if ($payload['secret'] === '' || $payload['response'] === '') {
            return [
                'success' => false,
                'score' => null,
                'action' => null,
                'errorCodes' => ['missing-input'],
            ];
        }

        $query = http_build_query($payload);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                    . 'Content-Length: ' . strlen($query) . "\r\n",
                'content' => $query,
                'timeout' => max(3, min(20, $timeoutSeconds)),
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($this->endpoint, false, $context);
        if (!is_string($raw) || trim($raw) === '') {
            return [
                'success' => false,
                'score' => null,
                'action' => null,
                'errorCodes' => ['network-error'],
            ];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [
                'success' => false,
                'score' => null,
                'action' => null,
                'errorCodes' => ['invalid-json'],
            ];
        }

        $score = isset($decoded['score']) && is_numeric($decoded['score']) ? (float) $decoded['score'] : null;
        $action = is_string($decoded['action'] ?? null) ? trim((string) $decoded['action']) : null;
        $errorCodes = [];

        if (is_array($decoded['error-codes'] ?? null)) {
            foreach ($decoded['error-codes'] as $errorCode) {
                if (!is_scalar($errorCode) && $errorCode !== null) {
                    continue;
                }

                $errorCodes[] = trim((string) $errorCode);
            }
        }

        return [
            'success' => (bool) ($decoded['success'] ?? false),
            'score' => $score,
            'action' => $action !== '' ? $action : null,
            'errorCodes' => array_values(array_filter($errorCodes, static fn (string $value): bool => $value !== '')),
        ];
    }
}
