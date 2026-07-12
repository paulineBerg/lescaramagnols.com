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
$statusLabels = [
    'draft' => $translate('TXT_ADMIN_ARTICLE_STATUS_DRAFT', 'Brouillon'),
    'published' => $translate('TXT_ADMIN_ARTICLE_STATUS_PUBLISHED', 'Publié'),
];
$pages = is_array($pages ?? null) ? $pages : [];
$draftCount = count(array_filter($pages, static fn (array $page): bool => ($page['status'] ?? '') === 'draft'));
$publishedCount = count(array_filter($pages, static fn (array $page): bool => ($page['status'] ?? '') === 'published'));
$currentStatusFilter = is_string($statusFilter ?? null) ? trim((string) $statusFilter) : '';
$currentLanguageFilter = is_string($languageFilter ?? null) ? trim((string) $languageFilter) : '';
$currentSearchQuery = is_string($searchQuery ?? null) ? trim((string) $searchQuery) : '';
?>

<section class="cards-grid">
  <article class="card">
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_PAGES_REGISTRY_TITLE', 'Registre éditorial'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p>
      <?php echo htmlspecialchars($translate('TXT_ADMIN_PAGES_REGISTRY_BODY', 'Le registre éditorial pilote les pages structurées. Selon la configuration, il est servi depuis JSON, SQL ou double écriture. Le workflow brouillon / publié est porté ici.'), ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <p class="actions">
      <a class="button-link" href="<?php echo htmlspecialchars((string) ($createPageUrl ?? $adminPageCreateUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_PAGES_CREATE', 'Créer une page'), ENT_QUOTES, 'UTF-8'); ?></a>
    </p>
  </article>

  <article class="card">
    <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_QUICK_VIEW', 'Vue rapide'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul>
      <li><span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_NAV_PAGES', 'Pages'), ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_PAGES_VISIBLE_COUNT', '%d entrées visibles avec les filtres courants.'), count($pages)), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_STATUS_PUBLISHED', 'Publié'), ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_PAGES_PUBLISHED_COUNT', '%d page(s).'), $publishedCount), ENT_QUOTES, 'UTF-8'); ?></li>
      <li><span class="tag"><?php echo htmlspecialchars($translate('TXT_ADMIN_ARTICLE_STATUS_DRAFT', 'Brouillon'), ENT_QUOTES, 'UTF-8'); ?></span> <?php echo htmlspecialchars(sprintf($translate('TXT_ADMIN_PAGES_DRAFT_COUNT', '%d page(s).'), $draftCount), ENT_QUOTES, 'UTF-8'); ?></li>
    </ul>
  </article>
</section>

<?php if (($message ?? null) !== null): ?>
<div class="notice notice-success"><?php echo htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<section class="card admin-pages-filters-card">
  <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_FILTERS', 'Filtres'), ENT_QUOTES, 'UTF-8'); ?></h2>

  <form class="admin-form-grid admin-pages-filters-grid" method="get" action="<?php echo htmlspecialchars((string) ($adminPagesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="field admin-pages-filters-search">
      <label for="pages-q"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_SEARCH', 'Recherche'), ENT_QUOTES, 'UTF-8'); ?></label>
      <input id="pages-q" name="q" type="text" value="<?php echo htmlspecialchars((string) ($searchQuery ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($translate('TXT_ADMIN_PAGES_SEARCH_PLACEHOLDER', 'slug, titre, route'), ENT_QUOTES, 'UTF-8'); ?>" />
    </div>

    <div class="field">
      <label for="pages-status"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></label>
      <select id="pages-status" name="status">
        <option value=""><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ALL', 'Tous'), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach (($supportedStatuses ?? []) as $status): ?>
        <option value="<?php echo htmlspecialchars((string) $status, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($statusFilter ?? '') === $status ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars($statusLabels[$status] ?? (string) $status, ENT_QUOTES, 'UTF-8'); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="pages-lang"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_LANGUAGE', 'Langue'), ENT_QUOTES, 'UTF-8'); ?></label>
      <select id="pages-lang" name="lang">
        <option value=""><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ALL_FEMININE', 'Toutes'), ENT_QUOTES, 'UTF-8'); ?></option>
        <?php foreach (($availableLanguages ?? []) as $language): ?>
        <option value="<?php echo htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($languageFilter ?? '') === $language ? ' selected' : ''; ?>>
          <?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="actions-inline admin-pages-filters-actions">
      <a class="button-link button-link-muted" href="<?php echo htmlspecialchars((string) ($pagesResetUrl ?? $adminPagesUrl ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_RESET', 'Réinitialiser'), ENT_QUOTES, 'UTF-8'); ?></a>
      <button type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_FILTER', 'Filtrer'), ENT_QUOTES, 'UTF-8'); ?></button>
    </div>
  </form>
</section>

