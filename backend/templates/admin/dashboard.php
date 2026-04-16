<?php
$pageSummary = is_array($pageSummary ?? null) ? $pageSummary : [];
$articleSummary = is_array($articleSummary ?? null) ? $articleSummary : [];
$discussionSummary = is_array($discussionSummary ?? null) ? $discussionSummary : [];
$navigationSummary = is_array($navigationSummary ?? null) ? $navigationSummary : [];
$blogMode = trim((string) ($blogMode ?? ''));
$blogStorage = trim((string) ($blogStorage ?? ''));
$publishedContentCount = (int) ($publishedContentCount ?? 0);
$draftContentCount = (int) ($draftContentCount ?? 0);
$discussionsPending = (int) ($discussionSummary['pending'] ?? 0);
$discussionsTotal = (int) ($discussionSummary['total'] ?? 0);
$discussionsApproved = (int) ($discussionSummary['approved'] ?? 0);
$discussionsRejected = (int) ($discussionSummary['rejected'] ?? 0);
$discussionsUrl = (string) ($adminDiscussionsUrl ?? admin_url('discussions'));
$pendingDiscussionsUrl = $discussionsUrl . (str_contains($discussionsUrl, '?') ? '&' : '?') . 'status=pending';
$translate = static function (string $key, string $fallback): string {
    if (!function_exists('t')) {
        return $fallback;
    }

    $translated = t($key);
    if (!is_string($translated) || $translated === '' || $translated === '[[' . $key . ']]') {
        return $fallback;
    }

    return $translated;
};
$translateFormat = static function (string $key, string $fallback, mixed ...$args) use ($translate): string {
    return sprintf($translate($key, $fallback), ...$args);
};
$discussionNotice = $discussionsPending > 0
    ? $translateFormat(
        'TXT_ADMIN_DASHBOARD_PENDING_ALERT',
        'Priorité modération : %d message(s) en attente de validation.',
        $discussionsPending
    )
    : $translate(
        'TXT_ADMIN_DASHBOARD_CLEAR_ALERT',
        'Aucun message en attente. La modération est à jour.'
    );
?>

