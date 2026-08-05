<?php

declare(strict_types=1);

namespace Caramagnols\PrivateApps\WebDevelopment\Service;

use Caramagnols\Http\Response;

final class PreviewFileService
{
    public function __construct(private readonly string $deploymentsRoot)
    {
    }

    /**
     * @param array{id: int, projectKey: string, publicPath: string} $project
     */
    public function serve(array $project, string $requestedPath, string $method): Response
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return $this->notFound();
        }

        $publicRoot = $this->resolvePublicRoot((string) $project['publicPath']);
        if ($publicRoot === null) {
            return $this->notFound();
        }

        $relativePath = $this->normalizeAssetPath($requestedPath);
        if ($relativePath === null) {
            return $this->notFound();
        }

        $targetPath = $this->resolveFilePath($publicRoot, $relativePath);
        if ($targetPath === null && ($relativePath === '' || !str_contains(basename($relativePath), '.'))) {
            $targetPath = $this->resolveFilePath($publicRoot, 'index.html');
        }

        if ($targetPath === null) {
            return $this->notFound();
        }

        $body = $method === 'HEAD' ? '' : (string) file_get_contents($targetPath);
        $headers = [
            'Content-Type' => $this->mimeType($targetPath),
        ];

        return $this->withPreviewHeaders(new Response(200, $headers, $body));
    }

    public function notFound(): Response
    {
        return $this->withPreviewHeaders(
            new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not found')
        );
    }

    public function withPreviewHeaders(Response $response): Response
    {
        $response->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive, noimageindex';
        $response->headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate';
        $response->headers['Pragma'] = 'no-cache';
        $response->headers['Expires'] = '0';
        $response->headers['X-Content-Type-Options'] = 'nosniff';
        $response->headers['Referrer-Policy'] = 'no-referrer';
        $response->headers['Permissions-Policy'] = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()';
        $response->headers['Content-Security-Policy'] = "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; media-src 'self' blob:; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none';";

        return $response;
    }

    private function resolvePublicRoot(string $publicPath): ?string
    {
        $publicPath = trim(str_replace('\\', '/', $publicPath));
        if ($publicPath === '') {
            return null;
        }

        $root = rtrim(str_replace('\\', '/', trim($this->deploymentsRoot)), '/');
        $candidate = str_starts_with($publicPath, '/') ? $publicPath : $root . '/' . ltrim($publicPath, '/');
        $realCandidate = realpath($candidate);
        if (!is_string($realCandidate) || !is_dir($realCandidate)) {
            return null;
        }

        if ($root !== '') {
            $realRoot = realpath($root);
            if (!is_string($realRoot) || !$this->pathStartsWith($realCandidate, $realRoot)) {
                return null;
            }
        }

        return rtrim(str_replace('\\', '/', $realCandidate), '/');
    }

    private function resolveFilePath(string $publicRoot, string $relativePath): ?string
    {
        $candidate = $relativePath === '' ? $publicRoot . '/index.html' : $publicRoot . '/' . $relativePath;
        $realCandidate = realpath($candidate);
        if (!is_string($realCandidate) || !is_file($realCandidate) || !$this->pathStartsWith($realCandidate, $publicRoot)) {
            return null;
        }

        return $realCandidate;
    }

    private function normalizeAssetPath(string $path): ?string
    {
        $path = rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path);
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        if (str_contains($path, "\0")) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function pathStartsWith(string $candidate, string $root): bool
    {
        $candidate = rtrim(str_replace('\\', '/', $candidate), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return $candidate === $root || str_starts_with($candidate, $root . '/');
    }

    private function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css; charset=UTF-8',
            'html', 'htm' => 'text/html; charset=UTF-8',
            'js', 'mjs' => 'text/javascript; charset=UTF-8',
            'json', 'map' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'txt' => 'text/plain; charset=UTF-8',
            'xml' => 'application/xml; charset=UTF-8',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
