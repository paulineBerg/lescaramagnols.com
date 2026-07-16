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

$csrfToken = is_string($csrfToken ?? null) ? (string) $csrfToken : '';
?>

<form method="post" action="<?php echo htmlspecialchars(private_portal_url('password_forgot'), ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
  <div>
    <label for="private-reset-identifier"><?php echo htmlspecialchars($translate('TXT_PRIVATE_IDENTIFIER_LABEL', 'Adresse email'), ENT_QUOTES, 'UTF-8'); ?></label>
    <input id="private-reset-identifier" name="identifier" type="email" autocomplete="email" required />
  </div>
  <button type="submit"><?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_FORGOT_SUBMIT', 'Préparer la réinitialisation'), ENT_QUOTES, 'UTF-8'); ?></button>
</form>
