<?php
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

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$profile = is_array($viewModel['privateMemberProfile'] ?? null) ? $viewModel['privateMemberProfile'] : [];
$formAction = is_string($viewModel['privateSettingsFormAction'] ?? null)
    ? (string) $viewModel['privateSettingsFormAction']
    : private_portal_url('member_settings');
$csrfToken = is_string($viewModel['privateSettingsCsrfToken'] ?? null) ? (string) $viewModel['privateSettingsCsrfToken'] : '';
$email = is_string($profile['email'] ?? null) ? (string) $profile['email'] : '';
$fullName = is_string($profile['fullName'] ?? null) ? (string) $profile['fullName'] : '';
$postalAddress = is_string($profile['postalAddress'] ?? null) ? (string) $profile['postalAddress'] : '';
$phone = is_string($profile['phone'] ?? null) ? (string) $profile['phone'] : '';
$smtp = is_array($viewModel['privateMemberSmtpSettings'] ?? null) ? $viewModel['privateMemberSmtpSettings'] : [];
$smtpConfigured = !empty($viewModel['privateMemberSmtpConfigured']);
$activeTab = is_string($viewModel['privateSettingsActiveTab'] ?? null) ? (string) $viewModel['privateSettingsActiveTab'] : 'profile';
$activeTab = in_array($activeTab, ['profile', 'smtp'], true) ? $activeTab : 'profile';
$smtpPopup = !empty($viewModel['privateSettingsSmtpPopup']);
$changeEmailAddress = 'private@lescaramagnols.com';
$completedProfileFields = 0;
foreach ([$fullName, $postalAddress, $phone] as $profileField) {
    if (trim($profileField) !== '') {
        ++$completedProfileFields;
    }
}
$profileCompletion = (int) round(($completedProfileFields / 3) * 100);
$tabUrl = static fn (string $tab): string => $formAction . '?' . http_build_query(['tab' => $tab], '', '&', PHP_QUERY_RFC3986);
?>

<nav class="private-module-nav private-section-tabs" aria-label="<?php echo $escape($translate('TXT_PRIVATE_SETTINGS_TABS', 'Sections paramètres')); ?>">
  <div class="private-module-nav-row">
    <a class="<?php echo $activeTab === 'profile' ? 'active' : ''; ?>" href="<?php echo $escape($tabUrl('profile')); ?>">
      <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_PROFILE_TAB', 'Profil')); ?>
    </a>
    <a class="<?php echo $activeTab === 'smtp' ? 'active' : ''; ?>" href="<?php echo $escape($tabUrl('smtp')); ?>">
      <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_TAB', 'SMTP')); ?>
    </a>
  </div>
</nav>

<?php if ($activeTab === 'profile'): ?>
<section class="private-module-dashboard">
  <div class="private-list-header">
    <div>
      <span class="tag"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_TAG', 'Compte membre')); ?></span>
      <h2><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_TITLE', 'Paramètres membre')); ?></h2>
      <p class="muted">
        <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_INTRO', 'Ces informations sont facultatives et restent rattachées à votre compte privé.')); ?>
      </p>
    </div>
    <button type="button" class="private-create-button" data-private-dialog-open="private-settings-profile-dialog">
      <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_EDIT', 'Modifier mes informations')); ?>
    </button>
  </div>

  <div class="private-dashboard-summary">
    <section class="private-dashboard-panel">
      <h3><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_EMAIL_LABEL', 'Email de connexion')); ?></h3>
      <p><strong><?php echo $escape($email !== '' ? $email : '-'); ?></strong></p>
      <p class="muted"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_EMAIL_DASHBOARD_HELP', 'Changement uniquement sur demande sécurisée.')); ?></p>
    </section>
    <section class="private-dashboard-panel">
      <h3><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_PROFILE_STATE', 'Profil')); ?></h3>
      <p><strong><?php echo $escape((string) $profileCompletion); ?> %</strong></p>
      <p class="muted"><?php echo $escape(sprintf($translate('TXT_PRIVATE_SETTINGS_PROFILE_COMPLETION', '%d/3 champs renseignés'), $completedProfileFields)); ?></p>
    </section>
    <section class="private-dashboard-panel">
      <h3><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_CONTACT', 'Contact changement email')); ?></h3>
      <p><a href="mailto:<?php echo $escape($changeEmailAddress); ?>"><?php echo $escape($changeEmailAddress); ?></a></p>
      <p class="muted"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_CONTACT_HELP', 'L’adresse de connexion reste protégée hors formulaire.')); ?></p>
    </section>
  </div>
