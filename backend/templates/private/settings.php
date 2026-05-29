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
?>

<section class="card private-card-wide">
  <span class="tag"><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_TAG', 'Compte membre')); ?></span>
  <h2><?php echo $escape($translate('TXT_PRIVATE_SETTINGS_TITLE', 'Paramètres membre')); ?></h2>
  <p class="muted">
    <?php echo $escape($translate('TXT_PRIVATE_SETTINGS_INTRO', 'Ces informations sont facultatives et restent rattachées à votre compte privé.')); ?>
  </p>

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
</section>
