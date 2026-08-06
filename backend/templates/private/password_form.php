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

$privateFormAction = is_string($privateFormAction ?? null) ? (string) $privateFormAction : '';
$privateFormCsrfToken = is_string($privateFormCsrfToken ?? null) ? (string) $privateFormCsrfToken : '';
$privateFormSubmitLabel = is_string($privateFormSubmitLabel ?? null) ? (string) $privateFormSubmitLabel : $translate('TXT_PRIVATE_FORM_SUBMIT', 'Valider');
$privateFormError = is_string($privateFormError ?? null) ? (string) $privateFormError : null;
$privateFormIntro = is_string($privateFormIntro ?? null) ? (string) $privateFormIntro : '';
$privateFormHelp = is_string($privateFormHelp ?? null) ? (string) $privateFormHelp : '';
?>

<?php if ($privateFormIntro !== ''): ?>
  <p class="muted"><?php echo htmlspecialchars($privateFormIntro, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php if ($privateFormError !== null): ?>
  <div class="notice notice-error" role="alert"><?php echo htmlspecialchars($privateFormError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($privateFormAction, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($privateFormCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
  <div>
    <label for="private-password"><?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_NEW', 'Nouveau mot de passe'), ENT_QUOTES, 'UTF-8'); ?></label>
    <div class="private-password-field">
      <input id="private-password" name="password" type="password" autocomplete="new-password" required />
      <button
        class="private-password-toggle"
        type="button"
        data-private-password-toggle
        data-private-password-show="<?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>"
        data-private-password-hide="<?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8'); ?>"
        aria-controls="private-password"
        aria-pressed="false"
      >
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>
  <div>
    <label for="private-password-confirm"><?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_CONFIRM', 'Confirmation'), ENT_QUOTES, 'UTF-8'); ?></label>
    <div class="private-password-field">
      <input id="private-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required />
      <button
        class="private-password-toggle"
        type="button"
        data-private-password-toggle
        data-private-password-show="<?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>"
        data-private-password-hide="<?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8'); ?>"
        aria-controls="private-password-confirm"
        aria-pressed="false"
      >
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>
  <?php if ($privateFormHelp !== ''): ?>
    <p class="muted"><?php echo htmlspecialchars($privateFormHelp, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <div class="private-actions">
    <button type="submit"><?php echo htmlspecialchars($privateFormSubmitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
  </div>
</form>
