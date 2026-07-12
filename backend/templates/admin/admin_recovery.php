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
<h1 id="admin-recovery-title"><?php echo htmlspecialchars($translate('TXT_ADMIN_RECOVERY_HEADING', 'Récupération admin'), ENT_QUOTES, 'UTF-8'); ?></h1>
<p class="intro">
  <?php echo htmlspecialchars($translate('TXT_ADMIN_RECOVERY_INTRO', 'Utilisez une clé de secours à usage unique pour définir un nouveau mot de passe admin. Le Code 2FA admin sera désactivé et devra être recréé après connexion.'), ENT_QUOTES, 'UTF-8'); ?>
</p>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error" role="alert"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="<?php echo htmlspecialchars((string) ($adminRecoveryUrl ?? admin_url('recovery')), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" novalidate>
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
  <div class="field">
    <label for="recovery_key"><?php echo htmlspecialchars($translate('TXT_ADMIN_RECOVERY_KEY_LABEL', 'Clé de récupération'), ENT_QUOTES, 'UTF-8'); ?></label>
    <textarea id="recovery_key" name="recovery_key" rows="3" required autocomplete="off" spellcheck="false" placeholder="CAR-REC-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"></textarea>
  </div>
  <div class="field">
    <label for="password"><?php echo htmlspecialchars($translate('TXT_ADMIN_RECOVERY_PASSWORD_LABEL', 'Nouveau mot de passe'), ENT_QUOTES, 'UTF-8'); ?></label>
    <div class="admin-password-field">
      <input id="password" name="password" type="password" required autocomplete="new-password" />
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
  <div class="field">
    <label for="password_confirm"><?php echo htmlspecialchars($translate('TXT_ADMIN_RECOVERY_PASSWORD_CONFIRM_LABEL', 'Confirmer le mot de passe'), ENT_QUOTES, 'UTF-8'); ?></label>
    <div class="admin-password-field">
      <input id="password_confirm" name="password_confirm" type="password" required autocomplete="new-password" />
      <button
        class="admin-password-toggle"
        type="button"
        data-admin-password-toggle
        data-admin-password-show="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>"
        data-admin-password-hide="<?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8'); ?>"
        aria-controls="password_confirm"
        aria-pressed="false"
      >
        <?php echo htmlspecialchars($translate('TXT_ADMIN_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
  <div class="actions">
    <button class="button-block" type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_RECOVERY_SUBMIT', 'Réinitialiser l’accès admin'), ENT_QUOTES, 'UTF-8'); ?></button>
  </div>
</form>
<p class="hint">
  <a href="<?php echo htmlspecialchars((string) ($adminLoginUrl ?? admin_url('login')), ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo htmlspecialchars($translate('TXT_ADMIN_RECOVERY_BACK_TO_LOGIN', 'Retour à la connexion admin'), ENT_QUOTES, 'UTF-8'); ?>
  </a>
</p>
