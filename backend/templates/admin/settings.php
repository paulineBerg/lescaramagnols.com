<?php
$view = is_array($settingsView ?? null) ? $settingsView : [];
$database = is_array($view['database'] ?? null) ? $view['database'] : [];
$admin = is_array($view['admin'] ?? null) ? $view['admin'] : [];
$url = is_array($view['url'] ?? null) ? $view['url'] : [];
$head = is_array($view['head'] ?? null) ? $view['head'] : [];
$tarteaucitron = is_array($view['tarteaucitron'] ?? null) ? $view['tarteaucitron'] : [];
$discussions = is_array($view['discussions'] ?? null) ? $view['discussions'] : [];
$instagram = is_array($view['instagram'] ?? null) ? $view['instagram'] : [];
$logAlerts = is_array($view['logAlerts'] ?? null) ? $view['logAlerts'] : [];
$translations = is_array($view['translations'] ?? null) ? $view['translations'] : [];
$storage = is_array($view['storage'] ?? null) ? $view['storage'] : [];
$openSection = is_string($openSettingsSection ?? null) ? (string) $openSettingsSection : null;
$settingsAction = (string) ($adminSettingsUrl ?? admin_url('settings'));
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
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
$discussionSummary = !empty($discussions['enabled']) ? 'Ouvert' : 'Fermé';
$discussionRecaptchaSummary = !empty($discussions['recaptchaEnabled']) ? 'reCAPTCHA actif' : 'reCAPTCHA inactif';
$instagramSummary = !empty($instagram['enabled']) ? 'Actif' : 'Inactif';
$logAlertsNotifyOn = strtolower(trim((string) ($logAlerts['notifyOn'] ?? 'alerts')));
if (!in_array($logAlertsNotifyOn, ['alerts', 'always'], true)) {
    $logAlertsNotifyOn = 'alerts';
}
$logAlertsModeSummary = $logAlertsNotifyOn === 'always'
    ? $translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_ALWAYS', 'Toujours notifier')
    : $translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_MODE_ALERTS', 'Uniquement en cas d’alerte');
$instagramAccount = trim((string) ($instagram['username'] ?? ''));
$instagramAccountSummary = $instagramAccount !== '' ? '@' . $instagramAccount : 'Compte dérivé du token';
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
$translationSummary = $translationSummaryParts !== [] ? implode(' · ', $translationSummaryParts) : 'Aucun override';
$translationKnownKeysCount = max(0, (int) ($translations['knownKeysCount'] ?? 0));
$databaseSummary = trim((string) (($database['host'] ?? '') . ':' . ($database['port'] ?? '')));
$adminSummary = trim((string) ($admin['identifier'] ?? ''));
$adminAllowedIpsRaw = trim((string) ($admin['allowedIps'] ?? ''));
$adminAllowedIps = preg_split('/[\s,;]+/', $adminAllowedIpsRaw, -1, PREG_SPLIT_NO_EMPTY);
$adminAllowedIps = is_array($adminAllowedIps) ? array_values(array_unique(array_map('trim', $adminAllowedIps))) : [];
$adminAllowedIpsSummary = $adminAllowedIps === []
    ? 'Filtrage IP désactivé'
    : sprintf('%d IP autorisée%s', count($adminAllowedIps), count($adminAllowedIps) > 1 ? 's' : '');