<section class="cards-grid dashboard-kpis" aria-labelledby="dashboard-overview">
  <article class="card dashboard-kpi-card" id="dashboard-overview" aria-live="polite">
    <span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_FOCUS_TAG', 'Priorité'), ENT_QUOTES, 'UTF-8'); ?></span>
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_FOCUS_TITLE', 'Modération des discussions'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="notice <?php echo $discussionsPending > 0 ? 'notice-error' : 'notice-success'; ?>">
      <?php echo htmlspecialchars($discussionNotice, ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <div class="cards-grid">
      <article class="card dashboard-kpi-card">
        <strong class="dashboard-kpi-value"><?php echo $discussionsPending; ?></strong>
        <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_PENDING_LABEL', 'En attente'), ENT_QUOTES, 'UTF-8'); ?></p>
      </article>
      <article class="card dashboard-kpi-card">
        <strong class="dashboard-kpi-value"><?php echo $discussionsTotal; ?></strong>
        <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_TOTAL_LABEL', 'Total'), ENT_QUOTES, 'UTF-8'); ?></p>
      </article>
      <article class="card dashboard-kpi-card">
        <strong class="dashboard-kpi-value"><?php echo $discussionsApproved; ?></strong>
        <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_APPROVED_LABEL', 'Approuvés'), ENT_QUOTES, 'UTF-8'); ?></p>
      </article>
      <article class="card dashboard-kpi-card">
        <strong class="dashboard-kpi-value"><?php echo $discussionsRejected; ?></strong>
        <p class="dashboard-kpi-label"><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_REJECTED_LABEL', 'Rejetés'), ENT_QUOTES, 'UTF-8'); ?></p>
      </article>
    </div>
    <p class="actions-inline dashboard-card-actions">
      <a class="button-link" href="<?php echo htmlspecialchars($pendingDiscussionsUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_REVIEW_PENDING', 'Traiter les messages en attente'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars($discussionsUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_OPEN_DISCUSSIONS', 'Ouvrir toutes les discussions'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
    </p>
  </article>

  <article class="card">
    <span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_CONTENT_TAG', 'Éditorial'), ENT_QUOTES, 'UTF-8'); ?></span>
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_CONTENT_TITLE', 'Éléments clés de contenu'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul>
      <li>
        <?php echo htmlspecialchars($translateFormat(
            'TXT_ADMIN_DASHBOARD_CONTENT_PAGES',
            'Pages : %d (%d publiées / %d brouillons).',
            (int) ($pageSummary['total'] ?? 0),
            (int) ($pageSummary['published'] ?? 0),
            (int) ($pageSummary['drafts'] ?? 0)
        ), ENT_QUOTES, 'UTF-8'); ?>
      </li>
      <li>
        <?php echo htmlspecialchars($translateFormat(
            'TXT_ADMIN_DASHBOARD_CONTENT_ARTICLES',
            'Articles : %d (%d publiés / %d brouillons).',
            (int) ($articleSummary['total'] ?? 0),
            (int) ($articleSummary['published'] ?? 0),
            (int) ($articleSummary['drafts'] ?? 0)
        ), ENT_QUOTES, 'UTF-8'); ?>
      </li>
      <li><?php echo htmlspecialchars($translateFormat('TXT_ADMIN_DASHBOARD_CONTENT_DRAFTS', 'Brouillons à traiter : %d.', $draftContentCount), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><?php echo htmlspecialchars($translateFormat('TXT_ADMIN_DASHBOARD_CONTENT_PUBLISHED', 'Contenus publiés : %d.', $publishedContentCount), ENT_QUOTES, 'UTF-8'); ?></li>
    </ul>
    <p class="actions-inline dashboard-card-actions">
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($adminPagesUrl ?? admin_url('pages')), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_OPEN_PAGES', 'Gérer les pages'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($adminArticlesUrl ?? admin_url('articles')), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_OPEN_ARTICLES', 'Gérer les articles'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
    </p>
  </article>

  <article class="card">
    <span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_ADMIN_TAG', 'Pilotage'), ENT_QUOTES, 'UTF-8'); ?></span>
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_ADMIN_TITLE', 'Éléments clés d’administration'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul>
      <li><?php echo htmlspecialchars($translateFormat('TXT_ADMIN_DASHBOARD_ADMIN_MENU', 'Menus : %d entrées.', (int) ($navigationSummary['totalItems'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><?php echo htmlspecialchars($translateFormat('TXT_ADMIN_DASHBOARD_ADMIN_LOCATIONS', 'Navigation configurée : %d emplacement(s) sur %d.', (int) ($navigationSummary['configuredLocations'] ?? 0), (int) ($navigationSummary['totalLocations'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><?php echo htmlspecialchars($translateFormat('TXT_ADMIN_DASHBOARD_ADMIN_BLOG_MODE', 'Mode blog : %s.', $blogMode !== '' ? $blogMode : '-'), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><?php echo htmlspecialchars($translateFormat('TXT_ADMIN_DASHBOARD_ADMIN_BLOG_STORAGE', 'Stockage blog : %s.', $blogStorage !== '' ? $blogStorage : '-'), ENT_QUOTES, 'UTF-8'); ?></li>
    </ul>
    <p class="actions-inline dashboard-card-actions">
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($adminMenusUrl ?? admin_url('menus')), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_OPEN_MENUS', 'Gérer les menus'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($adminSettingsUrl ?? admin_url('settings')), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_OPEN_SETTINGS', 'Paramètres'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($adminLogsUrl ?? admin_url('logs')), ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($translate('TXT_ADMIN_DASHBOARD_OPEN_LOGS', 'Consulter les logs'), ENT_QUOTES, 'UTF-8'); ?>
      </a>
    </p>
  </article>
</section>
