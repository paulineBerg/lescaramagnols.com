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
$privateMemberSettingsUrl = is_string($privateMemberSettingsUrl ?? null)
    ? (string) $privateMemberSettingsUrl
    : private_portal_url('member_settings');
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

if ($privateHasModule('Documents') || (bool) ($privateDocumentsEnabled ?? false)) {
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_DASHBOARD_DOCUMENTS_TITLE', 'Documents'),
        'href' => private_portal_url('documents'),
        'icon' => '🗂️',
        'active' => $privatePathIs(private_portal_url('documents')),
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
        private_portal_url('rental_rents'),
        private_portal_url('rental_payments'),
        private_portal_url('rental_expenses'),
        private_portal_url('rental_regularizations'),
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

if ($privateMemberSettingsEnabled) {
    $privateNavItems[] = [
        'label' => $translate('TXT_PRIVATE_SETTINGS_NAV', 'Paramètres'),
        'href' => $privateMemberSettingsUrl,
        'icon' => '⚙',
        'active' => $privatePathIs($privateMemberSettingsUrl),
    ];
}

$privateDashboardNavLabel = is_string($privateNavItems[0]['label'] ?? null)
    ? (string) $privateNavItems[0]['label']
    : $translate('TXT_PRIVATE_DASHBOARD_LINK', 'Tableau de bord');
$privateActiveNavItem = null;
foreach ($privateNavItems as $privateNavItem) {
    if ((bool) ($privateNavItem['active'] ?? false)) {
        $privateActiveNavItem = $privateNavItem;
        break;
    }
}
if ($privateActiveNavItem === null && isset($privateNavItems[0])) {
    $privateActiveNavItem = $privateNavItems[0];
}
$privateActiveNavLabel = is_array($privateActiveNavItem) && is_string($privateActiveNavItem['label'] ?? null)
    ? (string) $privateActiveNavItem['label']
    : $privateDashboardNavLabel;
$privateActiveIsDashboard = $privateActiveNavLabel === $privateDashboardNavLabel;
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
        <button
          type="button"
          class="private-nav-toggle"
          data-private-nav-toggle
          aria-controls="private-side-nav"
          aria-expanded="true"
          aria-label="<?php echo htmlspecialchars($translate('TXT_PRIVATE_NAV_COLLAPSE', 'Replier le menu'), ENT_QUOTES, 'UTF-8'); ?>"
          title="<?php echo htmlspecialchars($translate('TXT_PRIVATE_NAV_COLLAPSE', 'Replier le menu'), ENT_QUOTES, 'UTF-8'); ?>"
        >
          <span aria-hidden="true">☰</span>
        </button>
        <nav id="private-side-nav" class="private-nav" aria-label="Navigation privée">
          <div class="private-nav-brand">
            <strong>Les Caramagnols</strong>
            <span><?php echo htmlspecialchars($translate('TXT_PRIVATE_LAYOUT_BRAND_SUBTITLE', 'Espace privé sécurisé'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <?php if ($privateActiveNavItem !== null) : ?>
            <details class="private-mobile-breadcrumb">
              <summary>
                <?php if ($privateActiveIsDashboard) : ?>
                  <strong><?php echo htmlspecialchars($privateDashboardNavLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php else : ?>
                  <span><?php echo htmlspecialchars($privateDashboardNavLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                  <span class="private-mobile-breadcrumb-separator" aria-hidden="true">&gt;</span>
                  <strong><?php echo htmlspecialchars($privateActiveNavLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                <?php endif; ?>
                <span class="private-mobile-breadcrumb-toggle" aria-hidden="true">☰</span>
              </summary>
              <ul>
                <?php foreach ($privateNavItems as $privateNavItem) : ?>
                    <?php
                    $mobileNavLabel = is_string($privateNavItem['label'] ?? null) ? (string) $privateNavItem['label'] : '';
                    $mobileNavHref = is_string($privateNavItem['href'] ?? null) ? (string) $privateNavItem['href'] : '#';
                    $mobileNavIcon = is_string($privateNavItem['icon'] ?? null) ? (string) $privateNavItem['icon'] : '';
                    $mobileNavIsActive = (bool) ($privateNavItem['active'] ?? false);
                    if ($mobileNavLabel === '') {
                        continue;
                    }
                    ?>
                  <li>
                    <a class="<?php echo $mobileNavIsActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($mobileNavHref, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $mobileNavIsActive ? ' aria-current="page"' : ''; ?>>
                      <span class="private-nav-icon" aria-hidden="true"><?php echo htmlspecialchars($mobileNavIcon, ENT_QUOTES, 'UTF-8'); ?></span>
                      <span><?php echo htmlspecialchars($mobileNavLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endif; ?>
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
          <div class="notice notice-success private-screen-notice" role="status"><?php echo htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error !== null) : ?>
          <div class="notice notice-error private-screen-notice" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php echo $privateContent; ?>
      </main>
    <?php endif; ?>
    <?php if ($isAuthenticated) : ?>
      <dialog class="private-dialog private-confirm-dialog" id="private-sensitive-action-dialog" aria-labelledby="private-sensitive-action-title" aria-describedby="private-sensitive-action-message">
        <div class="private-dialog-panel">
          <header class="private-dialog-header">
            <h3 id="private-sensitive-action-title"><?php echo htmlspecialchars($translate('TXT_PRIVATE_SENSITIVE_ACTION_TITLE', 'Confirmer l’action'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <button type="button" class="private-dialog-close" data-private-sensitive-action-cancel aria-label="<?php echo htmlspecialchars($translate('TXT_PRIVATE_SENSITIVE_ACTION_NO', 'Non'), ENT_QUOTES, 'UTF-8'); ?>">×</button>
          </header>
          <p id="private-sensitive-action-message" data-private-sensitive-action-message>
            <?php echo htmlspecialchars($translate('TXT_PRIVATE_SENSITIVE_ACTION_MESSAGE', 'Cette action peut supprimer ou archiver des données privées. Voulez-vous continuer ?'), ENT_QUOTES, 'UTF-8'); ?>
          </p>
          <div class="private-confirm-actions">
            <button type="button" class="private-document-button-secondary" data-private-sensitive-action-cancel><?php echo htmlspecialchars($translate('TXT_PRIVATE_SENSITIVE_ACTION_NO', 'Non'), ENT_QUOTES, 'UTF-8'); ?></button>
            <button type="button" class="private-button-danger" data-private-sensitive-action-confirm><?php echo htmlspecialchars($translate('TXT_PRIVATE_SENSITIVE_ACTION_YES', 'Oui'), ENT_QUOTES, 'UTF-8'); ?></button>
          </div>
        </div>
      </dialog>
    <?php endif; ?>
    <?php $privateCspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
    <script<?php echo $privateCspNonce !== '' ? ' nonce="' . htmlspecialchars($privateCspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
      (() => {
        const navToggle = document.querySelector('[data-private-nav-toggle]');
        const navCollapseLabel = <?php echo json_encode($translate('TXT_PRIVATE_NAV_COLLAPSE', 'Replier le menu'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const navExpandLabel = <?php echo json_encode($translate('TXT_PRIVATE_NAV_EXPAND', 'Déplier le menu'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const sessionPingUrl = <?php echo json_encode((string) ($privateSessionPingUrl ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const sessionCsrfToken = <?php echo json_encode((string) ($privateSessionCsrfToken ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const sessionLoginUrl = <?php echo json_encode((string) ($privateLoginUrl ?? private_portal_url('login')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const sessionKeepAliveSeconds = Math.max(60, Number(<?php echo json_encode((int) ($privateSessionKeepAliveSeconds ?? 300)); ?>) || 300);
        const sessionRecentActivitySeconds = Math.max(120, Math.min(900, Number(<?php echo json_encode((int) ($privateSessionTimeoutSeconds ?? 3600)); ?>) || 3600));
        const navStorageKey = 'caramagnols.private.navCollapsed';
        const initSessionKeepAlive = () => {
          if (sessionPingUrl === '' || sessionCsrfToken === '') {
            return;
          }

          const keepAliveMs = sessionKeepAliveSeconds * 1000;
          const recentActivityMs = sessionRecentActivitySeconds * 1000;
          let lastActivityAt = Date.now();
          let lastPingAt = 0;
          let pingInFlight = false;

          const markActivity = () => {
            lastActivityAt = Date.now();
          };
          const hasRecentActivity = () => Date.now() - lastActivityAt <= recentActivityMs;
          const pingSession = async (force = false) => {
            if (pingInFlight || document.hidden) {
              return;
            }

            const now = Date.now();
            if (!force && (!hasRecentActivity() || now - lastPingAt < keepAliveMs)) {
              return;
            }

            pingInFlight = true;
            lastPingAt = now;

            try {
              const body = new URLSearchParams();
              body.set('csrf_token', sessionCsrfToken);
              const response = await fetch(sessionPingUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                  'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: body.toString(),
              });

              if (response.status === 401 && sessionLoginUrl !== '') {
                window.location.assign(sessionLoginUrl);
              }
            } catch (_error) {
              // La prochaine activite relancera un ping; ne pas interrompre la saisie utilisateur.
            } finally {
              pingInFlight = false;
            }
          };

          ['click', 'keydown', 'input', 'change', 'scroll', 'focus'].forEach((eventName) => {
            window.addEventListener(eventName, markActivity, { passive: true });
          });
          document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
              markActivity();
              void pingSession(true);
            }
          });
          window.setInterval(() => {
            void pingSession(false);
          }, Math.min(60000, keepAliveMs));
        };
        initSessionKeepAlive();

        const readNavCollapsed = () => {
          try {
            return window.localStorage.getItem(navStorageKey) === '1';
          } catch (_error) {
            return false;
          }
        };
        const writeNavCollapsed = (isCollapsed) => {
          try {
            window.localStorage.setItem(navStorageKey, isCollapsed ? '1' : '0');
          } catch (_error) {
            return;
          }
        };
        const setNavCollapsed = (isCollapsed) => {
          document.body.classList.toggle('private-nav-collapsed', isCollapsed);
          if (navToggle instanceof HTMLButtonElement) {
            const label = isCollapsed ? navExpandLabel : navCollapseLabel;
            navToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            navToggle.setAttribute('aria-label', label);
            navToggle.setAttribute('title', label);
          }
        };
        if (navToggle instanceof HTMLButtonElement) {
          setNavCollapsed(readNavCollapsed());
          navToggle.addEventListener('click', () => {
            const isCollapsed = !document.body.classList.contains('private-nav-collapsed');
            setNavCollapsed(isCollapsed);
            writeNavCollapsed(isCollapsed);
          });
        }

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

        document.querySelectorAll('[data-private-dialog-auto-open="1"]').forEach((dialog) => {
          if (dialog instanceof HTMLDialogElement && !dialog.open) {
            openDialog(dialog);
          }
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

        const normalizeFilterValue = (value) => String(value || '')
          .toLowerCase()
          .normalize('NFD')
          .replace(/[\u0300-\u036f]/g, '')
          .trim();
        const datasetKey = (key) => `filter${String(key || '')
          .replace(/(^|-|_)([a-z])/g, (_match, _sep, letter) => letter.toUpperCase())}`;
        const rowFilterValue = (row, key) => {
          if (!(row instanceof HTMLElement)) {
            return '';
          }

          const dataKey = datasetKey(key);
          return normalizeFilterValue(row.dataset[dataKey] || row.dataset.filterText || row.textContent || '');
        };
        document.querySelectorAll('[data-private-filter-scope]').forEach((scope, scopeIndex) => {
          const rows = Array.from(scope.querySelectorAll('[data-private-filter-row]'));
          const fields = Array.from(scope.querySelectorAll('[data-private-filter]'));
          const emptyState = scope.querySelector('[data-private-filter-empty]');
          const storageKey = `caramagnols.private.filters.${window.location.pathname}.${scopeIndex}`;
          const fieldStorageKey = (field, fieldIndex) => `${field.getAttribute('data-private-filter') || 'text'}:${field.name || field.id || fieldIndex}`;
          const readPersistedFilters = () => {
            try {
              const value = sessionStorage.getItem(storageKey);
              const decoded = value ? JSON.parse(value) : {};
              return decoded && typeof decoded === 'object' ? decoded : {};
            } catch (_error) {
              return {};
            }
          };
          const writePersistedFilters = () => {
            try {
              const values = {};
              fields.forEach((field, fieldIndex) => {
                if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) {
                  values[fieldStorageKey(field, fieldIndex)] = field.value;
                }
              });
              sessionStorage.setItem(storageKey, JSON.stringify(values));
            } catch (_error) {
              // Le filtrage doit rester utilisable meme si sessionStorage est indisponible.
            }
          };
          const persistedFilters = readPersistedFilters();
          fields.forEach((field, fieldIndex) => {
            if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) {
              return;
            }

            const persistedValue = persistedFilters[fieldStorageKey(field, fieldIndex)];
            if (typeof persistedValue === 'string') {
              field.value = persistedValue;
            }
          });
          const applyFilters = () => {
            let visibleCount = 0;
            rows.forEach((row) => {
              if (!(row instanceof HTMLElement)) {
                return;
              }

              const isVisible = fields.every((field) => {
                if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) {
                  return true;
                }

                const key = field.getAttribute('data-private-filter') || 'text';
                const value = normalizeFilterValue(field.value);
                if (value === '' || value === 'all') {
                  return true;
                }

                const rowValue = rowFilterValue(row, key);
                if (field instanceof HTMLSelectElement && key !== 'text') {
                  return rowValue === value;
                }

                return rowValue.includes(value);
              });

              row.hidden = !isVisible;
              if (isVisible) {
                visibleCount += 1;
              }
            });

            if (emptyState instanceof HTMLElement) {
              emptyState.hidden = visibleCount !== 0;
            }
          };

          fields.forEach((field) => {
            field.addEventListener('input', () => {
              writePersistedFilters();
              applyFilters();
            });
            field.addEventListener('change', () => {
              writePersistedFilters();
              applyFilters();
            });
          });
          scope.querySelectorAll('[data-private-filter-reset]').forEach((button) => {
            button.addEventListener('click', () => {
              fields.forEach((field) => {
                if (field instanceof HTMLInputElement) {
                  field.value = '';
                } else if (field instanceof HTMLSelectElement) {
                  field.value = 'all';
                }
              });
              try {
                sessionStorage.removeItem(storageKey);
              } catch (_error) {
                // Rien a faire.
              }
              applyFilters();
            });
          });
          applyFilters();
        });

        document.addEventListener('change', (event) => {
          const field = event.target instanceof Element
            ? event.target.closest('[data-private-auto-submit]')
            : null;
          if (!(field instanceof HTMLInputElement) && !(field instanceof HTMLSelectElement)) {
            return;
          }

          const form = field.closest('form');
          if (!(form instanceof HTMLFormElement)) {
            return;
          }

          const submitValue = field.getAttribute('data-private-auto-submit') || '';
          if (submitValue === '') {
            return;
          }

          const submitters = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"], button:not([type])'));
          const submitter = submitters.find((candidate) => (
            candidate instanceof HTMLButtonElement || candidate instanceof HTMLInputElement
          ) && candidate.getAttribute('value') === submitValue);
          if (submitter instanceof HTMLElement && typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter);
            return;
          }

          let fallback = form.querySelector('input[type="hidden"][name="action"][data-private-auto-submit-fallback]');
          if (!(fallback instanceof HTMLInputElement)) {
            fallback = document.createElement('input');
            fallback.type = 'hidden';
            fallback.name = 'action';
            fallback.setAttribute('data-private-auto-submit-fallback', 'true');
            form.appendChild(fallback);
          }
          fallback.value = submitValue;
          form.submit();
        });

        const sensitiveDialog = document.getElementById('private-sensitive-action-dialog');
        const sensitiveConfirmTemplate = <?php echo json_encode($translate('TXT_PRIVATE_SENSITIVE_ACTION_CONFIRM_TEMPLATE', 'Confirmer : %s ? Cette action peut supprimer ou archiver des données privées.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const sensitiveMessage = sensitiveDialog instanceof HTMLDialogElement
          ? sensitiveDialog.querySelector('[data-private-sensitive-action-message]')
          : null;
        const sensitiveConfirmButton = sensitiveDialog instanceof HTMLDialogElement
          ? sensitiveDialog.querySelector('[data-private-sensitive-action-confirm]')
          : null;
        let pendingSensitiveSubmission = null;
        const allowedSensitiveSubmissions = new WeakSet();
        const sensitivePattern = /(supprimer|suppression|delete|remove|retirer|archiver|archivage|archive|purge)/i;
        const textOf = (value) => String(value || '').replace(/\s+/g, ' ').trim();
        const submitterLabel = (submitter) => {
          if (submitter instanceof HTMLInputElement) {
            return textOf(submitter.value || submitter.getAttribute('aria-label') || submitter.name);
          }

          if (submitter instanceof HTMLElement) {
            return textOf(submitter.getAttribute('aria-label') || submitter.textContent);
          }

          return '';
        };
        const formSignals = (form, submitter) => {
          const signals = [
            form.getAttribute('action') || '',
            form.dataset.privateSensitiveAction || '',
            form.dataset.sensitiveAction || '',
          ];

          if (submitter instanceof HTMLElement) {
            signals.push(
              submitter.getAttribute('name') || '',
              submitter.getAttribute('value') || '',
              submitter.getAttribute('class') || '',
              submitter.dataset.privateSensitiveAction || '',
              submitter.dataset.sensitiveAction || '',
              submitterLabel(submitter)
            );
          }

          form.querySelectorAll('input[type="hidden"]').forEach((input) => {
            if (input instanceof HTMLInputElement) {
              signals.push(input.name, input.value);
            }
          });

          return signals.join(' ');
        };
        const isSensitiveSubmission = (form, submitter) => {
          if (!(form instanceof HTMLFormElement)) {
            return false;
          }

          const method = (form.getAttribute('method') || 'get').toLowerCase();
          if (method !== 'post') {
            return false;
          }

          if (form.closest('#private-sensitive-action-dialog')) {
            return false;
          }

          return sensitivePattern.test(formSignals(form, submitter));
        };
        const confirmSensitiveSubmission = (form, submitter) => {
          if (!(sensitiveDialog instanceof HTMLDialogElement)) {
            return window.confirm(sensitiveConfirmTemplate.replace('%s', submitterLabel(submitter) || 'cette action'));
          }

          const label = submitterLabel(submitter) || 'cette action';
          if (sensitiveMessage instanceof HTMLElement) {
            sensitiveMessage.textContent = sensitiveConfirmTemplate.replace('%s', label);
          }
          pendingSensitiveSubmission = { form, submitter };
          openDialog(sensitiveDialog);
          if (sensitiveConfirmButton instanceof HTMLElement) {
            sensitiveConfirmButton.focus();
          }

          return false;
        };

        document.addEventListener('click', (event) => {
          const submitter = event.target instanceof Element
            ? event.target.closest('button[type="submit"], input[type="submit"], button:not([type])')
            : null;
          const form = submitter instanceof HTMLElement ? submitter.closest('form') : null;
          if (!isSensitiveSubmission(form, submitter)) {
            return;
          }

          event.preventDefault();
          event.stopImmediatePropagation();
          confirmSensitiveSubmission(form, submitter);
        }, true);

        document.addEventListener('submit', (event) => {
          const form = event.target;
          if (!(form instanceof HTMLFormElement)) {
            return;
          }

          if (allowedSensitiveSubmissions.has(form)) {
            allowedSensitiveSubmissions.delete(form);
            event.stopImmediatePropagation();
            return;
          }

          const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
          if (!isSensitiveSubmission(form, submitter)) {
            return;
          }

          event.preventDefault();
          event.stopImmediatePropagation();
          confirmSensitiveSubmission(form, submitter);
        }, true);

        document.querySelectorAll('[data-private-sensitive-action-cancel]').forEach((button) => {
          button.addEventListener('click', () => {
            pendingSensitiveSubmission = null;
            if (sensitiveDialog instanceof HTMLDialogElement) {
              sensitiveDialog.close();
            }
          });
        });

        if (sensitiveConfirmButton instanceof HTMLElement) {
          sensitiveConfirmButton.addEventListener('click', () => {
            const pending = pendingSensitiveSubmission;
            pendingSensitiveSubmission = null;
            if (sensitiveDialog instanceof HTMLDialogElement) {
              sensitiveDialog.close();
            }
            if (!pending || !(pending.form instanceof HTMLFormElement)) {
              return;
            }

            allowedSensitiveSubmissions.add(pending.form);
            if (typeof pending.form.requestSubmit === 'function' && pending.submitter instanceof HTMLElement) {
              pending.form.requestSubmit(pending.submitter);
              return;
            }

            if (typeof pending.form.requestSubmit === 'function') {
              pending.form.requestSubmit();
              return;
            }

            pending.form.submit();
          });
        }

        if (sensitiveDialog instanceof HTMLDialogElement) {
          sensitiveDialog.addEventListener('close', () => {
            pendingSensitiveSubmission = null;
          });
        }
      })();
    </script>
  </body>
</html>