$adminTotpEnabled = !empty($admin['totpEnabled']);
$adminTotpSummary = $adminTotpEnabled ? '2FA TOTP actif' : '2FA TOTP inactif';
$adminTotpSecretSummary = !empty($admin['totpSecretConfigured']) ? 'secret enregistré' : 'secret absent';
$adminInactivitySummary = max(60, (int) ($admin['inactivityTimeoutSeconds'] ?? 1200));
$adminReauthSummary = max(60, (int) ($admin['reauthTimeoutSeconds'] ?? 600));
$urlSummaryParts = [];
if (trim((string) ($url['domain'] ?? '')) !== '') {
    $urlSummaryParts[] = 'HTTP ' . trim((string) $url['domain']);
}
if (trim((string) ($url['sslDomain'] ?? '')) !== '') {
    $urlSummaryParts[] = 'HTTPS ' . trim((string) $url['sslDomain']);
}
$urlSummary = $urlSummaryParts !== [] ? implode(' · ', $urlSummaryParts) : 'Domaines hérités de la requête ou de l’environnement';
$autostartAttr = static function (string $section, ?string $openSection): string {
    return $openSection === $section ? ' data-region-modal-autostart="true"' : '';
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
    <h2>Paramètres d’exploitation</h2>
    <p class="notice-muted">
      Chaque bloc s’ouvre dans une popup dédiée. Les overrides sensibles restent écrits hors webroot dans
      <code><?php echo htmlspecialchars((string) ($storage['databaseOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>,
      <code><?php echo htmlspecialchars((string) ($storage['adminOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
      et <code><?php echo htmlspecialchars((string) ($storage['siteOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>.
    </p>
    <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="actions-inline" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="settings_section" value="security" />
      <button type="submit" class="button-muted" name="settings_action" value="cache_clear">Vider le cache</button>
      <small>Vide les caches runtime (pages/navigation/traductions) et supprime le cache Instagram disque.</small>
    </form>
  </article>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-database"
    data-settings-section-card="database"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('database', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">Base SQL</h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($databaseSummary !== ':' ? $databaseSummary : 'Connexion SQL à compléter', ENT_QUOTES, 'UTF-8'); ?><br />
      Base <?php echo htmlspecialchars((string) ($database['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · préfixe <?php echo htmlspecialchars((string) ($database['prefix'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <span class="settings-section-card__cta">Configurer la base SQL</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-admin"
    data-settings-section-card="admin"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('admin', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">Connexion admin</h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($adminSummary !== '' ? $adminSummary : 'E-mail admin à définir', ENT_QUOTES, 'UTF-8'); ?><br />
      Mot de passe <?php echo !empty($admin['passwordConfigured']) ? 'déjà enregistré' : 'à configurer'; ?><br />
      <?php echo htmlspecialchars($adminAllowedIpsSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo htmlspecialchars($adminTotpSummary . ' · ' . $adminTotpSecretSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      Timeout <?php echo (int) $adminInactivitySummary; ?>s · Ré-auth <?php echo (int) $adminReauthSummary; ?>s
    </p>
    <span class="settings-section-card__cta">Configurer l’accès admin</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-url"
    data-settings-section-card="url"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('url', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">URL publique</h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($urlSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      Chemin de base <?php echo htmlspecialchars((string) ($url['basePath'] ?? '/'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <span class="settings-section-card__cta">Configurer les URL du site</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-head"
    data-settings-section-card="head"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('head', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">Métadonnées globales du head</h2>
    <p class="settings-section-card__summary">
      Injection globale de balises meta, link canonical/alternate et JSON-LD.<br />
      État: <?php echo $metadataConfigured ? 'configuration active' : 'aucune balise enregistrée'; ?>
    </p>
    <span class="settings-section-card__cta">Gérer les métadonnées</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-tarteaucitron"
    data-settings-section-card="tarteaucitron"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('tarteaucitron', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">Gestion tarteaucitron</h2>
    <p class="settings-section-card__summary">
      <?php echo !empty($tarteaucitron['enabled']) ? 'Activé' : 'Désactivé'; ?> · bannière <?php echo htmlspecialchars((string) ($tarteaucitron['orientation'] ?? 'bottom'), ENT_QUOTES, 'UTF-8'); ?><br />
      Icône <?php echo htmlspecialchars((string) ($tarteaucitron['iconPosition'] ?? 'BottomRight'), ENT_QUOTES, 'UTF-8'); ?> · <?php echo $tarteaucitronServiceCount; ?> service<?php echo $tarteaucitronServiceCount > 1 ? 's' : ''; ?><br />
      Variables JS: <?php echo (int) $tarteaucitronUserConfigCount; ?>
    </p>
    <span class="settings-section-card__cta">Configurer le consentement</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-discussions"
    data-settings-section-card="discussions"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('discussions', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">Discussions et anti-bot</h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($discussionSummary, ENT_QUOTES, 'UTF-8'); ?> · limite locale <?php echo (int) ($discussions['rateLimitPerIp'] ?? 6); ?>/<?php echo (int) ($discussions['rateLimitWindow'] ?? 600); ?>s<br />
      <?php echo htmlspecialchars($discussionRecaptchaSummary, ENT_QUOTES, 'UTF-8'); ?> · clé secrète <?php echo !empty($discussions['recaptchaSecretKeyConfigured']) ? 'enregistrée' : 'absente'; ?>
    </p>
    <span class="settings-section-card__cta">Configurer la modération publique</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-instagram"
    data-settings-section-card="instagram"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('instagram', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">Flux Instagram accueil</h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($instagramSummary, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($instagramAccountSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo (int) ($instagram['limit'] ?? 6); ?> post(s) · rotation <?php echo (int) ($instagram['rotationIntervalMs'] ?? 5500); ?> ms · token <?php echo !empty($instagram['accessTokenConfigured']) ? 'enregistré' : 'absent'; ?>
    </p>
    <span class="settings-section-card__cta">Configurer Instagram</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-observability"
    data-settings-section-card="observability"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('observability', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
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
    data-region-modal-open="settings-dialog-translations"
    data-settings-section-card="translations"
    aria-haspopup="dialog"
    <?php echo $autostartAttr('translations', $openSection); ?>
  >
    <p class="settings-section-card__eyebrow">Section</p>
    <h2 class="settings-section-card__title">Traductions globales</h2>
    <p class="settings-section-card__summary">
      <?php echo htmlspecialchars($translationSummary, ENT_QUOTES, 'UTF-8'); ?><br />
      <?php echo (int) $translationKnownKeysCount; ?> clé(s) connues dans le dictionnaire principal.
    </p>
    <span class="settings-section-card__cta">Configurer les overrides i18n</span>
  </button>

  <button
    type="button"
    class="settings-section-card"
    data-region-modal-open="settings-dialog-security"
    data-settings-section-card="security"
    aria-haspopup="dialog"
  >
    <p class="settings-section-card__eyebrow">Information</p>
    <h2 class="settings-section-card__title">Règles de sécurité</h2>
    <p class="settings-section-card__summary">
      Hash du mot de passe admin, mot de passe SQL masqué, overrides hors webroot et journalisation sensible.
    </p>
    <span class="settings-section-card__cta">Voir les règles</span>
  </button>
</section>

<dialog class="region-modal settings-dialog" id="settings-dialog-database" aria-labelledby="settings-dialog-database-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="database" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-database-title">Base SQL</h3>
          <p>Connexion principale, port, base, identifiant, mot de passe et préfixe des tables.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="database_host">Adresse</label>
            <input id="database_host" name="database[host]" type="text" value="<?php echo htmlspecialchars((string) ($database['host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_port">Port</label>
            <input id="database_port" name="database[port]" type="text" inputmode="numeric" value="<?php echo htmlspecialchars((string) ($database['port'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_name">Nom de base</label>
            <input id="database_name" name="database[name]" type="text" value="<?php echo htmlspecialchars((string) ($database['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_user">Identifiant SQL</label>
            <input id="database_user" name="database[user]" type="text" value="<?php echo htmlspecialchars((string) ($database['user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="database_password">Mot de passe SQL</label>
            <input id="database_password" name="database[password]" type="password" value="" autocomplete="new-password" />
            <small>Masqué côté interface. Laisser vide pour conserver la valeur enregistrée<?php echo !empty($database['passwordConfigured']) ? ' (' . htmlspecialchars((string) ($database['passwordMask'] ?? '********'), ENT_QUOTES, 'UTF-8') . ')' : ''; ?>.</small>
          </div>
          <div class="field">
            <label for="database_prefix">Préfixe des tables</label>
            <input id="database_prefix" name="database[prefix]" type="text" value="<?php echo htmlspecialchars((string) ($database['prefix'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit">Enregistrer la base SQL</button>
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
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-url-title">URL publique</h3>
          <p>Domaine HTTP, domaine SSL et chemin de base utilisés pour générer les URLs du site et de l’admin.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="url_domain">Domaine</label>
            <input id="url_domain" name="url[domain]" type="text" value="<?php echo htmlspecialchars((string) ($url['domain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="example.com" />
            <small>Sans protocole. Tu peux aussi coller une URL complète, seul le domaine sera conservé.</small>
          </div>
          <div class="field">
            <label for="url_ssl_domain">Domaine SSL</label>
            <input id="url_ssl_domain" name="url[ssl_domain]" type="text" value="<?php echo htmlspecialchars((string) ($url['sslDomain'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="secure.example.com" />
            <small>Utilisé en HTTPS. Laisse vide pour réutiliser le domaine standard.</small>
          </div>
          <div class="field">
            <label for="url_base_path">Chemin de base</label>
            <input id="url_base_path" name="url[base_path]" type="text" value="<?php echo htmlspecialchars((string) ($url['basePath'] ?? '/'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="/" required />
            <small>Le bon caractère par défaut est <code>/</code>. Les saisies en <code>\...</code> ou <code>/...</code> sont normalisées.</small>
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit">Enregistrer les URL</button>
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
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-admin-title">Connexion admin</h3>
          <p>Identifiant admin par e-mail et mot de passe hashé à la sauvegarde.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label for="admin_identifier">E-mail admin</label>
          <input id="admin_identifier" name="admin[identifier]" type="email" inputmode="email" value="<?php echo htmlspecialchars((string) ($admin['identifier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required />
        </div>
        <div class="field">
          <label for="admin_password">Mot de passe admin</label>
          <input id="admin_password" name="admin[password]" type="password" value="" autocomplete="new-password" />
          <small>Hashé avant sauvegarde. Laisser vide pour conserver le mot de passe actuel<?php echo !empty($admin['passwordConfigured']) ? ' (déjà enregistré)' : ''; ?>.</small>
        </div>
        <div class="field">
          <label for="admin_allowed_ips">IP autorisées</label>
          <input id="admin_allowed_ips" name="admin[allowed_ips]" type="text" value="<?php echo htmlspecialchars((string) ($admin['allowedIps'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="203.0.113.10, 198.51.100.0/24" />
          <small>Sépare les IP IPv4/IPv6 ou plages CIDR avec des virgules. Laisse vide pour autoriser toutes les IP.</small>
        </div>
        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="admin[totp_enabled]" value="0" />
            <input type="checkbox" name="admin[totp_enabled]" value="1"<?php echo !empty($admin['totpEnabled']) ? ' checked' : ''; ?> />
            <span>Activer le 2FA TOTP (désactivé automatiquement en localhost)</span>
          </label>
        </div>
        <div class="field">
          <label for="admin_totp_secret">Secret TOTP (Base32)</label>
          <input id="admin_totp_secret" name="admin[totp_secret]" type="password" value="" autocomplete="new-password" placeholder="JBSWY3DPEHPK3PXP" />
          <small>Laisser vide pour conserver le secret actuel<?php echo !empty($admin['totpSecretConfigured']) ? ' (déjà enregistré)' : ''; ?>. Format attendu: Base32 (A-Z, 2-7), au moins 16 caractères.</small>
        </div>
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="admin_inactivity_timeout_seconds">Timeout d’inactivité (secondes)</label>
            <input id="admin_inactivity_timeout_seconds" name="admin[inactivity_timeout_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($admin['inactivityTimeoutSeconds'] ?? 1200), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small>1200 = 20 minutes.</small>
          </div>
          <div class="field">
            <label for="admin_reauth_timeout_seconds">Fenêtre de ré-authentification (secondes)</label>
            <input id="admin_reauth_timeout_seconds" name="admin[reauth_timeout_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($admin['reauthTimeoutSeconds'] ?? 600), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small>Doit être inférieure ou égale au timeout d’inactivité.</small>
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit">Enregistrer l’accès admin</button>
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
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-head-title">Métadonnées globales du head</h3>
          <p>Injection globale contrôlée des balises meta, link canonical/alternate et scripts JSON-LD.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label for="head_metadata_html">Balises à injecter dans le head public</label>
          <textarea id="head_metadata_html" name="head[metadata_html]" rows="12" spellcheck="false"><?php echo htmlspecialchars((string) ($head['metadataHtml'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
          <small>Balises autorisées : <code>&lt;meta&gt;</code>, <code>&lt;link rel="canonical|alternate"&gt;</code> et <code>&lt;script type="application/ld+json"&gt;</code>. Tout le reste est ignoré à la sauvegarde.</small>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit">Enregistrer les métadonnées</button>
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
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-tarteaucitron-title">Gestion tarteaucitron</h3>
          <p>Pilotage du bandeau de consentement, de l’icône et des consent modes exposés au site public.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="tarteaucitron[enabled]" value="0" />
            <input type="checkbox" name="tarteaucitron[enabled]" value="1"<?php echo !empty($tarteaucitron['enabled']) ? ' checked' : ''; ?> />
            <span>Activer tarteaucitron sur le site public</span>
          </label>
        </div>
        <div class="settings-dialog__grid">
          <div class="field">
            <label for="tarteaucitron_privacy_url">URL politique de confidentialité</label>
            <input id="tarteaucitron_privacy_url" name="tarteaucitron[privacy_url]" type="text" value="<?php echo htmlspecialchars((string) ($tarteaucitron['privacyUrl'] ?? '/'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="/" required />
            <small>Utilise une adresse interne commençant par <code>/</code>. Les anciennes valeurs en <code>\...</code> ou <code>/...</code> sont normalisées automatiquement.</small>
          </div>
          <div class="field">
            <label for="tarteaucitron_orientation">Position de la bannière</label>
            <select id="tarteaucitron_orientation" name="tarteaucitron[orientation]">
              <?php foreach (['bottom' => 'Bas', 'top' => 'Haut', 'middle' => 'Milieu'] as $value => $label): ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($tarteaucitron['orientation'] ?? 'bottom') === $value) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="tarteaucitron_icon_position">Position de l’icône</label>
            <select id="tarteaucitron_icon_position" name="tarteaucitron[icon_position]">
              <?php foreach (['BottomRight', 'BottomLeft', 'TopRight', 'TopLeft'] as $value): ?>
              <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo (($tarteaucitron['iconPosition'] ?? 'BottomRight') === $value) ? ' selected' : ''; ?>><?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <div class="settings-services" data-tarteaucitron-services>
            <div class="settings-services__header">
              <div>
                <label class="settings-services__label">Services chargés après consentement</label>
                <p class="settings-services__summary">
                  Ajoute ici les identifiants techniques des services tarteaucitron à activer, par exemple
                  <code>youtube</code>, <code>vimeo</code>, <code>googlemaps</code> ou <code>recaptcha</code>.
                </p>
              </div>
              <button type="button" class="button-muted settings-services__add" data-service-add>Ajouter un service</button>
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
                  aria-label="Service tarteaucitron <?php echo $serviceIndex + 1; ?>"
                />
                <button type="button" class="button-muted settings-service-row__remove" data-service-remove>Retirer</button>
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
                  aria-label="Nouveau service tarteaucitron"
                />
                <button type="button" class="button-muted settings-service-row__remove" data-service-remove>Retirer</button>
              </div>
            </template>
          </div>
        </div>
        <div class="field">
          <label for="tarteaucitron_user_config_json">Variables JS services (objet JSON)</label>
          <textarea
            id="tarteaucitron_user_config_json"
            name="tarteaucitron[user_config_json]"
            rows="8"
            spellcheck="false"
            placeholder='{"googletagmanagerId":"GTM-XXXXXXX","googleadsId":"AW-XXXXXXX"}'
          ><?php echo htmlspecialchars($tarteaucitronUserConfigJson, ENT_QUOTES, 'UTF-8'); ?></textarea>
          <small>
            Ces clés sont injectées dans <code>tarteaucitron.user</code> pour tous les services.
            Exemple GTM: <code>{"googletagmanagerId":"GTM-MKG2FFBZ"}</code>.
          </small>
        </div>
        <div class="settings-dialog__grid">
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[show_icon]" value="0" />
              <input type="checkbox" name="tarteaucitron[show_icon]" value="1"<?php echo !empty($tarteaucitron['showIcon']) ? ' checked' : ''; ?> />
              <span>Afficher l’icône persistante</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[show_alert_small]" value="0" />
              <input type="checkbox" name="tarteaucitron[show_alert_small]" value="1"<?php echo !empty($tarteaucitron['showAlertSmall']) ? ' checked' : ''; ?> />
              <span>Afficher le rappel réduit</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[high_privacy]" value="0" />
              <input type="checkbox" name="tarteaucitron[high_privacy]" value="1"<?php echo !empty($tarteaucitron['highPrivacy']) ? ' checked' : ''; ?> />
              <span>Activer le mode haute confidentialité</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[accept_all_cta]" value="0" />
              <input type="checkbox" name="tarteaucitron[accept_all_cta]" value="1"<?php echo !empty($tarteaucitron['acceptAllCta']) ? ' checked' : ''; ?> />
              <span>Afficher “Tout accepter”</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[deny_all_cta]" value="0" />
              <input type="checkbox" name="tarteaucitron[deny_all_cta]" value="1"<?php echo !empty($tarteaucitron['denyAllCta']) ? ' checked' : ''; ?> />
              <span>Afficher “Tout refuser”</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[mandatory]" value="0" />
              <input type="checkbox" name="tarteaucitron[mandatory]" value="1"<?php echo !empty($tarteaucitron['mandatory']) ? ' checked' : ''; ?> />
              <span>Forcer les services obligatoires</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[google_consent_mode]" value="0" />
              <input type="checkbox" name="tarteaucitron[google_consent_mode]" value="1"<?php echo !empty($tarteaucitron['googleConsentMode']) ? ' checked' : ''; ?> />
              <span>Activer Google Consent Mode</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="tarteaucitron[bing_consent_mode]" value="0" />
              <input type="checkbox" name="tarteaucitron[bing_consent_mode]" value="1"<?php echo !empty($tarteaucitron['bingConsentMode']) ? ' checked' : ''; ?> />
              <span>Activer Bing Consent Mode</span>
            </label>
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit">Enregistrer tarteaucitron</button>
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
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-discussions-title">Discussions publiques et anti-bot</h3>
          <p>Activation des messages sous articles, limites anti-spam et configuration reCAPTCHA.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="settings-dialog__grid">
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="discussions[enabled]" value="0" />
              <input type="checkbox" name="discussions[enabled]" value="1"<?php echo !empty($discussions['enabled']) ? ' checked' : ''; ?> />
              <span>Autoriser les lecteurs à ouvrir une discussion sous les articles</span>
            </label>
          </div>
          <div class="field">
            <label class="checkbox-field">
              <input type="hidden" name="discussions[require_account]" value="0" />
              <input type="checkbox" name="discussions[require_account]" value="1"<?php echo !empty($discussions['requireAccount']) ? ' checked' : ''; ?> />
              <span>Forcer un compte client (non recommandé sur ce projet)</span>
            </label>
          </div>
          <div class="field">
            <label for="discussion_rate_limit_per_ip">Limite IP locale (messages)</label>
            <input id="discussion_rate_limit_per_ip" name="discussions[rate_limit_per_ip]" type="number" min="1" max="500" value="<?php echo htmlspecialchars((string) ($discussions['rateLimitPerIp'] ?? 6), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_rate_limit_window">Fenêtre locale (secondes)</label>
            <input id="discussion_rate_limit_window" name="discussions[rate_limit_window]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($discussions['rateLimitWindow'] ?? 600), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_global_rate_limit_per_ip">Limite IP globale (messages)</label>
            <input id="discussion_global_rate_limit_per_ip" name="discussions[global_rate_limit_per_ip]" type="number" min="1" max="5000" value="<?php echo htmlspecialchars((string) ($discussions['globalRateLimitPerIp'] ?? 20), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_global_rate_limit_window">Fenêtre globale (secondes)</label>
            <input id="discussion_global_rate_limit_window" name="discussions[global_rate_limit_window]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($discussions['globalRateLimitWindow'] ?? 3600), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_min_form_fill_seconds">Délai minimum de saisie (secondes)</label>
            <input id="discussion_min_form_fill_seconds" name="discussions[min_form_fill_seconds]" type="number" min="0" max="120" value="<?php echo htmlspecialchars((string) ($discussions['minFormFillSeconds'] ?? 3), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_max_form_age_seconds">Validité du formulaire (secondes)</label>
            <input id="discussion_max_form_age_seconds" name="discussions[max_form_age_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($discussions['maxFormAgeSeconds'] ?? 7200), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="discussion_honeypot_field">Nom du champ honeypot</label>
            <input id="discussion_honeypot_field" name="discussions[honeypot_field]" type="text" value="<?php echo htmlspecialchars((string) ($discussions['honeypotField'] ?? 'website'), ENT_QUOTES, 'UTF-8'); ?>" required />
            <small>Champ invisible côté front, déclenche un blocage s’il est rempli.</small>
          </div>
        </div>

        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="discussions[recaptcha_enabled]" value="0" />
            <input type="checkbox" name="discussions[recaptcha_enabled]" value="1"<?php echo !empty($discussions['recaptchaEnabled']) ? ' checked' : ''; ?> />
            <span>Activer reCAPTCHA (lié à tarteaucitron)</span>
          </label>
        </div>

        <div class="settings-dialog__grid">
          <div class="field">
            <label for="discussion_recaptcha_site_key">reCAPTCHA Site Key (publique)</label>
            <input id="discussion_recaptcha_site_key" name="discussions[recaptcha_site_key]" type="text" value="<?php echo htmlspecialchars((string) ($discussions['recaptchaSiteKey'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="6Lc..." />
          </div>
          <div class="field">
            <label for="discussion_recaptcha_secret_key">reCAPTCHA Secret Key</label>
            <input id="discussion_recaptcha_secret_key" name="discussions[recaptcha_secret_key]" type="password" value="" autocomplete="new-password" />
            <small>Laisser vide pour conserver la clé actuelle<?php echo !empty($discussions['recaptchaSecretKeyConfigured']) ? ' (déjà enregistrée)' : ''; ?>.</small>
          </div>
          <div class="field">
            <label for="discussion_recaptcha_minimum_score">Score minimum (v3)</label>
            <input id="discussion_recaptcha_minimum_score" name="discussions[recaptcha_minimum_score]" type="number" min="0" max="1" step="0.1" value="<?php echo htmlspecialchars((string) ($discussions['recaptchaMinimumScore'] ?? 0.5), ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
          <div class="field">
            <label for="discussion_recaptcha_timeout_seconds">Délai API (secondes)</label>
            <input id="discussion_recaptcha_timeout_seconds" name="discussions[recaptcha_timeout_seconds]" type="number" min="3" max="20" value="<?php echo htmlspecialchars((string) ($discussions['recaptchaTimeoutSeconds'] ?? 8), ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
        </div>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit">Enregistrer la sécurité des discussions</button>
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
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-instagram-title">Flux Instagram sur l’accueil</h3>
          <p>Affiche les derniers posts Instagram en bas de la page d’accueil, avec rotation automatique.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <div class="field">
          <label class="checkbox-field">
            <input type="hidden" name="instagram[enabled]" value="0" />
            <input type="checkbox" name="instagram[enabled]" value="1"<?php echo !empty($instagram['enabled']) ? ' checked' : ''; ?> />
            <span>Activer le bloc Instagram en bas de la page d’accueil</span>
          </label>
        </div>

        <div class="settings-dialog__grid">
          <div class="field">
            <label for="instagram_username">Compte Instagram (sans @)</label>
            <input id="instagram_username" name="instagram[username]" type="text" value="<?php echo htmlspecialchars((string) ($instagram['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="paulineetnoel" />
          </div>
          <div class="field">
            <label for="instagram_user_id">User ID Instagram (optionnel)</label>
            <input id="instagram_user_id" name="instagram[user_id]" type="text" inputmode="numeric" value="<?php echo htmlspecialchars((string) ($instagram['userId'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="178414..." />
            <small>Laisse vide pour utiliser l’endpoint <code>/me/media</code> via le token.</small>
          </div>
          <div class="field">
            <label for="instagram_access_token">Access Token Instagram</label>
            <input id="instagram_access_token" name="instagram[access_token]" type="password" value="" autocomplete="new-password" />
            <small>Laisser vide pour conserver le token actuel<?php echo !empty($instagram['accessTokenConfigured']) ? ' (déjà enregistré)' : ''; ?>.</small>
          </div>
          <div class="field">
            <label for="instagram_limit">Nombre de posts</label>
            <input id="instagram_limit" name="instagram[limit]" type="number" min="1" max="20" value="<?php echo htmlspecialchars((string) ($instagram['limit'] ?? 6), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="instagram_rotation_interval_ms">Rotation auto (millisecondes)</label>
            <input id="instagram_rotation_interval_ms" name="instagram[rotation_interval_ms]" type="number" min="2500" max="30000" step="100" value="<?php echo htmlspecialchars((string) ($instagram['rotationIntervalMs'] ?? 5500), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="instagram_cache_ttl_seconds">Durée de cache (secondes)</label>
            <input id="instagram_cache_ttl_seconds" name="instagram[cache_ttl_seconds]" type="number" min="60" max="86400" value="<?php echo htmlspecialchars((string) ($instagram['cacheTtlSeconds'] ?? 1800), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
          <div class="field">
            <label for="instagram_timeout_seconds">Timeout API (secondes)</label>
            <input id="instagram_timeout_seconds" name="instagram[timeout_seconds]" type="number" min="3" max="20" value="<?php echo htmlspecialchars((string) ($instagram['timeoutSeconds'] ?? 8), ENT_QUOTES, 'UTF-8'); ?>" required />
          </div>
        </div>

        <p class="settings-dialog__summary">
          Aide de configuration : <code>docs/instagram-feed-setup.md</code>
        </p>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit" name="settings_action" value="save">Enregistrer Instagram</button>
        <button type="submit" class="button-muted" name="settings_action" value="instagram_test">Tester la connexion</button>
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
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-observability-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_DIALOG_TITLE', 'Alertes logs (scheduler)'), ENT_QUOTES, 'UTF-8'); ?></h3>
          <p><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_DIALOG_LEAD', 'Le mode pilote check_log_alerts.php. Les secrets webhook/email restent gérés côté système.'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
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
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_SETTINGS_LOG_ALERTS_SAVE', 'Enregistrer les alertes logs'), ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-translations" aria-labelledby="settings-dialog-translations-title">
  <form method="post" action="<?php echo htmlspecialchars($settingsAction, ENT_QUOTES, 'UTF-8'); ?>" class="settings-dialog__form" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
    <input type="hidden" name="settings_section" value="translations" />
    <div class="region-modal__surface">
      <div class="region-modal__header">
        <div>
          <p class="region-modal__eyebrow">Paramètres</p>
          <h3 id="settings-dialog-translations-title">Overrides de traductions</h3>
          <p>Format: une ligne par clé, <code>CLE=Valeur</code>. Les clés inconnues sont refusées.</p>
        </div>
        <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
      </div>
      <div class="region-modal__body settings-dialog__body">
        <?php if ($translationLanguages === []): ?>
        <p class="notice-muted">Aucune langue configurée.</p>
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
                Dictionnaire existant <?php echo strtoupper(htmlspecialchars($language, ENT_QUOTES, 'UTF-8')); ?>
                (<?php echo (int) ($translationDictionaryCounts[$language] ?? 0); ?>)
              </summary>
              <textarea
                id="<?php echo htmlspecialchars($dictionaryTextareaId, ENT_QUOTES, 'UTF-8'); ?>"
                rows="10"
                spellcheck="false"
                readonly
              ><?php echo htmlspecialchars($dictionaryTextareaValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <small>Référence en lecture seule (clés existantes et traductions actuelles).</small>
            </details>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="region-modal__actions actions-inline actions-inline-end">
        <button type="button" class="button-muted" data-region-modal-close>Annuler</button>
        <button type="submit">Enregistrer les traductions</button>
      </div>
    </div>
  </form>
</dialog>

<dialog class="region-modal settings-dialog" id="settings-dialog-security" aria-labelledby="settings-dialog-security-title">
  <div class="region-modal__surface">
    <div class="region-modal__header">
      <div>
        <p class="region-modal__eyebrow">Information</p>
        <h3 id="settings-dialog-security-title">Règles de sécurité</h3>
        <p>Résumé des contraintes déjà appliquées à la sauvegarde des paramètres sensibles.</p>
      </div>
      <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
    </div>
    <div class="region-modal__body settings-dialog__body">
      <ul class="settings-dialog__list">
        <li>Le mot de passe admin est hashé via <code>password_hash()</code> avant écriture.</li>
        <li>Le 2FA TOTP peut être activé et reste automatiquement bypassé en localhost.</li>
        <li>La session admin coupe automatiquement après inactivité (20 min par défaut).</li>
        <li>Les actions sensibles forcent une ré-authentification périodique.</li>
        <li>Le mot de passe BDD n’est jamais réaffiché dans l’interface.</li>
        <li>La sauvegarde est écrite hors webroot : <code><?php echo !empty($storage['outsideWebroot']) ? 'oui' : 'à vérifier'; ?></code>.</li>
        <li>Chaque changement sensible est journalisé dans <code>backend/data/logs/security.log</code>.</li>
      </ul>
      <p class="settings-dialog__summary">
        Overrides actifs : <code><?php echo htmlspecialchars((string) ($storage['databaseOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>,
        <code><?php echo htmlspecialchars((string) ($storage['adminOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>,
        <code><?php echo htmlspecialchars((string) ($storage['siteOverridePath'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>.
      </p>
    </div>
    <div class="region-modal__actions actions-inline actions-inline-end">
      <button type="button" class="button-muted" data-region-modal-close>Fermer</button>
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
