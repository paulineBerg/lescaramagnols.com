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
$privateUserIdentifier = is_string($privateUserIdentifier ?? null) ? trim((string) $privateUserIdentifier) : '';
$privateNavigationModules = is_array($privateNavigationModules ?? null)
    ? $privateNavigationModules
    : (is_array($privateModules ?? null) ? $privateModules : []);
$privateNavigationModuleNames = [];
foreach ($privateNavigationModules as $moduleName) {
    if (!is_string($moduleName) || trim($moduleName) === '') {
        continue;
    }

    $privateNavigationModuleNames[] = trim($moduleName);
}
$privateHasModule = static fn (string $name): bool => in_array($name, $privateNavigationModuleNames, true);
$privateNormalizePath = static function (string $url): string {
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || trim($path) === '') {
        return '/';
    }

    return rtrim($path, '/') !== '' ? rtrim($path, '/') : '/';
};
$privateCurrentPathRaw = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$privateCurrentPath = is_string($privateCurrentPathRaw) && trim($privateCurrentPathRaw) !== ''
    ? (rtrim($privateCurrentPathRaw, '/') !== '' ? rtrim($privateCurrentPathRaw, '/') : '/')
    : '';
$privatePathIs = static function (string $url) use ($privateNormalizePath, $privateCurrentPath): bool {
    if ($privateCurrentPath === '') {
        return false;
    }

    return $privateCurrentPath === $privateNormalizePath($url);
};
$privatePathIsOneOf = static function (array $urls) use ($privateNormalizePath, $privateCurrentPath): bool {
    if ($privateCurrentPath === '') {
        return false;
    }

    foreach ($urls as $url) {
        if (is_string($url) && $privateCurrentPath === $privateNormalizePath($url)) {
            return true;
        }
    }

    return false;
};

$privateNavItems = [
    [
        'label' => $translate('TXT_PRIVATE_DASHBOARD_LINK', 'Tableau de bord'),
        'href' => $privateDashboardUrl,
        'icon' => '📊',
        'active' => $privatePathIs($privateDashboardUrl) || $privateCurrentPath === '',
    ],
];

if ($privateHasModule('Documents') || (bool) ($privateDocumentsEnabled ?? false)) {
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents'),
        'href' => $privateDashboardUrl . '#private-documents',
        'icon' => '🗂️',
        'active' => false,
    ];
}

if ($privateHasModule('Locations immobilières')) {
    $privateRentalPaths = [
        private_portal_url('rental_properties'),
        private_portal_url('rental_units'),
        private_portal_url('rental_property_members'),
        private_portal_url('rental_tenants'),
        private_portal_url('rental_leases'),
        private_portal_url('rental_payments'),
        private_portal_url('rental_expenses'),
        private_portal_url('rental_documents'),
        private_portal_url('rental_summary'),
    ];
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_NAV_RENTAL', 'Locations immobilières'),
        'href' => private_portal_url('rental_summary'),
        'icon' => '🏠',
        'active' => $privatePathIsOneOf($privateRentalPaths),
    ];
}

