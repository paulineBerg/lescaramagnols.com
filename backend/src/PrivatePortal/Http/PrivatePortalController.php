<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Http;

use Caramagnols\Http\Request;
use Caramagnols\Http\Response;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\PrivatePortal\Security\PrivateAuth;
use Caramagnols\PrivatePortal\Security\PrivatePortalSecurityGuard;

final class PrivatePortalController
{
    private const METHOD_GET = 'GET';
    private const METHOD_POST = 'POST';

    public function __construct(
        private readonly PrivateAuth $auth,
        private readonly ?PrivatePortalSecurityGuard $securityGuard = null,
        private readonly ?AppEventLogger $eventLogger = null
    ) {
    }

    public function handle(string $page, Request $request, array $routeParams = []): Response
    {
        return match ($page) {
            'login' => $this->handleLogin($request),
            'dashboard' => $this->handleDashboard($request),
            'logout' => $this->handleLogout($request),
            'activate' => $this->handleSimpleInfo(
                (string) ($routeParams['token'] ?? ''),
                'TXT_PRIVATE_ACTIVATE_TITLE',
                'TXT_PRIVATE_ACTIVATE_DESCRIPTION'
            ),
            'password_forgot' => $this->handlePasswordForgot($request),
            'password_reset' => $this->handlePasswordReset(
                $request,
                (string) ($routeParams['token'] ?? '')
            ),
            'files' => $this->handleFiles((string) ($routeParams['documentId'] ?? '')),
            default => $this->handleNotFound(),
        };
    }

    private function handleLogin(Request $request): Response
    {
        if ($this->auth->isAuthenticated()) {
            return $this->redirect(private_portal_url('dashboard'));
        }

        $this->guard();

        $body = $request->body();
        $identifier = is_string($body['identifier'] ?? null) ? trim((string) $body['identifier']) : '';

        $error = null;

        if ($request->method() === self::METHOD_POST) {
            $csrfToken = is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : null;
            if (!csrf_validate($csrfToken, 'private')) {
                $error = 'TXT_PRIVATE_ERROR_CSRF';

                $this->logEvent('private.login.rejected', ['reason' => 'csrf_invalid']);
            } else {
                $password = is_string($body['password'] ?? null) ? (string) $body['password'] : '';
                $clientIp = $request->clientIp((bool) app_config('private.trust_proxy_headers', false));

                if (!$this->auth->canAttemptLogin($identifier, $clientIp)) {
                    $error = $this->auth->failureReason() === 'account_locked'
                        ? 'TXT_PRIVATE_ERROR_ACCOUNT_LOCKED'
                        : 'TXT_PRIVATE_ERROR_RATE_LIMIT';

                    $this->logEvent('private.login.rejected', ['reason' => $this->auth->failureReason() ?? 'unknown', 'ip' => $clientIp ?? '']);
                } elseif ($this->auth->login($identifier, $password, $clientIp ?? null)) {
                    return $this->redirect(private_portal_url('dashboard'));
                } else {
                    $error = 'TXT_PRIVATE_ERROR_INVALID_CREDENTIALS';
                }
            }
        }

        return $this->render('login', [
            'privatePageTitle' => (string) t('TXT_PRIVATE_LOGIN_PAGE_TITLE', 'Espace privé'),
            'identifier' => $identifier,
            'errorKey' => $error,
            'csrfToken' => csrf_token('private'),
            'privatePasswordForgotUrl' => private_portal_url('password_forgot'),
        ]);
    }

    private function handleDashboard(Request $request): Response
    {
        $guard = $this->guard();
        $required = $guard->requireAuthenticated($request, private_portal_url('login'), true);
        if ($required !== null) {
            return $required;
        }

        $identifier = $this->auth->currentIdentifier();

        return $this->render('dashboard', [
            'privatePageTitle' => (string) t('TXT_PRIVATE_DASHBOARD_TITLE', 'Tableau de bord privé'),
            'privateUserIdentifier' => is_string($identifier) ? $identifier : '',
            'privateModules' => [],
            'privateDashboardLogoutUrl' => private_portal_url('logout'),
            'privateLogoutCsrfToken' => csrf_token('private_logout'),
            'privatePasswordForgotUrl' => private_portal_url('password_forgot'),
        ]);
    }

    private function handleLogout(Request $request): Response
    {
        if ($request->method() === self::METHOD_GET) {
            $this->logEvent('private.logout.failed', ['reason' => 'method_not_allowed']);

            return $this->withPrivateHeaders(new Response(
                405,
                ['Allow' => self::METHOD_POST],
                'Cette action exige une requête POST.'
            ));
        }

        if (!$this->guard()->validateCsrf($request, 'private_logout')) {
            $this->logEvent('private.logout.rejected', ['reason' => 'csrf_invalid']);
            return $this->render('login', [
                'privatePageTitle' => (string) t('TXT_PRIVATE_DASHBOARD_TITLE', 'Tableau de bord privé'),
                'errorKey' => 'TXT_PRIVATE_ERROR_CSRF',
                'csrfToken' => csrf_token('private'),
                'privatePasswordForgotUrl' => private_portal_url('password_forgot'),
            ]);
        }

        $this->auth->logout('manual');

        return $this->redirect(private_portal_url('login'));
    }

