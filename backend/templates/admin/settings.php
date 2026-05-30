<?php
$view = is_array($settingsView ?? null) ? $settingsView : [];
$database = is_array($view['database'] ?? null) ? $view['database'] : [];
$admin = is_array($view['admin'] ?? null) ? $view['admin'] : [];
$url = is_array($view['url'] ?? null) ? $view['url'] : [];
$head = is_array($view['head'] ?? null) ? $view['head'] : [];
$tarteaucitron = is_array($view['tarteaucitron'] ?? null) ? $view['tarteaucitron'] : [];
$discussions = is_array($view['discussions'] ?? null) ? $view['discussions'] : [];
$instagram = is_array($view['instagram'] ?? null) ? $view['instagram'] : [];
$privateMail = is_array($view['privateMail'] ?? null) ? $view['privateMail'] : [];
$logAlerts = is_array($view['logAlerts'] ?? null) ? $view['logAlerts'] : [];
$backup = is_array($view['backup'] ?? null) ? $view['backup'] : [];
$cronCenter = is_array($view['cronCenter'] ?? null) ? $view['cronCenter'] : [];
$translations = is_array($view['translations'] ?? null) ? $view['translations'] : [];
$storage = is_array($view['storage'] ?? null) ? $view['storage'] : [];
$openSection = is_string($openSettingsSection ?? null) ? (string) $openSettingsSection : null;
$autoscrollTarget = is_string($settingsAutoscrollTarget ?? null) ? (string) $settingsAutoscrollTarget : null;
$settingsAction = (string) ($adminSettingsUrl ?? admin_url('settings'));
$translate = static function (string $key, string $fallback): string {
    if (function_exists('admin_translate')) {
        return admin_translate($key, $fallback);
    }

    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};
$renderSecretToggleButton = static function (string $inputId) use ($translate): string {
    $showLabel = htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8');
    $hideLabel = htmlspecialchars($translate('TXT_ADMIN_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8');
    $safeInputId = htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8');

    return sprintf(
        '<button class="admin-password-toggle" type="button" data-admin-password-toggle data-admin-password-show="%s" data-admin-password-hide="%s" aria-controls="%s" aria-pressed="false">%s</button>',
        $showLabel,
        $hideLabel,
        $safeInputId,
        $showLabel
    );
};
$secretMask = '**********';
$secretMaskAttribute = static fn (bool $configured): string => $configured ? ' data-admin-secret-mask="true"' : '';
$adminInterfaceLanguage = function_exists('admin_interface_language') ? admin_interface_language() : 'fr';
$settingChoiceLabels = [
    'orientation' => [
        'fr' => ['bottom' => 'en bas', 'top' => 'en haut', 'middle' => 'au centre'],
        'en' => ['bottom' => 'bottom', 'top' => 'top', 'middle' => 'middle'],
        'de' => ['bottom' => 'unten', 'top' => 'oben', 'middle' => 'mittig'],
    ],
    'iconPosition' => [
        'fr' => [
            'BottomRight' => 'en bas à droite',
            'BottomLeft' => 'en bas à gauche',
            'TopRight' => 'en haut à droite',
            'TopLeft' => 'en haut à gauche',
        ],
        'en' => [
            'BottomRight' => 'bottom right',
            'BottomLeft' => 'bottom left',
            'TopRight' => 'top right',
            'TopLeft' => 'top left',
        ],
        'de' => [
            'BottomRight' => 'unten rechts',
            'BottomLeft' => 'unten links',
            'TopRight' => 'oben rechts',
            'TopLeft' => 'oben links',
        ],
    ],
];
$settingChoiceLabel = static function (string $group, string $value) use ($adminInterfaceLanguage, $settingChoiceLabels): string {
    $labelsByLanguage = is_array($settingChoiceLabels[$group] ?? null) ? $settingChoiceLabels[$group] : [];
    $labels = is_array($labelsByLanguage[$adminInterfaceLanguage] ?? null)
        ? $labelsByLanguage[$adminInterfaceLanguage]
        : (is_array($labelsByLanguage['fr'] ?? null) ? $labelsByLanguage['fr'] : []);

    return is_string($labels[$value] ?? null) ? $labels[$value] : $value;
};
$metadataConfigured = trim((string) ($head['metadataHtml'] ?? '')) !== '';
$tarteaucitronServices = array_values(array_filter(
    is_array($tarteaucitron['services'] ?? null) ? $tarteaucitron['services'] : [],
    static fn ($service): bool => is_string($service) && trim($service) !== ''
));
$tarteaucitronServiceRows = $tarteaucitronServices !== [] ? $tarteaucitronServices : [''];
$tarteaucitronServiceCount = count($tarteaucitronServices);
$tarteaucitronUserConfigJson = trim((string) ($tarteaucitron['userConfigJson'] ?? '{}'));
if ($tarteaucitronUserConfigJson === '') {
    $tarteaucitronUserConfigJson = '{}';
}
$tarteaucitronUserConfigValues = json_decode($tarteaucitronUserConfigJson, true);
$tarteaucitronUserConfigCount = is_array($tarteaucitronUserConfigValues) && !array_is_list($tarteaucitronUserConfigValues)
    ? count($tarteaucitronUserConfigValues)
    : 0;
$discussionSummary = !empty($discussions['enabled'])
    ? $translate('TXT_ADMIN_SETTINGS_STATUS_OPEN', 'Ouvert')
    : $translate('TXT_ADMIN_SETTINGS_STATUS_CLOSED', 'Fermé');
$discussionRecaptchaSummary = !empty($discussions['recaptchaEnabled'])
    ? $translate('TXT_ADMIN_SETTINGS_RECAPTCHA_ENABLED', 'reCAPTCHA actif')
    : $translate('TXT_ADMIN_SETTINGS_RECAPTCHA_DISABLED', 'reCAPTCHA inactif');
$discussionSecretKeySummary = !empty($discussions['recaptchaSecretKeyConfigured'])
    ? $translate('TXT_ADMIN_SETTINGS_SECRET_KEY_REGISTERED', 'enregistrée')
    : $translate('TXT_ADMIN_SETTINGS_SECRET_KEY_MISSING', 'absente');
$instagramSummary = !empty($instagram['enabled'])
    ? $translate('TXT_ADMIN_SETTINGS_STATUS_ACTIVE', 'Actif')
    : $translate('TXT_ADMIN_SETTINGS_STATUS_INACTIVE', 'Inactif');
$privateMailSummary = !empty($privateMail['enabled'])
    ? $translate('TXT_ADMIN_SETTINGS_STATUS_ACTIVE', 'Actif')
    : $translate('TXT_ADMIN_SETTINGS_STATUS_INACTIVE', 'Inactif');
$privateMailTemplates = is_array($privateMail['templates'] ?? null) ? $privateMail['templates'] : [];
$logAlertsNotifyOn = strtolower(trim((string) ($logAlerts['notifyOn'] ?? 'alerts')));
if (!in_array($logAlertsNotifyOn, ['alerts', 'always'], true)) {
    $logAlertsNotifyOn = 'alerts';
}
$scheduledBlogPublishPhpBinary = trim((string) ($logAlerts['blogPublishPhpBinary'] ?? 'php'));
$scheduledBlogPublishScriptPath = trim((string) ($logAlerts['blogPublishScriptPath'] ?? ''));
$scheduledBlogPublishCronCommand = trim((string) ($logAlerts['blogPublishCronCommand'] ?? ''));
$backupDatabase = is_array($backup['database'] ?? null) ? $backup['database'] : [];
$backupRoot = trim((string) ($backup['backupRoot'] ?? ''));
$backupRetentionDays = max(1, (int) ($backup['retentionDays'] ?? 14));
$backupCronCommand = trim((string) ($backup['cronCommand'] ?? ''));
$backupDryRunCommand = trim((string) ($backup['dryRunCommand'] ?? ''));
$backupPhpBinary = trim((string) ($backup['phpBinary'] ?? 'php'));
$backupTarBinary = trim((string) ($backup['tarBinary'] ?? 'tar'));
$backupMysqldumpBinary = trim((string) ($backup['mysqldumpBinary'] ?? 'mysqldump'));
$backupScriptPath = trim((string) ($backup['scriptPath'] ?? ''));
$backupFilesDirectory = trim((string) ($backup['filesDirectory'] ?? ''));
$backupSqlDirectory = trim((string) ($backup['sqlDirectory'] ?? ''));
$backupManifestDirectory = trim((string) ($backup['manifestDirectory'] ?? ''));
$backupRootOutsideRoot = !empty($backup['backupRootOutsideRoot']);
$backupDatabaseConfigured = !empty($backupDatabase['configured']);
$backupDatabasePasswordConfigured = !empty($backupDatabase['passwordConfigured']);
$cronAvailable = !empty($cronCenter['available']);
$cronScheduler = is_array($cronCenter['scheduler'] ?? null) ? $cronCenter['scheduler'] : [];
$cronJobs = is_array($cronCenter['jobs'] ?? null) ? $cronCenter['jobs'] : [];
$cronRuns = is_array($cronCenter['recentRuns'] ?? null) ? $cronCenter['recentRuns'] : [];
$cronAllowedScripts = is_array($cronCenter['allowedScripts'] ?? null) ? $cronCenter['allowedScripts'] : [];
$cronEmptyJobForm = is_array($cronCenter['emptyJobForm'] ?? null) ? $cronCenter['emptyJobForm'] : [];
$cronOvhCommand = trim((string) ($cronCenter['ovhCronCommand'] ?? ''));
$cronRunnerPath = trim((string) ($cronCenter['runnerPath'] ?? ''));
$cronPhpBinary = trim((string) ($cronCenter['phpBinary'] ?? 'php'));
$cronLogsUrl = trim((string) ($cronCenter['logsUrl'] ?? ''));
$cronError = is_string($cronCenter['error'] ?? null) ? (string) $cronCenter['error'] : null;
$cronActiveJobsCount = 0;
$cronFailedJobsCount = 0;
foreach ($cronJobs as $cronJob) {
    if (!is_array($cronJob)) {
        continue;
    }

    if ((string) ($cronJob['status'] ?? '') === 'active') {
        $cronActiveJobsCount++;
    }

    if (in_array((string) ($cronJob['last_status'] ?? ''), ['failed', 'timeout'], true)) {
        $cronFailedJobsCount++;
    }
}
$cronStatusLabel = trim((string) ($cronScheduler['status'] ?? ''));
if ($cronStatusLabel === '') {
    $cronStatusLabel = $cronAvailable
        ? $translate('TXT_ADMIN_SETTINGS_CRON_STATUS_NEVER_RUN', 'jamais lancé')
        : $translate('TXT_ADMIN_SETTINGS_CRON_STATUS_UNAVAILABLE', 'indisponible');
}
$formatCronDate = static function (mixed $value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '—';
    }

    $timestamp = strtotime($raw);
    if (!is_int($timestamp)) {
        return $raw;
    }

    return date('d/m/Y H:i:s', $timestamp);
};
$logAlertsModeSummary = $logAlertsNotifyOn === 'always'
    ? $translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_ALWAYS', 'Toujours notifier')
    : $translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_ALERTS', 'Uniquement en cas d’alerte');
