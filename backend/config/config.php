<?php
// ne pas changer l'ordre de chargement
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/../'));
}

$databaseDefaults = [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'name' => env('DB_NAME', ''),
    'user' => env('DB_USER', ''),
    'password' => env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];

$databaseOverridePath = ROOT_PATH . '/config/database.override.php';
$databaseOverride = [];
$databasePrefixOverride = null;

if (file_exists($databaseOverridePath)) {
    $override = include $databaseOverridePath;
    if (is_array($override)) {
        $databaseOverride = array_intersect_key($override, $databaseDefaults);
        $prefixOverride = trim((string) ($override['prefix'] ?? ''));
        $databasePrefixOverride = $prefixOverride !== '' ? $prefixOverride : null;
    }
}

$adminOverridePath = ROOT_PATH . '/config/admin.override.php';
$adminOverride = [];

if (file_exists($adminOverridePath)) {
    $override = include $adminOverridePath;
    if (is_array($override)) {
        $adminOverride = $override;
    }
}

$siteOverridePath = ROOT_PATH . '/config/site.override.php';
$siteOverride = [];

if (file_exists($siteOverridePath)) {
    $override = include $siteOverridePath;
    if (is_array($override)) {
        $siteOverride = $override;
    }
}

$siteTarteaucitronOverride = is_array($siteOverride['tarteaucitron'] ?? null) ? $siteOverride['tarteaucitron'] : [];
$siteUrlOverride = is_array($siteOverride['url'] ?? null) ? $siteOverride['url'] : [];
$siteDiscussionsOverride = is_array($siteOverride['discussions'] ?? null) ? $siteOverride['discussions'] : [];
$siteInstagramOverride = is_array($siteOverride['instagram'] ?? null) ? $siteOverride['instagram'] : [];
$siteLogAlertsOverride = is_array($siteOverride['log_alerts'] ?? null) ? $siteOverride['log_alerts'] : [];
$normalizeI18nOverrides = static function (mixed $overrides): array {
    if (!is_array($overrides)) {
        return [];
    }

    $normalizedByLanguage = [];
    foreach ($overrides as $language => $values) {
        $language = strtolower(trim((string) $language));
        if ($language === '' || preg_match('/^[a-z]{2,5}$/', $language) !== 1 || !is_array($values)) {
            continue;
        }

        $normalizedValues = [];
        foreach ($values as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || !is_scalar($value)) {
                continue;
            }

            $normalizedValues[$key] = trim((string) $value);
        }

        if ($normalizedValues === []) {
            continue;
        }

        ksort($normalizedValues);
        $normalizedByLanguage[$language] = $normalizedValues;
    }

    ksort($normalizedByLanguage);

    return $normalizedByLanguage;
};
$siteI18nOverrides = $normalizeI18nOverrides($siteOverride['i18n_overrides'] ?? []);
$siteDiscussionsRecaptchaOverride = is_array($siteDiscussionsOverride['recaptcha'] ?? null)
    ? $siteDiscussionsOverride['recaptcha']
    : [];
$envDiscussionRecaptchaSiteKey = trim((string) env('DISCUSSIONS_RECAPTCHA_SITE_KEY', env('SITE_RECAPTCHA_SITE_KEY', '')));
$envDiscussionRecaptchaSecretKey = trim((string) env('DISCUSSIONS_RECAPTCHA_SECRET_KEY', env('SITE_RECAPTCHA_SECRET_KEY', '')));
$envInstagramUsername = ltrim(trim((string) env('INSTAGRAM_USERNAME', '')), '@');
if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $envInstagramUsername) !== 1) {
    $envInstagramUsername = '';
}
$envInstagramUserId = trim((string) env('INSTAGRAM_USER_ID', ''));
$envInstagramAccessToken = trim((string) env('INSTAGRAM_ACCESS_TOKEN', ''));
$configuredBaseUrl = trim((string) env('BASE_URL', '/'));
$configuredBaseUrlHost = '';
$configuredBaseUrlPath = '/';