    private function handlePasswordForgot(Request $request): Response
    {
        if ($request->method() === self::METHOD_POST && !$this->guard()->validateCsrf($request, 'private_password')) {
            return $this->render('notice', [
                'privatePageTitle' => (string) t('TXT_PRIVATE_PASSWORD_FORGOT_TITLE', 'Réinitialisation privée'),
                'privateNoticeTitle' => (string) t('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
                'privateNoticeBody' => (string) t('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
            ]);
        }

        return $this->handleSimpleInfo(
            '',
            'TXT_PRIVATE_PASSWORD_FORGOT_TITLE',
            'TXT_PRIVATE_PASSWORD_FORGOT_DESCRIPTION'
        );
    }

    private function handlePasswordReset(Request $request, string $token): Response
    {
        if ($request->method() === self::METHOD_POST && !$this->guard()->validateCsrf($request, 'private_password')) {
            return $this->render('notice', [
                'privatePageTitle' => (string) t('TXT_PRIVATE_PASSWORD_RESET_TITLE', 'Réinitialisation privée'),
                'privateNoticeTitle' => (string) t('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
                'privateNoticeBody' => (string) t('TXT_PRIVATE_ERROR_CSRF', 'Requête invalide'),
            ]);
        }

        return $this->handleSimpleInfo(
            $token,
            'TXT_PRIVATE_PASSWORD_RESET_TITLE',
            'TXT_PRIVATE_PASSWORD_RESET_DESCRIPTION'
        );
    }

    private function handleFiles(string $documentId): Response
    {
        $guard = $this->guard();
        $required = $guard->requireAuthenticated(
            new Request(
                ['REQUEST_URI' => '/'],
                [],
                [],
                [],
                [],
                ''
            ),
            private_portal_url('login')
        );
        if ($required !== null) {
            return $required;
        }

        return $this->handleSimpleInfo(
            $documentId,
            'TXT_PRIVATE_FILES_TITLE',
            'TXT_PRIVATE_FILES_DESCRIPTION'
        );
    }

    private function handleSimpleInfo(string $token, string $titleKey, string $descKey): Response
    {
        return $this->render('notice', [
            'privatePageTitle' => (string) t($titleKey, 'Information privée'),
            'privateNoticeTitle' => (string) t($titleKey, 'Information privée'),
            'privateNoticeBody' => (string) t($descKey, 'Fonctionnalité en attente de mise en œuvre.'),
            'privateNoticeToken' => $token,
        ]);
    }

    private function handleNotFound(): Response
    {
        return $this->withPrivateHeaders(new Response(404, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Not Found'));
    }

    private function render(string $template, array $viewModel = []): Response
    {
        $contentTemplate = ROOT_PATH . '/templates/private/' . $template . '.php';
        if (!is_file($contentTemplate)) {
            return new Response(500, ['Content-Type' => 'text/plain; charset=UTF-8'], 'Template introuvable: ' . $template);
        }

        $privatePortalEnabled = private_portal_enabled();
        $privatePageTitle = is_string($viewModel['privatePageTitle'] ?? null)
            ? $viewModel['privatePageTitle']
            : 'Espace privé';
        $privatePageDescription = is_string($viewModel['privatePageDescription'] ?? null)
            ? $viewModel['privatePageDescription']
            : '';
        $privateNoticeTitle = is_string($viewModel['privateNoticeTitle'] ?? null)
            ? $viewModel['privateNoticeTitle']
            : '';
        $privateNoticeBody = is_string($viewModel['privateNoticeBody'] ?? null)
            ? $viewModel['privateNoticeBody']
            : '';
        $privateNoticeToken = is_string($viewModel['privateNoticeToken'] ?? null)
            ? $viewModel['privateNoticeToken']
            : '';
        $identifier = is_string($viewModel['identifier'] ?? null)
            ? $viewModel['identifier']
            : '';
        $errorKey = is_string($viewModel['errorKey'] ?? null)
            ? $viewModel['errorKey']
            : null;
        $csrfToken = is_string($viewModel['csrfToken'] ?? null)
            ? $viewModel['csrfToken']
            : '';
        $privateUserIdentifier = is_string($viewModel['privateUserIdentifier'] ?? null)
            ? $viewModel['privateUserIdentifier']
            : '';
        $privateModules = is_array($viewModel['privateModules'] ?? null) ? $viewModel['privateModules'] : [];
        $privatePasswordForgotUrl = is_string($viewModel['privatePasswordForgotUrl'] ?? null)
            ? $viewModel['privatePasswordForgotUrl']
            : private_portal_url('password_forgot');
        $privateDashboardLogoutUrl = is_string($viewModel['privateDashboardLogoutUrl'] ?? null)
            ? $viewModel['privateDashboardLogoutUrl']
            : private_portal_url('logout');
        $privateLogoutCsrfToken = is_string($viewModel['privateLogoutCsrfToken'] ?? null)
            ? $viewModel['privateLogoutCsrfToken']
            : '';

        ob_start();
        include $contentTemplate;
        $privateContent = (string) ob_get_clean();

        ob_start();
        $privateIsAuthenticated = $this->auth->isAuthenticated();
        $privateDashboardUrl = private_portal_url('dashboard');
        $privateLoginUrl = private_portal_url('login');
        include ROOT_PATH . '/templates/private/layout.php';
        $body = (string) ob_get_clean();

        return $this->withPrivateHeaders(new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $body));
    }

    private function redirect(string $url): Response
    {
        return $this->withPrivateHeaders(new Response(302, ['Location' => $url], ''));
    }

    private function withPrivateHeaders(Response $response): Response
    {
        $response->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';

        if (!isset($response->headers['Content-Type'])) {
            $response->headers['Content-Type'] = 'text/html; charset=UTF-8';
        }

        return $response;
    }

    private function guard(): PrivatePortalSecurityGuard
    {
        return $this->securityGuard ?? new PrivatePortalSecurityGuard($this->auth, $this->eventLogger);
    }

    private function logEvent(string $event, array $context): void
    {
        $logger = $this->eventLogger ?? (function_exists('app_event_logger') ? app_event_logger() : null);
        if ($logger === null) {
            return;
        }

        $logger->security($event, $context, 'warning');
    }
}