</section>

<dialog class="private-dialog" id="private-settings-profile-dialog" aria-labelledby="private-settings-profile-title">
  <div class="private-dialog-panel">
    <header class="private-dialog-header">
      <h3 id="private-settings-profile-title"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_EDIT', 'Modifier mes informations')); ?></h3>
      <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="<?php echo $escape($translate('TXT_PRIVATE_COMMON_CLOSE', 'Fermer')); ?>">×</button>
    </header>
    <form method="post" action="<?php echo $escape($formAction); ?>" autocomplete="on" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
      <input type="hidden" name="action" value="profile" />

    <div>
      <label for="member-profile-email"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_EMAIL_LABEL', 'Email de connexion')); ?></label>
      <input id="member-profile-email" type="email" value="<?php echo $escape($email); ?>" autocomplete="email" readonly aria-describedby="member-profile-email-help" />
      <p id="member-profile-email-help" class="muted">
        <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_EMAIL_LOCKED_PREFIX', 'Cet email n’est pas modifiable depuis l’espace privé. Changement possible exclusivement sur demande à')); ?>
        <a href="mailto:<?php echo $escape($changeEmailAddress); ?>"><?php echo $escape($changeEmailAddress); ?></a>.
      </p>
    </div>

    <div>
      <label for="member-profile-full-name"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_NAME_LABEL', 'Nom')); ?></label>
      <input id="member-profile-full-name" name="full_name" type="text" value="<?php echo $escape($fullName); ?>" maxlength="160" autocomplete="name" />
    </div>

    <div>
      <label for="member-profile-address"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_ADDRESS_LABEL', 'Adresse')); ?></label>
      <textarea id="member-profile-address" name="postal_address" rows="4" maxlength="500" autocomplete="street-address"><?php echo $escape($postalAddress); ?></textarea>
    </div>

    <div>
      <label for="member-profile-phone"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_PHONE_LABEL', 'Téléphone')); ?></label>
      <input id="member-profile-phone" name="phone" type="tel" value="<?php echo $escape($phone); ?>" maxlength="64" autocomplete="tel" inputmode="tel" />
    </div>

    <div class="private-actions">
      <button type="submit"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SAVE', 'Enregistrer')); ?></button>
    </div>
    </form>
  </div>
</dialog>
<?php endif; ?>

