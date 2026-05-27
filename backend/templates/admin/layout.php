<?php
$isLoginPage = ($contentTemplate ?? '') === 'login.php';
$adminInterfaceLanguage = is_string($adminInterfaceLanguage ?? null) && trim((string) $adminInterfaceLanguage) !== ''
    ? strtolower(trim((string) $adminInterfaceLanguage))
    : (function_exists('admin_interface_language') ? admin_interface_language() : 'fr');
$translate = static function (string $key, string $fallback = ''): string {
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
$adminMenu = [
    ['id' => 'dashboard', 'label' => $translate('TXT_ADMIN_NAV_DASHBOARD', 'Tableau de bord'), 'href' => $adminDashboardUrl ?? admin_url('dashboard'), 'icon' => '📊'],
    ['id' => 'pages', 'label' => $translate('TXT_ADMIN_NAV_PAGES', 'Pages'), 'href' => $adminPagesUrl ?? admin_url('pages'), 'icon' => '📝'],
    ['id' => 'articles', 'label' => $translate('TXT_ADMIN_NAV_ARTICLES', 'Articles'), 'href' => $adminArticlesUrl ?? admin_url('articles'), 'icon' => '📰'],
    ['id' => 'discussions', 'label' => $translate('TXT_ADMIN_NAV_DISCUSSIONS', 'Discussions'), 'href' => $adminDiscussionsUrl ?? admin_url('discussions'), 'icon' => '💬'],
    ['id' => 'media', 'label' => $translate('TXT_ADMIN_NAV_MEDIA', 'Médias'), 'href' => $adminMediaUrl ?? admin_url('media'), 'icon' => '🎞️'],
    ['id' => 'tiles', 'label' => $translate('TXT_ADMIN_NAV_TILES', 'Tuiles'), 'href' => $adminTilesUrl ?? admin_url('tiles'), 'icon' => '🧩'],
    ['id' => 'menus', 'label' => $translate('TXT_ADMIN_NAV_MENUS', 'Menus du site'), 'href' => $adminMenusUrl ?? admin_url('menus'), 'icon' => '🧭'],
    ['id' => 'logs', 'label' => $translate('TXT_ADMIN_NAV_LOGS', 'Logs'), 'href' => $adminLogsUrl ?? admin_url('logs'), 'icon' => '🧾'],
    [
        'id' => 'private_members',
        'label' => $translate('TXT_ADMIN_NAV_PRIVATE_MEMBERS', 'Espace privé'),
        'href' => is_string($adminPrivateMembersUrl ?? null) ? $adminPrivateMembersUrl : '#',
        'icon' => '🔒',
    ],
    ['id' => 'settings', 'label' => $translate('TXT_ADMIN_NAV_SETTINGS', 'Paramètres'), 'href' => $adminSettingsUrl ?? admin_url('settings'), 'icon' => '⚙️'],
];
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($adminInterfaceLanguage, ENT_QUOTES, 'UTF-8'); ?>">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars((string) ($pageTitle ?? $translate('TXT_ADMIN_LAYOUT_DEFAULT_TITLE', 'Administration')), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
      :root {
        color-scheme: light;
        --admin-bg: #f6f9fc;
        --admin-surface: rgba(255, 255, 255, 0.94);
        --admin-primary: #1d6f8d;
        --admin-primary-dark: #0d305e;
        --admin-text: #13294b;
        --admin-muted: rgba(19, 41, 75, 0.72);
        --admin-danger: #a11a2a;
        --admin-success: #0d6c30;
        --admin-shadow: 0 18px 40px rgba(19, 41, 75, 0.14);
        --admin-border: rgba(19, 41, 75, 0.08);
        --admin-nav-width: 250px;
      }

      * {
        box-sizing: border-box;
      }

      body {
        margin: 0;
        min-height: 100vh;
        font-family: "Segoe UI", Tahoma, Arial, sans-serif;
        color: var(--admin-text);
        background: var(--admin-bg);
      }

      a {
        color: inherit;
      }

      .admin-login-page {
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #123d6b, #1d6f8d);
      }

      .admin-login-shell {
        width: min(100%, 420px);
        margin: 1.5rem;
        background: var(--admin-surface);
        border-radius: 18px;
        box-shadow: var(--admin-shadow);
        padding: 2.5rem 2rem;
      }

      .admin-shell {
        display: flex;
        min-height: 100vh;
        align-items: flex-start;
      }

      nav.admin-nav {
        flex: 0 0 var(--admin-nav-width);
        width: var(--admin-nav-width);
        min-height: 100vh;
        max-height: 100vh;
        background: linear-gradient(180deg, var(--admin-primary), #24a0b5);
        color: #fff;
        padding: 2.5rem 1.8rem;
        display: flex;
        flex-direction: column;
        gap: 2rem;
        position: sticky;
        top: 0;
        overflow-y: auto;
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
        width: 100%;
        min-height: 2.85rem;
        padding: 0.85rem 1rem;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.16);
        color: inherit;
        text-decoration: none;
        font-weight: 600;
        transition: box-shadow 0.2s ease;
        background: rgba(255, 255, 255, 0.12);
      }

      .nav-menu a.active {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.16);
        box-shadow: none;
      }

      .nav-menu a:hover,
      .nav-menu a:focus-visible {
        background: rgba(255, 255, 255, 0.12);
        border-color: rgba(255, 255, 255, 0.16);
        box-shadow: none;
        outline: none;
      }

      .admin-content {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 100vh;
      }

      header.admin-header {
        background: #fff;
        border-bottom: 1px solid var(--admin-border);
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

      .header-meta {
        font-size: 0.9rem;
        color: var(--admin-muted);
      }

      a.logout {
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

      a.logout:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(29, 111, 141, 0.25);
      }

      main.admin-main {
        padding: clamp(1.5rem, 4vw, 3rem);
        flex: 1;
      }

      main.admin-main-wide {
        padding: clamp(0.8rem, 1.4vw, 1.35rem);
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

      .dashboard-kpis {
        margin-bottom: 1.5rem;
      }

      .dashboard-kpi-card {
        display: grid;
        gap: 0.35rem;
        align-content: start;
      }

      .dashboard-kpi-value {
        font-size: clamp(2rem, 4vw, 2.9rem);
        line-height: 1;
        color: var(--admin-primary-dark);
      }

      .dashboard-kpi-label {
        margin: 0;
        font-weight: 700;
        color: var(--admin-primary);
      }

      .dashboard-kpi-detail {
        margin: 0;
        color: var(--admin-muted);
      }

      .dashboard-card-actions {
        margin-top: 1.2rem;
      }

      .card ul {
        margin: 0;
        padding-left: 1.1rem;
        display: grid;
        gap: 0.6rem;
      }

      [hidden] {
        display: none !important;
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
        color: var(--admin-muted);
      }

      .field {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        margin-bottom: 1.4rem;
      }

      label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #274b6d;
      }

      input[type="email"],
      input[type="text"],
      input[type="number"],
      input[type="password"],
      select,
      textarea {
        border: 1px solid rgba(39, 75, 109, 0.25);
        border-radius: 10px;
        padding: 0.75rem 0.9rem;
        font-size: 1rem;
        font-family: inherit;
        transition: border 0.2s ease, box-shadow 0.2s ease;
      }

      input[type="email"]:focus,
      input[type="text"]:focus,
      input[type="number"]:focus,
      input[type="password"]:focus,
      select:focus,
      textarea:focus {
        outline: none;
        border-color: var(--admin-primary);
        box-shadow: 0 0 0 3px rgba(29, 111, 141, 0.2);
      }

      .actions {
        margin-top: 2rem;
      }

      button {
        background: linear-gradient(135deg, var(--admin-primary), #24a0b5);
        color: #fff;
        border: none;
        border-radius: 12px;
        padding: 0.85rem 1.1rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.2s ease;
      }

      button:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(29, 111, 141, 0.3);
      }

      button[disabled] {
        cursor: not-allowed;
        opacity: 0.55;
        transform: none;
        box-shadow: none;
      }

      .button-muted,
      .button-danger {
        background: rgba(19, 41, 75, 0.08);
        color: var(--admin-primary-dark);
      }

      .button-danger {
        background: rgba(161, 26, 42, 0.12);
        color: var(--admin-danger);
      }

      .button-block {
        width: 100%;
      }

      .button-small {
        min-height: auto;
        padding: 0.58rem 0.8rem;
        font-size: 0.92rem;
        border-radius: 10px;
      }

      .notice {
        margin: 0 0 1rem;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        font-size: 0.92rem;
      }

      .notice-error {
        color: var(--admin-danger);
        background: rgba(161, 26, 42, 0.12);
      }

      .notice-success {
        color: var(--admin-success);
        background: rgba(13, 108, 48, 0.12);
      }

      .button-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 2.9rem;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--admin-primary), #24a0b5);
        color: #fff;
        text-decoration: none;
        font-weight: 600;
      }

      .button-link-muted {
        background: rgba(19, 41, 75, 0.08);
        color: var(--admin-primary-dark);
      }

      .danger-confirmation {
        margin-top: 1rem;
      }

      .danger-confirmation summary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.9rem;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        background: rgba(161, 26, 42, 0.12);
        color: var(--admin-danger);
        font-weight: 700;
        cursor: pointer;
        list-style: none;
        user-select: none;
      }

      .danger-confirmation summary::-webkit-details-marker {
        display: none;
      }

      .danger-confirmation[open] summary {
        margin-bottom: 1rem;
      }

      .danger-confirmation__body {
        border: 1px solid rgba(161, 26, 42, 0.16);
        border-radius: 14px;
        padding: 1rem 1.1rem;
        background: rgba(161, 26, 42, 0.04);
      }

      .danger-confirmation__question {
        margin: 0 0 0.75rem;
        font-size: 1rem;
        font-weight: 700;
        color: var(--admin-danger);
      }

      .danger-confirmation__body .notice {
        margin-bottom: 1rem;
      }

      .actions-inline {
        display: flex;
        flex-wrap: wrap;
        gap: 0.8rem;
        align-items: center;
      }

      .actions-inline-end {
        justify-content: flex-end;
        margin-top: 1.5rem;
      }

      .checkbox-field {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        font-weight: 600;
        color: #274b6d;
      }

      .checkbox-field input {
        width: 1rem;
        height: 1rem;
      }

      .menu-builder-form {
        display: grid;
        gap: 1.5rem;
      }

      .menu-builder-meta-grid,
      .menu-preview-grid {
        display: grid;
        gap: 1.2rem;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      }

      .menu-builder-grid {
        display: grid;
        gap: 1.2rem;
        grid-template-columns: 1fr;
        align-items: start;
      }

      .menu-builder-card {
        padding: 1.4rem;
      }

      .menu-builder-card h3,
      .menu-preview-side-card strong {
        margin: 0;
        color: var(--admin-primary-dark);
      }

      .menu-system-card {
        display: grid;
        gap: 1rem;
      }

      .menu-system-card__summary {
        display: grid;
        gap: 0.65rem;
      }

      .menu-system-card__row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        color: var(--admin-muted);
      }

      .menu-system-card__row strong {
        color: var(--admin-primary-dark);
      }

      .menu-system-card__value {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        min-height: 2rem;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        background: rgba(19, 41, 75, 0.05);
        color: var(--admin-text);
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .menu-system-card__cta {
        margin-top: auto;
      }

      .menu-system-dialog {
        width: min(760px, calc(100vw - 2rem));
      }

      .menu-builder-card__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .menu-builder-card__header h3 {
        margin-bottom: 0.3rem;
      }

      .menu-builder-card__header p {
        margin: 0;
        color: var(--admin-muted);
      }

      .menu-builder-toolbar,
      .menu-builder-tabs,
      .menu-item-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
      }

      .menu-builder-tabs {
        align-items: stretch;
      }

      .menu-builder-tab {
        flex: 1 1 180px;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        padding: 0.95rem 1rem;
        border-radius: 16px;
        background: rgba(19, 41, 75, 0.06);
        color: var(--admin-primary-dark);
        box-shadow: none;
      }

      .menu-builder-tab small {
        font-size: 0.8rem;
        color: var(--admin-muted);
        text-align: left;
      }

      .menu-builder-tab-active {
        background: linear-gradient(135deg, var(--admin-primary), #24a0b5);
        color: #fff;
      }

      .menu-builder-tab-active small {
        color: rgba(255, 255, 255, 0.82);
      }

      .menu-structure-empty {
        padding: 1rem;
        border: 1px dashed rgba(19, 41, 75, 0.18);
        border-radius: 14px;
        background: rgba(19, 41, 75, 0.03);
        color: var(--admin-muted);
      }

      .menu-structure-list {
        display: grid;
        gap: 0.75rem;
      }

      .menu-item-card {
        margin-left: calc(var(--menu-depth, 0) * 1rem);
        padding: 1rem;
        border: 1px solid rgba(19, 41, 75, 0.08);
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.92);
        box-shadow: 0 10px 24px rgba(19, 41, 75, 0.05);
      }

      .menu-item-card-kind-group {
        border-color: rgba(192, 136, 35, 0.22);
        background: linear-gradient(180deg, rgba(255, 247, 230, 0.98), rgba(251, 239, 209, 0.92));
      }

      .menu-item-card-kind-group .menu-item-card__eyebrow {
        color: #9c5a00;
      }

      .menu-item-card-kind-group .menu-item-card__flag {
        background: rgba(192, 136, 35, 0.14);
        color: #8b5200;
      }

      .menu-item-card-selected {
        border-color: rgba(29, 111, 141, 0.42);
        box-shadow: 0 0 0 4px rgba(29, 111, 141, 0.12);
      }

      .menu-item-card__header,
      .menu-item-card__meta {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.8rem;
      }

      .menu-item-card__header {
        margin-bottom: 0.65rem;
      }

      .menu-item-card__header h3 {
        margin-top: 0.15rem;
        font-size: 1.02rem;
      }

      .menu-item-card__eyebrow {
        font-size: 0.76rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--admin-primary);
        font-weight: 700;
      }

      .menu-item-card__meta {
        flex-wrap: wrap;
        margin-bottom: 0.85rem;
        color: var(--admin-muted);
        font-size: 0.88rem;
      }

      .menu-item-card__target code,
      .menu-preview-side-card code {
        font-size: 0.78rem;
      }

      .menu-item-card__flag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.15rem 0.5rem;
        border-radius: 999px;
        background: rgba(29, 111, 141, 0.12);
        color: var(--admin-primary);
        font-size: 0.74rem;
        font-weight: 700;
      }

      .menu-item-card__presentation {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin: -0.15rem 0 0.85rem;
      }

      .menu-item-card__flag-template {
        font-size: 0.72rem;
      }

      .menu-item-card__flag-template-standard {
        background: rgba(19, 41, 75, 0.12);
        color: #173354;
      }

      .menu-item-card__flag-template-editorial {
        background: rgba(29, 111, 141, 0.18);
        color: #114f66;
      }

      .menu-item-card__flag-template-brands {
        background: rgba(192, 136, 35, 0.18);
        color: #8b5200;
      }

      .menu-item-card__flag-info {
        background: rgba(19, 41, 75, 0.08);
        color: var(--admin-muted);
        font-weight: 600;
      }

      .menu-item-children {
        display: grid;
        gap: 0.75rem;
      }

      .menu-editor-targets {
        display: grid;
        gap: 0.9rem;
      }

      .menu-editor-target {
        padding: 0.95rem 1rem;
        border: 1px solid rgba(19, 41, 75, 0.08);
        border-radius: 14px;
        background: rgba(19, 41, 75, 0.03);
      }

      .menu-editor-target-active {
        border-color: rgba(29, 111, 141, 0.28);
        background: rgba(29, 111, 141, 0.05);
      }

      .menu-editor-target h4 {
        margin: 0 0 0.75rem;
        color: var(--admin-primary-dark);
      }

      .menu-editor-presentation-hint {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin: 0 0 0.85rem;
      }

      .expert-mode summary {
        cursor: pointer;
        font-weight: 700;
        color: var(--admin-primary-dark);
      }

      .expert-mode summary + * {
        margin-top: 1rem;
      }

      .menu-preview-header,
      .menu-preview-mobile {
        display: grid;
        gap: 0.8rem;
      }

      .menu-preview-banner,
      .menu-preview-mobile__top {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-height: 3rem;
        padding: 0.85rem 1rem;
        border-radius: 14px;
        background: linear-gradient(135deg, #123d6b, #1d6f8d);
        color: #fff;
        font-weight: 600;
      }

      .menu-preview-utility {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      .menu-preview-chip {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: rgba(19, 41, 75, 0.08);
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--admin-primary-dark);
      }

      .menu-preview-list,
      .menu-preview-sublist {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 0.5rem;
      }

      .menu-preview-list > li,
      .menu-preview-sublist > li {
        padding: 0.7rem 0.85rem;
        border-radius: 12px;
        background: rgba(19, 41, 75, 0.05);
      }

      .menu-preview-sublist {
        margin-top: 0.5rem;
        padding-left: 0.8rem;
      }

      .menu-preview-empty {
        padding: 0.85rem;
        border-radius: 12px;
        background: rgba(19, 41, 75, 0.04);
        color: var(--admin-muted);
      }

      .menu-preview-hamburger {
        font-size: 1.2rem;
      }

      .menu-preview-sides {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }

      .menu-preview-sides section {
        display: grid;
        gap: 0.75rem;
      }

      .menu-preview-sides h4 {
        margin: 0;
        color: var(--admin-primary-dark);
      }

      .menu-preview-side-card {
        display: grid;
        gap: 0.45rem;
        padding: 0.95rem;
        border-radius: 14px;
        background: rgba(19, 41, 75, 0.04);
        border: 1px solid rgba(19, 41, 75, 0.08);
      }

      .menu-preview-side-card__image {
        padding: 0.45rem 0.6rem;
        border-radius: 10px;
        background: rgba(19, 41, 75, 0.08);
        font-size: 0.78rem;
        color: var(--admin-muted);
      }

      .menu-preview-side-card p {
        margin: 0;
        color: var(--admin-muted);
      }

      .admin-form-grid {
        display: grid;
        gap: 1rem;
      }

      .admin-pages-filters-card {
        padding: 1.35rem 1.5rem;
      }

      .admin-pages-filters-card h2 {
        margin-bottom: 0.95rem;
      }

      .admin-pages-filters-grid {
        display: grid;
        grid-template-columns: minmax(18rem, 2.2fr) repeat(3, minmax(9.5rem, 1fr)) auto;
        gap: 0.85rem 1rem;
        align-items: end;
      }

      .admin-pages-filters-grid .field {
        gap: 0.28rem;
        margin-bottom: 0;
      }

      .admin-pages-filters-grid label {
        font-size: 0.84rem;
      }

      .admin-pages-filters-grid input[type="text"],
      .admin-pages-filters-grid select {
        min-height: 2.65rem;
        padding: 0.62rem 0.8rem;
        font-size: 0.95rem;
      }

      .admin-pages-filters-search {
        min-width: 0;
      }

      .admin-pages-filters-actions {
        justify-content: flex-end;
        align-self: end;
        padding-bottom: 0.05rem;
        white-space: nowrap;
      }

      .admin-pages-filters-actions .button-link,
      .admin-pages-filters-actions button {
        width: 11.25rem;
        min-height: 2.65rem;
        padding: 0.62rem 0.95rem;
      }

      .admin-pages-row-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 0.65rem;
      }

      .admin-pages-row-action-form {
        margin: 0;
      }

      .admin-pages-row-action,
      .admin-pages-row-action-form {
        width: 10.2rem;
      }

      .admin-pages-row-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.9rem;
        padding: 0.75rem 1rem;
      }

      .admin-pages-row-action-form .admin-pages-row-action {
        width: 100%;
      }

      .admin-logs-filters-card {
        padding: 1.35rem 1.5rem;
      }

      .admin-logs-filters-card h2 {
        margin-bottom: 0.95rem;
      }

      .admin-logs-filters-grid {
        display: grid;
        grid-template-columns: minmax(18rem, 2.4fr) repeat(2, minmax(10rem, 1fr)) repeat(2, minmax(9rem, 0.9fr));
        gap: 0.85rem 1rem;
        align-items: end;
      }

      .admin-logs-filters-grid .field {
        gap: 0.28rem;
        margin-bottom: 0;
      }

      .admin-logs-filters-grid label {
        font-size: 0.84rem;
      }

      .admin-logs-filters-grid input[type="text"],
      .admin-logs-filters-grid input[type="date"],
      .admin-logs-filters-grid select {
        min-height: 2.65rem;
        padding: 0.62rem 0.8rem;
        font-size: 0.95rem;
      }

      .admin-logs-filters-search {
        min-width: 0;
      }

      .admin-logs-filters-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
        align-self: end;
        padding-bottom: 0.05rem;
        min-width: 0;
        max-width: 100%;
      }

      .admin-logs-filters-actions .button-link,
      .admin-logs-filters-actions button {
        flex: 0 1 11.25rem;
        min-width: min(11.25rem, 100%);
        min-height: 2.65rem;
        padding: 0.62rem 0.95rem;
        white-space: normal;
      }

      .admin-articles-filters-card {
        padding: 1.35rem 1.5rem;
      }

      .admin-articles-filters-card h2 {
        margin-bottom: 0.95rem;
      }

      .admin-articles-filters-grid {
        display: grid;
        grid-template-columns: minmax(16rem, 2fr) repeat(4, minmax(9rem, 1fr)) auto;
        gap: 0.85rem 1rem;
        align-items: end;
      }

      .admin-articles-filters-grid .field {
        gap: 0.28rem;
        margin-bottom: 0;
      }

      .admin-articles-filters-grid label {
        font-size: 0.84rem;
      }

      .admin-articles-filters-grid input[type="text"],
      .admin-articles-filters-grid input[type="date"],
      .admin-articles-filters-grid select {
        min-height: 2.65rem;
        padding: 0.62rem 0.8rem;
        font-size: 0.95rem;
      }

      .admin-articles-filters-actions {
        justify-content: flex-end;
        align-self: end;
        padding-bottom: 0.05rem;
        white-space: nowrap;
      }

      .admin-articles-filters-actions .button-link,
      .admin-articles-filters-actions button {
        min-height: 2.65rem;
        padding: 0.62rem 0.95rem;
      }

      .taxonomy-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
      }

      .taxonomy-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 1.9rem;
        padding: 0.3rem 0.7rem;
        border-radius: 999px;
        background: rgba(19, 41, 75, 0.06);
        color: var(--admin-primary-dark);
        font-size: 0.82rem;
        font-weight: 700;
      }

      .admin-form-help {
        margin: 0.2rem 0 0;
        font-size: 0.84rem;
        color: var(--admin-muted);
      }

      .textarea-large {
        min-height: 16rem;
      }

      .log-context-details {
        max-width: 28rem;
        margin-top: 0.75rem;
      }

      .log-context-details summary {
        cursor: pointer;
        color: var(--admin-primary-dark);
        font-weight: 600;
      }

      .log-context {
        margin: 0.75rem 0 0;
        padding: 0.85rem;
        border-radius: 12px;
        background: rgba(19, 41, 75, 0.05);
        border: 1px solid rgba(19, 41, 75, 0.08);
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.85rem;
        line-height: 1.45;
      }

      .log-event-cell {
        display: grid;
        gap: 0.35rem;
      }

      .log-level-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 5.5rem;
        padding: 0.32rem 0.7rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
      }

      .log-level-pill--debug {
        background: rgba(19, 41, 75, 0.1);
        color: var(--admin-primary-dark);
      }

      .log-level-pill--info {
        background: rgba(29, 111, 141, 0.12);
        color: var(--admin-primary);
      }

      .log-level-pill--warning {
        background: rgba(212, 138, 0, 0.16);
        color: #8c5600;
      }

      .log-level-pill--error {
        background: rgba(161, 26, 42, 0.14);
        color: var(--admin-danger);
      }

      .log-detail-list {
        display: grid;
        gap: 0.5rem;
      }

      .log-detail-item {
        display: grid;
        grid-template-columns: minmax(6.5rem, auto) 1fr;
        gap: 0.35rem 0.8rem;
        padding: 0.55rem 0.7rem;
        border-radius: 12px;
        background: rgba(19, 41, 75, 0.04);
        border: 1px solid rgba(19, 41, 75, 0.06);
      }

      .log-detail-item__label {
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--admin-primary);
      }

      .log-detail-item__value {
        color: var(--admin-primary-dark);
        word-break: break-word;
      }

      .log-selection-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.9rem;
        margin-bottom: 1rem;
      }

      .log-selection-toolbar__meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.9rem;
        color: var(--admin-muted);
      }

      .log-selection-toolbar__count {
        font-size: 0.92rem;
        font-weight: 700;
        color: var(--admin-primary-dark);
      }

      .admin-table-checkbox-cell {
        width: 3.25rem;
        text-align: center;
      }

      .admin-table-checkbox-cell input[type="checkbox"] {
        width: 1.05rem;
        height: 1.05rem;
        margin: 0;
      }

      .admin-table tr.is-selected {
        background: rgba(29, 111, 141, 0.05);
      }

      .menu-editor-dialog {
        width: min(980px, calc(100vw - 2rem));
      }

      .menu-editor-dialog__body {
        overflow: auto;
        padding-right: 0.25rem;
      }

      .settings-sections-grid {
        align-items: stretch;
      }

      .settings-intro-card {
        grid-column: 1 / -1;
      }

      .settings-section-card {
        width: 100%;
        min-height: 100%;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.8rem;
        padding: 1.45rem;
        border: 1px solid rgba(19, 41, 75, 0.08);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 16px 40px rgba(19, 41, 75, 0.08);
        color: var(--admin-text);
        text-align: left;
      }

      .settings-section-card:hover {
        border-color: rgba(29, 111, 141, 0.24);
        box-shadow: 0 20px 42px rgba(19, 41, 75, 0.12);
      }

      .settings-section-card__eyebrow {
        margin: 0;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--admin-primary);
      }

      .settings-section-card__title {
        margin: 0;
        font-size: 1.08rem;
        color: var(--admin-primary-dark);
      }

      .settings-section-card__summary {
        margin: 0;
        color: var(--admin-muted);
        line-height: 1.55;
      }

      .settings-section-card__cta {
        margin-top: auto;
        font-weight: 700;
        color: var(--admin-primary);
      }

      .settings-dialog {
        width: min(780px, calc(100vw - 2rem));
      }

      .settings-dialog__form {
        display: contents;
      }

      .settings-dialog__body {
        display: grid;
        gap: 1rem;
      }

      .settings-dialog__grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      }

      .settings-dialog__grid .field,
      .settings-dialog__body .field {
        margin-bottom: 0;
      }

      .settings-dialog__summary {
        margin: 0;
        color: var(--admin-muted);
        line-height: 1.6;
      }

      .settings-dialog__list {
        margin: 0;
        padding-left: 1.15rem;
        display: grid;
        gap: 0.7rem;
      }

      .settings-services {
        display: grid;
        gap: 0.85rem;
        padding: 1rem;
        border: 1px solid var(--admin-border);
        border-radius: 18px;
        background: rgba(29, 111, 141, 0.04);
      }

      .settings-services__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
      }

      .settings-services__label {
        display: block;
        margin: 0;
        font-weight: 700;
        color: var(--admin-primary-dark);
      }

      .settings-services__summary {
        margin: 0.35rem 0 0;
        color: var(--admin-muted);
        line-height: 1.55;
      }

      .settings-services__add {
        flex: 0 0 auto;
      }

      .settings-services__list {
        display: grid;
        gap: 0.75rem;
      }

      .settings-service-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem;
        align-items: center;
      }

      .settings-service-row input {
        margin: 0;
      }

      .settings-service-row__remove[disabled] {
        opacity: 0.5;
        cursor: not-allowed;
      }

      .admin-form-grid-2 {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      }

      .admin-form-grid-3 {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      }

      .admin-form-span-2 {
        grid-column: 1 / -1;
      }

      .field-compact {
        max-width: 16rem;
      }

      .table-shell {
        overflow-x: auto;
      }

      .admin-table {
        width: 100%;
        border-collapse: collapse;
      }

      .admin-table th,
      .admin-table td {
        padding: 0.9rem 0.8rem;
        border-bottom: 1px solid var(--admin-border);
        text-align: left;
        vertical-align: top;
      }

      .admin-table th {
        font-size: 0.82rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--admin-muted);
      }

      .admin-private-members-card {
        padding: 1.25rem;
      }

      .admin-private-members-intro-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .admin-private-members-intro-header h2 {
        margin-top: 0.65rem;
      }

      .admin-private-members-login-link {
        flex: 0 0 auto;
        min-height: 2.35rem;
        padding: 0.56rem 0.8rem;
        border-radius: 10px;
        font-size: 0.9rem;
        white-space: nowrap;
      }

      .admin-main-wide .admin-private-members-card {
        width: 100%;
      }

      .admin-private-members-count {
        margin: 0 0 1rem;
        color: var(--admin-muted);
      }

      .admin-private-members-table-shell {
        width: 100%;
        overflow-x: visible;
      }

      .admin-private-members-table {
        width: 100%;
        min-width: 0;
        table-layout: fixed;
        font-size: 0.9rem;
      }

      .admin-private-members-table th,
      .admin-private-members-table td {
        padding: 0.55rem 0.65rem;
        vertical-align: middle;
      }

      .admin-private-members-table th:nth-child(1) {
        width: 19%;
      }

      .admin-private-members-table th:nth-child(2) {
        width: 7%;
      }

      .admin-private-members-table th:nth-child(3) {
        width: 33%;
      }

      .admin-private-members-table th:nth-child(4) {
        width: 11%;
      }

      .admin-private-members-table th:nth-child(5) {
        width: 12%;
      }

      .admin-private-members-table th:nth-child(6) {
        width: 18%;
      }

      .admin-private-members-email {
        word-break: break-word;
        line-height: 1.35;
      }

      .admin-private-members-table tr[id] {
        scroll-margin-top: 1rem;
      }

      .admin-private-members-status,
      .admin-private-members-module-state {
        display: inline-flex;
        align-items: center;
        min-height: 1.45rem;
        padding: 0.2rem 0.5rem;
        border-radius: 999px;
        background: rgba(19, 41, 75, 0.07);
        color: var(--admin-primary-dark);
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1;
      }

      .admin-private-members-status-active {
        background: rgba(13, 108, 48, 0.12);
        color: var(--admin-success);
      }

      .admin-private-members-status-suspended,
      .admin-private-members-status-disabled,
      .admin-private-members-module-state.is-inactive {
        background: rgba(161, 26, 42, 0.1);
        color: var(--admin-danger);
      }

      .admin-private-members-modules-form {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.45rem 0.6rem;
        margin: 0;
      }

      .admin-private-members-module-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        min-width: 0;
      }

      .admin-private-members-module-option {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        min-height: 1.9rem;
        padding: 0.25rem 0.45rem;
        border: 1px solid rgba(39, 75, 109, 0.16);
        border-radius: 8px;
        background: rgba(19, 41, 75, 0.03);
        color: var(--admin-text);
        font-size: 0.82rem;
        line-height: 1.15;
      }

      .admin-private-members-module-option input[type="checkbox"] {
        width: 0.95rem;
        height: 0.95rem;
        margin: 0;
      }

      .admin-private-members-module-label {
        max-width: 10rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }

      .admin-private-members-module-state {
        min-height: 1.2rem;
        padding: 0.15rem 0.4rem;
        font-size: 0.68rem;
      }

      .admin-private-members-date {
        color: var(--admin-muted);
        font-size: 0.86rem;
        line-height: 1.35;
      }

      .admin-private-members-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
      }

      .admin-private-members-actions form {
        margin: 0;
      }

      .admin-private-members-actions button,
      .admin-private-members-modules-form button {
        min-height: 2rem;
        padding: 0.42rem 0.62rem;
        border-radius: 8px;
        font-size: 0.82rem;
        line-height: 1.1;
        white-space: nowrap;
      }

      .media-manager-breadcrumbs-card {
        margin-top: 1rem;
      }

      .media-manager-breadcrumbs {
        margin: 0;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      .media-manager-breadcrumbs a {
        text-decoration: none;
        color: var(--admin-primary);
        font-weight: 600;
      }

      .media-manager-filters-card {
        margin-top: 1.2rem;
      }

      .media-manager-filters-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        align-items: end;
      }

      .media-manager-filters-search {
        grid-column: span 2;
      }

      .media-manager-filters-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
        padding-bottom: 0;
      }

      .media-manager-top-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      }

      .media-manager-content-grid {
        margin-top: 1.2rem;
        grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
        align-items: start;
      }

      .media-manager-folder-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.7rem;
      }

      .media-manager-folder-item {
        display: grid;
        gap: 0.7rem;
        border: 1px solid var(--admin-border);
        border-radius: 12px;
        padding: 0.65rem 0.75rem;
        background: rgba(19, 41, 75, 0.02);
      }

      .media-manager-folder-head {
        min-width: 0;
      }

      .media-manager-folder-item a {
        display: grid;
        gap: 0.2rem;
        text-decoration: none;
      }

      .media-manager-folder-item span {
        color: var(--admin-muted);
        font-size: 0.82rem;
      }

      .media-manager-item-forms {
        display: grid;
        gap: 0.55rem;
      }

      .media-manager-inline-form {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin: 0;
      }

      .media-manager-inline-form input[type="text"],
      .media-manager-inline-form select {
        flex: 1 1 10rem;
        min-width: 8rem;
      }

      .media-manager-table td code {
        display: inline-block;
        max-width: 26rem;
        white-space: pre-wrap;
        word-break: break-all;
      }

      .media-manager-thumb {
        width: 110px;
        max-width: 100%;
        height: 68px;
        border-radius: 10px;
        border: 1px solid var(--admin-border);
        object-fit: cover;
        background: rgba(19, 41, 75, 0.05);
      }

      .media-manager-row-actions {
        display: grid;
        gap: 0.5rem;
      }

      .media-manager-row-actions form {
        margin: 0;
      }

      .translation-card,
      .nested-card {
        margin-top: 1.2rem;
      }

      .translation-card summary,
      .nested-card summary {
        cursor: pointer;
        list-style: none;
      }

      .translation-card summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        font-size: 1rem;
        margin-bottom: 1.2rem;
      }

      .nested-card {
        border: 1px solid var(--admin-border);
        border-radius: 12px;
        padding: 1rem;
        background: rgba(19, 41, 75, 0.02);
      }

      .nested-card + .nested-card {
        margin-top: 1rem;
      }

      .nested-card summary {
        font-weight: 600;
        color: var(--admin-primary-dark);
        margin-bottom: 1rem;
      }

      .page-editor-intro {
        position: sticky;
        top: 1rem;
        z-index: 40;
      }

      .page-editor-intro__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
      }

      .page-editor-intro__header h2 {
        margin: 0;
      }

      .page-editor-intro__description {
        margin-top: 1rem;
      }

      .page-editor-intro__actions {
        margin-top: 1rem;
      }

      .page-editor-intro__save {
        white-space: nowrap;
      }

      .shared-media-editor__list {
        display: grid;
        gap: 1rem;
        margin-top: 1rem;
      }

      .shared-media-row {
        margin-top: 0;
      }

      .checkbox-inline {
        display: inline-flex;
        align-items: center;
        gap: 0.55rem;
        min-height: 2.75rem;
      }

      .tile-editor-items,
      .page-tile-placement-list {
        display: grid;
        gap: 1rem;
        margin-top: 1rem;
      }

      .tile-editor-item,
      .page-tile-placement {
        margin-top: 0;
      }

      .tile-editor-overview,
      .tile-admin-catalog {
        align-items: start;
      }

      .tile-admin-group-card,
      .tile-group-preview-card {
        display: grid;
        gap: 0.9rem;
      }

      .tile-admin-group-card__actions {
        display: grid;
        gap: 0.55rem;
        grid-template-columns: 1fr;
        justify-items: stretch;
        align-self: start;
        min-width: 10rem;
      }

      .tile-admin-group-card__actions form,
      .tile-admin-group-card__duplicate-form {
        margin: 0;
        width: 100%;
      }

      .tile-admin-group-card__actions .button-link,
      .tile-admin-group-card__actions button {
        width: 100%;
      }

      .tile-admin-group-card__duplicate-button {
        border: 1px solid rgba(29, 111, 141, 0.18);
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.45);
      }

      .tile-admin-group-card__duplicate-button:hover,
      .tile-admin-group-card__duplicate-button:focus-visible {
        background: rgba(29, 111, 141, 0.16);
        color: var(--admin-primary-dark);
        outline: none;
      }

      .tile-admin-group-card h3,
      .tile-group-preview-card h3 {
        margin: 0;
      }

      .tile-admin-group-card__stats,
      .tile-editor-item__preview-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
      }

      .tile-editor-item__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        justify-content: flex-end;
      }

      .tile-group-preview-card__name {
        color: var(--admin-primary-dark);
      }

      .tile-group-preview-card__empty {
        margin: 0;
      }

      .tile-editor-item__preview-shell {
        display: grid;
        gap: 0.7rem;
        margin: 0.9rem 0 1rem;
      }

      .admin-tile-mosaic {
        --admin-tile-unit: 2.95rem;
        display: grid;
        grid-auto-flow: dense;
        grid-template-columns: repeat(auto-fit, minmax(var(--admin-tile-unit), var(--admin-tile-unit)));
        grid-auto-rows: var(--admin-tile-unit);
        gap: 0.45rem;
        justify-content: start;
        align-content: start;
      }

      .admin-tile-mosaic--editor {
        --admin-tile-unit: clamp(3.1rem, 8vw, 4.35rem);
      }

      .admin-tile-preview {
        position: relative;
        overflow: hidden;
        min-width: 0;
        color: #fff;
        background-image: var(--admin-tile-bg);
        background-repeat: no-repeat;
        background-position: center;
        background-size: 100% 100%;
        background-color: #1f64c8;
        box-shadow: 0 0.55rem 1.2rem rgba(19, 41, 75, 0.18);
        isolation: isolate;
      }

      .admin-tile-preview--small {
        grid-column: span 1;
        grid-row: span 1;
        min-height: var(--admin-tile-unit);
      }

      .admin-tile-preview--medium {
        grid-column: span 2;
        grid-row: span 2;
        min-height: calc(var(--admin-tile-unit) * 2);
      }

      .admin-tile-preview--large {
        grid-column: span 4;
        grid-row: span 4;
        min-height: calc(var(--admin-tile-unit) * 4);
      }

      .admin-tile-preview--rectangle {
        grid-column: span 4;
        grid-row: span 2;
        min-height: calc(var(--admin-tile-unit) * 2);
      }

      .admin-tile-preview__inner {
        position: relative;
        display: block;
        inline-size: 100%;
        block-size: 100%;
        min-block-size: 100%;
      }

      .admin-tile-preview__media {
        position: absolute;
        inset: 2.2rem 0.4rem 0.4rem;
        margin: 0;
        overflow: hidden;
        z-index: 1;
      }

      .admin-tile-preview--small .admin-tile-preview__media {
        inset: 1.5rem 0.25rem 0.25rem;
      }

      .admin-tile-preview--large .admin-tile-preview__media {
        inset: 2.8rem 0.5rem 0.5rem;
      }

      .admin-tile-preview--rectangle .admin-tile-preview__media {
        inset: 1.02rem 0.5rem 0.3rem;
      }

      .admin-tile-preview__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center center;
        display: block;
      }

      .admin-tile-preview--rectangle .admin-tile-preview__media img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        filter: brightness(1.08) contrast(1.03) saturate(1.02);
      }

      .admin-tile-preview__overlay {
        position: absolute;
        inset: 0;
        z-index: 2;
        background: linear-gradient(180deg, rgba(0, 0, 0, 0.28) 0%, rgba(0, 0, 0, 0.12) 34%, rgba(0, 0, 0, 0.48) 100%);
      }

      .admin-tile-preview__content {
        position: absolute;
        inset: 0;
        z-index: 3;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 0.42rem 0.48rem 0.58rem;
        overflow: hidden;
      }

      .admin-tile-preview__label,
      .admin-tile-preview__summary {
        display: block;
        text-shadow: 0 0.12rem 0.4rem rgba(0, 0, 0, 0.85);
      }

      .admin-tile-preview__label {
        font-family: 'Segoe UI Light', 'Segoe UI', sans-serif;
        font-size: clamp(0.82rem, 0.72rem + 0.24vw, 1.12rem);
        line-height: 1.02;
        inline-size: 100%;
        overflow-wrap: normal;
        word-break: keep-all;
        hyphens: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      .admin-tile-preview__summary {
        margin-top: auto;
        max-width: 82%;
        font-size: 0.74rem;
        line-height: 1.15;
        opacity: 0.94;
      }

      .admin-tile-preview--small .admin-tile-preview__content {
        padding: 0.25rem 0.3rem 0.32rem;
      }

      .admin-tile-preview--small .admin-tile-preview__label {
        font-size: 0.68rem;
      }

      .admin-tile-preview--small .admin-tile-preview__summary {
        display: none;
      }

      .admin-tile-preview--large .admin-tile-preview__label {
        font-size: clamp(1.18rem, 0.96rem + 0.55vw, 1.65rem);
      }

      .admin-tile-preview--rectangle .admin-tile-preview__label {
        max-width: 100%;
        font-size: clamp(0.86rem, 0.8rem + 0.2vw, 1rem);
        letter-spacing: -0.01em;
      }

      .admin-tile-preview--rectangle .admin-tile-preview__summary {
        max-width: 58%;
      }

      .admin-tile-preview--rectangle .admin-tile-preview__content {
        padding: 0.34rem 0.46rem 0.42rem;
      }

      .admin-tile-preview--rectangle.is-with-media .admin-tile-preview__overlay {
        background: linear-gradient(180deg, rgba(0, 0, 0, 0.22) 0%, rgba(0, 0, 0, 0) 42%, rgba(0, 0, 0, 0.18) 100%);
      }

      .admin-tile-preview:not(.is-with-media) .admin-tile-preview__media {
        display: none;
      }

      .admin-tile-preview:not(.is-with-media) .admin-tile-preview__overlay {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, rgba(0, 0, 0, 0.08) 100%);
      }

      .admin-tile-preview:not(.is-with-media) .admin-tile-preview__content {
        justify-content: flex-end;
      }

      .admin-tile-preview--color-blanc:not(.is-with-media),
      .admin-tile-preview--color-jaune:not(.is-with-media) {
        color: #08111f;
      }

      .admin-tile-preview--color-blanc:not(.is-with-media) .admin-tile-preview__label,
      .admin-tile-preview--color-blanc:not(.is-with-media) .admin-tile-preview__summary,
      .admin-tile-preview--color-jaune:not(.is-with-media) .admin-tile-preview__label,
      .admin-tile-preview--color-jaune:not(.is-with-media) .admin-tile-preview__summary {
        text-shadow: none;
      }

      .page-tile-placement__items {
        display: grid;
        gap: 0.85rem;
        margin-top: 1rem;
      }

      .shared-media-library-list {
        margin: 0.7rem 0 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.55rem;
      }

      .shared-media-library-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.65rem;
        border: 1px solid var(--admin-border);
        border-radius: 10px;
        padding: 0.55rem 0.65rem;
        background: #fff;
      }

      .shared-media-library-item code {
        white-space: pre-wrap;
        word-break: break-all;
      }

      .content-media-dialog__search {
        margin-bottom: 0.8rem;
      }

      .content-media-toolbar {
        margin-bottom: 0.7rem;
      }

      .content-media-favorites {
        margin-bottom: 0.7rem;
        flex-wrap: wrap;
      }

      .content-media-controls {
        margin-bottom: 0.9rem;
        border: 1px solid var(--admin-border);
        border-radius: 12px;
        padding: 0.8rem;
        background: rgba(19, 41, 75, 0.03);
      }

      .content-media-controls h4 {
        margin: 0 0 0.7rem;
        color: var(--admin-primary-dark);
      }

      .content-media-library {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 0.75rem;
      }

      @media (max-width: 900px) {
        .admin-tile-mosaic--editor {
          --admin-tile-unit: clamp(3rem, 12vw, 3.8rem);
        }
      }

      @media (max-width: 720px) {
        .admin-tile-mosaic {
          grid-template-columns: 1fr;
          grid-auto-rows: auto;
        }

        .admin-tile-preview--small,
        .admin-tile-preview--medium,
        .admin-tile-preview--large,
        .admin-tile-preview--rectangle {
          grid-column: auto;
          grid-row: auto;
        }
      }

      .content-media-item {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        min-height: 100%;
        border: 1px solid var(--admin-border);
        border-radius: 12px;
        padding: 0.65rem;
        background: #fff;
        box-shadow: 0 6px 18px rgba(19, 41, 75, 0.05);
      }

      .content-media-item[data-content-media-compliant="0"] {
        border-color: rgba(177, 39, 39, 0.34);
        background: rgba(177, 39, 39, 0.05);
      }

      .content-media-item__preview {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        overflow: hidden;
        background: rgba(19, 41, 75, 0.08);
        aspect-ratio: 16 / 9;
      }

      .content-media-item__preview img,
      .content-media-item__preview video {
        width: 100%;
        height: 100%;
        display: block;
        object-fit: cover;
        background: #101d32;
      }

      .content-media-item__meta {
        display: grid;
        gap: 0.25rem;
      }

      .content-media-item__meta strong {
        font-size: 0.93rem;
        color: var(--admin-primary-dark);
      }

      .content-media-item__meta code {
        font-size: 0.78rem;
        white-space: pre-wrap;
        word-break: break-all;
      }

      .content-media-item__meta small {
        color: var(--admin-muted);
      }

      .content-media-item__meta [data-content-media-policy-badge] {
        color: var(--admin-muted);
      }

      .content-media-item[data-content-media-compliant="0"] .content-media-item__meta [data-content-media-policy-badge] {
        color: #7f1d1d;
        font-weight: 600;
      }

      .content-media-audit__status {
        margin: 0.45rem 0 0;
      }

      .content-media-audit__results {
        margin: 0.5rem 0 0;
        padding-left: 1.1rem;
      }

      .content-media-audit__results li + li {
        margin-top: 0.3rem;
      }

      .content-media-item .actions-inline {
        margin-top: auto;
      }

      .content-media-dialog__empty {
        margin: 0.5rem 0 0;
      }

      .content-media-dialog__status {
        margin: 0.55rem 0 0;
      }

      .page-layout-plan {
        margin-bottom: 1.4rem;
        border: 1px solid rgba(29, 111, 141, 0.18);
        border-radius: 16px;
        padding: 1.2rem;
        background: linear-gradient(180deg, rgba(29, 111, 141, 0.05), rgba(255, 255, 255, 0.96));
      }

      .page-layout-plan__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .page-layout-plan__header h3,
      .region-editor-card__head h3 {
        margin: 0 0 0.35rem;
        font-size: 1rem;
        color: var(--admin-primary-dark);
      }

      .page-layout-plan__header p,
      .region-editor-card__head p {
        margin: 0;
        font-size: 0.9rem;
        color: var(--admin-muted);
      }

      .page-layout-plan__grid {
        display: grid;
        gap: 0.75rem;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        grid-template-areas:
          "hero hero hero"
          "intro aside aside"
          "body body body"
          "after after after"
          "left bottom right"
          "postscript postscript postscript"
          "footer footer footer";
      }

      .page-layout-plan__item {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        min-height: 6.5rem;
        width: 100%;
        padding: 0.9rem 1rem;
        border-radius: 14px;
        border: 1px solid rgba(19, 41, 75, 0.08);
        background: #fff;
        color: var(--admin-text);
        text-align: left;
        font: inherit;
        text-decoration: none;
        box-shadow: 0 10px 24px rgba(19, 41, 75, 0.06);
        transition: transform 0.15s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      }

      .page-layout-plan__item:hover {
        transform: translateY(-1px);
        border-color: rgba(29, 111, 141, 0.4);
        box-shadow: 0 14px 30px rgba(19, 41, 75, 0.1);
      }

      .page-layout-plan__item:focus-visible {
        outline: 3px solid rgba(29, 111, 141, 0.28);
        outline-offset: 2px;
      }

      .page-layout-plan__item strong {
        font-size: 1rem;
      }

      .page-layout-plan__eyebrow {
        font-size: 0.76rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--admin-primary);
      }

      .page-layout-plan__summary {
        font-size: 0.86rem;
        color: var(--admin-muted);
      }

      .page-layout-plan__meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.8rem;
        margin-top: auto;
        padding-top: 0.3rem;
      }

      .page-layout-plan__status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 700;
      }

      .page-layout-plan__status--filled {
        background: rgba(13, 108, 48, 0.12);
        color: var(--admin-success);
      }

      .page-layout-plan__status--empty {
        background: rgba(161, 26, 42, 0.12);
        color: var(--admin-danger);
      }

      .page-layout-plan__action {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--admin-primary);
      }

      .page-layout-plan__item--hero {
        grid-area: hero;
      }

      .page-layout-plan__item--intro {
        grid-area: intro;
      }

      .page-layout-plan__item--aside {
        grid-area: aside;
      }

      .page-layout-plan__item--body {
        grid-area: body;
      }

      .page-layout-plan__item--after {
        grid-area: after;
      }

      .page-layout-plan__item--left {
        grid-area: left;
      }

      .page-layout-plan__item--bottom {
        grid-area: bottom;
      }

      .page-layout-plan__item--right {
        grid-area: right;
      }

      .page-layout-plan__item--postscript {
        grid-area: postscript;
      }

      .page-layout-plan__item--footer {
        grid-area: footer;
      }

      .page-layout-plan__hint {
        margin: 0.9rem 0 0;
        font-size: 0.84rem;
        color: var(--admin-muted);
      }

      .structured-region-groups {
        display: grid;
        gap: 1rem;
      }

      .structured-region-dialogs {
        display: contents;
      }

      .region-editor-card {
        border: 1px solid var(--admin-border);
        border-radius: 14px;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.92);
        scroll-margin-top: 1.2rem;
      }

      .region-editor-card-secondary {
        background: rgba(19, 41, 75, 0.03);
      }

      .region-editor-card__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.85rem;
      }

      .region-slot-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.3rem 0.7rem;
        border-radius: 999px;
        background: rgba(29, 111, 141, 0.12);
        color: var(--admin-primary);
        font-size: 0.8rem;
        font-weight: 700;
        white-space: nowrap;
      }

      .editor-anchor:target {
        border-color: rgba(29, 111, 141, 0.45);
        box-shadow: 0 0 0 4px rgba(29, 111, 141, 0.12);
      }

      .region-modal {
        width: min(860px, calc(100vw - 2rem));
        max-width: 100%;
        max-height: calc(100vh - 2rem);
        padding: 0;
        border: 0;
        border-radius: 20px;
        background: transparent;
        box-shadow: 0 24px 60px rgba(19, 41, 75, 0.28);
      }

      .region-modal::backdrop {
        background: rgba(7, 19, 34, 0.48);
        backdrop-filter: blur(4px);
      }

      .region-modal__surface {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        max-height: calc(100vh - 2rem);
        padding: 1.3rem;
        border-radius: 20px;
        background: #fff;
      }

      .region-modal__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
      }

      .region-modal__eyebrow {
        margin: 0 0 0.35rem;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--admin-primary);
      }

      .region-modal__header h3 {
        margin: 0 0 0.3rem;
        font-size: 1.25rem;
        color: var(--admin-primary-dark);
      }

      .region-modal__header p {
        margin: 0;
        color: var(--admin-muted);
      }

      .region-modal__body {
        overflow: auto;
        padding-right: 0.25rem;
      }

      .region-callout-control {
        margin-top: 0.75rem;
        padding: 0.75rem;
        border: 1px solid rgba(212, 66, 132, 0.28);
        border-radius: 12px;
        background: rgba(212, 66, 132, 0.05);
      }

      .region-callout-control__status {
        margin: 0.45rem 0 0;
      }

      .region-modal__actions {
        margin-top: 0;
        padding-top: 0.5rem;
        border-top: 1px solid var(--admin-border);
      }

      .region-image-check {
        margin-top: 0.75rem;
        padding: 0.75rem;
        border: 1px solid var(--admin-border);
        border-radius: 12px;
        background: rgba(19, 41, 75, 0.03);
      }

      .region-image-check__status {
        margin: 0.45rem 0 0;
      }

      .region-image-check__results {
        margin: 0.7rem 0 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 0.45rem;
      }

      .region-image-check__item {
        display: grid;
        gap: 0.3rem;
        padding: 0.55rem 0.65rem;
        border-radius: 10px;
        border: 1px solid var(--admin-border);
        background: #fff;
      }

      .region-image-check__item.is-ok {
        border-color: rgba(13, 108, 48, 0.28);
      }

      .region-image-check__item.is-error {
        border-color: rgba(161, 26, 42, 0.28);
      }

      .region-image-check__badge {
        display: inline-flex;
        width: fit-content;
        padding: 0.16rem 0.5rem;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
      }

      .region-image-check__item.is-ok .region-image-check__badge {
        background: rgba(13, 108, 48, 0.12);
        color: var(--admin-success);
      }

      .region-image-check__item.is-error .region-image-check__badge {
        background: rgba(161, 26, 42, 0.12);
        color: var(--admin-danger);
      }

      .region-image-check__item code {
        white-space: pre-wrap;
        word-break: break-all;
      }

      .lang-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
      }

      .lang-badge,
      .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
      }

      .lang-badge {
        background: rgba(29, 111, 141, 0.12);
        color: var(--admin-primary);
      }

      .lang-badge-missing {
        background: rgba(161, 26, 42, 0.12);
        color: var(--admin-danger);
      }

      .status-draft {
        background: rgba(161, 26, 42, 0.12);
        color: var(--admin-danger);
      }

      .status-published {
        background: rgba(13, 108, 48, 0.12);
        color: var(--admin-success);
      }

      .status-scheduled {
        background: rgba(214, 146, 0, 0.16);
        color: #7f5500;
      }

      .status-pending {
        background: rgba(214, 146, 0, 0.16);
        color: #7f5500;
      }

      .status-approved {
        background: rgba(13, 108, 48, 0.12);
        color: var(--admin-success);
      }

      .status-rejected {
        background: rgba(161, 26, 42, 0.12);
        color: var(--admin-danger);
      }

      .intro {
        margin-bottom: 2rem;
        font-size: 0.95rem;
        color: #274b6d;
        text-align: center;
      }

      .hint {
        margin-top: 1.8rem;
        text-align: center;
        font-size: 0.8rem;
        color: rgba(39, 75, 109, 0.7);
      }

      .admin-session-warning {
        position: fixed;
        inset: 0;
        z-index: 1200;
        display: grid;
        place-items: center;
        background: rgba(8, 22, 41, 0.55);
        padding: 1rem;
      }

      .admin-session-warning[hidden] {
        display: none;
      }

      .admin-session-warning__surface {
        width: min(100%, 460px);
        background: #fff;
        border-radius: 16px;
        border: 1px solid var(--admin-border);
        box-shadow: var(--admin-shadow);
        padding: 1.3rem 1.3rem 1.1rem;
      }

      .admin-session-warning__title {
        margin: 0 0 0.45rem;
        color: var(--admin-primary-dark);
        font-size: 1.18rem;
      }

      .admin-session-warning__message {
        margin: 0 0 0.7rem;
        color: var(--admin-text);
      }

      .admin-session-warning__countdown {
        margin: 0 0 1rem;
        color: var(--admin-danger);
        font-weight: 700;
      }

      .admin-session-warning__actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.65rem;
      }

      @media (max-width: 840px) {
        nav.admin-nav {
          flex: 0 0 210px;
          width: 210px;
          padding: 2rem 1.4rem;
        }

        .admin-pages-filters-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .admin-pages-filters-search,
        .admin-pages-filters-actions {
          grid-column: 1 / -1;
        }

        .admin-pages-filters-actions {
          justify-content: flex-start;
          padding-bottom: 0;
        }

        .admin-logs-filters-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .admin-logs-filters-search,
        .admin-logs-filters-actions {
          grid-column: 1 / -1;
        }

        .admin-logs-filters-actions {
          justify-content: flex-start;
          padding-bottom: 0;
        }

        .admin-articles-filters-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .admin-articles-filters-actions {
          grid-column: 1 / -1;
          justify-content: flex-start;
          padding-bottom: 0;
        }

        .media-manager-content-grid {
          grid-template-columns: 1fr;
        }

        .media-manager-filters-grid {
          grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .media-manager-filters-search,
        .media-manager-filters-actions {
          grid-column: 1 / -1;
        }

        .media-manager-filters-actions {
          justify-content: flex-start;
        }
      }

      @media (max-width: 720px) {
        .admin-shell {
          flex-direction: column;
        }

        nav.admin-nav {
          flex: 1 1 auto;
          width: 100%;
          min-height: auto;
          max-height: none;
          position: static;
          overflow: visible;
          padding: 2rem 1.4rem;
        }

        .nav-menu {
          grid-auto-flow: row;
        }

        .admin-pages-filters-grid {
          grid-template-columns: 1fr;
        }

        .admin-pages-filters-search,
        .admin-pages-filters-actions {
          grid-column: auto;
        }

        .admin-logs-filters-grid {
          grid-template-columns: 1fr;
        }

        .admin-logs-filters-search,
        .admin-logs-filters-actions {
          grid-column: auto;
        }

        .admin-logs-filters-actions .button-link,
        .admin-logs-filters-actions button {
          flex-basis: 100%;
        }

        .admin-articles-filters-grid {
          grid-template-columns: 1fr;
        }

        .admin-articles-filters-actions {
          grid-column: auto;
        }

        .media-manager-filters-grid {
          grid-template-columns: 1fr;
        }

        .media-manager-filters-search,
        .media-manager-filters-actions {
          grid-column: auto;
        }

        .media-manager-item-forms,
        .media-manager-inline-form {
          width: 100%;
        }

        .settings-services__header,
        .settings-service-row {
          grid-template-columns: 1fr;
        }

        .page-layout-plan__header,
        .page-editor-intro__header,
        .region-editor-card__head,
        .region-modal__header {
          flex-direction: column;
        }

        .page-editor-intro__save {
          width: 100%;
        }

        .page-layout-plan__grid {
          grid-template-columns: 1fr;
          grid-template-areas:
            "hero"
            "intro"
            "aside"
            "body"
            "after"
            "left"
            "bottom"
            "right"
            "postscript"
            "footer";
        }

        .shared-media-library-item {
          flex-direction: column;
          align-items: flex-start;
        }

        .content-media-library {
          grid-template-columns: 1fr;
        }

        .content-media-toolbar,
        .content-media-controls .admin-form-grid-2,
        .content-media-controls .admin-form-grid-3 {
          grid-template-columns: 1fr;
        }

        .content-media-item__preview {
          aspect-ratio: 4 / 3;
        }

        .content-media-favorites {
          align-items: stretch;
        }

        .menu-builder-grid {
          grid-template-columns: 1fr;
        }

        .menu-builder-card__header,
        .menu-item-card__header,
        .menu-item-card__meta {
          flex-direction: column;
        }

        .region-modal {
          width: calc(100vw - 1rem);
          max-height: calc(100vh - 1rem);
        }

        .region-modal__surface {
          max-height: calc(100vh - 1rem);
          padding: 1rem;
        }
      }
    </style>
  </head>
  <body class="<?php echo $isLoginPage ? 'admin-login-page' : ''; ?>">
    <?php if ($isLoginPage): ?>
    <main class="admin-login-shell" aria-labelledby="admin-login-title">
      <?php require ROOT_PATH . '/templates/admin/' . $contentTemplate; ?>
    </main>
    <?php else: ?>
    <div class="admin-shell">
      <nav class="admin-nav" aria-label="<?php echo htmlspecialchars($translate('TXT_ADMIN_LAYOUT_NAV_ARIA', 'Navigation admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="nav-brand">
          <strong>Les Caramagnols</strong>
          <span><?php echo htmlspecialchars($translate('TXT_ADMIN_LAYOUT_BRAND_SUBTITLE', 'Administration technique'), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <ul class="nav-menu">
          <?php foreach ($adminMenu as $item): ?>
          <li>
            <a
              href="<?php echo htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8'); ?>"
              class="<?php echo ($activeMenu ?? '') === $item['id'] ? 'active' : ''; ?>"
            >
              <span aria-hidden="true"><?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
              <span><?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </nav>

      <div class="admin-content">
        <header class="admin-header">
          <div>
            <h1><?php echo htmlspecialchars((string) ($pageTitle ?? $translate('TXT_ADMIN_LAYOUT_DEFAULT_TITLE', 'Administration')), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="header-meta">
              <?php echo htmlspecialchars($translate('TXT_ADMIN_LAYOUT_SIGNED_IN_AS', 'Connecté en tant que'), ENT_QUOTES, 'UTF-8'); ?>
              <strong><?php echo htmlspecialchars((string) ($adminIdentifier ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
              · <?php echo htmlspecialchars($translate('TXT_ADMIN_LAYOUT_SESSION_OPENED_AT', 'session ouverte le'), ENT_QUOTES, 'UTF-8'); ?>
              <?php echo htmlspecialchars((string) ($formattedLogin ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </div>
          </div>
          <a class="logout" href="<?php echo htmlspecialchars((string) ($adminLogoutUrl ?? admin_url('logout')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($translate('TXT_ADMIN_LAYOUT_LOGOUT', 'Déconnexion'), ENT_QUOTES, 'UTF-8'); ?>
          </a>
        </header>

        <main class="admin-main <?php echo ($activeMenu ?? '') === 'private_members' ? 'admin-main-wide' : ''; ?>">
          <?php require ROOT_PATH . '/templates/admin/' . $contentTemplate; ?>
        </main>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!$isLoginPage): ?>
    <div class="admin-session-warning" id="admin-session-warning" role="dialog" aria-modal="true" aria-labelledby="admin-session-warning-title" aria-describedby="admin-session-warning-message" hidden>
      <div class="admin-session-warning__surface">
        <h2 class="admin-session-warning__title" id="admin-session-warning-title"><?php echo htmlspecialchars((string) ($adminSessionWarningTitle ?? $translate('TXT_ADMIN_LAYOUT_SESSION_TITLE', 'Session admin')), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="admin-session-warning__message" id="admin-session-warning-message"><?php echo htmlspecialchars((string) ($adminSessionWarningMessage ?? $translate('TXT_ADMIN_SESSION_WARNING_MESSAGE', 'Voulez-vous prolonger la session ?')), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="admin-session-warning__countdown" id="admin-session-warning-countdown"></p>
        <div class="admin-session-warning__actions">
          <button class="button-muted button-small" type="button" id="admin-session-warning-logout"><?php echo htmlspecialchars((string) ($adminSessionWarningLogoutLabel ?? $translate('TXT_ADMIN_LAYOUT_NO', 'Non')), ENT_QUOTES, 'UTF-8'); ?></button>
          <button class="button-small" type="button" id="admin-session-warning-confirm"><?php echo htmlspecialchars((string) ($adminSessionWarningConfirmLabel ?? $translate('TXT_ADMIN_LAYOUT_YES', 'Oui')), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
    <script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
      (() => {
        const openers = document.querySelectorAll('[data-region-modal-open]');
        const dialogs = document.querySelectorAll('dialog.region-modal');
        if (openers.length === 0 && dialogs.length === 0) {
          return;
        }
        const activeTriggerByDialog = new Map();
        const focusWithoutScroll = (element) => {
          if (!(element instanceof HTMLElement)) {
            return;
          }

          try {
            element.focus({ preventScroll: true });
          } catch (error) {
            element.focus();
          }
        };
        const scrollModalTarget = (opener) => {
          if (!(opener instanceof HTMLElement)) {
            return;
          }

          const targetId = opener.getAttribute('data-region-modal-scroll-target');
          if (targetId === null || targetId === '') {
            return;
          }

          const target = document.getElementById(targetId);
          if (!(target instanceof HTMLElement)) {
            return;
          }

          window.requestAnimationFrame(() => {
            target.scrollIntoView({ block: 'start', inline: 'nearest' });
            focusWithoutScroll(target);
          });
        };

        const closeDialog = (dialog) => {
          if (!(dialog instanceof HTMLDialogElement)) {
            return;
          }

          if (dialog.open) {
            dialog.close();
          }
          const trigger = activeTriggerByDialog.get(dialog);
          focusWithoutScroll(trigger);
        };

        openers.forEach((opener, index) => {
          if (!(opener instanceof HTMLElement)) {
            return;
          }

          if (opener.id === '') {
            opener.id = `region-modal-trigger-${index + 1}`;
          }

          opener.addEventListener('click', () => {
            const dialogId = opener.getAttribute('data-region-modal-open');
            if (dialogId === null || dialogId === '') {
              return;
            }

            const dialog = document.getElementById(dialogId);
            if (!(dialog instanceof HTMLDialogElement)) {
              return;
            }

            activeTriggerByDialog.set(dialog, opener);
            dialog.showModal();

            const firstField = dialog.querySelector('input, textarea, select, button');
            focusWithoutScroll(firstField);
          });
        });

        document.querySelectorAll('[data-region-modal-autostart="true"]').forEach((opener) => {
          if (!(opener instanceof HTMLElement)) {
            return;
          }

          opener.click();
          scrollModalTarget(opener);
          opener.removeAttribute('data-region-modal-autostart');
          opener.removeAttribute('data-region-modal-scroll-target');
        });

        dialogs.forEach((dialog) => {
          const isStaticDialog = dialog.getAttribute('data-region-modal-static') === 'true';

          dialog.querySelectorAll('[data-region-modal-close]').forEach((button) => {
            if (!(button instanceof HTMLElement)) {
              return;
            }

            button.addEventListener('click', () => closeDialog(dialog));
          });

          dialog.addEventListener('cancel', (event) => {
            if (isStaticDialog) {
              event.preventDefault();
            }
          });

          dialog.addEventListener('click', (event) => {
            if (isStaticDialog || event.target !== dialog) {
              return;
            }

            closeDialog(dialog);
          });

          dialog.addEventListener('close', () => {
            const trigger = activeTriggerByDialog.get(dialog);
            focusWithoutScroll(trigger);
          });
        });
      })();
    </script>
    <?php if (!$isLoginPage): ?>
    <script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
      (() => {
        const warningRoot = document.getElementById('admin-session-warning');
        if (!(warningRoot instanceof HTMLElement)) {
          return;
        }

        const confirmButton = document.getElementById('admin-session-warning-confirm');
        const logoutButton = document.getElementById('admin-session-warning-logout');
        const countdownNode = document.getElementById('admin-session-warning-countdown');
        if (!(confirmButton instanceof HTMLButtonElement) || !(logoutButton instanceof HTMLButtonElement) || !(countdownNode instanceof HTMLElement)) {
          return;
        }

        const pingUrl = <?php echo json_encode((string) ($adminSessionPingUrl ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const logoutUrl = <?php echo json_encode((string) ($adminLogoutUrl ?? admin_url('logout')), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const csrfToken = <?php echo json_encode((string) ($csrfToken ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const timeoutSeconds = Math.max(60, Number(<?php echo json_encode((int) ($adminSessionTimeoutSeconds ?? 7200)); ?>) || 7200);
        const warningLeadSeconds = Math.min(timeoutSeconds, Math.max(30, Number(<?php echo json_encode((int) ($adminSessionWarningLeadSeconds ?? 120)); ?>) || 120));
        const decisionSeconds = Math.max(30, Number(<?php echo json_encode((int) ($adminSessionDecisionSeconds ?? 120)); ?>) || 120);
        const countdownTemplateRaw = <?php echo json_encode((string) ($adminSessionWarningCountdownTemplate ?? 'Déconnexion automatique dans %d secondes.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const countdownTemplate = countdownTemplateRaw.includes('%d')
          ? countdownTemplateRaw
          : `${countdownTemplateRaw} (%d)`;
        const networkErrorMessage = <?php echo json_encode((string) ($adminSessionWarningNetworkError ?? 'Session expirée. Merci de vous reconnecter.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        if (pingUrl === '' || logoutUrl === '' || csrfToken === '') {
          return;
        }

        let warningTimerId = null;
        let countdownIntervalId = null;
        let autoLogoutTimerId = null;
        let deadlineTimestamp = 0;

        const clearTimers = () => {
          if (warningTimerId !== null) {
            window.clearTimeout(warningTimerId);
            warningTimerId = null;
          }
          if (countdownIntervalId !== null) {
            window.clearInterval(countdownIntervalId);
            countdownIntervalId = null;
          }
          if (autoLogoutTimerId !== null) {
            window.clearTimeout(autoLogoutTimerId);
            autoLogoutTimerId = null;
          }
        };

        const applyCountdownText = (remainingSeconds) => {
          const safeSeconds = Math.max(0, remainingSeconds);
          countdownNode.textContent = countdownTemplate.replace('%d', String(safeSeconds));
        };

        const hideWarning = () => {
          warningRoot.hidden = true;
          clearTimers();
        };

        const logoutNow = () => {
          hideWarning();
          window.location.assign(logoutUrl);
        };

        const scheduleWarning = () => {
          const openAfterMs = Math.max(1000, (timeoutSeconds - warningLeadSeconds) * 1000);
          warningTimerId = window.setTimeout(() => {
            warningRoot.hidden = false;
            deadlineTimestamp = Date.now() + (decisionSeconds * 1000);
            applyCountdownText(decisionSeconds);
            countdownIntervalId = window.setInterval(() => {
              const remaining = Math.ceil((deadlineTimestamp - Date.now()) / 1000);
              applyCountdownText(remaining);
            }, 1000);
            autoLogoutTimerId = window.setTimeout(logoutNow, decisionSeconds * 1000);
            confirmButton.focus();
          }, openAfterMs);
        };

        const restartCycle = () => {
          hideWarning();
          scheduleWarning();
        };

        const extendSession = async () => {
          confirmButton.disabled = true;
          logoutButton.disabled = true;

          try {
            const body = new URLSearchParams();
            body.set('csrf_token', csrfToken);

            const response = await fetch(pingUrl, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
              },
              credentials: 'same-origin',
              body: body.toString(),
            });

            if (!response.ok) {
              logoutNow();
              return;
            }

            const payload = await response.json().catch(() => null);
            if (!payload || payload.ok !== true) {
              logoutNow();
              return;
            }

            restartCycle();
          } catch (error) {
            window.alert(networkErrorMessage);
            logoutNow();
          } finally {
            confirmButton.disabled = false;
            logoutButton.disabled = false;
          }
        };

        confirmButton.addEventListener('click', extendSession);
        logoutButton.addEventListener('click', logoutNow);
        scheduleWarning();
      })();
    </script>
    <?php endif; ?>
  </body>
</html>