$instagramAccount = trim((string) ($instagram['username'] ?? ''));
$instagramAccountSummary = $instagramAccount !== ''
    ? '@' . $instagramAccount
    : $translate('TXT_ADMIN_SETTINGS_INSTAGRAM_TOKEN_DERIVED', 'Compte dérivé du token');
$translationLanguages = is_array($translations['languages'] ?? null) ? $translations['languages'] : [];
$translationCounts = is_array($translations['countByLanguage'] ?? null) ? $translations['countByLanguage'] : [];
$translationDictionaryCounts = is_array($translations['dictionaryCountByLanguage'] ?? null) ? $translations['dictionaryCountByLanguage'] : [];
$translationSummaryParts = [];
foreach ($translationLanguages as $translationLanguage) {
    $language = strtolower(trim((string) $translationLanguage));
    if ($language === '') {
        continue;
    }

    $translationSummaryParts[] = strtoupper($language) . ' ' . (int) ($translationCounts[$language] ?? 0);
}
$translationSummary = $translationSummaryParts !== []
    ? implode(' · ', $translationSummaryParts)
    : $translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_NONE', 'Aucun override');
$translationKnownKeysCount = max(0, (int) ($translations['knownKeysCount'] ?? 0));
$databaseSummary = trim((string) (($database['host'] ?? '') . ':' . ($database['port'] ?? '')));
$adminSummary = trim((string) ($admin['identifier'] ?? ''));
$adminAllowedIpsRaw = trim((string) ($admin['allowedIps'] ?? ''));
$adminAllowedIps = preg_split('/[\s,;]+/', $adminAllowedIpsRaw, -1, PREG_SPLIT_NO_EMPTY);
$adminAllowedIps = is_array($adminAllowedIps) ? array_values(array_unique(array_map('trim', $adminAllowedIps))) : [];
$adminAllowedIpsSummary = $adminAllowedIps === []
    ? $translate('TXT_ADMIN_SETTINGS_ADMIN_IP_FILTER_DISABLED', 'Filtrage IP désactivé')
    : sprintf(
        $translate('TXT_ADMIN_SETTINGS_ADMIN_ALLOWED_IPS_TEMPLATE', '%d IP autorisée%s'),
        count($adminAllowedIps),
        count($adminAllowedIps) > 1 ? 's' : ''
    );
$adminTotpEnabled = !empty($admin['totpEnabled']);
$adminTotpSummary = $adminTotpEnabled
    ? $translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_ENABLED', 'Code 2FA actif')
    : $translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_DISABLED', 'Code 2FA inactif');
$adminTotpSecretSummary = !empty($admin['totpSecretConfigured'])
    ? $translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_SECRET_SET', 'secret enregistré')
    : $translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_SECRET_MISSING', 'secret absent');
$adminInactivitySummary = max(60, (int) ($admin['inactivityTimeoutSeconds'] ?? 1200));
$adminReauthSummary = max(60, (int) ($admin['reauthTimeoutSeconds'] ?? 600));
$urlSummaryParts = [];
if (trim((string) ($url['domain'] ?? '')) !== '') {
    $urlSummaryParts[] = 'HTTP ' . trim((string) $url['domain']);
}
if (trim((string) ($url['sslDomain'] ?? '')) !== '') {
    $urlSummaryParts[] = 'HTTPS ' . trim((string) $url['sslDomain']);
}
$urlSummary = $urlSummaryParts !== []
    ? implode(' · ', $urlSummaryParts)
    : $translate('TXT_ADMIN_SETTINGS_URL_INHERITED', 'Domaines hérités de la requête ou de l’environnement');
