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
$changeEmailAddress = 'private@lescaramagnols.com';
$completedProfileFields = 0;
foreach ([$fullName, $postalAddress, $phone] as $profileField) {
    if (trim($profileField) !== '') {
        ++$completedProfileFields;
    }
}
$profileCompletion = (int) round(($completedProfileFields / 3) * 100);
?>

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
      <p><strong><?php echo $escape($email !== '' ? $email : '—'); ?></strong></p>
      <p class="muted"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_EMAIL_DASHBOARD_HELP', 'Changement uniquement sur demande sécurisée.')); ?></p>
    </section>
    <section class="private-dashboard-panel">
      <h3><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_PROFILE_STATE', 'Profil')); ?></h3>
      <p><strong><?php echo $escape((string) $profileCompletion); ?> %</strong></p>
      <p class="muted"><?php echo $escape($completedProfileFields . '/3 champs renseignés'); ?></p>
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
