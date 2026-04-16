<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Cette commande doit etre executee en CLI.\n");
    exit(1);
}

$options = parse_cli_options(array_slice($argv, 1));
$url = trim((string) ($options['url'] ?? env('PREPROD_CHECK_URL', '')));
$timeout = max(2, min(30, (int) ($options['timeout'] ?? 10)));
$forwardedProto = strtolower(trim((string) ($options['forwarded-proto'] ?? '')));
$userAgent = trim((string) ($options['user-agent'] ?? ''));
$jsonOutput = isset($options['json']);

if ($userAgent === '') {
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
}

if (!in_array($forwardedProto, ['', 'http', 'https'], true)) {
    fwrite(STDERR, "Option --forwarded-proto invalide (attendu: http|https).\n");
    exit(1);
}

if ($url === '') {
    fwrite(STDERR, "URL cible manquante. Utilisez --url=https://preprod.exemple.tld\n");
    exit(1);
}

if (preg_match('#^https?://#i', $url) !== 1) {
    $url = 'https://' . ltrim($url, '/');
}

$result = fetch_http_headers($url, $timeout, $forwardedProto !== '' ? $forwardedProto : null, $userAgent);
if ($result === null) {
    fwrite(STDERR, "Impossible de recuperer les headers HTTP de la cible.\n");
    exit(1);
}

$requiredHeaders = [
    'content-security-policy',
    'x-frame-options',
    'x-content-type-options',
    'referrer-policy',
    'permissions-policy',
    'cross-origin-opener-policy',
    'cross-origin-resource-policy',
];

$missingHeaders = [];
foreach ($requiredHeaders as $headerName) {
    if (!isset($result['headers'][$headerName]) || trim((string) $result['headers'][$headerName]) === '') {
        $missingHeaders[] = $headerName;
    }
}

$finalUrl = strtolower((string) ($result['final_url'] ?? $url));
$expectsHsts = str_starts_with($finalUrl, 'https://');
if ($expectsHsts && (!isset($result['headers']['strict-transport-security']) || trim((string) $result['headers']['strict-transport-security']) === '')) {
    $missingHeaders[] = 'strict-transport-security';
}

$payload = [
    'url' => $url,
    'final_url' => $result['final_url'],
    'status' => $result['status'],
    'missing_headers' => array_values(array_unique($missingHeaders)),
    'present_headers' => $result['headers'],
    'redirect_chain' => $result['status_chain'],
];

if ($jsonOutput) {
    fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} else {
    fwrite(STDOUT, sprintf("Verification headers securite: %s\n", $url));
    fwrite(STDOUT, sprintf("- URL finale: %s\n", (string) $result['final_url']));
    fwrite(STDOUT, sprintf("- Status final: %d\n", (int) $result['status']));

    if ($missingHeaders === []) {
        fwrite(STDOUT, "- Headers requis: OK\n");
    } else {
        fwrite(STDOUT, "- Headers manquants:\n");
        foreach (array_unique($missingHeaders) as $headerName) {
            fwrite(STDOUT, sprintf("  - %s\n", $headerName));
        }
    }
}

if ($missingHeaders !== []) {
    exit(1);
}

$status = (int) ($result['status'] ?? 0);
if ($status < 200 || $status >= 400) {
    fwrite(STDERR, "La cible ne repond pas en code 2xx/3xx.\n");
    exit(1);
}

exit(0);

/**
 * @return array{status:int, final_url:string, headers:array<string, string>, status_chain:array<int, int>}|null
 */
function fetch_http_headers(string $url, int $timeout, ?string $forwardedProto = null, string $userAgent = ''): ?array
{
    $httpHeaders = [];
    if (is_string($forwardedProto) && in_array($forwardedProto, ['http', 'https'], true)) {
        $httpHeaders[] = 'X-Forwarded-Proto: ' . $forwardedProto;
    }
    if ($userAgent !== '') {
        $httpHeaders[] = 'User-Agent: ' . $userAgent;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header' => $httpHeaders !== [] ? implode("\r\n", $httpHeaders) . "\r\n" : '',
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $handle = @fopen($url, 'r', false, $context);
    if (!is_resource($handle)) {
        return null;
    }

    try {
        stream_get_contents($handle);
        $meta = stream_get_meta_data($handle);
    } finally {
        fclose($handle);
    }

    $responseHeaders = is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : $http_response_header;
    if (!is_array($responseHeaders) || $responseHeaders === []) {
        return null;
    }

    $blocks = parse_header_blocks($responseHeaders);
    if ($blocks === []) {
        return null;
    }

    $lastBlock = $blocks[count($blocks) - 1];
    $statusChain = [];

    foreach ($blocks as $block) {
        $statusChain[] = $block['status'];
    }

    $finalUrl = is_string($meta['uri'] ?? null) && trim((string) $meta['uri']) !== ''
        ? trim((string) $meta['uri'])
        : $url;

    return [
        'status' => $lastBlock['status'],
        'final_url' => $finalUrl,
        'headers' => $lastBlock['headers'],
        'status_chain' => $statusChain,
    ];
}

/**
 * @param array<int, string> $headers
 * @return array<int, array{status:int, headers:array<string, string>}>
 */
function parse_header_blocks(array $headers): array
{
    $blocks = [];
    $currentStatus = 0;
    $currentHeaders = [];

    foreach ($headers as $line) {
        if (!is_string($line) || trim($line) === '') {
            continue;
        }

        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $matches) === 1) {
            if ($currentStatus !== 0) {
                $blocks[] = [
                    'status' => $currentStatus,
                    'headers' => $currentHeaders,
                ];
            }

            $currentStatus = (int) $matches[1];
            $currentHeaders = [];
            continue;
        }

        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }

        $name = strtolower(trim(substr($line, 0, $pos)));
        $value = trim(substr($line, $pos + 1));
        if ($name === '' || $value === '') {
            continue;
        }

        if (isset($currentHeaders[$name])) {
            $currentHeaders[$name] .= ', ' . $value;
        } else {
            $currentHeaders[$name] = $value;
        }
    }

    if ($currentStatus !== 0) {
        $blocks[] = [
            'status' => $currentStatus,
            'headers' => $currentHeaders,
        ];
    }

    return $blocks;
}

/**
 * @param array<int, string> $arguments
 * @return array<string, string|true>
 */
function parse_cli_options(array $arguments): array
{
    $options = [];

    foreach ($arguments as $argument) {
        if (!is_string($argument) || !str_starts_with($argument, '--')) {
            continue;
        }

        $parts = explode('=', substr($argument, 2), 2);
        if (!isset($parts[1])) {
            $options[$parts[0]] = true;
            continue;
        }

        $options[$parts[0]] = $parts[1];
    }

    return $options;
}