$autostartAttr = static function (string $section, ?string $openSection, ?string $autoscrollTarget): string {
    if ($openSection !== $section) {
        return '';
    }

    $attributes = ' data-region-modal-autostart="true"';
    if ($autoscrollTarget !== null && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,80}$/', $autoscrollTarget) === 1) {
        $attributes .= ' data-region-modal-scroll-target="' . htmlspecialchars($autoscrollTarget, ENT_QUOTES, 'UTF-8') . '"';
    }

    return $attributes;
};
?>
<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success" role="status"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error" role="alert"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="cards-grid settings-sections-grid">
  <article class="card settings-intro-card">
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INTRO_TITLE', 'Paramètres d’exploitation'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="notice-muted">
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INTRO_HELP_PREFIX', 'Chaque bloc s’ouvre dans une popup dédiée. Les overrides sensibles restent écrits hors webroot dans'), ENT_QUOTES, 'UTF-8'); ?>
      <code><?php echo htmlspecialchars((string) ($storage['databaseOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>,
      <code><?php echo htmlspecialchars((string) ($storage['adminOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INTRO_HELP_MIDDLE', 'et'), ENT_QUOTES, 'UTF-8'); ?> <code><?php echo htmlspecialchars((string) ($storage['siteOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>.
    </p>
    <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="actions-inline" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="settings_section" value="security" />
      <button type="submit" class="button-muted" name="settings_action" value="cache_clear"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CACHE_CLEAR', 'Vider le cache'), ENT_QUOTES, 'UTF-8'); ?></button>
      <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CACHE_CLEAR_HELP', 'Vide les caches runtime (pages/navigation/traductions) et supprime le cache Instagram disque.'), ENT_QUOTES, 'UTF-8'); ?></small>
    </form>
  </article>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-database"
    data-settings-section-card="database"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('database', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_CARD_TITLE', 'Base SQL'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($databaseSummary !== ':' ? $databaseSummary : $translate('TXT_ADMIN_SETTINGS_SQL_CONNECTION_TO_COMPLETE', 'Connexion SQL à compléter'), ENT_QUOTES, 'UTF-8'); ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_DATABASE_CARD_DETAILS', 'Base %1$s · préfixe %2$s'),
              (string) ($database['name'] ?? ''),
              (string) ($database['prefix'] ?? '')
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_CARD_CTA', 'Configurer la base SQL'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-admin"
    data-settings-section-card="admin"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('admin', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_CARD_TITLE', 'Connexion admin'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($adminSummary !== '' ? $adminSummary : $translate('TXT_ADMIN_SETTINGS_ADMIN_EMAIL_TO_DEFINE', 'E-mail admin à définir'), ENT_QUOTES, 'UTF-8'); ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_ADMIN_PASSWORD_SUMMARY', 'Mot de passe %s'),
              !empty($admin['passwordConfigured'])
                  ? $translate('TXT_ADMIN_SETTINGS_PASSWORD_ALREADY_SET', 'déjà enregistré')
                  : $translate('TXT_ADMIN_SETTINGS_PASSWORD_TO_CONFIGURE', 'à configurer')
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?><br />
      <?php echo htmlspecialchars($adminAllowedIpsSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo htmlspecialchars($adminTotpSummary . ' · ' . $adminTotpSecretSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_ADMIN_TIMEOUTS_TEMPLATE', 'Timeout %1$ss · Ré-auth %2$ss'),
              (int) $adminInactivitySummary,
              (int) $adminReauthSummary
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_CARD_CTA', 'Configurer l’accès admin'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-url"
    data-settings-section-card="url"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('url', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_CARD_TITLE', 'URL publique'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($urlSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_URL_BASE_PATH_TEMPLATE', 'Chemin de base %s'),
              (string) ($url['basePath'] ?? '/')
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_CARD_CTA', 'Configurer les URL du site'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-head"
    data-settings-section-card="head"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('head', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_CARD_TITLE', 'Métadonnées globales du head'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_CARD_SUMMARY', 'Injection globale de balises meta, link canonical/alternate et JSON-LD.'), ENT_QUOTES, 'UTF-8'); ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_HEAD_CARD_STATE_TEMPLATE', 'État: %s'),
              $metadataConfigured
                  ? $translate('TXT_ADMIN_SETTINGS_HEAD_STATE_ACTIVE', 'configuration active')
                  : $translate('TXT_ADMIN_SETTINGS_HEAD_STATE_EMPTY', 'aucune balise enregistrée')
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_CARD_CTA', 'Gérer les métadonnées'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-tarteaucitron"
    data-settings-section-card="tarteaucitron"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('tarteaucitron', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_CARD_TITLE', 'Gestion tarteaucitron'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_BANNER_TEMPLATE', '%1$s · bannière %2$s'),
              !empty($tarteaucitron['enabled'])
                  ? $translate('TXT_ADMIN_SETTINGS_ENABLED', 'Activé')
                  : $translate('TXT_ADMIN_SETTINGS_DISABLED', 'Désactivé'),
              $settingChoiceLabel('orientation', (string) ($tarteaucitron['orientation'] ?? 'bottom'))
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_ICON_TEMPLATE', 'Icône %1$s · %2$d service%s'),
              $settingChoiceLabel('iconPosition', (string) ($tarteaucitron['iconPosition'] ?? 'BottomRight')),
              $tarteaucitronServiceCount,
              $tarteaucitronServiceCount > 1 ? 's' : ''
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_JS_VARIABLES_TEMPLATE', 'Variables JS: %d'),
              (int) $tarteaucitronUserConfigCount
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_CARD_CTA', 'Configurer le consentement'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-discussions"
    data-settings-section-card="discussions"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('discussions', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_CARD_TITLE', 'Discussions et anti-bot'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_LOCAL_LIMIT_TEMPLATE', '%1$s · limite locale %2$d/%3$ds'),
              $discussionSummary,
              (int) ($discussions['rateLimitPerIp'] ?? 6),
              (int) ($discussions['rateLimitWindow'] ?? 600)
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_TEMPLATE', '%1$s · clé secrète %2$s'),
              $discussionRecaptchaSummary,
              $discussionSecretKeySummary
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_CARD_CTA', 'Configurer la modération publique'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-instagram"
    data-settings-section-card="instagram"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('instagram', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_CARD_TITLE', 'Flux Instagram accueil'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($instagramSummary, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($instagramAccountSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_INSTAGRAM_CARD_DETAILS', '%1$d post(s) · rotation %2$d ms · token %3$s'),
              (int) ($instagram['limit'] ?? 6),
              (int) ($instagram['rotationIntervalMs'] ?? 5500),
              !empty($instagram['accessTokenConfigured'])
                  ? $translate('TXT_ADMIN_SETTINGS_TOKEN_REGISTERED', 'enregistré')
                  : $translate('TXT_ADMIN_SETTINGS_TOKEN_MISSING', 'absent')
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_CARD_CTA', 'Configurer Instagram'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-observability"
    data-settings-section-card="observability"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('observability', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CARD_EYEBROW', 'Section'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_CARD_TITLE', 'Observabilité ops'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_CARD_SUMMARY', 'Canal logs'), ENT_QUOTES, 'UTF-8'); ?> :
      <?php echo htmlspecialchars($logAlertsModeSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_CARD_DETAIL', 'Pilotage du déclenchement webhook/email pour le scheduler de surveillance.'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_CARD_CTA', 'Configurer les alertes logs'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-backup"
    data-settings-section-card="backup"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('backup', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_CARD_TITLE', 'Sauvegardes'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_CARD_SUMMARY', 'Dossier production + dump SQL'), ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_CARD_ROOT', 'Racine'), ENT_QUOTES, 'UTF-8'); ?>:
      <?php echo htmlspecialchars($backupRoot !== '' ? $backupRoot : '-', ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_CARD_RETENTION', 'Rétention'), ENT_QUOTES, 'UTF-8'); ?>:
      <?php echo (int) $backupRetentionDays; ?> jour<?php echo $backupRetentionDays > 1 ? 's' : ''; ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_CARD_CTA', 'Voir les commandes de backup'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-cron"
    data-settings-section-card="cron"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('cron', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_CARD_EYEBROW', 'Planification'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_CARD_TITLE', 'Cron Center'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_CARD_SUMMARY', 'Coordination scheduler + jobs PHP locaux'), ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo (int) $cronActiveJobsCount; ?> job<?php echo $cronActiveJobsCount > 1 ? 's' : ''; ?> actif<?php echo $cronActiveJobsCount > 1 ? 's' : ''; ?> ·
      <?php echo htmlspecialchars($cronStatusLabel, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo (int) $cronFailedJobsCount; ?> job<?php echo $cronFailedJobsCount > 1 ? 's' : ''; ?> en alerte
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_CARD_CTA', 'Ouvrir le Cron Center'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-translations"
    data-settings-section-card="translations"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('translations', $openSection, $autoscrollTarget); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_CARD_TITLE', 'Traductions globales'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($translationSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php
      echo htmlspecialchars(
          sprintf(
              $translate('TXT_ADMIN_SETTINGS_KNOWN_KEYS_TEMPLATE', '%d clé(s) connues dans le dictionnaire principal.'),
              (int) $translationKnownKeysCount
          ),
          ENT_QUOTES,
          'UTF-8'
      );
      ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_CARD_CTA', 'Configurer les overrides i18n'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-security"
    data-settings-section-card="security"
    aria-haspopup="dialog"
  >
    <p class="settings-section-card__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INFORMATION_EYEBROW', 'Information'), ENT_QUOTES, 'UTF-8'); ?></p>
    <h2 class="settings-section-card__title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_CARD_TITLE', 'Règles de sécurité'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_CARD_SUMMARY', 'Hash du mot de passe admin, mot de passe SQL masqué, overrides hors webroot et journalisation sensible.'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <span class="settings-section-card__cta"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_CARD_CTA', 'Voir les règles'), ENT_QUOTES, 'UTF-8'); ?></span>
  </button>
</section>

<dialog class="region-modal settings-dialog" id="settings-dialog-database" aria-labelledby="settings-dialog-database-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="database" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-database-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_DIALOG_TITLE', 'Base SQL'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_DIALOG_LEAD', 'Connexion principale, port, base, identifiant, mot de passe et préfixe des tables.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="database_host"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_HOST_LABEL', 'Adresse'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="database_host" name="database[host]" type="text" value="<?php echo htmlspecialchars((string) ($database['host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_port">Port</label>
            <input id="database_port" name="database[port]" type="text" inputmode="numeric" value="<?php echo htmlspecialchars((string) ($database['port'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_name"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_NAME_LABEL', 'Nom de base'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="database_name" name="database[name]" type="text" value="<?php echo htmlspecialchars((string) ($database['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_user"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_USER_LABEL', 'Identifiant SQL'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="database_user" name="database[user]" type="text" value="<?php echo htmlspecialchars((string) ($database['user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_password"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_PASSWORD_LABEL', 'Mot de passe SQL'), ENT_QUOTES, 'UTF-8'); ?></label>
            <div class="admin-password-field">
              <input id="database_password" name="database[password]" type="password" value="<?php echo htmlspecialchars((string) ($database['password'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-password"<?php echo $secretMaskAttribute(!empty($database['passwordConfigured'])); ?> />
              <?php echo $renderSecretToggleButton('database_password'); ?>
            </div>
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_PASSWORD_HELP', 'Masqué côté interface. Laisser vide pour conserver la valeur enregistrée'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($database['passwordConfigured']) ? ' (' . htmlspecialchars((string) ($database['passwordMask'] ?? '********'), ENT_QUOTES, 'UTF-8') . ')' : ''; ?>.</small>
          </div>
          <div class="field">
            <label for="database_prefix"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_PREFIX_LABEL', 'Préfixe des tables'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="database_prefix" name="database[prefix]" type="text" value="<?php echo htmlspecialchars((string) ($database['prefix'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DATABASE_SAVE', 'Enregistrer la base SQL'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-url" aria-labelledby="settings-dialog-url-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="url" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-url-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_DIALOG_TITLE', 'URL publique'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_DIALOG_LEAD', 'Domaine HTTP, domaine SSL et chemin de base utilisés pour générer les URLs du site et de l’admin.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="url_domain"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_DOMAIN_LABEL', 'Domaine'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="url_domain" name="url[domain]" type="text" value="<?php echo htmlspecialchars((string) ($url['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="example.com" />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_DOMAIN_HELP', 'Sans protocole. Tu peux aussi coller une URL complète, seul le domaine sera conservé.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="url_ssl_domain"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_SSL_DOMAIN_LABEL', 'Domaine SSL'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="url_ssl_domain" name="url[ssl_domain]" type="text" value="<?php echo htmlspecialchars((string) ($url['sslDomain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="secure.example.com" />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_SSL_DOMAIN_HELP', 'Utilisé en HTTPS. Laisse vide pour réutiliser le domaine standard.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="url_base_path"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_BASE_PATH_LABEL', 'Chemin de base'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="url_base_path" name="url[base_path]" type="text" value="<?php echo htmlspecialchars((string) ($url['basePath'] ?? '/'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="/" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_BASE_PATH_HELP', 'Le bon caractère par défaut est /. Les saisies en \\... ou /... sont normalisées.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_URL_SAVE', 'Enregistrer les URL'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-admin" aria-labelledby="settings-dialog-admin-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="admin" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-admin-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_DIALOG_TITLE', 'Connexion admin'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_DIALOG_LEAD', 'Identifiant admin par e-mail et mot de passe hashé à la sauvegarde.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label for="admin_identifier"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_IDENTIFIER_LABEL', 'E-mail admin'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="admin_identifier" name="admin[identifier]" type="email" inputmode="email" value="<?php echo htmlspecialchars((string) ($admin['identifier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
        </div>
        <div class="field">
          <label for="admin_password"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_PASSWORD_LABEL', 'Mot de passe admin'), ENT_QUOTES, 'UTF-8'); ?></label>
          <div class="admin-password-field">
            <input id="admin_password" name="admin[password]" type="password" value="<?php echo htmlspecialchars((string) ($admin['password'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-password"<?php echo $secretMaskAttribute(!empty($admin['passwordConfigured'])); ?> />
            <button
              class="admin-password-toggle"
              type="button"
              data-admin-password-toggle
              data-admin-password-show="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>"
              data-admin-password-hide="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8'); ?>"
              aria-controls="admin_password"
              aria-pressed="false"
            >
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
          </div>
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_PASSWORD_HELP', 'Hashé avant sauvegarde. Laisser vide pour conserver le mot de passe actuel'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($admin['passwordConfigured']) ? ' (' . htmlspecialchars($translate('TXT_ADMIN_SETTINGS_PASSWORD_ALREADY_SET', 'déjà enregistré'), ENT_QUOTES, 'UTF-8') . ')' : ''; ?>.</small>
        </div>
        <div class="field">
          <label for="admin_allowed_ips"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_ALLOWED_IPS_LABEL', 'IP autorisées'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="admin_allowed_ips" name="admin[allowed_ips]" type="text" value="<?php echo htmlspecialchars((string) ($admin['allowedIps'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="203.0.113.10, 198.51.100.0/24" />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_ALLOWED_IPS_HELP', 'Sépare les IP IPv4/IPv6 ou plages CIDR avec des virgules. Laisse vide pour autoriser toutes les IP.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="admin[totp_enabled]" value="0" />
            <input type="checkbox" name="admin[totp_enabled]" value="1"<?php echo !empty($admin['totpEnabled']) ? ' checked' : ''; ?> />
            <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_ENABLED_LABEL', 'Activer le Code 2FA pour l’admin (désactivé par défaut)'), ENT_QUOTES, 'UTF-8'); ?></span>
          </label>
        </div>
        <div class="field">
          <label for="admin_totp_secret"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_SECRET_LABEL', 'Secret Code 2FA (Base32)'), ENT_QUOTES, 'UTF-8'); ?></label>
          <div class="admin-password-field">
            <input id="admin_totp_secret" name="admin[totp_secret]" type="password" value="<?php echo htmlspecialchars((string) ($admin['totpSecret'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-password" placeholder="JBSWY3DPEHPK3PXP"<?php echo $secretMaskAttribute(!empty($admin['totpSecretConfigured'])); ?> />
            <button
              class="admin-password-toggle"
              type="button"
              data-admin-password-toggle
              data-admin-password-show="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>"
              data-admin-password-hide="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8'); ?>"
              aria-controls="admin_totp_secret"
              aria-pressed="false"
            >
              <?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
          </div>
          <div class="actions-inline admin-secret-actions">
            <button
              type="button"
              class="button-muted button-small"
              data-admin-totp-generate
              aria-controls="admin_totp_secret"
            >
              <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_GENERATE', 'Générer un secret aléatoire'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
          </div>
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_SECRET_HELP', 'Laisser vide pour conserver le secret actuel. Pour désactiver le TOTP, décoche simplement la case ci-dessus'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($admin['totpSecretConfigured']) ? ' (' . htmlspecialchars($translate('TXT_ADMIN_SETTINGS_PASSWORD_ALREADY_SET', 'déjà enregistré'), ENT_QUOTES, 'UTF-8') . ')' : ''; ?>. <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_TOTP_SECRET_FORMAT_HELP', 'Activation possible uniquement avec un secret Base32 valide (A-Z, 2-7), au moins 16 caractères.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="admin_inactivity_timeout_seconds"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_INACTIVITY_TIMEOUT_LABEL', 'Timeout d’inactivité (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="admin_inactivity_timeout_seconds" name="admin[inactivity_timeout_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($admin['inactivityTimeoutSeconds'] ?? 1200), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_INACTIVITY_TIMEOUT_HELP', '1200 = 20 minutes.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="admin_reauth_timeout_seconds"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_REAUTH_TIMEOUT_LABEL', 'Fenêtre de ré-authentification (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="admin_reauth_timeout_seconds" name="admin[reauth_timeout_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($admin['reauthTimeoutSeconds'] ?? 600), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_REAUTH_TIMEOUT_HELP', 'Doit être inférieure ou égale au timeout d’inactivité.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_ADMIN_SAVE', 'Enregistrer l’accès admin'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-head" aria-labelledby="settings-dialog-head-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="head" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-head-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_DIALOG_TITLE', 'Métadonnées globales du head'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_DIALOG_LEAD', 'Injection globale contrôlée des balises meta, link canonical/alternate et scripts JSON-LD.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label for="head_metadata_html"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_METADATA_LABEL', 'Balises à injecter dans le head public'), ENT_QUOTES, 'UTF-8'); ?></label>
          <textarea id="head_metadata_html" name="head[metadata_html]" rows="12" spellcheck="false"><?php echo htmlspecialchars((string) ($head['metadataHtml'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_METADATA_HELP', 'Balises autorisées : meta, link rel canonical|alternate et script type application/ld+json. Tout le reste est ignoré à la sauvegarde.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_HEAD_SAVE', 'Enregistrer les métadonnées'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-tarteaucitron" aria-labelledby="settings-dialog-tarteaucitron-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="tarteaucitron" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-tarteaucitron-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_DIALOG_TITLE', 'Gestion tarteaucitron'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_DIALOG_LEAD', 'Pilotage du bandeau de consentement, de l’icône et des consent modes exposés au site public.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="tarteaucitron[enabled]" value="0" />
            <input type="checkbox" name="tarteaucitron[enabled]" value="1"<?php echo !empty($tarteaucitron['enabled']) ? ' checked' : ''; ?> />
            <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_ENABLED_LABEL', 'Activer tarteaucitron sur le site public'), ENT_QUOTES, 'UTF-8'); ?></span>
          </label>
        </div>
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="tarteaucitron_privacy_url"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_PRIVACY_URL_LABEL', 'URL politique de confidentialité'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="tarteaucitron_privacy_url" name="tarteaucitron[privacy_url]" type="text" value="<?php echo htmlspecialchars((string) ($tarteaucitron['privacyUrl'] ?? '/'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="/" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_PRIVACY_URL_HELP', 'Utilise une adresse interne commençant par /. Les anciennes valeurs en \\... ou /... sont normalisées automatiquement.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="tarteaucitron_orientation"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_ORIENTATION_LABEL', 'Position de la bannière'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="tarteaucitron_orientation" name="tarteaucitron[orientation]">
              <?php foreach (['bottom', 'top', 'middle'] as $value): ?>
              <?php $label = $settingChoiceLabel('orientation', $value); ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($tarteaucitron['orientation'] ?? 'bottom') === $value) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="tarteaucitron_icon_position"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_ICON_POSITION_LABEL', 'Position de l’icône'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="tarteaucitron_icon_position" name="tarteaucitron[icon_position]">
              <?php foreach (['BottomRight', 'BottomLeft', 'TopRight', 'TopLeft'] as $value): ?>
              <?php $label = $settingChoiceLabel('iconPosition', $value); ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($tarteaucitron['iconPosition'] ?? 'BottomRight') === $value) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <div class="settings-services" data-tarteaucitron-services>
            <div class="settings-services__header">
              <div>
                <label class="settings-services__label"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_SERVICES_LABEL', 'Services chargés après consentement'), ENT_QUOTES, 'UTF-8'); ?></label>
                <p class="settings-services__summary">
                  <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_SERVICES_HELP_PREFIX', 'Ajoute ici les identifiants techniques des services tarteaucitron à activer, par exemple'), ENT_QUOTES, 'UTF-8'); ?>
                  <code>youtube</code>, <code>vimeo</code>, <code>googlemaps</code> ou <code>recaptcha</code>.
                </p>
              </div>
              <button type="button" class="button-muted settings-services__add" data-service-add><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_ADD_SERVICE', 'Ajouter un service'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <div class="settings-services__list" data-service-list>
              <?php foreach ($tarteaucitronServiceRows as $serviceIndex => $serviceName): ?>
              <div class="settings-service-row" data-service-row>
                <input
                  name="tarteaucitron[services][]"
                  type="text"
                  inputmode="text"
                  value="<?php echo htmlspecialchars((string) $serviceName, ENT_QUOTES, 'UTF-8'); ?>"
                  placeholder="youtube"
                  autocapitalize="off"
                  spellcheck="false"
                  aria-label="<?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_SERVICE_ARIA_LABEL', 'Service tarteaucitron %d'), $serviceIndex + 1), ENT_QUOTES, 'UTF-8'); ?>"
                />
                <button type="button" class="button-muted settings-service-row__remove" data-service-remove><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_REMOVE_SERVICE', 'Retirer'), ENT_QUOTES, 'UTF-8'); ?></button>
              </div>
              <?php endforeach; ?>
            </div>
            <template data-service-template>
              <div class="settings-service-row" data-service-row>
                <input
                  name="tarteaucitron[services][]"
                  type="text"
                  inputmode="text"
                  value=""
                  placeholder="youtube"
                  autocapitalize="off"
                  spellcheck="false"
                  aria-label="<?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_NEW_SERVICE_ARIA_LABEL', 'Nouveau service tarteaucitron'), ENT_QUOTES, 'UTF-8'); ?>"
                />
                <button type="button" class="button-muted settings-service-row__remove" data-service-remove><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_REMOVE_SERVICE', 'Retirer'), ENT_QUOTES, 'UTF-8'); ?></button>
              </div>
            </template>
          </div>
        </div>
        <div class="field">
          <label for="tarteaucitron_user_config_json"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_USER_CONFIG_LABEL', 'Variables JS services (objet JSON)'), ENT_QUOTES, 'UTF-8'); ?></label>
          <textarea
            id="tarteaucitron_user_config_json"
            name="tarteaucitron[user_config_json]"
            rows="8"
            spellcheck="false"
            placeholder='{"googletagmanagerId":"GTM-XXXXXXX","googleadsId":"AW-XXXXXXX"}'
          ><?php echo htmlspecialchars($tarteaucitronUserConfigJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <small>
            <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_USER_CONFIG_HELP', 'Ces clés sont injectées dans tarteaucitron.user pour tous les services. Exemple GTM:'), ENT_QUOTES, 'UTF-8'); ?>
            <code>{"googletagmanagerId":"GTM-MKG2FFBZ"}</code>.
          </small>
        </div>
        <div class="settings-dialog__grid">
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[show_icon]" value="0" />
              <input type="checkbox" name="tarteaucitron[show_icon]" value="1"<?php echo !empty($tarteaucitron['showIcon']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_SHOW_ICON_LABEL', 'Afficher l’icône persistante'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[show_alert_small]" value="0" />
              <input type="checkbox" name="tarteaucitron[show_alert_small]" value="1"<?php echo !empty($tarteaucitron['showAlertSmall']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_SHOW_ALERT_SMALL_LABEL', 'Afficher le rappel réduit'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[high_privacy]" value="0" />
              <input type="checkbox" name="tarteaucitron[high_privacy]" value="1"<?php echo !empty($tarteaucitron['highPrivacy']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_HIGH_PRIVACY_LABEL', 'Activer le mode haute confidentialité'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[accept_all_cta]" value="0" />
              <input type="checkbox" name="tarteaucitron[accept_all_cta]" value="1"<?php echo !empty($tarteaucitron['acceptAllCta']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_ACCEPT_ALL_LABEL', 'Afficher “Tout accepter”'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[deny_all_cta]" value="0" />
              <input type="checkbox" name="tarteaucitron[deny_all_cta]" value="1"<?php echo !empty($tarteaucitron['denyAllCta']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_DENY_ALL_LABEL', 'Afficher “Tout refuser”'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[mandatory]" value="0" />
              <input type="checkbox" name="tarteaucitron[mandatory]" value="1"<?php echo !empty($tarteaucitron['mandatory']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_MANDATORY_LABEL', 'Forcer les services obligatoires'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[google_consent_mode]" value="0" />
              <input type="checkbox" name="tarteaucitron[google_consent_mode]" value="1"<?php echo !empty($tarteaucitron['googleConsentMode']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_GOOGLE_CONSENT_MODE_LABEL', 'Activer Google Consent Mode'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[bing_consent_mode]" value="0" />
              <input type="checkbox" name="tarteaucitron[bing_consent_mode]" value="1"<?php echo !empty($tarteaucitron['bingConsentMode']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_BING_CONSENT_MODE_LABEL', 'Activer Bing Consent Mode'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TARTEAUCITRON_SAVE', 'Enregistrer tarteaucitron'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-discussions" aria-labelledby="settings-dialog-discussions-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="discussions" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-discussions-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_DIALOG_TITLE', 'Discussions publiques et anti-bot'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_DIALOG_LEAD', 'Activation des messages sous articles, limites anti-spam et configuration reCAPTCHA.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="settings-dialog__grid">
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="discussions[enabled]" value="0" />
              <input type="checkbox" name="discussions[enabled]" value="1"<?php echo !empty($discussions['enabled']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_ENABLED_LABEL', 'Autoriser les lecteurs à ouvrir une discussion sous les articles'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="discussions[require_account]" value="0" />
              <input type="checkbox" name="discussions[require_account]" value="1"<?php echo !empty($discussions['requireAccount']) ? ' checked' : ''; ?> />
              <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_REQUIRE_ACCOUNT_LABEL', 'Forcer un compte client (non recommandé sur ce projet)'), ENT_QUOTES, 'UTF-8'); ?></span>
            </label>
          </div>
          <div class="field">
            <label for="discussion_rate_limit_per_ip"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RATE_LIMIT_PER_IP_LABEL', 'Limite IP locale (messages)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_rate_limit_per_ip" name="discussions[rate_limit_per_ip]" type="number" min="1" max="500" value="<?php echo htmlspecialchars((string) ($discussions['rateLimitPerIp'] ?? 6), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_rate_limit_window"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RATE_LIMIT_WINDOW_LABEL', 'Fenêtre locale (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_rate_limit_window" name="discussions[rate_limit_window]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($discussions['rateLimitWindow'] ?? 600), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_global_rate_limit_per_ip"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_GLOBAL_RATE_LIMIT_PER_IP_LABEL', 'Limite IP globale (messages)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_global_rate_limit_per_ip" name="discussions[global_rate_limit_per_ip]" type="number" min="1" max="5000" value="<?php echo htmlspecialchars((string) ($discussions['globalRateLimitPerIp'] ?? 20), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_global_rate_limit_window"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_GLOBAL_RATE_LIMIT_WINDOW_LABEL', 'Fenêtre globale (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_global_rate_limit_window" name="discussions[global_rate_limit_window]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($discussions['globalRateLimitWindow'] ?? 3600), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_min_form_fill_seconds"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_MIN_FORM_FILL_SECONDS_LABEL', 'Délai minimum de saisie (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_min_form_fill_seconds" name="discussions[min_form_fill_seconds]" type="number" min="0" max="120" value="<?php echo htmlspecialchars((string) ($discussions['minFormFillSeconds'] ?? 3), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_max_form_age_seconds"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_MAX_FORM_AGE_SECONDS_LABEL', 'Validité du formulaire (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_max_form_age_seconds" name="discussions[max_form_age_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($discussions['maxFormAgeSeconds'] ?? 7200), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_honeypot_field"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_HONEYPOT_FIELD_LABEL', 'Nom du champ honeypot'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_honeypot_field" name="discussions[honeypot_field]" type="text" value="<?php echo htmlspecialchars((string) ($discussions['honeypotField'] ?? 'website'), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_HONEYPOT_FIELD_HELP', 'Champ invisible côté front, déclenche un blocage s’il est rempli.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
        </div>

        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="discussions[recaptcha_enabled]" value="0" />
            <input type="checkbox" name="discussions[recaptcha_enabled]" value="1"<?php echo !empty($discussions['recaptchaEnabled']) ? ' checked' : ''; ?> />
            <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_ENABLED_LABEL', 'Activer reCAPTCHA (lié à tarteaucitron)'), ENT_QUOTES, 'UTF-8'); ?></span>
          </label>
        </div>

        <div class="settings-dialog__grid">
          <div class="field">
            <label for="discussion_recaptcha_mode"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_MODE_LABEL', 'Mode reCAPTCHA'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="discussion_recaptcha_mode" name="discussions[recaptcha_mode]">
              <option value="v2_checkbox"<?php echo (($discussions['recaptchaMode'] ?? 'v2_checkbox') === 'v2_checkbox') ? ' selected' : ''; ?>><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_MODE_V2', 'v2 case à cocher visible'), ENT_QUOTES, 'UTF-8'); ?></option>
              <option value="v3_score"<?php echo (($discussions['recaptchaMode'] ?? 'v2_checkbox') === 'v3_score') ? ' selected' : ''; ?>><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_MODE_V3', 'v3 invisible par score'), ENT_QUOTES, 'UTF-8'); ?></option>
            </select>
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_MODE_HELP', 'Choisir le mode qui correspond exactement au type de clés Google enregistré.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="discussion_recaptcha_site_key"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_SITE_KEY_LABEL', 'reCAPTCHA Site Key (publique)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_recaptcha_site_key" name="discussions[recaptcha_site_key]" type="text" value="<?php echo htmlspecialchars((string) ($discussions['recaptchaSiteKey'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="6Lc..." />
          </div>
          <div class="field">
            <label for="discussion_recaptcha_secret_key"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_SECRET_KEY_LABEL', 'reCAPTCHA Secret Key'), ENT_QUOTES, 'UTF-8'); ?></label>
            <div class="admin-password-field">
              <input id="discussion_recaptcha_secret_key" name="discussions[recaptcha_secret_key]" type="password" value="<?php echo htmlspecialchars((string) ($discussions['recaptchaSecretKey'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-password"<?php echo $secretMaskAttribute(!empty($discussions['recaptchaSecretKeyConfigured'])); ?> />
              <?php echo $renderSecretToggleButton('discussion_recaptcha_secret_key'); ?>
            </div>
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_SECRET_KEY_HELP', 'Laisser vide pour conserver la clé actuelle'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($discussions['recaptchaSecretKeyConfigured']) ? ' (' . htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECRET_KEY_REGISTERED', 'enregistrée'), ENT_QUOTES, 'UTF-8') . ')' : ''; ?>.</small>
          </div>
          <div class="field">
            <label for="discussion_recaptcha_minimum_score"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_MINIMUM_SCORE_LABEL', 'Score minimum (v3)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_recaptcha_minimum_score" name="discussions[recaptcha_minimum_score]" type="number" min="0" max="1" step="0.1" value="<?php echo htmlspecialchars((string) ($discussions['recaptchaMinimumScore'] ?? 0.5), ENT_QUOTES, 'UTF-8'); ?>" />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_MINIMUM_SCORE_HELP', 'Utilisé uniquement quand le mode v3 invisible est sélectionné.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="discussion_recaptcha_timeout_seconds"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_RECAPTCHA_TIMEOUT_SECONDS_LABEL', 'Délai API (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="discussion_recaptcha_timeout_seconds" name="discussions[recaptcha_timeout_seconds]" type="number" min="3" max="20" value="<?php echo htmlspecialchars((string) ($discussions['recaptchaTimeoutSeconds'] ?? 8), ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_DISCUSSIONS_SAVE', 'Enregistrer la sécurité des discussions'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-instagram" aria-labelledby="settings-dialog-instagram-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="instagram" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-instagram-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_DIALOG_TITLE', 'Flux Instagram sur l’accueil'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_DIALOG_LEAD', 'Affiche les derniers posts Instagram en bas de la page d’accueil, avec rotation automatique.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="instagram[enabled]" value="0" />
            <input type="checkbox" name="instagram[enabled]" value="1"<?php echo !empty($instagram['enabled']) ? ' checked' : ''; ?> />
            <span><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_ENABLED_LABEL', 'Activer le bloc Instagram en bas de la page d’accueil'), ENT_QUOTES, 'UTF-8'); ?></span>
          </label>
        </div>

        <div class="settings-dialog__grid">
          <div class="field">
            <label for="instagram_username"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_USERNAME_LABEL', 'Compte Instagram (sans @)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="instagram_username" name="instagram[username]" type="text" value="<?php echo htmlspecialchars((string) ($instagram['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="paulineetnoel" />
          </div>
          <div class="field">
            <label for="instagram_user_id"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_USER_ID_LABEL', 'User ID Instagram (optionnel)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="instagram_user_id" name="instagram[user_id]" type="text" inputmode="numeric" value="<?php echo htmlspecialchars((string) ($instagram['userId'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="178414..." />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_USER_ID_HELP', 'Laisse vide pour utiliser le endpoint /me/media via le token.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="instagram_access_token"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_ACCESS_TOKEN_LABEL', 'Access Token Instagram'), ENT_QUOTES, 'UTF-8'); ?></label>
            <div class="admin-password-field">
              <input id="instagram_access_token" name="instagram[access_token]" type="password" value="<?php echo htmlspecialchars((string) ($instagram['accessToken'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="new-password"<?php echo $secretMaskAttribute(!empty($instagram['accessTokenConfigured'])); ?> />
              <?php echo $renderSecretToggleButton('instagram_access_token'); ?>
            </div>
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_ACCESS_TOKEN_HELP', 'Laisser vide pour conserver le token actuel'), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($instagram['accessTokenConfigured']) ? ' (' . htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TOKEN_REGISTERED', 'enregistré'), ENT_QUOTES, 'UTF-8') . ')' : ''; ?>.</small>
          </div>
          <div class="field">
            <label for="instagram_limit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_LIMIT_LABEL', 'Nombre de posts'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="instagram_limit" name="instagram[limit]" type="number" min="1" max="20" value="<?php echo htmlspecialchars((string) ($instagram['limit'] ?? 6), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="instagram_rotation_interval_ms"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_ROTATION_INTERVAL_LABEL', 'Rotation auto (millisecondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="instagram_rotation_interval_ms" name="instagram[rotation_interval_ms]" type="number" min="2500" max="30000" step="100" value="<?php echo htmlspecialchars((string) ($instagram['rotationIntervalMs'] ?? 5500), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="instagram_cache_ttl_seconds"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_CACHE_TTL_LABEL', 'Durée de cache (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="instagram_cache_ttl_seconds" name="instagram[cache_ttl_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($instagram['cacheTtlSeconds'] ?? 1800), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="instagram_timeout_seconds"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_TIMEOUT_SECONDS_LABEL', 'Timeout API (secondes)'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="instagram_timeout_seconds" name="instagram[timeout_seconds]" type="number" min="3" max="20" value="<?php echo htmlspecialchars((string) ($instagram['timeoutSeconds'] ?? 8), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
        </div>

        <p class="settings-dialog__summary">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_HELP_DOC_LABEL', 'Aide de configuration :'), ENT_QUOTES, 'UTF-8'); ?> <code>docs/deployment/instagram-feed-setup.md</code>
        </p>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit" name="settings_action" value="save"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_SAVE', 'Enregistrer Instagram'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit" class="button-muted" name="settings_action" value="instagram_test"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INSTAGRAM_TEST_CONNECTION', 'Tester la connexion'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-observability" aria-labelledby="settings-dialog-observability-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="observability" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-observability-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_DIALOG_TITLE', 'Alertes logs (scheduler)'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_DIALOG_LEAD', 'Le mode pilote check_log_alerts.php. Les secrets webhook/email restent gérés côté système.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label for="log-alerts-notify-on"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_LABEL', 'Mode de notification'), ENT_QUOTES, 'UTF-8'); ?></label>
          <select id="log-alerts-notify-on" name="log_alerts[notify_on]">
            <option value="alerts"<?php echo $logAlertsNotifyOn === 'alerts' ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_ALERTS', 'Uniquement en cas d’alerte'), ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <option value="always"<?php echo $logAlertsNotifyOn === 'always' ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_ALWAYS', 'Toujours notifier'), ENT_QUOTES, 'UTF-8'); ?>
            </option>
          </select>
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_HELP', 'Utilise "Toujours notifier" temporairement pour valider email/webhook, puis repasse en mode alerte.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <p class="settings-dialog__summary">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_NOTE', 'Configuration système associée : /etc/caramagnols/check-log-alerts.env (webhook, emails, timeout).'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
        <div class="field">
          <label for="blog-publish-php-binary"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BLOG_PUBLISH_PHP_BINARY_LABEL', 'Binaire PHP détecté'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="blog-publish-php-binary" type="text" value="<?php echo htmlspecialchars($scheduledBlogPublishPhpBinary, ENT_QUOTES, 'UTF-8'); ?>" readonly />
        </div>
        <div class="field">
          <label for="blog-publish-script-path"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BLOG_PUBLISH_SCRIPT_PATH_LABEL', 'Script de publication planifiée'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="blog-publish-script-path" type="text" value="<?php echo htmlspecialchars($scheduledBlogPublishScriptPath, ENT_QUOTES, 'UTF-8'); ?>" readonly />
        </div>
        <div class="field">
          <label for="blog-publish-cron-command"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BLOG_PUBLISH_CRON_COMMAND_LABEL', 'Commande cron recommandée'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="blog-publish-cron-command" type="text" value="<?php echo htmlspecialchars($scheduledBlogPublishCronCommand, ENT_QUOTES, 'UTF-8'); ?>" readonly />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BLOG_PUBLISH_CRON_COMMAND_HELP', 'A lancer chaque minute ou toutes les 5 minutes pour basculer les articles blog planifiés en publiés dès que leur date est atteinte.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_SAVE', 'Enregistrer les alertes logs'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-backup" aria-labelledby="settings-dialog-backup-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="backup" />
    <input type="hidden" name="settings_action" value="backup_save" />
    <div class="region-modal__surface">
    <div class="region-modal__header">
      <div>
        <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
        <h3 id="settings-dialog-backup-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DIALOG_TITLE', 'Sauvegardes production'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DIALOG_LEAD', 'Le backup se lance en CLI ou par cron. Aucun dump SQL ni archive fichier n’est déclenché depuis la requête web admin.'), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
      <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    <div class="region-modal__body settings-dialog__body">
      <?php if (!$backupRootOutsideRoot): ?>
      <p class="notice notice-error" role="alert">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_ROOT_UNSAFE', 'Le dossier de backup configuré est dans le backend ou le webroot. Le script refusera d’écrire tant qu’il n’est pas placé hors du site.'), ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <?php endif; ?>
      <div class="settings-dialog__grid">
        <div class="field">
          <label for="backup-root-path"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_ROOT_LABEL', 'Dossier racine des backups'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="backup-root-path" name="backup[root_dir]" type="text" value="<?php echo htmlspecialchars($backupRoot, ENT_QUOTES, 'UTF-8'); ?>" required />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_ROOT_HELP', 'Doit rester hors backend/public. Par défaut, le script utilise un dossier backups à côté du backend.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="field">
          <label for="backup-retention-days"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_RETENTION_LABEL', 'Rétention'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="backup-retention-days" name="backup[retention_days]" type="number" min="1" max="365" value="<?php echo (int) $backupRetentionDays; ?>" required />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_RETENTION_HELP', 'Nombre de jours conservés avant nettoyage automatique des anciennes archives et anciens dumps.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="field">
          <label for="backup-files-directory"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_FILES_DIR_LABEL', 'Archives du dossier'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="backup-files-directory" name="backup[files_dir]" type="text" value="<?php echo htmlspecialchars($backupFilesDirectory, ENT_QUOTES, 'UTF-8'); ?>" required />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_FILES_DIR_HELP', 'Dossier où seront écrites les archives .tar.gz du site. Il doit rester hors du dossier public.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="field">
          <label for="backup-sql-directory"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_SQL_DIR_LABEL', 'Dumps SQL'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="backup-sql-directory" name="backup[sql_dir]" type="text" value="<?php echo htmlspecialchars($backupSqlDirectory, ENT_QUOTES, 'UTF-8'); ?>" required />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_SQL_DIR_HELP', 'Dossier où seront écrits les dumps .sql.gz de la base. Ne le place jamais dans backend/public.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="field">
          <label for="backup-manifest-directory"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_MANIFEST_DIR_LABEL', 'Manifestes'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="backup-manifest-directory" name="backup[manifest_dir]" type="text" value="<?php echo htmlspecialchars($backupManifestDirectory, ENT_QUOTES, 'UTF-8'); ?>" required />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_MANIFEST_DIR_HELP', 'Dossier des fichiers de résumé: date, fichiers générés, tailles, empreintes et erreurs éventuelles.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
      </div>
      <section class="card">
        <h4><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_LABEL', 'Base sauvegardée'), ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_HELP', 'Connexion SQL utilisée par le site et par le dump mysqldump. Le mot de passe n’est jamais réaffiché; remplis-le uniquement pour le remplacer.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="backup-database-host"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_HOST_LABEL', 'Hôte SQL'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="backup-database-host" name="backup[database_host]" type="text" value="<?php echo htmlspecialchars((string) ($backupDatabase['host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_HOST_HELP', 'Chez OVH, c’est souvent un nom du type db123.example-host.tld, sans l’utilisateur et sans le port.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="backup-database-port"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_PORT_LABEL', 'Port SQL'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="backup-database-port" name="backup[database_port]" type="number" min="1" max="65535" value="<?php echo (int) ($backupDatabase['port'] ?? 3306); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_PORT_HELP', 'Port fourni par OVH. Exemple courant CloudDB : 35987.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="backup-database-name"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_NAME_LABEL', 'Nom de la base'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="backup-database-name" name="backup[database_name]" type="text" value="<?php echo htmlspecialchars((string) ($backupDatabase['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_NAME_HELP', 'Nom exact de la base à exporter, par exemple CarBDbase.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="backup-database-user"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_USER_LABEL', 'Utilisateur SQL'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="backup-database-user" name="backup[database_user]" type="text" value="<?php echo htmlspecialchars((string) ($backupDatabase['user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_USER_HELP', 'Identifiant SQL fourni par OVH, par exemple db_user_example.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="backup-database-password"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_PASSWORD_LABEL', 'Mot de passe SQL'), ENT_QUOTES, 'UTF-8'); ?></label>
            <div class="admin-password-field">
              <input id="backup-database-password" name="backup[database_password]" type="password" value="<?php echo $backupDatabasePasswordConfigured ? htmlspecialchars($secretMask, ENT_QUOTES, 'UTF-8') : ''; ?>" autocomplete="new-password"<?php echo $secretMaskAttribute($backupDatabasePasswordConfigured); ?> />
              <?php echo $renderSecretToggleButton('backup-database-password'); ?>
            </div>
            <small>
              <?php echo $backupDatabasePasswordConfigured
                  ? htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_PASSWORD_KEEP_HELP', 'Mot de passe déjà enregistré. Laisse vide pour le conserver, remplis uniquement pour le remplacer.'), ENT_QUOTES, 'UTF-8')
                  : htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_PASSWORD_SET_HELP', 'Mot de passe requis pour que mysqldump puisse exporter la base SQL.'), ENT_QUOTES, 'UTF-8'); ?>
            </small>
          </div>
        </div>
        <p class="<?php echo $backupDatabaseConfigured ? 'notice-muted' : 'notice notice-error'; ?>">
          <?php echo $backupDatabaseConfigured
              ? htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_READY', 'Configuration SQL lisible. Le mot de passe n’est pas affiché.'), ENT_QUOTES, 'UTF-8')
              : htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DATABASE_MISSING', 'Configuration SQL incomplète: le backup SQL échouera tant que DB_NAME et DB_USER manquent.'), ENT_QUOTES, 'UTF-8'); ?>
        </p>
      </section>
      <div class="field">
        <label for="backup-php-binary"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_PHP_BINARY_LABEL', 'Binaire PHP détecté'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="backup-php-binary" name="backup[php_binary]" type="text" value="<?php echo htmlspecialchars($backupPhpBinary, ENT_QUOTES, 'UTF-8'); ?>" required />
        <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_PHP_BINARY_HELP', 'Binaire utilisé par la commande cron OVH pour lancer les scripts PHP CLI.'), ENT_QUOTES, 'UTF-8'); ?></small>
      </div>
      <div class="settings-dialog__grid">
        <div class="field">
          <label for="backup-tar-binary"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_TAR_BINARY_LABEL', 'Binaire tar'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="backup-tar-binary" name="backup[tar_binary]" type="text" value="<?php echo htmlspecialchars($backupTarBinary, ENT_QUOTES, 'UTF-8'); ?>" required />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_TAR_BINARY_HELP', 'Commande utilisée pour compresser le dossier de production en archive .tar.gz.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
        <div class="field">
          <label for="backup-mysqldump-binary"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_MYSQLDUMP_BINARY_LABEL', 'Binaire mysqldump'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="backup-mysqldump-binary" name="backup[mysqldump_binary]" type="text" value="<?php echo htmlspecialchars($backupMysqldumpBinary, ENT_QUOTES, 'UTF-8'); ?>" required />
          <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_MYSQLDUMP_BINARY_HELP', 'Commande utilisée pour exporter la base SQL. Sur certains hébergements, le chemin complet peut être nécessaire.'), ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
      </div>
      <div class="field">
        <label for="backup-script-path"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_SCRIPT_PATH_LABEL', 'Script de backup'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="backup-script-path" type="text" value="<?php echo htmlspecialchars($backupScriptPath, ENT_QUOTES, 'UTF-8'); ?>" readonly />
      </div>
      <div class="field">
        <label for="backup-cron-command"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_CRON_COMMAND_LABEL', 'Commande cron recommandée'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="backup-cron-command" type="text" value="<?php echo htmlspecialchars($backupCronCommand, ENT_QUOTES, 'UTF-8'); ?>" readonly />
        <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_CRON_COMMAND_HELP', 'Exécution quotidienne recommandée. Le script crée une archive .tar.gz du backend et un dump .sql.gz, puis applique la rétention.'), ENT_QUOTES, 'UTF-8'); ?></small>
      </div>
      <div class="field">
        <label for="backup-dry-run-command"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_DRY_RUN_COMMAND_LABEL', 'Commande de vérification'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="backup-dry-run-command" type="text" value="<?php echo htmlspecialchars($backupDryRunCommand, ENT_QUOTES, 'UTF-8'); ?>" readonly />
      </div>
      <p class="settings-dialog__summary">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_ENV_NOTE', 'Configuration système possible dans .env : PRODUCTION_BACKUP_ROOT, PRODUCTION_BACKUP_RETENTION_DAYS, PRODUCTION_BACKUP_TAR_BINARY et PRODUCTION_BACKUP_MYSQLDUMP_BINARY.'), ENT_QUOTES, 'UTF-8'); ?>
      </p>
    </div>
    <div class="region-modal__actions actions-inline actions-inline-end">
      <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
      <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_BACKUP_SAVE_BUTTON', 'Enregistrer les sauvegardes'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-cron" aria-labelledby="settings-dialog-cron-title">
  <div class="region-modal__surface">
    <div class="region-modal__header">
      <div>
        <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_DIALOG_EYEBROW', 'Tâche cron'), ENT_QUOTES, 'UTF-8'); ?></p>
        <h3 id="settings-dialog-cron-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_DIALOG_TITLE', 'Cron Center'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_DIALOG_LEAD', 'Tableau de bord des exécutions planifiées, statut du scheduler, point d’entrée OVH, jobs et journaux.'), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
      <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    <div class="region-modal__body settings-dialog__body">
      <?php if (!$cronAvailable && $cronError !== null): ?>
      <p class="notice notice-error" role="alert">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_UNAVAILABLE', 'Cron Center indisponible :'), ENT_QUOTES, 'UTF-8'); ?>
        <?php echo htmlspecialchars($cronError, ENT_QUOTES, 'UTF-8'); ?>
      </p>
      <?php endif; ?>

      <div class="settings-dialog__grid">
        <div class="field">
          <label for="cron-center-status"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_STATUS_LABEL', 'Statut scheduler'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="cron-center-status" type="text" value="<?php echo htmlspecialchars($cronStatusLabel, ENT_QUOTES, 'UTF-8'); ?>" readonly />
        </div>
        <div class="field">
          <label for="cron-center-last-run"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_LAST_RUN_LABEL', 'Dernière coordination'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="cron-center-last-run" type="text" value="<?php echo htmlspecialchars($formatCronDate($cronScheduler['finishedAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly />
        </div>
        <div class="field">
          <label for="cron-center-jobs-count"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_JOBS_COUNT_LABEL', 'Jobs actifs'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="cron-center-jobs-count" type="text" value="<?php echo (int) $cronActiveJobsCount; ?> / <?php echo count($cronJobs); ?>" readonly />
        </div>
        <div class="field">
          <label for="cron-center-alerts-count"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ALERTS_COUNT_LABEL', 'Jobs en alerte'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="cron-center-alerts-count" type="text" value="<?php echo (int) $cronFailedJobsCount; ?>" readonly />
        </div>
      </div>

      <div class="field">
        <label for="cron-center-ovh-command"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_OVH_COMMAND_LABEL', 'Commande OVH'), ENT_QUOTES, 'UTF-8'); ?></label>
        <input id="cron-center-ovh-command" type="text" value="<?php echo htmlspecialchars($cronOvhCommand, ENT_QUOTES, 'UTF-8'); ?>" readonly />
        <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_OVH_COMMAND_HELP', 'OVH appelle ce script chaque minute. Cron Center décide ensuite quels jobs PHP locaux doivent partir.'), ENT_QUOTES, 'UTF-8'); ?></small>
      </div>
      <div class="settings-dialog__grid">
        <div class="field">
          <label for="cron-center-runner-path"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_RUNNER_PATH_LABEL', 'Script de coordination'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="cron-center-runner-path" type="text" value="<?php echo htmlspecialchars($cronRunnerPath, ENT_QUOTES, 'UTF-8'); ?>" readonly />
        </div>
        <div class="field">
          <label for="cron-center-php-binary"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_PHP_BINARY_LABEL', 'Binaire PHP détecté'), ENT_QUOTES, 'UTF-8'); ?></label>
          <input id="cron-center-php-binary" type="text" value="<?php echo htmlspecialchars($cronPhpBinary, ENT_QUOTES, 'UTF-8'); ?>" readonly />
        </div>
      </div>
      <p class="settings-dialog__summary">
        <a href="<?php echo htmlspecialchars($cronLogsUrl !== '' ? $cronLogsUrl : admin_url('logs'), ENT_QUOTES, 'UTF-8'); ?>">
          <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_LOGS_LINK', 'Voir les logs Cron Center dans Admin > Logs'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
      </p>

      <datalist id="cron-center-script-paths">
        <?php foreach ($cronAllowedScripts as $scriptPath): ?>
        <option value="<?php echo htmlspecialchars((string) $scriptPath, ENT_QUOTES, 'UTF-8'); ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <section class="card">
        <h4><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ADD_TITLE', 'Ajouter un script PHP local'), ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ADD_LEAD', 'Le cron OVH appelle un seul script de coordination. Les jobs ci-dessous restent limités aux scripts PHP locaux de backend/core/tools/.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="admin-form-grid" autocomplete="off" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
          <input type="hidden" name="settings_section" value="cron" />
          <input type="hidden" name="settings_action" value="cron_create" />
          <div class="field">
            <label for="cron-job-code"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_JOB_CODE_LABEL', 'Code job'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="cron-job-code" name="cron_job[code]" type="text" value="<?php echo htmlspecialchars((string) ($cronEmptyJobForm['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="publiarticles" pattern="[a-z0-9][a-z0-9_-]{1,63}" required />
          </div>
          <div class="field">
            <label for="cron-job-name"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_JOB_NAME_LABEL', 'Nom'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="cron-job-name" name="cron_job[name]" type="text" value="<?php echo htmlspecialchars((string) ($cronEmptyJobForm['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Publication articles" required />
          </div>
          <div class="field">
            <label for="cron-job-script-path"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_SCRIPT_PATH_LABEL', 'Chemin du script PHP local'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="cron-job-script-path" name="cron_job[script_path]" type="text" list="cron-center-script-paths" value="<?php echo htmlspecialchars((string) ($cronEmptyJobForm['script_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_SCRIPT_PATH_HELP', 'Chemin relatif à backend, limité à core/tools/*.php.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="cron-job-status"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ACTIVATION_LABEL', 'Activation'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select id="cron-job-status" name="cron_job[status]">
              <option value="active" selected><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_STATUS_ACTIVE', 'Actif'), ENT_QUOTES, 'UTF-8'); ?></option>
              <option value="inactive"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_STATUS_INACTIVE', 'Inactif'), ENT_QUOTES, 'UTF-8'); ?></option>
            </select>
          </div>
          <div class="field">
            <label for="cron-job-schedule"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_EXPRESSION_LABEL', 'Expression cron'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="cron-job-schedule" name="cron_job[schedule_expression]" type="text" value="<?php echo htmlspecialchars((string) ($cronEmptyJobForm['schedule_expression'] ?? '*/5 * * * *'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="00 12 * * *" required />
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_EXPRESSION_HELP', 'Format : minute heure jour mois jour_semaine. Exemple 00 12 * * * = tous les jours à 12:00.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="field">
            <label for="cron-job-timeout"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TIMEOUT_LABEL', 'Timeout'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input id="cron-job-timeout" name="cron_job[timeout_seconds]" type="number" min="5" max="3600" value="<?php echo htmlspecialchars((string) ($cronEmptyJobForm['timeout_seconds'] ?? 300), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="cron-job-description"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_DESCRIPTION_LABEL', 'Description'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea id="cron-job-description" name="cron_job[description]" rows="4"><?php echo htmlspecialchars((string) ($cronEmptyJobForm['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
          <div class="field">
            <label for="cron-job-arguments-json"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ARGUMENTS_LABEL', 'Paramètres JSON'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea id="cron-job-arguments-json" name="cron_job[arguments_json]" rows="4" spellcheck="false"><?php echo htmlspecialchars((string) ($cronEmptyJobForm['arguments_json'] ?? "{\n  \"args\": []\n}"), ENT_QUOTES, 'UTF-8'); ?></textarea>
            <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ARGUMENTS_HELP', 'Objet JSON optionnel, par exemple {"args":["--quiet"]}. stdout, stderr et code retour sont journalisés.'), ENT_QUOTES, 'UTF-8'); ?></small>
          </div>
          <div class="actions-inline actions-inline-end">
            <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_CREATE_BUTTON', 'Créer le job script'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
        </form>
      </section>

      <section class="card">
        <h4><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_JOBS_TITLE', 'Jobs créés'), ENT_QUOTES, 'UTF-8'); ?></h4>
        <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_MANUAL_TEST_HELP', 'Le test manuel exécute réellement le script autorisé et écrit le résultat dans l’historique.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($cronJobs === []): ?>
        <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_NO_JOBS', 'Aucun job cron enregistré.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
        <div class="table-shell">
          <table class="admin-table">
            <thead>
              <tr>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_JOB', 'Job'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_SCHEDULE', 'Planification'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_LAST_RUN', 'Dernière exécution'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_NEXT_RUN', 'Prochaine exécution'), ENT_QUOTES, 'UTF-8'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cronJobs as $cronJob): ?>
              <?php
              if (!is_array($cronJob)) {
                  continue;
              }

              $cronJobCode = (string) ($cronJob['code'] ?? '');
              $cronJobHtmlId = preg_replace('/[^a-z0-9_-]+/i', '-', $cronJobCode) ?: 'job';
              $cronJobActive = (string) ($cronJob['status'] ?? '') === 'active';
              $cronJobDefault = !empty($cronJob['is_default']);
              $cronJobLastStatus = trim((string) ($cronJob['last_status'] ?? ''));
              $cronJobAlert = in_array($cronJobLastStatus, ['failed', 'timeout'], true);
              ?>
              <tr>
                <td>
                  <strong><?php echo htmlspecialchars((string) ($cronJob['name'] ?? $cronJobCode), ENT_QUOTES, 'UTF-8'); ?></strong><br />
                  <code><?php echo htmlspecialchars($cronJobCode, ENT_QUOTES, 'UTF-8'); ?></code><br />
                  <small><?php echo htmlspecialchars((string) ($cronJob['script_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                  <?php if ($cronJobDefault): ?>
                  <br /><small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_DEFAULT_JOB_LABEL', 'Job par défaut'), ENT_QUOTES, 'UTF-8'); ?></small>
                  <?php endif; ?>
                  <details>
                    <summary><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_EDIT_SUMMARY', 'Modifier'), ENT_QUOTES, 'UTF-8'); ?></summary>
                    <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="admin-form-grid" autocomplete="off" novalidate>
                      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                      <input type="hidden" name="settings_section" value="cron" />
                      <input type="hidden" name="settings_action" value="cron_save" />
                      <input type="hidden" name="cron_job[code]" value="<?php echo htmlspecialchars($cronJobCode, ENT_QUOTES, 'UTF-8'); ?>" />
                      <div class="field">
                        <label for="cron-job-name-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_JOB_NAME_LABEL', 'Nom'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input id="cron-job-name-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>" name="cron_job[name]" type="text" value="<?php echo htmlspecialchars((string) ($cronJob['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </div>
                      <div class="field">
                        <label for="cron-job-script-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_SCRIPT_PATH_LABEL', 'Chemin du script PHP local'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input id="cron-job-script-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>" name="cron_job[script_path]" type="text" list="cron-center-script-paths" value="<?php echo htmlspecialchars((string) ($cronJob['script_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </div>
                      <div class="field">
                        <label for="cron-job-status-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ACTIVATION_LABEL', 'Activation'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <select id="cron-job-status-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>" name="cron_job[status]">
                          <option value="active"<?php echo $cronJobActive ? ' selected' : ''; ?>><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_STATUS_ACTIVE', 'Actif'), ENT_QUOTES, 'UTF-8'); ?></option>
                          <option value="inactive"<?php echo !$cronJobActive ? ' selected' : ''; ?>><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_STATUS_INACTIVE', 'Inactif'), ENT_QUOTES, 'UTF-8'); ?></option>
                        </select>
                      </div>
                      <div class="field">
                        <label for="cron-job-schedule-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_EXPRESSION_LABEL', 'Expression cron'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input id="cron-job-schedule-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>" name="cron_job[schedule_expression]" type="text" value="<?php echo htmlspecialchars((string) ($cronJob['schedule_expression'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </div>
                      <div class="field">
                        <label for="cron-job-timeout-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TIMEOUT_LABEL', 'Timeout'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input id="cron-job-timeout-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>" name="cron_job[timeout_seconds]" type="number" min="5" max="3600" value="<?php echo htmlspecialchars((string) ($cronJob['timeout_seconds'] ?? 300), ENT_QUOTES, 'UTF-8'); ?>" required />
                      </div>
                      <div class="field">
                        <label for="cron-job-description-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_DESCRIPTION_LABEL', 'Description'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <textarea id="cron-job-description-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>" name="cron_job[description]" rows="3"><?php echo htmlspecialchars((string) ($cronJob['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                      </div>
                      <div class="field">
                        <label for="cron-job-arguments-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_ARGUMENTS_LABEL', 'Paramètres JSON'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <textarea id="cron-job-arguments-<?php echo htmlspecialchars($cronJobHtmlId, ENT_QUOTES, 'UTF-8'); ?>" name="cron_job[arguments_json]" rows="4" spellcheck="false"><?php echo htmlspecialchars((string) ($cronJob['arguments_json'] ?? "{\n  \"args\": []\n}"), ENT_QUOTES, 'UTF-8'); ?></textarea>
                      </div>
                      <div class="actions-inline actions-inline-end">
                        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_SAVE_BUTTON', 'Enregistrer le job'), ENT_QUOTES, 'UTF-8'); ?></button>
                      </div>
                    </form>
                  </details>
                </td>
                <td>
                  <span class="tag"><?php echo htmlspecialchars((string) ($cronJob['status_label'] ?? ($cronJobActive ? 'Actif' : 'Inactif')), ENT_QUOTES, 'UTF-8'); ?></span><br />
                  <?php if ($cronJobLastStatus !== ''): ?>
                  <small><?php echo htmlspecialchars($cronJobLastStatus, ENT_QUOTES, 'UTF-8'); ?><?php echo $cronJobAlert ? ' · alerte' : ''; ?></small>
                  <?php endif; ?>
                  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="actions-inline" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="settings_section" value="cron" />
                    <input type="hidden" name="settings_action" value="cron_toggle" />
                    <input type="hidden" name="cron_job_code" value="<?php echo htmlspecialchars($cronJobCode, ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="cron_job_status" value="<?php echo $cronJobActive ? 'inactive' : 'active'; ?>" />
                    <button type="submit" class="button-muted button-small"><?php echo htmlspecialchars($cronJobActive ? $translate('TXT_ADMIN_SETTINGS_CRON_DISABLE_BUTTON', 'Désactiver') : $translate('TXT_ADMIN_SETTINGS_CRON_ENABLE_BUTTON', 'Activer'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </form>
                  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="actions-inline" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="settings_section" value="cron" />
                    <input type="hidden" name="settings_action" value="cron_test" />
                    <input type="hidden" name="cron_job_code" value="<?php echo htmlspecialchars($cronJobCode, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button type="submit" class="button-small"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TEST_BUTTON', 'Tester maintenant'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </form>
                </td>
                <td>
                  <code><?php echo htmlspecialchars((string) ($cronJob['schedule_expression'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code><br />
                  <small><?php echo htmlspecialchars((string) ($cronJob['schedule_summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                </td>
                <td>
                  <?php echo htmlspecialchars($formatCronDate($cronJob['last_run_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><br />
                  <?php if (($cronJob['last_duration_ms'] ?? null) !== null): ?>
                  <small><?php echo (int) $cronJob['last_duration_ms']; ?> ms · code <?php echo $cronJob['last_exit_code'] === null ? '—' : (int) $cronJob['last_exit_code']; ?></small>
                  <?php endif; ?>
                </td>
                <td>
                  <?php echo htmlspecialchars($formatCronDate($cronJob['next_run_at'] ?? ($cronJob['next_run_display'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                  <?php if (!$cronJobDefault): ?>
                  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="actions-inline" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                    <input type="hidden" name="settings_section" value="cron" />
                    <input type="hidden" name="settings_action" value="cron_delete" />
                    <input type="hidden" name="cron_job_code" value="<?php echo htmlspecialchars($cronJobCode, ENT_QUOTES, 'UTF-8'); ?>" />
                    <button type="submit" class="button-danger button-small"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_DELETE_BUTTON', 'Supprimer'), ENT_QUOTES, 'UTF-8'); ?></button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>

      <section class="card" id="cron-center-history" tabindex="-1">
        <h4><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_HISTORY_TITLE', 'Dernières exécutions'), ENT_QUOTES, 'UTF-8'); ?></h4>
        <?php if ($cronRuns === []): ?>
        <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_NO_RUNS', 'Aucune exécution journalisée pour le moment.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
        <div class="table-shell">
          <table class="admin-table">
            <thead>
              <tr>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_JOB', 'Job'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_TABLE_LAST_RUN', 'Dernière exécution'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_CRON_MESSAGE_LABEL', 'Message'), ENT_QUOTES, 'UTF-8'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cronRuns as $cronRun): ?>
              <?php if (!is_array($cronRun)) { continue; } ?>
              <tr>
                <td><code><?php echo htmlspecialchars((string) ($cronRun['job_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                <td><span class="tag"><?php echo htmlspecialchars((string) ($cronRun['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                <td><?php echo htmlspecialchars($formatCronDate($cronRun['started_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($cronRun['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>
    </div>
    <div class="region-modal__actions actions-inline actions-inline-end">
      <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-translations" aria-labelledby="settings-dialog-translations-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="translations" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECTION_EYEBROW', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?></p>
          <h3 id="settings-dialog-translations-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_DIALOG_TITLE', 'Overrides de traductions'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_DIALOG_HELP', 'Format: une ligne par clé, CLE=Valeur. Les clés inconnues sont refusées.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <?php if ($translationLanguages === []): ?>
        <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_NO_LANGUAGES', 'Aucune langue configurée.'), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php else: ?>
          <?php foreach ($translationLanguages as $translationLanguage): ?>
            <?php
            $language = strtolower(trim((string) $translationLanguage));
            if ($language === '') {
                continue;
            }
            $textareaId = 'translations_' . $language;
            $textareaValue = (string) ((is_array($translations['textByLanguage'] ?? null) ? $translations['textByLanguage'] : [])[$language] ?? '');
            $dictionaryTextareaId = 'translations_dictionary_' . $language;
            $dictionaryTextareaValue = (string) ((is_array($translations['dictionaryTextByLanguage'] ?? null) ? $translations['dictionaryTextByLanguage'] : [])[$language] ?? '');
            ?>
          <div class="field">
            <label for="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>">
              Overrides <?php echo strtoupper(htmlspecialchars($language, ENT_QUOTES, 'UTF-8')); ?>
              (<?php echo (int) ($translationCounts[$language] ?? 0); ?>)
            </label>
            <textarea
              id="<?php echo htmlspecialchars($textareaId, ENT_QUOTES, 'UTF-8'); ?>"
              name="translations[<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>]"
              rows="10"
              spellcheck="false"
              placeholder="TXT_BLOG_SITE_TITLE=Blog des Caramagnols&#10;TXT_CONTACT_SUBMIT=Envoyer"
            ><?php echo htmlspecialchars($textareaValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
            <details class="settings-translation-dictionary">
              <summary>
                <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_DICTIONARY_LABEL', 'Dictionnaire existant'), ENT_QUOTES, 'UTF-8'); ?> <?php echo strtoupper(htmlspecialchars($language, ENT_QUOTES, 'UTF-8')); ?>
                (<?php echo (int) ($translationDictionaryCounts[$language] ?? 0); ?>)
              </summary>
              <textarea
                id="<?php echo htmlspecialchars($dictionaryTextareaId, ENT_QUOTES, 'UTF-8'); ?>"
                rows="10"
                spellcheck="false"
                readonly
              ><?php echo htmlspecialchars($dictionaryTextareaValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <small><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_DICTIONARY_HELP', 'Référence en lecture seule (clés existantes et traductions actuelles).'), ENT_QUOTES, 'UTF-8'); ?></small>
            </details>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CANCEL', 'Annuler'), ENT_QUOTES, 'UTF-8'); ?></button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_TRANSLATIONS_SAVE', 'Enregistrer les traductions'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-security" aria-labelledby="settings-dialog-security-title">
  <div class="region-modal__surface">
    <div class="region-modal__header">
      <div>
        <p class="region-modal__eyebrow"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_INFORMATION_EYEBROW', 'Information'), ENT_QUOTES, 'UTF-8'); ?></p>
        <h3 id="settings-dialog-security-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_DIALOG_TITLE', 'Règles de sécurité'), ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_DIALOG_LEAD', 'Résumé des contraintes déjà appliquées à la sauvegarde des paramètres sensibles.'), ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
      <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
    <div class="region-modal__body settings-dialog__body">
      <ul class="settings-dialog__list">
        <li><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_RULE_PASSWORD_HASHED', 'Le mot de passe admin est hashé via password_hash() avant écriture.'), ENT_QUOTES, 'UTF-8'); ?></li>
        <li><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_RULE_TOTP_LOCALHOST', 'Le Code 2FA est désactivé par défaut, activable depuis les paramètres admin, et bypassé en localhost quand le bypass local est configuré.'), ENT_QUOTES, 'UTF-8'); ?></li>
        <li><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_RULE_SESSION_TIMEOUT', 'La session admin coupe automatiquement après inactivité (20 min par défaut).'), ENT_QUOTES, 'UTF-8'); ?></li>
        <li><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_RULE_REAUTH', 'Les actions sensibles forcent une ré-authentification périodique.'), ENT_QUOTES, 'UTF-8'); ?></li>
        <li><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_RULE_DATABASE_PASSWORD', 'Le mot de passe BDD n’est jamais réaffiché dans l’interface.'), ENT_QUOTES, 'UTF-8'); ?></li>
        <li><?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_SETTINGS_SECURITY_RULE_OUTSIDE_WEBROOT', 'La sauvegarde est écrite hors webroot : %s.'), !empty($storage['outsideWebroot']) ? $translate('TXT_ADMIN_SETTINGS_SECURITY_YES', 'oui') : $translate('TXT_ADMIN_SETTINGS_SECURITY_TO_VERIFY', 'à vérifier')), ENT_QUOTES, 'UTF-8'); ?></li>
        <li><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_RULE_LOGGING', 'Chaque changement sensible est journalisé dans backend/data/logs/security.log.'), ENT_QUOTES, 'UTF-8'); ?></li>
      </ul>
      <p class="settings-dialog__summary">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_SECURITY_ACTIVE_OVERRIDES', 'Overrides actifs :'), ENT_QUOTES, 'UTF-8'); ?> <code><?php echo htmlspecialchars((string) ($storage['databaseOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>,
        <code><?php echo htmlspecialchars((string) ($storage['adminOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>,
        <code><?php echo htmlspecialchars((string) ($storage['siteOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>.
      </p>
    </div>
    <div class="region-modal__actions actions-inline actions-inline-end">
      <button type="button" class="button-muted" data-region-modal-close><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_CLOSE', 'Fermer'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </div>
</dialog>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
  (() => {
    const serviceEditor = document.querySelector('[data-tarteaucitron-services]');
    if (!(serviceEditor instanceof HTMLElement)) {
      return;
    }

    const list = serviceEditor.querySelector('[data-service-list]');
    const template = serviceEditor.querySelector('template[data-service-template]');
    const addButton = serviceEditor.querySelector('[data-service-add]');

    if (!(list instanceof HTMLElement) || !(template instanceof HTMLTemplateElement) || !(addButton instanceof HTMLElement)) {
      return;
    }

    const updateRemoveButtons = () => {
      const rows = list.querySelectorAll('[data-service-row]');
      rows.forEach((row) => {
        const removeButton = row.querySelector('[data-service-remove]');
        if (!(removeButton instanceof HTMLButtonElement)) {
          return;
        }

        removeButton.disabled = rows.length === 1;
      });
    };

    const appendRow = () => {
      const fragment = template.content.cloneNode(true);
      if (!(fragment instanceof DocumentFragment)) {
        return;
      }

      list.appendChild(fragment);
      updateRemoveButtons();

      const inputs = list.querySelectorAll('input[name="tarteaucitron[services][]"]');
      const lastInput = inputs.item(inputs.length - 1);
      if (lastInput instanceof HTMLInputElement) {
        lastInput.focus();
      }
    };

    addButton.addEventListener('click', () => appendRow());

    list.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.matches('[data-service-remove]')) {
        return;
      }

      const row = target.closest('[data-service-row]');
      if (!(row instanceof HTMLElement)) {
        return;
      }

      const rows = list.querySelectorAll('[data-service-row]');
      if (rows.length <= 1) {
        const input = row.querySelector('input[name="tarteaucitron[services][]"]');
        if (input instanceof HTMLInputElement) {
          input.value = '';
          input.focus();
        }
        updateRemoveButtons();
        return;
      }

      row.remove();
      updateRemoveButtons();
    });

    updateRemoveButtons();
  })();
</script>
