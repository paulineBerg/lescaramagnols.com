<?php
// backend/public/site/adminFtyhik5642sZ/layout.php

require_once dirname(__DIR__, 3) . '/core/bootstrap.php';
require_once dirname(__DIR__, 3) . '/core/auth/admin.php';

/**
 * Rend une page de l'espace admin avec la navigation latérale standard.
 *
 * @param string   $pageTitle        Titre complet de la page.
 * @param callable $contentRenderer  Callback qui reçoit un tableau $context et affiche le contenu principal.
 * @param array    $options          Options supplémentaires (menu personnalisé, etc.).
 */
function admin_render_layout(string $pageTitle, callable $contentRenderer, array $options = []): void
{
    admin_require_login('index.php');

    $user = admin_current_user();
    $adminEmail = $user['email'] ?? app_config('admin.email', '');
    $loginAt = $user['login_at'] ?? time();
    $formattedLogin = date('d/m/Y H:i', (int) $loginAt);

    $menu = $options['menu'] ?? [
        ['id' => 'dashboard', 'label' => 'Tableau de bord', 'href' => 'dashboard.php', 'icon' => '📊'],
        ['id' => 'database', 'label' => 'Connexion MySQL', 'href' => 'database.php', 'icon' => '🗄️'],
        ['id' => 'menus', 'label' => 'Menus du site', 'href' => 'menus.php', 'icon' => '🧭'],
    ];

    $currentPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
    if ($currentPath === '') {
        $currentPath = basename($_SERVER['PHP_SELF']);
    }

    ob_start();
    $contentRenderer([
        'adminEmail' => $adminEmail,
        'formattedLogin' => $formattedLogin,
    ]);
    $content = ob_get_clean();

    ?>
    <!DOCTYPE html>
    <html lang="fr">
      <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
        <style>
          :root {
            color-scheme: light;
            --admin-bg: #f6f9fc;
            --admin-primary: #1d6f8d;
            --admin-primary-dark: #0d305e;
            --admin-text: #13294b;
            --admin-nav-width: 250px;
          }

          * {
            box-sizing: border-box;
          }

          body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: var(--admin-text);
            background: var(--admin-bg);
            min-height: 100vh;
            display: flex;
          }

          .admin-shell {
            display: flex;
            width: 100%;
          }

          nav.admin-nav {
            width: var(--admin-nav-width);
            background: linear-gradient(180deg, var(--admin-primary), #24a0b5);
            color: #fff;
            padding: 2.5rem 1.8rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
          }

          .nav-brand {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
          }

          .nav-brand strong {
            font-size: 1.35rem;
          }

          .nav-brand span {
            font-size: 0.85rem;
            opacity: 0.85;
          }

          .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 0.4rem;
          }

          .nav-menu a {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            color: inherit;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s ease, transform 0.15s ease;
            background: rgba(255, 255, 255, 0.08);
          }

          .nav-menu a.active {
            background: rgba(255, 255, 255, 0.22);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
            transform: translateX(4px);
          }

          .nav-menu a:hover:not(.active) {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(2px);
          }

          .admin-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
          }

          header.admin-header {
            background: #fff;
            border-bottom: 1px solid rgba(19, 41, 75, 0.1);
            padding: 1.6rem clamp(1.5rem, 4vw, 3rem);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
          }

          header.admin-header h1 {
            margin: 0;
            font-size: clamp(1.4rem, 3vw, 2rem);
            color: var(--admin-primary-dark);
          }

          header.admin-header .header-meta {
            font-size: 0.9rem;
            color: rgba(19, 41, 75, 0.7);
          }

          header.admin-header a.logout {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.1rem;
            border-radius: 10px;
            background: var(--admin-primary);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
          }

          header.admin-header a.logout:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(29, 111, 141, 0.25);
          }

          main.admin-main {
            padding: clamp(1.5rem, 4vw, 3rem);
            flex: 1;
          }

          .cards-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
          }

          .card {
            background: #fff;
            border-radius: 18px;
            padding: 1.6rem;
            box-shadow: 0 16px 40px rgba(19, 41, 75, 0.08);
            border: 1px solid rgba(19, 41, 75, 0.05);
          }

          .card h2 {
            margin: 0 0 1rem;
            font-size: 1.1rem;
            color: var(--admin-primary-dark);
          }

          .card ul {
            margin: 0;
            padding-left: 1.1rem;
            display: grid;
            gap: 0.6rem;
          }

          .tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 999px;
            background: rgba(29, 111, 141, 0.12);
            color: var(--admin-primary);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.3rem 0.75rem;
          }

          .notice-muted {
            margin-top: 1.6rem;
            font-size: 0.9rem;
            color: rgba(19, 41, 75, 0.7);
          }

          @media (max-width: 840px) {
            nav.admin-nav {
              width: 210px;
              padding: 2rem 1.4rem;
            }
          }

          @media (max-width: 720px) {
            body {
              flex-direction: column;
            }

            nav.admin-nav {
              width: 100%;
              flex-direction: row;
              align-items: center;
              justify-content: space-between;
            }

            .admin-content {
              min-height: auto;
            }

            .nav-menu {
              grid-auto-flow: column;
              overflow-x: auto;
            }

            .nav-menu a {
              white-space: nowrap;
            }
          }
        </style>
      </head>
      <body>
        <div class="admin-shell">
          <nav class="admin-nav" aria-label="Navigation administrateur">
            <div class="nav-brand">
              <strong>Les Caramagnols</strong>
              <span>Espace blog</span>
            </div>
            <ul class="nav-menu">
              <?php foreach ($menu as $item): ?>
              <?php
                $href = $item['href'] ?? '#';
                $label = $item['label'] ?? '';
                $icon = $item['icon'] ?? '';
                $isActive = ($currentPath === basename($href));
              ?>
              <li>
                <a
                  href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
                  class="<?php echo $isActive ? 'active' : ''; ?>"
                >
                  <?php if ($icon !== ''): ?><span aria-hidden="true"><?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                  <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
              </li>
              <?php endforeach; ?>
            </ul>
          </nav>
          <div class="admin-content">
            <header class="admin-header">
              <div>
                <h1><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="header-meta">
                  Connecté : <strong><?php echo htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8'); ?></strong> · Dernière connexion&nbsp;: <?php echo htmlspecialchars($formattedLogin, ENT_QUOTES, 'UTF-8'); ?>
                </div>
              </div>
              <a class="logout" href="logout.php">Déconnexion</a>
            </header>
            <main class="admin-main">
              <?php echo $content; ?>
            </main>
          </div>
        </div>
      </body>
    </html>
    <?php
}
