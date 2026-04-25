<h1 id="admin-login-title">Espace admin</h1>
<p class="intro">
  Connectez-vous pour accéder au tableau de bord du site Les Caramagnols.
</p>
<?php if (($notice ?? null) !== null): ?>
<div class="notice notice-success" role="status"><?php echo htmlspecialchars((string) $notice, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if (($error ?? null) !== null): ?>
<div class="notice notice-error" role="alert"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="post" action="<?php echo htmlspecialchars((string) ($adminLoginUrl ?? admin_url('login')), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" novalidate>
  <div class="field">
    <label for="identifier">E-mail admin</label>
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
    <label for="password">Mot de passe</label>
    <input id="password" name="password" type="password" required />
  </div>
  <?php endif; ?>
  <?php if (!empty($totpRequired)): ?>
  <div class="field">
    <label for="totp_code">Code 2FA (TOTP)</label>
    <input id="totp_code" name="totp_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" required />
  </div>
  <?php endif; ?>
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
  <div class="actions">
    <button class="button-block" type="submit">Se connecter</button>
  </div>
</form>
<p class="hint">
  Route canonique admin : <code><?php echo htmlspecialchars((string) ($loginPath ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
</p>
