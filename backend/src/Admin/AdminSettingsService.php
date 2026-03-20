<?php

declare(strict_types=1);

namespace Caramagnols\Admin;

use Caramagnols\Admin\Settings\AdminTranslationSettingsManager;
use Caramagnols\Logging\AppEventLogger;
use Caramagnols\Social\InstagramFeedService;

final class AdminSettingsService
{
    private AppEventLogger $eventLogger;
    private ?InstagramFeedService $instagramFeedService;
    private AdminTranslationSettingsManager $translationSettingsManager;

    public function __construct(
        private readonly string $databaseOverridePath = ROOT_PATH . '/config/database.override.php',
        private readonly string $adminOverridePath = ROOT_PATH . '/config/admin.override.php',
        ?AppEventLogger $eventLogger = null,
        private readonly string $siteOverridePath = ROOT_PATH . '/config/site.override.php',
        ?InstagramFeedService $instagramFeedService = null,
        ?AdminTranslationSettingsManager $translationSettingsManager = null
    ) {
        $this->eventLogger = $eventLogger ?? app_event_logger();
        $this->instagramFeedService = $instagramFeedService;
        $this->translationSettingsManager = $translationSettingsManager
            ?? new AdminTranslationSettingsManager((string) app_config('default_lang', 'fr'));
    }

    /**
     * @return array<string, mixed>
     */
    public function viewModel(): array
    {
        return $this->buildViewModel(
            [
                'host' => (string) app_config('database.host', '127.0.0.1'),
                'port' => (string) ((int) app_config('database.port', 3306)),
                'name' => (string) app_config('database.name', ''),
                'user' => (string) app_config('database.user', ''),
                'password' => '',
                'prefix' => (string) app_config('database_prefix', 'car_'),
            ],
            [
                'identifier' => $this->configuredAdminIdentifier(),
                'password' => '',
                'allowedIps' => implode(', ', $this->configuredAdminAllowedIps()),
                'totpEnabled' => $this->configuredAdminTotpEnabled(),
                'totpSecret' => '',
                'totpSecretFallback' => $this->configuredAdminTotpSecret(),
                'inactivityTimeoutSeconds' => (string) $this->configuredAdminInactivityTimeoutSeconds(),
                'reauthTimeoutSeconds' => (string) $this->configuredAdminReauthTimeoutSeconds(),
            ],
            $this->configuredUrlSettings(),
            [
                'metadataHtml' => $this->configuredHeadMetadataHtml(),
            ],
            $this->configuredTarteaucitronSettings(),
            $this->configuredDiscussionSettings(),
            $this->configuredInstagramSettings(),
            $this->configuredTranslationSettings()
        );
    }

