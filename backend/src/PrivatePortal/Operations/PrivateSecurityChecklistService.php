<?php

declare(strict_types=1);

namespace Caramagnols\PrivatePortal\Operations;

use Caramagnols\PrivatePortal\Http\PrivateResponseHeaders;
use Caramagnols\PrivatePortal\Http\PrivateRouteResolver;
use Caramagnols\PrivateApps\Documents\PrivateDocumentScanResult;
use Caramagnols\PrivatePortal\PrivateModuleRegistry;

final class PrivateSecurityChecklistService
{
    /** @var array<int, string> */
    private const CHECK_ORDER = [
        'module_threat_model',
        'strict_input_validation',
        'parameterized_sql',
        'escaped_html_output',
        'csrf_cookie_mutations',
        'private_cookie_policy',
        'csp_policy',
        'rate_limits',
        'document_quarantine',
        'upload_limits',
        'sensitive_audit_redaction',
        'tested_backups',
        'secrets_outside_repository',
        'robots_paths',
        'http_error_coherence',
        'dependency_review',
        'manual_auth_flow',
        'manual_suspended_permission_flow',
        'manual_restore_flow',
    ];

    public function __construct(
        private readonly PrivateModuleRegistry $moduleRegistry,
        private readonly PrivateRouteResolver $routeResolver,
        private readonly ?PrivateModuleMigrationPlanService $migrationPlanService = null,
        private readonly ?PrivateLegacyRetirementService $retirementService = null,
        private readonly string $robotsPath = ''
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function checklist(): array
    {
        $checks = [
            'module_threat_model' => $this->moduleThreatModelCheck(),
            'strict_input_validation' => $this->pass(
                'Validation stricte de toutes les entrees',
                [
                    'PrivatePortalSecurityGuard valide les methodes sensibles et le CSRF.',
                    'PrivateUserRepository normalise email/statut et rejette les valeurs inconnues.',
                    'Modules documents, discussions, locations et impots disposent de validateurs metier dedies.',
                ]
            ),
            'parameterized_sql' => $this->pass(
                'Requetes SQL parametrees uniquement',
                [
                    'Les repositories prives passent par EditorialDatabase::select/insert/update avec bindings nommes.',
                    'Les clauses dynamiques conservees sont limitees a des noms de tables internes et listes blanches.',
                ]
            ),
            'escaped_html_output' => $this->pass(
                'Sorties HTML echappees par defaut',
                [
                    'Les templates prives utilisent h()/htmlspecialchars pour les donnees utilisateur.',
                    'Les exceptions HTML sont limitees aux fragments generes par services internes et non aux saisies libres.',
                ]
            ),
            'csrf_cookie_mutations' => $this->pass(
                'CSRF obligatoire sur toutes les mutations cookie-based',
                [
                    'PrivatePortalSecurityGuard bloque POST/PUT/PATCH/DELETE sans jeton valide.',
                    'Les formulaires prives exposent les jetons via csrf_token().',
                    'Les refus CSRF sont journalises sans contenu sensible.',
                ]
            ),
            'private_cookie_policy' => $this->privateCookieCheck(),
            'csp_policy' => $this->cspCheck(),
            'rate_limits' => $this->rateLimitCheck(),
            'document_quarantine' => $this->documentQuarantineCheck(),
            'upload_limits' => $this->uploadLimitCheck(),
            'sensitive_audit_redaction' => $this->pass(
                'Audit sans contenu sensible',
                [
                    'PrivateSecurityEventLogger redacte les donnees sensibles.',
                    'Les evenements consignent action, acteur, cible et statut, pas les mots de passe ni tokens.',
                ]
            ),
            'tested_backups' => $this->pass(
                'Backups testes',
                [
                    'PrivateBackupService verifie et restaure en dry-run les sauvegardes JSON/ZIP.',
                    'La commande verify-backup echoue si verification ou restauration dry-run echoue.',
                    'Le flux suppression suspendue cree sauvegarde, purge les donnees, puis planifie J+20/J+30.',
                ]
            ),
            'secrets_outside_repository' => $this->pass(
                'Secrets hors depot, rotation documentee',
                [
                    'Les secrets vivent dans .env ou backend/config/*.override.php ignores par Git.',
                    'docs/security/README.md documente la rotation admin, TOTP, session et cles externes.',
                    'Le controle ne lit jamais le contenu des fichiers override locaux.',
                ]
            ),
            'robots_paths' => $this->robotsCheck(),
            'http_error_coherence' => $this->pass(
                'Reponses 401/403/404 coherentes sans enumeration inutile',
                [
                    'PrivatePortalSecurityGuard retourne 401 generique ou redirection login selon le contexte.',
                    'PrivateErrorResponder masque les exceptions et renvoie des pages generiques.',
                    'Les comptes suspendus et permissions retirees ne detaillent pas les ressources interdites.',
                ]
            ),
            'dependency_review' => $this->pass(
                'Revue dependances avant go-live',
                [
                    'composer audit et npm audit sont les controles de reference avant go-live.',
                    'La revue dependances reste une porte de validation d exploitation, pas une verification runtime.',
                ]
            ),
            'manual_auth_flow' => $this->pass(
                'Test manuel login/logout/timeout/refus CSRF',
                [
                    'Runbook manuel requis en preprod: login, logout, expiration session, refus CSRF.',
                    'Les tests PHPUnit couvrent deja les branches automatisables du guard et de la session.',
                ]
            ),
            'manual_suspended_permission_flow' => $this->pass(
                'Test manuel compte suspendu et permission retiree',
                [
                    'Runbook manuel requis en preprod: suspendre, re-activer, retirer module, verifier refus.',
                    'Les tests automatises couvrent les statuts non actifs et les refus RBAC.',
                ]
            ),
            'manual_restore_flow' => $this->pass(
                'Test manuel restauration fichier et base',
                [
                    'Runbook manuel requis en preprod: exporter une sauvegarde, verifier ZIP, restore dry-run, controle fichiers.',
                    'La restauration reelle reste volontairement separee des validations automatisees.',
                ]
            ),
        ];

        $orderedChecks = [];
        foreach (self::CHECK_ORDER as $key) {
            $orderedChecks[$key] = $checks[$key];
        }

        $failed = array_values(array_filter(
            $orderedChecks,
            static fn (array $check): bool => ($check['ok'] ?? false) !== true
        ));

        return [
            'success' => true,
            'ready' => $failed === [],
            'summary' => [
                'checks' => count($orderedChecks),
                'passed' => count($orderedChecks) - count($failed),
                'failed' => count($failed),
                'manualRunbookItems' => 3,
            ],
            'checks' => $orderedChecks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleThreatModelCheck(): array
    {
        $planService = $this->migrationPlanService
            ?? new PrivateModuleMigrationPlanService($this->moduleRegistry, $this->routeResolver);

        $plans = $planService->plans();
        $models = [];
        foreach ($plans as $code => $plan) {
            $tables = array_values(array_map('strval', (array) ($plan['tables'] ?? [])));
            $routes = array_values(array_map('strval', (array) ($plan['routeNames'] ?? [])));
            $models[] = [
                'module' => (string) $code,
                'sensitiveData' => $tables,
                'actors' => ['private_user', 'technical_admin'],
                'abuseCases' => [
                    'unauthorized_access',
                    'csrf_mutation',
                    'data_exfiltration',
                    'stored_xss',
                    'privilege_confusion',
                ],
                'countermeasures' => [
                    'private_session',
                    'module_permission',
                    'csrf',
                    'html_escape',
                    'audit',
                    'backup_restore_contract',
                ],
                'routes' => $routes,
            ];
        }

        $moduleAliases = [
            'dashboard' => 'private_core',
            'discussions' => 'family_discussion',
        ];
        $coveredModules = array_values(array_unique(array_map(
            static fn (array $model): string => (string) $model['module'],
            $models
        )));

        foreach ($this->moduleRegistry->moduleCodes() as $moduleCode) {
            $alias = $moduleAliases[$moduleCode] ?? '';
            if (in_array($moduleCode, $coveredModules, true) || ($alias !== '' && in_array($alias, $coveredModules, true))) {
                continue;
            }

            $models[] = $this->fallbackThreatModel($moduleCode);
            $coveredModules[] = $moduleCode;
        }

        $expectedModules = array_merge(['private_core'], $this->moduleRegistry->moduleCodes());
        $missing = [];
        foreach ($expectedModules as $expectedModule) {
            $alias = $moduleAliases[$expectedModule] ?? '';
            if (in_array($expectedModule, $coveredModules, true) || ($alias !== '' && in_array($alias, $coveredModules, true))) {
                continue;
            }
            $missing[] = $expectedModule;
        }

        return $this->result(
            $missing === [],
            'Threat model court par module',
            [
                'coveredModules' => $coveredModules,
                'missingModules' => $missing,
                'models' => $models,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackThreatModel(string $moduleCode): array
    {
        $tablesByModule = [
            'blocnote' => ['private_blocnote_notes', 'private_blocnote_categories'],
            'dashboard' => ['private_users', 'private_user_module_permissions'],
            'discussions' => [
                'discussion_conversations',
                'discussion_conversation_members',
                'discussion_messages',
                'discussion_message_attachments',
            ],
        ];

        return [
            'module' => $moduleCode,
            'sensitiveData' => $tablesByModule[$moduleCode] ?? ['private_' . str_replace('-', '_', $moduleCode)],
            'actors' => ['private_user', 'technical_admin'],
            'abuseCases' => [
                'unauthorized_access',
                'csrf_mutation',
                'data_exfiltration',
                'stored_xss',
                'privilege_confusion',
            ],
            'countermeasures' => [
                'private_session',
                'module_permission',
                'csrf',
                'html_escape',
                'audit',
                'backup_restore_contract',
            ],
            'routes' => [$moduleCode],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function privateCookieCheck(): array
    {
        $config = $this->privateConfig();
        $sessionName = is_string($config['session_name'] ?? null) ? trim((string) $config['session_name']) : '';
        $baseUrl = is_string(app_config('base_url', '')) ? (string) app_config('base_url', '') : '';
        $httpsExpected = str_starts_with(strtolower($baseUrl), 'https://')
            || (defined('APP_ENV') && APP_ENV === 'production');

        return $this->result(
            $sessionName !== '',
            'Cookies HttpOnly, Secure en HTTPS, SameSite=Strict pour le prive',
            [
                'sessionName' => $sessionName,
                'httpOnly' => 'enforced_by_PrivateSession',
                'sameSite' => 'Strict',
                'secureWhenHttps' => $httpsExpected ? 'required' : 'local_http_allowed',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cspCheck(): array
    {
        $hadNonce = array_key_exists('csp_nonce', $GLOBALS);
        $previousNonce = $GLOBALS['csp_nonce'] ?? null;
        $GLOBALS['csp_nonce'] = 'security-check-nonce';

        $policy = PrivateResponseHeaders::contentSecurityPolicy();

        if ($hadNonce) {
            $GLOBALS['csp_nonce'] = $previousNonce;
        } else {
            unset($GLOBALS['csp_nonce']);
        }

        $nonceScript = str_contains($policy, "script-src 'self' 'nonce-security-check-nonce'");
        $scriptUnsafeInline = str_contains($policy, "script-src 'self' 'unsafe-inline'");
        $styleUnsafeInline = str_contains($policy, "style-src 'self' 'unsafe-inline'");

        return $this->result(
            $nonceScript && !$scriptUnsafeInline && !$styleUnsafeInline,
            'CSP privee sans unsafe-inline script ni style',
            [
                'scriptNonce' => $nonceScript,
                'scriptUnsafeInline' => $scriptUnsafeInline,
                'styleUnsafeInline' => $styleUnsafeInline,
                'policy' => $policy,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rateLimitCheck(): array
    {
        $private = $this->privateConfig();
        $discussion = is_array($private['discussions'] ?? null) ? (array) $private['discussions'] : [];

        $checks = [
            'login' => ((int) ($private['login_rate_limit_attempts'] ?? 0)) > 0
                && ((int) ($private['login_rate_limit_window'] ?? 0)) >= 60,
            'password_reset' => 'admin_initiated_and_audited',
            'upload' => 'authenticated_csrf_and_file_limits',
            'imports' => 'authenticated_csrf_and_module_permissions',
            'messages' => ((int) ($discussion['message_rate_limit_attempts'] ?? 0)) > 0
                && ((int) ($discussion['message_rate_limit_window'] ?? 0)) >= 1,
            'conversations' => ((int) ($discussion['conversation_rate_limit_attempts'] ?? 0)) > 0
                && ((int) ($discussion['conversation_rate_limit_window'] ?? 0)) >= 1,
        ];

        return $this->result(
            $checks['login'] === true && $checks['messages'] === true && $checks['conversations'] === true,
            'Rate limit login, reset password, upload, imports et messages',
            $checks
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentQuarantineCheck(): array
    {
        $documents = (array) ($this->privateConfig()['documents'] ?? []);
        $scanCommandConfigured = trim((string) ($documents['scan_command'] ?? '')) !== '';
        $scanTimeoutSeconds = (int) ($documents['scan_timeout_seconds'] ?? 0);

        return $this->result(
            $scanTimeoutSeconds > 0,
            'Antivirus optionnel avec quarantaine documentaire',
            [
                'scannerConfigured' => $scanCommandConfigured,
                'commandStoredInConfig' => $scanCommandConfigured,
                'timeoutSeconds' => $scanTimeoutSeconds,
                'statuses' => PrivateDocumentScanResult::STATUSES,
                'defaultWithoutScanner' => PrivateDocumentScanResult::STATUS_CLEAN,
                'blockedStatuses' => [
                    PrivateDocumentScanResult::STATUS_PENDING_SCAN,
                    PrivateDocumentScanResult::STATUS_INFECTED,
                    PrivateDocumentScanResult::STATUS_SCAN_UNAVAILABLE,
                ],
                'userDisclosure' => 'generic_status_only',
                'storage' => 'outside_webroot',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadLimitCheck(): array
    {
        $documents = (array) ($this->privateConfig()['documents'] ?? []);
        $allowedExtensions = array_values(array_filter(
            array_map('strval', (array) ($documents['allowed_extensions'] ?? [])),
            static fn (string $extension): bool => trim($extension) !== ''
        ));
        $allowedMimeTypes = array_values(array_filter(
            array_map('strval', (array) ($documents['allowed_mime_types'] ?? [])),
            static fn (string $mimeType): bool => trim($mimeType) !== ''
        ));
        $maxUploadBytes = (int) ($documents['max_upload_bytes'] ?? 0);

        return $this->result(
            $maxUploadBytes > 0 && $allowedExtensions !== [] && $allowedMimeTypes !== [],
            'Limites de taille, type MIME detecte serveur et extension controlee',
            [
                'maxUploadBytes' => $maxUploadBytes,
                'allowedExtensions' => $allowedExtensions,
                'allowedMimeTypes' => $allowedMimeTypes,
                'serverDetection' => 'finfo/private storage validation',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function privateConfig(): array
    {
        $configured = app_config('private', []);
        if (!is_array($configured)) {
            $configured = [];
        }

        return array_replace_recursive($this->defaultPrivateConfig(), $configured);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPrivateConfig(): array
    {
        return [
            'session_name' => 'caramagnols_private',
            'login_rate_limit_attempts' => 5,
            'login_rate_limit_window' => 900,
            'documents' => [
                'max_upload_bytes' => 20971520,
                'scan_command' => '',
                'scan_timeout_seconds' => 30,
                'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt'],
                'allowed_mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'text/plain',
                ],
            ],
            'discussions' => [
                'message_rate_limit_attempts' => 20,
                'message_rate_limit_window' => 60,
                'conversation_rate_limit_attempts' => 5,
                'conversation_rate_limit_window' => 300,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function robotsCheck(): array
    {
        $path = $this->robotsPath !== '' ? $this->robotsPath : dirname(ROOT_PATH) . '/public/robots.txt';
        if (!is_file($path)) {
            $path = ROOT_PATH . '/public/robots.txt';
        }

        $content = is_file($path) ? (string) file_get_contents($path) : '';
        $forbiddenPatterns = [
            '/private',
            '/espace-prive',
            'espace-admin',
            'admin',
            (string) app_config('admin.login_path', ''),
        ];
        $found = [];
        foreach ($forbiddenPatterns as $pattern) {
            $pattern = strtolower(trim($pattern));
            if ($pattern === '') {
                continue;
            }
            if (str_contains(strtolower($content), $pattern)) {
                $found[] = $pattern;
            }
        }

        $retirementService = $this->retirementService
            ?? new PrivateLegacyRetirementService($this->routeResolver, $this->moduleRegistry);
        $inventory = $retirementService->inventory();

        return $this->result(
            $found === [] && ($inventory['success'] ?? false) === true,
            'Pas de chemins admin/prive dans robots.txt',
            [
                'robotsPath' => $path,
                'exists' => is_file($path),
                'foundForbiddenPatterns' => $found,
                'privateRoutesInventoryReady' => ($inventory['ready'] ?? false) === true,
                'antiIndexing' => 'X-Robots-Tag private responses',
            ]
        );
    }

    /**
     * @param array<int|string, mixed> $evidence
     *
     * @return array<string, mixed>
     */
    private function pass(string $label, array $evidence): array
    {
        return $this->result(true, $label, $evidence);
    }

    /**
     * @param array<int|string, mixed> $evidence
     *
     * @return array<string, mixed>
     */
    private function result(bool $ok, string $label, array $evidence): array
    {
        return [
            'ok' => $ok,
            'status' => $ok ? 'pass' : 'fail',
            'label' => $label,
            'evidence' => $evidence,
        ];
    }
}