<section class="card">
  <h2><?php echo htmlspecialchars($translate('TXT_ADMIN_NAV_PAGES', 'Pages'), ENT_QUOTES, 'UTF-8'); ?></h2>

  <?php if ($pages === []): ?>
  <p class="notice-muted"><?php echo htmlspecialchars($translate('TXT_ADMIN_PAGES_NO_RESULTS', 'Aucune page ne correspond aux filtres courants.'), ENT_QUOTES, 'UTF-8'); ?></p>
  <?php else: ?>
  <div class="table-shell">
    <table class="admin-table">
      <thead>
        <tr>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_SLUG', 'Slug'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_TITLE', 'Titre'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_STATUS', 'Statut'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_LANGUAGES', 'Langues'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ROUTE', 'Route'), ENT_QUOTES, 'UTF-8'); ?></th>
          <th><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_ACTION', 'Action'), ENT_QUOTES, 'UTF-8'); ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pages as $page): ?>
        <?php
        $pageSlug = (string) ($page['slug'] ?? '');
        $languages = is_array($page['languages'] ?? null) ? $page['languages'] : [];
        $missingLanguages = is_array($page['missingLanguages'] ?? null) ? $page['missingLanguages'] : [];
        ?>
        <tr>
          <td><code><?php echo htmlspecialchars($pageSlug, ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td><?php echo htmlspecialchars((string) ($page['title'] ?? $pageSlug), ENT_QUOTES, 'UTF-8'); ?></td>
          <td>
            <span class="status-pill status-<?php echo htmlspecialchars((string) ($page['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($statusLabels[(string) ($page['status'] ?? '')] ?? (string) ($page['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </td>
          <td>
            <div class="lang-badges">
              <?php foreach ($languages as $language): ?>
              <span class="lang-badge"><?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?></span>
              <?php endforeach; ?>
              <?php foreach ($missingLanguages as $language): ?>
              <span class="lang-badge lang-badge-missing"><?php echo strtoupper(htmlspecialchars((string) $language, ENT_QUOTES, 'UTF-8')); ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td><code><?php echo htmlspecialchars((string) ($page['route'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
          <td>
            <?php $pageEditPath = admin_url('pages') . '/' . rawurlencode($pageSlug); ?>
            <div class="actions-inline admin-pages-row-actions">
              <a class="button-link admin-pages-row-action" href="<?php echo htmlspecialchars($pageEditPath, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_EDIT', 'Éditer'), ENT_QUOTES, 'UTF-8'); ?></a>
              <?php
              $deleteWarningTemplate = function_exists('admin_translate')
                  ? admin_translate('TXT_ADMIN_PAGES_DELETE_WARNING_TEMPLATE')
                  : (function_exists('t') ? (string) t('TXT_ADMIN_PAGES_DELETE_WARNING_TEMPLATE') : '');
              if ($deleteWarningTemplate === '' || $deleteWarningTemplate === '[[TXT_ADMIN_PAGES_DELETE_WARNING_TEMPLATE]]') {
                  $deleteWarningTemplate = "ATTENTION : suppression definitive de la page \"%s\".\nToutes les traductions associees seront supprimees.";
              }

              $deleteWarning = sprintf($deleteWarningTemplate, $pageSlug);
              ?>
              <form class="admin-pages-row-action-form" method="post" action="<?php echo htmlspecialchars($pageEditPath, ENT_QUOTES, 'UTF-8'); ?>" data-page-delete-form data-delete-warning="<?php echo htmlspecialchars($deleteWarning, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="page_action" value="delete" />
                <input type="hidden" name="confirm_delete" value="1" />
                <?php if ($currentStatusFilter !== ''): ?>
                <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($currentStatusFilter, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <?php if ($currentLanguageFilter !== ''): ?>
                <input type="hidden" name="return_lang" value="<?php echo htmlspecialchars($currentLanguageFilter, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <?php if ($currentSearchQuery !== ''): ?>
                <input type="hidden" name="return_q" value="<?php echo htmlspecialchars($currentSearchQuery, ENT_QUOTES, 'UTF-8'); ?>" />
                <?php endif; ?>
                <button class="button-danger admin-pages-row-action" type="submit"><?php echo htmlspecialchars($translate('TXT_ADMIN_COMMON_DELETE', 'Supprimer'), ENT_QUOTES, 'UTF-8'); ?></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?php $cspNonce = (string) ($GLOBALS['csp_nonce'] ?? ''); ?>
<script<?php echo $cspNonce !== '' ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
(function () {
  function confirmPageDelete(form) {
    if (!(form instanceof HTMLFormElement)) {
      return false;
    }

    const warning = form.getAttribute('data-delete-warning') || <?php echo json_encode($translate('TXT_ADMIN_PAGES_DELETE_WARNING_FALLBACK', 'ATTENTION : suppression definitive.'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    return window.confirm(warning);
  }

  document.querySelectorAll('form[data-page-delete-form]').forEach(function (form) {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    form.addEventListener('submit', function (event) {
      if (!confirmPageDelete(form)) {
        event.preventDefault();
      }
    });
  });
})();
</script>