if ($privateHasModule('Aide impôts')) {
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_NAV_TAX', 'Aide impôts'),
        'href' => private_portal_url('tax_dashboard'),
        'icon' => '€',
        'active' => $privateCurrentPath !== ''
            && str_starts_with($privateCurrentPath, $privateNormalizePath(private_portal_url('tax_dashboard'))),
    ];
}
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
        --private-bg: #f6f9fc;
        --private-surface: rgba(255, 255, 255, 0.94);
        --private-primary: #1d6f8d;
        --private-primary-dark: #0d305e;
        --private-text: #13294b;
        --private-muted: rgba(19, 41, 75, 0.72);
        --private-danger: #a11a2a;
        --private-success: #0d6c30;
        --private-shadow: 0 18px 40px rgba(19, 41, 75, 0.14);
        --private-border: rgba(19, 41, 75, 0.08);
        --private-nav-width: 250px;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        background: var(--private-bg);
        color: var(--private-text);
        min-height: 100vh;
      }

      a {
        color: inherit;
      }

      .private-auth-page {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #123d6b, #1d6f8d);
      }

      .private-auth-shell {
        width: min(100%, 440px);
        margin: 1.5rem;
        background: var(--private-surface);
        border-radius: 18px;
        box-shadow: var(--private-shadow);
        padding: 2.4rem 2rem;
      }

      .private-auth-header {
        margin-bottom: 1.5rem;
      }

      .private-auth-header p {
        margin: 0 0 0.35rem;
        color: var(--private-primary);
        font-weight: 700;
      }

      .private-auth-header h1 {
        margin: 0;
        color: var(--private-primary-dark);
        font-size: clamp(1.5rem, 4vw, 2rem);
      }

      .private-app-shell {
        display: flex;
        min-height: 100vh;
        align-items: flex-start;
      }

      .private-nav {
        flex: 0 0 var(--private-nav-width);
        width: var(--private-nav-width);
        min-height: 100vh;
        max-height: 100vh;
        background: linear-gradient(180deg, var(--private-primary), #24a0b5);
        color: #fff;
        display: flex;
        flex-direction: column;
        gap: 2rem;
        overflow-y: auto;
        padding: 2.5rem 1.8rem;
        position: sticky;
        top: 0;
      }

      .private-nav-brand {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
      }

      .private-nav-brand strong {
        font-size: 1.35rem;
      }

      .private-nav-brand span {
        font-size: 0.85rem;
        opacity: 0.85;
      }

      .private-nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 0.4rem;
      }

      .private-nav-menu a {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        width: 100%;
        min-height: 2.85rem;
        padding: 0.85rem 1rem;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: inherit;
        text-decoration: none;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.12);
      }

      .private-nav-menu a.active,
      .private-nav-menu a:hover,
      .private-nav-menu a:focus-visible {
        background: rgba(255, 255, 255, 0.18);
        outline: none;
      }

      .private-nav-icon {
        width: 1.45rem;
        text-align: center;
      }

      .private-content {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 100vh;
      }

      .private-header {
        background: #fff;
        border-bottom: 1px solid var(--private-border);
        padding: 1.6rem clamp(1.5rem, 4vw, 3rem);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
      }

      .private-header h1 {
        margin: 0;
        color: var(--private-primary-dark);
        font-size: clamp(1.4rem, 3vw, 2rem);
      }

      .private-header-meta {
        margin: 0.35rem 0 0;
        color: var(--private-muted);
        font-size: 0.95rem;
      }

      .private-main {
        flex: 1;
        padding: clamp(1.5rem, 4vw, 3rem);
      }

      .notice {
        margin: 0 0 1rem;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        font-size: 0.92rem;
        line-height: 1.4;
        background: rgba(29, 111, 141, 0.1);
      }

      .notice-success {
        background: rgba(13, 108, 48, 0.12);
        color: var(--private-success);
      }

      .notice-error,
      .error {
        background: rgba(161, 26, 42, 0.12);
        color: var(--private-danger);
      }

      form {
        display: grid;
        gap: 0.85rem;
        margin: 0;
      }

      label {
        display: inline-block;
        margin-bottom: 0.3rem;
        color: #274b6d;
        font-size: 0.9rem;
        font-weight: 600;
      }

      input,
      select,
      textarea {
        width: 100%;
        border: 1px solid rgba(39, 75, 109, 0.25);
        border-radius: 10px;
        background: #fff;
        color: var(--private-text);
        font: inherit;
        padding: 0.75rem 0.9rem;
      }

      input:focus,
      select:focus,
      textarea:focus {
        outline: none;
        border-color: var(--private-primary);
        box-shadow: 0 0 0 3px rgba(29, 111, 141, 0.2);
      }

      button {
        border: 0;
        background: linear-gradient(135deg, var(--private-primary), #24a0b5);
        color: #fff;
        padding: 0.85rem 1.1rem;
        border-radius: 12px;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.2s ease;
      }

      button:hover {
        box-shadow: 0 12px 24px rgba(29, 111, 141, 0.3);
        transform: translateY(-1px);
      }

      .muted {
        color: var(--private-muted);
        font-size: 0.95rem;
      }

      .private-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.8rem;
        align-items: center;
        margin: 1.2rem 0 0;
      }

      .private-actions form {
        margin: 0;
      }

      .private-actions a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.9rem;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        background: rgba(19, 41, 75, 0.08);
        color: var(--private-primary-dark);
        text-decoration: none;
        font-weight: 600;
      }

      .private-logout-form {
        display: block;
      }

      .private-logout {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.9rem;
        background: var(--private-primary);
        color: #fff;
        padding: 0.75rem 1.1rem;
      }

      .private-logout:hover {
        box-shadow: 0 12px 24px rgba(29, 111, 141, 0.25);
      }

      .card {
        background: #fff;
        border: 1px solid rgba(19, 41, 75, 0.05);
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(19, 41, 75, 0.08);
        padding: 1.6rem;
      }

      .card h2 {
        margin: 0 0 1rem;
        color: var(--private-primary-dark);
        font-size: 1.1rem;
      }

      .card ul {
        margin: 0;
        padding-left: 1.1rem;
        display: grid;
        gap: 0.6rem;
      }

      .cards-grid {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      }

      .private-card-wide {
        grid-column: 1 / -1;
      }

      .private-dashboard > .private-header-meta {
        margin: 0 0 1.5rem;
      }

      .tag {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        border-radius: 999px;
        background: rgba(29, 111, 141, 0.12);
        color: var(--private-primary);
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.3rem 0.75rem;
        margin-bottom: 0.8rem;
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      th,
      td {
        border-bottom: 1px solid var(--private-border);
        padding: 0.85rem 0.7rem;
        text-align: left;
        vertical-align: top;
      }

      th {
        color: #274b6d;
        font-size: 0.9rem;
      }

      td form {
        display: block;
      }

      @media (max-width: 900px) {
        .private-app-shell {
          display: block;
        }

        .private-nav {
          position: static;
          width: 100%;
          min-height: auto;
          max-height: none;
          padding: 1.2rem;
        }

        .private-nav-menu {
          grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .private-header {
          align-items: flex-start;
          flex-direction: column;
        }

        .private-main {
          padding: 1.2rem;
        }
      }

      @media (max-width: 640px) {
        .private-auth-shell {
          margin: 1rem;
          padding: 1.5rem;
        }

        .private-nav-menu {
          grid-template-columns: 1fr;
        }

        .private-actions {
          align-items: stretch;
          flex-direction: column;
        }

        .private-actions a,
        .private-actions button {
          width: 100%;
        }

        table {
          display: block;
          overflow-x: auto;
          white-space: nowrap;
        }
      }
    </style>
  </head>
  <body class="<?php echo $isAuthenticated ? 'private-app-page' : 'private-auth-page'; ?>">
    <?php if ($isAuthenticated): ?>
      <div class="private-app-shell">
        <nav class="private-nav" aria-label="Navigation privée">
          <div class="private-nav-brand">
            <strong>Les Caramagnols</strong>
            <span><?php echo htmlspecialchars($translate('TXT_PRIVATE_LAYOUT_BRAND_SUBTITLE', 'Espace privé sécurisé'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <ul class="private-nav-menu">
            <?php foreach ($privateNavItems as $privateNavItem): ?>
              <?php
              $navLabel = is_string($privateNavItem['label'] ?? null) ? (string) $privateNavItem['label'] : '';
              $navHref = is_string($privateNavItem['href'] ?? null) ? (string) $privateNavItem['href'] : '#';
              $navIcon = is_string($privateNavItem['icon'] ?? null) ? (string) $privateNavItem['icon'] : '';
              $navIsActive = (bool) ($privateNavItem['active'] ?? false);
              if ($navLabel === '') {
                  continue;
              }
              ?>
              <li>
                <a class="<?php echo $navIsActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($navHref, ENT_QUOTES, 'UTF-8'); ?>">
                  <span class="private-nav-icon" aria-hidden="true"><?php echo htmlspecialchars($navIcon, ENT_QUOTES, 'UTF-8'); ?></span>
                  <span><?php echo htmlspecialchars($navLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </nav>

        <div class="private-content">
          <header class="private-header">
            <div>
              <h1><?php echo htmlspecialchars((string) ($privatePageTitle ?? 'Espace privé'), ENT_QUOTES, 'UTF-8'); ?></h1>
              <?php if ($privateUserIdentifier !== ''): ?>
                <p class="private-header-meta">
                  <?php echo htmlspecialchars($translate('TXT_PRIVATE_LAYOUT_CONNECTED_AS', 'Connecté en tant que'), ENT_QUOTES, 'UTF-8'); ?>
                  <strong><?php echo htmlspecialchars($privateUserIdentifier, ENT_QUOTES, 'UTF-8'); ?></strong>
                </p>
              <?php endif; ?>
            </div>
            <form class="private-logout-form" method="post" action="<?php echo htmlspecialchars($privateLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($privateLogoutCsrfToken, ENT_QUOTES, 'UTF-8'); ?>" />
              <button type="submit" class="private-logout">
                <?php echo htmlspecialchars($translate('TXT_PRIVATE_LOGOUT_LINK', 'Déconnexion'), ENT_QUOTES, 'UTF-8'); ?>
              </button>
            </form>
          </header>

          <main class="private-main">
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
    <?php else: ?>
      <main class="private-auth-shell">
        <header class="private-auth-header">
          <p>Les Caramagnols</p>
          <h1><?php echo htmlspecialchars((string) ($privatePageTitle ?? 'Espace privé'), ENT_QUOTES, 'UTF-8'); ?></h1>
        </header>

        <?php if ($noticeText !== null): ?>
          <div class="notice notice-success"><?php echo htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== null): ?>
          <div class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php echo $privateContent; ?>
      </main>
    <?php endif; ?>
  </body>
</html>
