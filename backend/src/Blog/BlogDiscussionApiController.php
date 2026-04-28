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
            return new Response(400, ['Content-Type' => 'text/plain; charset=utf-8'], (string) t('TXT_BLOG_DISCUSSION_ERROR_INVALID_ARTICLE'));
        }

        $scope = $this->discussionScope($slug, $language);
        $redirectUrl = $this->resolveRedirectUrl($request, $payload, $slug, $language);
        $article = $this->articleRepository->findPublished($slug, $language);

        if (!is_array($article)) {
            return new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], (string) t('TXT_BLOG_DISCUSSION_ERROR_ARTICLE_NOT_FOUND'));
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

            $this->setFlash($scope, 'error', (string) t('TXT_BLOG_DISCUSSION_ERROR_DISABLED'));

            return $this->redirect($redirectUrl);
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

            $this->setFlash($scope, 'error', (string) t('TXT_BLOG_DISCUSSION_ACCOUNT_REQUIRED'));

            return $this->redirect($redirectUrl);
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

            $this->setFlash($scope, 'error', (string) t('TXT_BLOG_DISCUSSION_ERROR_SESSION_EXPIRED'), $payload);

            return $this->redirect($redirectUrl);
        }

        if (!$this->validateFormNonce($scope, (string) ($payload['form_nonce'] ?? ''))) {
            $this->setFlash($scope, 'error', (string) t('TXT_BLOG_DISCUSSION_ERROR_FORM_EXPIRED'), $payload);

            return $this->redirect($redirectUrl);
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

            $this->setFlash($scope, 'error', (string) t('TXT_BLOG_DISCUSSION_ERROR_SPAM_BLOCKED'), $payload);

            return $this->redirect($redirectUrl);
        }

        $clientIp = $request->clientIp((bool) app_config('admin.trust_proxy_headers', false)) ?? 'unknown';
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

            $this->setFlash(
                $scope,
                'error',
                sprintf((string) t('TXT_BLOG_DISCUSSION_ERROR_RATE_LIMIT'), $retryAfter),
                $payload
            );

            return $this->redirect($redirectUrl);
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
            $this->setFlash($scope, 'error', $errorMessage, $payload);

            return $this->redirect($redirectUrl);
        }

        $recaptchaError = $this->validateRecaptcha($request, $payload, $slug, $language);
        if ($recaptchaError !== null) {
            $this->setFlash($scope, 'error', $recaptchaError, $payload);

            return $this->redirect($redirectUrl);
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

            $this->setFlash($scope, 'error', (string) t('TXT_BLOG_DISCUSSION_ERROR_SAVE_FAILED'), $payload);

            return $this->redirect($redirectUrl);
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

        $this->setFlash(
            $scope,
            'success',
            (string) t('TXT_BLOG_DISCUSSION_SUCCESS_PENDING')
        );

        return $this->redirect($redirectUrl);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateRecaptcha(Request $request, array $payload, string $slug, string $language): ?string
    {
        $recaptcha = app_config('site.discussions.recaptcha', []);
        $recaptcha = is_array($recaptcha) ? $recaptcha : [];

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

        $minimumScore = (float) ($recaptcha['minimum_score'] ?? 0.5);
        $score = $verification['score'] ?? null;
        if ($score !== null && is_numeric($score) && (float) $score < $minimumScore) {
            $this->eventLogger->security(
                'blog.discussion.recaptcha.low_score',
                [
                    'slug' => $slug,
                    'lang' => $language,
                    'score' => (float) $score,
                    'minimum_score' => $minimumScore,
                ],
                'warning'
            );

            return (string) t('TXT_BLOG_DISCUSSION_ERROR_RECAPTCHA_LOW_SCORE');
        }

        return null;
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
