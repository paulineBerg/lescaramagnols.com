<?php
$translate = static function (string $key, string $fallback = ''): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};

$language = is_string($privatePortalLanguage ?? null) && trim($privatePortalLanguage) !== ''
    ? trim((string) $privatePortalLanguage)
    : (defined('CURRENT_LANG') ? (string) CURRENT_LANG : 'fr');

$isAuthenticated = (bool) ($privateIsAuthenticated ?? false);
$privateLoginUrl = is_string($privateLoginUrl ?? null) ? (string) $privateLoginUrl : private_portal_url('login');
$privateDashboardUrl = is_string($privateDashboardUrl ?? null) ? (string) $privateDashboardUrl : private_portal_url('dashboard');
$privateLogoutUrl = is_string($privateDashboardLogoutUrl ?? null) ? (string) $privateDashboardLogoutUrl : private_portal_url('logout');
$privatePasswordForgotUrl = is_string($privatePasswordForgotUrl ?? null) ? (string) $privatePasswordForgotUrl : private_portal_url('password_forgot');
$privateLogoutCsrfToken = is_string($privateLogoutCsrfToken ?? null) ? (string) $privateLogoutCsrfToken : '';
$errorKey = is_string($errorKey ?? null) ? (string) $errorKey : null;
$notice = is_string($notice ?? null) ? (string) $notice : null;
$error = $errorKey !== null ? $translate($errorKey, $errorKey) : (is_string($errorMessage ?? null) ? (string) $errorMessage : null);
$noticeText = $notice !== null ? $notice : null;
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars((string) ($privatePageTitle ?? 'Espace privé'), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
      :root {
        color-scheme: light;
        --private-bg: #eef2f7;
        --private-surface: #ffffff;
        --private-primary: #0c4a6e;
        --private-primary-soft: #e0f2ff;
        --private-text: #11263a;
        --private-muted: rgba(17, 38, 58, 0.72);
        --private-border: rgba(17, 38, 58, 0.12);
        --private-radius: 12px;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        background: radial-gradient(circle at 20% 10%, #f2f8ff, #eef2f7 45%, #dfe9f6);
        color: var(--private-text);
        min-height: 100vh;
      }

      .private-shell {
        max-width: 960px;
        margin: 0 auto;
        padding: 1.2rem;
      }

      .private-surface {
        margin-top: 1.5rem;
        background: var(--private-surface);
        border: 1px solid var(--private-border);
        border-radius: var(--private-radius);
        box-shadow: 0 18px 42px rgba(16, 40, 72, 0.14);
        overflow: hidden;
      }

      .private-header {
        background: linear-gradient(150deg, var(--private-primary), #0284c7);
        color: #fff;
        padding: 1rem 1.2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.8rem;
      }

      .private-header h1 {
        margin: 0;
        font-size: clamp(1rem, 2vw, 1.3rem);
      }

      .private-header a {
        color: #fff;
        text-decoration: none;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.35);
      }

      .private-header a:hover {
        background: rgba(255, 255, 255, 0.14);
      }

      .private-body {
        padding: 1.2rem;
      }

      .notice {
        border-radius: var(--private-radius);
        padding: 0.8rem 1rem;
        margin-bottom: 1rem;
        line-height: 1.35;
      }

      .notice-success {
        background: #e9f9ef;
        color: #0b5d38;
      }

      .notice-error {
        background: #fff2f2;
        color: #8d1b11;
      }

      form {
        display: grid;
        gap: 0.85rem;
        margin: 0;
      }

      label {
        display: inline-block;
        margin-bottom: 0.3rem;
        font-weight: 600;
      }

      input {
        width: 100%;
        padding: 0.65rem 0.7rem;
        border-radius: 8px;
        border: 1px solid var(--private-border);
        background: #fff;
        color: var(--private-text);
      }

      input:focus {
        outline: 2px solid var(--private-primary-soft);
        border-color: var(--private-primary);
      }

      button {
        border: 0;
        background: var(--private-primary);
        color: #fff;
        padding: 0.65rem 0.8rem;
        border-radius: 999px;
        font-weight: 600;
        cursor: pointer;
      }

      button:hover {
        background: #075985;
      }

      .inline-row {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }

      .muted {
        color: var(--private-muted);
        font-size: 0.95rem;
      }

      .private-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }

      .private-actions form {
        margin: 0;
      }

      .private-logout {
        border: 0;
        background: rgba(255, 255, 255, 0.16);
        color: #fff;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        border: 1px solid rgba(255, 255, 255, 0.35);
        cursor: pointer;
      }

      .private-logout:hover {
        background: rgba(255, 255, 255, 0.26);
      }

      .card {
        background: #f7fbff;
        border: 1px dashed #b9d5ef;
        border-radius: var(--private-radius);
        padding: 1rem;
      }
    </style>
  </head>
  <body>
    <div class="private-shell">
      <div class="private-surface">
        <header class="private-header">
          <h1><?php echo htmlspecialchars((string) ($privatePageTitle ?? 'Espace privé'), ENT_QUOTES, 'UTF-8'); ?></h1>
          <div class="inline-row">
            <?php if ($isAuthenticated): ?>
              <a href="<?php echo htmlspecialchars($privateDashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($translate('TXT_PRIVATE_DASHBOARD_LINK', 'Tableau de bord'), ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <form method="post" action="<?php echo htmlspecialchars($privateLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($privateLogoutCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="private-logout">
                <?php echo htmlspecialchars($translate('TXT_PRIVATE_LOGOUT_LINK', 'Déconnexion'), ENT_QUOTES, 'UTF-8'); ?>
                </button>
              </form>
            <?php else: ?>
              <a href="<?php echo htmlspecialchars($privateLoginUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($translate('TXT_PRIVATE_LOGIN_LINK', 'Connexion'), ENT_QUOTES, 'UTF-8'); ?>
              </a>
              <a href="<?php echo htmlspecialchars($privatePasswordForgotUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($translate('TXT_PRIVATE_FORGOT_PASSWORD_LINK', 'Mot de passe oublié'), ENT_QUOTES, 'UTF-8'); ?>
              </a>
            <?php endif; ?>
          </div>
        </header>

        <main class="private-body">
          <?php if ($noticeText !== null): ?>
            <div class="notice notice-success"><?php echo htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <?php if ($error !== null): ?>
            <div class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <?php echo $privateContent; ?>
        </main>
      </div>
    </div>
  </body>
</html>