if ($configuredBaseUrl !== '' && preg_match('#^https?://#i', $configuredBaseUrl) === 1) {
    $configuredBaseUrlHost = (string) (parse_url($configuredBaseUrl, PHP_URL_HOST) ?? '');
    $configuredBaseUrlPort = parse_url($configuredBaseUrl, PHP_URL_PORT);
    if ($configuredBaseUrlHost !== '' && is_int($configuredBaseUrlPort)) {
        $configuredBaseUrlHost .= ':' . $configuredBaseUrlPort;
    }

    $configuredBaseUrlPath = normalize_public_route((string) (parse_url($configuredBaseUrl, PHP_URL_PATH) ?? '/')) ?? '/';
} else {
    $configuredBaseUrlPath = normalize_public_route($configuredBaseUrl) ?? '/';
}

$siteUrlDefaults = [
    'domain' => $configuredBaseUrlHost,
    'ssl_domain' => $configuredBaseUrlHost,
    'base_path' => $configuredBaseUrlPath,
];
$siteTarteaucitronDefaults = [
    'enabled' => true,
    'privacy_url' => '/',
    'orientation' => 'bottom',
    'icon_position' => 'BottomRight',
    'show_icon' => true,
    'show_alert_small' => true,
    'high_privacy' => true,
    'accept_all_cta' => true,
    'deny_all_cta' => true,
    'mandatory' => true,
    'google_consent_mode' => true,
    'bing_consent_mode' => true,
    'user_config_json' => '{}',
];
$siteDiscussionsDefaults = [
    'enabled' => true,
    'require_account' => false,
    'rate_limit_per_ip' => 6,
    'rate_limit_window' => 600,
    'global_rate_limit_per_ip' => 20,
    'global_rate_limit_window' => 3600,
    'min_form_fill_seconds' => 3,
    'max_form_age_seconds' => 7200,
    'honeypot_field' => 'website',
];
$siteDiscussionsRecaptchaDefaults = [
    'enabled' => false,
    'site_key' => '',
    'secret_key' => '',
    'minimum_score' => 0.5,
    'timeout_seconds' => 8,
];
$siteInstagramDefaults = [
    'enabled' => false,
    'username' => '',
    'user_id' => '',
    'access_token' => '',
    'limit' => 6,
    'rotation_interval_ms' => 5500,
    'cache_ttl_seconds' => 1800,
    'timeout_seconds' => 8,
];
$siteLogAlertsDefaults = [
    'notify_on' => trim((string) env('LOG_ALERTS_NOTIFY_ON', 'alerts')),
];
$normalizeBooleanValue = static function (mixed $value, bool $fallback): bool {
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
};
$siteTarteaucitronConfig = array_merge(
    $siteTarteaucitronDefaults,
    array_intersect_key($siteTarteaucitronOverride, $siteTarteaucitronDefaults)
);
$siteTarteaucitronConfig['enabled'] = $normalizeBooleanValue($siteTarteaucitronConfig['enabled'] ?? true, true);
$siteTarteaucitronConfig['show_icon'] = $normalizeBooleanValue($siteTarteaucitronConfig['show_icon'] ?? true, true);
$siteTarteaucitronConfig['show_alert_small'] = $normalizeBooleanValue($siteTarteaucitronConfig['show_alert_small'] ?? true, true);
$siteTarteaucitronConfig['high_privacy'] = $normalizeBooleanValue($siteTarteaucitronConfig['high_privacy'] ?? true, true);
$siteTarteaucitronConfig['accept_all_cta'] = $normalizeBooleanValue($siteTarteaucitronConfig['accept_all_cta'] ?? true, true);
$siteTarteaucitronConfig['deny_all_cta'] = $normalizeBooleanValue($siteTarteaucitronConfig['deny_all_cta'] ?? true, true);
$siteTarteaucitronConfig['mandatory'] = $normalizeBooleanValue($siteTarteaucitronConfig['mandatory'] ?? true, true);
$siteTarteaucitronConfig['google_consent_mode'] = $normalizeBooleanValue($siteTarteaucitronConfig['google_consent_mode'] ?? true, true);
$siteTarteaucitronConfig['bing_consent_mode'] = $normalizeBooleanValue($siteTarteaucitronConfig['bing_consent_mode'] ?? true, true);
$siteTarteaucitronConfig['privacy_url'] = normalize_public_route((string) ($siteTarteaucitronConfig['privacy_url'] ?? '/')) ?? '/';
$siteTarteaucitronConfig['orientation'] = in_array(
    trim((string) ($siteTarteaucitronConfig['orientation'] ?? 'bottom')),
    ['top', 'bottom', 'middle'],
    true
) ? trim((string) ($siteTarteaucitronConfig['orientation'] ?? 'bottom')) : 'bottom';
$siteTarteaucitronConfig['icon_position'] = in_array(
    trim((string) ($siteTarteaucitronConfig['icon_position'] ?? 'BottomRight')),
    ['BottomRight', 'BottomLeft', 'TopRight', 'TopLeft'],
    true
) ? trim((string) ($siteTarteaucitronConfig['icon_position'] ?? 'BottomRight')) : 'BottomRight';
$siteTarteaucitronConfig['user_config_json'] = trim((string) ($siteTarteaucitronConfig['user_config_json'] ?? '{}'));
if ($siteTarteaucitronConfig['user_config_json'] === '') {
    $siteTarteaucitronConfig['user_config_json'] = '{}';
}
$siteTarteaucitronConfig['services'] = array_values(array_filter(
    is_array($siteTarteaucitronOverride['services'] ?? null) ? $siteTarteaucitronOverride['services'] : [],
    static fn ($service): bool => is_scalar($service) && trim((string) $service) !== ''
));
$siteDiscussionsConfig = array_merge(
    $siteDiscussionsDefaults,
    array_intersect_key($siteDiscussionsOverride, $siteDiscussionsDefaults)
);
$siteDiscussionsConfig['recaptcha'] = array_merge(
    $siteDiscussionsRecaptchaDefaults,
    array_intersect_key($siteDiscussionsRecaptchaOverride, $siteDiscussionsRecaptchaDefaults)
);
$siteInstagramConfig = array_merge(
    $siteInstagramDefaults,
    array_intersect_key($siteInstagramOverride, $siteInstagramDefaults)
);
$siteLogAlertsConfig = array_merge(
    $siteLogAlertsDefaults,
    array_intersect_key($siteLogAlertsOverride, $siteLogAlertsDefaults)
);
$siteLogAlertsConfig['notify_on'] = strtolower(trim((string) ($siteLogAlertsConfig['notify_on'] ?? 'alerts')));
if (!in_array($siteLogAlertsConfig['notify_on'], ['alerts', 'always'], true)) {
    $siteLogAlertsConfig['notify_on'] = 'alerts';
}
$siteInstagramUsername = ltrim(trim((string) ($siteInstagramConfig['username'] ?? '')), '@');
if (preg_match('/^[A-Za-z0-9._]{1,30}$/', $siteInstagramUsername) !== 1) {
    $siteInstagramUsername = '';
}
$configuredInstagramUsername = $envInstagramUsername !== '' ? $envInstagramUsername : $siteInstagramUsername;
$configuredInstagramUserId = $envInstagramUserId !== '' ? $envInstagramUserId : trim((string) ($siteInstagramConfig['user_id'] ?? ''));
$configuredInstagramAccessToken = $envInstagramAccessToken !== '' ? $envInstagramAccessToken : trim((string) ($siteInstagramConfig['access_token'] ?? ''));

