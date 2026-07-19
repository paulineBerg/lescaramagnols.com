<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;

final class BlogApiController
{
    private AppEventLogger $eventLogger;

    public function __construct(
        private readonly BlogSaveService $saveService,
        ?AppEventLogger $eventLogger = null
    ) {
        require_once ROOT_PATH . '/core/auth/admin.php';
        require_once ROOT_PATH . '/core/rate_limiter.php';

        $this->eventLogger = $eventLogger ?? app_event_logger();
    }

    public function saveArticle(Request $request): Response
    {
        if ($request->method() !== 'POST') {
            return Response::json(['error' => 'Méthode non autorisée. Utilisez POST.'], 405);
        }

        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false));
        $allowedIps = app_config('admin.allowed_ips', []);
        $allowedIps = is_array($allowedIps) ? array_values(array_filter(array_map('strval', $allowedIps))) : [];
        if (!ip_matches_allowlist($clientIp, $allowedIps)) {
            $this->eventLogger->security(
                'blog.article.ip_not_allowed',
                [
                    'uri' => $request->uri(),
                    'method' => $request->method(),
                    'ip' => $clientIp,
                    'actor' => admin_current_masked_identifier(),
                ],
                'warning'
            );

            return Response::json(['error' => 'Accès interdit.'], 403);
        }

        if (!admin_is_authenticated()) {
            $this->eventLogger->security(
                'blog.article.unauthenticated',
                [
                    'uri' => $request->uri(),
                    'method' => $request->method(),
                ],
                'warning'
            );

            return Response::json(['error' => 'Authentification admin requise.'], 401);
        }

        $decoded = json_decode($request->content(), true);
        if (!is_array($decoded)) {
            $this->eventLogger->security(
                'blog.article.invalid_json',
                [
                    'uri' => $request->uri(),
                    'actor' => admin_current_masked_identifier(),
                ],
                'warning'
            );

            return Response::json(['error' => 'Payload JSON invalide.'], 400);
        }

        $token = $decoded['csrf_token'] ?? $request->body()['csrf_token'] ?? null;
        if (!admin_validate_csrf_token(is_string($token) ? $token : null)) {
            $this->eventLogger->security(
                'blog.article.invalid_csrf',
                [
                    'uri' => $request->uri(),
                    'actor' => admin_current_masked_identifier(),
                ],
                'warning'
            );

            return Response::json(['error' => 'Jeton CSRF invalide ou expiré.'], 403);
        }

        $limiter = new \SessionRateLimiter('blog:save_article', 10, 120);
        if (!$limiter->allow()) {
            $this->eventLogger->security(
                'blog.article.rate_limited',
                [
                    'uri' => $request->uri(),
                    'actor' => admin_current_masked_identifier(),
                    'retry_after' => $limiter->retryAfter(),
                ],
                'warning'
            );

            return Response::json(
                ['error' => 'Trop de requêtes, merci de patienter ' . $limiter->retryAfter() . ' secondes.'],
                429
            );
        }

        $limiter->hit();

        $actorIdentifier = admin_current_identifier();
        $result = $this->saveService->save($decoded, $actorIdentifier);

        if (($result['ok'] ?? false) !== true) {
            $payload = [];

            if (isset($result['errors']) && is_array($result['errors'])) {
                $payload['errors'] = $result['errors'];
            } else {
                $payload['error'] = 'Erreur inconnue lors de la sauvegarde.';
            }

            return Response::json($payload, (int) ($result['status'] ?? 500));
        }

        return Response::json(
            [
                'data' => $result['data'],
                'meta' => [
                    'storage' => blog_storage_mode(),
                    'mode' => (string) app_config('blog.mode', 'experimental'),
                    'path' => basename((string) ($result['path'] ?? '')),
                ],
            ],
            (int) ($result['status'] ?? 201)
        );
    }
}
