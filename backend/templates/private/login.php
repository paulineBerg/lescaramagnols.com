<?php
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === $key || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};

$privateLoginUrl = is_string($privateLoginUrl ?? null) ? (string) $privateLoginUrl : private_portal_url('login');
$privatePasswordForgotUrl = is_string($privatePasswordForgotUrl ?? null)
    ? (string) $privatePasswordForgotUrl
    : private_portal_url('password_forgot');
?>
<section>
  <p class="muted">
    <?php echo htmlspecialchars(
        $translate('TXT_PRIVATE_LOGIN_INTRO', 'Connectez-vous pour accéder à l’espace privé.'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>
  </p>

  <form method="post" action="<?php echo htmlspecialchars($privateLoginUrl, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" novalidate>
    <div>
      <label for="identifier">
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_IDENTIFIER_LABEL', 'Identifiant'), ENT_QUOTES, 'UTF-8'); ?>
      </label>
      <input
        id="identifier"
        name="identifier"
        type="email"
        required
        autocomplete="username"
        value="<?php echo htmlspecialchars((string) ($identifier ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
        autofocus
      />
    </div>

    <div>
      <label for="password">
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_LABEL', 'Mot de passe'), ENT_QUOTES, 'UTF-8'); ?>
      </label>
      <div class="private-password-field">
        <input
          id="password"
          name="password"
          type="password"
          required
          autocomplete="current-password"
        />
        <button
          class="private-password-toggle"
          type="button"
          data-private-password-toggle
          data-private-password-show="<?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>"
          data-private-password-hide="<?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_HIDE', 'Masquer'), ENT_QUOTES, 'UTF-8'); ?>"
          aria-controls="password"
          aria-pressed="false"
        >
          <?php echo htmlspecialchars($translate('TXT_PRIVATE_PASSWORD_SHOW', 'Afficher'), ENT_QUOTES, 'UTF-8'); ?>
        </button>
      </div>
    </div>

    <?php if (!empty($privateMfaEnabled)): ?>
    <div>
      <label for="mfa_code">
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_MFA_CODE_LABEL', 'Code 2FA'), ENT_QUOTES, 'UTF-8'); ?>
      </label>
      <input id="mfa_code" name="mfa_code" type="text" inputmode="numeric" autocomplete="one-time-code" />
    </div>
    <?php endif; ?>

    <?php if (!empty($persistentPrivateEnabled)): ?>
    <div>
      <label>
        <input type="checkbox" name="trust_private_device" value="1" />
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_LOGIN_TRUST_DEVICE', 'Faire confiance à cet appareil'), ENT_QUOTES, 'UTF-8'); ?>
      </label>
    </div>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />

    <div class="private-actions">
      <button type="submit">
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_LOGIN_SUBMIT', 'Se connecter'), ENT_QUOTES, 'UTF-8'); ?>
      </button>
      <a href="<?php echo htmlspecialchars($privatePasswordForgotUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_PRIVATE_FORGOT_PASSWORD_LINK', 'Mot de passe oublié'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
    </div>
  </form>
</section>
