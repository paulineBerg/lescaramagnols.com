<?php
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
?>
<h1 id="admin-login-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_HEADING', 'Espace admin'), ENT_QUOTES, 'UTF-8'); ?></h1>
<p class="intro">
  <?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_INTRO', 'Connectez-vous pour accéder au tableau de bord du site Les Caramagnols.'), ENT_QUOTES, 'UTF-8'); ?>
</p>
<?php if (($notice ?? null) !== null): ?>
<div class="notice notice-success" role="status"><?php echo htmlspecialchars((string) $notice, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error" role="alert"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="<?php echo htmlspecialchars((string) ($adminLoginUrl ?? admin_url('login')), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" novalidate>
  <div class="field">
    <label for="identifier"><?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_IDENTIFIER_LABEL', 'E-mail admin'), ENT_QUOTES, 'UTF-8'); ?></label>
    <input
      id="identifier"
      name="identifier"
      type="email"
      inputmode="email"
      required
      value="<?php echo htmlspecialchars((string) ($submittedIdentifier ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
      autofocus
    />
  </div>
  <?php if (($passwordRequired ?? true)): ?>
  <div class="field">
    <label for="password"><?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_PASSWORD_LABEL', 'Mot de passe'), ENT_QUOTES, 'UTF-8'); ?></label>
    <div class="admin-password-field">
      <input id="password" name="password" type="password" required autocomplete="current-password" />
      <button
        class="admin-password-toggle"
        type="button"
        data-admin-password-toggle
        data-admin-password-show="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>"
        data-admin-password-hide="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8'); ?>"
        aria-controls="password"
        aria-pressed="false"
      >
        <?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>
  <?php endif; ?>
  <?php if (!empty($totpRequired)): ?>
  <div class="field">
    <label for="totp_code"><?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_TOTP_LABEL', 'Code 2FA'), ENT_QUOTES, 'UTF-8'); ?></label>
    <input id="totp_code" name="totp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="<?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_TOTP_PLACEHOLDER', '123456'), ENT_QUOTES, 'UTF-8'); ?>" required />
  </div>
  <?php endif; ?>
  <?php if (!empty($persistentAdminEnabled)): ?>
  <div class="field">
    <label class="checkbox-inline">
      <input type="checkbox" name="trust_admin_device" value="1" />
      <?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_TRUST_DEVICE', 'Autoriser cet appareil pour l’administration pendant 30 jours'), ENT_QUOTES, 'UTF-8'); ?>
    </label>
  </div>
  <?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
  <div class="actions">
    <button class="button-block" type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_SUBMIT', 'Se connecter'), ENT_QUOTES, 'UTF-8'); ?></button>
  </div>
</form>
<p class="hint">
  <?php echo htmlspecialchars($translate('TXT_ADMIN_LOGIN_CANONICAL_PATH', 'Route canonique admin :'), ENT_QUOTES, 'UTF-8'); ?> <code><?php echo htmlspecialchars((string) ($loginPath ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
</p>
