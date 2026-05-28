<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;

final class PrivateHttpBoundary
{
    public function __construct(
        private readonly PrivateRouteResolver $routeResolver,
        private readonly ?PrivatePortalController $controller,
        private readonly bool $enabled
    ) {
    }

    /**
     * @return array<int, array{methods: array<int, string>, path: string, handler: array<string, mixed>}>
     */
    public function routeDefinitions(): array
    {
        return $this->enabled ? $this->routeResolver->routeDefinitions() : [];
    }

    /**
     * @param array<string, mixed> $handler
     * @param array<string, mixed> $routeVars
     */
    public function handle(array $handler, Request $request, array $routeVars = []): Response
    {
        if (!$this->enabled) {
            return $this->disabledResponse();
        }

        $type = is_string($handler['type'] ?? null) ? (string) $handler['type'] : '';
        if ($type === 'redirect') {
            $status = max(300, min(399, (int) ($handler['status'] ?? 302)));
            $location = is_string($handler['location'] ?? null) ? (string) $handler['location'] : $this->routeResolver->canonicalPath('login');

            return PrivateResponseHeaders::apply(new Response($status, ['Location' => $location], ''));
        }

        if ($type !== 'private' || !$this->controller instanceof PrivatePortalController) {
            return $this->disabledResponse();
        }

        return PrivateResponseHeaders::apply(
            $this->controller->handle((string) ($handler['page'] ?? 'login'), $request, $routeVars)
        );
    }

    /**
     * @param array<string, mixed> $handler
     */
    public function ownsHandler(array $handler): bool
    {
        $type = is_string($handler['type'] ?? null) ? (string) $handler['type'] : '';
        if ($type === 'private') {
            return true;
        }

        if ($type !== 'redirect') {
            return false;
        }

        $location = is_string($handler['location'] ?? null) ? (string) $handler['location'] : '';

        return $this->pathBelongsToPrivateBase($location);
    }

    public function matchesPrivatePath(Request $request): bool
    {
        return $this->pathBelongsToPrivateBase(\request_path($request->uri()));
    }

    public function disabledResponse(): Response
    {
        return PrivateResponseHeaders::apply(
            new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not found')
        );
    }

    private function pathBelongsToPrivateBase(string $path): bool
    {
        $candidate = parse_url($path, PHP_URL_PATH);
        $candidatePath = is_string($candidate) && $candidate !== '' ? $candidate : $path;
        $normalizedPath = '/' . trim($candidatePath, '/');
        $basePath = '/' . trim($this->routeResolver->basePath(), '/');

        return $normalizedPath === $basePath || str_starts_with($normalizedPath, $basePath . '/');
    }
}