<?php if ($activeTab === 'smtp'): ?>
<section class="private-module-dashboard">
  <div class="private-list-header">
    <div>
      <span class="tag"><?php echo $escape($smtpConfigured ? $translate('TXT_PRIVATE_SETTINGS_SMTP_READY', 'SMTP prêt') : $translate('TXT_PRIVATE_SETTINGS_SMTP_REQUIRED_TAG', 'SMTP à configurer')); ?></span>
      <h2><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_TITLE', 'Configuration SMTP')); ?></h2>
      <p class="muted">
        <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_INTRO', 'Ces paramètres sont utilisés pour envoyer les emails depuis vos modules privés, notamment les demandes de paiement et documents locatifs.')); ?>
      </p>
    </div>
  </div>

  <form method="post" action="<?php echo $escape($formAction); ?>?tab=smtp" autocomplete="on" class="private-settings-smtp-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>" />
    <input type="hidden" name="action" value="smtp_settings" />

    <section class="card private-list-section">
      <div class="private-form-grid">
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_ENABLED', 'Activer SMTP')); ?>
          <select name="enabled">
            <option value="1"<?php echo !empty($smtp['enabled']) ? ' selected' : ''; ?>>Oui</option>
            <option value="0"<?php echo empty($smtp['enabled']) ? ' selected' : ''; ?>>Non</option>
          </select>
        </label>
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_HOST', 'Serveur SMTP')); ?>
          <input type="text" name="smtp_host" maxlength="190" value="<?php echo $escape((string) ($smtp['smtpHost'] ?? '')); ?>" placeholder="ssl0.ovh.net" required />
        </label>
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_PORT', 'Port')); ?>
          <input type="number" name="smtp_port" min="1" max="65535" value="<?php echo $escape((string) (int) ($smtp['smtpPort'] ?? 587)); ?>" required />
        </label>
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_SECURITY', 'Sécurité')); ?>
          <select name="smtp_encryption">
            <?php foreach (['tls' => 'TLS / STARTTLS', 'ssl' => 'SSL', '' => 'Aucune'] as $value => $label): ?>
              <option value="<?php echo $escape($value); ?>"<?php echo (string) ($smtp['smtpEncryption'] ?? 'tls') === $value ? ' selected' : ''; ?>><?php echo $escape($label); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="private-form-grid">
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_USER', 'Utilisateur SMTP')); ?>
          <input type="text" name="smtp_user" maxlength="190" value="<?php echo $escape((string) ($smtp['smtpUser'] ?? '')); ?>" autocomplete="username" />
        </label>
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_PASSWORD', 'Mot de passe SMTP')); ?>
          <span class="private-password-field">
            <input id="private-member-smtp-password" type="password" name="smtp_password" maxlength="512" value="<?php echo !empty($smtp['smtpPasswordConfigured']) ? '**********' : ''; ?>" autocomplete="new-password" />
            <button type="button" class="private-button-secondary" data-private-password-toggle aria-controls="private-member-smtp-password" data-private-password-show="Afficher" data-private-password-hide="Masquer">Afficher</button>
          </span>
        </label>
        <label class="private-checkbox-inline">
          <input type="checkbox" name="clear_smtp_password" value="1" />
          <span><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_CLEAR_PASSWORD', 'Effacer le mot de passe enregistré')); ?></span>
        </label>
      </div>

      <div class="private-form-grid">
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_FROM', 'Adresse expéditeur')); ?>
          <input type="email" name="from_address" maxlength="254" value="<?php echo $escape((string) ($smtp['fromAddress'] ?? $email)); ?>" required />
        </label>
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_FROM_NAME', 'Nom expéditeur')); ?>
          <input type="text" name="from_name" maxlength="120" value="<?php echo $escape((string) ($smtp['fromName'] ?? 'Les Caramagnols')); ?>" required />
        </label>
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_REPLY_TO', 'Adresse de réponse')); ?>
          <input type="email" name="reply_to" maxlength="254" value="<?php echo $escape((string) ($smtp['replyTo'] ?? $email)); ?>" />
        </label>
      </div>

      <div class="private-form-grid">
        <label>
          <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_TEST_RECIPIENT', 'Email de test')); ?>
          <input type="email" name="test_recipient" maxlength="254" value="<?php echo $escape($email); ?>" />
        </label>
      </div>

      <div class="private-actions">
        <button type="submit"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_SAVE', 'Enregistrer SMTP')); ?></button>
        <button type="submit" name="send_test" value="1" class="private-button-secondary"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_TEST', 'Enregistrer et tester')); ?></button>
      </div>
    </section>
  </form>
</section>
<?php endif; ?>

<?php if ($smtpPopup): ?>
<dialog class="private-dialog private-confirm-dialog" id="private-settings-smtp-required-dialog" aria-labelledby="private-settings-smtp-required-title" data-private-dialog-auto-open="1">
  <div class="private-dialog-panel">
    <header class="private-dialog-header">
      <h3 id="private-settings-smtp-required-title"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_REQUIRED_TITLE', 'Paramètres SMTP requis')); ?></h3>
      <button type="button" class="private-dialog-close" data-private-dialog-close aria-label="<?php echo $escape($translate('TXT_PRIVATE_COMMON_CLOSE', 'Fermer')); ?>">×</button>
    </header>
    <p><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_SMTP_REQUIRED_POPUP', 'Veuillez remplir vos paramètres SMTP avant d’envoyer un email.')); ?></p>
    <div class="private-dialog-actions">
      <button type="button" data-private-dialog-close><?php echo $escape($translate('TXT_PRIVATE_COMMON_CLOSE', 'Fermer')); ?></button>
    </div>
  </div>
</dialog>
<?php endif; ?>