    /**
     * @return array{languages: array<int, string>, textByLanguage: array<string, string>}
     */
    private function configuredTranslationSettings(): array
    {
        $availableLanguages = function_exists('site_available_languages')
            ? site_available_languages()
            : [(string) app_config('default_lang', 'fr')];

        return $this->translationSettingsManager->configured(
            app_config('site.i18n_overrides', []),
            is_array($availableLanguages) ? $availableLanguages : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function configuredTarteaucitronSettings(): array
    {
        $normalizedUserConfig = $this->normalizeTarteaucitronUserConfigJson(
            app_config('site.tarteaucitron.user_config_json', '{}')
        );

        return [
            'enabled' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.enabled', true), true),
            'privacyUrl' => normalize_public_route((string) app_config('site.tarteaucitron.privacy_url', '/')) ?? '/',
            'orientation' => trim((string) app_config('site.tarteaucitron.orientation', 'bottom')),
            'iconPosition' => trim((string) app_config('site.tarteaucitron.icon_position', 'BottomRight')),
            'showIcon' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.show_icon', true), true),
            'showAlertSmall' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.show_alert_small', true), true),
            'highPrivacy' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.high_privacy', true), true),
            'acceptAllCta' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.accept_all_cta', true), true),
            'denyAllCta' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.deny_all_cta', true), true),
            'mandatory' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.mandatory', true), true),
            'googleConsentMode' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.google_consent_mode', true), true),
            'bingConsentMode' => $this->normalizeBooleanValue(app_config('site.tarteaucitron.bing_consent_mode', true), true),
            'userConfigJson' => $normalizedUserConfig['json'],
            'services' => $this->normalizeTarteaucitronServiceKeys(app_config('site.tarteaucitron.services', [])),
        ];
    }

    /**
     * @return array<string, bool|float|int|string>
     */
    private function configuredDiscussionSettings(): array
    {
        return [
            'enabled' => $this->normalizeBooleanValue(app_config('site.discussions.enabled', true), true),
            'requireAccount' => $this->normalizeBooleanValue(app_config('site.discussions.require_account', false), false),
            'rateLimitPerIp' => (int) app_config('site.discussions.rate_limit_per_ip', 6),
            'rateLimitWindow' => (int) app_config('site.discussions.rate_limit_window', 600),
            'globalRateLimitPerIp' => (int) app_config('site.discussions.global_rate_limit_per_ip', 20),
            'globalRateLimitWindow' => (int) app_config('site.discussions.global_rate_limit_window', 3600),
            'minFormFillSeconds' => (int) app_config('site.discussions.min_form_fill_seconds', 3),
            'maxFormAgeSeconds' => (int) app_config('site.discussions.max_form_age_seconds', 7200),
            'honeypotField' => trim((string) app_config('site.discussions.honeypot_field', 'website')),
            'recaptchaEnabled' => $this->normalizeBooleanValue(app_config('site.discussions.recaptcha.enabled', false), false),
            'recaptchaSiteKey' => trim((string) app_config('site.discussions.recaptcha.site_key', '')),
            'recaptchaSecretKey' => trim((string) app_config('site.discussions.recaptcha.secret_key', '')),
            'recaptchaMinimumScore' => (float) app_config('site.discussions.recaptcha.minimum_score', 0.5),
            'recaptchaTimeoutSeconds' => (int) app_config('site.discussions.recaptcha.timeout_seconds', 8),
        ];
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function configuredInstagramSettings(): array
    {
        return [
            'enabled' => $this->normalizeBooleanValue(app_config('site.instagram.enabled', false), false),
            'username' => ltrim(trim((string) app_config('site.instagram.username', '')), '@'),
            'userId' => trim((string) app_config('site.instagram.user_id', '')),
            'accessToken' => trim((string) app_config('site.instagram.access_token', '')),
            'limit' => (int) app_config('site.instagram.limit', 6),
            'rotationIntervalMs' => (int) app_config('site.instagram.rotation_interval_ms', 5500),
            'cacheTtlSeconds' => (int) app_config('site.instagram.cache_ttl_seconds', 1800),
            'timeoutSeconds' => (int) app_config('site.instagram.timeout_seconds', 8),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function configuredUrlSettings(): array
    {
        return [
            'domain' => trim((string) app_config('site.url.domain', '')),
            'sslDomain' => trim((string) app_config('site.url.ssl_domain', '')),
            'basePath' => normalize_public_route((string) app_config('site.url.base_path', app_config('base_url', '/'))) ?? '/',
        ];
    }

    private function postBoolean(array $payload, string $key, bool $fallback): bool
    {
        if (!array_key_exists($key, $payload)) {
            return $fallback;
        }

        $value = $payload[$key];
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeBooleanValue(mixed $value, bool $fallback): bool
    {
        if ($value === null) {
            return $fallback;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) !== 0;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $fallback;
            }

            $normalized = filter_var($trimmed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return (bool) $value;
    }

    /**
     * @param array<string, mixed> $fallback
     * @return array<string, mixed>
     */
    private function tarteaucitronForm(array $payload, array $fallback): array
    {
        return [
            'enabled' => $this->postBoolean($payload, 'enabled', $this->normalizeBooleanValue($fallback['enabled'] ?? true, true)),
            'privacyUrl' => trim((string) ($payload['privacy_url'] ?? ($fallback['privacyUrl'] ?? '/'))),
            'orientation' => trim((string) ($payload['orientation'] ?? ($fallback['orientation'] ?? 'bottom'))),
            'iconPosition' => trim((string) ($payload['icon_position'] ?? ($fallback['iconPosition'] ?? 'BottomRight'))),
            'showIcon' => $this->postBoolean($payload, 'show_icon', $this->normalizeBooleanValue($fallback['showIcon'] ?? true, true)),
            'showAlertSmall' => $this->postBoolean($payload, 'show_alert_small', $this->normalizeBooleanValue($fallback['showAlertSmall'] ?? true, true)),
            'highPrivacy' => $this->postBoolean($payload, 'high_privacy', $this->normalizeBooleanValue($fallback['highPrivacy'] ?? true, true)),
            'acceptAllCta' => $this->postBoolean($payload, 'accept_all_cta', $this->normalizeBooleanValue($fallback['acceptAllCta'] ?? true, true)),
            'denyAllCta' => $this->postBoolean($payload, 'deny_all_cta', $this->normalizeBooleanValue($fallback['denyAllCta'] ?? true, true)),
            'mandatory' => $this->postBoolean($payload, 'mandatory', $this->normalizeBooleanValue($fallback['mandatory'] ?? true, true)),
            'googleConsentMode' => $this->postBoolean($payload, 'google_consent_mode', $this->normalizeBooleanValue($fallback['googleConsentMode'] ?? true, true)),
            'bingConsentMode' => $this->postBoolean($payload, 'bing_consent_mode', $this->normalizeBooleanValue($fallback['bingConsentMode'] ?? true, true)),
            'userConfigJson' => (string) ($payload['user_config_json'] ?? ($fallback['userConfigJson'] ?? '{}')),
            'services' => $this->submittedTarteaucitronServices(
                $payload['services'] ?? ($fallback['services'] ?? [])
            ),
        ];
    }

    /**
     * @param array<string, bool|float|int|string> $fallback
     * @return array<string, bool|float|int|string>
     */
    private function discussionsForm(array $payload, array $fallback): array
    {
        return [
            'enabled' => $this->postBoolean($payload, 'enabled', $this->normalizeBooleanValue($fallback['enabled'] ?? true, true)),
            'requireAccount' => $this->postBoolean($payload, 'require_account', $this->normalizeBooleanValue($fallback['requireAccount'] ?? false, false)),
            'rateLimitPerIp' => (string) ($payload['rate_limit_per_ip'] ?? ($fallback['rateLimitPerIp'] ?? 6)),
            'rateLimitWindow' => (string) ($payload['rate_limit_window'] ?? ($fallback['rateLimitWindow'] ?? 600)),
            'globalRateLimitPerIp' => (string) ($payload['global_rate_limit_per_ip'] ?? ($fallback['globalRateLimitPerIp'] ?? 20)),
            'globalRateLimitWindow' => (string) ($payload['global_rate_limit_window'] ?? ($fallback['globalRateLimitWindow'] ?? 3600)),
            'minFormFillSeconds' => (string) ($payload['min_form_fill_seconds'] ?? ($fallback['minFormFillSeconds'] ?? 3)),
            'maxFormAgeSeconds' => (string) ($payload['max_form_age_seconds'] ?? ($fallback['maxFormAgeSeconds'] ?? 7200)),
            'honeypotField' => trim((string) ($payload['honeypot_field'] ?? ($fallback['honeypotField'] ?? 'website'))),
            'recaptchaEnabled' => $this->postBoolean($payload, 'recaptcha_enabled', $this->normalizeBooleanValue($fallback['recaptchaEnabled'] ?? false, false)),
            'recaptchaSiteKey' => trim((string) ($payload['recaptcha_site_key'] ?? ($fallback['recaptchaSiteKey'] ?? ''))),
            'recaptchaSecretKey' => trim((string) ($payload['recaptcha_secret_key'] ?? '')),
            'recaptchaMinimumScore' => (string) ($payload['recaptcha_minimum_score'] ?? ($fallback['recaptchaMinimumScore'] ?? 0.5)),
            'recaptchaTimeoutSeconds' => (string) ($payload['recaptcha_timeout_seconds'] ?? ($fallback['recaptchaTimeoutSeconds'] ?? 8)),
            'recaptchaSecretKeyFallback' => trim((string) ($fallback['recaptchaSecretKey'] ?? '')),
        ];
    }

    /**
     * @param array<string, bool|int|string> $fallback
     * @return array<string, bool|int|string>
     */
    private function instagramForm(array $payload, array $fallback): array
    {
        return [
            'enabled' => $this->postBoolean($payload, 'enabled', $this->normalizeBooleanValue($fallback['enabled'] ?? false, false)),
            'username' => ltrim(trim((string) ($payload['username'] ?? ($fallback['username'] ?? ''))), '@'),
            'userId' => trim((string) ($payload['user_id'] ?? ($fallback['userId'] ?? ''))),
            'accessToken' => trim((string) ($payload['access_token'] ?? '')),
            'limit' => (string) ($payload['limit'] ?? ($fallback['limit'] ?? 6)),
            'rotationIntervalMs' => (string) ($payload['rotation_interval_ms'] ?? ($fallback['rotationIntervalMs'] ?? 5500)),
            'cacheTtlSeconds' => (string) ($payload['cache_ttl_seconds'] ?? ($fallback['cacheTtlSeconds'] ?? 1800)),
            'timeoutSeconds' => (string) ($payload['timeout_seconds'] ?? ($fallback['timeoutSeconds'] ?? 8)),
            'accessTokenFallback' => trim((string) ($fallback['accessToken'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{languages: array<int, string>, textByLanguage: array<string, string>} $fallback
     * @return array{languages: array<int, string>, textByLanguage: array<string, string>}
     */
    private function translationsForm(array $payload, array $fallback): array
    {
        return $this->translationSettingsManager->form($payload, $fallback);
    }

    /**
     * @param array{languages: array<int, string>, textByLanguage: array<string, string>} $translations
     * @return array{data: array<string, array<string, string>>, error: string|null}
     */
    private function normalizeTranslationsConfig(array $translations): array
    {
        return $this->translationSettingsManager->normalizeConfig($translations);
    }

    /**
     * @return array<int, string>
     */
    private function knownTranslationKeys(): array
    {
        return $this->translationSettingsManager->knownKeys();
    }

    /**
     * @param mixed $overrides
     * @return array<string, array<string, string>>
     */
    private function normalizeI18nOverrides(mixed $overrides): array
    {
        return $this->translationSettingsManager->normalizeOverrides($overrides);
    }

    private function countTranslationOverrideLines(string $rawText): int
    {
        return $this->translationSettingsManager->countOverrideLines($rawText);
    }

    /**
     * @param array<string, mixed> $tarteaucitron
     * @return array{data: array<string, mixed>, error: string|null}
     */
    private function normalizeTarteaucitronConfig(array $tarteaucitron): array
    {
        $privacyUrl = normalize_public_route((string) ($tarteaucitron['privacyUrl'] ?? '')) ?? '/';

        $orientation = trim((string) ($tarteaucitron['orientation'] ?? 'bottom'));
        if (!in_array($orientation, ['top', 'bottom', 'middle'], true)) {
            return ['data' => [], 'error' => 'La position de bannière tarteaucitron est invalide.'];
        }

        $iconPosition = trim((string) ($tarteaucitron['iconPosition'] ?? 'BottomRight'));
        if (!in_array($iconPosition, ['BottomRight', 'BottomLeft', 'TopRight', 'TopLeft'], true)) {
            return ['data' => [], 'error' => 'La position de l’icône tarteaucitron est invalide.'];
        }

        $services = $this->validateTarteaucitronServices($tarteaucitron['services'] ?? []);
        if ($services['error'] !== null) {
            return ['data' => [], 'error' => $services['error']];
        }

        $userConfig = $this->normalizeTarteaucitronUserConfigJson($tarteaucitron['userConfigJson'] ?? '{}');
        if ($userConfig['error'] !== null) {
            return ['data' => [], 'error' => $userConfig['error']];
        }

        return [
            'data' => [
                'enabled' => $this->normalizeBooleanValue($tarteaucitron['enabled'] ?? true, true),
                'privacyUrl' => $privacyUrl,
                'orientation' => $orientation,
                'iconPosition' => $iconPosition,
                'showIcon' => $this->normalizeBooleanValue($tarteaucitron['showIcon'] ?? true, true),
                'showAlertSmall' => $this->normalizeBooleanValue($tarteaucitron['showAlertSmall'] ?? true, true),
                'highPrivacy' => $this->normalizeBooleanValue($tarteaucitron['highPrivacy'] ?? true, true),
                'acceptAllCta' => $this->normalizeBooleanValue($tarteaucitron['acceptAllCta'] ?? true, true),
                'denyAllCta' => $this->normalizeBooleanValue($tarteaucitron['denyAllCta'] ?? true, true),
                'mandatory' => $this->normalizeBooleanValue($tarteaucitron['mandatory'] ?? true, true),
                'googleConsentMode' => $this->normalizeBooleanValue($tarteaucitron['googleConsentMode'] ?? true, true),
                'bingConsentMode' => $this->normalizeBooleanValue($tarteaucitron['bingConsentMode'] ?? true, true),
                'userConfigJson' => $userConfig['json'],
                'services' => $services['data'],
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, bool|float|int|string> $discussions
     * @return array{data: array<string, bool|float|int|string>, error: string|null}
     */
    private function normalizeDiscussionsConfig(array $discussions): array
    {
        $rateLimitPerIp = filter_var((string) ($discussions['rateLimitPerIp'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 500],
        ]);
        if ($rateLimitPerIp === false) {
            return ['data' => [], 'error' => 'La limite IP locale doit être un entier entre 1 et 500.'];
        }

        $rateLimitWindow = filter_var((string) ($discussions['rateLimitWindow'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 60, 'max_range' => 86400],
        ]);
        if ($rateLimitWindow === false) {
            return ['data' => [], 'error' => 'La fenêtre locale doit être un entier entre 60 et 86400 secondes.'];
        }

        $globalRateLimitPerIp = filter_var((string) ($discussions['globalRateLimitPerIp'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5000],
        ]);
        if ($globalRateLimitPerIp === false) {
            return ['data' => [], 'error' => 'La limite IP globale doit être un entier entre 1 et 5000.'];
        }

        $globalRateLimitWindow = filter_var((string) ($discussions['globalRateLimitWindow'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 60, 'max_range' => 86400],
        ]);
        if ($globalRateLimitWindow === false) {
            return ['data' => [], 'error' => 'La fenêtre globale doit être un entier entre 60 et 86400 secondes.'];
        }

        $minFormFillSeconds = filter_var((string) ($discussions['minFormFillSeconds'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 120],
        ]);
        if ($minFormFillSeconds === false) {
            return ['data' => [], 'error' => 'Le délai minimum de saisie doit être un entier entre 0 et 120 secondes.'];
        }

        $maxFormAgeSeconds = filter_var((string) ($discussions['maxFormAgeSeconds'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 60, 'max_range' => 86400],
        ]);
        if ($maxFormAgeSeconds === false) {
            return ['data' => [], 'error' => 'La durée de validité du formulaire doit être un entier entre 60 et 86400 secondes.'];
        }

        if ($maxFormAgeSeconds <= $minFormFillSeconds) {
            return ['data' => [], 'error' => 'La durée de validité doit être supérieure au délai minimum de saisie.'];
        }

        $honeypotField = trim((string) ($discussions['honeypotField'] ?? 'website'));
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{1,40}$/', $honeypotField) !== 1) {
            return ['data' => [], 'error' => 'Le nom du champ honeypot est invalide.'];
        }

        $recaptchaMinimumScore = (float) ($discussions['recaptchaMinimumScore'] ?? 0.5);
        if ($recaptchaMinimumScore < 0 || $recaptchaMinimumScore > 1) {
            return ['data' => [], 'error' => 'Le score minimum reCAPTCHA doit être compris entre 0 et 1.'];
        }

        $recaptchaTimeoutSeconds = filter_var((string) ($discussions['recaptchaTimeoutSeconds'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 3, 'max_range' => 20],
        ]);
        if ($recaptchaTimeoutSeconds === false) {
            return ['data' => [], 'error' => 'Le délai reCAPTCHA doit être un entier entre 3 et 20 secondes.'];
        }

        $recaptchaSiteKey = trim((string) ($discussions['recaptchaSiteKey'] ?? ''));
        $recaptchaSecretKey = trim((string) ($discussions['recaptchaSecretKey'] ?? ''));
        $recaptchaSecretKeyFallback = trim((string) ($discussions['recaptchaSecretKeyFallback'] ?? ''));
        if ($recaptchaSecretKey === '') {
            $recaptchaSecretKey = $recaptchaSecretKeyFallback;
        }

        $recaptchaEnabled = (bool) ($discussions['recaptchaEnabled'] ?? false);
        if ($recaptchaEnabled && ($recaptchaSiteKey === '' || $recaptchaSecretKey === '')) {
            return ['data' => [], 'error' => 'Active reCAPTCHA uniquement après avoir renseigné la clé site et la clé secrète.'];
        }

        $requireAccount = (bool) ($discussions['requireAccount'] ?? false);

        return [
            'data' => [
                'enabled' => (bool) ($discussions['enabled'] ?? true),
                'requireAccount' => $requireAccount,
                'rateLimitPerIp' => (int) $rateLimitPerIp,
                'rateLimitWindow' => (int) $rateLimitWindow,
                'globalRateLimitPerIp' => (int) $globalRateLimitPerIp,
                'globalRateLimitWindow' => (int) $globalRateLimitWindow,
                'minFormFillSeconds' => (int) $minFormFillSeconds,
                'maxFormAgeSeconds' => (int) $maxFormAgeSeconds,
                'honeypotField' => $honeypotField,
                'recaptchaEnabled' => $recaptchaEnabled,
                'recaptchaSiteKey' => $recaptchaSiteKey,
                'recaptchaSecretKey' => $recaptchaSecretKey,
                'recaptchaMinimumScore' => $recaptchaMinimumScore,
                'recaptchaTimeoutSeconds' => (int) $recaptchaTimeoutSeconds,
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, bool|int|string> $instagram
     * @return array{data: array<string, bool|int|string>, error: string|null}
     */
    private function normalizeInstagramConfig(array $instagram): array
    {
        $username = ltrim(trim((string) ($instagram['username'] ?? '')), '@');
        if ($username !== '' && preg_match('/^[A-Za-z0-9._]{1,30}$/', $username) !== 1) {
            return ['data' => [], 'error' => 'Le compte Instagram est invalide. Utilise uniquement lettres, chiffres, point et underscore.'];
        }

        $userId = trim((string) ($instagram['userId'] ?? ''));
        if ($userId !== '' && preg_match('/^[0-9]{4,30}$/', $userId) !== 1) {
            return ['data' => [], 'error' => 'Le User ID Instagram doit contenir uniquement des chiffres.'];
        }

        $limit = filter_var((string) ($instagram['limit'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 20],
        ]);
        if ($limit === false) {
            return ['data' => [], 'error' => 'Le nombre de posts Instagram doit être un entier entre 1 et 20.'];
        }

        $rotationIntervalMs = filter_var((string) ($instagram['rotationIntervalMs'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 2500, 'max_range' => 30000],
        ]);
        if ($rotationIntervalMs === false) {
            return ['data' => [], 'error' => 'La rotation Instagram doit être comprise entre 2500 et 30000 ms.'];
        }

        $cacheTtlSeconds = filter_var((string) ($instagram['cacheTtlSeconds'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 60, 'max_range' => 86400],
        ]);
        if ($cacheTtlSeconds === false) {
            return ['data' => [], 'error' => 'Le cache Instagram doit être compris entre 60 et 86400 secondes.'];
        }

        $timeoutSeconds = filter_var((string) ($instagram['timeoutSeconds'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 3, 'max_range' => 20],
        ]);
        if ($timeoutSeconds === false) {
            return ['data' => [], 'error' => 'Le délai d’appel Instagram doit être un entier entre 3 et 20 secondes.'];
        }

        $accessToken = trim((string) ($instagram['accessToken'] ?? ''));
        $accessTokenFallback = trim((string) ($instagram['accessTokenFallback'] ?? ''));
        if ($accessToken === '') {
            $accessToken = $accessTokenFallback;
        }

        $enabled = (bool) ($instagram['enabled'] ?? false);
        if ($enabled && $accessToken === '') {
            return ['data' => [], 'error' => 'Active Instagram uniquement après avoir renseigné le token d’accès.'];
        }

        return [
            'data' => [
                'enabled' => $enabled,
                'username' => $username,
                'userId' => $userId,
                'accessToken' => $accessToken,
                'limit' => (int) $limit,
                'rotationIntervalMs' => (int) $rotationIntervalMs,
                'cacheTtlSeconds' => (int) $cacheTtlSeconds,
                'timeoutSeconds' => (int) $timeoutSeconds,
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string|null, error: string|null, view: array<string, mixed>, adminIdentifier: string|null}
     */
    public function save(array $payload, ?string $actorIdentifier = null): array
    {
        $databasePayload = is_array($payload['database'] ?? null) ? $payload['database'] : [];
        $adminPayload = is_array($payload['admin'] ?? null) ? $payload['admin'] : [];
        $urlPayload = is_array($payload['url'] ?? null) ? $payload['url'] : [];
        $headPayload = is_array($payload['head'] ?? null) ? $payload['head'] : [];
        $tarteaucitronPayload = is_array($payload['tarteaucitron'] ?? null) ? $payload['tarteaucitron'] : [];
        $discussionsPayload = is_array($payload['discussions'] ?? null) ? $payload['discussions'] : [];
        $instagramPayload = is_array($payload['instagram'] ?? null) ? $payload['instagram'] : [];
        $translationsPayload = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
        $configuredUrl = $this->configuredUrlSettings();
        $configuredTarteaucitron = $this->configuredTarteaucitronSettings();
        $configuredDiscussions = $this->configuredDiscussionSettings();
        $configuredInstagram = $this->configuredInstagramSettings();
        $configuredTranslations = $this->configuredTranslationSettings();

        $databaseForm = [
            'host' => trim((string) ($databasePayload['host'] ?? app_config('database.host', '127.0.0.1'))),
            'port' => trim((string) ($databasePayload['port'] ?? app_config('database.port', 3306))),
            'name' => trim((string) ($databasePayload['name'] ?? app_config('database.name', ''))),
            'user' => trim((string) ($databasePayload['user'] ?? app_config('database.user', ''))),
            'password' => (string) ($databasePayload['password'] ?? ''),
            'prefix' => trim((string) ($databasePayload['prefix'] ?? app_config('database_prefix', 'car_'))),
        ];
        $adminForm = [
            'identifier' => trim((string) ($adminPayload['identifier'] ?? $this->configuredAdminIdentifier())),
            'password' => (string) ($adminPayload['password'] ?? ''),
            'allowedIps' => trim((string) ($adminPayload['allowed_ips'] ?? implode(', ', $this->configuredAdminAllowedIps()))),
            'totpEnabled' => $this->postBoolean($adminPayload, 'totp_enabled', $this->configuredAdminTotpEnabled()),
            'totpSecret' => trim((string) ($adminPayload['totp_secret'] ?? '')),
            'totpSecretFallback' => $this->configuredAdminTotpSecret(),
            'inactivityTimeoutSeconds' => trim((string) ($adminPayload['inactivity_timeout_seconds'] ?? $this->configuredAdminInactivityTimeoutSeconds())),
            'reauthTimeoutSeconds' => trim((string) ($adminPayload['reauth_timeout_seconds'] ?? $this->configuredAdminReauthTimeoutSeconds())),
        ];
        $urlForm = [
            'domain' => trim((string) ($urlPayload['domain'] ?? ($configuredUrl['domain'] ?? ''))),
            'sslDomain' => trim((string) ($urlPayload['ssl_domain'] ?? ($configuredUrl['sslDomain'] ?? ''))),
            'basePath' => trim((string) ($urlPayload['base_path'] ?? ($configuredUrl['basePath'] ?? '/'))),
        ];
        $headForm = [
            'metadataHtml' => (string) ($headPayload['metadata_html'] ?? $this->configuredHeadMetadataHtml()),
        ];
        $tarteaucitronForm = $this->tarteaucitronForm($tarteaucitronPayload, $configuredTarteaucitron);
        $discussionsForm = $this->discussionsForm($discussionsPayload, $configuredDiscussions);
        $instagramForm = $this->instagramForm($instagramPayload, $configuredInstagram);
        $translationsForm = $this->translationsForm($translationsPayload, $configuredTranslations);

        $databaseConfig = $this->normalizeDatabaseConfig($databaseForm);
        if ($databaseConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $databaseConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $adminConfig = $this->normalizeAdminConfig($adminForm);
        if ($adminConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $adminConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $urlConfig = $this->normalizeUrlConfig($urlForm);
        if ($urlConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $urlConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $headConfig = $this->normalizeHeadConfig($headForm);
        if ($headConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $headConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $tarteaucitronConfig = $this->normalizeTarteaucitronConfig($tarteaucitronForm);
        if ($tarteaucitronConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $tarteaucitronConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $discussionsConfig = $this->normalizeDiscussionsConfig($discussionsForm);
        if ($discussionsConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $discussionsConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $instagramConfig = $this->normalizeInstagramConfig($instagramForm);
        if ($instagramConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $instagramConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $translationsConfig = $this->normalizeTranslationsConfig($translationsForm);
        if ($translationsConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $translationsConfig['error'],
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $previousDatabase = [
            'host' => (string) app_config('database.host', ''),
            'port' => (int) app_config('database.port', 3306),
            'name' => (string) app_config('database.name', ''),
            'user' => (string) app_config('database.user', ''),
            'password' => (string) app_config('database.password', ''),
            'prefix' => (string) app_config('database_prefix', 'car_'),
        ];
        $previousAdminIdentifier = $this->configuredAdminIdentifier();
        $previousAdminHash = (string) app_config('admin.password_hash', '');
        $previousAdminAllowedIps = $this->configuredAdminAllowedIps();
        $previousAdminTotpEnabled = $this->configuredAdminTotpEnabled();
        $previousAdminTotpSecret = $this->configuredAdminTotpSecret();
        $previousAdminInactivityTimeout = $this->configuredAdminInactivityTimeoutSeconds();
        $previousAdminReauthTimeout = $this->configuredAdminReauthTimeoutSeconds();
        $previousUrl = $configuredUrl;
        $previousHeadMetadataHtml = $this->configuredHeadMetadataHtml();
        $previousTarteaucitron = $configuredTarteaucitron;
        $previousDiscussions = $configuredDiscussions;
        $previousInstagram = $configuredInstagram;
        $previousTranslations = $this->normalizeI18nOverrides(app_config('site.i18n_overrides', []));

        $databasePassword = $databaseConfig['data']['password'] !== ''
            ? $databaseConfig['data']['password']
            : $previousDatabase['password'];
        $adminPasswordHash = $adminConfig['data']['password'] !== ''
            ? password_hash($adminConfig['data']['password'], PASSWORD_DEFAULT)
            : $previousAdminHash;

        if ($adminPasswordHash === '') {
            return [
                'success' => false,
                'message' => null,
                'error' => 'Le mot de passe admin est obligatoire pour la première configuration.',
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        $databaseOverride = [
            'host' => $databaseConfig['data']['host'],
            'port' => $databaseConfig['data']['port'],
            'name' => $databaseConfig['data']['name'],
            'user' => $databaseConfig['data']['user'],
            'password' => $databasePassword,
            'charset' => (string) app_config('database.charset', 'utf8mb4'),
            'prefix' => $databaseConfig['data']['prefix'],
        ];
        $adminOverride = [
            'identifier' => $adminConfig['data']['identifier'],
            'password_hash' => $adminPasswordHash,
            'allowed_ips' => $adminConfig['data']['allowedIps'],
            'totp_enabled' => (bool) $adminConfig['data']['totpEnabled'],
            'totp_secret' => (string) $adminConfig['data']['totpSecret'],
            'inactivity_timeout_seconds' => (int) $adminConfig['data']['inactivityTimeoutSeconds'],
            'reauth_timeout_seconds' => (int) $adminConfig['data']['reauthTimeoutSeconds'],
        ];
        $siteOverride = [
            'url' => [
                'domain' => (string) $urlConfig['data']['domain'],
                'ssl_domain' => (string) $urlConfig['data']['sslDomain'],
                'base_path' => (string) $urlConfig['data']['basePath'],
            ],
            'head_metadata_html' => $headConfig['data']['metadataHtml'],
            'tarteaucitron' => [
                'enabled' => (bool) $tarteaucitronConfig['data']['enabled'],
                'privacy_url' => (string) $tarteaucitronConfig['data']['privacyUrl'],
                'orientation' => (string) $tarteaucitronConfig['data']['orientation'],
                'icon_position' => (string) $tarteaucitronConfig['data']['iconPosition'],
                'show_icon' => (bool) $tarteaucitronConfig['data']['showIcon'],
                'show_alert_small' => (bool) $tarteaucitronConfig['data']['showAlertSmall'],
                'high_privacy' => (bool) $tarteaucitronConfig['data']['highPrivacy'],
                'accept_all_cta' => (bool) $tarteaucitronConfig['data']['acceptAllCta'],
                'deny_all_cta' => (bool) $tarteaucitronConfig['data']['denyAllCta'],
                'mandatory' => (bool) $tarteaucitronConfig['data']['mandatory'],
                'google_consent_mode' => (bool) $tarteaucitronConfig['data']['googleConsentMode'],
                'bing_consent_mode' => (bool) $tarteaucitronConfig['data']['bingConsentMode'],
                'user_config_json' => (string) $tarteaucitronConfig['data']['userConfigJson'],
                'services' => $tarteaucitronConfig['data']['services'],
            ],
            'discussions' => [
                'enabled' => (bool) $discussionsConfig['data']['enabled'],
                'require_account' => (bool) $discussionsConfig['data']['requireAccount'],
                'rate_limit_per_ip' => (int) $discussionsConfig['data']['rateLimitPerIp'],
                'rate_limit_window' => (int) $discussionsConfig['data']['rateLimitWindow'],
                'global_rate_limit_per_ip' => (int) $discussionsConfig['data']['globalRateLimitPerIp'],
                'global_rate_limit_window' => (int) $discussionsConfig['data']['globalRateLimitWindow'],
                'min_form_fill_seconds' => (int) $discussionsConfig['data']['minFormFillSeconds'],
                'max_form_age_seconds' => (int) $discussionsConfig['data']['maxFormAgeSeconds'],
                'honeypot_field' => (string) $discussionsConfig['data']['honeypotField'],
                'recaptcha' => [
                    'enabled' => (bool) $discussionsConfig['data']['recaptchaEnabled'],
                    'site_key' => (string) $discussionsConfig['data']['recaptchaSiteKey'],
                    'secret_key' => (string) $discussionsConfig['data']['recaptchaSecretKey'],
                    'minimum_score' => (float) $discussionsConfig['data']['recaptchaMinimumScore'],
                    'timeout_seconds' => (int) $discussionsConfig['data']['recaptchaTimeoutSeconds'],
                ],
            ],
            'instagram' => [
                'enabled' => (bool) $instagramConfig['data']['enabled'],
                'username' => (string) $instagramConfig['data']['username'],
                'user_id' => (string) $instagramConfig['data']['userId'],
                'access_token' => (string) $instagramConfig['data']['accessToken'],
                'limit' => (int) $instagramConfig['data']['limit'],
                'rotation_interval_ms' => (int) $instagramConfig['data']['rotationIntervalMs'],
                'cache_ttl_seconds' => (int) $instagramConfig['data']['cacheTtlSeconds'],
                'timeout_seconds' => (int) $instagramConfig['data']['timeoutSeconds'],
            ],
            'i18n_overrides' => $translationsConfig['data'],
        ];

        try {
            $this->writePhpArrayFile($this->databaseOverridePath, $databaseOverride);
            $this->writePhpArrayFile($this->adminOverridePath, $adminOverride);
            $this->writePhpArrayFile($this->siteOverridePath, $siteOverride);
            $this->applyRuntimeConfig($databaseOverride, $adminOverride, $siteOverride);
        } catch (\Throwable $exception) {
            $this->eventLogger->security(
                'admin.settings.save_failed',
                [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'exception' => $exception->getMessage(),
                ],
                'error'
            );

            return [
                'success' => false,
                'message' => null,
                'error' => 'Impossible de sauvegarder les paramètres d’exploitation.',
                'view' => $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm),
                'adminIdentifier' => null,
            ];
        }

        app_runtime_cache_clear(['translations', 'navigation']);

        $this->eventLogger->security(
            'admin.settings.saved',
            [
                'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                'database_changes' => [
                    'host' => $previousDatabase['host'] !== $databaseOverride['host'],
                    'port' => $previousDatabase['port'] !== $databaseOverride['port'],
                    'name' => $previousDatabase['name'] !== $databaseOverride['name'],
                    'user' => $previousDatabase['user'] !== $databaseOverride['user'],
                    'password' => $databaseConfig['data']['password'] !== '',
                    'prefix' => $previousDatabase['prefix'] !== $databaseOverride['prefix'],
                ],
                'admin_changes' => [
                    'identifier' => $previousAdminIdentifier !== $adminOverride['identifier'],
                    'password' => $adminConfig['data']['password'] !== '',
                    'allowed_ips' => $previousAdminAllowedIps !== $adminOverride['allowed_ips'],
                    'totp_enabled' => $previousAdminTotpEnabled !== $adminOverride['totp_enabled'],
                    'totp_secret' => $previousAdminTotpSecret !== $adminOverride['totp_secret'],
                    'inactivity_timeout_seconds' => $previousAdminInactivityTimeout !== $adminOverride['inactivity_timeout_seconds'],
                    'reauth_timeout_seconds' => $previousAdminReauthTimeout !== $adminOverride['reauth_timeout_seconds'],
                ],
                'site_changes' => [
                    'url' => [
                        'domain' => (string) ($previousUrl['domain'] ?? '') !== $siteOverride['url']['domain'],
                        'ssl_domain' => (string) ($previousUrl['sslDomain'] ?? '') !== $siteOverride['url']['ssl_domain'],
                        'base_path' => (string) ($previousUrl['basePath'] ?? '/') !== $siteOverride['url']['base_path'],
                    ],
                    'head_metadata_html' => $previousHeadMetadataHtml !== $siteOverride['head_metadata_html'],
                    'tarteaucitron' => [
                        'enabled' => (bool) ($previousTarteaucitron['enabled'] ?? true) !== $siteOverride['tarteaucitron']['enabled'],
                        'privacy_url' => (string) ($previousTarteaucitron['privacyUrl'] ?? '') !== $siteOverride['tarteaucitron']['privacy_url'],
                        'orientation' => (string) ($previousTarteaucitron['orientation'] ?? '') !== $siteOverride['tarteaucitron']['orientation'],
                        'icon_position' => (string) ($previousTarteaucitron['iconPosition'] ?? '') !== $siteOverride['tarteaucitron']['icon_position'],
                        'user_config_json' => (string) ($previousTarteaucitron['userConfigJson'] ?? '{}') !== $siteOverride['tarteaucitron']['user_config_json'],
                        'services' => $this->normalizeTarteaucitronServiceKeys($previousTarteaucitron['services'] ?? []) !== $this->normalizeTarteaucitronServiceKeys($siteOverride['tarteaucitron']['services']),
                    ],
                    'discussions' => [
                        'enabled' => (bool) ($previousDiscussions['enabled'] ?? true) !== $siteOverride['discussions']['enabled'],
                        'require_account' => (bool) ($previousDiscussions['requireAccount'] ?? false) !== $siteOverride['discussions']['require_account'],
                        'rate_limit_per_ip' => (int) ($previousDiscussions['rateLimitPerIp'] ?? 6) !== $siteOverride['discussions']['rate_limit_per_ip'],
                        'rate_limit_window' => (int) ($previousDiscussions['rateLimitWindow'] ?? 600) !== $siteOverride['discussions']['rate_limit_window'],
                        'global_rate_limit_per_ip' => (int) ($previousDiscussions['globalRateLimitPerIp'] ?? 20) !== $siteOverride['discussions']['global_rate_limit_per_ip'],
                        'global_rate_limit_window' => (int) ($previousDiscussions['globalRateLimitWindow'] ?? 3600) !== $siteOverride['discussions']['global_rate_limit_window'],
                        'min_form_fill_seconds' => (int) ($previousDiscussions['minFormFillSeconds'] ?? 3) !== $siteOverride['discussions']['min_form_fill_seconds'],
                        'max_form_age_seconds' => (int) ($previousDiscussions['maxFormAgeSeconds'] ?? 7200) !== $siteOverride['discussions']['max_form_age_seconds'],
                        'honeypot_field' => (string) ($previousDiscussions['honeypotField'] ?? 'website') !== $siteOverride['discussions']['honeypot_field'],
                        'recaptcha_enabled' => (bool) ($previousDiscussions['recaptchaEnabled'] ?? false) !== $siteOverride['discussions']['recaptcha']['enabled'],
                        'recaptcha_site_key' => (string) ($previousDiscussions['recaptchaSiteKey'] ?? '') !== $siteOverride['discussions']['recaptcha']['site_key'],
                        'recaptcha_secret_key' => (string) ($previousDiscussions['recaptchaSecretKey'] ?? '') !== $siteOverride['discussions']['recaptcha']['secret_key'],
                    ],
                    'instagram' => [
                        'enabled' => (bool) ($previousInstagram['enabled'] ?? false) !== $siteOverride['instagram']['enabled'],
                        'username' => (string) ($previousInstagram['username'] ?? '') !== $siteOverride['instagram']['username'],
                        'user_id' => (string) ($previousInstagram['userId'] ?? '') !== $siteOverride['instagram']['user_id'],
                        'access_token' => (string) ($previousInstagram['accessToken'] ?? '') !== $siteOverride['instagram']['access_token'],
                        'limit' => (int) ($previousInstagram['limit'] ?? 6) !== $siteOverride['instagram']['limit'],
                        'rotation_interval_ms' => (int) ($previousInstagram['rotationIntervalMs'] ?? 5500) !== $siteOverride['instagram']['rotation_interval_ms'],
                    ],
                    'i18n_overrides' => $previousTranslations !== $this->normalizeI18nOverrides($siteOverride['i18n_overrides']),
                ],
                'storage' => [
                    'database_override' => basename($this->databaseOverridePath),
                    'admin_override' => basename($this->adminOverridePath),
                    'site_override' => basename($this->siteOverridePath),
                    'outside_webroot' => $this->isOutsideWebroot($this->databaseOverridePath)
                        && $this->isOutsideWebroot($this->adminOverridePath)
                        && $this->isOutsideWebroot($this->siteOverridePath),
                ],
            ]
        );

        return [
            'success' => true,
            'message' => 'Paramètres d’exploitation sauvegardés.',
            'error' => null,
            'view' => $this->viewModel(),
            'adminIdentifier' => $adminOverride['identifier'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string|null, error: string|null, view: array<string, mixed>}
     */
    public function testInstagramConnection(array $payload, ?string $actorIdentifier = null): array
    {
        $databasePayload = is_array($payload['database'] ?? null) ? $payload['database'] : [];
        $adminPayload = is_array($payload['admin'] ?? null) ? $payload['admin'] : [];
        $urlPayload = is_array($payload['url'] ?? null) ? $payload['url'] : [];
        $headPayload = is_array($payload['head'] ?? null) ? $payload['head'] : [];
        $tarteaucitronPayload = is_array($payload['tarteaucitron'] ?? null) ? $payload['tarteaucitron'] : [];
        $discussionsPayload = is_array($payload['discussions'] ?? null) ? $payload['discussions'] : [];
        $instagramPayload = is_array($payload['instagram'] ?? null) ? $payload['instagram'] : [];
        $translationsPayload = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];

        $configuredUrl = $this->configuredUrlSettings();
        $configuredTarteaucitron = $this->configuredTarteaucitronSettings();
        $configuredDiscussions = $this->configuredDiscussionSettings();
        $configuredInstagram = $this->configuredInstagramSettings();
        $configuredTranslations = $this->configuredTranslationSettings();

        $databaseForm = [
            'host' => trim((string) ($databasePayload['host'] ?? app_config('database.host', '127.0.0.1'))),
            'port' => trim((string) ($databasePayload['port'] ?? app_config('database.port', 3306))),
            'name' => trim((string) ($databasePayload['name'] ?? app_config('database.name', ''))),
            'user' => trim((string) ($databasePayload['user'] ?? app_config('database.user', ''))),
            'password' => (string) ($databasePayload['password'] ?? ''),
            'prefix' => trim((string) ($databasePayload['prefix'] ?? app_config('database_prefix', 'car_'))),
        ];
        $adminForm = [
            'identifier' => trim((string) ($adminPayload['identifier'] ?? $this->configuredAdminIdentifier())),
            'password' => (string) ($adminPayload['password'] ?? ''),
            'allowedIps' => trim((string) ($adminPayload['allowed_ips'] ?? implode(', ', $this->configuredAdminAllowedIps()))),
            'totpEnabled' => $this->postBoolean($adminPayload, 'totp_enabled', $this->configuredAdminTotpEnabled()),
            'totpSecret' => trim((string) ($adminPayload['totp_secret'] ?? '')),
            'totpSecretFallback' => $this->configuredAdminTotpSecret(),
            'inactivityTimeoutSeconds' => trim((string) ($adminPayload['inactivity_timeout_seconds'] ?? $this->configuredAdminInactivityTimeoutSeconds())),
            'reauthTimeoutSeconds' => trim((string) ($adminPayload['reauth_timeout_seconds'] ?? $this->configuredAdminReauthTimeoutSeconds())),
        ];
        $urlForm = [
            'domain' => trim((string) ($urlPayload['domain'] ?? ($configuredUrl['domain'] ?? ''))),
            'sslDomain' => trim((string) ($urlPayload['ssl_domain'] ?? ($configuredUrl['sslDomain'] ?? ''))),
            'basePath' => trim((string) ($urlPayload['base_path'] ?? ($configuredUrl['basePath'] ?? '/'))),
        ];
        $headForm = [
            'metadataHtml' => (string) ($headPayload['metadata_html'] ?? $this->configuredHeadMetadataHtml()),
        ];
        $tarteaucitronForm = $this->tarteaucitronForm($tarteaucitronPayload, $configuredTarteaucitron);
        $discussionsForm = $this->discussionsForm($discussionsPayload, $configuredDiscussions);
        $instagramForm = $this->instagramForm($instagramPayload, $configuredInstagram);
        $translationsForm = $this->translationsForm($translationsPayload, $configuredTranslations);
        $view = $this->buildViewModel($databaseForm, $adminForm, $urlForm, $headForm, $tarteaucitronForm, $discussionsForm, $instagramForm, $translationsForm);

        $instagramConfig = $this->normalizeInstagramConfig($instagramForm);
        if ($instagramConfig['error'] !== null) {
            return [
                'success' => false,
                'message' => null,
                'error' => $instagramConfig['error'],
                'view' => $view,
            ];
        }

        $probe = $this->instagramFeedService()->probe([
            'enabled' => true,
            'username' => (string) $instagramConfig['data']['username'],
            'user_id' => (string) $instagramConfig['data']['userId'],
            'access_token' => (string) $instagramConfig['data']['accessToken'],
            'limit' => (int) $instagramConfig['data']['limit'],
            'rotation_interval_ms' => (int) $instagramConfig['data']['rotationIntervalMs'],
            'cache_ttl_seconds' => (int) $instagramConfig['data']['cacheTtlSeconds'],
            'timeout_seconds' => (int) $instagramConfig['data']['timeoutSeconds'],
        ]);

        if (!(bool) ($probe['success'] ?? false)) {
            $error = trim((string) ($probe['error'] ?? 'Impossible de contacter Instagram.'));
            $error = $error !== '' ? $error : 'Impossible de contacter Instagram.';

            $this->eventLogger->security(
                'admin.settings.instagram_test_failed',
                [
                    'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                    'username' => (string) $instagramConfig['data']['username'],
                    'error' => $error,
                ],
                'warning'
            );

            return [
                'success' => false,
                'message' => null,
                'error' => $error,
                'view' => $view,
            ];
        }

        $probeUsername = trim((string) ($probe['username'] ?? ''));
        $probePostCount = (int) ($probe['postCount'] ?? 0);
        $accountLabel = $probeUsername !== '' ? '@' . $probeUsername : 'compte inconnu';
        $message = sprintf(
            'Connexion Instagram validée pour %s (%d post(s) récupéré(s)).',
            $accountLabel,
            $probePostCount
        );

        $this->eventLogger->security(
            'admin.settings.instagram_tested',
            [
                'actor' => AppEventLogger::maskIdentifier($actorIdentifier),
                'username' => $probeUsername,
                'post_count' => $probePostCount,
            ]
        );

        return [
            'success' => true,
            'message' => $message,
            'error' => null,
            'view' => $view,
        ];
    }

    /**
     * @param array<string, string> $database
     * @param array<string, mixed> $admin
     * @param array<string, string> $url
     * @param array<string, string> $head
     * @param array<string, mixed> $tarteaucitron
     * @param array<string, bool|float|int|string> $discussions
     * @param array<string, bool|int|string> $instagram
     * @param array{languages?: array<int, string>, textByLanguage?: array<string, string>} $translations
     * @return array<string, mixed>
     */
    private function buildViewModel(
        array $database,
        array $admin,
        array $url,
        array $head,
        array $tarteaucitron,
        array $discussions,
        array $instagram,
        array $translations = []
    ): array {
        $databasePasswordConfigured = ((string) app_config('database.password', '')) !== '';
        $adminPasswordConfigured = ((string) app_config('admin.password_hash', '')) !== '';
        $adminTotpSecretConfigured = trim((string) ($admin['totpSecretFallback'] ?? '')) !== '';
        $adminInactivityTimeoutSeconds = max(60, (int) ($admin['inactivityTimeoutSeconds'] ?? 7200));
        $adminReauthTimeoutSeconds = max(60, (int) ($admin['reauthTimeoutSeconds'] ?? 7200));
        if ($adminReauthTimeoutSeconds > $adminInactivityTimeoutSeconds) {
            $adminReauthTimeoutSeconds = $adminInactivityTimeoutSeconds;
        }
        $discussionRecaptchaSecretConfigured = trim((string) ($discussions['recaptchaSecretKey'] ?? '')) !== '';
        $instagramAccessTokenConfigured = trim((string) ($instagram['accessToken'] ?? '')) !== '';
        $translationLanguages = is_array($translations['languages'] ?? null)
            ? array_values(array_filter(array_map(static fn ($value): string => strtolower(trim((string) $value)), $translations['languages']), static fn (string $value): bool => $value !== ''))
            : [];
        if ($translationLanguages === []) {
            $translationLanguages = function_exists('site_available_languages') ? site_available_languages() : [(string) app_config('default_lang', 'fr')];
            $translationLanguages = array_values(array_filter(array_map(static fn ($value): string => strtolower(trim((string) $value)), $translationLanguages), static fn (string $value): bool => $value !== ''));
        }
        if ($translationLanguages === []) {
            $translationLanguages = ['fr'];
        }

        $textByLanguage = is_array($translations['textByLanguage'] ?? null) ? $translations['textByLanguage'] : [];
        $translationTextareas = [];
        $translationCounts = [];
        foreach ($translationLanguages as $language) {
            $rawText = trim((string) ($textByLanguage[$language] ?? ''));
            $translationTextareas[$language] = $rawText;
            $translationCounts[$language] = $this->countTranslationOverrideLines($rawText);
        }
        $knownTranslationKeys = $this->knownTranslationKeys();

        return [
            'database' => [
                'host' => $database['host'],
                'port' => $database['port'],
                'name' => $database['name'],
                'user' => $database['user'],
                'password' => '',
                'passwordConfigured' => $databasePasswordConfigured,
                'passwordMask' => $databasePasswordConfigured ? '********' : '',
                'prefix' => $database['prefix'],
            ],
            'admin' => [
                'identifier' => $admin['identifier'],
                'password' => '',
                'allowedIps' => (string) ($admin['allowedIps'] ?? ''),
                'passwordConfigured' => $adminPasswordConfigured,
                'totpEnabled' => $this->normalizeBooleanValue($admin['totpEnabled'] ?? false, false),
                'totpSecret' => '',
                'totpSecretConfigured' => $adminTotpSecretConfigured,
                'inactivityTimeoutSeconds' => $adminInactivityTimeoutSeconds,
                'reauthTimeoutSeconds' => $adminReauthTimeoutSeconds,
            ],
            'url' => [
                'domain' => $url['domain'],
                'sslDomain' => $url['sslDomain'],
                'basePath' => $url['basePath'],
            ],
            'head' => [
                'metadataHtml' => $head['metadataHtml'],
            ],
            'tarteaucitron' => [
                'enabled' => $this->normalizeBooleanValue($tarteaucitron['enabled'] ?? true, true),
                'privacyUrl' => (string) ($tarteaucitron['privacyUrl'] ?? ''),
                'orientation' => (string) ($tarteaucitron['orientation'] ?? 'bottom'),
                'iconPosition' => (string) ($tarteaucitron['iconPosition'] ?? 'BottomRight'),
                'showIcon' => $this->normalizeBooleanValue($tarteaucitron['showIcon'] ?? true, true),
                'showAlertSmall' => $this->normalizeBooleanValue($tarteaucitron['showAlertSmall'] ?? true, true),
                'highPrivacy' => $this->normalizeBooleanValue($tarteaucitron['highPrivacy'] ?? true, true),
                'acceptAllCta' => $this->normalizeBooleanValue($tarteaucitron['acceptAllCta'] ?? true, true),
                'denyAllCta' => $this->normalizeBooleanValue($tarteaucitron['denyAllCta'] ?? true, true),
                'mandatory' => $this->normalizeBooleanValue($tarteaucitron['mandatory'] ?? true, true),
                'googleConsentMode' => $this->normalizeBooleanValue($tarteaucitron['googleConsentMode'] ?? true, true),
                'bingConsentMode' => $this->normalizeBooleanValue($tarteaucitron['bingConsentMode'] ?? true, true),
                'userConfigJson' => (string) ($tarteaucitron['userConfigJson'] ?? '{}'),
                'services' => $this->normalizeTarteaucitronServiceKeys($tarteaucitron['services'] ?? []),
            ],
            'discussions' => [
                'enabled' => $this->normalizeBooleanValue($discussions['enabled'] ?? true, true),
                'requireAccount' => $this->normalizeBooleanValue($discussions['requireAccount'] ?? false, false),
                'rateLimitPerIp' => (int) ($discussions['rateLimitPerIp'] ?? 6),
                'rateLimitWindow' => (int) ($discussions['rateLimitWindow'] ?? 600),
                'globalRateLimitPerIp' => (int) ($discussions['globalRateLimitPerIp'] ?? 20),
                'globalRateLimitWindow' => (int) ($discussions['globalRateLimitWindow'] ?? 3600),
                'minFormFillSeconds' => (int) ($discussions['minFormFillSeconds'] ?? 3),
                'maxFormAgeSeconds' => (int) ($discussions['maxFormAgeSeconds'] ?? 7200),
                'honeypotField' => (string) ($discussions['honeypotField'] ?? 'website'),
                'recaptchaEnabled' => $this->normalizeBooleanValue($discussions['recaptchaEnabled'] ?? false, false),
                'recaptchaSiteKey' => (string) ($discussions['recaptchaSiteKey'] ?? ''),
                'recaptchaSecretKey' => '',
                'recaptchaSecretKeyConfigured' => $discussionRecaptchaSecretConfigured,
                'recaptchaMinimumScore' => (float) ($discussions['recaptchaMinimumScore'] ?? 0.5),
                'recaptchaTimeoutSeconds' => (int) ($discussions['recaptchaTimeoutSeconds'] ?? 8),
            ],
            'instagram' => [
                'enabled' => $this->normalizeBooleanValue($instagram['enabled'] ?? false, false),
                'username' => (string) ($instagram['username'] ?? ''),
                'userId' => (string) ($instagram['userId'] ?? ''),
                'accessToken' => '',
                'accessTokenConfigured' => $instagramAccessTokenConfigured,
                'limit' => (int) ($instagram['limit'] ?? 6),
                'rotationIntervalMs' => (int) ($instagram['rotationIntervalMs'] ?? 5500),
                'cacheTtlSeconds' => (int) ($instagram['cacheTtlSeconds'] ?? 1800),
                'timeoutSeconds' => (int) ($instagram['timeoutSeconds'] ?? 8),
            ],
            'translations' => [
                'languages' => $translationLanguages,
                'textByLanguage' => $translationTextareas,
                'countByLanguage' => $translationCounts,
                'knownKeysCount' => count($knownTranslationKeys),
            ],
            'storage' => [
                'databaseOverridePath' => $this->databaseOverridePath,
                'adminOverridePath' => $this->adminOverridePath,
                'siteOverridePath' => $this->siteOverridePath,
                'outsideWebroot' => $this->isOutsideWebroot($this->databaseOverridePath)
                    && $this->isOutsideWebroot($this->adminOverridePath)
                    && $this->isOutsideWebroot($this->siteOverridePath),
            ],
        ];
    }

    /**
     * @param array<string, string> $url
     * @return array{data: array<string, string>, error: string|null}
     */
    private function normalizeUrlConfig(array $url): array
    {
        $domain = $this->normalizeDomainInput($url['domain'] ?? '');
        if ($domain === null) {
            return ['data' => [], 'error' => 'Le domaine public est invalide. Saisis seulement le nom de domaine, par exemple example.com.'];
        }

        $sslDomain = $this->normalizeDomainInput($url['sslDomain'] ?? '');
        if ($sslDomain === null) {
            return ['data' => [], 'error' => 'Le domaine SSL est invalide. Saisis seulement le nom de domaine, par exemple secure.example.com.'];
        }

        return [
            'data' => [
                'domain' => $domain,
                'sslDomain' => $sslDomain,
                'basePath' => normalize_public_route((string) ($url['basePath'] ?? '/')) ?? '/',
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, string> $database
     * @return array{data: array<string, mixed>, error: string|null}
     */
    private function normalizeDatabaseConfig(array $database): array
    {
        if ($database['host'] === '') {
            return ['data' => [], 'error' => 'L’adresse de la base est obligatoire.'];
        }

        $port = filter_var($database['port'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if ($port === false) {
            return ['data' => [], 'error' => 'Le port SQL doit être un entier entre 1 et 65535.'];
        }

        if ($database['name'] === '') {
            return ['data' => [], 'error' => 'Le nom de base est obligatoire.'];
        }

        if ($database['user'] === '') {
            return ['data' => [], 'error' => 'L’identifiant SQL est obligatoire.'];
        }

        if ($database['prefix'] === '') {
            return ['data' => [], 'error' => 'Le préfixe SQL est obligatoire.'];
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $database['prefix']) !== 1) {
            return ['data' => [], 'error' => 'Le préfixe SQL ne doit contenir que des lettres, chiffres ou underscores.'];
        }

        return [
            'data' => [
                'host' => $database['host'],
                'port' => (int) $port,
                'name' => $database['name'],
                'user' => $database['user'],
                'password' => $database['password'],
                'prefix' => $database['prefix'],
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $admin
     * @return array{
     *   data: array{
     *     identifier: string,
     *     password: string,
     *     allowedIps: array<int, string>,
     *     totpEnabled: bool,
     *     totpSecret: string,
     *     inactivityTimeoutSeconds: int,
     *     reauthTimeoutSeconds: int
     *   },
     *   error: null
     * }|array{
     *   data: array{},
     *   error: non-empty-string
     * }
     */
    private function normalizeAdminConfig(array $admin): array
    {
        if ((string) ($admin['identifier'] ?? '') === '') {
            return ['data' => [], 'error' => 'L’e-mail admin est obligatoire.'];
        }

        if (filter_var((string) $admin['identifier'], FILTER_VALIDATE_EMAIL) === false) {
            return ['data' => [], 'error' => 'L’e-mail admin est invalide.'];
        }

        $allowedIps = [];
        $allowedIpTokens = preg_split('/[\s,;]+/', (string) ($admin['allowedIps'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $allowedIpTokens = is_array($allowedIpTokens) ? $allowedIpTokens : [];

        foreach ($allowedIpTokens as $allowedIpToken) {
            $rawAllowedIp = trim((string) $allowedIpToken);
            if ($rawAllowedIp === '') {
                continue;
            }

            $normalizedAllowedIp = $this->normalizeAllowedIpInput($rawAllowedIp);
            if ($normalizedAllowedIp === null) {
                return ['data' => [], 'error' => sprintf('L’IP autorisée "%s" est invalide.', $rawAllowedIp)];
            }

            if (!in_array($normalizedAllowedIp, $allowedIps, true)) {
                $allowedIps[] = $normalizedAllowedIp;
            }
        }

        $inactivityTimeoutSeconds = filter_var((string) ($admin['inactivityTimeoutSeconds'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 60, 'max_range' => 86400],
        ]);
        if ($inactivityTimeoutSeconds === false) {
            return ['data' => [], 'error' => 'Le timeout d’inactivité admin doit être un entier entre 60 et 86400 secondes.'];
        }

        $reauthTimeoutSeconds = filter_var((string) ($admin['reauthTimeoutSeconds'] ?? ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 60, 'max_range' => 86400],
        ]);
        if ($reauthTimeoutSeconds === false) {
            return ['data' => [], 'error' => 'La fenêtre de ré-authentification doit être un entier entre 60 et 86400 secondes.'];
        }

        if ($reauthTimeoutSeconds > $inactivityTimeoutSeconds) {
            return ['data' => [], 'error' => 'La fenêtre de ré-authentification ne peut pas dépasser le timeout d’inactivité.'];
        }

        $totpEnabled = (bool) ($admin['totpEnabled'] ?? false);
        $totpSecret = $this->normalizeTotpSecret((string) ($admin['totpSecret'] ?? ''));
        $totpSecretFallback = $this->normalizeTotpSecret((string) ($admin['totpSecretFallback'] ?? ''));
        if ($totpSecret === '') {
            $totpSecret = $totpSecretFallback;
        }

        if ($totpEnabled && $totpSecret === '') {
            return ['data' => [], 'error' => 'Active le 2FA TOTP uniquement après avoir renseigné un secret Base32 valide.'];
        }

        if ($totpEnabled && $totpSecret !== '' && preg_match('/^[A-Z2-7]{16,}$/', $totpSecret) !== 1) {
            return ['data' => [], 'error' => 'Le secret 2FA TOTP est invalide. Utilise un secret Base32 d’au moins 16 caractères.'];
        }

        return [
            'data' => [
                'identifier' => (string) $admin['identifier'],
                'password' => (string) ($admin['password'] ?? ''),
                'allowedIps' => $allowedIps,
                'totpEnabled' => $totpEnabled,
                'totpSecret' => $totpSecret,
                'inactivityTimeoutSeconds' => (int) $inactivityTimeoutSeconds,
                'reauthTimeoutSeconds' => (int) $reauthTimeoutSeconds,
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, string> $head
     * @return array{data: array<string, string>, error: string|null}
     */
    private function normalizeHeadConfig(array $head): array
    {
        return [
            'data' => [
                'metadataHtml' => $this->sanitizeHeadMetadataHtml($head['metadataHtml'] ?? ''),
            ],
            'error' => null,
        ];
    }

    /**
     * @param array<string, mixed> $databaseOverride
     * @param array<string, mixed> $adminOverride
     * @param array<string, mixed> $siteOverride
     */
    private function applyRuntimeConfig(array $databaseOverride, array $adminOverride, array $siteOverride): void
    {
        global $appConfig;

        if (!is_array($appConfig)) {
            return;
        }

        $appConfig['database'] = array_merge((array) ($appConfig['database'] ?? []), [
            'host' => (string) ($databaseOverride['host'] ?? ''),
            'port' => (int) ($databaseOverride['port'] ?? 3306),
            'name' => (string) ($databaseOverride['name'] ?? ''),
            'user' => (string) ($databaseOverride['user'] ?? ''),
            'password' => (string) ($databaseOverride['password'] ?? ''),
            'charset' => (string) ($databaseOverride['charset'] ?? 'utf8mb4'),
        ]);
        $appConfig['database_prefix'] = (string) ($databaseOverride['prefix'] ?? $appConfig['database_prefix'] ?? 'car_');
        $runtimeAdminInactivityTimeout = max(60, min(86400, (int) ($adminOverride['inactivity_timeout_seconds'] ?? 7200)));
        $runtimeAdminReauthTimeout = max(60, min(86400, (int) ($adminOverride['reauth_timeout_seconds'] ?? 7200)));
        if ($runtimeAdminReauthTimeout > $runtimeAdminInactivityTimeout) {
            $runtimeAdminReauthTimeout = $runtimeAdminInactivityTimeout;
        }

        $appConfig['admin'] = array_merge((array) ($appConfig['admin'] ?? []), [
            'identifier' => (string) ($adminOverride['identifier'] ?? ''),
            'email' => (string) ($adminOverride['identifier'] ?? ''),
            'password_hash' => (string) ($adminOverride['password_hash'] ?? ''),
            'allowed_ips' => $this->normalizeConfiguredAdminAllowedIps($adminOverride['allowed_ips'] ?? []),
            'totp_enabled' => $this->normalizeBooleanValue($adminOverride['totp_enabled'] ?? false, false),
            'totp_secret' => $this->normalizeTotpSecret((string) ($adminOverride['totp_secret'] ?? '')),
            'inactivity_timeout_seconds' => $runtimeAdminInactivityTimeout,
            'reauth_timeout_seconds' => $runtimeAdminReauthTimeout,
        ]);
        $appConfig['site'] = array_merge((array) ($appConfig['site'] ?? []), [
            'url' => [
                'domain' => trim((string) ($siteOverride['url']['domain'] ?? '')),
                'ssl_domain' => trim((string) ($siteOverride['url']['ssl_domain'] ?? '')),
                'base_path' => normalize_public_route((string) ($siteOverride['url']['base_path'] ?? '/')) ?? '/',
            ],
            'head_metadata_html' => trim((string) ($siteOverride['head_metadata_html'] ?? '')),
            'tarteaucitron' => [
                'enabled' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['enabled'] ?? true, true),
                'privacy_url' => normalize_public_route((string) ($siteOverride['tarteaucitron']['privacy_url'] ?? '/')) ?? '/',
                'orientation' => trim((string) ($siteOverride['tarteaucitron']['orientation'] ?? 'bottom')),
                'icon_position' => trim((string) ($siteOverride['tarteaucitron']['icon_position'] ?? 'BottomRight')),
                'show_icon' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['show_icon'] ?? true, true),
                'show_alert_small' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['show_alert_small'] ?? true, true),
                'high_privacy' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['high_privacy'] ?? true, true),
                'accept_all_cta' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['accept_all_cta'] ?? true, true),
                'deny_all_cta' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['deny_all_cta'] ?? true, true),
                'mandatory' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['mandatory'] ?? true, true),
                'google_consent_mode' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['google_consent_mode'] ?? true, true),
                'bing_consent_mode' => $this->normalizeBooleanValue($siteOverride['tarteaucitron']['bing_consent_mode'] ?? true, true),
                'user_config_json' => $this->normalizeTarteaucitronUserConfigJson($siteOverride['tarteaucitron']['user_config_json'] ?? '{}')['json'],
                'services' => $this->normalizeTarteaucitronServiceKeys($siteOverride['tarteaucitron']['services'] ?? []),
            ],
            'discussions' => [
                'enabled' => $this->normalizeBooleanValue($siteOverride['discussions']['enabled'] ?? true, true),
                'require_account' => $this->normalizeBooleanValue($siteOverride['discussions']['require_account'] ?? false, false),
                'rate_limit_per_ip' => max(1, (int) ($siteOverride['discussions']['rate_limit_per_ip'] ?? 6)),
                'rate_limit_window' => max(60, (int) ($siteOverride['discussions']['rate_limit_window'] ?? 600)),
                'global_rate_limit_per_ip' => max(1, (int) ($siteOverride['discussions']['global_rate_limit_per_ip'] ?? 20)),
                'global_rate_limit_window' => max(60, (int) ($siteOverride['discussions']['global_rate_limit_window'] ?? 3600)),
                'min_form_fill_seconds' => max(0, (int) ($siteOverride['discussions']['min_form_fill_seconds'] ?? 3)),
                'max_form_age_seconds' => max(60, (int) ($siteOverride['discussions']['max_form_age_seconds'] ?? 7200)),
                'honeypot_field' => trim((string) ($siteOverride['discussions']['honeypot_field'] ?? 'website')),
                'recaptcha' => [
                    'enabled' => $this->normalizeBooleanValue($siteOverride['discussions']['recaptcha']['enabled'] ?? false, false),
                    'site_key' => trim((string) ($siteOverride['discussions']['recaptcha']['site_key'] ?? '')),
                    'secret_key' => trim((string) ($siteOverride['discussions']['recaptcha']['secret_key'] ?? '')),
                    'minimum_score' => max(0.0, min(1.0, (float) ($siteOverride['discussions']['recaptcha']['minimum_score'] ?? 0.5))),
                    'timeout_seconds' => max(3, min(20, (int) ($siteOverride['discussions']['recaptcha']['timeout_seconds'] ?? 8))),
                ],
            ],
            'instagram' => [
                'enabled' => $this->normalizeBooleanValue($siteOverride['instagram']['enabled'] ?? false, false),
                'username' => ltrim(trim((string) ($siteOverride['instagram']['username'] ?? '')), '@'),
                'user_id' => trim((string) ($siteOverride['instagram']['user_id'] ?? '')),
                'access_token' => trim((string) ($siteOverride['instagram']['access_token'] ?? '')),
                'limit' => max(1, min(20, (int) ($siteOverride['instagram']['limit'] ?? 6))),
                'rotation_interval_ms' => max(2500, min(30000, (int) ($siteOverride['instagram']['rotation_interval_ms'] ?? 5500))),
                'cache_ttl_seconds' => max(60, min(86400, (int) ($siteOverride['instagram']['cache_ttl_seconds'] ?? 1800))),
                'timeout_seconds' => max(3, min(20, (int) ($siteOverride['instagram']['timeout_seconds'] ?? 8))),
                'cache_path' => ROOT_PATH . '/var/cache/instagram-feed.json',
            ],
            'i18n_overrides' => $this->normalizeI18nOverrides($siteOverride['i18n_overrides'] ?? []),
        ]);
    }

    /**
     * @param mixed $rawValue
     * @return array{
     *   data: array<string, bool|float|int|string>,
     *   json: string,
     *   error: string|null
     * }
     */
    private function normalizeTarteaucitronUserConfigJson(mixed $rawValue): array
    {
        $decoded = [];
        $isExplicitJsonList = false;

        if (is_array($rawValue)) {
            $decoded = $rawValue;
        } else {
            $rawJson = trim((string) $rawValue);
            if ($rawJson === '') {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => null,
                ];
            }

            try {
                $parsed = json_decode($rawJson, true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => 'Le JSON des variables services tarteaucitron est invalide.',
                ];
            }

            if (!is_array($parsed)) {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => 'Les variables services tarteaucitron doivent être un objet JSON (paires clé/valeur).',
                ];
            }

            $decoded = $parsed;
            $trimmedRawJson = ltrim($rawJson);
            $isExplicitJsonList = $trimmedRawJson !== '' && $trimmedRawJson[0] === '[';
        }

        // Note: {} est décodé en [] en mode associatif, ce cas doit rester valide.
        if (array_is_list($decoded) && ($decoded !== [] || $isExplicitJsonList)) {
            return [
                'data' => [],
                'json' => '{}',
                'error' => 'Les variables services tarteaucitron doivent être un objet JSON (pas une liste).',
            ];
        }

        $normalized = [];

        foreach ($decoded as $key => $value) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => 'Chaque variable services tarteaucitron doit avoir une clé non vide.',
                ];
            }

            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{1,79}$/', $normalizedKey) !== 1) {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => sprintf(
                        'La clé "%s" est invalide. Utilise uniquement lettres/chiffres/underscore, sans espace.',
                        $normalizedKey
                    ),
                ];
            }

            if (!is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => sprintf(
                        'La variable "%s" doit être un booléen, un nombre ou une chaîne.',
                        $normalizedKey
                    ),
                ];
            }

            if (is_float($value) && !is_finite($value)) {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => sprintf(
                        'La variable "%s" contient un nombre non valide.',
                        $normalizedKey
                    ),
                ];
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }

                if (strlen($value) > 2048) {
                    return [
                        'data' => [],
                        'json' => '{}',
                        'error' => sprintf(
                            'La variable "%s" dépasse 2048 caractères.',
                            $normalizedKey
                        ),
                    ];
                }
            }

            $normalized[$normalizedKey] = $value;
        }

        ksort($normalized);
        if ($normalized === []) {
            $encoded = '{}';
        } else {
            $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                return [
                    'data' => [],
                    'json' => '{}',
                    'error' => 'Impossible de sérialiser les variables services tarteaucitron.',
                ];
            }
        }

        return [
            'data' => $normalized,
            'json' => $encoded,
            'error' => null,
        ];
    }

    /**
     * @param mixed $services
     * @return array<int, string>
     */
    private function submittedTarteaucitronServices(mixed $services): array
    {
        if (is_string($services)) {
            return [$services];
        }

        if (!is_array($services)) {
            return [];
        }

        $values = [];
        foreach ($services as $service) {
            if (!is_scalar($service) && $service !== null) {
                continue;
            }

            $values[] = trim((string) $service);
        }

        return $values;
    }

    /**
     * @param mixed $services
     * @return array{data: array<int, string>, error: string|null}
     */
    private function validateTarteaucitronServices(mixed $services): array
    {
        if (!is_array($services)) {
            return ['data' => [], 'error' => null];
        }

        $normalizedServices = [];
        $seen = [];

        foreach ($services as $service) {
            if (!is_scalar($service) && $service !== null) {
                continue;
            }

            $raw = trim((string) $service);
            if ($raw === '') {
                continue;
            }

            $normalized = strtolower($raw);
            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $normalized) !== 1) {
                return [
                    'data' => [],
                    'error' => sprintf(
                        'Le service tarteaucitron "%s" est invalide. Utilise l’identifiant technique du service, par exemple youtube, vimeo ou googlemaps.',
                        $raw
                    ),
                ];
            }

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $normalizedServices[] = $normalized;
        }

        return ['data' => $normalizedServices, 'error' => null];
    }

    /**
     * @param mixed $services
     * @return array<int, string>
     */
    private function normalizeTarteaucitronServiceKeys(mixed $services): array
    {
        if (!is_array($services)) {
            return [];
        }

        $normalizedServices = [];
        $seen = [];

        foreach ($services as $service) {
            if (!is_scalar($service) && $service !== null) {
                continue;
            }

            $normalized = strtolower(trim((string) $service));
            if ($normalized === '') {
                continue;
            }

            if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $normalized) !== 1) {
                continue;
            }

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $normalizedServices[] = $normalized;
        }

        return $normalizedServices;
    }

    private function configuredAdminIdentifier(): string
    {
        $email = trim((string) app_config('admin.email', ''));
        if ($email !== '') {
            return $email;
        }

        $identifier = trim((string) app_config('admin.identifier', ''));
        if ($identifier !== '') {
            return $identifier;
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function configuredAdminAllowedIps(): array
    {
        return $this->normalizeConfiguredAdminAllowedIps(app_config('admin.allowed_ips', []));
    }

    private function configuredAdminTotpEnabled(): bool
    {
        return $this->normalizeBooleanValue(app_config('admin.totp_enabled', false), false);
    }

    private function configuredAdminTotpSecret(): string
    {
        return $this->normalizeTotpSecret((string) app_config('admin.totp_secret', ''));
    }

    private function configuredAdminInactivityTimeoutSeconds(): int
    {
        return max(60, min(86400, (int) app_config('admin.inactivity_timeout_seconds', 7200)));
    }

    private function configuredAdminReauthTimeoutSeconds(): int
    {
        $timeout = max(60, min(86400, (int) app_config('admin.reauth_timeout_seconds', 7200)));
        $inactivityTimeout = $this->configuredAdminInactivityTimeoutSeconds();

        return $timeout > $inactivityTimeout ? $inactivityTimeout : $timeout;
    }

    /**
     * @param mixed $allowedIps
     * @return array<int, string>
     */
    private function normalizeConfiguredAdminAllowedIps(mixed $allowedIps): array
    {
        if (!is_array($allowedIps)) {
            return [];
        }

        $normalizedAllowedIps = [];

        foreach ($allowedIps as $allowedIp) {
            $normalizedAllowedIp = $this->normalizeAllowedIpInput(trim((string) $allowedIp));
            if ($normalizedAllowedIp === null) {
                continue;
            }

            if (!in_array($normalizedAllowedIp, $normalizedAllowedIps, true)) {
                $normalizedAllowedIps[] = $normalizedAllowedIp;
            }
        }

        return $normalizedAllowedIps;
    }

    private function normalizeAllowedIpInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!str_contains($value, '/')) {
            return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
        }

        [$subnet, $prefixLength] = array_pad(explode('/', $value, 2), 2, null);
        $subnet = trim((string) $subnet);
        $prefixLength = trim((string) $prefixLength);

        if ($subnet === '' || $prefixLength === '' || ctype_digit($prefixLength) === false) {
            return null;
        }

        if (filter_var($subnet, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $subnetBinary = @inet_pton($subnet);
        if ($subnetBinary === false) {
            return null;
        }

        $maxBits = strlen($subnetBinary) * 8;
        $prefixLengthInt = (int) $prefixLength;
        if ($prefixLengthInt < 0 || $prefixLengthInt > $maxBits) {
            return null;
        }

        return $subnet . '/' . $prefixLengthInt;
    }

    private function normalizeTotpSecret(string $secret): string
    {
        $normalized = strtoupper(trim($secret));
        $normalized = preg_replace('/[\s\-=]+/', '', $normalized) ?? $normalized;

        if ($normalized === '' || preg_match('/^[A-Z2-7]+$/', $normalized) !== 1) {
            return '';
        }

        return $normalized;
    }

    private function configuredHeadMetadataHtml(): string
    {
        return trim((string) app_config('site.head_metadata_html', ''));
    }

    private function instagramFeedService(): InstagramFeedService
    {
        if ($this->instagramFeedService instanceof InstagramFeedService) {
            return $this->instagramFeedService;
        }

        $this->instagramFeedService = \instagram_feed_service();

        return $this->instagramFeedService;
    }

    private function normalizeDomainInput(string $value): ?string
    {
        $value = trim(str_replace('\\', '/', $value));
        if ($value === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            $host = (string) (parse_url($value, PHP_URL_HOST) ?? '');
            $port = parse_url($value, PHP_URL_PORT);
            $value = $host;

            if ($value !== '' && is_int($port)) {
                $value .= ':' . $port;
            }
        } elseif (str_contains($value, '/')) {
            $value = strtok($value, '/') ?: '';
        }

        $port = null;
        if (preg_match('/^(.*):(\d{1,5})$/', $value, $matches) === 1) {
            $value = $matches[1];
            $port = (int) $matches[2];

            if ($port < 1 || $port > 65535) {
                return null;
            }
        }

        $host = strtolower(trim($value));
        if ($host === '') {
            return '';
        }

        $isValidHost = $host === 'localhost'
            || filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        if (!$isValidHost) {
            return null;
        }

        return $port !== null ? $host . ':' . $port : $host;
    }

    private function sanitizeHeadMetadataHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $snippets = [];
        preg_match_all('/<meta\b[^>]*>|<link\b[^>]*>|<script\b[^>]*>.*?<\/script>/isu', $html, $matches, PREG_OFFSET_CAPTURE);
        $fragments = $matches[0];

        foreach ($fragments as $fragment) {
            $markup = (string) $fragment[0];
            if ($markup === '') {
                continue;
            }

            if (preg_match('/^<meta\b/i', $markup) === 1) {
                $snippet = $this->sanitizeMetaTag($markup);
            } elseif (preg_match('/^<link\b/i', $markup) === 1) {
                $snippet = $this->sanitizeLinkTag($markup);
            } elseif (preg_match('/^<script\b/i', $markup) === 1) {
                $snippet = $this->sanitizeJsonLdScriptTag($markup);
            } else {
                $snippet = null;
            }

            if ($snippet !== null) {
                $snippets[] = $snippet;
            }
        }

        return implode("\n", $snippets);
    }

    private function sanitizeMetaTag(string $markup): ?string
    {
        $attributes = $this->extractTagAttributes($markup);
        $allowed = [];

        foreach (['name', 'property', 'content', 'itemprop'] as $attributeName) {
            if (!array_key_exists($attributeName, $attributes)) {
                continue;
            }

            $value = trim($attributes[$attributeName]);
            if ($value === '') {
                continue;
            }

            $allowed[$attributeName] = $value;
        }

        if (!isset($allowed['content']) || (!isset($allowed['name']) && !isset($allowed['property']) && !isset($allowed['itemprop']))) {
            return null;
        }

        return '<meta' . $this->compileAttributes($allowed) . ' />';
    }

    private function sanitizeLinkTag(string $markup): ?string
    {
        $attributes = $this->extractTagAttributes($markup);
        $rel = strtolower(trim($attributes['rel'] ?? ''));
        $href = trim($attributes['href'] ?? '');

        if ($href === '' || !in_array($rel, ['canonical', 'alternate'], true)) {
            return null;
        }

        $allowed = [
            'rel' => $rel,
            'href' => $href,
        ];

        foreach (['hreflang', 'title', 'media'] as $attributeName) {
            $value = trim($attributes[$attributeName] ?? '');
            if ($value !== '') {
                $allowed[$attributeName] = $value;
            }
        }

        return '<link' . $this->compileAttributes($allowed) . ' />';
    }

    private function sanitizeJsonLdScriptTag(string $markup): ?string
    {
        if (preg_match('/^<script\b([^>]*)>(.*?)<\/script>$/isu', trim($markup), $matches) !== 1) {
            return null;
        }

        $attributes = $this->extractTagAttributes('<script' . $matches[1] . '>');
        $type = strtolower(trim($attributes['type'] ?? ''));
        if ($type !== 'application/ld+json') {
            return null;
        }

        $content = trim(html_entity_decode((string) $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($content === '') {
            return null;
        }

        $content = preg_replace('/<\/script/iu', '<\\/script', $content) ?? $content;

        return '<script type="application/ld+json">' . $content . '</script>';
    }

    /**
     * @return array<string, string>
     */
    private function extractTagAttributes(string $markup): array
    {
        $attributes = [];
        preg_match_all(
            '/([A-Za-z][A-Za-z0-9:_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/u',
            $markup,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $attributeName = strtolower((string) $match[1]);
            if ($attributeName === '') {
                continue;
            }

            $attributeValue = (string) ($match[2] ?? $match[3] ?? $match[4] ?? '');
            $attributes[$attributeName] = trim(html_entity_decode($attributeValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return $attributes;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function compileAttributes(array $attributes): string
    {
        $compiled = '';

        foreach ($attributes as $name => $value) {
            $compiled .= sprintf(
                ' %s="%s"',
                $name,
                htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            );
        }

        return $compiled;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePhpArrayFile(string $path, array $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier %s.', $directory));
        }

        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
        $temporaryPath = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        $written = file_put_contents($temporaryPath, $content, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException(sprintf('Impossible d’écrire le fichier %s.', $temporaryPath));
        }

        @chmod($temporaryPath, 0600);

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new \RuntimeException(sprintf('Impossible de déplacer le fichier %s vers %s.', $temporaryPath, $path));
        }

        @chmod($path, 0600);
    }

    private function isOutsideWebroot(string $path): bool
    {
        $publicRoot = realpath(ROOT_PATH . '/public');
        $targetDirectory = realpath(dirname($path));

        if ($publicRoot === false) {
            return true;
        }

        if ($targetDirectory === false) {
            return true;
        }

        return !str_starts_with($targetDirectory, $publicRoot);
    }
}