$normalizeAdminAllowedIp = static function (string $value): ?string {
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
};
$normalizeAdminAllowedIps = static function (mixed $rawAllowedIps) use ($normalizeAdminAllowedIp): array {
    $entries = is_array($rawAllowedIps)
        ? $rawAllowedIps
        : preg_split('/[\s,;]+/', (string) $rawAllowedIps, -1, PREG_SPLIT_NO_EMPTY);
    $entries = is_array($entries) ? $entries : [];

    $normalizedAllowedIps = [];
    foreach ($entries as $entry) {
        $normalizedAllowedIp = $normalizeAdminAllowedIp((string) $entry);
        if ($normalizedAllowedIp === null) {
            continue;
        }

        if (!in_array($normalizedAllowedIp, $normalizedAllowedIps, true)) {
            $normalizedAllowedIps[] = $normalizedAllowedIp;
        }
    }

    return $normalizedAllowedIps;
};
$adminAllowedIpsFromEnv = $normalizeAdminAllowedIps((string) env('ADMIN_ALLOWED_IPS', ''));
$adminAllowedIps = array_key_exists('allowed_ips', $adminOverride)
    ? $normalizeAdminAllowedIps($adminOverride['allowed_ips'])
    : $adminAllowedIpsFromEnv;

$adminIdentifier = trim((string) (
    $adminOverride['identifier']
    ?? $adminOverride['email']
    ?? env('ADMIN_IDENTIFIER', env('ADMIN_EMAIL', ''))
));
$adminPasswordHash = trim((string) ($adminOverride['password_hash'] ?? env('ADMIN_PASSWORD_HASH', '')));
$adminLocalPasswordlessLocalhost = array_key_exists('local_passwordless_localhost', $adminOverride)
    ? filter_var($adminOverride['local_passwordless_localhost'], FILTER_VALIDATE_BOOLEAN)
    : filter_var(env('ADMIN_LOCAL_PASSWORDLESS_LOCALHOST', false), FILTER_VALIDATE_BOOLEAN);
