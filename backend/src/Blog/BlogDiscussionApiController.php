<?php

declare(strict_types=1);

namespace Caramagnols\Blog;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;

final class BlogDiscussionApiController
{
    private AppEventLogger $eventLogger;
    private RecaptchaVerifier $recaptchaVerifier;
    private BlogPublicUrlResolver $publicUrlResolver;

    public function __construct(
        private readonly BlogRepositoryInterface $articleRepository,
        private readonly BlogDiscussionRepositoryInterface $discussionRepository,
        ?RecaptchaVerifier $recaptchaVerifier = null,
        ?AppEventLogger $eventLogger = null,
        ?BlogPublicUrlResolver $publicUrlResolver = null
    ) {
        require_once ROOT_PATH . '/core/rate_limiter.php';

        $this->recaptchaVerifier = $recaptchaVerifier ?? new RecaptchaVerifier();
        $this->eventLogger = $eventLogger ?? app_event_logger();
        $this->publicUrlResolver = $publicUrlResolver ?? new BlogPublicUrlResolver(
            $this->articleRepository,
            page_repository(pages_data_path()),
            (string) app_config('default_lang', 'fr')
        );
    }

    public function submit(Request $request): Response
    {
        $expectsJson = $this->wantsJsonResponse($request);

        if ($request->method() !== 'POST') {
            return Response::json(['error' => (string) t('TXT_BLOG_DISCUSSION_ERROR_METHOD_NOT_ALLOWED')], 405);
        }

        $payload = $request->body();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->json();
        }

        $slug = $this->normalizeSlug((string) ($payload['article_slug'] ?? ''));
        $language = $this->normalizeLanguage((string) ($payload['article_lang'] ?? (app_config('default_lang', 'fr'))));

        if ($slug === '') {
            return $this->submitErrorResponse($expectsJson, 400, (string) t('TXT_BLOG_DISCUSSION_ERROR_INVALID_ARTICLE'));
        }

        $scope = $this->discussionScope($slug, $language);
        $redirectUrl = $this->resolveRedirectUrl($request, $payload, $slug, $language);
        $article = $this->articleRepository->findPublished($slug, $language);
        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown';

        $this->eventLogger->content(
            'blog.discussion.submit_received',
            [
                'slug' => $slug,
                'lang' => $language,
                'ip' => $clientIp,
                'has_recaptcha_token' => trim((string) ($payload['g-recaptcha-response'] ?? '')) !== '',
                'recaptcha_token_length' => strlen(trim((string) ($payload['g-recaptcha-response'] ?? ''))),
                'return_to' => trim((string) ($payload['return_to'] ?? '')),
                'referer' => (string) ($request->header('Referer') ?? ''),
                'user_agent' => (string) ($request->header('User-Agent') ?? ''),
            ]
        );

        if (!is_array($article)) {
            return $this->submitErrorResponse(
                $expectsJson,
                404,
                (string) t('TXT_BLOG_DISCUSSION_ERROR_ARTICLE_NOT_FOUND'),
                $scope
            );
        }

