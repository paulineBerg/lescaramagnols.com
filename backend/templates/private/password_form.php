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
?>

<?php if ($privateFormError !== null): ?>
  <div class="notice notice-error" role="alert"><?php echo htmlspecialchars($privateFormError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form method="post" action="<?php echo htmlspecialchars($privateFormAction, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($privateFormCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
  <div>
    <label for="private-password"><?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_NEW', 'Nouveau mot de passe'), ENT_QUOTES, 'UTF-8'); ?></label>
    <input id="private-password" name="password" type="password" autocomplete="new-password" required />
  </div>
  <div>
    <label for="private-password-confirm"><?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_CONFIRM', 'Confirmation'), ENT_QUOTES, 'UTF-8'); ?></label>
    <input id="private-password-confirm" name="password_confirm" type="password" autocomplete="new-password" required />
  </div>
  <button type="submit"><?php echo htmlspecialchars($privateFormSubmitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
</form>