$normalizeAdminTotpSecret = static function (mixed $secret): string {
    $normalized = strtoupper(trim((string) $secret));
    $normalized = preg_replace('/[\s\-=]+/', '', $normalized) ?? $normalized;

    if ($normalized === '' || preg_match('/^[A-Z2-7]+$/', $normalized) !== 1) {
        return '';
    }

    return $normalized;
};
$adminTotpEnabled = array_key_exists('totp_enabled', $adminOverride)
    ? filter_var($adminOverride['totp_enabled'], FILTER_VALIDATE_BOOLEAN)
    : filter_var(env('ADMIN_TOTP_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
$adminTotpSecret = array_key_exists('totp_secret', $adminOverride)
    ? $normalizeAdminTotpSecret($adminOverride['totp_secret'])
    : $normalizeAdminTotpSecret(env('ADMIN_TOTP_SECRET', ''));
$adminTotpSkipLocalhost = filter_var(env('ADMIN_TOTP_SKIP_LOCALHOST', true), FILTER_VALIDATE_BOOLEAN);
$adminInactivityTimeoutSeconds = array_key_exists('inactivity_timeout_seconds', $adminOverride)
    ? (int) $adminOverride['inactivity_timeout_seconds']
    : (int) env('ADMIN_INACTIVITY_TIMEOUT_SECONDS', 7200);
$adminInactivityTimeoutSeconds = max(60, min(86400, $adminInactivityTimeoutSeconds));
$adminReauthTimeoutSeconds = array_key_exists('reauth_timeout_seconds', $adminOverride)
    ? (int) $adminOverride['reauth_timeout_seconds']
    : (int) env('ADMIN_REAUTH_TIMEOUT_SECONDS', 7200);
$adminReauthTimeoutSeconds = max(60, min(86400, $adminReauthTimeoutSeconds));
if ($adminReauthTimeoutSeconds > $adminInactivityTimeoutSeconds) {
    $adminReauthTimeoutSeconds = $adminInactivityTimeoutSeconds;
}
$adminLanguage = strtolower(trim((string) env('ADMIN_LANGUAGE', env('ADMIN_LANG', env('DEFAULT_LANG', 'fr')))));
if (!in_array($adminLanguage, ['fr', 'en', 'de'], true)) {
    $adminLanguage = 'fr';
}

$appEnv = defined('CARAMAGNOLS_LOCAL_DEV_ROUTER') ? 'development' : env('APP_ENV', 'development');

$appConfig = [
    'env' => $appEnv,
    'base_url' => env('BASE_URL', '/'),
    'default_lang' => env('DEFAULT_LANG', 'fr'),
    'database' => array_merge($databaseDefaults, $databaseOverride),
    'database_prefix' => $databasePrefixOverride ?? env('DB_TABLE_PREFIX', 'car_'),
    'editorial' => [
        'storage' => in_array(
            (string) env('EDITORIAL_STORAGE', 'json'),
            ['json', 'sql', 'dual-write'],
            true
        ) ? (string) env('EDITORIAL_STORAGE', 'json') : 'json',
        'schema_dir' => ROOT_PATH . '/sql/editorial',
    ],
    'mail' => [
        'smtp_host' => env('MAIL_SMTP_HOST', 'localhost'),
        'smtp_port' => (int) env('MAIL_SMTP_PORT', 25),
        'smtp_user' => env('MAIL_SMTP_USER', ''),
        'smtp_password' => env('MAIL_SMTP_PASSWORD', ''),
        'smtp_encryption' => env('MAIL_SMTP_ENCRYPTION', ''),
        'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Les Caramagnols'),
    ],
    'admin' => [
        'login_path' => env('ADMIN_LOGIN_PATH', 'admin'),
        'language' => $adminLanguage,
        'identifier' => $adminIdentifier,
        'email' => $adminIdentifier,
        'password_hash' => $adminPasswordHash,
        'session_key' => env('ADMIN_SESSION_KEY', 'caramagnols_admin'),
        'allowed_ips' => $adminAllowedIps,
        'trust_proxy_headers' => filter_var(
            env('ADMIN_TRUST_PROXY_HEADERS', env('TRUST_PROXY_HEADERS', false)),
            FILTER_VALIDATE_BOOLEAN
        ),
        'login_rate_limit_attempts' => max(1, (int) env('ADMIN_LOGIN_RATE_LIMIT_ATTEMPTS', 5)),
        'login_rate_limit_window' => max(60, (int) env('ADMIN_LOGIN_RATE_LIMIT_WINDOW', 900)),
        'local_passwordless_localhost' => (bool) $adminLocalPasswordlessLocalhost,
        'inactivity_timeout_seconds' => $adminInactivityTimeoutSeconds,
        'reauth_timeout_seconds' => $adminReauthTimeoutSeconds,
        'totp_enabled' => (bool) $adminTotpEnabled,
        'totp_secret' => $adminTotpSecret,
        'totp_period_seconds' => 30,
        'totp_allowed_drift_steps' => 1,
        'totp_skip_localhost' => (bool) $adminTotpSkipLocalhost,
    ],
    'blog' => [
        'mode' => env('BLOG_MODE', 'experimental'),
        'storage' => in_array(
            (string) env('BLOG_STORAGE', env('EDITORIAL_STORAGE', 'json')),
            ['json', 'sql', 'dual-write'],
            true
        ) ? (string) env('BLOG_STORAGE', env('EDITORIAL_STORAGE', 'json')) : 'json',
        'data_dir' => env('BLOG_DATA_DIR', ROOT_PATH . '/data/blog'),
    ],
    'site' => [
        'url' => [
            'domain' => trim((string) ($siteUrlOverride['domain'] ?? $siteUrlDefaults['domain'])),
            'ssl_domain' => trim((string) ($siteUrlOverride['ssl_domain'] ?? $siteUrlDefaults['ssl_domain'])),
            'base_path' => normalize_public_route((string) ($siteUrlOverride['base_path'] ?? $siteUrlDefaults['base_path'])) ?? '/',
        ],
        'head_metadata_html' => trim((string) ($siteOverride['head_metadata_html'] ?? '')),
        'tarteaucitron' => $siteTarteaucitronConfig,
        'discussions' => [
            'enabled' => (bool) ($siteDiscussionsConfig['enabled'] ?? true),
            'require_account' => (bool) ($siteDiscussionsConfig['require_account'] ?? false),
            'rate_limit_per_ip' => max(1, (int) ($siteDiscussionsConfig['rate_limit_per_ip'] ?? 6)),
            'rate_limit_window' => max(60, (int) ($siteDiscussionsConfig['rate_limit_window'] ?? 600)),
            'global_rate_limit_per_ip' => max(1, (int) ($siteDiscussionsConfig['global_rate_limit_per_ip'] ?? 20)),
            'global_rate_limit_window' => max(60, (int) ($siteDiscussionsConfig['global_rate_limit_window'] ?? 3600)),
            'min_form_fill_seconds' => max(0, (int) ($siteDiscussionsConfig['min_form_fill_seconds'] ?? 3)),
            'max_form_age_seconds' => max(60, (int) ($siteDiscussionsConfig['max_form_age_seconds'] ?? 7200)),
            'honeypot_field' => trim((string) ($siteDiscussionsConfig['honeypot_field'] ?? 'website')),
            'recaptcha' => [
                'enabled' => (bool) ($siteDiscussionsConfig['recaptcha']['enabled'] ?? false),
                'site_key' => $envDiscussionRecaptchaSiteKey !== ''
                    ? $envDiscussionRecaptchaSiteKey
                    : trim((string) ($siteDiscussionsConfig['recaptcha']['site_key'] ?? '')),
                'secret_key' => $envDiscussionRecaptchaSecretKey !== ''
                    ? $envDiscussionRecaptchaSecretKey
                    : trim((string) ($siteDiscussionsConfig['recaptcha']['secret_key'] ?? '')),
                'minimum_score' => max(0.0, min(1.0, (float) ($siteDiscussionsConfig['recaptcha']['minimum_score'] ?? 0.5))),
                'timeout_seconds' => max(3, min(20, (int) ($siteDiscussionsConfig['recaptcha']['timeout_seconds'] ?? 8))),
            ],
        ],
        'instagram' => [
            'enabled' => (bool) ($siteInstagramConfig['enabled'] ?? false),
            'username' => $configuredInstagramUsername,
            'user_id' => $configuredInstagramUserId,
            'access_token' => $configuredInstagramAccessToken,
            'limit' => max(1, min(20, (int) ($siteInstagramConfig['limit'] ?? 6))),
            'rotation_interval_ms' => max(2500, min(30000, (int) ($siteInstagramConfig['rotation_interval_ms'] ?? 5500))),
            'cache_ttl_seconds' => max(60, min(86400, (int) ($siteInstagramConfig['cache_ttl_seconds'] ?? 1800))),
            'timeout_seconds' => max(3, min(20, (int) ($siteInstagramConfig['timeout_seconds'] ?? 8))),
            'cache_path' => ROOT_PATH . '/var/cache/instagram-feed.json',
        ],
        'log_alerts' => $siteLogAlertsConfig,
        'i18n_overrides' => $siteI18nOverrides,
    ],
    'security' => [
        'rate_limit_dir' => ROOT_PATH . '/var/rate-limits',
        'trust_proxy_headers' => filter_var(
            env('TRUST_PROXY_HEADERS', env('ADMIN_TRUST_PROXY_HEADERS', false)),
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
    'logging' => [
        'retention_files' => max(2, min(90, (int) env('LOG_RETENTION_FILES', 14))),
        'rotation_max_bytes' => max(262144, min(104857600, (int) env('LOG_ROTATION_MAX_BYTES', 5242880))),
    ],
];

define('APP_ENV', $appConfig['env']);
define('BASE_URL', $appConfig['base_url']);
define('DEFAULT_LANG', $appConfig['default_lang']);
define('DB_TABLE_PREFIX', $appConfig['database_prefix']);
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