        if (!(bool) app_config('site.discussions.enabled', true)) {
            $this->eventLogger->security(
                'blog.discussion.disabled_submission',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'ip' => $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)),
                ],
                'warning'
            );

            return $this->submitErrorResponse(
                $expectsJson,
                403,
                (string) t('TXT_BLOG_DISCUSSION_ERROR_DISABLED'),
                $scope,
                $redirectUrl
            );
        }

        if ((bool) app_config('site.discussions.require_account', false)) {
            $this->eventLogger->security(
                'blog.discussion.require_account_blocked',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'ip' => $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)),
                ],
                'warning'
            );

            return $this->submitErrorResponse(
                $expectsJson,
                403,
                (string) t('TXT_BLOG_DISCUSSION_ACCOUNT_REQUIRED'),
                $scope,
                $redirectUrl
            );
        }

        $token = is_string($payload['csrf_token'] ?? null) ? $payload['csrf_token'] : null;
        if (!csrf_validate($token, $scope, false)) {
            $this->eventLogger->security(
                'blog.discussion.invalid_csrf',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'ip' => $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)),
                ],
                'warning'
            );

            return $this->submitErrorResponse(
                $expectsJson,
                422,
                (string) t('TXT_BLOG_DISCUSSION_ERROR_SESSION_EXPIRED'),
                $scope,
                $redirectUrl,
                $payload
            );
        }

        if (!$this->validateFormNonce($scope, (string) ($payload['form_nonce'] ?? ''))) {
            return $this->submitErrorResponse(
                $expectsJson,
                422,
                (string) t('TXT_BLOG_DISCUSSION_ERROR_FORM_EXPIRED'),
                $scope,
                $redirectUrl,
                $payload
            );
        }

        $honeypotField = $this->honeypotFieldName();
        $honeypot = trim((string) ($payload[$honeypotField] ?? ''));
        if ($honeypot !== '') {
            $this->eventLogger->security(
                'blog.discussion.honeypot_triggered',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'ip' => $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)),
                ],
                'warning'
            );

            return $this->submitErrorResponse(
                $expectsJson,
                422,
                (string) t('TXT_BLOG_DISCUSSION_ERROR_SPAM_BLOCKED'),
                $scope,
                $redirectUrl,
                $payload
            );
        }

        $perIpLimiter = new \FileRateLimiter(
            'blog-discussion:' . $language . ':' . $slug . ':' . $clientIp,
            max(1, (int) app_config('site.discussions.rate_limit_per_ip', 6)),
            max(60, (int) app_config('site.discussions.rate_limit_window', 600))
        );
        $globalLimiter = new \FileRateLimiter(
            'blog-discussion-global:' . $clientIp,
            max(1, (int) app_config('site.discussions.global_rate_limit_per_ip', 20)),
            max(60, (int) app_config('site.discussions.global_rate_limit_window', 3600))
        );

        if (!$perIpLimiter->allow() || !$globalLimiter->allow()) {
            $retryAfter = max($perIpLimiter->retryAfter(), $globalLimiter->retryAfter());

            $this->eventLogger->security(
                'blog.discussion.rate_limited',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'ip' => $clientIp,
                    'retry_after' => $retryAfter,
                ],
                'warning'
            );

            return $this->submitErrorResponse(
                $expectsJson,
                429,
                sprintf((string) t('TXT_BLOG_DISCUSSION_ERROR_RATE_LIMIT'), $retryAfter),
                $scope,
                $redirectUrl,
                $payload
            );
        }

        $perIpLimiter->hit();
        $globalLimiter->hit();

        $sanitized = sanitize_comment_payload([
            'author' => $payload['author'] ?? '',
            'email' => $payload['email'] ?? '',
            'content' => $payload['content'] ?? '',
        ]);

        if (($sanitized['errors'] ?? []) !== []) {
            $errorMessage = implode(' ', array_map('strval', $sanitized['errors']));

            return $this->submitErrorResponse(
                $expectsJson,
                422,
                $errorMessage,
                $scope,
                $redirectUrl,
                $payload
            );
        }

        $recaptchaError = $this->validateRecaptcha($request, $payload, $slug, $language);
        if ($recaptchaError !== null) {
            return $this->submitErrorResponse(
                $expectsJson,
                422,
                $recaptchaError,
                $scope,
                $redirectUrl,
                $payload
            );
        }

        $userAgent = (string) ($request->header('User-Agent') ?? '');
        $hashSalt = (string) app_config('admin.session_key', 'caramagnols');

        $comment = [
            'author' => (string) ($sanitized['data']['author'] ?? ''),
            'email' => (string) ($sanitized['data']['email'] ?? ''),
            'content' => (string) ($sanitized['data']['content'] ?? ''),
            'ip_hash' => hash('sha256', $clientIp . '|' . $hashSalt),
            'user_agent_hash' => hash('sha256', $userAgent . '|' . $hashSalt),
        ];

        try {
            $saved = $this->discussionRepository->submitPending($slug, $language, $comment);
        } catch (\Throwable $exception) {
            $this->eventLogger->content(
                'blog.discussion.save_failed',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'ip' => $clientIp,
                    'exception' => $exception->getMessage(),
                ],
                'error'
            );

            return $this->submitErrorResponse(
                $expectsJson,
                500,
                (string) t('TXT_BLOG_DISCUSSION_ERROR_SAVE_FAILED'),
                $scope,
                $redirectUrl,
                $payload
            );
        }

        $this->eventLogger->content(
            'blog.discussion.submitted',
            [
                'slug' => $slug,
                'lang' => $language,
                'discussion_id' => (string) ($saved['id'] ?? ''),
                'ip' => $clientIp,
            ]
        );

        return $this->submitSuccessResponse(
            $expectsJson,
            (string) t('TXT_BLOG_DISCUSSION_SUCCESS_PENDING'),
            $scope,
            $redirectUrl,
            [
                'discussion' => [
                    'id' => (string) ($saved['id'] ?? ''),
                    'status' => (string) ($saved['status'] ?? 'pending'),
                ],
            ]
        );
    }

    public function logClientEvent(Request $request): Response
    {
        if ($request->method() !== 'POST') {
            return Response::json(['error' => (string) t('TXT_BLOG_DISCUSSION_ERROR_METHOD_NOT_ALLOWED')], 405);
        }

        $payload = $request->json();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->body();
        }
        if (!is_array($payload)) {
            $payload = [];
        }

        $slug = $this->normalizeSlug((string) ($payload['article_slug'] ?? ''));
        $language = $this->normalizeLanguage((string) ($payload['article_lang'] ?? (app_config('default_lang', 'fr'))));
        $stage = $this->normalizeClientEventStage((string) ($payload['stage'] ?? 'unknown'));
        $level = $this->normalizeClientEventLevel((string) ($payload['level'] ?? 'info'));
        $mode = DiscussionRecaptchaMode::normalize($payload['mode'] ?? null);
        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown';
        $userAgent = (string) ($request->header('User-Agent') ?? '');
        $environment = strtolower((string) env('APP_ENV', 'production'));
        $isProduction = in_array($environment, ['production', 'prod', 'live'], true);
        $endpoint = $this->normalizeClientTelemetryEndpoint($request->uri());
        $samplingDivisor = max(
            1,
            (int) app_config('site.discussions.telemetry_sample_divisor', 4)
        );

        $limiter = new \FileRateLimiter(
            'blog-discussion-telemetry:' . $endpoint . ':' . $clientIp,
            max(1, (int) app_config('site.discussions.telemetry_rate_limit_per_ip', 45)),
            max(1, (int) app_config('site.discussions.telemetry_rate_limit_window', 120))
        );

        if (!$limiter->allow()) {
            $this->eventLogger->security(
                'blog.discussion.client_telemetry_rate_limited',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'ip' => $clientIp,
                    'retry_after' => $limiter->retryAfter(),
                    'stage' => $stage,
                    'level' => $level,
                ],
                'warning'
            );

            return new Response(204, ['Cache-Control' => 'no-store'], '');
        }

        $limiter->hit();

        if (
            $isProduction
            && $level === 'info'
            && !$this->shouldSampleTelemetryEvent($clientIp, $slug, $stage, $language, $samplingDivisor)
        ) {
            return new Response(204, ['Cache-Control' => 'no-store'], '');
        }

        $referer = (string) ($request->header('Referer') ?? '');
        $normalizedUserAgent = function_exists('mb_substr') ? mb_substr($userAgent, 0, 200) : substr($userAgent, 0, 200);
        $normalizedReferer = $this->normalizeClientPage($referer);
        if ($normalizedReferer === '') {
            $normalizedReferer = 'unknown';
        }
        if ($normalizedUserAgent === '') {
            $normalizedUserAgent = 'unknown';
        }

        $this->eventLogger->content(
            'blog.discussion.client_telemetry',
            [
                'slug' => $slug,
                'lang' => $language,
                'stage' => $stage,
                'level' => $level,
                'mode' => $mode,
                'page' => $this->normalizeClientPage((string) ($payload['page'] ?? '')),
                'client_ip' => $clientIp,
                'referer' => $normalizedReferer,
                'user_agent' => $normalizedUserAgent,
                'details' => $this->sanitizeClientEventDetails($payload['details'] ?? []),
            ],
            $level
        );

        return new Response(204, ['Cache-Control' => 'no-store'], '');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateRecaptcha(Request $request, array $payload, string $slug, string $language): ?string
    {
        $recaptcha = app_config('site.discussions.recaptcha', []);
        $recaptcha = is_array($recaptcha) ? $recaptcha : [];
        $mode = DiscussionRecaptchaMode::normalize($recaptcha['mode'] ?? null);

        $enabled = (bool) ($recaptcha['enabled'] ?? false);
        if (!$enabled) {
            return null;
        }

        $secretKey = trim((string) ($recaptcha['secret_key'] ?? ''));
        $siteKey = trim((string) ($recaptcha['site_key'] ?? ''));

        if ($secretKey === '' || $siteKey === '') {
            $this->eventLogger->security(
                'blog.discussion.recaptcha.misconfigured',
                [
                    'slug' => $slug,
                    'lang' => $language,
                ],
                'warning'
            );

            return (string) t('TXT_BLOG_DISCUSSION_ERROR_RECAPTCHA_MISCONFIGURED');
        }

        $token = trim((string) ($payload['g-recaptcha-response'] ?? ''));
        if ($token === '') {
            return (string) t('TXT_BLOG_DISCUSSION_ERROR_RECAPTCHA_REQUIRED');
        }

        $verification = $this->recaptchaVerifier->verify(
            $secretKey,
            $token,
            $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)),
            max(3, (int) ($recaptcha['timeout_seconds'] ?? 8))
        );

        $this->eventLogger->content(
            'blog.discussion.recaptcha.verify_result',
            [
                'slug' => $slug,
                'lang' => $language,
                'mode' => $mode,
                'success' => (bool) ($verification['success'] ?? false),
                'score' => is_numeric($verification['score'] ?? null) ? (float) $verification['score'] : null,
                'action' => is_string($verification['action'] ?? null) ? (string) $verification['action'] : null,
                'error_codes' => is_array($verification['errorCodes'] ?? null) ? $verification['errorCodes'] : [],
                'token_length' => strlen($token),
            ]
        );

        if (($verification['success'] ?? false) !== true) {
            $this->eventLogger->security(
                'blog.discussion.recaptcha.failed',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'errors' => $verification['errorCodes'] ?? [],
                ],
                'warning'
            );

            return (string) t('TXT_BLOG_DISCUSSION_ERROR_RECAPTCHA_INVALID');
        }

        if (DiscussionRecaptchaMode::usesScoreVerification($mode)) {
            $action = trim((string) ($verification['action'] ?? ''));
            if ($action !== DiscussionRecaptchaMode::V3_ACTION) {
                $this->eventLogger->security(
                    'blog.discussion.recaptcha.invalid_action',
                    [
                        'slug' => $slug,
                        'lang' => $language,
                        'action' => $action,
                    ],
                    'warning'
                );

                return (string) t('TXT_BLOG_DISCUSSION_ERROR_RECAPTCHA_INVALID');
            }

            $minimumScore = (float) ($recaptcha['minimum_score'] ?? 0.5);
            $score = $verification['score'] ?? null;
            if (!is_numeric($score) || (float) $score < $minimumScore) {
                $this->eventLogger->security(
                    'blog.discussion.recaptcha.low_score',
                    [
                        'slug' => $slug,
                        'lang' => $language,
                        'score' => is_numeric($score) ? (float) $score : null,
                        'minimum_score' => $minimumScore,
                    ],
                    'warning'
                );

                return (string) t('TXT_BLOG_DISCUSSION_ERROR_RECAPTCHA_LOW_SCORE');
            }
        }

        return null;
    }

    private function wantsJsonResponse(Request $request): bool
    {
        $requestedWith = strtolower(trim((string) ($request->header('X-Requested-With') ?? '')));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string) ($request->header('Accept') ?? ''));

        return str_contains($accept, 'application/json');
    }

    private function submitErrorResponse(
        bool $expectsJson,
        int $status,
        string $message,
        string $scope = '',
        string $redirectUrl = '',
        array $payload = []
    ): Response {
        if ($expectsJson) {
            $responsePayload = [
                'ok' => false,
                'message' => $message,
            ];

            if ($scope !== '') {
                $responsePayload['form'] = $this->issueFormState($scope);
            }

            return Response::json($responsePayload, $status, ['Cache-Control' => 'no-store']);
        }

        if ($scope !== '') {
            $this->setFlash($scope, 'error', $message, $payload);
        }

        if ($redirectUrl === '') {
            return new Response($status, ['Content-Type' => 'text/plain; charset=utf-8'], $message);
        }

        return $this->redirect($redirectUrl);
    }

    private function submitSuccessResponse(
        bool $expectsJson,
        string $message,
        string $scope,
        string $redirectUrl,
        array $extra = []
    ): Response {
        if ($expectsJson) {
            $responsePayload = array_merge(
                [
                    'ok' => true,
                    'message' => $message,
                    'form' => $this->issueFormState($scope),
                ],
                $extra
            );

            return Response::json($responsePayload, 201, ['Cache-Control' => 'no-store']);
        }

        $this->setFlash($scope, 'success', $message);

        return $this->redirect($redirectUrl);
    }

    /**
     * @return array{csrf_token:string,form_nonce:string}
     */
    private function issueFormState(string $scope): array
    {
        ensure_session_started();

        $nonce = bin2hex(random_bytes(16));
        $_SESSION['_blog_discussion_form_nonces'][$nonce] = [
            'scope' => $scope,
            'issued_at' => time(),
        ];

        return [
            'csrf_token' => csrf_token($scope),
            'form_nonce' => $nonce,
        ];
    }

    private function normalizeClientEventStage(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? substr($value, 0, 64) : 'unknown';
    }

    private function normalizeClientEventLevel(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['debug', 'info', 'warning', 'error'], true) ? $value : 'info';
    }

    private function normalizeClientTelemetryEndpoint(string $uri): string
    {
        $path = is_string(parse_url($uri, PHP_URL_PATH)) ? (string) parse_url($uri, PHP_URL_PATH) : '';
        if ($path === '') {
            return 'unknown';
        }

        $normalized = normalize_public_route($path);
        $normalized = is_string($normalized) ? $normalized : '/';

        if (!preg_match('#^/[a-z0-9/_.-]*$#i', $normalized)) {
            return 'unknown';
        }

        return $normalized;
    }

    private function normalizeClientPage(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        if (!is_array($parts)) {
            return substr($value, 0, 200);
        }

        $path = normalize_public_route((string) ($parts['path'] ?? '')) ?? '';
        $query = isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '' ? '#' . $parts['fragment'] : '';

        return substr($path . $query . $fragment, 0, 200);
    }

    private function shouldSampleTelemetryEvent(
        string $clientIp,
        string $slug,
        string $stage,
        string $language,
        int $samplingDivisor
    ): bool {
        if ($samplingDivisor <= 1) {
            return true;
        }

        $hash = crc32($clientIp . '|' . $slug . '|' . $stage . '|' . $language);
        $bucket = abs((int) $hash) % $samplingDivisor;

        return $bucket === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function sanitizeClientEventDetails(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $details = [];
        foreach ($value as $key => $detailValue) {
            $normalizedKey = strtolower(trim((string) $key));
            $normalizedKey = preg_replace('/[^a-z0-9_-]+/', '_', $normalizedKey) ?? '';
            $normalizedKey = trim($normalizedKey, '_');
            if ($normalizedKey === '') {
                continue;
            }

            if (is_bool($detailValue) || is_int($detailValue) || is_float($detailValue) || $detailValue === null) {
                $details[$normalizedKey] = $detailValue;
                continue;
            }

            if (is_string($detailValue)) {
                $details[$normalizedKey] = function_exists('mb_substr') ? mb_substr($detailValue, 0, 300) : substr($detailValue, 0, 300);
                continue;
            }

            if (is_array($detailValue)) {
                $details[$normalizedKey] = array_map(
                    static fn (mixed $item): string|int|float|bool|null => is_scalar($item) || $item === null
                        ? (is_string($item) ? (function_exists('mb_substr') ? mb_substr($item, 0, 120) : substr($item, 0, 120)) : $item)
                        : gettype($item),
                    array_slice(array_values($detailValue), 0, 20)
                );
            }
        }

        return $details;
    }

    private function honeypotFieldName(): string
    {
        $field = trim((string) app_config('site.discussions.honeypot_field', 'website'));

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,40}$/', $field) !== 1) {
            return 'website';
        }

        return $field;
    }

    private function discussionScope(string $slug, string $language): string
    {
        return 'blog_discussion_' . hash('sha256', $language . ':' . $slug);
    }

    private function articleUrl(string $slug, string $language): string
    {
        $path = $this->publicUrlResolver->publicPathForPublishedArticleSlug($slug, $language)
            ?? $this->publicUrlResolver->fallbackArticlePath($slug, $language);

        return app_url(ltrim($path, '/'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRedirectUrl(Request $request, array $payload, string $slug, string $language): string
    {
        $attachedPath = $this->publicUrlResolver->attachedPathForPublishedArticleSlug($slug, $language);
        $attachedDiscussionBasePath = is_string($attachedPath)
            ? preg_replace('/#.*$/', '', $attachedPath)
            : null;
        $fallback = is_string($attachedDiscussionBasePath) && $attachedDiscussionBasePath !== ''
            ? app_url(ltrim((string) $attachedDiscussionBasePath, '/'), $request) . '#discussion-form-' . rawurlencode($slug)
            : $this->articleUrl($slug, $language) . '#discussion-form';
        $candidate = trim((string) ($payload['return_to'] ?? ''));

        if ($candidate === '' || preg_match('/[\r\n]/', $candidate) === 1) {
            return $fallback;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts)) {
            return $fallback;
        }

        $path = normalize_public_route((string) ($parts['path'] ?? ''));
        if (!is_string($path) || $path === '' || preg_match('#^/(?:core|admin)(?:/|$)#', $path) === 1) {
            return $fallback;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            $baseParts = parse_url(app_base_url($request));
            $baseHost = app_host_strip_port((string) ($baseParts['host'] ?? ''));
            $candidateHost = app_host_strip_port((string) ($parts['host'] ?? ''));
            $baseScheme = strtolower((string) ($baseParts['scheme'] ?? ''));
            $candidateScheme = strtolower((string) ($parts['scheme'] ?? ''));
            $basePort = (int) ($baseParts['port'] ?? 0);
            $candidatePort = (int) ($parts['port'] ?? 0);

            if ($baseHost === '' || $candidateHost === '' || !hash_equals($baseHost, $candidateHost)) {
                return $fallback;
            }

            if ($candidateScheme !== '' && $baseScheme !== '' && $candidateScheme !== $baseScheme) {
                return $fallback;
            }

            if ($candidatePort !== 0 && $basePort !== 0 && $candidatePort !== $basePort) {
                return $fallback;
            }
        }

        $query = isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== ''
            ? '?' . $parts['query']
            : '';
        $fragment = isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== ''
            ? '#' . $parts['fragment']
            : '';

        return app_url(ltrim($path, '/'), $request) . $query . $fragment;
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }

    private function normalizeLanguage(string $language): string
    {
        $normalized = strtolower(trim($language));

        if (!in_array($normalized, site_available_languages(), true)) {
            return (string) app_config('default_lang', 'fr');
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function setFlash(string $scope, string $type, string $message, array $payload = []): void
    {
        ensure_session_started();

        $author = trim((string) ($payload['author'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));

        $_SESSION['_blog_discussion_flash'][$scope] = [
            'type' => $type,
            'message' => $message,
            'old' => [
                'author' => $author,
                'email' => $email,
                'content' => $content,
            ],
        ];
    }

    private function validateFormNonce(string $scope, string $nonce): bool
    {
        $nonce = strtolower(trim($nonce));
        if ($nonce === '' || preg_match('/^[a-f0-9]{16,64}$/', $nonce) !== 1) {
            return false;
        }

        ensure_session_started();

        $entries = is_array($_SESSION['_blog_discussion_form_nonces'] ?? null)
            ? $_SESSION['_blog_discussion_form_nonces']
            : [];
        $entry = is_array($entries[$nonce] ?? null) ? $entries[$nonce] : null;
        unset($_SESSION['_blog_discussion_form_nonces'][$nonce]);

        if ($entry === null) {
            return false;
        }

        if (!hash_equals($scope, (string) ($entry['scope'] ?? ''))) {
            return false;
        }

        $issuedAt = (int) ($entry['issued_at'] ?? 0);
        if ($issuedAt <= 0) {
            return false;
        }

        $elapsed = time() - $issuedAt;
        $minAge = max(0, (int) app_config('site.discussions.min_form_fill_seconds', 3));
        $maxAge = max($minAge + 1, (int) app_config('site.discussions.max_form_age_seconds', 7200));

        return $elapsed >= $minAge && $elapsed <= $maxAge;
    }

    private function redirect(string $location): Response
    {
        return new Response(303, ['Location' => $location], '');
    }
}
