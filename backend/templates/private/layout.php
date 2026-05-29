<?php
$translate = static function (string $key, string $fallback = ''): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === $key || $translated === '[[' . $key . ']]') {
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
$privateMemberSettingsEnabled = (bool) ($privateMemberSettingsEnabled ?? false);
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

if ($privateMemberSettingsEnabled) {
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_SETTINGS_NAV', 'Paramètres'),
        'href' => private_portal_url('member_settings'),
        'icon' => '⚙',
        'active' => $privatePathIs(private_portal_url('member_settings')),
    ];
}

if ($privateHasModule('Documents') || (bool) ($privateDocumentsEnabled ?? false)) {
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents'),
        'href' => private_portal_url('documents'),
        'icon' => '🗂️',
        'active' => $privatePathIs(private_portal_url('documents')),
    ];
}

if ($privateHasModule('Bloc-note')) {
    $privateNavItems[] = [
        'label' => 'Bloc-note',
        'href' => private_portal_url('blocnote'),
        'icon' => '📝',
        'active' => $privatePathIs(private_portal_url('blocnote')),
    ];
}

if ($privateHasModule('Discussions')) {
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_NAV_DISCUSSIONS', 'Discussions'),
        'href' => private_portal_url('discussion_index'),
        'icon' => '✉',
        'active' => $privateCurrentPath !== ''
            && str_starts_with($privateCurrentPath, $privateNormalizePath(private_portal_url('discussion_index'))),
    ];
}

if ($privateHasModule('Locations immobilières')) {
    $privateRentalPaths = [
        private_portal_url('rental_dashboard'),
        private_portal_url('rental_properties'),
        private_portal_url('rental_units'),
        private_portal_url('rental_property_members'),
        private_portal_url('rental_tenants'),
        private_portal_url('rental_leases'),
        private_portal_url('rental_payments'),
        private_portal_url('rental_expenses'),
        private_portal_url('rental_documents'),
        private_portal_url('rental_agency_imports'),
        private_portal_url('rental_agency_review'),
        private_portal_url('rental_summary'),
    ];
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_NAV_RENTAL', 'Locations immobilières'),
        'href' => private_portal_url('rental_dashboard'),
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
    <meta name="robots" content="noindex,nofollow,noarchive" />
    <title><?php echo htmlspecialchars((string) ($privatePageTitle ?? 'Espace privé'), ENT_QUOTES, 'UTF-8'); ?></title>
    <?php foreach (vite_css('src/scss/private.scss') as $privateStylesheetUrl): ?>
      <link rel="stylesheet" href="<?php echo htmlspecialchars($privateStylesheetUrl, ENT_QUOTES, 'UTF-8'); ?>" />
    <?php endforeach; ?>
  </head>
  <body class="<?php echo $isAuthenticated ? 'private-app-page' : 'private-auth-page'; ?>">
    <?php if ($isAuthenticated) : ?>
      <div class="private-app-shell">
        <nav class="private-nav" aria-label="Navigation privée">
          <div class="private-nav-brand">
            <strong>Les Caramagnols</strong>
            <span><?php echo htmlspecialchars($translate('TXT_PRIVATE_LAYOUT_BRAND_SUBTITLE', 'Espace privé sécurisé'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <ul class="private-nav-menu">
            <?php foreach ($privateNavItems as $privateNavItem) : ?>
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
              <?php if ($privateUserIdentifier !== '') : ?>
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
            <?php if ($noticeText !== null) : ?>
              <div class="notice notice-success"><?php echo htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($error !== null) : ?>
              <div class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php echo $privateContent; ?>
          </main>
        </div>
      </div>
    <?php else : ?>
      <main class="private-auth-shell">
        <header class="private-auth-header">
          <p>Les Caramagnols</p>
          <h1><?php echo htmlspecialchars((string) ($privatePageTitle ?? 'Espace privé'), ENT_QUOTES, 'UTF-8'); ?></h1>
        </header>

        <?php if ($noticeText !== null) : ?>
          <div class="notice notice-success"><?php echo htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== null) : ?>
          <div class="notice notice-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php echo $privateContent; ?>
      </main>
    <?php endif; ?>
    <?php $privateCspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
    <script<?php echo $privateCspNonce !== '' ? ' nonce="' . htmlspecialchars($privateCspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
      (() => {
        document.addEventListener('click', (event) => {
          const button = event.target instanceof Element
            ? event.target.closest('[data-private-password-toggle]')
            : null;
          if (!(button instanceof HTMLButtonElement)) {
            return;
          }

          const inputId = button.getAttribute('aria-controls') || '';
          const input = document.getElementById(inputId);
          if (!(input instanceof HTMLInputElement)) {
            return;
          }

          event.preventDefault();
          const showLabel = button.dataset.privatePasswordShow || 'Afficher';
          const hideLabel = button.dataset.privatePasswordHide || 'Masquer';
          const isVisible = input.type === 'text';
          input.type = isVisible ? 'password' : 'text';
          button.textContent = isVisible ? showLabel : hideLabel;
          button.setAttribute('aria-pressed', isVisible ? 'false' : 'true');
        });

        const openDialog = (dialog) => {
          if (!dialog) {
            return;
          }

          if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            return;
          }

          dialog.setAttribute('open', 'open');
        };

        document.querySelectorAll('[data-private-dialog-open]').forEach((trigger) => {
          const dialog = document.getElementById(trigger.getAttribute('data-private-dialog-open') || '');
          trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            openDialog(dialog);
          });
          trigger.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
              return;
            }

            event.preventDefault();
            event.stopPropagation();
            openDialog(dialog);
          });
        });

        document.querySelectorAll('[data-private-dialog-close]').forEach((button) => {
          button.addEventListener('click', () => {
            button.closest('dialog')?.close();
          });
        });

        document.querySelectorAll('.private-dialog').forEach((dialog) => {
          dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
              dialog.close();
            }
          });
        });
      })();
    </script>
  </body>
</html>
